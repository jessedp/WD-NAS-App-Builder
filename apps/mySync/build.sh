#!/bin/bash
. "../build_helpers.sh"

RCLONE_VERSION="${RCLONE_VERSION:-v1.72.1}"
# Ensure version has 'v' prefix
if [[ ! $RCLONE_VERSION == v* ]]; then
    RCLONE_VERSION="v$RCLONE_VERSION"
fi

# Install unzip if not present
if ! command -v unzip &> /dev/null; then
    echo "Installing unzip..."
    apt-get update && apt-get install -y unzip
fi

# Ensure Package name matches directory exactly
sed -i "s/Package:.*/Package:\t\t\t\tmySync/" apkg.rc

# Note: MODELS are defined in build_helpers.sh

for ARCH in "${!MODELS[@]}"; do
	# Map WD architecture to Rclone architecture
    RCLONE_ARCH="amd64"
    if [ "$ARCH" == "armhf" ]; then
        RCLONE_ARCH="arm"
    fi

    FILENAME="rclone-${RCLONE_VERSION}-linux-${RCLONE_ARCH}.zip"
    URL="https://downloads.rclone.org/${RCLONE_VERSION}/${FILENAME}"
    
    echo "Downloading ${URL}..."
    download "$URL" "$FILENAME"
    
    # Extract
    echo "Extracting ${FILENAME}..."
    unzip -o "$FILENAME"
    
    # Move binary to bin/ (as expected by init.sh)
    EXTRACTED_DIR="rclone-${RCLONE_VERSION}-linux-${RCLONE_ARCH}"
    if [ -f "${EXTRACTED_DIR}/rclone" ]; then
        cp "${EXTRACTED_DIR}/rclone" bin/
        chmod +x bin/rclone
        echo "Rclone binary placed in bin/rclone"
    else
        echo "ERROR: Rclone binary not found in extracted directory!"
        exit 1
    fi
    
    # Build the archive for all models of this architecture
    # build() handles MODEL_OVERRIDE internally
	build ${MODELS[${ARCH}]} ${ARCH}
    
    # Cleanup for next arch
    rm "$FILENAME"
    rm -rf "${EXTRACTED_DIR}"
    rm bin/rclone
done

# Cleanup
. "../build_helpers.sh"
