#!/bin/sh

# Called upon app removal
	#	 1. $INSTALL_PATH/stop.sh
	# -> 2. $INSTALL_PATH/clean.sh
	#	 3. $INSTALL_PATH/remove.sh $INSTALL_PATH

# Called upon app reinstallation
	#	 1. $INSTALL_PATH/stop.sh
	# -> 2. $INSTALL_PATH/clean.sh
	#	 3. $INSTALL_PATH/preinst.sh $INSTALL_PATH
	#	 4. $INSTALL_PATH/remove.sh $INSTALL_PATH
	#	 5. $UPLOAD_PATH/install.sh $UPLOAD_PATH $INSTALL_PATH
	#	 6. $INSTALL_PATH/init.sh $INSTALL_PATH
	#	 7. $INSTALL_PATH/start.sh $INSTALL_PATH

# Load all the useful variables
. "$1/helpers.sh" "$0" "$1";

# --------------------------------------------------------------------
# Cleanup script to revert the init.sh steps. Ensures a clean shutdown
# --------------------------------------------------------------------

# Remove the web path
log "removing web path: ${APP_WEB_PATH}"
rm -rf ${APP_WEB_PATH}
