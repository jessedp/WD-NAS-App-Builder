#!/bin/bash
#
# Builds a package and optionally installs it on a target host platform
#
# Usage:
#
#  ./build.sh <package> [<host> <model> <version>]
#
#  MODEL_OVERRIDE=MyCloudPR4100 ./build.sh <package>  (Builds only for specific model)

APP_NAME=$1

usage () {
	echo "Usage: $0 <package> [<host> <model> <version>]"
}

if [ -z "$1" ]; then
	usage
	exit 1
fi

if [ "$1" = "-h" ]; then
	usage
	exit 0
fi

if [ ! -d "apps/${APP_NAME}" ]; then
	echo "No app found named: ${APP_NAME}"
	exit 1
fi

# Build any statically linked binaries first if required
if [ -d "static/${APP_NAME}" ]; then
	/bin/bash build_static.sh ${APP_NAME} amd64
	#/bin/bash build_static.sh ${APP_NAME} armhf
fi

if [[ "$(docker images -q wd_builder:latest 2> /dev/null)" == "" ]]; then
	docker build -f docker/build.Dockerfile -t wd_builder .
fi

# Create packages for app
if [[ "$(docker images -q wd_builder:latest 2> /dev/null)" != "" ]]; then
	docker run -it -v $(pwd):/data -e MODEL_OVERRIDE="${MODEL_OVERRIDE}" wd_builder:latest /bin/bash -c "\
	cd /data;\
	find -type f -name \"*.sh\" -exec chmod +x {} +;\
	find -type f -name \"*.rc\" -exec chmod +x {} +;\
	cd /data/apps/${APP_NAME};\
	./build.sh;\
	chown -R 1000:1000 ../../packages/;"
else
	echo "Docker container build failing!"
	exit 1
fi

# Deploy to a host machine
HOST="$2"
if [ -z "${HOST}" ]; then
	exit 0
fi

echo -e "\nDeploying to Host: ${HOST}"
echo "App: ${APP_NAME}"

# Get the model
MODEL="$3"
if [ -z "${MODEL}" ]; then
	MODEL="MyCloudPR4100"
fi
echo "Model: ${MODEL}"

# Get the version
VERSION="$4"
if [ -z "${VERSION}" ]; then
	VERSION="$(cat packages/${APP_NAME}/latest)"
fi
echo "Version: ${VERSION}"

# Find latest package
BINARY=$(find packages/${APP_NAME}/${VERSION} -name "*${APP_NAME}_*_${MODEL}.bin" | sort | tail -n1)
echo "Package: ${BINARY}"

echo "Deployment to host has not yet been investigated / tested"
exit 1;


echo -e "\nUploading the app binary via SCP"
scp ${BINARY} ${HOST}:/shares/Volume_1/.systemfile/upload/app.bin

#cssh='ssh -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null'
cssh=ssh

echo "Installing using upload_apkg"
ALREADY_INSTALLED=$($cssh ${HOST} "del_apkg whatever | grep ${APP_NAME}")
if [ -n "${ALREADY_INSTALLED}" ]; then
	${cssh} ${HOST} "PATH=/sbin:/usr/sbin:/bin:/usr/bin:/usr/local/sbin:/usr/local/bin /usr/sbin/upload_apkg -rapp.bin -d -f1 -g1 && echo 'SUCCESS!'"
else
	echo "ALREADY INSTALLED"
	echo "(Warning: this usually doesn't work!)"
	${cssh} ${HOST} "PATH=/sbin:/usr/sbin:/bin:/usr/bin:/usr/local/sbin:/usr/local/bin /usr/sbin/upload_apkg -m -papp.bin -t2  && echo 'SUCCESS!'"
fi

# Run any tests
TEST=tests/${APP_NAME}/test.sh
if [ -e ${TEST} ]; then
	echo -e "\nRunning tests"
	export PACKAGE=${APP_NAME}
	export TARGET=${HOST}
    ${TEST}
else
	echo -e "\nNo tests found for ${APP_NAME}... skipping"
fi