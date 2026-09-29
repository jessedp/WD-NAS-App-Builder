#!/usr/bin/env python3
"""
os5-sysinfo collector for WD My Cloud OS5.

Reads disk / RAID / volume state from WD's sysinfo database (xmldbc), CPU, RAM,
load, uptime and temperatures from /proc and /sys, and publishes one JSON
document:

  * always to <conf dir>/status.json (read by the app's config page)
  * optionally to OUTPUT_PATH (a file on a share, for SMB/NFS/copyparty readers)
  * optionally over HTTP on LISTEN_HOST:LISTEN_PORT (/status, /health), with an
    optional API key

Python 3 standard library only; nothing else is available on the device.

Credit: the knowledge that WD keeps this data in the sysinfo xmldb, reachable
with `xmldbc -p /disks|/raids|/vols <file> -S /var/run/xmldb_sock_sysinfo`,
comes from fata13rorr/wd-os5-exporter (MIT), docs/WD-OS5-INTERNALS.md:
https://github.com/fata13rorr/wd-os5-exporter
The top-level JSON field names are kept compatible with that project. The
parsing and health logic here were written fresh against dumps from a PR4100.

    sysinfo_collector.py --conf /mnt/HD/HD_a2/Nas_Prog/os5-sysinfo_conf
    sysinfo_collector.py --conf DIR --once        # one collect to stdout

Set SYSINFO_FIXTURES=<dir> to read device files from <dir>/<path> and xmldbc
dumps from <dir>/xmldbc/<node>.xml instead of the live system (used by tests).
"""

import argparse
import glob
import hmac
import json
import logging
import logging.handlers
import os
import signal
import socket
import subprocess
import sys
import tempfile
import threading
import time
import urllib.parse
import urllib.request
import xml.etree.ElementTree as ET
from datetime import datetime, timezone
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

VERSION = "1.0.0"
XMLDBC = "xmldbc"
XMLDBC_SOCK = "/var/run/xmldb_sock_sysinfo"
MODEL_FILE = "/usr/local/modules/files/model"
FIXTURES = os.environ.get("SYSINFO_FIXTURES")
SYSTEM_RAID_MAX_BYTES = 16 * 1024 ** 3  # WD's md0 system raid is ~2 GB

DEFAULTS = {
    "LISTENER": "1",
    "LISTEN_HOST": "0.0.0.0",
    "LISTEN_PORT": "8085",
    "API_KEY": "",
    "OUTPUT_PATH": "",
    "INTERVAL": "30",
    "INCLUDE_SERIALS": "0",
    "INCLUDE_MODELS": "1",
    "HEARTBEAT_URL": "",
}

log = logging.getLogger("os5-sysinfo")


# --------------------------------------------------------------------------
# Config
# --------------------------------------------------------------------------

def load_config(conf_dir):
    cfg = dict(DEFAULTS)
    path = os.path.join(conf_dir, "os5-sysinfo.conf")
    try:
        with open(path, encoding="utf-8", errors="replace") as fh:
            for line in fh:
                line = line.strip()
                if not line or line.startswith("#") or "=" not in line:
                    continue
                key, value = line.split("=", 1)
                key = key.strip().upper()
                if key in DEFAULTS:
                    cfg[key] = value.strip()
    except FileNotFoundError:
        log.info("no config at %s, using defaults", path)
    except OSError as exc:
        log.warning("config %s not readable (%s), using defaults", path, exc)

    def as_int(key, lo, hi):
        try:
            v = int(cfg[key])
        except (TypeError, ValueError):
            v = int(DEFAULTS[key])
        return max(lo, min(hi, v))

    cfg["INTERVAL"] = as_int("INTERVAL", 5, 3600)
    cfg["LISTEN_PORT"] = as_int("LISTEN_PORT", 1, 65535)
    for key in ("LISTENER", "INCLUDE_SERIALS", "INCLUDE_MODELS"):
        cfg[key] = cfg[key].strip() in ("1", "true", "yes", "on")
    if not cfg["LISTEN_HOST"]:
        cfg["LISTEN_HOST"] = DEFAULTS["LISTEN_HOST"]
    return cfg


