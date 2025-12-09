# Laravel Scheduler Setup

Laravel gère automatiquement les tâches planifiées via son scheduler.

## Tâches configurées

- **Sync calendars** : Toutes les heures (`bokit:sync`)
  - Tourne en background (non-bloquant)
  - Évite les chevauchements (`withoutOverlapping`)
  - Un seul serveur (`onOneServer`)

## Installation Cron

**Un seul cron est nécessaire** pour toutes les tâches Laravel :

```bash
* * * * * cd /path/to/bokit && php artisan schedule:run >> /dev/null 2>&1
```

Ce cron s'exécute **chaque minute** et Laravel décide quelles tâches lancer.

### Exemple pour bokit.click

```bash
# Éditer le crontab
crontab -e

# Ajouter cette ligne
* * * * * cd /home/magic/domains/bokit.click/bokit && php artisan schedule:run >> /dev/null 2>&1
```

## Vérification

```bash
# Voir les tâches planifiées
php artisan schedule:list

# Tester manuellement
php artisan schedule:run

# Voir les logs
tail -f storage/logs/laravel.log
```

## Modifier les fréquences

Éditer `routes/console.php` :

```php
// Toutes les heures (actuel)
Schedule::command('bokit:sync')->hourly();

// Toutes les 30 minutes
Schedule::command('bokit:sync')->everyThirtyMinutes();

// Tous les jours à 2h du matin
Schedule::command('bokit:sync')->dailyAt('02:00');

// Toutes les 6 heures
Schedule::command('bokit:sync')->everySixHours();
```

## Multi-tenant (futur)

Chaque tenant aura son propre scheduler avec sa propre fréquence configurable.
