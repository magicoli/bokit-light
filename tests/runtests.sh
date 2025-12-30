#!/bin/bash

set -e

BASEDIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)

# Temporary file to store output
TMPFILE=$(mktemp)
trap "rm -f $TMPFILE" EXIT

if [ "$TESTING_WITH_SYNC" = "true" ]
then
    echo "Including sync in tests... To disable, unset TESTING_WITH_SYNC" >&2
else
    echo "Skipping sync... To enable, execute:" >&2
    echo "  TESTING_WITH_SYNC=true ${0}" >&2
fi

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