# --------------------------------------------------------------------------
# Device access (fixture-aware)
# --------------------------------------------------------------------------

def fx(path):
    """Map a device path to its fixture file when SYSINFO_FIXTURES is set."""
    if FIXTURES:
        return os.path.join(FIXTURES, path.lstrip("/"))
    return path


def unfx(path):
    """Inverse of fx(): a glob hit under the fixture dir back to its device path."""
    if FIXTURES and path.startswith(FIXTURES):
        return "/" + os.path.relpath(path, FIXTURES)
    return path


def read_text(path):
    try:
        with open(fx(path), encoding="utf-8", errors="replace") as fh:
            return fh.read().strip()
    except OSError:
        return None


def read_int(path):
    text = read_text(path)
    if text is None:
        return None
    try:
        return int(text.split()[0])
    except (ValueError, IndexError):
        return None


def txt(el, tag, default=""):
    if el is None:
        return default
    child = el.find(tag)
    if child is None or child.text is None:
        return default
    return child.text.strip()


def num(el, tag, default=None):
    value = txt(el, tag, "")
    if value == "":
        return default
    try:
        return int(value)
    except ValueError:
        try:
            return int(float(value))
        except ValueError:
            return default


def flag(el, tag):
    return num(el, tag, 0) == 1


class Collector:
    def __init__(self, conf_dir, cfg):
        self.conf_dir = conf_dir
        self.cfg = cfg
        self.errors = []
        self.prev_cpu = None
        self.fixture_cpu_calls = 0
        self.static = None

    # -- xmldbc ------------------------------------------------------------

    def run_xmldbc(self, node):
        """Dump one sysinfo tree to a temp file and parse it. None on failure."""
        if FIXTURES:
            path = os.path.join(FIXTURES, "xmldbc", node.strip("/") + ".xml")
            try:
                return ET.parse(path).getroot()
            except (OSError, ET.ParseError) as exc:
                self.errors.append("xmldbc %s: %s" % (node, exc))
                return None

        fd, tmp = tempfile.mkstemp(prefix="xmldbc-", suffix=".xml", dir=self.conf_dir)
        os.close(fd)
        try:
            proc = subprocess.run(
                [XMLDBC, "-p", node, tmp, "-S", XMLDBC_SOCK],
                stdout=subprocess.DEVNULL, stderr=subprocess.PIPE, timeout=10, check=False,
            )
            if proc.returncode != 0:
                self.errors.append("xmldbc %s: rc=%d %s" % (node, proc.returncode, proc.stderr.decode(errors="replace").strip()))
                return None
            if os.path.getsize(tmp) == 0:
                self.errors.append("xmldbc %s: empty dump" % node)
                return None
            return ET.parse(tmp).getroot()
        except FileNotFoundError:
            self.errors.append("xmldbc %s: xmldbc not found" % node)
        except subprocess.TimeoutExpired:
            self.errors.append("xmldbc %s: timed out" % node)
        except (OSError, ET.ParseError) as exc:
            self.errors.append("xmldbc %s: %s" % (node, exc))
        finally:
            try:
                os.unlink(tmp)
            except OSError:
                pass
        return None

    def xmldbc_get(self, key):
        if FIXTURES:
            return read_text("/xmldbc/" + key.strip("/") + ".txt") or ""
        try:
            proc = subprocess.run([XMLDBC, "-g", key], stdout=subprocess.PIPE,
                                  stderr=subprocess.DEVNULL, timeout=5, check=False)
            return proc.stdout.decode(errors="replace").strip()
        except (OSError, subprocess.TimeoutExpired):
            return ""

    def sweep_temp_files(self, max_age=300):
        now = time.time()
        for path in glob.glob(os.path.join(self.conf_dir, "xmldbc-*.xml")):
            try:
                if now - os.path.getmtime(path) > max_age:
                    os.unlink(path)
            except OSError:
                pass

    # -- sections ------------------------------------------------------------

    def collect_disks(self):
        root = self.run_xmldbc("/disks")
        disks = []
        if root is None:
            return disks
        for el in root.findall("disk"):
            if num(el, "connected", 1) != 1:
                continue
            failed = flag(el, "failed")
            healthy = flag(el, "healthy")
            over_temp = flag(el, "over_temp")
            smart_test = txt(el.find("smart"), "test", "")
            size = num(el, "size", 0) or 0
            disk = {
                "id": int(el.get("id") or len(disks) + 1),
                "name": txt(el, "name"),
                "dev": txt(el, "dev"),
            }
            if self.cfg["INCLUDE_MODELS"]:
                disk["vendor"] = txt(el, "vendor")
                disk["model"] = txt(el, "model")
            if self.cfg["INCLUDE_SERIALS"]:
                disk["serial"] = txt(el, "sn")
            disk.update({
                "size_bytes": size,
                "size_display": human_bytes(size),
                "temp_c": num(el, "temp"),
                "over_temp": over_temp,
                "failed": failed,
                "healthy": healthy,
                "removable": flag(el, "removable"),
                "sleeping": flag(el, "sleep"),
                "smart_test": smart_test,
                "smart_display": smart_test or "Not run",
                "status": "failed" if failed else ("warning" if (not healthy or over_temp) else "ok"),
            })
            disks.append(disk)
        return disks

    def collect_raids(self):
        root = self.run_xmldbc("/raids")
        raids = []
        if root is None:
            return raids, None
        for el in root.findall("raid"):
            size = num(el, "size", 0) or 0
            raids.append({
                "id": int(el.get("id") or len(raids) + 1),
                "level": txt(el, "level"),
                "dev": txt(el, "dev"),
                "state": txt(el, "state"),
                "state_detail": txt(el, "state_detail"),
                "is_system": size < SYSTEM_RAID_MAX_BYTES,
                "disks": txt(el, "raid_disks").split(),
                "spare_disks": txt(el, "spare_disks").split(),
                "failed_disks": txt(el, "failed_disks").split(),
                "rebuilding_disks": txt(el, "rebuilding_disks").split(),
                "num_total": num(el, "num_of_total_disks", 0),
                "num_active": num(el, "num_of_active_disks", 0),
                "num_working": num(el, "num_of_working_disks", 0),
                "num_spare": num(el, "num_of_spare_disks", 0),
                "num_failed": num(el, "num_of_failed_disks", 0),
                "size_bytes": size,
                "size_display": human_bytes(size),
                "dirty": flag(el, "dirty"),
            })
        return raids, flag(root, "dirty")

    def collect_vols(self):
        root = self.run_xmldbc("/vols")
        vols = []
        totals = None
        if root is None:
            return vols, totals
        for el in root.findall("vol"):
            size = num(el, "size", 0) or 0
            used = num(el, "used_size", 0) or 0
            vols.append({
                "id": int(el.get("id") or len(vols) + 1),
                "name": txt(el, "name"),
                "label": txt(el, "label"),
                "mount": txt(el, "mnt"),
                "dev": txt(el, "dev"),
                "mounted": flag(el, "mounted"),
                "unlocked": flag(el, "unlocked"),
                "encrypted": flag(el, "encrypted"),
                "size_bytes": size,
                "used_bytes": used,
                "free_bytes": max(0, size - used),
                "percent": round(100.0 * used / size, 1) if size else None,
                "size_display": human_bytes(size),
                "used_display": human_bytes(used),
                "raid_level": txt(el, "raid_level"),
                "raid_state": txt(el, "raid_state"),
                "raid_state_detail": txt(el, "raid_state_detail"),
            })
        total = num(root, "total_size")
        if total:
            totals = {
                "size_bytes": total,
                "used_bytes": num(root, "total_used_size", 0) or 0,
                "free_bytes": num(root, "total_unused_size", 0) or 0,
            }
        return vols, totals

    def sample_cpu(self):
        path = "/proc/stat"
        if FIXTURES:
            # tests: second and later samples come from proc/stat2 when present
            self.fixture_cpu_calls += 1
            if self.fixture_cpu_calls > 1 and os.path.exists(fx("/proc/stat2")):
                path = "/proc/stat2"
        text = read_text(path)
        if not text:
            return None
        first = text.splitlines()[0].split()
        if len(first) < 5 or first[0] != "cpu":
            return None
        try:
            values = [int(v) for v in first[1:]]
        except ValueError:
            return None
        idle = values[3] + (values[4] if len(values) > 4 else 0)
        return idle, sum(values)

    def cpu_percent(self):
        cur = self.sample_cpu()
        if cur is None:
            return None
        if self.prev_cpu is None:
            self.prev_cpu = cur
            time.sleep(1)
            cur = self.sample_cpu()
            if cur is None:
                return None
        d_idle = cur[0] - self.prev_cpu[0]
        d_total = cur[1] - self.prev_cpu[1]
        self.prev_cpu = cur
        if d_total <= 0:
            return 0.0
        return round(max(0.0, min(100.0, 100.0 * (1.0 - d_idle / d_total))), 1)

    def collect_mem(self):
        text = read_text("/proc/meminfo")
        if not text:
            return None
        fields = {}
        for line in text.splitlines():
            parts = line.split()
            if len(parts) >= 2 and parts[0].endswith(":"):
                try:
                    fields[parts[0][:-1]] = int(parts[1]) * 1024
                except ValueError:
                    pass
        total = fields.get("MemTotal")
        if not total:
            return None
        available = fields.get("MemAvailable")
        if available is None:
            available = fields.get("MemFree", 0) + fields.get("Buffers", 0) + fields.get("Cached", 0)
        used = max(0, total - available)
        return {
            "total_bytes": total,
            "used_bytes": used,
            "available_bytes": available,
            "percent": round(100.0 * used / total, 1),
        }

    def collect_load(self):
        text = read_text("/proc/loadavg")
        if not text:
            return None
        try:
            return [float(v) for v in text.split()[:3]]
        except ValueError:
            return None

    def collect_uptime(self):
        text = read_text("/proc/uptime")
        if not text:
            return None
        try:
            return int(float(text.split()[0]))
        except (ValueError, IndexError):
            return None

    def collect_temps(self):
        cores = []
        zones = {}
        for hw in sorted(glob.glob(fx("/sys/class/hwmon/hwmon*"))):
            name = read_text(unfx(hw) + "/name")
            for inp in sorted(glob.glob(hw + "/temp*_input")):
                dev_path = unfx(inp)
                value = read_int(dev_path)
                if value is None:
                    continue
                label = read_text(dev_path[:-len("_input")] + "_label") or (name or os.path.basename(hw))
                celsius = int(round(value / 1000.0))
                if name in ("coretemp", "cpu_thermal", "k10temp", "soc_thermal") or "core" in (label or "").lower():
                    cores.append(celsius)
                else:
                    zones[label] = celsius
        for tz in sorted(glob.glob(fx("/sys/class/thermal/thermal_zone*"))):
            dev_path = unfx(tz)
            value = read_int(dev_path + "/temp")
            if value is None:
                continue
            ztype = read_text(dev_path + "/type") or os.path.basename(tz)
            zones[ztype] = int(round(value / 1000.0))
        cpu_c = max(cores) if cores else None
        if cpu_c is None and zones:
            for key in ("cpu-thermal", "cpu_thermal", "soc-thermal", "x86_pkg_temp"):
                if key in zones:
                    cpu_c = zones[key]
                    break
            if cpu_c is None and len(zones) == 1:
                cpu_c = next(iter(zones.values()))
        return {"cpu_c": cpu_c, "cores": cores, "zones": zones}

    def collect_static(self):
        if self.static is None:
            self.static = {
                "model": read_text(MODEL_FILE) or "",
                "firmware": self.xmldbc_get("/sw_ver_2"),
                "hostname": socket.gethostname(),
            }
        return self.static

    # -- document ------------------------------------------------------------

    def build_status(self, state):
        self.errors = []
        t0 = time.monotonic()
        static = self.collect_static()
        disks = self.collect_disks()
        raids, raids_dirty = self.collect_raids()
        vols, totals = self.collect_vols()
        cpu = self.cpu_percent()
        mem = self.collect_mem()
        load = self.collect_load()
        uptime = self.collect_uptime()
        temps = self.collect_temps()

        # Primary volume: first mounted, else first, else the totals
        primary = next((v for v in vols if v["mounted"]), vols[0] if vols else None)
        if primary:
            storage_size, storage_used = primary["size_bytes"], primary["used_bytes"]
            volume_name, mount = primary["name"], primary["mount"]
        elif totals:
            storage_size, storage_used = totals["size_bytes"], totals["used_bytes"]
            volume_name, mount = "", ""
        else:
            storage_size = storage_used = 0
            volume_name = mount = ""
        storage_pct = round(100.0 * storage_used / storage_size, 1) if storage_size else None

        health, severity, icon, display = derive_health(disks, raids, vols, storage_pct, raids_dirty)

        now = datetime.now(timezone.utc)
        doc = {
            "cpu": cpu,
            "cpu_display": ("%.1f%%" % cpu) if cpu is not None else "n/a",
            "ram": mem["percent"] if mem else None,
            "ram_display": ("%s / %s (%d%%)" % (human_bytes(mem["used_bytes"]), human_bytes(mem["total_bytes"]), round(mem["percent"]))) if mem else "n/a",
            "storage": storage_pct,
            "storage_display": ("%s / %s" % (human_bytes(storage_used), human_bytes(storage_size))) if storage_size else "n/a",
            "storage_percent_display": ("%d%%" % round(storage_pct)) if storage_pct is not None else "n/a",
            "health": health,
            "health_display": display,
            "health_severity": severity,
            "health_icon": icon,
            "temperature_display": temperature_display(temps["cpu_c"], disks),
            "smart_display": smart_display(disks),
            "volume": volume_name,
            "mount": mount,
            "updated": now.strftime("%Y-%m-%dT%H:%M:%SZ"),
            "updated_epoch": int(now.timestamp()),
            "age_seconds": 0,
            "collect_ms": 0,
            "error": None,
            "system": {
                "model": static["model"],
                "firmware": static["firmware"],
                "hostname": static["hostname"],
                "uptime_seconds": uptime,
                "uptime_display": uptime_display(uptime),
                "load": load,
                "cpu_percent": cpu,
                "cpu_temp_c": temps["cpu_c"],
                "cpu_cores_c": temps["cores"],
                "thermal_zones": temps["zones"],
                "mem_total_bytes": mem["total_bytes"] if mem else None,
                "mem_used_bytes": mem["used_bytes"] if mem else None,
                "mem_available_bytes": mem["available_bytes"] if mem else None,
                "mem_percent": mem["percent"] if mem else None,
                "listener": state.listener,
                "output_path": self.cfg["OUTPUT_PATH"] or None,
                "output_error": state.output_error,
                "interval": self.cfg["INTERVAL"],
                "exporter": "os5-sysinfo",
                "exporter_version": VERSION,
            },
            "disks": disks,
            "raids": raids,
            "volumes": vols,
        }
        doc["collect_ms"] = int((time.monotonic() - t0) * 1000)
        if self.errors:
            doc["error"] = "; ".join(self.errors)
        if not disks and not raids and not vols:
            raise RuntimeError(doc["error"] or "no data from xmldbc")
        return doc


