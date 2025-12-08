# Guide de test - Backend Bokit

## 1. Setup initial

```bash
cd ~/Projects/bokit-light

# Rendre le script exécutable
chmod +x dev/setup-backend.sh

# Lancer le setup
./dev/setup-backend.sh
```

Ce script va :
- Installer sabre/vobject
- Configurer SQLite dans .env
- Créer la base de données
- Lancer les migrations

## 2. Test avec des données de démo

```bash
# Importer la config de test (calendrier Google public)
php artisan bokit:import-config storage/config/properties.test.json
```

Tu devrais voir :
```
📥 Importing config from: storage/config/properties.test.json

✓ Property: Villa Test
  → Source: Test Calendar 1

✅ Import successful!
  Properties: 1
  iCal sources: 1
```

## 3. Synchroniser les calendriers

```bash
php artisan bokit:sync
```

Tu devrais voir quelque chose comme :
```
🏖️  Starting Bokit calendar synchronization...

Found 1 source(s) to sync

Syncing: Test Calendar 1 (Property: Villa Test)
  ✓ Created: X, Updated: 0
  Last synced: 1 second ago

Summary:
  Bookings created: X
  Bookings updated: 0

✅ Synchronization complete!
```

## 4. Vérifier les données

```bash
# Ouvrir Tinker (REPL Laravel)
php artisan tinker
```

Puis dans Tinker :

```php
// Lister les propriétés
App\Models\Property::all();

// Voir les sources iCal
App\Models\IcalSource::with('property')->get();

// Voir les réservations importées
App\Models\Booking::with('property')->get();

// Voir les réservations d'une propriété spécifique
App\Models\Property::first()->bookings;
```

Pour sortir de Tinker : `exit` ou `Ctrl+D`

## 5. Test avec tes vraies URLs iCal

Une fois que le test fonctionne :

1. Copie l'exemple de config :
```bash
cp storage/config/properties.example.json storage/config/properties.json
```

2. Édite `storage/config/properties.json` avec tes vraies données

3. Importe ta config :
```bash
php artisan bokit:import-config
```

4. Synchronise :
```bash
php artisan bokit:sync
```

## Commandes utiles

```bash
# Synchroniser uniquement une source spécifique
php artisan bokit:sync --source=1

# Synchroniser uniquement une propriété
php artisan bokit:sync --property=1

# Réimporter la config (met à jour sans perdre les réservations)
php artisan bokit:import-config

# Reset complet de la base (ATTENTION : supprime tout)
php artisan migrate:fresh
```

## En cas de problème

Vérifie les logs :
```bash
tail -f storage/logs/laravel.log
```

Teste la connexion SQLite :
```bash
php artisan tinker
>>> DB::connection()->getPdo();
```

Vérifie que sabre/vobject est bien installé :
```bash
composer show sabre/vobject
```
