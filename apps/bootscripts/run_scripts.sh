#!/bin/sh
#
# Runs every *.sh in <persistent dir>/scripts.d, in name order, logging to
# <persistent dir>/bootscripts.log. Called by start.sh (boot / enable) and by
# the "Run now" button on the app page. Safe to run by hand:
#
#   sh /mnt/HD/HD_a2/Nas_Prog/bootscripts/run_scripts.sh /mnt/HD/HD_a2/Nas_Prog/bootscripts_conf
#
# Plain BusyBox sh; no helpers.sh so it can be run from anywhere.

BOOTSCRIPTS_DIR="$1"
BOOTSCRIPTS_REASON="${2:-manual}"
SCRIPT_TIMEOUT="${BOOTSCRIPTS_TIMEOUT:-300}"	# seconds per script
MAX_LOG_LINES=2000

if [ -z "${BOOTSCRIPTS_DIR}" ] || [ ! -d "${BOOTSCRIPTS_DIR}" ]; then
	echo "usage: $0 <persistent dir> [reason]" >&2
	exit 1
fi

SCRIPTS_DIR="${BOOTSCRIPTS_DIR}/scripts.d"
BOOTSCRIPTS_LOG="${BOOTSCRIPTS_DIR}/bootscripts.log"
LOCK_DIR="${BOOTSCRIPTS_DIR}/.run.lock"
export BOOTSCRIPTS_DIR BOOTSCRIPTS_LOG BOOTSCRIPTS_REASON
export PATH="${PATH}:/opt/bin:/opt/sbin:/opt/usr/bin:/usr/local/sbin:/usr/local/bin"

log() {
	echo "$(date '+%Y-%m-%d %H:%M:%S') $*" >> "${BOOTSCRIPTS_LOG}"
}

mkdir -p "${SCRIPTS_DIR}"
touch "${BOOTSCRIPTS_LOG}"

# Keep the log from growing forever
if [ "$(wc -l < "${BOOTSCRIPTS_LOG}")" -gt "${MAX_LOG_LINES}" ]; then
	tail -n $((MAX_LOG_LINES / 2)) "${BOOTSCRIPTS_LOG}" > "${BOOTSCRIPTS_LOG}.tmp" \
		&& mv "${BOOTSCRIPTS_LOG}.tmp" "${BOOTSCRIPTS_LOG}"
fi

# One run at a time (mkdir is atomic); recover from a stale lock
if ! mkdir "${LOCK_DIR}" 2>/dev/null; then
	OLD_PID="$(cat "${LOCK_DIR}/pid" 2>/dev/null)"
	if [ -n "${OLD_PID}" ] && kill -0 "${OLD_PID}" 2>/dev/null; then
		log "=== skipped (${BOOTSCRIPTS_REASON}): a run is already in progress (pid ${OLD_PID})"
		exit 0
	fi
	rm -rf "${LOCK_DIR}"
	mkdir "${LOCK_DIR}" || exit 1
fi
echo $$ > "${LOCK_DIR}/pid"
trap 'rm -rf "${LOCK_DIR}"' EXIT INT TERM

log "=== run started (${BOOTSCRIPTS_REASON}) in ${SCRIPTS_DIR}"
total=0
failed=0

for script in "${SCRIPTS_DIR}"/*.sh; do
	[ -f "${script}" ] || continue
	total=$((total + 1))
	name="$(basename "${script}")"
	log "--- ${name}"

	# Run directly if executable (honours its #! line), otherwise with sh
	if [ -x "${script}" ]; then
		"${script}" >> "${BOOTSCRIPTS_LOG}" 2>&1 &
	else
		sh "${script}" >> "${BOOTSCRIPTS_LOG}" 2>&1 &
	fi
	pid=$!

	# Portable timeout: BusyBox's `timeout` syntax differs between versions
	waited=0
	while kill -0 "${pid}" 2>/dev/null && [ "${waited}" -lt "${SCRIPT_TIMEOUT}" ]; do
		sleep 1
		waited=$((waited + 1))
	done

	if kill -0 "${pid}" 2>/dev/null; then
		kill "${pid}" 2>/dev/null
		sleep 2
		kill -9 "${pid}" 2>/dev/null
		rc=124
		log "--- ${name}: KILLED after ${SCRIPT_TIMEOUT}s"
	else
		wait "${pid}"
		rc=$?
	fi

	if [ "${rc}" -eq 0 ]; then
		log "--- ${name}: ok (${waited}s)"
	else
		failed=$((failed + 1))
		log "--- ${name}: FAILED rc=${rc} (${waited}s)"
	fi
done

if [ "${total}" -eq 0 ]; then
	log "=== nothing to run: no *.sh in ${SCRIPTS_DIR}"
else
	log "=== run finished: ${total} script(s), ${failed} failed"
fi
[ "${failed}" -eq 0 ]
