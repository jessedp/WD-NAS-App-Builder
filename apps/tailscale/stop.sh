#!/bin/sh
. "$1/helpers.sh" "$0" "$1";

log "Stopping Tailscale..."

# Kill tailscaled
pkill tailscaled || killall tailscaled