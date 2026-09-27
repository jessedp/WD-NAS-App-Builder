# Boot Scripts for WD MyCloud OS5

Runs your own shell scripts every time the NAS boots.

> **Looking for boot4shell?** This is a maintained replacement for the unlicensed, unmaintained [boot4shell](https://github.com/Sebastien-Gutierrez/boot4shell) app: same idea (run a script at boot), plus a persistent scripts directory that survives upgrades, per-script timeouts, a log, and an in-browser editor for scripts and root's SSH keys.

OS5 rebuilds the root filesystem on every reboot. Anything you change outside the data volume is lost: root's `~/.ssh/authorized_keys`, lines you add to root's crontab, edits to `/etc/profile`, bind mounts, and so on. Boot Scripts puts it back for you.

## Installation

1. Download the `.bin` package for your NAS model from the [releases](../../../packages/bootscripts/latest) directory.
2. Install the app via the "App Store" in the WD MyCloud web interface using the "Install an app manually" option.
3. Make sure the app is **enabled**. Only enabled apps are started at boot.

## Usage

The common case, keeping root's SSH keys, takes three steps:

1. **Install** the app and make sure it is enabled.
2. **Configure**: click the app's Configure button to open its page inside the NAS web UI.
3. In **SSH keys for root**, add or verify every public key you want, one per line, and press **Save keys**. They are installed immediately and restored on every boot from then on.

The box starts out showing only keys that were already on the NAS when the app was installed. Keys from machines that never had access are not there until you paste them.

From the same page you can also:

- see which scripts will run, and open, edit, create or delete them
- paste public keys for root's SSH access
- press **Run now** to run everything immediately, and watch the log

Everything lives in `bootscripts_conf/` next to the app on your data volume, so you can also manage it over SSH or a share:

```
/mnt/HD/HD_a2/Nas_Prog/bootscripts_conf/
  scripts.d/*.sh          your scripts, run in name order (10-..., 20-...)
  examples/               shipped examples to copy from
  ssh/authorized_keys     public keys for root
  crontab.root            crontab lines to keep
  bootscripts.log         what happened on each run
```

Scripts run as root. One failing script does not stop the others. A script that runs for more than 5 minutes is killed. Output and exit codes go to `bootscripts.log`.

### Keeping SSH keys (installed by default)

`10-ssh-authorized-keys.sh` is enabled on first install. Each time it runs it merges `ssh/authorized_keys` with root's live `~/.ssh/authorized_keys` and writes the union to both, so:

- **No SSH access at all?** Paste your public key into the "SSH keys for root" box on the app page and press Save keys. You can log in immediately, and after every future reboot.
- **Added a key with `ssh-copy-id`?** Press Run now (or run the script) once, so it is saved before the next reboot.
- **Removing a key:** delete it from `ssh/authorized_keys`, then from the live file (or reboot).

Root's home on OS5 is `/home/root`, not `/root`. The script reads the real path from `/etc/passwd`.

### Keeping crontab lines

Copy `examples/20-root-crontab.sh` into `scripts.d/` and put the lines you want in `crontab.root`, one job per line. Any line missing from root's crontab is appended on each run.

### Your own scripts

Write plain BusyBox `sh`. If Entware is installed its `bin` directories are already on `PATH`. Useful environment:

| Variable             | Value                                      |
| -------------------- | ------------------------------------------ |
| `BOOTSCRIPTS_DIR`    | the `bootscripts_conf` directory           |
| `BOOTSCRIPTS_LOG`    | the log file                               |
| `BOOTSCRIPTS_REASON` | `start` (boot / enable), `web`, or `manual` |

Scripts run in the background after the app system starts the app, so they do not hold up other apps. Do not rely on ordering relative to other apps starting.

## Persistent Data

`bootscripts_conf/` is kept when the app is upgraded or removed, so your scripts and keys survive. Delete it yourself if you want a clean slate.

## Credits

The idea comes from [boot4shell](https://github.com/Sebastien-Gutierrez/boot4shell). This is a from-scratch implementation for this repo's build system.
