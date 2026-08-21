<?php

return [
    'label' => 'Unidade',
    'plural_label' => 'Unidades',
    'field' => [
        'name' => 'Nome',
        'property_id' => 'Propriedade',
        'unit_type' => 'Tipo',
        'bedrooms' => 'Quartos',
        'max_guests' => 'Hóspedes máx.',
        'is_active' => 'Ativa',
        'description' => 'Descrição',
        'details' => 'Detalhes',
        'slug' => 'Slug',
        'actions' => 'Ações',
        'source_type' => 'Tipo',
        'source_config' => 'Configuração',
        'source_label' => 'Etiqueta',
        'source_beds24_room_id' => 'ID do quarto Beds24',
        'source_hbook_unit' => 'Unidade HBook',
        'source_multipass_unit' => 'Unidade Multipass',
        'source_ical_url' => 'URL iCal',
        'source_enabled' => 'Ativada',
    ],

    // Data sources section
    'section' => [
        'sources' => 'Fontes de dados',
        'sources_description' => 'Fontes de reservas para esta unidade, ordenadas da maior para a menor prioridade. Arraste para reordenar.',
    ],
    'action' => [
        'add_source' => 'Adicionar fonte',
    ],
    'source_type' => [
        'beds24' => 'Beds24',
        'hbook' => 'HBook (WordPress)',
        'multipass' => 'Multipass (WordPress)',
        'ical' => 'iCal',
    ],
];
