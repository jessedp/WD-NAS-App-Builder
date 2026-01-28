#!/bin/sh
. "$1/helpers.sh" "$0" "$1";

log "Stopping Entware services..."

if [ -x /opt/etc/init.d/rc.unslung ]; then
    /opt/etc/init.d/rc.unslung stop
fi

# We do NOT unmount /opt here usually, because other things might depend on it?
# But if we are uninstalling, remove.sh will run.
# If just stopping, maybe keep it mounted?
# But if we restart the app, start.sh checks mount.
# If we want to be clean:
# umount /opt
# BUT: if user is logged in via SSH and using /opt/bin/..., umount will fail (busy).
# So lazy unmount or skip unmount might be safer for interactive users.
# However, if we don't unmount, and we upgrade, things might get weird.
# I'll attempt to unmount.

umount /opt 2>/dev/null