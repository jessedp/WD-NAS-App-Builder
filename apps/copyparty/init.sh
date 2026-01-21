#!/bin/sh

# Load all the useful variables
. "$1/helpers.sh" "$0" "$1";

# Create folder for the webpage
log "creating web path: ${APP_WEB_PATH}"
mkdir -p ${APP_WEB_PATH}

# Create persistent configuration directory
CONFIG_DIR="${APPS_PATH}/copyparty_conf"
if [ ! -d "${CONFIG_DIR}" ]; then
    log "Creating persistent configuration directory: ${CONFIG_DIR}"
    mkdir -p "${CONFIG_DIR}"
fi

# Create default config if it doesn't exist
CONFIG_FILE="${CONFIG_DIR}/copyparty.conf"
if [ ! -f "${CONFIG_FILE}" ]; then
    log "Creating default config file: ${CONFIG_FILE}"
    cat <<EOF > "${CONFIG_FILE}"
[global]
  e2d, e2t         # remember uploads & read media tags
  rss, daw, ver    # some other nice-to-have features
  #dedup            # you may want this, or maybe not
  hist: /cfg/hist  # don't pollute the shared-folder
  unlist: ^@eaDir  # hide the synology "@eaDir" folders
  name: mycloud5   # shows in the browser, can be anything

[accounts]
  admin: admin

[/]                # share the following at the webroot:
  /mnt/HD/HD_a2    # the NAS data volume
  accs:
    A: admin       # give Admin to username admin
EOF
fi

log "linking redirect page from: ${APP_PATH}/web/* to: ${APP_WEB_PATH}"
ln -sf ${APP_PATH}/web/* ${APP_WEB_PATH} >> ${LOG} 2>&1