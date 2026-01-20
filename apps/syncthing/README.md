# Syncthing for WD MyCloud OS5

This is a package for Syncthing for WD MyCloud OS5 NAS devices.

## Building the app

To build the app for the PR4100, run the build script in the root directory:

```bash
./build.sh syncthing
```

The WD bin files will be created in `packages/syncthing/2.0.13/*`. A file containing the most recently built version number will also be created in `packages/syncthing/latest`.

## Configuration

Syncthing is configured to run on port 8384. The configuration and data are stored in the persistent data directory for the app.