# --------------------------------------------------------------------------
# Derivations
# --------------------------------------------------------------------------

def human_bytes(n):
    if n is None:
        return "n/a"
    n = float(n)
    for unit in ("B", "kB", "MB", "GB", "TB", "PB"):
        if n < 1000 or unit == "PB":
            if unit == "B":
                return "%d B" % n
            return "%.2f %s" % (n, unit)
        n /= 1000.0
    return "n/a"


def uptime_display(seconds):
    if seconds is None:
        return "n/a"
    days, rem = divmod(int(seconds), 86400)
    hours, rem = divmod(rem, 3600)
    minutes = rem // 60
    if days:
        return "%dd %dh %dm" % (days, hours, minutes)
    if hours:
        return "%dh %dm" % (hours, minutes)
    return "%dm" % minutes


def smart_display(disks):
    values = []
    for d in disks:
        v = d.get("smart_test") or ""
        if v and v not in values:
            values.append(v)
    return ", ".join(values) if values else "Not run"


def temperature_display(cpu_c, disks):
    parts = []
    if cpu_c is not None:
        parts.append("CPU %d°C" % cpu_c)
    temps = [d["temp_c"] for d in disks if d.get("temp_c") is not None]
    if temps:
        lo, hi = min(temps), max(temps)
        parts.append("Disks %d°C" % lo if lo == hi else "Disks %d–%d°C" % (lo, hi))
    return " • ".join(parts) if parts else "n/a"


