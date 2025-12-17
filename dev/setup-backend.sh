#!/bin/bash
set -e

echo "🏖️  Setup Bokit - Backend"
echo ""

# 1. Installer les dépendances PHP
echo "📦 Installation de sabre/vobject..."
composer require sabre/vobject

echo ""
echo "⚙️  Configuration..."

# 2. Copier .env.example vers .env si pas déjà fait
if [ ! -f .env ]; then
    echo "📝 Création du fichier .env..."
    cp .env.example .env
fi

# 3. Configurer SQLite
echo "💾 Configuration de SQLite..."
sed -i.bak 's/DB_CONNECTION=.*/DB_CONNECTION=sqlite/' .env
sed -i.bak '/^DB_HOST=/d' .env
sed -i.bak '/^DB_PORT=/d' .env
sed -i.bak '/^DB_DATABASE=/d' .env
sed -i.bak '/^DB_USERNAME=/d' .env
sed -i.bak '/^DB_PASSWORD=/d' .env
rm .env.bak

# 4. Ajouter la ligne DB_DATABASE après DB_CONNECTION
if ! grep -q "DB_DATABASE=" .env; then
    sed -i.bak "/^DB_CONNECTION=sqlite/a\\
DB_DATABASE=$(pwd)/database/default.sqlite
" .env
    rm .env.bak
fi

# 5. Créer la base SQLite
if [ ! -f database/default.sqlite ]; then
    echo "💾 Création de default.sqlite..."
    touch database/default.sqlite
fi

# 6. Générer la clé d'application si nécessaire
if ! grep -q "APP_KEY=base64:" .env; then
    echo "🔑 Génération de la clé d'application..."
    php artisan key:generate
fi

# 7. Lancer les migrations
echo ""
echo "🗄️  Lancement des migrations..."
php artisan migrate --force

echo ""
echo "✅ Setup terminé!"
echo ""
echo "Prochaines étapes:"
echo ""
echo "1. Édite storage/config/properties.json avec tes vraies URLs iCal"
echo "   (copie properties.example.json comme base)"
echo ""
echo "2. Importe la config:"
echo "   php artisan bokit:import-config"
echo ""
echo "3. Synchronise les calendriers:"
echo "   php artisan bokit:sync"
echo ""
echo "4. Vérifie que ça marche:"
echo "   php artisan tinker"
echo "   >>> App\\Models\\Property::with('bookings')->get()"
echo ""
