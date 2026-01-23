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
# Starts the app when enabled (e.g. on boot)
#  - When no index page is defined in apkg.rc, the enable button is greyed out
# ----------------------------------------------------------------------------

# Load all the useful variables
. "$1/helpers.sh" "$0" "$1";

ST_BIN="${APP_PATH}/syncthing"
PID_FILE="/var/run/${APP_NAME}.pid"

log "starting syncthing..."

# Debug: Print version to main log
log "Checking binary version..."
${ST_BIN} --version >> ${LOG} 2>&1

# Syncthing arguments:
# -home: config directory
# -no-browser: don't open browser
# -gui-address: listen address
# -logfile: log file path (optional, but good for debugging)

export HOME="${APP_PERSISTENT_DATA_PATH}"
export STNOUPGRADE=1

log "COMMAND: ${ST_BIN} -home=${APP_PERSISTENT_DATA_PATH} -no-browser -gui-address=0.0.0.0:8384"

nohup ${ST_BIN} \
    --home="${APP_PERSISTENT_DATA_PATH}" \
    --no-browser \
    --gui-address="0.0.0.0:8384" \
    > "${APP_PATH}/syncthing.log" 2>&1 &

echo $! > ${PID_FILE}

log "...app started with PID: $(cat ${PID_FILE})"
