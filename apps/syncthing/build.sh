#!/bin/bash
. "../build_helpers.sh"

# Syncthing Version
ST_VERSION="v2.0.13"

# Update version in apkg.rc (remove 'v' prefix)
sed -i "s/Version:.*/Version:\t\t\t${ST_VERSION#v}/" apkg.rc

# Some models use AMD64 architecture and others use ARM
declare -A MODELS
MODELS[amd64]="MyCloudPR4100"
# MODELS[amd64]="MyCloudPR4100 MyCloudPR2100 WDMyCloudDL4100 WDMyCloudDL2100"
# MODELS[armhf]="WDCloud WDMyCloud WDMyCloudMirror WDMyCloudEX4100 WDMyCloudEX2100 MyCloudEX2Ultra"

for ARCH in "${!MODELS[@]}"; do
	# Build the archive for all models of this architecture
    echo -e "\nPreparing Syncthing ${ST_VERSION} for ${ARCH}..."
    
    if [ "$ARCH" == "amd64" ]; then
        URL="https://github.com/syncthing/syncthing/releases/download/${ST_VERSION}/syncthing-linux-amd64-${ST_VERSION}.tar.gz"
    elif [ "$ARCH" == "armhf" ]; then
        # Using linux-arm (ARMv5) for broad compatibility on armhf devices
        URL="https://github.com/syncthing/syncthing/releases/download/${ST_VERSION}/syncthing-linux-arm-${ST_VERSION}.tar.gz"
    fi

    echo "Downloading from: $URL"
    curl -L -o syncthing.tar.gz "$URL"
    
    if [ $? -ne 0 ]; then
        echo "Download failed!"
        exit 1
    fi
    
    echo "Extracting..."
    tar -xzf syncthing.tar.gz
    
    # The tarball extracts to a folder named like syncthing-linux-amd64-v1.27.2
    # We need to find the binary inside and move it to root
    EXTRACTED_DIR=$(tar -tf syncthing.tar.gz | head -1 | cut -f1 -d"/")
    echo "Moving binary from ${EXTRACTED_DIR}/syncthing to ."
    cp "${EXTRACTED_DIR}/syncthing" .
    chmod +x syncthing

	build ${MODELS[${ARCH}]} ${ARCH}
    
    # Cleanup for next iteration
    rm -rf "${EXTRACTED_DIR}" syncthing.tar.gz syncthing
done

# Cleanup
. "../build_helpers.sh"
