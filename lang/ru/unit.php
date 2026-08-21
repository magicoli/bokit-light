<?php

return [
    'label' => 'Юнит',
    'plural_label' => 'Юниты',
    'empty_state' => 'Нет юнитов',
    'field' => [
        'name' => 'Название',
        'property_id' => 'Объект',
        'unit_type' => 'Тип',
        'bedrooms' => 'Спальни',
        'max_guests' => 'Макс. гостей',
        'is_active' => 'Активен',
        'description' => 'Описание',
        'details' => 'Детали',
        'slug' => 'Слаг',
        'actions' => 'Действия',
        'source_type' => 'Тип',
        'source_config' => 'Конфигурация',
        'source_label' => 'Метка',
        'source_beds24_room_id' => 'ID комнаты Beds24',
        'source_hbook_unit' => 'Юнит HBook',
        'source_multipass_unit' => 'Юнит Multipass',
        'source_ical_url' => 'URL iCal',
        'source_enabled' => 'Включено',
    ],

    // Data sources section
    'section' => [
        'sources' => 'Источники данных',
        'sources_description' => 'Источники бронирований для этого юнита, отсортированные от наивысшего к наименьшему приоритету. Перетащите для изменения порядка.',
    ],
    'action' => [
        'add_source' => 'Добавить источник',
    ],
    'source_type' => [
        'beds24' => 'Beds24',
        'hbook' => 'HBook (WordPress)',
        'multipass' => 'Multipass (WordPress)',
        'ical' => 'iCal',
    ],
];
