<?php

return [
    'label' => 'Unit',
    'plural_label' => 'Units',
    'empty_state' => 'No units',
    'field' => [
        'name' => 'Name',
        'property_id' => 'Property',
        'unit_type' => 'Type',
        'bedrooms' => 'Bedrooms',
        'max_guests' => 'Max Guests',
        'is_active' => 'Active',
        'description' => 'Description',
        'details' => 'Details',
        'slug' => 'Slug',
        'actions' => 'Actions',
        'source_type' => 'Type',
        'source_config' => 'Configuration',
        'source_label' => 'Label',
        'source_beds24_room_id' => 'Beds24 Room ID',
        'source_hbook_unit' => 'HBook Unit',
        'source_multipass_unit' => 'Multipass Unit',
        'source_ical_url' => 'iCal URL',
        'source_enabled' => 'Enabled',
    ],

    // Data sources section
    'section' => [
        'sources' => 'Data Sources',
        'sources_description' => 'Booking sources for this unit, ordered from highest to lowest priority. Drag to reorder.',
    ],
    'action' => [
        'add_source' => 'Add source',
    ],
    'source_type' => [
        'beds24' => 'Beds24',
        'hbook' => 'HBook (WordPress)',
        'multipass' => 'Multipass (WordPress)',
        'ical' => 'iCal',
    ],
];
