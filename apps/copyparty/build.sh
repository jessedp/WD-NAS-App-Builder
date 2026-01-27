#!/bin/bash
. "../build_helpers.sh"

CP_VERSION="${CP_VERSION:-v1.20.3}"
# Ensure version has 'v' prefix for the download URL if not present
if [[ ! $CP_VERSION == v* ]]; then
    CP_VERSION="v$CP_VERSION"
fi

# Download the SFX (architecture independent python script)
URL="https://github.com/9001/copyparty/releases/download/${CP_VERSION}/copyparty-sfx.py"
download "$URL" "copyparty-sfx.py"

# Build all standard models
build_all

# Cleanup
rm copyparty-sfx.py
. "../build_helpers.sh"