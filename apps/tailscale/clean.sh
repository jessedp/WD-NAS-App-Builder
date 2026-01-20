#!/bin/sh
. "$1/helpers.sh" "$0" "$1";

# --------------------------------------------------------------------
# Cleanup script to revert the init.sh steps. Ensures a clean shutdown
# --------------------------------------------------------------------

# Remove the web path
log "removing web path: ${APP_WEB_PATH}"
rm -rf ${APP_WEB_PATH}

log "removing tailscale symlink"
rm -f /var/lib/tailscale