#!/bin/sh

# Called when app is enabled
	# -> 1. $INSTALL_PATH/start.sh

# Called upon app installation
	#	 1. $UPLOAD_PATH/install.sh $UPLOAD_PATH $INSTALL_PATH
	#	 2. $INSTALL_PATH/init.sh $INSTALL_PATH
	# -> 3. $INSTALL_PATH/start.sh $INSTALL_PATH

# Called upon app reinstallation
	#	 1. $INSTALL_PATH/stop.sh
	#	 2. $INSTALL_PATH/clean.sh
	#	 3. $INSTALL_PATH/preinst.sh $INSTALL_PATH
	#	 4. $INSTALL_PATH/remove.sh $INSTALL_PATH
	#	 5. $UPLOAD_PATH/install.sh $UPLOAD_PATH $INSTALL_PATH
	#	 6. $INSTALL_PATH/init.sh $INSTALL_PATH
	# -> 7. $INSTALL_PATH/start.sh $INSTALL_PATH

# ----------------------------------------------------------------------------
# Starts the app when enabled (e.g. on boot): runs everything in scripts.d
#  - Runs in the background so a slow script never holds up the other apps
# ----------------------------------------------------------------------------

# Load all the useful variables
. "$1/helpers.sh" "$0" "$1";

log "running scripts from: ${APP_PERSISTENT_DATA_PATH}/scripts.d (log: ${APP_PERSISTENT_DATA_PATH}/bootscripts.log)"
sh "${APP_PATH}/run_scripts.sh" "${APP_PERSISTENT_DATA_PATH}" start > /dev/null 2>&1 &
