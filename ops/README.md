# ops

Reliability scripts written for this specific broadcast deployment — they
don't depend on `bcast-control` or `ndi-relay`'s code, but they're what
makes an unattended, remotely-managed box like this recoverable when
something goes wrong overnight with no one physically present to power-cycle
it.

## watchdog/ — hardware watchdog

Uses the real Intel `iTCO_wdt` **hardware** watchdog timer (not a software
watchdog) so it can force a reset even if the kernel is fully locked up.

- `watchdog-arm.py` + `systemd/watchdog-arm.service` — runs once at boot,
  sets the hardware timeout to 300s via the `WDIOC_SETTIMEOUT` ioctl, and
  sends one initial pat. `RemainAfterExit=yes`, `WantedBy=sysinit.target`.
- `watchdog-check.sh` + `cron.d/broadcast-watchdog-cron` — runs every
  minute via cron (niced to +19). Only pats (`printf '\0' > /dev/watchdog`)
  if a plain `ps -ef` and `ls /tmp` both succeed; otherwise it skips the
  pat and sends a `wall` message. If nothing pats it for 5 minutes, the
  hardware force-resets the machine with no OS cooperation required.

**Deliberately does not use a persistent daemon or heartbeat file** — the
oneshot-arm + stateless-cron-pat design is intentional; a more complex
"holder daemon" version was tried and rejected as overengineered for what
this needs to do.

Requires `/etc/modprobe.d/iTCO-wdt-nowayout.conf` with
`options iTCO_wdt nowayout=1` (so a stray open/close of `/dev/watchdog`
can't accidentally disarm it — this cron pattern relies on that) —
**this needs `update-initramfs -u` and a reboot to actually take effect**
if the module loads early from initramfs, which it typically does. Verify
with `cat /sys/class/watchdog/watchdog0/nowayout` (should read `1`).

You'll see `watchdog0: watchdog did not stop!` in `dmesg`/`journalctl`
once a minute, forever, once this is running — that's expected (the
watchdog core logs this on every close that isn't preceded by the "magic
close" character), not a sign of a problem.

## gateway-watchdog/ — network-loss reboot

`gateway-watchdog.sh` + `systemd/gateway-watchdog.{service,timer}` — runs
every 5 minutes, pings the default gateway, and reboots
(`/sbin/reboot`, a normal graceful reboot — separate from the hardware
watchdog above) if it's been unreachable for 30+ minutes straight
(`/run/gateway-watchdog.first-failure` tracks when the outage started).
Adjust `THRESHOLD_SECS` in the script to change the 30-minute threshold.

This exists because a WiFi adapter can silently drop its association
(no deauth/disconnect event logged at all, driver believes it's still
connected) while genuinely losing all connectivity — a plain systemd
`Restart=` rule can't catch that, since nothing actually crashes.

## boot-notify/ — "I'm back up" push notification

`notify-boot.sh` runs once at boot (`systemd/boot-notify.service`) and
calls `notify.sh` with uptime + recent login info. `notify.sh.template` →
copy to `notify.sh`, fill in your own webhook URL/secret (a Home Assistant
webhook in the original deployment, but the script is generic enough to
point at anything that accepts a JSON POST). Treat the filled-in
`notify.sh` like a password (`chmod 700`) — it's gitignored, don't commit
it with real credentials in it.

## ndi-preview-check/ — self-heal for a known relay startup race

`ndi-preview-check.sh` + `cron.d/ndi-preview-check-cron` — runs every 2
minutes, checks the age of the relay's preview JPEG
(`/run/bcast-control/cam.jpg`), and force-restarts
`ndi-preview.service` if it's stale/missing. See `../relay/README.md`'s
"Known limitations" section for why this is needed — the underlying
service can get stuck `active (running)` while silently producing zero
frames, which plain `Restart=on-failure` doesn't catch.

## Install

Each subdirectory's files map directly onto the paths referenced in their
own scripts/units:

```bash
sudo cp watchdog/watchdog-arm.py watchdog/watchdog-check.sh \
        gateway-watchdog/gateway-watchdog.sh \
        boot-notify/notify-boot.sh \
        ndi-preview-check/ndi-preview-check.sh \
        /usr/local/sbin/
sudo chmod 755 /usr/local/sbin/{watchdog-arm.py,watchdog-check.sh,gateway-watchdog.sh,notify-boot.sh,ndi-preview-check.sh}

sudo cp boot-notify/notify.sh.template /usr/local/sbin/notify.sh
sudo chmod 700 /usr/local/sbin/notify.sh
# edit /usr/local/sbin/notify.sh now with your real webhook URL/secret

sudo cp */systemd/*.service */systemd/*.timer /etc/systemd/system/
sudo cp watchdog/cron.d/* ndi-preview-check/cron.d/* /etc/cron.d/

echo "options iTCO_wdt nowayout=1" | sudo tee /etc/modprobe.d/iTCO-wdt-nowayout.conf
sudo update-initramfs -u

sudo systemctl daemon-reload
sudo systemctl enable --now watchdog-arm.service gateway-watchdog.timer boot-notify.service
```

Then reboot once so the `nowayout=1` module option actually takes effect
(check `cat /sys/class/watchdog/watchdog0/nowayout` reads `1` afterward).
