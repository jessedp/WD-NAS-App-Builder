#!/bin/bash
. "../build_helpers.sh"

# Syncthing Version
ST_VERSION="v2.0.13"

# Update version in apkg.rc (remove 'v' prefix)
sed -i "s/Version:.*/Version:\t\t\t${ST_VERSION#v}/" apkg.rc

# Note: MODELS are defined in build_helpers.sh

for ARCH in "${!MODELS[@]}"; do
	# Build the archive for all models of this architecture
    echo -e "\nPreparing Syncthing ${ST_VERSION} for ${ARCH}..."
    
    if [ "$ARCH" == "amd64" ]; then
        ST_ARCH="amd64"
    elif [ "$ARCH" == "armhf" ]; then
        # Using linux-arm (ARMv5) for broad compatibility on armhf devices
        ST_ARCH="arm"
    elif [ "$ARCH" == "arm64" ]; then
        ST_ARCH="arm64"
    fi

    FILENAME="syncthing-linux-${ST_ARCH}-${ST_VERSION}.tar.gz"
    URL="https://github.com/syncthing/syncthing/releases/download/${ST_VERSION}/${FILENAME}"

    download "$URL" "$FILENAME"
    
    if [ ! -f "$FILENAME" ]; then
        echo "Download failed!"
        exit 1
    fi
    
    echo "Extracting..."
    tar -xzf "$FILENAME"
    
    # The tarball extracts to a folder named like syncthing-linux-amd64-v1.27.2
    # We need to find the binary inside and move it to root
    EXTRACTED_DIR=$(tar -tf "$FILENAME" | head -1 | cut -f1 -d"/")
    echo "Moving binary from ${EXTRACTED_DIR}/syncthing to ."
    cp "${EXTRACTED_DIR}/syncthing" .
    chmod +x syncthing

	# build() handles MODEL_OVERRIDE internally
	build ${MODELS[${ARCH}]} ${ARCH}
    
    # Cleanup for next iteration
    rm -rf "${EXTRACTED_DIR}" "$FILENAME" syncthing
done

# Cleanup
. "../build_helpers.sh"