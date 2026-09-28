#!/bin/bash
# Local smoke test for the os5-sysinfo collector. Runs off-device against fixtures.
#   tests/os5-sysinfo/test.sh
set -euo pipefail
HERE="$(cd "$(dirname "$0")" && pwd)"
ROOT="$(cd "${HERE}/../.." && pwd)"
COLLECTOR="${ROOT}/apps/os5-sysinfo/sysinfo_collector.py"
TMP="$(mktemp -d)"
cleanup() { if [ "${SERVER_PID:-0}" -gt 0 ]; then kill "${SERVER_PID}" 2>/dev/null || true; fi; rm -rf "${TMP}"; }
trap cleanup EXIT
PY="${PYTHON:-python3}"
fail() { echo "FAIL: $*" >&2; exit 1; }
jq_get() { "${PY}" -c "import json,sys; d=json.load(sys.stdin); print(eval(sys.argv[1], {'d': d}))" "$1"; }

echo "== py_compile"
"${PY}" -m py_compile "${COLLECTOR}"

echo "== healthy PR4100 fixtures (--once)"
mkdir -p "${TMP}/c1"
OUT="$(SYSINFO_FIXTURES="${HERE}/fixtures" "${PY}" "${COLLECTOR}" --conf "${TMP}/c1" --once)"
echo "${OUT}" | jq_get "len(d['disks'])" | grep -qx 4 || fail "expected 4 disks"
echo "${OUT}" | jq_get "len(d['raids'])" | grep -qx 2 || fail "expected 2 raids"
echo "${OUT}" | jq_get "d['raids'][0]['is_system']" | grep -qx True || fail "md0 should be is_system"
echo "${OUT}" | jq_get "d['health_display']" | grep -qx "RAID5 • Healthy" || fail "health_display"
echo "${OUT}" | jq_get "d['smart_display']" | grep -qx "Not run" || fail "smart_display"
echo "${OUT}" | jq_get "'serial' in d['disks'][0]" | grep -qx False || fail "serial must be absent by default"
echo "${OUT}" | jq_get "d['disks'][0]['model']" | grep -q "WD60EFRX" || fail "model present by default"
echo "${OUT}" | jq_get "d['system']['cpu_temp_c']" | grep -qx 49 || fail "cpu temp"
echo "${OUT}" | jq_get "d['storage_display']" | grep -qx "7.90 TB / 17.92 TB" || fail "storage_display"
echo "${OUT}" | jq_get "d['cpu'] > 0" | grep -qx True || fail "cpu percent from two samples"
echo "${OUT}" | jq_get "d['error']" | grep -qx None || fail "no error expected"
echo "   ok: $(echo "${OUT}" | jq_get "d['health_display'] + ' | ' + d['temperature_display'] + ' | ' + d['ram_display']")"

echo "== serials on"
printf 'INCLUDE_SERIALS=1\nINCLUDE_MODELS=0\n' > "${TMP}/c1/os5-sysinfo.conf"
OUT="$(SYSINFO_FIXTURES="${HERE}/fixtures" "${PY}" "${COLLECTOR}" --conf "${TMP}/c1" --once)"
echo "${OUT}" | jq_get "d['disks'][1]['serial']" | grep -q "WD-WX11D278ZZ2J" || fail "serial expected"
echo "${OUT}" | jq_get "'model' in d['disks'][0]" | grep -qx False || fail "model must be absent when off"

echo "== degraded fixtures"
mkdir -p "${TMP}/c2"
OUT="$(SYSINFO_FIXTURES="${HERE}/fixtures-degraded" "${PY}" "${COLLECTOR}" --conf "${TMP}/c2" --once)"
echo "${OUT}" | jq_get "d['health']" | grep -qx Critical || fail "degraded fixtures should be Critical"
echo "${OUT}" | jq_get "d['health_display']" | grep -q "RAID5 • Critical (degraded)" || fail "health_display degraded"
echo "${OUT}" | jq_get "d['disks'][1]['status']" | grep -qx failed || fail "sdb failed"

