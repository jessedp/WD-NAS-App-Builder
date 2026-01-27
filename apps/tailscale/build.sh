#!/bin/bash
. "../build_helpers.sh"

TAILSCALE_VERSION="1.92.5"

# Note: MODELS are defined in build_helpers.sh

for ARCH in "${!MODELS[@]}"; do
	# 1. Download and extract binary for this ARCH
	TS_ARCH=$ARCH
	if [ "$ARCH" == "armhf" ]; then
		TS_ARCH="arm"
	fi
	
	FILENAME="tailscale_${TAILSCALE_VERSION}_${TS_ARCH}.tgz"
	URL="https://pkgs.tailscale.com/stable/${FILENAME}"
	
	download "$URL" "$FILENAME"
	tar xzf "$FILENAME"
	
	# Move binaries to root
	mv tailscale_${TAILSCALE_VERSION}_${TS_ARCH}/tailscale .
	mv tailscale_${TAILSCALE_VERSION}_${TS_ARCH}/tailscaled .
	
	# Cleanup tar and dir
	rm "$FILENAME"
	rm -rf tailscale_${TAILSCALE_VERSION}_${TS_ARCH}

	# Build the archive for all models of this architecture
    # build() handles MODEL_OVERRIDE internally
	build ${MODELS[${ARCH}]} ${ARCH}
    
    # Cleanup binaries after build
    rm tailscale tailscaled
done

# Cleanup
. "../build_helpers.sh"