CRITICAL_RAID_STATES = {"degraded", "inactive", "failed", "fail", "broken"}
WARNING_RAID_STATES = {"recovering", "resyncing", "rebuilding", "reshape", "reshaping", "recovery", "resync", "checking"}


def derive_health(disks, raids, vols, storage_pct, raids_dirty):
    if not disks and not raids and not vols:
        return "Unknown", "unknown", "❔", "Unknown"

    data_raids = [r for r in raids if not r["is_system"]] or raids
    reasons_critical = []
    reasons_warning = []

    for d in disks:
        if d["failed"]:
            reasons_critical.append("%s failed" % d["name"])
        elif not d["healthy"]:
            reasons_warning.append("%s unhealthy" % d["name"])
        elif d["over_temp"]:
            reasons_warning.append("%s over temperature" % d["name"])

    for r in data_raids:
        state = (r["state"] or "").lower()
        if r["num_failed"] or r["failed_disks"] or state in CRITICAL_RAID_STATES:
            reasons_critical.append("%s %s" % (r["dev"] or r["level"], state or "failed disks"))
        elif r["rebuilding_disks"] or state in WARNING_RAID_STATES or r["dirty"]:
            reasons_warning.append("%s %s" % (r["dev"] or r["level"], state or "rebuilding"))

    for v in vols:
        if v["dev"] and not v["mounted"]:
            reasons_critical.append("%s not mounted%s" % (v["name"], "" if v["unlocked"] else " (locked)"))

    if storage_pct is not None and storage_pct >= 90:
        reasons_warning.append("storage %d%% full" % round(storage_pct))
    if raids_dirty:
        reasons_warning.append("raid dirty")

    if reasons_critical:
        health, severity, icon = "Critical", "critical", "❌"
    elif reasons_warning:
        health, severity, icon = "Degraded", "warning", "⚠️"
    else:
        health, severity, icon = "Healthy", "ok", "✅"

    # Label from the data raid: the one backing the first mounted volume
    label = "Disk"
    primary = next((v for v in vols if v["mounted"]), vols[0] if vols else None)
    raid = None
    if primary and primary["dev"]:
        raid = next((r for r in raids if r["dev"] == primary["dev"]), None)
    if raid is None and data_raids:
        raid = max(data_raids, key=lambda r: r["size_bytes"])
    if raid and raid["level"]:
        label = raid["level"].upper()
    elif primary and primary.get("raid_level"):
        label = primary["raid_level"].upper()
    display = "%s • %s" % (label, health)
    if raid and raid["state"] and raid["state"].lower() != "clean":
        display += " (%s)" % raid["state"]
    return health, severity, icon, display


