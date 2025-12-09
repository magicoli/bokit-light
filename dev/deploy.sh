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

rsync -Wavz --delete $BASE_DIR/ $DEPLOY_BOKIT/ --exclude=.git* --exclude-from=.gitignore
rsync -Wavz $BASE_DIR/wordpress/bokit-connector/ $DEPLOY_WP/wp-content/plugins/bokit-connector/
curl -I $DEPLOY_BOKIT_URL | head -1
open $DEPLOY_BOKIT_URL
