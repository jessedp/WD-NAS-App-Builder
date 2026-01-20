#!/bin/bash
. "../build_helpers.sh"

# Some models use AMD64 architecture and others use ARM
declare -A MODELS
MODELS[x64]="MyCloudPR4100 MyCloudPR2100 WDMyCloudDL4100 WDMyCloudDL2100"
MODELS[armv7l]="WDCloud WDMyCloud WDMyCloudMirror WDMyCloudEX4100 WDMyCloudEX2100 MyCloudEX2Ultra"

for ARCH in "${!MODELS[@]}"; do
	FILENAME="node-v${APP_VERSION}-linux-${ARCH}.tar.xz"
	NODE_REPO="https://nodejs.org/dist/v${APP_VERSION}/${FILENAME}"
	
	# Make a directory for the files
	mkdir -p binaries
	cd binaries
	
	# Download and extract the right version of Node.js
	download "${NODE_REPO}" "${FILENAME}"
	
	# Extract the data
	if [ -f "${FILENAME}" ]; then
		tar -xf "${FILENAME}"
		rm "${FILENAME}"
		DIR=$(ls | head -n 1)
		if [ -d ${DIR} ] && [ "${DIR}" != "" ]; then
			mv ${DIR}/* .
			rm -rf ${DIR}
		else
			abort "$(pwd)/nodejs.tar.xz could not be extracted"
		fi
	else
		abort "$(pwd)/nodejs.tar.xz could not be extracted"
	fi
	
	cd ../

	# Build the archive for all models of this architecture
	build ${MODELS[${ARCH}]} ${ARCH}
	
	# Clean up any mess
	rm -rf binaries
done

# Cleanup
. "../build_helpers.sh"