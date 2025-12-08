#!/bin/bash

set -e

# Télécharger et installer Composer
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php

# Le rendre exécutable globalement (optionnel)
sudo mv composer.phar /usr/local/bin/composer
composer --version

# Ou juste l'utiliser localement
# php composer.phar --version

php -r "unlink('composer-setup.php');"
