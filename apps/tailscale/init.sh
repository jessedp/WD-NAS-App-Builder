#!/bin/sh

# Called upon app installation
	#	 1. $UPLOAD_PATH/install.sh $UPLOAD_PATH $INSTALL_PATH
	# -> 2. $INSTALL_PATH/init.sh $INSTALL_PATH
	#	 3. $INSTALL_PATH/start.sh $INSTALL_PATH

# Called upon app reinstallation
	#	 1. $INSTALL_PATH/stop.sh
	#	 2. $INSTALL_PATH/clean.sh
	#	 3. $INSTALL_PATH/preinst.sh $INSTALL_PATH
	#	 4. $INSTALL_PATH/remove.sh $INSTALL_PATH
	#	 5. $UPLOAD_PATH/install.sh $UPLOAD_PATH $INSTALL_PATH
	# -> 6. $INSTALL_PATH/init.sh $INSTALL_PATH
	#	 7. $INSTALL_PATH/start.sh $INSTALL_PATH

# Load all the useful variables
. "$1/helpers.sh" "$0" "$1";

# ----------------------------------------------------------------------
# Initialisation script: prepares app icon, index page and paths
#  - Use this to restore custom paths and settings on reinstall / reboot
# ----------------------------------------------------------------------

# Create folder for the webpage
log "creating web path: /var/www/apps/tailscale"
mkdir -p /var/www/apps/tailscale

# Create persistent configuration directory
if [ ! -d "${APP_PERSISTENT_DATA_PATH}" ]; then
    log "Creating persistent configuration directory: ${APP_PERSISTENT_DATA_PATH}"
    mkdir -p "${APP_PERSISTENT_DATA_PATH}"
fi

log "linking redirect page from: ${APP_PATH}/web/* to: /var/www/apps/tailscale/"
ln -sf ${APP_PATH}/web/* /var/www/apps/tailscale/ >> ${LOG} 2>&1
