#!/bin/bash

set -e

log() {
    echo "$PGM: $*" >&2
}

end() {
    log "$*"
    exit 0
}

fail() {
    log "$*"
    exit 1
}

PGM=$(basename "$0")
SCRIPT_DIR=$(cd $(dirname "$0") && pwd)
log "SCRIPT_DIR=$SCRIPT_DIR"
BASE_DIR=$(dirname "$SCRIPT_DIR")
log "BASE_DIR: $BASE_DIR"
[ -f $SCRIPT_DIR/.env ] && . $SCRIPT_DIR/.env && log "$SCRIPT_DIR/.env loaded"
[ -z "$DEPLOY_BOKIT" ] && fail DEPLOY_BOKIT not defined
[ -z "$DEPLOY_WP" ] && fail DEPLOY_WP not defiled
log DEPLOY_BOKIT=$DEPLOY_BOKIT
log DEPLOY_WP=$DEPLOY_WP

log deploying bokit app on $DEPLOY_BOKIT
rsync --progress -Wavz --delete $BASE_DIR/ $DEPLOY_BOKIT/ --exclude=.git* --exclude-from=.gitignore
remote_bokit_host=$(echo $DEPLOY_BOKIT | cut -d: -f 1)
remote_bokit_path=$(echo $DEPLOY_BOKIT | cut -d: -f 2)
log "executing ssh $remote_bokit_host 'cd $remote_bokit_path && php artisan migrate --force'"
ssh $remote_bokit_host "cd $remote_bokit_path && php artisan migrate --force"
log "deploying bokit-connector plugin on $DEPLOY_WP"
rsync --progress -Wavz $BASE_DIR/wordpress/bokit-connector/ $DEPLOY_WP/wp-content/plugins/bokit-connector/
log "checking bokit app deployment"
curl -I $DEPLOY_BOKIT_URL | head -1
log "opening $DEPLOY_BOKIT_URL in local browser"
open $DEPLOY_BOKIT_URL
