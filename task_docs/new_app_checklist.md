# Checklist: Adding a New App to WD-NAS-App-Builder

This guide outlines the steps to add a new application to the repository, ensuring compatibility with the build system, automation, and MyCloud OS5 environment.

# Important documentation
- Docs on OS5 apps in general are in ../guides/README.md .
- Docs on our apps build details are in ../apps/README.md .


## 1. Preparation & Discovery
Before starting, gather the following information about the target application:
- [ ] **Source URL:** Where is the binary or source code hosted? (e.g., GitHub Releases, PyPI, official download site).
- [ ] **Runtime Requirements:** What does it need to run? (e.g., Python 3, Node.js, specific libraries, or is it a static binary?).
- [ ] **Persistence Needs:** Does it need a database, config file, or data directory that must survive app updates/reinstalls?
- [ ] **Startup Command:** What is the exact command line to start the application?
- [ ] **Port:** Which TCP/IP port does the application use for its Web UI?

## 2. Scaffolding
Create the application directory structure using the template.

- [ ] **Copy Template:**
  ```bash
  cp -R apps/template apps/<my_new_app>
  ```
- [ ] **Clean Up:** Remove any `gitkeep` files or example artifacts from the copied directory if they aren't needed.

## 3. Configuration (`apkg.rc`)
Edit `apps/<my_new_app>/apkg.rc`. This file defines the package metadata for OS5.

> **CRITICAL:** You **must** change the default values. The CI/CD pipeline (`build-and-release.yml`) will fail if the Description or Icon matches the template defaults.

- [ ] **Package:** Must match your directory name exactly (e.g., `copyparty`).
- [ ] **Version:** Set the initial version (e.g., `1.0.0`).
- [ ] **Description:** Write a unique description. *Do not leave as "A blank template..."*
- [ ] **Icon:** Replace `logo.svg` or `logo.png` in the `web/` folder and update the filename here if necessary.
- [ ] **AddonShowName:** The specific name shown in the NAS App Store UI.

## 4. Build Logic (`build.sh`)
Edit `apps/<my_new_app>/build.sh`. This script runs inside the Docker container (Debian Bullseye) during the build process.

- [ ] **Download Source:** Add commands to `curl` or `wget` the upstream binary/source code.
  - *Tip:* Use the `${VERSION}` variable (passed from `apkg.rc`) to construct download URLs dynamically.
- [ ] **Extract/Install:** Unzip/untar the downloaded files.
- [ ] **Cleanup:** Remove downloaded archives or unnecessary files to keep the `.bin` size small.
- [ ] **Permissions:** Ensure the main executable has execute permissions (`chmod +x`).

## 5. Runtime & Persistence (`init.sh`, `start.sh`)
These scripts run on the NAS itself.

### Persistence Strategy
OS5 apps are installed to a temporary location. To keep data (configs, databases) across updates, you must store them in the `NAS_PROG` directory (usually `/mnt/HD/HD_a2/Nas_Prog/<app_name>`).

- [ ] **Define Config Paths:** In `init.sh`, identify the persistent storage path.
- [ ] **Default Configuration:**
  - Create a default config file in your source (e.g., `apps/<my_new_app>/default.conf`).
  - In `init.sh`, add logic to copy `default.conf` to the persistent location **only if** a config does not already exist there.
  ```bash
  # Example snippet for init.sh
  CONFIG_DIR="${path}/../${pkg}_conf"
  CONFIG_FILE="${CONFIG_DIR}/config.conf"

  if [ ! -d "$CONFIG_DIR" ]; then
      mkdir -p "$CONFIG_DIR"
  fi

  if [ ! -f "$CONFIG_FILE" ]; then
      cp "${path}/default.conf" "$CONFIG_FILE"
  fi
  ```

### Startup Logic
- [ ] **Edit `start.sh`:**
  - Construct the command line arguments.
  - Point the app to the *persistent* config file path.
  - Ensure the app runs in the background (daemonized) or use `start-stop-daemon`.
  - *Example (Python):* `python3 ${path}/app.py -c ${CONFIG_FILE} &`

## 6. Web UI Integration (`web/`)
- [ ] **Edit `web/index.php`:**
  - Update the PHP redirect to point to the correct port of your application (e.g., `header('Location: http://' . $_SERVER['SERVER_NAME'] . ':12345');`).

## 7. Testing
- [ ] **Build Locally:**
  ```bash
  ./build.sh <my_new_app>
  ```
- [ ] **Verify Artifact:** Check `packages/<my_new_app>/` for the generated `.bin` file.
- [ ] **Manual Install:** Upload the `.bin` to your WD NAS via the Web UI to verify it installs, starts, and the settings persist after a reboot.

## 8. CI/CD Verification
- [ ] **Check Auto-Update Logic:** Ensure your `apkg.rc` does not trigger the "Template Default" errors in the GitHub Action validation step.
