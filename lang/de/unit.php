<?php

return [
    'label' => 'Einheit',
    'plural_label' => 'Einheiten',
    'empty_state' => 'Keine Einheiten',
    'field' => [
        'name' => 'Name',
        'property_id' => 'Objekt',
        'unit_type' => 'Typ',
        'bedrooms' => 'Schlafzimmer',
        'max_guests' => 'Max. Gäste',
        'is_active' => 'Aktiv',
        'description' => 'Beschreibung',
        'details' => 'Details',
        'slug' => 'Slug',
        'actions' => 'Aktionen',
        'source_type' => 'Typ',
        'source_config' => 'Konfiguration',
        'source_label' => 'Bezeichnung',
        'source_beds24_room_id' => 'Beds24-Zimmer-ID',
        'source_hbook_unit' => 'HBook-Einheit',
        'source_multipass_unit' => 'Multipass-Einheit',
        'source_ical_url' => 'iCal-URL',
        'source_enabled' => 'Aktiviert',
    ],

    // Data sources section
    'section' => [
        'sources' => 'Datenquellen',
        'sources_description' => 'Buchungsquellen für diese Einheit, sortiert von höchster zu niedrigster Priorität. Zum Umsortieren ziehen.',
    ],
    'action' => [
        'add_source' => 'Quelle hinzufügen',
    ],
    'source_type' => [
        'beds24' => 'Beds24',
        'hbook' => 'HBook (WordPress)',
        'multipass' => 'Multipass (WordPress)',
        'ical' => 'iCal',
    ],
];
