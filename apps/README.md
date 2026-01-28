# Apps

This `apps` directory houses the build files for all WD NAS devices in their own directories.

They are all packaged from an editable `build.sh` bash file, which has a few helpers to parse the `apkg.rc` file and load some useful variables and functions into it *(see [Build Helpers](#build-helpers) below)*.

They must contain all the shell files as described in the [guide](../guides/README.md#package-contents), but to make creating these files easier, they all load in an identical set of pre-defined variables and functions *(see [Script Helpers](#script-helpers) below)*.

## Building an app

From the project root directory:

```bash
./build.sh <app_name>
```

The WD bin files will be created in `packages/ <app_name>/`.

If you don't have docker installed, see the [guide](../../docker/README.md) in the `docker` directory. If you're on Windows 10 or 11, see the [Windows guide](../../docker/WINDOWS.md) for WSL / docker installation.

## Build Helpers

The `build_helpers.sh` defines some useful variables and functions, but also ensures that files are moved to the correct locations before and after build.

In addition to these, build scripts also load in all [APKG helpers](#apkg-helpers).

### Variables

| Variable         | Description                                      |
| ---------------- | ------------------------------------------------ |
| `${APP_PATH}`    | The absolute path of the app                     |
| `${APP_NAME}`    | The directory name *(name)* of the app           |
| `${APP_VERSION}` | The version of the app                           |
| `${APPS_PATH}`   | The absolute path of the parent `apps` directory |
| `${REPO_PATH}`   | The absolute path of the whole project directory |
| `${RELEASE_DIR}` | The directory where this app will be built       |

### Functions

`build()` - handles building the package. It accepts a list of [model names](../guides/README.md#packaging-command) and a final variable of the architecture *(either `amd64` or `armhf`) for those specific models.

```bash
build "MyCloudPR4100" "MyCloudPR2100" "WDMyCloudDL4100" "WDMyCloudDL2100" "amd64"
```

`abort()` - stops execution and restores any temporarily moved files with an error message.

```bash
abort "$(pwd)/filename could not be found"
```

## Script Helpers

WD NAS device scripts are called from two locations and with three combinations of input parameters! This script normalises this and provides a consistent set of variables / functions for each.

In addition to these, all scripts also load in all [APKG helpers](#apkg-helpers).

### Variables

| Variable                      | Description                                                      | Example                            |
| ----------------------------- | ---------------------------------------------------------------- | ---------------------------------- |
| `${LOG}`                      | The location of the install / run log                            | `/tmp/debug_apkg`                  |
| `${APP_SCRIPT}`               | The name of the shell script currently running                   | `install.sh`                       |
| `${APP_NAME}`                 | The name of the app                                              | `template`                         |
| `${APP_PATH}`                 | The absolute path to where the app is *(or will be)* installed   | `/mnt/HD/HD_a2/Nas_Prog/template`  |
| `${APP_VERSION}`              | The version of the app                                           | `0.0.1`                            |
| `${APP_WEB_PATH}`             | The location of the web path that this app will use              | `/var/www/template`                |
| `${APP_PORT}`                 | The port that this app will listen on                            | `1234`                             |
| `${APP_UPLOAD_PATH}`          | The path where the app will be initially uploaded                | `/mnt/HD/HD_a2/Nas_Prog/_install`  |
| `${APPS_PATH}`                | The path where all apps will be installed                        | `/mnt/HD/HD_a2/Nas_Prog`           |
| `${APP_PERSISTENT_DATA_PATH}` | The path where any persistent data for this app should be stored | `/mnt/HD/HD_a2/Nas_Prog/_template` |
| `${APP_PERSISTENT_DATA_PATH}` | Alt path where any persistent data for this app should be stored | `/mnt/HD/HD_a2/Nas_Prog/template_conf` |
| `${USER_GROUP}`               | The system group that the app should run under                   | `mygroup`                          |
| `${USER_NAME}`                | The system user that the app should run as                       | `myuser`                           |
| `${LDC_PATH}`                 | The LD config file path                                          | `/etc/ld.so.conf`                  |
| `${PROFILE_PATH}`             | The shell profile path                                           | `/etc/profile`                     |
| `${HARDWARE_MODEL}`           | The WD NAS device [model](../guides/README.md#packaging-command) | `MyCloudPR4100`                    |
| `${HARDWARE_KERNEL_ARCH}`     | The WD NAS device kernel architecture *(x86_64 or armv7l)*       | `x86_64`                           |
| `${HARDWARE_DEBIAN_ARCH}`     | The WD NAS device Debian architecture *(amd64 or armhf)*         | `amd64`                            |
| `${HARDWARE_TOOLCHAIN}`       | The toolchain needed to build for the architecture               | `x86_64-linux-gnu`                 |
| `${DATA_VOL_1}`               | The first data volume *(if present)*                             | `/mnt/HD/HD_a2`                    |
| `${DATA_VOL_2}`               | The second data volume *(if present)*                            | `/mnt/HD/HD_b2`                    |
| `${DATA_VOL_3}`               | The third data volume *(if present)*                             | `/mnt/HD/HD_c2`                    |
| `${DATA_VOL_4}`               | The fourth data volume *(if present)*                            | `/mnt/HD/HD_d2`                    |

### Functions

`log` - a simple function to write to a log file

```bash
log "Log contents"
```

## APKG Helpers

The helpers for the `apkg.rc` file may be used **both** in the local build script and on the WD NAS devices, so it has been separated into its own file.

### Variables

| Variable                      | Description                                                  |
| ----------------------------- | ------------------------------------------------------------ |
| `${APKG_PACKAGE}`             | The app name                                                 |
| `${APKG_VERSION}`             | The app version                                              |
| `${APKG_PACKAGER}`            | The packager's name                                          |
| `${APKG_EMAIL}`               | The creator / packager's email *(for support)*               |
| `${APKG_HOMEPAGE}`            | The app's homepage                                           |
| `${APKG_DESCRIPTION}`         | A brief app description                                      |
| `${APKG_ICON}`                | A logo to show in the web UI                                 |
| `${APKG_ADDON_SHOW_NAME}`     | The Web UI app name                                          |
| `${APKG_ADDON_INDEX_PAGE}`    | The web UI configure source                                  |
| `${APKG_ADDON_USED_PORT}`     | The port used by the app                                     |
| `${APKG_ADDON_DEFAULT_PORT}`  |                                                              |
| `${APKG_ADDON_DEFAULT_GROUP}` | The system group that the app should run under               |
| `${APKG_ADDON_DEFAULT_USER}`  | The system user that the app should run as                   |
| `${APKG_INST_DEPEND}`         | Any other apps that this app depends upon for installation   |
| `${APKG_INST_CONFLICT}`       | Any other apps that this app conflicts with at installation  |
| `${APKG_START_DEPEND}`        | Any other apps that this app depends upon at startup         |
| `${APKG_START_CONFLICT}`      | Any other apps that this app conflicts with at startup       |
| `${APKG_CENTER_TYPE}`         | Embed app configuration UI: 0 - own page, 1 - web UI page    |
| `${APKG_USER_CONTROL}`        | Controllable by: 0 - any user, 1 - admin only                |
| `${APKG_MIN_FW_VER}`          | The minimum compatible firmware version                      |
| `${APKG_MAX_FW_VER}`          | The maximum compatible firmware version                      |
| `${APKG_INDIVIDUAL_FLAG}`     | The web UI will show some notice information for apps        |
| `${APKG_HIDDEN}`              | Should the app appear for users                              |

***N.B.** these variables may well be blank (depending upon the app).*

### Functions

`check_apkg_variables` - accepts no arguments, but checks that all `apkg.rc` variables are valid.

```bash
check_apkg_variables
```
