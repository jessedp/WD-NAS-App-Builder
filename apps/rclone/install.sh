#!/bin/sh

# Called upon app installation
	# -> 1. $UPLOAD_PATH/install.sh $UPLOAD_PATH $INSTALL_PATH
	#	 2. $INSTALL_PATH/init.sh $INSTALL_PATH
	#	 3. $INSTALL_PATH/start.sh $INSTALL_PATH

# Called upon app reinstallation
	#	 1. $INSTALL_PATH/stop.sh
	#	 2. $INSTALL_PATH/clean.sh
	#	 3. $INSTALL_PATH/preinst.sh $INSTALL_PATH
	#	 4. $INSTALL_PATH/remove.sh $INSTALL_PATH
	# -> 5. $UPLOAD_PATH/install.sh $UPLOAD_PATH $INSTALL_PATH
	#	 6. $INSTALL_PATH/init.sh $INSTALL_PATH
	#	 7. $INSTALL_PATH/start.sh $INSTALL_PATH

# Load all the useful variables
. "$1/helpers.sh" "$0" "$1" "$2";

# ----------------------------------------------------------------------------------
# Install script: moves the required files to a permanent home
#  - Use this to must move the shell files (and other resources) to the install path
# ----------------------------------------------------------------------------------

# Move the files from the temporary upload directory to the app directory
log "Moving files from: ${APP_UPLOAD_PATH} to: ${APPS_PATH}";
mv -f "${APP_UPLOAD_PATH}" "${APPS_PATH}";
