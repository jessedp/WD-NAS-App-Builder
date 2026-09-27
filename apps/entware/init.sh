#!/bin/sh

# Load all the useful variables
. "$1/helpers.sh" "$0" "$1";

# ----------------------------------------------------------------------
# Initialisation script
# ----------------------------------------------------------------------

# Create folder for the webpage
log "creating web path: /var/www/apps/entware"
mkdir -p /var/www/apps/entware

OPT_ROOT="${APP_PERSISTENT_DATA_PATH}"

log "Mounting ${OPT_ROOT} to /opt"
if ! mount | grep -q "on /opt type"; then
    mount --bind "${OPT_ROOT}" /opt
fi

# Add Entware paths to PATH in profile
# Note: These modifications will persist until reboot as the rootfs is volatile.
# We do not remove them on app stop/removal as other apps might depend on them.
if ! grep -q "/opt/bin" /etc/profile; then
    log "Adding /opt/bin, /opt/sbin, and /opt/usr/bin to /etc/profile PATH"
    echo 'export PATH=$PATH:/opt/bin:/opt/sbin:/opt/usr/bin' >> /etc/profile
fi

# Link web content
log "Linking web content"
rm -rf /var/www/apps/entware
ln -sf ${APP_PATH}/web /var/www/apps/entware >> ${LOG} 2>&1

# Weekly update check in root's crontab (tagged for clean removal).
# This lives in init.sh, not install.sh: OS5 regenerates /var/spool/cron/crontabs/root
# on every boot, and init.sh is re-run on every boot, so this is what keeps the job alive.
# Written via a temp file + mv so BusyBox crond notices the directory change.
CRONTAB="/var/spool/cron/crontabs/root"
CRON_TAG="${APP_NAME}-update-check"
if [ -f "${CRONTAB}" ] && ! grep -q "${CRON_TAG}" "${CRONTAB}"; then
    cp "${CRONTAB}" "${CRONTAB}.tmp" \
        && echo "0 6 * * 1 ${APP_PATH}/check_update.sh # ${CRON_TAG}" >> "${CRONTAB}.tmp" \
        && mv "${CRONTAB}.tmp" "${CRONTAB}" \
        && log "Added update check cron job"
fi
