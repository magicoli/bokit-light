#!/bin/bash
set -e

echo "🏖️  Configuration de Bokit..."

# 1. Installer les dépendances PHP nécessaires
echo "📦 Installation des packages PHP..."
composer require sabre/vobject

# 2. Copier .env.example vers .env si pas déjà fait
if [ ! -f .env ]; then
    echo "📝 Création du fichier .env..."
    cp .env.example .env
fi

# 3. Générer la clé d'application si pas déjà fait
if ! grep -q "APP_KEY=base64:" .env; then
    echo "🔑 Génération de la clé d'application..."
    php artisan key:generate
fi

# 4. Créer la base SQLite si elle n'existe pas
if [ ! -f database/database.sqlite ]; then
    echo "💾 Création de la base de données SQLite..."
    touch database/database.sqlite
fi

# 5. Configuration de l'environnement pour SQLite
echo "⚙️  Configuration de SQLite dans .env..."
sed -i.bak 's/DB_CONNECTION=.*/DB_CONNECTION=sqlite/' .env
sed -i.bak 's|DB_DATABASE=.*|DB_DATABASE='$(pwd)'/database/database.sqlite|' .env
rm .env.bak

echo "✅ Configuration terminée!"
echo ""
echo "Prochaines étapes:"
echo "1. Lance les migrations : php artisan migrate"
echo "2. Démarre le serveur : php artisan serve"
echo ""
