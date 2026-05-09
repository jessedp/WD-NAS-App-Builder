#!/bin/sh
# Checks upstream entware for a new release and fires OS5 alert 1501 if found.
# Added to root crontab by install.sh; removed by remove.sh.
# Can also be run manually: sh /path/to/entware/check_update.sh

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
APP_NAME="entware"
UPSTREAM_API="https://api.github.com/repos/Entware/Entware/releases/latest"
ALERT_BIN="/usr/sbin/alert_test"
ALERT_CODE="1501"

CURRENT_VERSION=$(grep "^Version:" "${SCRIPT_DIR}/apkg.rc" | awk '{print $2}')
[ -z "$CURRENT_VERSION" ] && exit 0

PERSISTENT_DIR="$(dirname "${SCRIPT_DIR}")/${APP_NAME}_conf"
LAST_ALERT_FILE="${PERSISTENT_DIR}/last_update_alert"

LATEST=$(curl -sf --max-time 15 "${UPSTREAM_API}" \
    | grep '"tag_name"' \
    | sed 's/.*"tag_name":[[:space:]]*"\([^"]*\)".*/\1/' \
    | sed 's/^v//')
[ -z "$LATEST" ] && exit 0

LAST_ALERTED=""
[ -f "$LAST_ALERT_FILE" ] && LAST_ALERTED=$(cat "$LAST_ALERT_FILE")

if [ "$LATEST" != "$CURRENT_VERSION" ] && [ "$LATEST" != "$LAST_ALERTED" ]; then
    [ -x "$ALERT_BIN" ] && "$ALERT_BIN" -a "$ALERT_CODE" -p "<br><br><b>$APP_NAME $LATEST</b>" -f
    echo "$LATEST" > "$LAST_ALERT_FILE"
    echo "Alert fired: $APP_NAME $LATEST available (installed: $CURRENT_VERSION)"
else
    echo "No alert: installed=$CURRENT_VERSION latest=$LATEST last_alerted=$LAST_ALERTED"
fi
