#!/usr/bin/env python3
"""
Runs once at boot (via watchdog-arm.service). Opens /dev/watchdog, sets the
hardware timeout to 300 seconds (5 minutes) via the WDIOC_SETTIMEOUT ioctl,
writes one initial pat, and exits.

Closing the fd here does NOT disarm the watchdog: nowayout=1 is set via
/etc/modprobe.d/iTCO-wdt-nowayout.conf (takes effect on the next reboot
after that file is installed), so the countdown keeps running regardless of
this process exiting. From this point on, watchdog-check.sh (run every
minute via cron) is what actually pats the device going forward -- this
script only needs to run once per boot to set the timeout.
"""
import fcntl
import os
import struct
import sys

WATCHDOG_DEVICE = "/dev/watchdog"
TIMEOUT_SECONDS = 300  # 5 minutes
WDIOC_SETTIMEOUT = 0xC0045706  # _IOWR('W', 6, int), from linux/watchdog.h

fd = os.open(WATCHDOG_DEVICE, os.O_WRONLY)
try:
    fcntl.ioctl(fd, WDIOC_SETTIMEOUT, struct.pack("i", TIMEOUT_SECONDS))
except OSError as e:
    print(f"warning: could not set timeout via ioctl: {e} (using driver default)", file=sys.stderr)

os.write(fd, b"\0")
os.close(fd)
print(f"watchdog armed: timeout={TIMEOUT_SECONDS}s, initial pat sent")
