#!/bin/sh

# Called upon app installation
	# -> 1. $UPLOAD_PATH/install.sh $UPLOAD_PATH $INSTALL_PATH
	#	 2. $INSTALL_PATH/init.sh $INSTALL_PATH
	#	 3. $INSTALL_PATH/start.sh $INSTALL_PATH

# Load all the useful variables
. "$1/helpers.sh" "$0" "$1" "$2";

# ----------------------------------------------------------------------------------
# Install script
# ----------------------------------------------------------------------------------

# Move the files from the temporary upload directory to the app directory
log "Moving files from: ${APP_UPLOAD_PATH} to: ${APPS_PATH}";
mv -f "${APP_UPLOAD_PATH}" "${APPS_PATH}";

# Determine Entware Architecture
if [ "${HARDWARE_DEBIAN_ARCH}" == "amd64" ]; then
    ENT_ARCH="x64"
elif [ "${HARDWARE_DEBIAN_ARCH}" == "armhf" ]; then
    ENT_ARCH="armv7sf"
else
    # Fallback or error?
    ENT_ARCH="armv7sf" # Guessing for other ARMs
fi

log "Detected Architecture: ${HARDWARE_DEBIAN_ARCH} -> Entware: ${ENT_ARCH}"

# Define Opt Path (Persistent)
# We use APP_PERSISTENT_DATA_PATH for the entware root
OPT_ROOT="${APP_PERSISTENT_DATA_PATH}"

log "Creating Entware root at ${OPT_ROOT}"
mkdir -p "${OPT_ROOT}"

# Bind mount to /opt
log "Mounting ${OPT_ROOT} to /opt"
# Ensure /opt exists (it should)
if [ ! -d /opt ]; then
    mkdir -p /opt
fi

# Check if already mounted
if mount | grep -q "on /opt type"; then
    log "/opt is already mounted"
else
    mount --bind "${OPT_ROOT}" /opt
fi

# Download and run installer if /opt/bin/opkg does not exist
if [ ! -f /opt/bin/opkg ]; then
    log "Downloading installer for ${ENT_ARCH}..."
    INSTALLER_URL="http://bin.entware.net/${ENT_ARCH}-k3.2/installer/generic.sh"
    
    # We need wget. OS5 usually has it.
    wget -O - "${INSTALLER_URL}" | /bin/sh 2>&1 | tee -a ${LOG}
    
    if [ $? -eq 0 ]; then
        log "Entware installation successful"
    else
        log "Entware installation FAILED"
    fi
else
    log "Entware already installed at ${OPT_ROOT}"
fi

# Fix /opt/tmp permissions (sometimes needed)
chmod 777 /opt/tmp

# Unmount /opt so init.sh can handle it cleanly on startup?
# Or leave it mounted?
# The standard flow calls init.sh right after.
# If we unmount here, init.sh must remount.
log "Unmounting /opt for clean init"
umount /opt