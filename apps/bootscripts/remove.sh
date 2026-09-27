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
# Removes the app completely.
#  - The persistent directory (scripts.d, ssh keys, log) is deliberately kept,
#    so a reinstall / upgrade picks your scripts straight back up.
# ----------------------------------------------------------------------------

log "Removing all files from: ${APP_PATH} (keeping ${APP_PERSISTENT_DATA_PATH})";
rm -rf "${APP_PATH}";
