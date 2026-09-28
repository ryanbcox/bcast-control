# bcast-control

A small Apache/PHP web control panel for a one-camera church broadcast
setup: start/stop a YouTube live stream, switch between a few fixed
"scenes" (live camera, a blank title card, a sacrament-time title card),
move a PTZ camera to saved presets or drive it manually, and (optionally)
power off/reboot the broadcast computer — all from a phone or tablet on
the local network.

The web panel (`www/`, `python/`, `apache/`) doesn't capture or encode
video itself — it shells out (via a small, explicit `sudo` allowlist) to
whichever backend is actually doing that. See "Backend: two options"
below. This repo also includes the original code for the recommended
backend (`relay/`) and a set of reliability scripts for running this
unattended on a remote, hard-to-physically-access machine (`ops/`) — see
each directory's own README.

## Backend: two options

The panel needs something running underneath it that can start/stop a
stream, switch scenes, and answer status queries. Pick one:

### Option A (recommended on modest/older hardware): a GStreamer NDI→RTMP relay

A minimal `gst-launch-1.0` pipeline that reads an NDI camera feed and
pushes straight to YouTube's RTMP ingest — no GUI, no compositor, nothing
running that isn't strictly needed. Its own scripts/systemd
units/install steps are in **`relay/`** in this repo — see
`relay/README.md`. It depends on one piece that genuinely isn't included
here (an external, unmodified Rust GStreamer plugin, plus NDI's own
proprietary redistributable runtime — both documented, with exact build
steps, in `relay/README.md`). See `CHANGES.md` for why this option exists
and what it replaced.

If you're standing up this option, `www/vars-template.php` already
assumes it (the `$set_scene`/`$start_stream`/`$stop_stream` variables
call `sudo systemctl start/stop ndi-relay.service` and a
`set-scene.sh` script) — copy it to `vars.php` and adjust the paths/IP
for your setup.

### Option B (original design): OBS Studio + obs-websocket

The original design for this project: OBS Studio running headless,
driven by the Python scripts in `python/` over `obs-websocket`. Works,
but OBS is a full compositor/GUI application and is noticeably heavier
than Option A — fine on a machine with a few GB of spare RAM to give it,
overkill on very old/low-power hardware. `python/config.sample` and
`www/vars-template.php`'s obs-websocket-oriented variant (see this
repo's git history from before the relay cutover, or just replace the
relay lines with `python/obs-*.py` calls) are what this path needs.

## Install (Debian 13 / trixie)

These steps were worked out and verified on a real Debian 13 install —
**this project's docs originally targeted Ubuntu, but Ubuntu-specific
steps (PPAs, `libmfx1`, `intel-media-va-driver-non-free`) don't apply on
Debian** and are called out below where they differ. If you're actually
on Ubuntu, the equivalent Ubuntu package names are noted inline.

### 1. Base packages

```bash
sudo apt update
sudo apt install -y apache2 php libapache2-mod-php \
  python3-pip python3-venv openssh-server curl git vim sudo \
  intel-media-va-driver vainfo
```

- Debian: `intel-media-va-driver` (there is no `-non-free` variant on a
  system that only has `non-free-firmware` enabled, not full `non-free`).
  Ubuntu: `intel-media-va-driver-non-free`.
- `libmfx1` (Ubuntu) doesn't exist on trixie; the equivalent is
  `libmfx-gen1.2` if you need it (only relevant for hardware-accelerated
  encode, not required for either backend option above).

### 2a. If using Option A (GStreamer relay)

```bash
sudo apt install -y \
  gstreamer1.0-plugins-base gstreamer1.0-plugins-good \
  gstreamer1.0-plugins-bad gstreamer1.0-plugins-ugly \
  gstreamer1.0-libav gstreamer1.0-tools \
  libgstreamer1.0-dev libgstreamer-plugins-base1.0-dev \
  libgstreamer-plugins-bad1.0-dev \
  meson ninja-build pkg-config cargo rustc
```

