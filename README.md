# bcast-control

A small Apache/PHP web control panel for a one-camera church broadcast
setup: start/stop a YouTube live stream, switch between a few fixed
"scenes" (live camera, a blank title card, a sacrament-time title card),
move a PTZ camera to saved presets or drive it manually, and (optionally)
power off/reboot the broadcast computer — all from a phone or tablet on
the local network.

The web panel (`www/`, `apache/`) doesn't capture or encode video itself
— it shells out (via a small, explicit `sudo` allowlist) to a GStreamer
NDI→RTMP relay that does. This repo includes that relay's own original
code (`relay/`) and a set of reliability scripts for running this
unattended on a remote, hard-to-physically-access machine (`ops/`) — see
each directory's own README.

## The relay backend

A minimal `gst-launch-1.0` pipeline that reads an NDI camera feed and
pushes straight to YouTube's RTMP ingest — no GUI, no compositor, nothing
running that isn't strictly needed. Its scripts, systemd units, and
install steps are in **`relay/`** — see `relay/README.md`. It depends on
one piece that genuinely isn't included here (an external, unmodified
Rust GStreamer plugin, plus NDI's own proprietary redistributable runtime
— both documented, with exact build steps, in `relay/README.md`).

`www/vars-template.php` already assumes this backend (the
`$set_scene`/`$start_stream`/`$stop_stream` variables call
`sudo systemctl start/stop ndi-relay.service` and a `set-scene.sh`
script) — copy it to `vars.php` and adjust the paths/IP for your setup.

## Install (Debian 13 / trixie)

These steps were worked out and verified on a real Debian 13 install.

### 1. Base packages

```bash
sudo apt update
sudo apt install -y apache2 php libapache2-mod-php \
  openssh-server curl git vim sudo \
  intel-media-va-driver vainfo
```

(`intel-media-va-driver` is Debian's name for this — Ubuntu calls it
`intel-media-va-driver-non-free`. Only relevant for hardware-accelerated
encode; not required by the relay itself.)

### 2. GStreamer relay dependencies

```bash
sudo apt install -y \
  gstreamer1.0-plugins-base gstreamer1.0-plugins-good \
  gstreamer1.0-plugins-bad gstreamer1.0-plugins-ugly \
  gstreamer1.0-libav gstreamer1.0-tools \
  libgstreamer1.0-dev libgstreamer-plugins-base1.0-dev \
  libgstreamer-plugins-bad1.0-dev \
  meson ninja-build pkg-config cargo rustc
```

Then follow `relay/README.md` for the rest (building the external NDI
GStreamer plugin, installing the NDI runtime, deploying `relay/`'s
scripts and systemd units).

Also consider `ops/README.md` — the hardware/gateway watchdogs and boot
notification scripts it documents are optional but recommended for any
unattended, remotely-managed install like this one.

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

```bash
sudo visudo -c -f /etc/sudoers.d/www-data-bcast && sudo chmod 440 /etc/sudoers.d/www-data-bcast
```

## Camera control API

PTZ movement/presets and camera settings (like night-mode brightness) are
driven via the camera's own HTTP CGI API, not anything GStreamer-specific:

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

The relay's preview thumbnail is written to `tmpfs` (`/run/...`), not
disk — on any box with a spinning HDD and limited RAM, a frame written
every few seconds forever is real wear on a disk over years of uptime,
and a small RAM cache costs nothing on modern RAM sizes.

## History

This repo is a fork of
[evade-ninja/bcast-control](https://github.com/evade-ninja/bcast-control),
which originally drove OBS Studio + obs-websocket instead of the
GStreamer relay above.
