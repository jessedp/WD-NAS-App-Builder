#!/bin/sh

# Load all the useful variables
. "$1/helpers.sh" "$0" "$1";

# ----------------------------------------------------------------------
# Initialisation script
# ----------------------------------------------------------------------

# Create folder for the webpage
log "creating web path: /var/www/apps/entware"
mkdir -p /var/www/apps/entware

OPT_ROOT="${APP_PERSISTENT_DATA_PATH}"

log "Mounting ${OPT_ROOT} to /opt"
if ! mount | grep -q "on /opt type"; then
    mount --bind "${OPT_ROOT}" /opt
fi

# Add /opt/bin and /opt/sbin to PATH in profile?
# OS5 usually resets profile.
# We can try to append to /etc/profile if not present.
if ! grep -q "/opt/bin" /etc/profile; then
    echo 'export PATH=$PATH:/opt/bin:/opt/sbin' >> /etc/profile
fi

# Link web content
log "Linking web content"
rm -rf /var/www/apps/entware
ln -sf ${APP_PATH}/web /var/www/apps/entware >> ${LOG} 2>&1