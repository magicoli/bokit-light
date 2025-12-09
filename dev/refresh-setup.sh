#!/bin/bash

set -e

# Ensure database directory exists
mkdir -p storage/database/default

# 1. Reset complet de la base
php artisan migrate:fresh

# 2. Réimporter la config
php artisan bokit:import-config

# 3. Resynchroniser
php artisan bokit:sync

# Cleanup: Remove old database location (only if it exists)
if [ -f "database/database.sqlite" ]; then
    echo "Removing old database location: database/database.sqlite"
    rm database/database.sqlite
fi
