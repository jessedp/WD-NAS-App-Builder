#!/bin/sh

# Load all the useful variables
. "$1/helpers.sh" "$0" "$1";

log "Starting Rclone..."

# Create a wrapper script to ensure persistence of config
WRAPPER_PATH="${APP_PATH}/rclone_wrapper"
REAL_BINARY="${APP_PATH}/rclone"
CONFIG_PATH="${APP_PERSISTENT_DATA_PATH}/config"

# Create the wrapper script
cat <<EOF > "${WRAPPER_PATH}"
#!/bin/sh
# Rclone wrapper for WD NAS
export XDG_CONFIG_HOME="${CONFIG_PATH}"
exec "${REAL_BINARY}" "\$@"
EOF

# Make it executable
chmod +x "${WRAPPER_PATH}"

# Link wrapper to the path
log "linking rclone wrapper: ${WRAPPER_PATH} to: /usr/bin/rclone"
ln -sf "${WRAPPER_PATH}" /usr/bin/rclone >> ${LOG} 2>&1