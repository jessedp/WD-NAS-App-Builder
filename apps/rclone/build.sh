#!/bin/bash
. "../build_helpers.sh"

RCLONE_VERSION="${RCLONE_VERSION:-v1.69.0}"
# Ensure version has 'v' prefix for the download URL if not present
if [[ ! $RCLONE_VERSION == v* ]]; then
    RCLONE_VERSION="v$RCLONE_VERSION"
fi

# Install unzip if not present
if ! command -v unzip &> /dev/null; then
    echo "Installing unzip..."
    apt-get update && apt-get install -y unzip
fi

# Some models use AMD64 architecture and others use ARM
declare -A MODELS
MODELS[amd64]="MyCloudPR4100 MyCloudPR2100 WDMyCloudDL4100 WDMyCloudDL2100"
MODELS[armhf]="WDCloud WDMyCloud WDMyCloudMirror WDMyCloudMirrorG2 WDMyCloudEX4100 WDMyCloudEX2100 MyCloudEX2Ultra"

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
    
    # Move binary to root (renaming to just 'rclone')
    EXTRACTED_DIR="rclone-${RCLONE_VERSION}-linux-${RCLONE_ARCH}"
    if [ -f "${EXTRACTED_DIR}/rclone" ]; then
        mv "${EXTRACTED_DIR}/rclone" .
        chmod +x rclone
        echo "Rclone binary extracted and moved."
    else
        echo "ERROR: Rclone binary not found in extracted directory!"
        ls -R "${EXTRACTED_DIR}"
        exit 1
    fi
    
    # Build the archive for all models of this architecture
	build ${MODELS[${ARCH}]} ${ARCH}
    
    # Cleanup
    rm "$FILENAME"
    rm -rf "${EXTRACTED_DIR}"
    rm rclone
done

# Cleanup
. "../build_helpers.sh"