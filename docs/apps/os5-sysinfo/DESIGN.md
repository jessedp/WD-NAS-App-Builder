# Handover: NAS metrics collector for lotsofthings (PR4100)

**Destination:** `jessedp/wd-nas-builder-app` — move this file there; it lives in
control-center only because that repo isn't cloned on oddjob.

**Status:** design decided, nothing built. Written 2026-09-27 from oddjob.

## What was asked

Implement <https://github.com/fata13rorr/wd-os5-exporter> (Homepage-facing JSON
exporter of WD OS5 disk/RAID/volume/CPU/RAM metrics), then: security-check it
first, since it wants a private key with NAS access.

## Audit outcome — upstream is clean, its architecture isn't worth adopting

Reviewed at `2f075fc` (v0.1.1), 781 lines, in full. No backdoor: no egress except
SSH to `NAS_HOST`, no `eval`/`exec`/`base64`/subprocess/telemetry, deps pinned
(Flask 3.1.1, paramiko 3.5.1, gunicorn 23.0.0), 4 commits with no orphaned
objects, CI minimal and release-only. The GHCR image is genuinely built from this
source — `latest` = `sha256:bcb7194c…`, SLSA provenance names Actions run
`34195120787` and revision `2f075fcf…`, and the image layer history matches the
Dockerfile line-for-line. Author is pseudonymous (0 followers, 3 repos, ~3 weeks
old), which is why `:latest` shouldn't be trusted to stay this code.

Findings, in its own files:

| Sev | Issue |
| --- | --- |
| High | The key it wants is root on the NAS — verified `sshd@lotsofthings` = `uid=0`. It runs 4 read-only commands; nothing restricts the key to them. |
| High | `AutoAddPolicy()` (`src/app.py:55`) with nothing persisted — every connection accepts any host key. Pubkey auth means the key can't be replayed against the real NAS, but a spoofed host can feed fake "Healthy" metrics silently. |
| Med | `/status` unauthenticated, returns disk serials/device paths/models/mountpoints; compose binds it on all host interfaces. |
| Med | Failures return `str(exc)` to the caller (`src/app.py:754-764`) — leaks NAS hostname, user, key path. |
| Med | Container runs as root (no `USER`) with the key bind-mounted. Root-in-container + root-on-NAS key = one escape owns the NAS. |
| Low | Fixed predictable paths written as root in NAS `/tmp` (`src/app.py:108-113`), which is `drwxrwxrwt`. |
| Low | Compose pulls mutable `:latest`. |

Two non-security defects for our hardware: it reports **2 of our 4 disks**
(`disks[0..1]` hardcoded at `src/app.py:595-605,652-661`; upstream developed on a
2-bay EX2 Ultra), and it has no negative caching while holding the collection
lock (`src/app.py:734-746`), so a down NAS means a fresh 10s-timeout SSH attempt
per Homepage poll.

**The container is a passthrough** — strip the SSH helpers and only four commands
plus an XML parser remain. SSH is the cost, not the benefit: it's what forces a
root-equivalent key onto oddjob and what re-breaks whenever the NAS wipes
`authorized_keys` (2026-07-03, 2026-09-11 — see `inventory/lotsofthings.md`).

## Decision: run the collector on the NAS, no key anywhere

Probed on lotsofthings, all confirmed:

- `python3` 3.9.2 (stdlib only), `curl`, `wget`, `awk`, `crond`, `busybox`. No `nc`, no `jq`.
- `xmldbc` at `/usr/sbin/xmldbc`; socket `/var/run/xmldb_sock_sysinfo` is `srwxrwxrwx`; `/disks` returns 4 disks with `healthy over_temp sleep temp`.
- Persistent: `/mnt/HD/HD_a2` (= `/opt`, md1 16.3T). `Nas_Prog/` is the app dir — tailscale and copyparty live there and survive reboots.
- Tailscale up: `100.64.93.4/32` on `tailscale0`. Port 8085 free.
- oddjob already mounts `192.168.1.10:/nfs/Public` → `/mnt/Public` rw with hardened `_netdev,nofail,retry=5`.

**Chosen shape (A):** one self-contained script in `Nas_Prog/<app>/`, launched at
boot by the BootScripts hook, looping every X seconds — run `xmldbc`, read
`/proc/{stat,meminfo}`, write JSON, push an Uptime Kuma heartbeat. oddjob reads
the file at `/mnt/Public/…`; Homepage gets its URL from a new route on the Flask
app already serving `oddjob:5000/nas-report`.

Why: no key material and no new listener (the NAS already exposes FTP 21, SMB
139/445, NFS 2049, nasAdmin 8543, syncthing 8384, copyparty 3923 on all
interfaces). A boot-launched loop beats cron because `/etc/cron.d` is on the
non-persistent root fs. Tradeoff: staleness is by design, so the `updated` field
and the Kuma push are load-bearing, not optional — they are exactly what was
missing during the 8-week silent-stale episode. Anything in `Public` is readable
by anyone with SMB/NFS/copyparty access, so omit serial numbers.

**Runner-up (B):** same collector bound to `100.64.93.4:8085` via stdlib
`http.server`, Homepage pointing at `http://lotsofthings:8085/status`. Fresher,
nothing in a shared folder, no oddjob-side change — but a long-lived root daemon
on the device whose root fs gets wiped, plus one more listener. BootScripts is the
install hook either way; A is a script, B is a service.

## Next steps

1. Read the BootScripts layout in this repo; decide where a second app package goes.
2. Port from upstream (MIT — keep attribution): `docs/WD-OS5-INTERNALS.md` (the real value: tree names + socket path) and the pure functions `parse_disks`, `parse_raids`, `parse_volumes`, `determine_health`, `build_status`, plus the Homepage field/display mapping. Drop paramiko, Flask, gunicorn, Dockerfile, compose.
3. Fix the 4-disk gap while porting; omit serials from the JSON.
4. Add the Kuma push + `updated` timestamp; create the monitor.
5. oddjob side: serve the JSON from the `nas-report` Flask app, point the Homepage `customapi` widget at it, retire the SSH path.
6. Update `inventory/lotsofthings.md` and `inventory.md` per control-center rules.

## Open questions

- Does this replace oddjob's 03:00 `collect_nas_usage.sh` cron, or run alongside it?
- Does a firmware update survive `Nas_Prog/` app installs? Tailscale survived at 1.102.3, so probably — but the BootScripts re-install path should be assumed to be the recovery mechanism.
