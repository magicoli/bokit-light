**Attention**, il y a un bon paquet de changements et d'ajouts à faire, il faut y aller par étapes sinon on va se retrouver de nouveau avec un bug dont on ne trouve pas la source.

## Metadata structure

- je ne suis pas convaincu par l'utilisation d'une colonne "raw" dans la table bookings
- ces données vont évoluer, ça va finir par alourdir considérablement la table bookings, qui doit rester légère pour maintenir de bonnnes performances
- le concept des metadata sera aussi utile pour d'autres types d'objets (users, properties,...). 
- il faudrait une table metadata pour stocker les données des metadata, organisée par type d'objet et par clé, avec une colonne key et une colonne value
- ça permettra plus de flexibilité plus tard pour les recherches, filtres, tris, etc.
- la table  booking ne doit contenir que les données essentielles pour les listes et les calendriers.

## Nomenclature

- Bokit est un PMS (Property Management System)
- Beds24.com, Lodgify sont des Channel Manager
- Booking.com, Airbnb ou Abritel/VRBO sont des OTAs (Online Travel Agencies) ou des Booking Engines mais OTA est plus commun
- iCal Sync peut être utilisé pour synchroniser les données avec des PMS ou des OTAs
- Chaque PMS et chaque OTA ont leurs propres standards d'échange de données, que ce soit par API ou par iCal sync, ils peuvent utiliser des conventions différentes pour représenter les données.
- Il faut donc prendre en compte des régles générales et des règles spécifiques.
- A ce stade, Bokit ne gère que l'iCal Sync, avec Beds24, Airbnb et le plugin WordPress HBook.
- A court terme, seule l'API de Beds24 va être ajoutée.
- Booking.com et Airbnb n'ont pas d'API publique pour l'instant. C'est encore à vérifier pour VRBO (Abritel/Expedia).

## Bugs
- dans syncEventsToDatabase, les tests de statut sont fait sur les chaînes formattées pour l'affichage, ça va poser un problème à la traduction. Les statuts sauvés dans la db et utilisés pour tous les tests doivent être des slugs, ce n'est qu'à l'affichage qu'on les change en strings humaines.
- la vérification si un événement a changé ne peut pas se faire sur l'état actuel de la db, car certaines données sont transformées avant d'être sauvegardées ou peuvent être modifiées. Il faut conserver un checksum des données sources à vérifier, et le comparer avec le checksum des nouvelles données sources. On ne calcule qu'une seule fois la valeur a comparer et on ne stocke qu'une seule simple chaîne pour les vérifications futures.
- en corrigeant l'interprétation des status, les dates bloquées (unavailable) n'apparaissent plus. Elles doivent apparaître sur le calendrier.
- l'ajout d'une rangée pour la property est utile uniquement si elle contient plusieurs units. Si il n'y a qu'une seule unité, il faut afficher une seule rangée.
- REGRESSION: la vue par défaut affiche maintenant plus, ou moins qu'un mois, selon la largeur du viewport, elle doit afficher un mois entier, responsive pour entrer au minimum dans un viewport de 1280px.

## iCal Decode

