# WD-NAS-App-Builder

This project is a collection of scripts and tools to build and package applications for the Western Digital MyCloud OS5 NAS devices. It utilizes Docker to ensure a consistent build environment (Debian Bullseye) and provides a structured way to define, build, and deploy apps.

## Project Structure

*   **`apps/`**: Contains the source code and configuration for individual applications. Each subdirectory (e.g., `entware`, `syncthing`) represents a separate app.
    *   **`apkg.rc`**: The main configuration file for the package (metadata, version, dependencies).
    *   **`build.sh`**: The script that compiles and prepares the application files (runs locally or in Docker).
    *   **`init.sh`, `install.sh`, `remove.sh`, `start.sh`, `stop.sh`**: Lifecycle scripts that run on the NAS device itself.
    *   **`web/`**: Contains the web UI files for the application (if applicable).
*   **`docker/`**: Contains the Dockerfile and scripts for setting up the build environment.
*   **`mksapkg-OS5`**: The tool used to package the built application into a `.bin` file installable on the NAS.
*   **`packages/`**: The output directory where built packages (`.bin` files) are stored.
*   **`build.sh`**: The master build script in the root directory.

## Building Apps

The primary way to build an app is using the `build.sh` script in the root directory. This script will automatically spin up the Docker container and run the build process.

**Usage:**

```bash
./build.sh <app_name>
```

*   **`<app_name>`**: The name of the directory in `apps/` (e.g., `entware`, `syncthing`).
*   **`all`**: Builds all available apps.

**Example:**

```bash
./build.sh entware
```

The output artifacts will be placed in `packages/<app_name>/<version>/`.

## Creating a New App

1.  **Copy Template**: Start by copying the `apps/template` directory to `apps/<new_app_name>`.
2.  **Configure `apkg.rc`**: Update the `Package` field to match your new directory name and fill in other metadata.
3.  **Customize Scripts**:
    *   Modify `build.sh` to download or compile the binaries you need.
    *   Update `install.sh` and `init.sh` to handle installation paths and startup logic.
    *   Ensure all paths in scripts typically reference `/opt` or the app's persistent data path.

## Development Conventions

*   **Persistence**: Apps should store data in `APP_PERSISTENT_DATA_PATH` (usually linked to `/shares/Volume_1/Nas_Prog/<app_name>`).
*   **Entware Integration**: Many apps rely on Entware. The `entware` app provided here sets up `/opt` and basic package management.
*   **Web UI**: If the app has a web UI, place files in the `web/` subdirectory. These are linked to `/var/www/apps/<app_name>` on the device.
