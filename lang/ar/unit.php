<?php

return [
    'label' => 'الوحدة',
    'plural_label' => 'الوحدات',
    'empty_state' => 'لا توجد وحدات',
    'field' => [
        'name' => 'الاسم',
        'property_id' => 'العقار',
        'unit_type' => 'النوع',
        'bedrooms' => 'غرف النوم',
        'max_guests' => 'الحد الأقصى للضيوف',
        'is_active' => 'نشطة',
        'description' => 'الوصف',
        'details' => 'التفاصيل',
        'slug' => 'المعرّف',
        'actions' => 'الإجراءات',
        'source_type' => 'النوع',
        'source_config' => 'التكوين',
        'source_label' => 'التسمية',
        'source_beds24_room_id' => 'معرّف الغرفة في Beds24',
        'source_hbook_unit' => 'وحدة HBook',
        'source_multipass_unit' => 'وحدة Multipass',
        'source_ical_url' => 'رابط iCal',
        'source_enabled' => 'مفعّلة',
    ],

    // Data sources section
    'section' => [
        'sources' => 'مصادر البيانات',
        'sources_description' => 'مصادر الحجز لهذه الوحدة، مرتبة من الأعلى إلى الأدنى أولوية. اسحب لإعادة الترتيب.',
    ],
    'action' => [
        'add_source' => 'إضافة مصدر',
    ],
    'source_type' => [
        'beds24' => 'Beds24',
        'hbook' => 'HBook (WordPress)',
        'multipass' => 'Multipass (WordPress)',
        'ical' => 'iCal',
    ],
];
