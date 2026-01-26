#!/bin/sh

# Load all the useful variables
. "$1/helpers.sh" "$0" "$1";

# Create folder for the webpage
log "creating web path: ${APP_WEB_PATH}"
mkdir -p ${APP_WEB_PATH}

# Create persistent configuration directory
# Rclone usually uses ~/.config/rclone/rclone.conf
# We will map XDG_CONFIG_HOME to ${APP_PERSISTENT_DATA_PATH}/config
if [ ! -d "${APP_PERSISTENT_DATA_PATH}/config" ]; then
    log "Creating persistent configuration directory: ${APP_PERSISTENT_DATA_PATH}/config"
    mkdir -p "${APP_PERSISTENT_DATA_PATH}/config"
fi

log "linking redirect page from: ${APP_PATH}/web/* to: ${APP_WEB_PATH}"
ln -sf ${APP_PATH}/web/* ${APP_WEB_PATH} >> ${LOG} 2>&1