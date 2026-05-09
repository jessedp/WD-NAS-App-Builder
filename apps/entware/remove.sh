#!/bin/sh

# Called upon app removal
	#	 1. $INSTALL_PATH/stop.sh
	#	 2. $INSTALL_PATH/clean.sh
	# -> 3. $INSTALL_PATH/remove.sh $INSTALL_PATH

# Load all the useful variables
. "$1/helpers.sh" "$0" "$1";

# Remove update check cron entry before wiping app files
CRONTAB="/var/spool/cron/crontabs/root"
if [ -f "${CRONTAB}" ]; then
    sed -i "/${APP_NAME}-update-check/d" "${CRONTAB}"
    log "Removed update check cron job"
fi

log "Removing Entware app files..."
rm -rf "${APP_PATH}";

# Note: We do NOT remove APP_PERSISTENT_DATA_PATH (Persistent data)
# Use manual cleanup if desired.
log "Entware root at ${APP_PERSISTENT_DATA_PATH} PRESERVED."
