# copyparty for WD MyCloud OS5

Portable file server with accelerated resumable uploads, dedup, WebDAV, SFTP, FTP, TFTP, zeroconf, media indexer, thumbnails++ all in one file.

## Building the app

To build the app, run the build script in the root directory:

```bash
./build.sh copyparty
```

## Configuration

- **Port**: copyparty runs on port `3923`.
- **Persistence**: Configuration is stored in `copyparty_conf/` on the application volume.
- **Default Login**: `admin / admin` (configured in `copyparty.conf`).

## Shared Folders
By default, the app shares `/mnt/HD/HD_a2` (the main data volume) at the root.
