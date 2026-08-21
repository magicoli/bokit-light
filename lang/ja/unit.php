<?php

return [
    'label' => 'ユニット',
    'plural_label' => 'ユニット',
    'field' => [
        'name' => '名前',
        'property_id' => '物件',
        'unit_type' => 'タイプ',
        'bedrooms' => '寝室数',
        'max_guests' => '最大宿泊人数',
        'is_active' => '有効',
        'description' => '説明',
        'details' => '詳細',
        'slug' => 'スラッグ',
        'actions' => '操作',
        'source_type' => 'タイプ',
        'source_config' => '設定',
        'source_label' => 'ラベル',
        'source_beds24_room_id' => 'Beds24 ルームID',
        'source_hbook_unit' => 'HBook ユニット',
        'source_multipass_unit' => 'Multipass ユニット',
        'source_ical_url' => 'iCal URL',
        'source_enabled' => '有効',
    ],

    // Data sources section
    'section' => [
        'sources' => 'データソース',
        'sources_description' => 'このユニットの予約ソースです。優先度の高い順に並んでいます。ドラッグして並べ替えできます。',
    ],
    'action' => [
        'add_source' => 'ソースを追加',
    ],
    'source_type' => [
        'beds24' => 'Beds24',
        'hbook' => 'HBook（WordPress）',
        'multipass' => 'Multipass（WordPress）',
        'ical' => 'iCal',
    ],
];
