#!/bin/sh

# Load all the useful variables
. "$1/helpers.sh" "$0" "$1";

CP_BIN="${APP_PATH}/copyparty-sfx.py"
CONFIG_FILE="${APPS_PATH}/copyparty_conf/copyparty.conf"
PID_FILE="/var/run/${APP_NAME}.pid"

log "Starting copyparty..."

if [ ! -f "${CP_BIN}" ]; then
    log "ERROR: ${CP_BIN} not found!"
    exit 1
fi

# Run copyparty
# -c: config file
# -p: port
# --p: create more workers if needed
# -q: quiet (optional, but good for logs)

nohup python3 "${CP_BIN}" \
    -c "${CONFIG_FILE}" \
    -p 3923 \
    > "${APP_PATH}/copyparty.log" 2>&1 &

echo $! > ${PID_FILE}

log "...app started with PID: $(cat ${PID_FILE})"