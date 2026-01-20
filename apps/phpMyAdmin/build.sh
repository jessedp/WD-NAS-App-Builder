#!/bin/bash
. "../build_helpers.sh"

# Make a directory for the files
mkdir -p binaries
cd binaries

FILENAME="phpMyAdmin-${APKG_VERSION}-all-languages.tar.gz"
PMA_REPO="https://files.phpmyadmin.net/phpMyAdmin/${APKG_VERSION}/${FILENAME}"

# Download and extract the right version of phpMyAdmin
download "${PMA_REPO}" "${FILENAME}"

# Extract the data
if [ -f "${FILENAME}" ]; then
	tar -xf "${FILENAME}"
	rm "${FILENAME}"
	DIR=$(ls | head -n 1)
	if [ -d ${DIR} ] && [ "${DIR}" != "" ]; then
		mv ${DIR}/* .
		rm -rf ${DIR}
	else
		abort "$(pwd)/${FILENAME} could not be extracted"
	fi
else
	abort "$(pwd)/${FILENAME} could not be downloaded"
fi

cd ../

# Some models use AMD64 architecture and others use ARM
declare -A MODELS
MODELS[amd64]="MyCloudPR4100 MyCloudPR2100 WDMyCloudDL4100 WDMyCloudDL2100"
MODELS[armhf]="WDCloud WDMyCloud WDMyCloudMirror WDMyCloudEX4100 WDMyCloudEX2100 MyCloudEX2Ultra"
for ARCH in "${!MODELS[@]}"; do
	# Build the archive for all models of this architecture
	build ${MODELS[${ARCH}]} ${ARCH}
done

# Clean up any mess
rm -rf binaries

# Cleanup
. "../build_helpers.sh"