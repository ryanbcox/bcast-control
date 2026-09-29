# Changes from upstream (evade-ninja/bcast-control)

This fork moved the web panel to driving a from-source-built GStreamer
NDI-to-RTMP relay (see `relay/`), for a single-camera church broadcast
setup running on modest, older hardware (Dell OptiPlex 390, 4GB RAM)
where running a full compositor/GUI app 24/7 was more overhead than the
job needed. See the bottom of this repo's `README.md` for what upstream
used before.

Full unified diffs for every changed text file are in
`upstream-diff.patch` in this same directory. This document is the
human-readable summary.

## New files not in upstream

- **`www/vars-template.php`** — rewritten for the relay path. Copy to
  `vars.php` (gitignored) and fill in your camera's IP and relay paths.
- **`www/config-template.js`** — updated `template` string to include the
  preset-number badge and press-flash support that `bcast.js`/`styles.css`
  now expect. Copy to `config.js` (gitignored) and list your camera
  presets.
- **6 new PHP endpoints**: `www/ptz-move.php`, `www/ptz-zoom.php`,
  `www/ptz-nudge.php`, `www/ptz-zoom-nudge.php` (manual directional/zoom
  PTZ control — tap for a short pulse, hold for continuous movement),
  `www/ptz-goto.php` / `www/ptz-save.php` (free-text "go to preset / save
  preset" box), `www/poweroff.php` / `www/reboot.php` (remote power
  control, with a day-of-month confirmation check), `www/night-mode.php`
  (camera brightness toggle, done via the camera's own hardware setting,
  not a software filter).
- **`www/index.php`** — new; replaces `index.html` as the actual served
  page, so JS/CSS asset URLs can carry a `?t=<filemtime>` cache-busting
  query string computed fresh on every load. `index.html` is now just a
  redirect stub pointing at it.

## Files substantially rewritten

- **`www/bcast.js`** (largest change):
  - Removed all stream-key-selection code (`loadKeys`/`loadKey`/
    `getKeyName`) — the relay path has exactly one configured YouTube
    destination per running instance, so there's no key to choose.
  - Added tap-vs-hold gesture handling for the PTZ manual-control pad.
  - Added a collapsible manual-PTZ-controls section (collapsed by
    default), placed above the preset grid.
  - Added a "pause all page updates" toggle.
  - Added connection/health monitoring: detects if the browser has
    stopped receiving status updates, and separately pings a known-good
    external host to distinguish "browser's own connection died" from
    "broadcast computer is unreachable."
  - Added day-of-month confirmation logic for the power-off/reboot
    buttons (client-side check; the server independently re-validates).
  - Fixed a bug where the stop button wasn't graying out based on actual
    streaming state.
  - Added the preset-thumbnail number badge and a green "press flash" for
    camera preset button feedback.
  - Added the "go to preset / save preset" input box logic, including a
    save-confirmation flow (saving overwrites the camera's stored preset).
- **`www/status.php`** — reports the relay's `systemctl is-active` state
  and current scene/night-mode, read from small state files the relay
  writes.
- **`www/start.php` / `www/stop.php` / `www/setstart.php`** — now
  `sudo systemctl start/stop` the relay service.
- **`www/set-blank.php` / `www/set-norm.php` / `www/set-sac.php`** — now
  call a `set-scene.sh` script that restarts the relay into a different
  GStreamer pipeline (live camera vs. a static branded image).
- **`www/setkey.php`** — made inert (HTTP 410) since there's no more
  per-stream-key concept; left in place rather than deleted in case
  anything still links to the URL.
- **`www/styles.css`** — added styling for the PTZ D-pad layout and the
  preset-card number badge / press-flash.

## Repo layout: `relay/` and `ops/` added

The GStreamer relay's own original code (the scripts and systemd units in
`vars.php`/`www/*.php`'s `sudo`-wrapped calls) is now included, under
`relay/` — see `relay/README.md`. So are a set of reliability scripts
written for running this unattended (hardware/gateway watchdogs, boot
notification, a self-heal for a known relay startup race), under `ops/`
— see `ops/README.md`.

**Not included**, and not something this repo can vendor:
- The actual NDI GStreamer elements (`ndisrc`/`ndisrcdemux`) — external,
  unmodified code from
  [teltek/gst-plugin-ndi](https://github.com/teltek/gst-plugin-ndi)
  (LGPL). `relay/README.md` documents the exact commit and build steps.
- NDI's own proprietary redistributable runtime library.
