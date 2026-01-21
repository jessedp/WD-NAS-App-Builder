#!/bin/sh
. "$1/helpers.sh" "$0" "$1";

log "Starting Tailscale..."

# Persistent state directory
CONFIG_DIR="${APPS_PATH}/tailscale_conf"

# Link /var/lib/tailscale to persistence
# We force the link creation to ensure it points to the right place
rm -rf /var/lib/tailscale
ln -sf "${CONFIG_DIR}" /var/lib/tailscale

# Start tailscaled
log "Starting tailscaled daemon"
"${APP_PATH}/tailscaled" &

# Wait a bit for daemon to start
sleep 5

# Run tailscale up
log "Running tailscale up"
"${APP_PATH}/tailscale" up &