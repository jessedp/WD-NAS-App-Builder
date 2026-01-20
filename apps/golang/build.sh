#!/bin/bash
. "../build_helpers.sh"

# Some models use AMD64 architecture and others use ARM
declare -A MODELS
MODELS[amd64]="MyCloudPR4100 MyCloudPR2100 WDMyCloudDL4100 WDMyCloudDL2100"
#MODELS[armhf]="WDCloud WDMyCloud WDMyCloudMirror WDMyCloudEX4100 WDMyCloudEX2100 MyCloudEX2Ultra"

for ARCH in "${!MODELS[@]}"; do
	FILENAME="go${APKG_VERSION}.linux-${ARCH}.tar.gz"
	GO_REPO="https://go.dev/dl/${FILENAME}"

	# Make a directory for the files
	mkdir -p binaries
	cd binaries
	
	# Download and extract the right version of Go
	download "${GO_REPO}" "${FILENAME}"
	
	# Extract the data
	if [ -f "${FILENAME}" ]; then
		tar -xf "${FILENAME}"
		rm "${FILENAME}"
		DIR=$(ls | head -n 1)
		if [ -d ${DIR} ] && [ "${DIR}" != "" ]; then
			mv ${DIR}/* .
			rm -rf ${DIR}
		else
			abort "$(pwd)/go.tar.gz could not be extracted"
		fi
	else
		abort "$(pwd)/go.tar.gz could not be downloaded"
	fi
	
	cd ../

	# Build the archive for all models of this architecture
	build ${MODELS[${ARCH}]} ${ARCH}

	# Clean up any mess
	rm -rf binaries
done

. "../build_helpers.sh"