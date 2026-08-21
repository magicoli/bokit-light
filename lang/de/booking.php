<?php

return [
    'label' => 'Buchung',
    'plural_label' => 'Buchungen',
    'field' => [
        'status' => 'Status',
        'guest_name' => 'Name',
        'check_in' => 'Anreise',
        'check_out' => 'Abreise',
        'guests' => 'Personen',
        'adults' => 'Erwachsene',
        'children' => 'Kinder',
        'source_name' => 'Quelle',
        'price' => 'Preis',
        'commission' => 'Provision',
        'notes' => 'Notizen',
        'unit_name' => 'Einheit',
        'ota_url' => 'OTA-URL',
        'ota_link' => 'OTA-Link',
        'actions' => 'Aktionen',
        'metadata' => 'Rohdaten',
        'metadata.status' => 'Status',
        'metadata.api_source' => 'Quelle',
        'metadata.email' => 'E-Mail',
        'metadata.guests' => 'Telefon',
        'is_manual' => 'Manuelle Buchung',
        'group_id' => 'Gruppen-ID',
        'unit_id' => 'Einheiten-ID',
        'property_id' => 'Objekt-ID',
        'uid' => 'UID',
        'country' => 'Land',
        'comments' => 'Kommentare',
        'paid' => 'Bezahlt',
        'balance' => 'Restbetrag',
        'deposit' => 'Anzahlung',
        'created_at' => 'Erstellt',
        'updated_at' => 'Aktualisiert',
        'deleted_at' => 'Gelöscht',
        'group' => 'Gruppe',
    ],
    'status' => [

        // Canonical statuses (see Booking::STATUSES)
        'confirmed' => 'Bestätigt',
        'option' => 'Option',
        'quote' => 'Angebot',
        'blocked' => 'Blockiert',
        'cancelled' => 'Storniert',
        'vanished' => 'Verschwunden',
        'deleted' => 'Gelöscht',
        'undefined' => 'Unbestimmt',
        'unavailable' => 'Nicht verfügbar',
        'cancelled_by_owner' => 'Vom Eigentümer storniert',
        'cancelled_by_guest' => 'Vom Gast storniert',
    ],

    'tag.new' => 'Neu',

    'title' => [
        // One-line booking title (widgets, mail subjects)
        'units' => ':count Einheit|:count Einheiten',
        'dates' => 'von :from bis :to',
    ],

    'widget' => [
        // Dashboard widgets
        'ongoing' => 'Laufende Aufenthalte',
        'upcoming' => 'Bevorstehende Aufenthalte',
        'options' => 'Ausstehende Optionen',
        'quotes' => 'Ausstehende Angebote',
        'see_all' => 'Alle anzeigen',
    ],

    'filter' => [
        // Display filters
        'show_cancelled' => 'Stornierte anzeigen',
        'show_quotes' => 'Angebote anzeigen',
        'effective_only' => 'Nur tatsächliche Buchungen',
        'period' => 'Zeitraum',
    ],

    'period' => [
        'ongoing' => 'Laufend',
        'upcoming' => 'Bevorstehend',
        'current' => 'Laufend oder bevorstehend',
        'past' => 'Vergangen',
    ],

    'section' => [
        // Form sections
        'guests' => 'Gäste',
        'pricing' => 'Preisgestaltung',
        'metadata' => 'Metadaten',
        'sources' => 'Quellen',
        'group' => 'Gruppenbuchung',
        'invoice' => 'Rechnungsdetails',
    ],

    'source' => [
        // Sources
        'origin' => 'Ursprung',
        'beds24' => 'In Beds24 öffnen',
        'external_id' => 'Externe ID',
        'last_seen' => 'Zuletzt gesehen',
        'pending_origin' => 'Ursprung (nicht verbunden)',
        'not_connected' => 'nicht verbunden',
    ],

    'group' => [
        'none' => 'Keine Gruppe',
        'total' => 'Gesamt',
    ],

    'invoice' => [
        // Invoice detail
        'description' => 'Beschreibung',
        'qty' => 'Anz.',
        'unit_price' => 'Einzelpreis',
        'amount' => 'Betrag',
        'total' => 'Gesamt',
        'payment' => 'Zahlung',
    ],

    'push.failed' => 'Übertragung an Kanäle fehlgeschlagen',
    'protected_origin' => 'Die Ursprungs-OTA verwaltet diese Buchung — Datum und Preis sind schreibgeschützt.',
    'guests_auto_calculated' => 'Wird automatisch berechnet, wenn leer',
];