Then follow `relay/README.md` in this repo for the rest (building the
external NDI GStreamer plugin, installing the NDI runtime, deploying
`relay/`'s scripts and systemd units).

Also consider `ops/README.md` — the hardware/gateway watchdogs and boot
notification scripts it documents are optional but recommended for any
unattended, remotely-managed install like this one.

### 2b. If using Option B (OBS)

```bash
sudo apt install -y obs-studio ffmpeg tigervnc-standalone-server icewm
pip3 install obs-websocket-py --break-system-packages
sudo -u www-data pip3 install obs-websocket-py --break-system-packages
```

- Debian trixie ships OBS 30.2.3 and ffmpeg 7.1.5 in the standard repos —
  **no PPA needed** (skip the README's old
  `add-apt-repository ppa:obsproject/obs-studio` /
  `ppa:ubuntuhandbook1/ffmpeg-7` steps entirely; `add-apt-repository`
  isn't even installed by default on Debian).
- There's no display/autologin on a headless box, so OBS runs inside a
  loopback-only TigerVNC session (`tigervnc-standalone-server` + `icewm`,
  a lightweight window manager — not a full desktop environment) driven
  by a systemd unit, instead of the "autologin + X at boot" approach an
  installed-desktop system might use. `scripts/run_obs.sh` /
  `scripts/run_capture.sh` are meant to be launched from the VNC
  session's `xstartup`, in a restart loop.
- OBS's obs-websocket plugin is bundled but **disabled by default** —
  turn it on in OBS's WebSocket Server Settings (Tools menu) and note
  the password for `python/config.json`.
- If you need NDI input/output in OBS for this path: NDI and the DistroAV
  plugin are **not packaged for Debian or Ubuntu** and need manual
  install. See the "NDI / DistroAV" section below — it's involved enough
  that it gets its own section regardless of which backend you pick,
  since Option A's relay also needs the NDI runtime.

### 3. Clone this repo and configure Apache

```bash
cd /opt
sudo git clone https://github.com/ryanbcox/bcast-control.git
sudo chown -R broadcast:broadcast /opt/bcast-control   # or whatever user will run things
```

```bash
sudo ln -s /opt/bcast-control/apache/bcast.conf /etc/apache2/sites-available/bcast.conf
sudo a2enmod ssl headers
sudo a2ensite bcast
sudo a2dissite 000-default
sudo systemctl restart apache2
```

`apache/bcast.conf` expects `<Directory />  Require all granted
</Directory>` for the document root and a TLS cert at whatever path you
set `SSLCertificateFile`/`SSLCertificateKeyFile` to — either a real
certificate or a self-signed one to get Apache started:

```bash
sudo openssl req -x509 -nodes -newkey rsa:2048 \
  -keyout /etc/ssl/private/yourhost.key \
  -out /etc/ssl/private/yourhost.crt -days 365 \
  -subj "/CN=yourhost.example.com"
```

If you want the panel's own basic-auth login (recommended — the panel has
no auth of its own otherwise), add to `bcast.conf`'s `<Directory />`
block:

```apache
AuthType Basic
AuthName "Broadcast Control"
AuthUserFile /etc/apache2/bcast.htpasswd
Require valid-user
```

```bash
sudo htpasswd -bc /etc/apache2/bcast.htpasswd someuser somepassword
sudo chown root:www-data /etc/apache2/bcast.htpasswd && sudo chmod 640 /etc/apache2/bcast.htpasswd
```

### 4. Configure the panel itself

```bash
cd /opt/bcast-control/www
cp vars-template.php vars.php      # edit: camera IP, sudo command paths
cp config-template.js config.js    # edit: list your camera's presets
```

Both `vars.php` and `config.js` are gitignored — they hold your real
camera IP/preset list and are meant to be filled in per install, not
committed.

If using Option B (OBS), also:

```bash
cd /opt/bcast-control/python
cp config.sample config.json       # edit: obs-websocket host/port/password, stream key
```

### 5. `sudo` allowlist for the web server

Apache (`www-data`) needs to run a small, specific set of privileged
commands (start/stop the stream, switch scenes, reboot/power off). Add an
exact-match sudoers file — **do not use wildcards or `NOPASSWD: ALL`**:

```
# /etc/sudoers.d/www-data-bcast  (validate with: visudo -c -f <path>)
www-data ALL=(root) NOPASSWD: /usr/bin/systemctl start ndi-relay.service
www-data ALL=(root) NOPASSWD: /usr/bin/systemctl stop ndi-relay.service
www-data ALL=(root) NOPASSWD: /opt/ndi-relay/bin/set-scene.sh Broadcast
www-data ALL=(root) NOPASSWD: /opt/ndi-relay/bin/set-scene.sh Sacrament
www-data ALL=(root) NOPASSWD: /opt/ndi-relay/bin/set-scene.sh Blank
www-data ALL=(root) NOPASSWD: /opt/ndi-relay/bin/set-night-mode.sh on
www-data ALL=(root) NOPASSWD: /opt/ndi-relay/bin/set-night-mode.sh off
www-data ALL=(root) NOPASSWD: /usr/sbin/poweroff
www-data ALL=(root) NOPASSWD: /usr/sbin/reboot
```

(Adjust the first five lines to match whatever your backend's actual
start/stop/scene-switch commands are if you're on Option B.)

```bash
sudo visudo -c -f /etc/sudoers.d/www-data-bcast && sudo chmod 440 /etc/sudoers.d/www-data-bcast
```

## NDI / DistroAV (only needed for OBS + NDI, Option B)

Neither the NDI runtime nor the DistroAV OBS plugin are packaged for
Debian or Ubuntu.

**NDI runtime**: the upstream helper script works fine as-is:

```bash
wget https://raw.githubusercontent.com/DistroAV/DistroAV/refs/heads/master/CI/libndi-get.sh
chmod +x libndi-get.sh
yes | ./libndi-get.sh install
```

**DistroAV plugin — prebuilt `.deb` releases do not work on Debian
trixie.** DistroAV 6.1.x/6.2.x require OBS ≥ 31 (Debian ships 30.2.3, so
they crash OBS at startup with a missing-symbol error), and even 6.0.0's
`.deb` fails to load (`undefined symbol: obs_module_author`) because it's
built against Ubuntu's Qt6/GCC toolchain, not Debian's. **Build DistroAV
6.0.0 from source against Debian's own `libobs-dev`** instead (this gets
exact ABI parity with Debian's OBS package for free):

```bash
sudo apt install -y libobs-dev cmake ninja-build qt6-base-dev libcurl4-openssl-dev

wget https://github.com/DistroAV/DistroAV/releases/download/6.0.0/distroav-6.0.0-source.tar.xz
tar xf distroav-6.0.0-source.tar.xz && cd distroav-6.0.0
mkdir build && cd build
cmake -G Ninja -DCMAKE_BUILD_TYPE=Release \
  -DCMAKE_CXX_FLAGS="-Wno-error=deprecated-declarations" ..
ninja

sudo cp distroav.so /usr/lib/x86_64-linux-gnu/obs-plugins/distroav.so
sudo cp -r ../data/locale /usr/share/obs/obs-plugins/distroav/
```

Use 6.0.0 specifically — newer releases hard-require OBS 31 in their own
version check and will refuse to load even if you get them to compile.
Both the `.so` and the `locale/` directory are required — without the
locale data the module fails to initialize with no useful error beyond
`Failed to initialize module 'distroav.so'`.

Verify success by checking OBS's actual per-run log file (not just
terminal output) at `~/.config/obs-studio/logs/<latest>.txt` for
`[DistroAV] obs_module_load: NDI library initialized successfully`.

## Camera control API

Both backend options drive PTZ movement/presets and camera settings (like
night-mode brightness) via the camera's own HTTP CGI API rather than
anything OBS/GStreamer-specific:

```
http://<camera-ip>/cgi-bin/ptzctrl.cgi?ptzcmd&poscall&<preset>   # recall a preset
http://<camera-ip>/cgi-bin/ptzctrl.cgi?ptzcmd&posset&<preset>    # save current position (destructive!)
http://<camera-ip>/cgi-bin/ptzctrl.cgi?ptzcmd&left&<speed>       # (also right/up/down/zoomin/zoomout/stop)
http://<camera-ip>/cgi-bin/param.cgi?post_image_value&bright&<0-14>
```

Valid preset numbers are camera-dependent (commonly 0-89 and 100-254 on
this family of PTZ cameras) — check your specific camera's CGI docs.
There's no "list configured presets" call on most of these cameras; the
only way to know what's stored at a preset is to recall it and look.

## What's in the web panel

- Scene buttons (live camera / blank / sacrament-time title card)
- Start/stop streaming
- A grid of camera preset buttons (configured in `config.js`), each with
  a live thumbnail, a number badge, and a green flash on press
- A collapsible manual PTZ control pad (directional + zoom, both tap for
  a short nudge and press-and-hold for continuous movement)
- A free-text "go to preset / save preset" box, with a confirmation
  before saving (saving overwrites whatever was stored there)
- A night-mode toggle (brightens the camera's own image setting, not a
  software filter, so it needs no stream restart)
- Remote power-off/reboot buttons, gated behind a day-of-month
  confirmation (both client- and server-side) so it's hard to trigger by
  accident
- A basic connection-health banner that distinguishes "this browser lost
  its own network" from "the broadcast computer stopped responding"

See `CHANGES.md` for the full history of what was added/changed and why.

## Ramdisk / cache note

If you're building the camera preview thumbnail path yourself (either
backend writes a JPEG somewhere that `www/cam.jpg` points at), prefer
`tmpfs` (`/run/...`) over disk on any box with a spinning HDD and limited
RAM — a frame written every few seconds forever is real wear on a disk
over years of uptime, and a small RAM cache costs nothing on modern RAM
sizes. On a genuinely RAM-constrained box (≤4GB), writing to disk instead
is a reasonable tradeoff — just don't use a large dedicated ramdisk
partition (e.g. the old `size=128m` tmpfs suggestion) if RAM is already
tight elsewhere.
