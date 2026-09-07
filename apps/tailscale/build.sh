#!/bin/bash
. "../build_helpers.sh"

TAILSCALE_VERSION="${TAILSCALE_VERSION:-1.92.5}"
TAILSCALE_VERSION="${TAILSCALE_VERSION#v}"

check_version "${TAILSCALE_VERSION}"

# Note: MODELS are defined in build_helpers.sh

for ARCH in "${!MODELS[@]}"; do
	# 1. Download and extract binary for this ARCH
	TS_ARCH=$ARCH
	if [ "$ARCH" == "armhf" ]; then
		TS_ARCH="arm"
	fi
	
	FILENAME="tailscale_${TAILSCALE_VERSION}_${TS_ARCH}.tgz"
	URL="https://pkgs.tailscale.com/stable/${FILENAME}"
	EXTRACTED_DIR="tailscale_${TAILSCALE_VERSION}_${TS_ARCH}"
	
	download "$URL" "$FILENAME"
	tar xzf "$FILENAME"
	check_extracted "$EXTRACTED_DIR"
	
	# Move binaries to root
	mv ${EXTRACTED_DIR}/tailscale .
	mv ${EXTRACTED_DIR}/tailscaled .
	
	# Cleanup tar and dir
	rm "$FILENAME"
	rm -rf ${EXTRACTED_DIR}

	# Build the archive for all models of this architecture
    # build() handles MODEL_OVERRIDE internally
	build ${MODELS[${ARCH}]} ${ARCH}
    
    # Cleanup binaries after build
    rm tailscale tailscaled
done

# Cleanup
. "../build_helpers.sh"