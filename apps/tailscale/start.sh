#!/bin/sh
. "$1/helpers.sh" "$0" "$1";

log "Starting Tailscale..."

# Ensure clean state
pkill tailscaled 2>/dev/null
killall tailscaled 2>/dev/null
sleep 1

# Link /var/lib/tailscale to persistence
# We force the link creation to ensure it points to the right place
rm -rf /var/lib/tailscale
ln -sf "${APP_PERSISTENT_DATA_PATH}" /var/lib/tailscale

# Start tailscaled
log "Starting tailscaled daemon"
"${APP_PATH}/tailscaled" > /dev/null 2>&1 &

# Wait a bit for daemon to start
sleep 5

if ! pgrep tailscaled > /dev/null; then
    log "ERROR: tailscaled failed to start"
    exit 1
fi

# Run tailscale up (auto-connect if previously logged in)
log "Running tailscale up"
"${APP_PATH}/tailscale" up > /dev/null 2>&1 &