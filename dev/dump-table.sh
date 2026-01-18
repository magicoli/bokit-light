#!/bin/bash

[ -z "$1" ] && echo "Usage: $0 table [table2] [...]" >&2 && exit 1

db=storage/database/default.sqlite

for table in $*
do
    sqlite3 $db ".schema $table" \
    | sed -E "s/, /,\n\t/g; s/\((.*,.*)\)/(\n\t\\1\n)/g" \
    | tee tmp/schema.$table.sql
done
