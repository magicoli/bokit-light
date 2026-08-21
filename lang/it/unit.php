<?php

return [
    'label' => 'Unità',
    'plural_label' => 'Unità',
    'field' => [
        'name' => 'Nome',
        'property_id' => 'Struttura',
        'unit_type' => 'Tipo',
        'bedrooms' => 'Camere da letto',
        'max_guests' => 'Ospiti max',
        'is_active' => 'Attiva',
        'description' => 'Descrizione',
        'details' => 'Dettagli',
        'slug' => 'Slug',
        'actions' => 'Azioni',
        'source_type' => 'Tipo',
        'source_config' => 'Configurazione',
        'source_label' => 'Etichetta',
        'source_beds24_room_id' => 'ID camera Beds24',
        'source_hbook_unit' => 'Unità HBook',
        'source_multipass_unit' => 'Unità Multipass',
        'source_ical_url' => 'URL iCal',
        'source_enabled' => 'Abilitata',
    ],

    // Data sources section
    'section' => [
        'sources' => 'Fonti dati',
        'sources_description' => 'Fonti di prenotazione per questa unità, ordinate dalla priorità più alta alla più bassa. Trascina per riordinare.',
    ],
    'action' => [
        'add_source' => 'Aggiungi fonte',
    ],
    'source_type' => [
        'beds24' => 'Beds24',
        'hbook' => 'HBook (WordPress)',
        'multipass' => 'Multipass (WordPress)',
        'ical' => 'iCal',
    ],
];
