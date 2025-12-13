#!/bin/bash

set -e

BASEDIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)

# Run tests
# php artisan test --stop-on-failure --coverage-text --coverage-html=coverage
php artisan test --stop-on-failure --testdox
