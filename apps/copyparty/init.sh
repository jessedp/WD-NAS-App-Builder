#!/bin/sh

# Load all the useful variables
. "$1/helpers.sh" "$0" "$1";

# Create folder for the webpage
log "creating web path: /var/www/apps/copyparty"
mkdir -p /var/www/apps/copyparty

# Create persistent configuration directory
if [ ! -d "${APP_PERSISTENT_DATA_PATH}" ]; then
    log "Creating persistent configuration directory: ${APP_PERSISTENT_DATA_PATH}"
    mkdir -p "${APP_PERSISTENT_DATA_PATH}"
fi

# Create default config if it doesn't exist
CONFIG_FILE="${APP_PERSISTENT_DATA_PATH}/copyparty.conf"
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

log "linking redirect page from: ${APP_PATH}/web/* to: /var/www/apps/copyparty/"
ln -sf ${APP_PATH}/web/* /var/www/apps/copyparty/ >> ${LOG} 2>&1