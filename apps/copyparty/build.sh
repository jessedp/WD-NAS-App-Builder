#!/bin/bash
. "../build_helpers.sh"

CP_VERSION="${CP_VERSION:-v1.20.3}"
# Ensure version has 'v' prefix for the download URL if not present
if [[ ! $CP_VERSION == v* ]]; then
    CP_VERSION="v$CP_VERSION"
fi

# Some models use AMD64 architecture and others use ARM
declare -A MODELS
MODELS[amd64]="MyCloudPR4100 MyCloudPR2100 WDMyCloudDL4100 WDMyCloudDL2100"
MODELS[armhf]="WDCloud WDMyCloud WDMyCloudMirror WDMyCloudMirrorG2 WDMyCloudEX4100 WDMyCloudEX2100 MyCloudEX2Ultra"

# Download the SFX (architecture independent python script)
URL="https://github.com/9001/copyparty/releases/download/${CP_VERSION}/copyparty-sfx.py"
download "$URL" "copyparty-sfx.py"

for ARCH in "${!MODELS[@]}"; do
	# Build the archive for all models of this architecture
	build ${MODELS[${ARCH}]} ${ARCH}
done

# Cleanup
rm copyparty-sfx.py
. "../build_helpers.sh"
