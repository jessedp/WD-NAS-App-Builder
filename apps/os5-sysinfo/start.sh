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
# Starts the collector when enabled (e.g. on boot)
# ----------------------------------------------------------------------------

# Load all the useful variables
. "$1/helpers.sh" "$0" "$1";

PID_FILE="/var/run/${APP_NAME}.pid"
COLLECTOR="${APP_PATH}/sysinfo_collector.py"
OUT_FILE="${APP_PERSISTENT_DATA_PATH}/os5-sysinfo.out"

if [ -f "${PID_FILE}" ] && kill -0 "$(cat "${PID_FILE}" 2>/dev/null)" 2>/dev/null; then
	log "already running with PID $(cat "${PID_FILE}")"
	exit 0
fi

if ! command -v python3 >/dev/null 2>&1; then
	log "ERROR: python3 not found on this device; the collector cannot run"
	exit 1
fi

mkdir -p "${APP_PERSISTENT_DATA_PATH}"
: > "${OUT_FILE}"
log "starting collector: python3 ${COLLECTOR} --conf ${APP_PERSISTENT_DATA_PATH}"
nohup python3 "${COLLECTOR}" --conf "${APP_PERSISTENT_DATA_PATH}" >> "${OUT_FILE}" 2>&1 &
echo $! > "${PID_FILE}"
log "collector started with PID $(cat "${PID_FILE}")"
