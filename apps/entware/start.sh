#!/bin/sh
. "$1/helpers.sh" "$0" "$1";

log "Starting Entware services..."

# Ensure /opt is mounted (redundant check but safe)
OPT_ROOT="${APP_PERSISTENT_DATA_PATH}"
if ! mount | grep -q "on /opt type"; then
    mount --bind "${OPT_ROOT}" /opt
fi

# Run rc.unslung
if [ -x /opt/etc/init.d/rc.unslung ]; then
    /opt/etc/init.d/rc.unslung start
else
    log "rc.unslung not found or not executable"
fi