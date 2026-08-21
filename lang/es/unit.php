<?php

return [
    'label' => 'Unidad',
    'plural_label' => 'Unidades',
    'empty_state' => 'No hay unidades',
    'field' => [
        'name' => 'Nombre',
        'property_id' => 'Propiedad',
        'unit_type' => 'Tipo',
        'bedrooms' => 'Dormitorios',
        'max_guests' => 'Huéspedes máx.',
        'is_active' => 'Activa',
        'description' => 'Descripción',
        'details' => 'Detalles',
        'slug' => 'Slug',
        'actions' => 'Acciones',
        'source_type' => 'Tipo',
        'source_config' => 'Configuración',
        'source_label' => 'Etiqueta',
        'source_beds24_room_id' => 'ID de habitación Beds24',
        'source_hbook_unit' => 'Unidad HBook',
        'source_multipass_unit' => 'Unidad Multipass',
        'source_ical_url' => 'URL iCal',
        'source_enabled' => 'Habilitada',
    ],

    // Data sources section
    'section' => [
        'sources' => 'Fuentes de datos',
        'sources_description' => 'Fuentes de reservas para esta unidad, ordenadas de mayor a menor prioridad. Arrastra para reordenar.',
    ],
    'action' => [
        'add_source' => 'Añadir fuente',
    ],
    'source_type' => [
        'beds24' => 'Beds24',
        'hbook' => 'HBook (WordPress)',
        'multipass' => 'Multipass (WordPress)',
        'ical' => 'iCal',
    ],
];
