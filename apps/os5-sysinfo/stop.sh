#!/bin/sh

# Called when app is disabled
	# -> 1. $INSTALL_PATH/stop.sh

# Called upon app removal
	# -> 1. $INSTALL_PATH/stop.sh
	#	 2. $INSTALL_PATH/clean.sh
	#	 3. $INSTALL_PATH/remove.sh $INSTALL_PATH

# Called upon app reinstallation
	# -> 1. $INSTALL_PATH/stop.sh
	#	 2. $INSTALL_PATH/clean.sh
	#	 3. $INSTALL_PATH/preinst.sh $INSTALL_PATH
	#	 4. $INSTALL_PATH/remove.sh $INSTALL_PATH
	#	 5. $UPLOAD_PATH/install.sh $UPLOAD_PATH $INSTALL_PATH
	#	 6. $INSTALL_PATH/init.sh $INSTALL_PATH
	#	 7. $INSTALL_PATH/start.sh $INSTALL_PATH

# Load all the useful variables
. "$1/helpers.sh" "$0" "$1";

# -----------------------------------------------------------------------------
# Stops the collector
# -----------------------------------------------------------------------------

PID_FILE="/var/run/${APP_NAME}.pid"

if [ -f "${PID_FILE}" ]; then
	PID="$(cat "${PID_FILE}" 2>/dev/null)"
	if [ -n "${PID}" ] && kill -0 "${PID}" 2>/dev/null; then
		log "stopping collector PID ${PID}"
		kill "${PID}" 2>/dev/null
		waited=0
		while kill -0 "${PID}" 2>/dev/null && [ "${waited}" -lt 5 ]; do
			sleep 1
			waited=$((waited + 1))
		done
		if kill -0 "${PID}" 2>/dev/null; then
			log "collector did not exit, killing -9"
			kill -9 "${PID}" 2>/dev/null
		fi
	fi
	rm -f "${PID_FILE}"
else
	log "no pidfile; stopping any stray collector"
	pkill -f "sysinfo_collector.py" 2>/dev/null
fi
log "stopped"