# --------------------------------------------------------------------------
# Outputs
# --------------------------------------------------------------------------

def write_json_atomic(path, doc):
    directory = os.path.dirname(path) or "."
    os.makedirs(directory, mode=0o755, exist_ok=True)
    fd, tmp = tempfile.mkstemp(prefix=".status-", suffix=".tmp", dir=directory)
    try:
        with os.fdopen(fd, "w", encoding="utf-8") as fh:
            json.dump(doc, fh, indent=2, ensure_ascii=False)
            fh.write("\n")
            fh.flush()
            os.fsync(fh.fileno())
        os.chmod(tmp, 0o644)
        os.replace(tmp, path)
    except Exception:
        try:
            os.unlink(tmp)
        except OSError:
            pass
        raise


def send_heartbeat(url, doc):
    sep = "&" if "?" in url else "?"
    full = "%s%sstatus=up&msg=%s&ping=%s" % (
        url, sep, urllib.parse.quote(doc.get("health_display", "")), doc.get("collect_ms", 0))
    req = urllib.request.Request(full, headers={"User-Agent": "os5-sysinfo/%s" % VERSION})
    try:
        with urllib.request.urlopen(req, timeout=5) as resp:
            resp.read(256)
    except Exception as exc:  # noqa: BLE001 - never let the heartbeat kill the loop
        log.warning("heartbeat failed: %s", exc)


