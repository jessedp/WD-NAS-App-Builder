# App Template

This is a simple application, where the whole directory is designed to be copied and pasted into the `apps` directory to begin any new project.

## Building the template app

To build an existing app, there is a build script in the root directory that will run the process through a Debian 11 (Bullseye) docker container. For example:

```bash
./build.sh template
```

The WD bin files will be created in `packages/template/0.0.1/*`. A file containing the most recently built version number will also be created in `packages/template/latest`.

If you don't have docker installed, see the [guide](../../docker/README.md) in the `docker` directory. If you're on Windows 10 or 11, see the [Windows guide](../../docker/WINDOWS.md) for WSL / docker installation.

## New App Instructions

- Copy the `apps/template` app directory and create a new app directory from it in the same parent directory.
  - e.g. `cp -R apps/template apps/new_app`
- Edit the `apps/new_app/apkg.rc` file to have a `Package` value which exactly matches the new directory name.
  - e.g. `sed -i 's/Package:.*/Package:\t\t\tnew_app/' apps/new_app/apkg.rc`
  - Other options `apkg.rc` are explained in the [guide](../../guides/README.md).
- The `apps/new_app/build.sh` script runs locally to package your app. Customise it to your app's needs *(e.g. if you need to package downloaded binaries)*.
  - If you're downloading the application files on the WD NAS device itself, you can leave this file alone.
- All other shell scripts (`apps/new_app/*.sh`) run on the WD NAS device itself to perform the actions that are required. See [guide](guides/README.md).