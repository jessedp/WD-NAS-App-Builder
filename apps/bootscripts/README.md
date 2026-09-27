# Boot Scripts for WD MyCloud OS5

Runs your own shell scripts every time the NAS boots. OS5 rebuilds its root filesystem on every reboot, so anything you change outside the data volume (root's `~/.ssh/authorized_keys`, extra crontab lines, `/etc/profile` tweaks) is gone next boot. This app is the sanctioned way to put it back: at boot the app system runs every enabled app's `start.sh`, and this app's `start.sh` runs everything in your `scripts.d`.

Inspired by [boot4shell](https://github.com/Sebastien-Gutierrez/boot4shell), rewritten from scratch for this repo's build system with a persistent scripts directory, per-script timeouts, a log, and an in-browser editor.

## Building the app

```bash
./build.sh bootscripts
```

Nothing is downloaded: the package is just the lifecycle scripts, `run_scripts.sh`, the examples and the web page.

## Layout on the NAS

```
Nas_Prog/bootscripts/            the app (replaced on upgrade)
Nas_Prog/bootscripts_conf/       persistent, kept across upgrades and removal
  scripts.d/*.sh                 your scripts, run in name order
  examples/                      shipped examples, refreshed on each install
  ssh/authorized_keys            keys restored by 10-ssh-authorized-keys.sh
  crontab.root                   lines re-added by 20-root-crontab.sh
  bootscripts.log                output of every run
```

On first install `10-ssh-authorized-keys.sh` is copied into `scripts.d` so the common case works out of the box. It is a no-op until there are keys to restore.

## Runner

`run_scripts.sh <conf dir> [reason]` is plain BusyBox `sh` and can be run by hand. It runs each `*.sh` with `sh` (or directly if executable), gives each 5 minutes (`BOOTSCRIPTS_TIMEOUT` overrides), logs exit codes, refuses to run twice at once, and keeps the log to a couple of thousand lines. Scripts get `BOOTSCRIPTS_DIR`, `BOOTSCRIPTS_LOG` and `BOOTSCRIPTS_REASON` (`start`, `web` or `manual`) in their environment.

See [the user docs](../../docs/apps/bootscripts/README.md) for usage.
