#!/bin/sh

# Called upon app removal
	#	 1. $INSTALL_PATH/stop.sh
	#	 2. $INSTALL_PATH/clean.sh
	# -> 3. $INSTALL_PATH/remove.sh $INSTALL_PATH

# Called upon app reinstallation
	#	 1. $INSTALL_PATH/stop.sh
	#	 2. $INSTALL_PATH/clean.sh
	#	 3. $INSTALL_PATH/preinst.sh $INSTALL_PATH
	# -> 4. $INSTALL_PATH/remove.sh $INSTALL_PATH
	#	 5. $UPLOAD_PATH/install.sh $UPLOAD_PATH $INSTALL_PATH
	#	 6. $INSTALL_PATH/init.sh $INSTALL_PATH
	#	 7. $INSTALL_PATH/start.sh $INSTALL_PATH

# Load all the useful variables
. "$1/helpers.sh" "$0" "$1";

# ----------------------------------------------------------------------------
# Removes all the app completely. 
#  - Remember to make backups of configuration / data here to support upgrades
# ----------------------------------------------------------------------------

# Remove update check cron entry before wiping app files
CRONTAB="/var/spool/cron/crontabs/root"
if [ -f "${CRONTAB}" ]; then
    sed -i "/${APP_NAME}-update-check/d" "${CRONTAB}"
    log "Removed update check cron job"
fi

log "Removing all files from: ${APP_PATH}";
rm -rf "${APP_PATH}";