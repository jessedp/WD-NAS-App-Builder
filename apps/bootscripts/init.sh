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
# Initialisation script: prepares the web page and the persistent layout
#  - Also runs on every boot, before start.sh
# ----------------------------------------------------------------------

# Web page (embedded in the OS5 UI)
APP_WEB_PATH="/var/www/apps/${APP_NAME}"
log "creating web path: ${APP_WEB_PATH}"
mkdir -p "${APP_WEB_PATH}"
ln -sf "${APP_PATH}"/web/* "${APP_WEB_PATH}/" >> ${LOG} 2>&1

# Persistent layout:
#   bootscripts_conf/
#     scripts.d/            <- your scripts, *.sh, run in name order on boot
#     examples/             <- shipped examples, refreshed on every install
#     ssh/authorized_keys   <- used by the ssh example
#     bootscripts.log       <- runner output
FIRST_INSTALL=0
if [ ! -d "${APP_PERSISTENT_DATA_PATH}/scripts.d" ]; then
	FIRST_INSTALL=1
fi

log "preparing persistent directory: ${APP_PERSISTENT_DATA_PATH}"
mkdir -p "${APP_PERSISTENT_DATA_PATH}/scripts.d" \
         "${APP_PERSISTENT_DATA_PATH}/examples" \
         "${APP_PERSISTENT_DATA_PATH}/ssh"
chmod 700 "${APP_PERSISTENT_DATA_PATH}/ssh"

# Always refresh the examples so upgrades ship fixes; never touch scripts.d on upgrade
cp -f "${APP_PATH}"/examples/*.sh "${APP_PERSISTENT_DATA_PATH}/examples/"

if [ "${FIRST_INSTALL}" = "1" ]; then
	log "first install: enabling the ssh authorized_keys example"
	cp -f "${APP_PATH}/examples/10-ssh-authorized-keys.sh" "${APP_PERSISTENT_DATA_PATH}/scripts.d/"
fi

if [ ! -f "${APP_PERSISTENT_DATA_PATH}/README.txt" ]; then
cat > "${APP_PERSISTENT_DATA_PATH}/README.txt" <<'TXT'
Boot Scripts
============

Every *.sh file in scripts.d/ is run, in name order, each time the app starts
(that is: on every boot while the app is enabled, when you press Enable, and
when you press "Run now" in the app page).

  scripts.d/            your scripts (10-foo.sh, 20-bar.sh, ...)
  examples/             shipped examples - copy one into scripts.d/ to use it
  ssh/authorized_keys   public keys restored to root's ~/.ssh by the ssh example
  crontab.root          lines re-added to root's crontab by the crontab example
  bootscripts.log       output of every run

Scripts run as root with BOOTSCRIPTS_DIR (this directory), BOOTSCRIPTS_LOG and
BOOTSCRIPTS_REASON (start|web|manual) in the environment. A script that runs
for more than 5 minutes is killed. One failing script does not stop the others.
TXT
fi
