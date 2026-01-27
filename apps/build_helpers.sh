#!/bin/bash

if [ -z ${APP_PATH+x} ]; then
	APP_PATH="$(pwd)"
	APP_NAME="$(basename ${APP_PATH})"

	# Import the APKG file helpers (runs pre-checks)
	. "../apkg_helpers.sh"
	check_apkg_variables

	APP_VERSION="${APKG_VERSION}"
	APPS_PATH="$(dirname ${APP_PATH})"
	REPO_PATH="$(dirname ${APPS_PATH})"
	RELEASE_DIR="../../packages/${APP_NAME}/${APP_VERSION}"
	DOWNLOAD_CACHE_DIR="${REPO_PATH}/downloads"

	# Standard WD NAS Models
	declare -A MODELS
	MODELS[amd64]="MyCloudPR4100 MyCloudPR2100 WDMyCloudDL4100 WDMyCloudDL2100"
	MODELS[armhf]="WDCloud WDMyCloud WDMyCloudMirror WDMyCloudMirrorG2 WDMyCloudEX4100 WDMyCloudEX2100 MyCloudEX2Ultra"
	# MODELS[arm64]=""

	# DECLARE FUNCTIONS --------------------------------------------------------

	# Downloads a file from a URL and caches it in the downloads directory
	download() {
		url=$1
		filename=$2
		if [ -z "$filename" ]; then
			filename=$(basename "$url")
		fi

		mkdir -p "${DOWNLOAD_CACHE_DIR}"
		
		if [ ! -f "${DOWNLOAD_CACHE_DIR}/${filename}" ]; then
			echo "Downloading ${filename}..."
			curl -L -o "${DOWNLOAD_CACHE_DIR}/${filename}" "$url"
			if [ $? -ne 0 ]; then
				echo "Download failed!"
				return 1
			fi
		else
			echo "Using cached ${filename}"
		fi
		
		cp "${DOWNLOAD_CACHE_DIR}/${filename}" .
	}

	# Build function accepts an array of WD NAS device models and builds for all of them
	# Usage: build model1 [model2 ...] arch
	build() {
		models=($@)
		((last_id=${#models[@]} - 1))
		arch=${models[last_id]}
		unset models[last_id]
		
		# Normalise variations on the arch variable (e.g. x86_64 => amd64 | x64 => amd64 | armv7l => armhf)
		if [ "$arch" = "x86_64" ]; then
			arch="amd64"
		elif [ "$arch" = "x64" ]; then
			arch="amd64"
		elif [ "$arch" = "x86" ]; then
			arch="amd64"
		elif [ "$arch" = "armv7l" ]; then
			arch="armhf"
		elif [ "$arch" = "arm" ]; then
			arch="armhf"
		fi

		# Check if we should build for this arch/model based on override
		build_list=()
		if [ -n "$MODEL_OVERRIDE" ]; then
			for model in "${models[@]}"; do
				if [ "$model" == "$MODEL_OVERRIDE" ]; then
					build_list+=("$model")
				fi
			done
			if [ ${#build_list[@]} -eq 0 ]; then
				# Override set but no matching models in this batch, skipping
				return 0
			fi
		else
			build_list=("${models[@]}")
		fi

		# Build the archive for all filtered models of this architecture
		for model in "${build_list[@]}"; do
			echo -e  "\n-----------------------------------"
			echo "BUILDING FOR: ${model} ($arch)"
			echo -e  "-----------------------------------\n"
			../../mksapkg-OS5 -E -s -m ${model} > /dev/null
		done
		
		# Create a source bundle for this architecture (only if we built something)
		if [ ${#build_list[@]} -gt 0 ]; then
			echo -e "\nBundle sources for ${arch} into release dir"
			src_tar="${RELEASE_DIR}/${APP_NAME}_${APP_VERSION}_${arch}.tar.gz"
			tar -czf ${src_tar} -C ${APP_PATH} .
		fi

		rm apkg.sign
		rm apkg.xml
	}

	# Helper to build all standard models defined in MODELS array
	build_all() {
		for ARCH in "${!MODELS[@]}"; do
			build ${MODELS[${ARCH}]} ${ARCH}
		done
	}

	# Restore any files temporarily imported or removed
	restore_files() {
		# Clean up the helpers file
		echo -e "\nHelper files removed"
		rm -f "${APP_PATH}/helpers.sh" "${APP_PATH}/apkg_helpers.sh"

		# Restore the app build instructions
		if [ -f "${APPS_PATH}/build_${APP_NAME}.sh" ]; then
			echo "Build script restored"
			mv "${APPS_PATH}/build_${APP_NAME}.sh" "${APP_PATH}/build.sh"
		fi

		# Restore the app readme file
		if [ -f "${APPS_PATH}/README_${APP_NAME}.md" ]; then
			echo "README.md restored"
			mv "${APPS_PATH}/README_${APP_NAME}.md" "${APP_PATH}/README.md"
		fi
	}

	# Prepare the directory for a clean build
	prepare_files() {
		# Bring in the helpers files
		echo -e "\nHelper files imported"
		cp "${APPS_PATH}/helpers.sh" .
		cp "${APPS_PATH}/apkg_helpers.sh" .

		# We don't need to build our build file, let's keep the files packaged to those actually needed
		echo "Build script removed"
		mv "${APP_PATH}/build.sh" "${APPS_PATH}/build_${APP_NAME}.sh"

		# We don't need to build our readme file, let's keep the files packaged to those actually needed
		if [ -f "${APP_PATH}/README.md" ]; then
			echo "README.md removed"
			mv "${APP_PATH}/README.md" "${APPS_PATH}/README_${APP_NAME}.md"
		fi
	}

	# Ensure that our release directory is empty
	prepare_release_dir() {	
		# Only clear if NO override, or if it's the first run? 
		# If we build multiple times (e.g. for different archs), we don't want to wipe previous results.
		# But prepare_release_dir is called ONCE at start of script.
		# If we override, we might only build one bin.
		# Users probably expect a clean slate.
		rm -rf "${RELEASE_DIR}"
		mkdir -p "${RELEASE_DIR}"
		echo -e "\nRelease dir created: ${RELEASE_DIR}"
	}

	# Move the files to the release location with sensible names
	move_binaries_to_release_dir() {	
		echo -e "\nMoving binaries to release dir"
		find ${APPS_PATH} -maxdepth 1 -name "*.bin*" | while read file; do
			file=${file/${APPS_PATH}\/}
			parts=(${file//_${APP_NAME}_/ })
			newFile="${APP_NAME}_${APP_VERSION}_${parts[0]#*/}.bin"
			mv ${APPS_PATH}/${file} ${APPS_PATH}/${newFile} 2>/dev/null || true
		done
		mv ${APPS_PATH}/${APP_NAME}_*.bin ${RELEASE_DIR} 2>/dev/null || true
	}

	# Create a latest release file for ease of deployment testing
	create_latest_release_file() {	
		LATEST_PATH="$(dirname ${RELEASE_DIR})/latest"
		echo -e "\nCreating a latest release file: ${LATEST_PATH}"
		rm -f ${LATEST_PATH}
		printf "%s" "${APP_VERSION}" > ${LATEST_PATH}
	}

	# Mistakes will be made and half built files will litter directories, this will remove them
	clean_failed_files() {
		echo -e "\nRemoving any failed binaries"
		find ${APPS_PATH} -maxdepth 1 -name "*.bin*" | while read file; do
			rm -f ${file}
		done
	}

	# Abort the current operation
	abort() {
		if [ $# -eq 1 ]; then
			echo "CRITICAL ERROR: $1"
		fi
		clean_failed_files
		restore_files
		exit 1
	}

	# BEGIN BUILD --------------------------------------------------------------

	echo "Building ${APP_NAME} version ${APP_VERSION}"
	if [ -n "$MODEL_OVERRIDE" ]; then
		echo "Model Override: ${MODEL_OVERRIDE}"
	fi

	restore_files
	prepare_files
	prepare_release_dir
	clean_failed_files
else
	# END BUILD --------------------------------------------------------------

	restore_files
	move_binaries_to_release_dir
	create_latest_release_file
fi
