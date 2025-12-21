# Système de Migration Automatique - Bokit Light

## ✅ Fichiers créés/modifiés

### Nouveaux fichiers

**Migrations:**
- `database/migrations/2025_12_11_100000_add_status_to_bookings.php` - Ajoute colonne `status`

**Middleware:**
- `app/Http/Middleware/ApplyMigrations.php` - Détecte et exécute migrations automatiquement avec backup

**Contrôleurs:**
- `app/Http/Controllers/UpdateController.php` - Page /update pour mode local
- `app/Http/Controllers/CalendarController.php` - Calendrier + API booking

**Modèles:**
- `app/Models/Property.php` - Modèle Property avec relations
- `app/Models/Unit.php` - Modèle Unit avec relations
- `app/Models/Booking.php` - Modèle Booking avec métadonnées et accessors

**Services:**
- `app/Services/BookingMetadataParser.php` - Parse DESCRIPTION iCal + couleurs status
- `app/Services/BookingSyncIcal.php` - Synchronise iCal avec parsing métadonnées

**Commandes:**
- `app/Console/Commands/SyncIcalCommand.php` - Commande `php artisan bokit:sync`

**Vues:**
- `resources/views/update.blade.php` - Interface update pour mode local

### Fichiers modifiés

- `routes/web.php` - Ajout routes /update et CalendarController
- `bootstrap/app.php` - Enregistrement middlewares (ApplyMigrations, AutoSync)

## 🎯 Comment ça marche

### Mode Production (automatique)
1. Middleware `ApplyMigrations` détecte migration pendante
2. **Backup automatique** de la DB dans `storage/backups/`
3. **Exécution automatique** de la migration
4. Notification silencieuse stockée dans Options
5. Utilisateur ne voit rien, tout est transparent ✨

### Mode Local (avec confirmation)
1. Middleware détecte migration
2. Redirection vers `/update`
3. Liste des migrations à exécuter
4. Clic "Run Update Now"
5. Migration s'exécute
6. Retour au calendar

## 🧪 Test (pour Oli)

```bash
# 1. Ouvre l'app dans le navigateur
# Tu seras redirigé vers /update (mode local)

# 2. Clique sur "Run Update Now"
# La colonne "status" sera ajoutée à la table bookings

# 3. Recharge n'importe quelle page
# AutoSync va se déclencher et parser les métadonnées

# 4. Ouvre le calendrier
# Les réservations ont maintenant des couleurs selon leur status

# 5. Clique sur une réservation
# Le popup affiche toutes les métadonnées
```

## 📊 Nouveauté: Métadonnées Beds24

Les champs DESCRIPTION des iCal Beds24 sont maintenant parsés:

```
STATUS: New         → status = "new" (couleur bleue)
GUESTS: 2/2/0       → raw_data.guests = 2 total guests, 2 adults, 0 children
PHONE: 556699447300 → raw_data.phone (needs sanitization to international format)
EMAIL: user@mail.com → raw_data.email
COUNTRY2: US        → raw_data.country
OTA: Airbnb ABCDE12345     → raw_data.api_source + api_ref
```

## 🎨 Couleurs par status

A revoir, cf dev/ICAL-DECODE.md

## 🔧 Configuration

Dans `.env`:
```bash
APP_ENV=local    # → Page /update avec confirmation
APP_ENV=production # → Migration automatique silencieuse
```

## 📁 Structure backups

```
storage/backups/
  backup_before_migration_2025-12-11_140523.sqlite
  backup_before_migration_2025-12-11_153042.sqlite
  ...
```

**Rétention**: Garde les 10 derniers backups automatiquement

## 🚀 Ready to test!

Tout est en place. Plus besoin de commandes terminal, tout se fait via le web ! 🎯
