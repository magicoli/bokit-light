<?php

return [
    'label' => 'Eenheid',
    'plural_label' => 'Eenheden',
    'field' => [
        'name' => 'Naam',
        'property_id' => 'Accommodatie',
        'unit_type' => 'Type',
        'bedrooms' => 'Slaapkamers',
        'max_guests' => 'Max. gasten',
        'is_active' => 'Actief',
        'description' => 'Beschrijving',
        'details' => 'Details',
        'slug' => 'Slug',
        'actions' => 'Acties',
        'source_type' => 'Type',
        'source_config' => 'Configuratie',
        'source_label' => 'Label',
        'source_beds24_room_id' => 'Beds24-kamer-ID',
        'source_hbook_unit' => 'HBook-eenheid',
        'source_multipass_unit' => 'Multipass-eenheid',
        'source_ical_url' => 'iCal-URL',
        'source_enabled' => 'Ingeschakeld',
    ],

    // Data sources section
    'section' => [
        'sources' => 'Gegevensbronnen',
        'sources_description' => 'Boekingsbronnen voor deze eenheid, gesorteerd van hoogste naar laagste prioriteit. Sleep om opnieuw te ordenen.',
    ],
    'action' => [
        'add_source' => 'Bron toevoegen',
    ],
    'source_type' => [
        'beds24' => 'Beds24',
        'hbook' => 'HBook (WordPress)',
        'multipass' => 'Multipass (WordPress)',
        'ical' => 'iCal',
    ],
];
