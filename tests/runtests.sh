#!/bin/bash

set -e

BASEDIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)

# Temporary file to store output
TMPFILE=$(mktemp)
trap "rm -f $TMPFILE" EXIT

# Run tests with colors, filter with PHP, and capture output
php artisan test --colors=always "$@" 2>&1 | tee "$TMPFILE"

# Extract exit code from test run
EXIT_CODE=${PIPESTATUS[0]}

# Extract failed test lines (⨯ lines with timing) and display as summary
FAILURES=$(grep "⨯" "$TMPFILE" | grep -E '[0-9]+\.[0-9]+s' || true)

if [ -n "$FAILURES" ]; then
    echo "FAILURES SUMMARY"
    echo "$FAILURES"
fi

exit $EXIT_CODE
