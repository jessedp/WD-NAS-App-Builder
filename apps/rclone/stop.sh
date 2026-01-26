#!/bin/sh

# Load all the useful variables
. "$1/helpers.sh" "$0" "$1";

log "Stopping Rclone..."

log "Removing binary symlink from: /usr/bin/rclone";
rm -f /usr/bin/rclone