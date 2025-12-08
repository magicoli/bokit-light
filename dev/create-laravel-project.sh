#!/bin/bash

set -e
composer create-project laravel/laravel temp
mv temp/* temp/.* . 2>/dev/null
rm -rf temp
