#!/bin/sh
. "$1/helpers.sh" "$0" "$1";

log "Stopping Tailscale..."

# Kill tailscaled
pkill tailscaled
killall tailscaled 2>/dev/null

# Wait for it to die
for i in 1 2 3 4 5; do
    if pgrep tailscaled > /dev/null; then
        sleep 1
    else
        break
    fi
done

# Force kill if still alive
if pgrep tailscaled > /dev/null; then
    log "Force killing tailscaled..."
    pkill -9 tailscaled
    killall -9 tailscaled 2>/dev/null
fi

log "Tailscale stopped."