echo "== arm single-bay fixtures"
mkdir -p "${TMP}/c3"
OUT="$(SYSINFO_FIXTURES="${HERE}/fixtures-arm" "${PY}" "${COLLECTOR}" --conf "${TMP}/c3" --once)"
echo "${OUT}" | jq_get "d['system']['cpu_temp_c']" | grep -qx None || fail "arm: no cpu temp"
echo "${OUT}" | jq_get "d['health_display']" | grep -qx "Disk • Healthy" || fail "arm: Disk • Healthy"
echo "${OUT}" | jq_get "d['temperature_display']" | grep -qx "Disks 40°C" || fail "arm: temperature_display"

echo "== listener with key"
mkdir -p "${TMP}/c4"
PORT=18085
cat > "${TMP}/c4/os5-sysinfo.conf" <<CONF
LISTENER=1
LISTEN_HOST=127.0.0.1
LISTEN_PORT=${PORT}
API_KEY=testkey0123456789
INTERVAL=5
OUTPUT_PATH=${TMP}/share/os5-sysinfo/status.json
CONF
SYSINFO_FIXTURES="${HERE}/fixtures" "${PY}" "${COLLECTOR}" --conf "${TMP}/c4" &
SERVER_PID=$!
# the listener comes up before the first collection finishes: wait for a 200
for _ in $(seq 1 40); do [ "$(curl -s -o /dev/null -w '%{http_code}' -H 'X-Api-Key: testkey0123456789' "http://127.0.0.1:${PORT}/status")" = 200 ] && break; sleep 0.5; done
code="$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:${PORT}/status")"; [ "${code}" = 401 ] || fail "no key should be 401, got ${code}"
code="$(curl -s -o /dev/null -w '%{http_code}' -H 'X-Api-Key: wrongkey000000000' "http://127.0.0.1:${PORT}/status")"; [ "${code}" = 401 ] || fail "wrong key should be 401"
curl -s -H 'X-Api-Key: testkey0123456789' "http://127.0.0.1:${PORT}/status" | jq_get "d['health']" | grep -qx Healthy || fail "status with key"
code="$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:${PORT}/health?key=testkey0123456789")"; [ "${code}" = 200 ] || fail "/health should be 200"
code="$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:${PORT}/nope?key=testkey0123456789")"; [ "${code}" = 404 ] || fail "unknown path 404"
code="$(curl -s -o /dev/null -w '%{http_code}' -I -H 'X-Api-Key: testkey0123456789' "http://127.0.0.1:${PORT}/status")"; [ "${code}" = 200 ] || fail "HEAD 200"
[ -s "${TMP}/c4/status.json" ] || fail "private status.json not written"
[ -s "${TMP}/share/os5-sysinfo/status.json" ] || fail "OUTPUT_PATH not written"
grep -q "listening on 127.0.0.1:${PORT} (key required)" "${TMP}/c4/os5-sysinfo.log" || fail "listener log line"
kill -TERM "${SERVER_PID}"; wait "${SERVER_PID}" || true; SERVER_PID=0
grep -q "stopped after" "${TMP}/c4/os5-sysinfo.log" || fail "clean shutdown log line"
ls "${TMP}/c4"/xmldbc-*.xml 2>/dev/null && fail "temp files left behind"
[ -z "$(ls -A "${TMP}/c4" | grep '^\.status-')" ] || fail "tmp json left behind"

echo "== listener open (no key), port conflict retry path"
mkdir -p "${TMP}/c5"
printf 'LISTENER=1\nLISTEN_HOST=127.0.0.1\nLISTEN_PORT=%s\nINTERVAL=5\n' "${PORT}" > "${TMP}/c5/os5-sysinfo.conf"
SYSINFO_FIXTURES="${HERE}/fixtures" "${PY}" "${COLLECTOR}" --conf "${TMP}/c5" &
SERVER_PID=$!
for _ in $(seq 1 40); do [ "$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:${PORT}/status")" = 200 ] && break; sleep 0.5; done
code="$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:${PORT}/status")"; [ "${code}" = 503 ] && fail "first collection never completed"
curl -s "http://127.0.0.1:${PORT}/status" | jq_get "d['system']['listener']" | grep -q "listening on" || fail "open listener"
kill -TERM "${SERVER_PID}"; wait "${SERVER_PID}" || true; SERVER_PID=0

echo "ALL TESTS PASSED"
