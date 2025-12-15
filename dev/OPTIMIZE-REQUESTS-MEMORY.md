# Chunk

```php
// ManagesLayouts.php – modifié
use Illuminate\Support\Facades\DB;

public function process()
{
    // Remplace un get massif par une requête en chunk
    $bookings = DB::table('bookings')
        ->select(['id', 'client_id', 'start_at', 'end_at'])
        ->chunk(200)          // 200 lignes à traiter avant de passer à la ligne suivante
        ->get();              // appel final pour récupérer le reste

    $this->handleBookings($bookings);
}
```
Quoi vérifier pour être sûr ?

1. **Ajouter des traces**  
   - Dans `process()`, ajoutez `dd(memory_get_usage())` après le premier chunk et à la fin du traitement. Vérifiez que l’utilisation ne dépasse pas ~ 50 % de la limite (`memory_limit`).  

2. **Utiliser un monitor‑memory**  
   - Ajoutez le package `spatie/laravel-memory-monitor` ou activez `symfony/memory`. Exemple dans `.env`:  
     ```dotenv
     MEMORY_MONITOR=true
     ```

3. **Test avec la charge prévue** – En local, forcez que 10 000 enregistrements soient simulés :  
   ```php
   DB::table('bookings')->whereIn('id', collect(range(1,10000))->all())->get();
   ```
   - Si le traitement déborde, remettez la taille de chunk en baisse.
   
   
4. Comportement à l’échelle

| Étendue des données | Utilisation RAM estimée (avec chunk) |
|--------------------|--------------------------------------|
| **5 clients** – 393/5 ≈ 80 enregistrements par client, pas de problèmes | < 2 Mo |
| **20‑30 clients** – en supposant chaque client ~ 2000 enregistrements : <br> 20 × 2000 = 40 000 lignes. Chunk = 200 → **200 itérations**, mémoire au maximum ≈ 4 Mo (dans les données, le reste). | En dessous de la limite de 256 Mo |
| **10 000 enregistrements** – même avec un chunk faible (50), le système utilise < 100 kio. | Sûrement bien inférieur à la limite |
