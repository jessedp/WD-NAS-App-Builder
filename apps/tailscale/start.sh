#!/bin/sh
. "$1/helpers.sh" "$0" "$1";

log "Starting Tailscale..."

# Create persistent state directory if it doesn't exist
if [ ! -d "${APP_PATH}/var_lib_tailscale" ]; then
    log "Creating persistent state directory"
    mkdir -p "${APP_PATH}/var_lib_tailscale"
fi

# Link /var/lib/tailscale to persistence
# We force the link creation to ensure it points to the right place
rm -rf /var/lib/tailscale
ln -sf "${APP_PATH}/var_lib_tailscale" /var/lib/tailscale

# Start tailscaled
log "Starting tailscaled daemon"
"${APP_PATH}/tailscaled" &

# Wait a bit for daemon to start
sleep 5

# Run tailscale up
log "Running tailscale up"
"${APP_PATH}/tailscale" up &