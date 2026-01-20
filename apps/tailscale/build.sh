#!/bin/bash
. "../build_helpers.sh"

TAILSCALE_VERSION="1.92.5"

# Some models use AMD64 architecture and others use ARM
declare -A MODELS
MODELS[amd64]="MyCloudPR4100 MyCloudPR2100 WDMyCloudDL4100 WDMyCloudDL2100"
# MODELS[armhf]="WDCloud WDMyCloud WDMyCloudMirror WDMyCloudEX4100 WDMyCloudEX2100 MyCloudEX2Ultra"

for ARCH in "${!MODELS[@]}"; do
	# 1. Download and extract binary for this ARCH
	TS_ARCH=$ARCH
	if [ "$ARCH" == "armhf" ]; then
		TS_ARCH="arm"
	fi
	
	echo "Downloading Tailscale for $ARCH ($TS_ARCH)..."
	wget -q --no-check-certificate https://pkgs.tailscale.com/stable/tailscale_${TAILSCALE_VERSION}_${TS_ARCH}.tgz
	tar xzf tailscale_${TAILSCALE_VERSION}_${TS_ARCH}.tgz
	
	# Move binaries to root
	mv tailscale_${TAILSCALE_VERSION}_${TS_ARCH}/tailscale .
	mv tailscale_${TAILSCALE_VERSION}_${TS_ARCH}/tailscaled .
	
	# Cleanup tar and dir
	rm tailscale_${TAILSCALE_VERSION}_${TS_ARCH}.tgz
	rm -rf tailscale_${TAILSCALE_VERSION}_${TS_ARCH}

	# Build the archive for all models of this architecture
	build ${MODELS[${ARCH}]} ${ARCH}
    
    # Cleanup binaries after build
    rm tailscale tailscaled
done

# Cleanup
. "../build_helpers.sh"
