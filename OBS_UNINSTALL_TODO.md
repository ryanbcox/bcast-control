# What could be removed later, once the GStreamer relay path has proven
# itself in real services (do NOT remove any of this automatically as part
# of any single cutover -- this is a follow-up cleanup list only, to be
# done deliberately after confidence is established)

## systemd
- `vncserver-broadcast.service` is stopped + disabled as part of the
  relay cutover (2026-09-19), but its unit file is left in place at
  `/etc/systemd/system/vncserver-broadcast.service` in case of rollback.
  Delete that file (and run `systemctl daemon-reload`) once OBS is
  confirmed permanently retired.

## Packages (apt) -- only remove once fully confident OBS won't be needed again
- `obs-studio`
- `tigervnc-standalone-server`
- `tigervnc-common`
- `tigervnc-tools`
- `icewm`
- `icewm-common`
(verify no other service on this box depends on any of these before
removing -- `apt remove --dry-run <pkg>` first.)

## Manually-placed files (NOT managed by dpkg -- `apt remove` won't touch these)
- `/usr/lib/x86_64-linux-gnu/obs-plugins/distroav.so`
- `/usr/share/obs/obs-plugins/distroav/` (directory, includes `locale/`)
- `/opt/bcast-control/build/distroav/` (backup copy of the above, including
  `README.txt` and `data/` -- keep this one longer than the live copies
  above, it's the source-of-truth backup if DistroAV is ever needed again)

## bcast-control's own OBS-specific files (superseded, not deleted by this
## plan -- python/*.py, config.json still reference obs-websocket)
- `/opt/bcast-control/python/obs-capture.py`
- `/opt/bcast-control/python/obs-getstatus.py`
- `/opt/bcast-control/python/obs-setkey.py`
- `/opt/bcast-control/python/obs-setscene.py`
- `/opt/bcast-control/python/obs-startstream.py`
- `/opt/bcast-control/python/obs-stopstream.py`
- `/opt/bcast-control/python/config.json` (contains OBS websocket password
  -- rotate/discard rather than leaving it lying around indefinitely once
  OBS is fully retired)
- `/opt/bcast-control/www/setkey.php` (made inert by this plan -- safe to
  delete once confirmed nothing references it)

## Not touched by this list
- OBS's user-level config under `/home/broadcast/.config/obs-studio/` (if
  present) -- leave alone, irrelevant to dpkg/uninstall, only matters if
  someone later deletes the `broadcast` user's home directory.
