#!/bin/sh
#
# Re-add your own lines to root's crontab after a reboot.
#
# OS5 regenerates /var/spool/cron/crontabs/root at boot, so anything you append
# to it is gone after a reboot. Put the crontab lines you want kept in
#
#     <bootscripts_conf>/crontab.root
#
# (one job per line, comments and blank lines ignored) and this script appends
# any that are missing. The file is rewritten in place so BusyBox crond notices
# the change.

SRC="${BOOTSCRIPTS_DIR:-$(cd "$(dirname "$0")/.." && pwd)}/crontab.root"
CRONTAB="/var/spool/cron/crontabs/root"

if [ ! -s "${SRC}" ]; then
	echo "no ${SRC}, nothing to do"
	exit 0
fi

mkdir -p "$(dirname "${CRONTAB}")"
touch "${CRONTAB}"
cp "${CRONTAB}" "${CRONTAB}.tmp" || exit 1

added=0
while IFS= read -r line || [ -n "${line}" ]; do
	# skip blank lines and comments (leading whitespace allowed)
	trimmed="${line#"${line%%[![:space:]]*}"}"
	case "${trimmed}" in
		''|'#'*) continue ;;
	esac
	if ! grep -qxF -- "${line}" "${CRONTAB}.tmp"; then
		echo "${line}" >> "${CRONTAB}.tmp"
		added=$((added + 1))
	fi
done < "${SRC}"

if [ "${added}" -gt 0 ]; then
	mv "${CRONTAB}.tmp" "${CRONTAB}"
	chmod 600 "${CRONTAB}"
else
	rm -f "${CRONTAB}.tmp"
fi

echo "crontab: ${added} line(s) added"
