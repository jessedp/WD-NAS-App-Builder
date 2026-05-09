![wdmycloud-logo-wide](docs/combined_logo_wide.png)

# WD NAS App Builder

WD My Cloud OS5 still has an active user base, but the third-party app ecosystem has largely stalled since WD stopped investing in it. This project fills that gap: reproducible, auto-updating app packages for OS5 devices — installable without SSH, with native update notifications.

---

## For Users

### Available Apps

| App | Latest | Docs |
|-----|--------|------|
| [copyparty](https://github.com/9001/copyparty) | [download](https://github.com/jessedp/WD-NAS-App-Builder/releases/tag/latest-copyparty) | [docs](docs/apps/copyparty/README.md) |
| [Entware](https://entware.net/) | [download](https://github.com/jessedp/WD-NAS-App-Builder/releases/tag/latest-entware) | [docs](docs/apps/entware/README.md) |
| [Syncthing](https://syncthing.net/) | [download](https://github.com/jessedp/WD-NAS-App-Builder/releases/tag/latest-syncthing) | [docs](docs/apps/syncthing/README.md) |
| [Tailscale](https://tailscale.com/) | [download](https://github.com/jessedp/WD-NAS-App-Builder/releases/tag/latest-tailscale) | [docs](docs/apps/tailscale/README.md) |

### Installation

Download the `.bin` for your device and install via **Settings → Apps → Install an app manually** in the WD web admin. No SSH required.

### Auto Updates

Apps check for new upstream releases weekly and send a native OS5 email notification when one is available. Manual reinstall is required to apply the update.

Requires email configured under **Settings → Notifications**.

### Supported Devices

_Tested_: MyCloudPR4100

_Built_: MyCloudPR4100, MyCloudPR2100, MyCloudEX2Ultra, WDMyCloudDL4100, WDMyCloudDL2100, WDMyCloudEX4100, WDMyCloudEX2100, WDMyCloudMirror, WDMyCloud, WDCloud

---

## For Developers

### Building an Existing App

Builds run inside a Debian 11 (Bullseye) Docker container to match the NAS environment:

```bash
./build.sh <app_name>
```

Output goes to `/packages/<app_name>/<version>/`. Requires Docker — see the [Docker guide](docker/README.md) or [Windows guide](docker/WINDOWS.md).

### Creating a New App

A [checklist](task_docs/new_app_checklist.md) covers the full process. Short version:

- Copy `apps/template` → `apps/<new_app>`
- Update `apkg.rc` (`Package` must match the directory name exactly)
- Customize `build.sh` (runs in Docker) and the lifecycle scripts (`install.sh`, `init.sh`, `start.sh`, `stop.sh`, `remove.sh`) which run on the NAS
- See the [app guide](apps/README.md) and [guides](guides/README.md)

### Static Linking

The NAS runs a stripped-down Debian Bullseye with no package manager and outdated libraries. Apps that can't run against the system libraries need to be statically linked.

_Note: Check Entware first — it provides many packages without the complexity._

```bash
./build_static.sh <app_name>
```

Output goes to `/packages/static/<app_name>/<version>/<arch>/`.

### Incomplete / Untested Apps

These exist in the repo but are not pre-built or automatically updated:

- [Node 23.5.0](https://nodejs.org/dist/v23.5.0/node-v23.5.0-linux-x64.tar.xz)
- [Go 1.23.4](https://go.dev/dl/go1.23.4.linux-amd64.tar.gz)
- [phpMyAdmin 5.2.1](https://files.phpmyadmin.net/phpMyAdmin/5.2.1/phpMyAdmin-5.2.1-all-languages.tar.gz)
- [Mailpit 1.21.8](https://github.com/axllent/mailpit/releases/download/v1.21.8/mailpit-linux-amd64.tar.gz)
- [ValKey 8.0.1](https://github.com/valkey-io/valkey/archive/refs/tags/8.0.1.tar.gz)
- [Docker 29.1.3](https://download.docker.com/linux/static/stable/x86_64/docker-29.1.3.tgz) / [Docker-Compose 2.39.4](https://github.com/docker/compose/releases/download/v2.39.4/docker-compose-linux-x86_64) / [Portainer 2.25.1](https://github.com/portainer/portainer/releases/download/2.25.1/portainer-2.25.1-linux-amd64.tar.gz)
  - _Note_: Mostly unusable. On the PR4100 (and likely most devices), all ports share the same network interface — containers exposing ports already used by the NAS (80, 443, etc.) will conflict.

### Project Structure

- `/apps` — app build definitions and lifecycle scripts
- `/docker` — Dockerfiles for the build environment
- `/guides` — notes and reference material
- `/static` — static linking build instructions
- `/packages` — built output (gitignored)

---

## Project Background

### History

This is built on top of [paul-norman/WD-NAS-App-Builder](https://github.com/paul-norman/WD-NAS-App-Builder), which itself builds on work by [Stefan (aka TFL)](https://github.com/stefaang) in the [WDCommunity repo](https://github.com/WDCommunity/wdpksrc/). The `helpers.sh` script was influenced by Cerberus's [App Template](https://drive.google.com/uc?export=download&id=1Qds0Nh2o4DPlGG6WfIlXLkcChsZlqrp7) from the [WD Community Support forums](https://community.wd.com/t/my-cloud-os5-app-template/286542).

The original focus was getting Jellyfin running natively on WD devices (see below). The focus here is broader: keeping common apps current and installable.

### State of the WD OS5 App Ecosystem (2026)

- **WDCommunity** ([wdpksrc](https://github.com/WDCommunity/wdpksrc)) appears largely inactive
- Existing third-party package repos are mostly stale
- WD itself stopped meaningfully investing in OS5 app support
- OS5 devices still have a real user base with no official upgrade path

This repo exists to automate what the community used to do manually: build, package, and distribute current versions of useful apps for these devices. The goal is sustainability, not one-off binaries.

### Jellyfin Experiments

An original motivation was native Jellyfin support on WD hardware. That is not happening here, but preserved for reference:

- [x] Wrapping existing Jellyfin Debian builds (`jellyfin` app) — installs and runs
- [ ] Loads Jellyfin-Web *(truncates HTML output)*
- [ ] Use Jellyfin-Ffmpeg *(falls back to 3rd party statically linked version)*
- [ ] Statically linked ARM build
- [ ] Automatic SSH installation via `build.sh`

---

## Disclaimer

GenAI has absolutely touched this repo, but things should be manually checked/tested.
