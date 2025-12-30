# Admin Menu

1. On crée l'architecture admin:

    - 1.1. On crée les routes, les menus, les droits d'accès et des pages placeholder
    - 1.2. On crée les listes et crud basiques avec DataList et Form tels qu'on les a (donc application bête et méchante des propriétés de chaque modèle, sauf Rate qui a déjà des layouts définis dans son modèle)
    - 1.3. On crée une page de settings (options) pour chaque objet. On utilise déjà les options pour les liens iCal des units, on en aura aussi besoin pour stocker les clefs API. Les options ont des ordres de fallback selon les modèles : quel que soit le paramètre:
        - User->options('section.option') va utiliser comme fallback Property->options('section.option'), 
        - Property->options('section.option') va lui-même utiliser comme fallback Owner->options('section.option')
        - Owner->options('section.option') va lui-même utiliser comme fallback option('section.option') comme fallback
        - (et jamais config('section.option'), config() est réservé aux paramètres statiques de l'app, non user-configurable, donc incompatibles avec la notion d'option)
2. On regarde la doc l'API de Beds24 (celle dont j'ai personnellement besoin), celle de Lodgify (que j'ai déjà utiliisée pour le même genre de sync, et éventuellement d'autres API courantes si on les trouve (j'adorerais AirBNB et Booking.com mais pas sûr qu'elle soit disponible sans un contrat dévelopeur/partenaire), pour établir notre propre structure en évitant les problèmes prévisibles (pour les problèmes imprévisibles, on sera stoïque quand ça arrivera)
3. On ajuste le modèle Booking avec la structure qu'on aura établie, et on fait les pages "définitives" (lol) de liste et add/edit (spécificité de Booking: en général on ne delete pas, on annule et on garde la trace). Il y aura aussi une vue "Devis" à prévoir (mêmes infos de base, mais adaptée pour l'envoi au client)
4. iCal sync out: à ce stade on peut créer des réservations en interne, la synchro out devient prioritaire: donc export iCal pour qu'on puisse déjà garder le calendrier beds24 à jour (il accepte aussi iCal comme source et ça devrait être rapide à implémenter)
5. On attaque enfin les bases de la synchro API beds24 
    - 5.1. réglages par unit/property: le plugin doit déclarer sa propre section de réglages à la structure d'admin, pour qu'ils soient disponibles dans Admin > Property > settings 
    - 5.2. Donc, non à "Config : API keys dans plugin, pas dans app": ni dans le plugin, ni dans l'app, exclusivement par le framework d'options. 
    - 5.3. import (le plus important) 4. export (moins important, les dispos seront déjà synchros
6. On se fait payer. Enfin moi. Enfin non, pas moi, mes clients. Bref on met en place un lien de paiement pour les réservations (paypal et stripe ont des outils relativement rapides à mettre en place) avec la mise à jour du statut de la réservation

## Tree reference
```
admin-menu
|-- dashboard       // placeholder, I have great plans for this, but out of scope for now
|-- settings        // general site settings (options), admin only
|-- Bookings        // per owner, can only see own property bookings
|   |-- list
|   |-- add
|   |-- calendar    // Not yet, first focus on default sub-menus
|   `-- settings
|-- Properties      // per owner
|   |-- list
|   |-- add
|   |-- categories  // (not yet implemented, ignore for now)
|   `-- settings
|-- Units           // Might become a sub-section of properties eventually
|   |-- list
|   |-- add
|   |-- categories  // (not yet implemented, ignore for now)
|   `-- settings
|-- Rates           // per owner
|   |-- list
|   |-- add
|   `-- settings
|-- Coupons         // Might become a sub-section of rates eventually
|   |-- list
|   |-- add
|   `-- settings    // probably not needed but let's be generic for now
`-- Users           // admin only
    |-- add
    |-- list
    `-- settings
```
