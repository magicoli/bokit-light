<?php

return [
    'label' => 'Boeking',
    'plural_label' => 'Boekingen',
    'empty_state' => 'Geen boekingen',
    'field' => [
        'status' => 'Status',
        'guest_name' => 'Naam',
        'check_in' => 'Inchecken',
        'check_out' => 'Uitchecken',
        'guests' => 'Personen',
        'adults' => 'Volwassenen',
        'children' => 'Kinderen',
        'source_name' => 'Bron',
        'price' => 'Prijs',
        'commission' => 'Commissie',
        'notes' => 'Notities',
        'unit_name' => 'Eenheid',
        'ota_url' => 'OTA-URL',
        'ota_link' => 'OTA-link',
        'actions' => 'Acties',
        'metadata' => 'Ruwe gegevens',
        'metadata.status' => 'Status',
        'metadata.api_source' => 'Bron',
        'metadata.email' => 'E-mail',
        'metadata.guests' => 'Telefoon',
        'is_manual' => 'Handmatige boeking',
        'group_id' => 'Groeps-ID',
        'unit_id' => 'Eenheids-ID',
        'property_id' => 'Accommodatie-ID',
        'uid' => 'UID',
        'country' => 'Land',
        'comments' => 'Opmerkingen',
        'paid' => 'Betaald',
        'balance' => 'Saldo',
        'deposit' => 'Aanbetaling',
        'created_at' => 'Aangemaakt',
        'updated_at' => 'Bijgewerkt',
        'deleted_at' => 'Verwijderd',
        'group' => 'Groep',
    ],
    'status' => [

        // Canonical statuses (see Booking::STATUSES)
        'confirmed' => 'Bevestigd',
        'option' => 'Optie',
        'quote' => 'Offerte',
        'blocked' => 'Geblokkeerd',
        'cancelled' => 'Geannuleerd',
        'vanished' => 'Verdwenen',
        'deleted' => 'Verwijderd',
        'undefined' => 'Onbepaald',
        'unavailable' => 'Niet beschikbaar',
        'cancelled_by_owner' => 'Geannuleerd door eigenaar',
        'cancelled_by_guest' => 'Geannuleerd door gast',
    ],

    'tag.new' => 'Nieuw',

    'title' => [
        // One-line booking title (widgets, mail subjects)
        'units' => ':count eenheid|:count eenheden',
        'dates' => 'van :from tot :to',
    ],

    'widget' => [
        // Dashboard widgets
        'ongoing' => 'Lopende verblijven',
        'upcoming' => 'Aankomende verblijven',
        'options' => 'Openstaande opties',
        'quotes' => 'Openstaande offertes',
        'see_all' => 'Alles bekijken',
    ],

    'filter' => [
        // Display filters
        'show_cancelled' => 'Geannuleerde tonen',
        'show_quotes' => 'Offertes tonen',
        'effective_only' => 'Alleen daadwerkelijke boekingen',
        'period' => 'Periode',
    ],

    'period' => [
        'ongoing' => 'Lopend',
        'upcoming' => 'Aankomend',
        'current' => 'Lopend of aankomend',
        'past' => 'Verleden',
    ],

    'section' => [
        // Form sections
        'guests' => 'Gasten',
        'pricing' => 'Prijzen',
        'metadata' => 'Metadata',
        'sources' => 'Bronnen',
        'group' => 'Groepsboeking',
        'invoice' => 'Factuurdetails',
    ],

    'source' => [
        // Sources
        'origin' => 'Herkomst',
        'beds24' => 'Openen in Beds24',
        'external_id' => 'Extern ID',
        'last_seen' => 'Laatst gezien',
        'pending_origin' => 'Herkomst (niet verbonden)',
        'not_connected' => 'niet verbonden',
    ],

    'group' => [
        'none' => 'Geen groep',
        'total' => 'Totaal',
    ],

    'invoice' => [
        // Invoice detail
        'description' => 'Beschrijving',
        'qty' => 'Aantal',
        'unit_price' => 'Prijs per stuk',
        'amount' => 'Bedrag',
        'total' => 'Totaal',
        'payment' => 'Betaling',
    ],

    'push.failed' => 'Verzenden naar kanalen mislukt',
    'protected_origin' => 'De OTA van herkomst beheert deze boeking — data en prijs zijn alleen-lezen.',
    'guests_auto_calculated' => 'Automatisch berekend indien leeg',
];