# --------------------------------------------------------------------------
# Shared state + HTTP listener
# --------------------------------------------------------------------------

class State:
    def __init__(self):
        self.lock = threading.Lock()
        self.doc = None
        self.error = None
        self.listener = "disabled"
        self.output_error = None
        self.collect_count = 0

    def set_ok(self, doc):
        with self.lock:
            self.doc = doc
            self.error = None
            self.collect_count += 1

    def set_error(self, message):
        with self.lock:
            self.error = message

    def snapshot(self):
        with self.lock:
            doc = dict(self.doc) if self.doc else None
            error = self.error
        if doc is None:
            return None, error
        doc["age_seconds"] = max(0, int(time.time()) - int(doc.get("updated_epoch") or 0))
        if error:
            doc["error"] = error
        return doc, error


class Handler(BaseHTTPRequestHandler):
    server_version = "os5-sysinfo/" + VERSION
    sys_version = ""
    protocol_version = "HTTP/1.1"

    def address_string(self):  # no reverse DNS
        return self.client_address[0]

    def log_message(self, fmt, *args):
        log.debug("http %s " + fmt, self.address_string(), *args)

    def _send(self, code, payload):
        body = json.dumps(payload, indent=2, ensure_ascii=False).encode("utf-8") + b"\n"
        self.send_response(code)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        self.send_header("Cache-Control", "no-store")
        self.send_header("Access-Control-Allow-Origin", "*")
        self.end_headers()
        if self.command != "HEAD":
            self.wfile.write(body)

    def _authorized(self, query):
        key = self.server.cfg["API_KEY"]
        if not key:
            return True
        supplied = self.headers.get("X-Api-Key") or (query.get("key") or [""])[0]
        return bool(supplied) and hmac.compare_digest(supplied, key)

    def do_HEAD(self):
        self.do_GET()

    def do_GET(self):
        parsed = urllib.parse.urlsplit(self.path)
        query = urllib.parse.parse_qs(parsed.query)
        path = parsed.path.rstrip("/") or "/"
        if not self._authorized(query):
            self._send(401, {"error": "unauthorized"})
            return
        doc, error = self.server.state.snapshot()
        if path in ("/", "/status"):
            if doc is None:
                self._send(503, {"error": error or "no data yet"})
            else:
                self._send(503 if error else 200, doc)
        elif path == "/health":
            payload = {
                "status": "error" if (error or doc is None) else "ok",
                "health": doc["health"] if doc else None,
                "health_severity": doc["health_severity"] if doc else None,
                "age_seconds": doc["age_seconds"] if doc else None,
                "error": error if error else (None if doc else "no data yet"),
            }
            self._send(503 if payload["status"] == "error" else 200, payload)
        else:
            self._send(404, {"error": "not found"})


