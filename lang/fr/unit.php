<?php

return [
    'label' => 'Unité',
    'plural_label' => 'Unités',
    'field' => [
        'name' => 'Nom',
        'property_id' => 'Propriété',
        'unit_type' => 'Type',
        'bedrooms' => 'Chambres',
        'max_guests' => 'Max personnes',
        'is_active' => 'Actif',
        'description' => 'Description',
        'details' => 'Détails',
        'slug' => 'Identifiant',
        'actions' => 'Actions',
        'source_type' => 'Type',
        'source_config' => 'Configuration',
        'source_label' => 'Libellé',
        'source_beds24_room_id' => 'ID chambre Beds24',
        'source_hbook_unit' => 'Unité HBook',
        'source_multipass_unit' => 'Unité Multipass',
        'source_ical_url' => 'URL iCal',
        'source_enabled' => 'Activé',
    ],
    // Section sources de données
    'section' => [
        'sources' => 'Sources de données',
        'sources_description' => 'Sources de réservation pour cette unité, par ordre de priorité décroissante. Glisser-déposer pour réordonner.',
    ],
    'source_type' => [
        'beds24' => 'Beds24',
        'hbook' => 'HBook (WordPress)',
        'multipass' => 'Multipass (WordPress)',
        'ical' => 'iCal',
    ],
    'action' => [
        'add_source' => 'Ajouter une source',
    ],
];
