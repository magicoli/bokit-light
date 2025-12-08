#!/bin/bash

set -e
# 1. Reset complet de la base
php artisan migrate:fresh

# 2. Réimporter la config
php artisan bokit:import-config

# 3. Resynchroniser
php artisan bokit:sync
