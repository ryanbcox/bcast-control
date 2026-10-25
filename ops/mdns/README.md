# mdns

Makes the broadcast computer discoverable on the local network the way a
Chromecast is — resolvable as `broadcast-computer.local` and advertised as
a browsable service, with no periodic-broadcast loop to maintain (mDNS is
query/response: the daemon answers instantly whenever asked, which is how
"instant" discovery actually works under the hood).

Depends only on `avahi-daemon`/`avahi-utils` (Debian: `apt install
avahi-daemon avahi-utils`) — nothing from `bcast-control`/`ndi-relay`
itself, same as the rest of `ops/`.

## Install

1. **Hostname** — add (or uncomment/edit) this line in
   `/etc/avahi/avahi-daemon.conf`'s `[server]` section:

   ```
   host-name=broadcast-computer
   ```

   This makes Avahi publish `broadcast-computer.local` **without**
   renaming the box's actual OS hostname (`hostnamectl` can keep
   reporting whatever it already does — this is a separate, independent
   setting). Pick a different name here if you're deploying this
   elsewhere; it doesn't need to match the real hostname.

2. **Service advertisement** — copy `broadcast-computer.service` from
   this directory to `/etc/avahi/services/`:

   ```bash
   sudo cp broadcast-computer.service /etc/avahi/services/
   ```

   It advertises the web panel over `_http._tcp`/`_https._tcp`, plus a
   custom `_broadcast-computer._tcp` type (mainly so `avahi-browse` shows
   something identifiable beyond "a web server"). Edit the `<name>` and
   `<type>` if you rename the hostname in step 1 — they don't have to
   match, but it's confusing if they don't.

3. **Apply**:

   ```bash
   sudo systemctl restart avahi-daemon
   ```

## Verify

```bash
avahi-resolve -n broadcast-computer.local   # should return this box's LAN IP
avahi-browse -art                           # should list "Broadcast Computer" under _broadcast-computer._tcp (and the http/https entries)
```

## Notes

- This is **on-demand** mDNS (query/response), not a fixed-interval
  broadcast — don't build a periodic-announce loop on top of this
  thinking something's missing; it isn't needed, and it's not how
  Chromecast-style discovery actually works either.
- If this box is ever renamed, update **both** `host-name=` in
  `avahi-daemon.conf` and the `<name>` in the service file to match (or
  intentionally don't, if you want the mDNS name to stay stable across a
  hostname change — that's exactly why these are two independent
  settings).
