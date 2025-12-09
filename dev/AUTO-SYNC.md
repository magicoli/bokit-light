# Auto-Sync System (WordPress-style)

Bokit utilise un système de pseudo-cron à la WordPress : **pas besoin de cron système**.

## Fonctionnement

À chaque requête web, le middleware `AutoSync` vérifie si une synchronisation est nécessaire :

1. Vérifie le timestamp de dernière sync (stocké en cache)
2. Si > 1 heure → lance `php artisan bokit:sync` en arrière-plan
3. La page se charge normalement (non-bloquant)

## Avantages

✅ **Pas de cron système** - Une seule source de vérité  
✅ **Auto-nettoyage** - Supprime l'app = supprime la sync  
✅ **Simple** - Fonctionne partout sans config serveur  
✅ **Non-bloquant** - La sync tourne en background  

## Configuration

Le système est activé automatiquement via `bootstrap/app.php`.

Pour **désactiver** (dev/debug), commenter dans `bootstrap/app.php` :
```php
// $middleware->append(\App\Http\Middleware\AutoSync::class);
```

## Sync manuelle

Pour lancer une sync immédiatement (dev/debug) :

```bash
php artisan bokit:sync
```

Ceci fonctionne toujours et **n'interfère pas** avec l'auto-sync.

## Fréquence

Par défaut : **toutes les heures** (3600 secondes)

Pour modifier, éditer `.env` :
```env
# Exemples :
SYNC_INTERVAL=1800   # 30 minutes
SYNC_INTERVAL=3600   # 1 heure (défaut)
SYNC_INTERVAL=7200   # 2 heures
SYNC_INTERVAL=21600  # 6 heures
```

## Multi-tenant (futur)

Chaque tenant aura sa propre fréquence configurable en DB.

## Cache

Le timestamp est stocké dans le cache Laravel :
- Clé : `last_auto_sync`
- TTL : 2 heures (pour éviter la perte)

Pour forcer une sync immédiate :
```bash
php artisan cache:forget last_auto_sync
```

## Production

**Aucune configuration serveur nécessaire** - ça marche out-of-the-box !

Tant qu'il y a du trafic sur le site, la sync se fait automatiquement.

Si le site a très peu de trafic et que vous voulez garantir une sync même sans visiteurs, vous pouvez optionnellement ajouter un cron qui visite la page :
```bash
0 * * * * curl -s https://bokit.click > /dev/null
```

Mais ce n'est généralement **pas nécessaire**.
