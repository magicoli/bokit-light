<?php

return [
    'label' => 'الحجز',
    'plural_label' => 'الحجوزات',
    'empty_state' => 'لا توجد حجوزات',
    'field' => [
        'status' => 'الحالة',
        'guest_name' => 'الاسم',
        'check_in' => 'تسجيل الوصول',
        'check_out' => 'تسجيل المغادرة',
        'guests' => 'الأشخاص',
        'adults' => 'البالغون',
        'children' => 'الأطفال',
        'source_name' => 'المصدر',
        'price' => 'السعر',
        'commission' => 'العمولة',
        'notes' => 'ملاحظات',
        'unit_name' => 'الوحدة',
        'ota_url' => 'رابط وكيل السفر الإلكتروني',
        'ota_link' => 'رابط وكيل السفر الإلكتروني',
        'actions' => 'الإجراءات',
        'metadata' => 'البيانات الخام',
        'metadata.status' => 'الحالة',
        'metadata.api_source' => 'المصدر',
        'metadata.email' => 'البريد الإلكتروني',
        'metadata.guests' => 'الهاتف',
        'is_manual' => 'حجز يدوي',
        'group_id' => 'معرّف المجموعة',
        'unit_id' => 'معرّف الوحدة',
        'property_id' => 'معرّف العقار',
        'uid' => 'المعرّف الفريد',
        'country' => 'البلد',
        'comments' => 'التعليقات',
        'paid' => 'المدفوع',
        'balance' => 'الرصيد',
        'deposit' => 'العربون',
        'created_at' => 'تاريخ الإنشاء',
        'updated_at' => 'تاريخ التحديث',
        'deleted_at' => 'تاريخ الحذف',
        'group' => 'المجموعة',
    ],
    'status' => [

        // Canonical statuses (see Booking::STATUSES)
        'confirmed' => 'مؤكد',
        'option' => 'خيار',
        'quote' => 'عرض سعر',
        'blocked' => 'محظور',
        'cancelled' => 'ملغى',
        'vanished' => 'مفقود',
        'deleted' => 'محذوف',
        'undefined' => 'غير محدد',
        'unavailable' => 'غير متاح',
        'cancelled_by_owner' => 'ألغاه المالك',
        'cancelled_by_guest' => 'ألغاه الضيف',
    ],

    'tag.new' => 'جديد',

    'title' => [
        // One-line booking title (widgets, mail subjects)
        'units' => ':count وحدة|:count وحدات',
        'dates' => 'من :from إلى :to',
    ],

    'widget' => [
        // Dashboard widgets
        'ongoing' => 'الإقامات الجارية',
        'upcoming' => 'الإقامات القادمة',
        'options' => 'الخيارات المعلقة',
        'quotes' => 'عروض الأسعار المعلقة',
        'see_all' => 'عرض الكل',
    ],

    'filter' => [
        // Display filters
        'show_cancelled' => 'إظهار الملغاة',
        'show_quotes' => 'إظهار عروض الأسعار',
        'effective_only' => 'الحجوزات الفعلية فقط',
        'period' => 'الفترة',
    ],

    'period' => [
        'ongoing' => 'جارية',
        'upcoming' => 'قادمة',
        'current' => 'جارية أو قادمة',
        'past' => 'سابقة',
    ],

    'section' => [
        // Form sections
        'guests' => 'الضيوف',
        'pricing' => 'التسعير',
        'metadata' => 'البيانات الوصفية',
        'sources' => 'المصادر',
        'group' => 'حجز جماعي',
        'invoice' => 'تفاصيل الفاتورة',
    ],

    'source' => [
        // Sources
        'origin' => 'المصدر الأصلي',
        'beds24' => 'فتح في Beds24',
        'external_id' => 'المعرّف الخارجي',
        'last_seen' => 'آخر ظهور',
        'pending_origin' => 'المصدر الأصلي (غير متصل)',
        'not_connected' => 'غير متصل',
    ],

    'group' => [
        'none' => 'بدون مجموعة',
        'total' => 'الإجمالي',
    ],

    'invoice' => [
        // Invoice detail
        'description' => 'الوصف',
        'qty' => 'الكمية',
        'unit_price' => 'سعر الوحدة',
        'amount' => 'المبلغ',
        'total' => 'الإجمالي',
        'payment' => 'الدفع',
    ],

    'push.failed' => 'فشل الإرسال إلى القنوات',
    'protected_origin' => 'يدير وكيل السفر الأصلي هذا الحجز — التواريخ والسعر للقراءة فقط.',
    'guests_auto_calculated' => 'يُحسب تلقائيًا إذا كان فارغًا',
];
