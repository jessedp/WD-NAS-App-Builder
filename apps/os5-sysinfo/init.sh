#!/bin/sh

# Called upon app installation
	#	 1. $UPLOAD_PATH/install.sh $UPLOAD_PATH $INSTALL_PATH
	# -> 2. $INSTALL_PATH/init.sh $INSTALL_PATH
	#	 3. $INSTALL_PATH/start.sh $INSTALL_PATH

# Called upon app reinstallation
	#	 1. $INSTALL_PATH/stop.sh
	#	 2. $INSTALL_PATH/clean.sh
	#	 3. $INSTALL_PATH/preinst.sh $INSTALL_PATH
	#	 4. $INSTALL_PATH/remove.sh $INSTALL_PATH
	#	 5. $UPLOAD_PATH/install.sh $UPLOAD_PATH $INSTALL_PATH
	# -> 6. $INSTALL_PATH/init.sh $INSTALL_PATH
	#	 7. $INSTALL_PATH/start.sh $INSTALL_PATH

# Load all the useful variables
. "$1/helpers.sh" "$0" "$1";

# ----------------------------------------------------------------------
# Initialisation script: web page links and the persistent layout.
#  - Runs on every boot (before start.sh) and on every (re)install.
# ----------------------------------------------------------------------

APP_WEB_PATH="/var/www/apps/${APP_NAME}"
log "creating web path: ${APP_WEB_PATH}"
mkdir -p "${APP_WEB_PATH}"
ln -sf "${APP_PATH}"/web/* "${APP_WEB_PATH}/" >> ${LOG} 2>&1

# Persistent layout:
#   os5-sysinfo_conf/
#     os5-sysinfo.conf   settings (seeded from default.conf on first install only)
#     status.json        latest document (always written)
#     os5-sysinfo.log    collector log (rotated by the collector)
#     os5-sysinfo.out    stdout/stderr of the collector process (crash tracebacks)
CONF_FILE="${APP_PERSISTENT_DATA_PATH}/os5-sysinfo.conf"
log "preparing persistent directory: ${APP_PERSISTENT_DATA_PATH}"
mkdir -p "${APP_PERSISTENT_DATA_PATH}"

if [ ! -f "${CONF_FILE}" ]; then
	log "first install: seeding ${CONF_FILE}"
	cp -f "${APP_PATH}/default.conf" "${CONF_FILE}"
fi
chmod 600 "${CONF_FILE}"

if [ ! -f "${APP_PERSISTENT_DATA_PATH}/README.txt" ]; then
cat > "${APP_PERSISTENT_DATA_PATH}/README.txt" <<'TXT'
OS5 SysInfo
===========

  os5-sysinfo.conf   settings; edit here or from the app's Configure page, then restart
                     the app (disable/enable) to apply. Contains the API key: keep it private.
  status.json        the latest JSON document, rewritten every INTERVAL seconds
  os5-sysinfo.log    collector log
  os5-sysinfo.out    process output (only crash tracebacks end up here)

Data endpoints:
  http://<nas-ip>:<LISTEN_PORT>/status   full document (401 without the key, if one is set)
  http://<nas-ip>:<LISTEN_PORT>/health   small ok/error summary for monitors
  OUTPUT_PATH                            a copy of status.json on a share, if configured

Use the NAS IP address, not its name: the WD UI proxy rejects unknown host names, and
consumers should not depend on name resolution anyway.
TXT
fi

# Leftovers from a crashed collector
rm -f "${APP_PERSISTENT_DATA_PATH}"/xmldbc-*.xml "${APP_PERSISTENT_DATA_PATH}"/.status-*.tmp

# A pidfile whose process is gone (e.g. after a reboot the file is gone anyway, but be safe)
PID_FILE="/var/run/${APP_NAME}.pid"
if [ -f "${PID_FILE}" ] && ! kill -0 "$(cat "${PID_FILE}" 2>/dev/null)" 2>/dev/null; then
	rm -f "${PID_FILE}"
fi