- "status" doit être une colonne dans bookings, pas une metadonnée
- les statuts à prendre en compte sont
    - cancelled -> pas affiché dans le calendrier (option show deleted dans le futur), pas bloquant
    - vanished -> pas affiché dans le calendrier (option show deleted dans le futur), pas bloquant (statut spécial pour la gestion interne)
    - inquiry -> affiché en gris, pas bloquant
    - request
    - new
    - confirmed
    - blocked (je crois que c'est "black" dans beds24) -> bloquant, affiché en black 50% opacity
    - unavailable ("SUMMARY=Unavailable" dans beds24, "SUMMARY:Airbnb (Not available)" dans airbnb) -> pas encore affiché en black 50% opacity
    - undefined -> statut par défaut (on n'utilise pas "Confirmed" comme défaut), bloquant
- quand on gérera les réservations, request, new, confirmed et undefined seront bloquants
- quand on gérera les availabilities, blocked, unavailable seront bloquants
- cancelled et inquiry ne sont pas bloquants

- arrival time peut être ajouté après check-in dans la rangée check-in check-out nights
- guests, adults et children peuvent être en une seule ligne: `Guests: <guests> (<adults> adults, <children>`children)`
- phone et email seront multiples (possibilité de plusieurs valeurs), on ne le gère pas pour le moment mais ils doivent être déjà être stockés comme une array
- les sources peuvent être en une seule ligne: `Source: <ical source> <api source> <api ref>`

- changements sur l'ics beds24:
    - les 3 lignes guests, adults, child ont été fusionnées en une: `GUESTS: [NUMPEOPLE1]/[NUMADULT1]/[NUMCHILD1]`
    - APISOURCE devient OTA: `OTA: [APISOURCETEXT] [APIREF]`
    - la ligne EMAIL a été ajoutée: `EMAIL: [GUESTEMAIL]`

- l'URL de la réservation est parfois mentionnée dans l'iCal:
    - beds24: dans le champ standard iCal `URL;VALUE=URI:https://beds24.com/control.php?bookid=75919643` mais le lien ne fonctionne pas, il vaut le tranformer en `https://beds24.com/control2.php?ajax=bookedit&id=75919643`
    - airbnb: dans DESCRIPTION. Attention: avec curl, le lien peut être coupé par un passage à la ligne et des espaces, il faut le réunir. Ca ne devrait pas être un problème si on utilise une librairie ou des fonctions dédiées iCal.
    ```
    DESCRIPTION:Reservation URL: https://www.airbnb.com/hosting/reservations/de
     tails/HMESA2CKAX\nPhone Number (Last 4 Digits): 1315
    ```

## Gestion des disponibilités

- On va importer les Unavailable, mais on ne les affiche pas avant d'avoir implémenté la gestion des availabilities
- A l'import, "SUMMARY=Unavailable" n'est qu'un des filtres possibles, il faut prévoir un ruleset qui va évoluer avec le temps (quand j'implémente Airbnb, des API, etc.)
. Pour l'affichage, on a donc un filtre différent de celui de l'import, basé sur les statuts: cancelled, inquiry, blocked ou unavailable sont ignorés à l'affichage, jusqu'à ce que la gestion des availabilities soit implémentée.

## Gestion des vanished/deleted

- Si une résa locale n'est plus dans son calendrier source, **et que ce n'est pas une ancienne réservation**, elle doit être marquée comme "vanished" et ignorée à l'affichage. On avait commencé à implémenter ça autrement (en la marquant "DELETED" pour le débug en attendant de l'effacer réellement) mais ça n'a plus l'air actif non plus.
- Comme on est en développement, on va les laisser dans le calendrier pour pouvoir les voir et tester. A terme, on fera peut-être un truc plus subtil comme les afficher pendant une période limitée, puis les marquer comme "deleted" si elles sont toujours absentes au bout de ce délai (ex. 2 semaines).
- Dans tous les cas, on ne delete jamais les résas, on les marque comme "deleted" et elles n'apparaissent plus dans les listes de réservations. On a besoin de garder l'historique pour pouvoir vérifier en cas de problème.

## Sync/Autosync

- les logs montraient le détail "x new bookings, y updated bookings, z deleted bookings", ce n'est plus le cas. 
- il faut l'afficher mais il faut mieux compter les updates: un update, c'est une réservation qui existait déjà et qui a été modifiée, pas simplement re-fetchée, 
- il donc faut vérifier si il y a eu des changements dans les champs qu'on importe (un checksum avant-après devrait faire l'affaire)
- on aura donc au final "x bookings: n new, u updated, d deleted, v vanished" (où x est le total des réservations reçues, d celles qui sont marquées comme "deleted" ou "cancelled" par la source et v celles qui existaient mais ne sont plus dans le calendrier source)
- il reste quelques lignes de debug dans le log qui peuvent être supprimées:
    - "[AutoSync] Sync triggered..."
    - "[AutoSync] Sync job will run..."
    - [SyncJob] Synced .... {"success":true,"count":...}
    - [IcalParser] ...

Ce serait bien de garder un historique des modifications dans une table séparée, succint (date, source/user, booking id, champs modifiés), pour permettre au moins de surveiller l'origine des modifications apportées aux réservations.
