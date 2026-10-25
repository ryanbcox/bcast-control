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
sudo systemctl enable --now ndi-relay.service            # the actual YouTube relay
sudo systemctl enable --now ndi-preview-watchdog.service  # on-demand preview orchestrator (no NDI connection itself)
```

`ndi-relay.service` defaults to `RELAY_MODE=Broadcast` (live camera) on
start if `relay-mode.env` doesn't exist yet — `bin/set-scene.sh` is what
the web panel calls to switch modes afterward.

**`ndi-relay-preview.service` is deliberately not enabled/started here** —
`ndi-preview-watchdog.service` starts and stops it on demand (only while a
browser is actually fetching `www/preview.php`, and never while
`ndi-relay.service` is already live), per
`bin/preview-watchdog.sh`. `ndi-preview.service` (the old always-on
preview thumbnail capture) is kept in this repo only as historical
reference — it's superseded by the above and should stay disabled; don't
`systemctl enable` it on a fresh install.

## Known limitations (by design, not bugs)

- **Scene switches cause a brief RTMP reconnect.** `set-scene.sh` fully
  restarts `ndi-relay.service` to switch between the live camera and a
  static placeholder image — there's no in-process seamless scene switch.
  Expect a few seconds of "reconnecting"/frozen frame on YouTube's end
  every time the scene changes. Revisit this pipeline if seamless
  switching is ever required (would need e.g. `input-selector` with both
  branches always running).
- **The old `ndi-preview.service` has a known startup race** (it can settle
  into `active (running)` while never actually producing a frame, if the
  camera isn't reachable the instant the service starts), which is why
  `../ops/ndi-preview-check/`'s cron-driven self-heal existed. That cron
  job has since been **removed** from the live box, because once the
  on-demand preview model (`ndi-relay-preview.service` +
  `ndi-preview-watchdog.service`) shipped, the old script's core
  assumption — "`cam.jpg` going stale always means something's broken" —
  became false: a stale `cam.jpg` while nobody's viewing the page is the
  *correct*, by-design state now, not a fault. Left running, that cron
  was force-restarting the retired always-on service every ~2 minutes
  forever, silently defeating the whole point of the on-demand design
  (caught and fixed 2026-10-25). **If the new on-demand pipeline turns
  out to have the same startup race, it needs a new, on-demand-aware
  staleness check** (one that only treats staleness as a fault while
  `ndi-relay-preview.service` is supposed to be active, per the
  `preview-lastpoll` marker) — `ops/ndi-preview-check/` has not been
  rewritten for this yet.
- **`run-preview.sh`/`run-relay.sh`'s `multifilesink` overwrites its
  output file in place** (not an atomic rename) — a web request can very
  rarely read a partially-written JPEG. Self-heals on the next poll; not
  worth fixing for a best-effort thumbnail.
- **The audio-level meter shares the relay's existing connection.** A
  GStreamer `level` element sits in each relay audio path, and
  `run-gst.sh` forwards its messages to `audio-level-monitor.py` over a
  local pipe. The meter process opens no NDI connection and runs only as
  part of a relay. The meter is stale while neither the real relay nor the
  on-demand preview relay is running.
