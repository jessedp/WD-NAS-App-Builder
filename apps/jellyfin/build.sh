#!/bin/bash
. "../build_helpers.sh"

# Update these and the version in `apkg.rc` for each build
Jellyfin="10.8.12-1"
JellyfinMain="10.8.12"
Ffmpeg="6_6.0-8"
FfmpegMain="6.0-8"

# Some models use AMD64 architecture and others use ARM
declare -A MODELS
MODELS[amd64]="MyCloudPR4100 MyCloudPR2100 WDMyCloudDL4100 WDMyCloudDL2100"
MODELS[armhf]="WDCloud WDMyCloud WDMyCloudMirror WDMyCloudEX4100 WDMyCloudEX2100 MyCloudEX2Ultra"

for ARCH in "${!MODELS[@]}"; do
	JELLYFIN_REPO="https://repo.jellyfin.org/releases/server/debian/versions/stable/server/${JellyfinMain}/jellyfin-server_${Jellyfin}_${ARCH}.deb"
	JELLYFIN_WEB_REPO="https://repo.jellyfin.org/releases/server/debian/versions/stable/web/${JellyfinMain}/jellyfin-web_${Jellyfin}_all.deb"
	FFMPEG_REPO="https://repo.jellyfin.org/releases/server/debian/versions/jellyfin-ffmpeg/${FfmpegMain}/jellyfin-ffmpeg${Ffmpeg}-bullseye_${ARCH}.deb"
	
	# Make a directory for the files
	mkdir -p binaries
	cd binaries
	
	# Download and extract the right version of Jellyfin
	JELLYFIN_FILENAME="jellyfin-server_${Jellyfin}_${ARCH}.deb"
	download "${JELLYFIN_REPO}" "${JELLYFIN_FILENAME}"
	
	# Extract the archive
	if [ -f "${JELLYFIN_FILENAME}" ]; then
		ar x "${JELLYFIN_FILENAME}"
		rm "${JELLYFIN_FILENAME}"
		rm debian-binary
	else
		abort "$(pwd)/${JELLYFIN_FILENAME} could not be extracted"
	fi
	
	# Extract the data
	if [ -f data.tar.xz ]; then
		tar -xf data.tar.xz
		rm data.tar.xz
		rm control.tar.xz
	else
		abort "$(pwd)/data.tar.xz could not be extracted"
	fi
	
	# Make a subdirectory to store FFMPEG
	mkdir -p ffmpeg
	cd ffmpeg
	
	# For now we'll just use generic statically linked FFMPEG
	FFMPEG_FILENAME="ffmpeg-release-${ARCH}-static.tar.xz"
	download "https://johnvansickle.com/ffmpeg/releases/${FFMPEG_FILENAME}" "${FFMPEG_FILENAME}"
	if [ -f "${FFMPEG_FILENAME}" ]; then
		tar -xf "${FFMPEG_FILENAME}"
		rm "${FFMPEG_FILENAME}"
		DIR=$(ls | head -n 1)
		mv ${DIR} bin
	else
		abort "$(pwd)/${FFMPEG_FILENAME} could not be extracted"
	fi
	
	# Make a subdirectory to store Jellyfin Web
	cd ../
	mkdir -p jellyfin-web
	cd jellyfin-web
	
	# Download and extract the right version of FFMPEG
	JELLYFIN_WEB_FILENAME="jellyfin-web_${Jellyfin}_all.deb"
	download "${JELLYFIN_WEB_REPO}" "${JELLYFIN_WEB_FILENAME}"
	
	# Extract the archive
	if [ -f "${JELLYFIN_WEB_FILENAME}" ]; then
		ar x "${JELLYFIN_WEB_FILENAME}"
		rm "${JELLYFIN_WEB_FILENAME}"
		rm debian-binary
	else
		abort "$(pwd)/${JELLYFIN_WEB_FILENAME} could not be extracted"
	fi

	# Extract the data
	if [ -f data.tar.xz ]; then
		tar -xf data.tar.xz
		rm data.tar.xz
		rm control.tar.xz
	else
		abort "$(pwd)/data.tar.xz could not be extracted"
	fi
	
	cd ../../
	
	# Build the archive for all models of this architecture
	build ${MODELS[${ARCH}]} ${ARCH}
	
	# Clean up any mess
	rm -rf binaries
done

# Cleanup
. "../build_helpers.sh"