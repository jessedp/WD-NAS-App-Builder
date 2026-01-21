#!/bin/sh

# Load all the useful variables
. "$1/helpers.sh" "$0" "$1";

PID_FILE="/var/run/${APP_NAME}.pid"

log "Stopping copyparty..."

if [ -f "${PID_FILE}" ]; then
    PID=$(cat "${PID_FILE}")
    log "Killing PID: ${PID}"
    kill "${PID}"
    rm -f "${PID_FILE}"
else
    log "PID file not found, trying pkill"
    pkill -f "copyparty-sfx.py"
fi