class Server(ThreadingHTTPServer):
    allow_reuse_address = True
    daemon_threads = True

    def __init__(self, addr, cfg, state):
        self.cfg = cfg
        self.state = state
        super().__init__(addr, Handler)


def listener_thread(cfg, state, stop):
    host, port = cfg["LISTEN_HOST"], cfg["LISTEN_PORT"]
    last_warn = 0
    while not stop.is_set():
        try:
            server = Server((host, port), cfg, state)
        except OSError as exc:
            state.listener = "bind failed on %s:%d: %s" % (host, port, exc)
            if time.time() - last_warn > 60:
                log.warning("%s (retrying)", state.listener)
                last_warn = time.time()
            stop.wait(15)
            continue
        state.listener = "listening on %s:%d%s" % (host, port, " (key required)" if cfg["API_KEY"] else "")
        log.info(state.listener)
        server.serve_forever(poll_interval=0.5)
        server.server_close()
        return


def setup_logging(conf_dir, to_stderr):
    log.setLevel(logging.INFO)
    fmt = logging.Formatter("%(asctime)s %(levelname)s %(message)s", "%Y-%m-%d %H:%M:%S")
    if to_stderr:
        handler = logging.StreamHandler(sys.stderr)
    else:
        handler = logging.handlers.RotatingFileHandler(
            os.path.join(conf_dir, "os5-sysinfo.log"), maxBytes=256 * 1024, backupCount=2)
    handler.setFormatter(fmt)
    log.addHandler(handler)


