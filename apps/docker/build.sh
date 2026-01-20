#!/bin/bash
. "../build_helpers.sh"

# Some models use AMD64 architecture and others use ARM
declare -A MODELS
MODELS[x86_64]="MyCloudPR4100 MyCloudPR2100 WDMyCloudDL4100 WDMyCloudDL2100"
MODELS[armhf]="WDCloud WDMyCloud WDMyCloudMirror WDMyCloudEX4100 WDMyCloudEX2100 MyCloudEX2Ultra"

COMPOSE_VERSION=2.39.4

for ARCH in "${!MODELS[@]}"; do
	ARCH1=$ARCH
	ARCH2=$ARCH
	if [ "$ARCH" = "armhf" ]; then
		ARCH1="armv7"
	fi
			
	DOCKER_REPO="https://download.docker.com/linux/static/stable/${ARCH}/docker-${APKG_VERSION}.tgz"
	COMPOSE_REPO="https://github.com/docker/compose/releases/download/v${COMPOSE_VERSION}/docker-compose-linux-${ARCH1}"
	
	# Make a directory for the files
	mkdir -p binaries
	cd binaries

	# Download and extract the right version of Docker
	wget ${DOCKER_REPO} -O docker.tar.gz

	# Extract the data
	if [ -f docker.tar.gz ]; then
		tar -xf docker.tar.gz --strip-components=1
		rm docker.tar.gz
	else
		abort "$(pwd)/docker.tar.gz could not be downloaded"
	fi
	
	# Download the right version of Docker Compose
	wget ${COMPOSE_REPO} -O docker-compose
	chmod +x docker-compose

	cd ../

	# Build the archive for all models of this architecture
	build ${MODELS[${ARCH}]} ${ARCH}
	
	# Clean up any mess
	rm -rf binaries
done

# Cleanup
. "../build_helpers.sh"
