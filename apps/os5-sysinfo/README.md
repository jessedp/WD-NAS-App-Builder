# OS5 SysInfo for WD MyCloud OS5

Exports NAS health as JSON: disks, RAID, volumes, CPU, RAM, temperatures, uptime. Runs on the NAS itself, so nothing needs SSH access or a root key. Serves the JSON on its own port (optional API key) and/or writes it to a share.

Replaces the SSH-based [wd-os5-exporter](https://github.com/fata13rorr/wd-os5-exporter) with a native app. That project (MIT) did the reverse engineering this depends on: its [WD-OS5-INTERNALS.md](https://github.com/fata13rorr/wd-os5-exporter/blob/main/docs/WD-OS5-INTERNALS.md) documents that WD keeps disk, RAID and volume state in the sysinfo xmldb and how to dump it with `xmldbc`. Its Homepage field names are kept for compatibility. No code was copied; the parsers and health logic were written against dumps from a PR4100. See [DESIGN.md](../../docs/apps/os5-sysinfo/DESIGN.md) for why it is built this way, and [the user docs](../../docs/apps/os5-sysinfo/README.md) for usage.

## Building the app

```bash
./build.sh os5-sysinfo
```

Nothing is downloaded. The package is the lifecycle scripts, `sysinfo_collector.py`, `default.conf` and the web page.

## Layout on the NAS

```
Nas_Prog/os5-sysinfo/            the app (replaced on upgrade)
Nas_Prog/os5-sysinfo_conf/       persistent, kept across upgrades and removal
  os5-sysinfo.conf               KEY=value settings (seeded from default.conf on first install)
  status.json                    latest document, always written
  os5-sysinfo.log                collector log (rotated, 256 KiB x 2)
  os5-sysinfo.out                process stdout/stderr (crash tracebacks only)
```

## Collector

`sysinfo_collector.py --conf <conf dir>` is Python 3 stdlib only (the device has 3.9, no pip). It dumps `/disks`, `/raids` and `/vols` from WD's sysinfo database with `xmldbc -p <tree> <tmpfile> -S /var/run/xmldb_sock_sysinfo`, reads `/proc/{stat,meminfo,loadavg,uptime}` and hwmon/thermal sysfs, derives health, and every `INTERVAL` seconds writes `status.json` (atomic temp + rename), the optional `OUTPUT_PATH`, and fetches the optional heartbeat URL. When `LISTENER=1` a `ThreadingHTTPServer` serves `/status` and `/health` with the cached document; `X-Api-Key` or `?key=` is required when `API_KEY` is set (constant-time compare). A bind failure is retried every 15 s and never stops the file output.

`--once` collects once, prints the JSON, and exits 1 on any partial error. `SYSINFO_FIXTURES=<dir>` redirects every device read to files under `<dir>` (xmldbc dumps in `<dir>/xmldbc/<tree>.xml`), which is how `tests/os5-sysinfo/test.sh` runs the whole thing off-device.

## Tests

```bash
tests/os5-sysinfo/test.sh
```

Covers: healthy 4-bay PR4100 fixtures, serial/model toggles, degraded RAID with a failed disk, ARM single-bay with no RAID and no temperature sensors, the listener with and without a key (401/200/404/HEAD), the file output, clean shutdown, and temp-file cleanup.
