#!/bin/sh
#
# Keep root's SSH authorized_keys across reboots.
#
# OS5 rebuilds the root filesystem on every boot, so any key you add with
# ssh-copy-id lands in a file that vanishes at the next reboot. This script
# keeps a persistent copy at
#
#     <bootscripts_conf>/ssh/authorized_keys
#
# and, each time it runs, merges that with whatever is live in root's ~/.ssh
# and writes the union back to BOTH places. So:
#
#   - to add a key without SSH: paste it into the persistent file (the app page
#     has an editor for it) and press "Run now" - or just wait for the next boot
#   - to add a key with ssh-copy-id: run ssh-copy-id, then press "Run now" so the
#     new key is saved before the next reboot
#   - to remove a key: delete it from the persistent file, then delete it from
#     the live file too (or reboot)
#
# Set BOOTSCRIPTS_ROOT_HOME to override where root's home is (testing only).

PERSIST_DIR="${BOOTSCRIPTS_DIR:-$(cd "$(dirname "$0")/.." && pwd)}/ssh"
PERSIST="${PERSIST_DIR}/authorized_keys"

ROOT_HOME="${BOOTSCRIPTS_ROOT_HOME:-$(awk -F: '$1 == "root" { print $6; exit }' /etc/passwd)}"
if [ -z "${ROOT_HOME}" ]; then
	ROOT_HOME="/home/root"
fi
LIVE_DIR="${ROOT_HOME}/.ssh"
LIVE="${LIVE_DIR}/authorized_keys"

umask 077
mkdir -p "${PERSIST_DIR}" "${LIVE_DIR}" || exit 1
chmod 700 "${PERSIST_DIR}" "${LIVE_DIR}"
touch "${PERSIST}" "${LIVE}"

# Union of both files, first-seen order, blank lines dropped
cat "${PERSIST}" "${LIVE}" | awk 'NF && !seen[$0]++' > "${PERSIST}.tmp" || exit 1

keys="$(grep -vc '^[[:space:]]*#' "${PERSIST}.tmp")"
if [ "${keys}" -eq 0 ]; then
	rm -f "${PERSIST}.tmp"
	echo "no keys yet: add some to ${PERSIST} (or use ssh-copy-id, then run again)"
	exit 0
fi

mv "${PERSIST}.tmp" "${PERSIST}"
cp "${PERSIST}" "${LIVE}"
chmod 600 "${PERSIST}" "${LIVE}"
chown root:root "${LIVE_DIR}" "${LIVE}" 2>/dev/null

echo "authorized_keys: ${keys} key(s) in sync -> ${LIVE}"