def main():
    parser = argparse.ArgumentParser(description="WD OS5 sysinfo collector")
    parser.add_argument("--conf", default=os.environ.get("SYSINFO_CONF_DIR") or
                        os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "os5-sysinfo_conf"))
    parser.add_argument("--once", action="store_true", help="collect once, print JSON, exit")
    args = parser.parse_args()

    conf_dir = os.path.abspath(args.conf)
    os.makedirs(conf_dir, exist_ok=True)
    setup_logging(conf_dir, to_stderr=args.once)
    cfg = load_config(conf_dir)
    state = State()
    collector = Collector(conf_dir, cfg)
    collector.sweep_temp_files()

    if args.once:
        try:
            doc = collector.build_status(state)
        except Exception as exc:  # noqa: BLE001
            print(json.dumps({"error": str(exc)}, indent=2))
            return 1
        print(json.dumps(doc, indent=2, ensure_ascii=False))
        return 1 if doc.get("error") else 0

    stop = threading.Event()

    def on_signal(signum, _frame):
        log.info("signal %d, stopping", signum)
        stop.set()

    signal.signal(signal.SIGTERM, on_signal)
    signal.signal(signal.SIGINT, on_signal)

    log.info("os5-sysinfo %s starting: interval=%ss listener=%s output=%s", VERSION,
             cfg["INTERVAL"], "%s:%s" % (cfg["LISTEN_HOST"], cfg["LISTEN_PORT"]) if cfg["LISTENER"] else "off",
             cfg["OUTPUT_PATH"] or "none")
    thread = None
    if cfg["LISTENER"]:
        thread = threading.Thread(target=listener_thread, args=(cfg, state, stop), daemon=True)
        thread.start()

    private_path = os.path.join(conf_dir, "status.json")
    while not stop.is_set():
        started = time.monotonic()
        ok = False
        try:
            doc = collector.build_status(state)
            state.set_ok(doc)
            ok = True
        except Exception as exc:  # noqa: BLE001
            log.exception("collect failed")
            state.set_error(str(exc))

        doc, _ = state.snapshot()
        if doc is not None:
            try:
                write_json_atomic(private_path, doc)
            except OSError as exc:
                log.error("cannot write %s: %s", private_path, exc)
            if cfg["OUTPUT_PATH"]:
                try:
                    write_json_atomic(cfg["OUTPUT_PATH"], doc)
                    if state.output_error:
                        log.info("output file %s writable again", cfg["OUTPUT_PATH"])
                    state.output_error = None
                except OSError as exc:
                    if state.output_error != str(exc):
                        log.error("cannot write %s: %s", cfg["OUTPUT_PATH"], exc)
                    state.output_error = str(exc)
            if ok and cfg["HEARTBEAT_URL"]:
                send_heartbeat(cfg["HEARTBEAT_URL"], doc)

        elapsed = time.monotonic() - started
        stop.wait(max(1.0, cfg["INTERVAL"] - elapsed))

    log.info("stopped after %d collections", state.collect_count)
    return 0


if __name__ == "__main__":
    sys.exit(main())
