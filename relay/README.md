# ndi-relay

A minimal GStreamer pipeline that reads a single NDI camera feed and
relays it straight to YouTube's RTMP ingest, plus a low-framerate JPEG
preview capture for the `bcast-control` web panel. No OBS, no GUI, no
compositor — built for older/low-RAM hardware where running a full
desktop app 24/7 for a single-camera relay job is overkill. This is what
`bcast-control`'s `vars.php` (Option A) expects and drives via `sudo`.

## What's here vs. what isn't

Everything in this directory is original code written for this project.
It is **not** self-contained, though — it depends on one external piece
that isn't vendored here:

- **`gst-plugin-ndi`** — the actual NDI GStreamer elements (`ndisrc`,
  `ndisrcdemux`) come from
  [teltek/gst-plugin-ndi](https://github.com/teltek/gst-plugin-ndi)
  (Rust, LGPL), used unmodified at commit `12656af` (2022-04-09, the last
  commit as of this writing). Build it yourself (below) rather than
  expecting a copy in this repo.
- **The NDI runtime library** (`libndi.so`) — proprietary, redistributed
  by NDI's own installer script, not something to vendor in a public repo.

## Install

### 1. Dependencies

```bash
sudo apt install -y \
  gstreamer1.0-plugins-base gstreamer1.0-plugins-good \
  gstreamer1.0-plugins-bad gstreamer1.0-plugins-ugly \
  gstreamer1.0-libav gstreamer1.0-tools \
  libgstreamer1.0-dev libgstreamer-plugins-base1.0-dev \
  libgstreamer-plugins-bad1.0-dev \
  meson ninja-build pkg-config cargo rustc
```

### 2. NDI runtime

```bash
wget https://raw.githubusercontent.com/DistroAV/DistroAV/refs/heads/master/CI/libndi-get.sh
chmod +x libndi-get.sh
yes | ./libndi-get.sh install
```

(This is the same NDI runtime installer used by the DistroAV OBS plugin —
it has no dependency on OBS itself, it just installs `libndi.so` to
`/usr/local/lib/`.)

### 3. Build the NDI GStreamer plugin

```bash
git clone https://github.com/teltek/gst-plugin-ndi.git
cd gst-plugin-ndi
git checkout 12656af   # pin to a known-working commit; newer commits are untested here
cargo build --release
```

The built plugin ends up at `target/release/libgstndi.so`. Put it
somewhere stable and point `GST_PLUGIN_PATH` at that directory — the
scripts in `bin/` all assume `/opt/ndi-relay/gst-plugins/`:

```bash
sudo mkdir -p /opt/ndi-relay/gst-plugins
sudo cp target/release/libgstndi.so /opt/ndi-relay/gst-plugins/
```

Verify it's found:

```bash
GST_PLUGIN_PATH=/opt/ndi-relay/gst-plugins gst-inspect-1.0 ndisrc
```

### 4. Deploy this directory's files

```bash
sudo mkdir -p /opt/ndi-relay/bin /opt/ndi-relay/images /opt/ndi-relay/camera-info
sudo cp bin/*.sh bin/*.py /opt/ndi-relay/bin/
sudo chown root:root /opt/ndi-relay/bin/*
sudo chmod 755 /opt/ndi-relay/bin/*

sudo cp relay.env.template /opt/ndi-relay/relay.env
sudo chown broadcast:broadcast /opt/ndi-relay/relay.env   # or whichever user runs the service
sudo chmod 600 /opt/ndi-relay/relay.env
# edit /opt/ndi-relay/relay.env now: real camera IP, real YouTube stream key

sudo cp systemd/*.service /etc/systemd/system/
sudo cp tmpfiles.d/bcast-control.conf /etc/tmpfiles.d/
sudo systemd-tmpfiles --create
sudo systemctl daemon-reload
```

### 5. Blank/Sacrament placeholder images

`run-relay.sh`'s `Blank`/`Sacrament` scenes expect
`/opt/ndi-relay/images/blank.png` and `.../sacrament.png` (1280x720). Use
whatever branded title-card images you want — `bcast-control`'s own
`www/Blank.jpg`/`SacramentTime.jpg` are a reasonable source if you want
the local browser preview and the actual broadcast output to match:

```bash
ffmpeg -y -i /opt/bcast-control/www/Blank.jpg -vf scale=1280:720 /opt/ndi-relay/images/blank.png
ffmpeg -y -i /opt/bcast-control/www/SacramentTime.jpg -vf scale=1280:720 /opt/ndi-relay/images/sacrament.png
```

### 6. Start it

```bash
sudo systemctl enable --now ndi-relay.service     # the actual YouTube relay
sudo systemctl enable --now ndi-preview.service   # the web panel's preview thumbnail
```

`ndi-relay.service` defaults to `RELAY_MODE=Broadcast` (live camera) on
start if `relay-mode.env` doesn't exist yet — `bin/set-scene.sh` is what
the web panel calls to switch modes afterward.

## Known limitations (by design, not bugs)

- **Scene switches cause a brief RTMP reconnect.** `set-scene.sh` fully
  restarts `ndi-relay.service` to switch between the live camera and a
  static placeholder image — there's no in-process seamless scene switch.
  Expect a few seconds of "reconnecting"/frozen frame on YouTube's end
  every time the scene changes. Revisit this pipeline if seamless
  switching is ever required (would need e.g. `input-selector` with both
  branches always running).
- **`ndi-preview.service` has a known startup race**: it can settle into
  `active (running)` while never actually producing a frame, if the
  camera isn't reachable yet the instant the service starts. Systemd's
  `Restart=on-failure` does not catch this (the process never exits). See
  `../ops/ndi-preview-check/` for the cron-driven self-heal for this.
- **`run-preview.sh`'s `multifilesink` overwrites its output file in
  place** (not an atomic rename) — a web request can very rarely read a
  partially-written JPEG. Self-heals on the next ~3s poll; not worth
  fixing for a best-effort thumbnail.
