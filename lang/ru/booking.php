<?php

return [
    'label' => 'Бронирование',
    'plural_label' => 'Бронирования',
    'empty_state' => 'Нет бронирований',
    'field' => [
        'status' => 'Статус',
        'guest_name' => 'Имя',
        'check_in' => 'Заезд',
        'check_out' => 'Выезд',
        'guests' => 'Гости',
        'adults' => 'Взрослые',
        'children' => 'Дети',
        'source_name' => 'Источник',
        'price' => 'Цена',
        'commission' => 'Комиссия',
        'notes' => 'Заметки',
        'unit_name' => 'Юнит',
        'ota_url' => 'URL OTA',
        'ota_link' => 'Ссылка OTA',
        'actions' => 'Действия',
        'metadata' => 'Необработанные данные',
        'metadata.status' => 'Статус',
        'metadata.api_source' => 'Источник',
        'metadata.email' => 'Эл. почта',
        'metadata.guests' => 'Телефон',
        'is_manual' => 'Ручное бронирование',
        'group_id' => 'ID группы',
        'unit_id' => 'ID юнита',
        'property_id' => 'ID объекта',
        'uid' => 'UID',
        'country' => 'Страна',
        'comments' => 'Комментарии',
        'paid' => 'Оплачено',
        'balance' => 'Остаток',
        'deposit' => 'Депозит',
        'created_at' => 'Создано',
        'updated_at' => 'Обновлено',
        'deleted_at' => 'Удалено',
        'group' => 'Группа',
    ],
    'status' => [

        // Canonical statuses (see Booking::STATUSES)
        'confirmed' => 'Подтверждено',
        'option' => 'Опция',
        'quote' => 'Смета',
        'blocked' => 'Заблокировано',
        'cancelled' => 'Отменено',
        'vanished' => 'Исчезло',
        'deleted' => 'Удалено',
        'undefined' => 'Не определено',
        'unavailable' => 'Недоступно',
        'cancelled_by_owner' => 'Отменено владельцем',
        'cancelled_by_guest' => 'Отменено гостем',
    ],

    'tag.new' => 'Новое',

    'title' => [
        // One-line booking title (widgets, mail subjects)
        'units' => ':count юнит|:count юнитов',
        'dates' => 'с :from по :to',
    ],

    'widget' => [
        // Dashboard widgets
        'ongoing' => 'Текущие проживания',
        'upcoming' => 'Предстоящие проживания',
        'options' => 'Ожидающие опции',
        'quotes' => 'Ожидающие сметы',
        'see_all' => 'Смотреть все',
    ],

    'filter' => [
        // Display filters
        'show_cancelled' => 'Показать отменённые',
        'show_quotes' => 'Показать сметы',
        'effective_only' => 'Только действующие бронирования',
        'period' => 'Период',
    ],

    'period' => [
        'ongoing' => 'Текущие',
        'upcoming' => 'Предстоящие',
        'current' => 'Текущие или предстоящие',
        'past' => 'Прошедшие',
    ],

    'section' => [
        // Form sections
        'guests' => 'Гости',
        'pricing' => 'Ценообразование',
        'metadata' => 'Метаданные',
        'sources' => 'Источники',
        'group' => 'Групповое бронирование',
        'invoice' => 'Детали счёта',
    ],

    'source' => [
        // Sources
        'origin' => 'Источник',
        'beds24' => 'Открыть в Beds24',
        'external_id' => 'Внешний ID',
        'last_seen' => 'Последнее обновление',
        'pending_origin' => 'Источник (не подключён)',
        'not_connected' => 'не подключён',
    ],

    'group' => [
        'none' => 'Без группы',
        'total' => 'Итого',
    ],

    'invoice' => [
        // Invoice detail
        'description' => 'Описание',
        'qty' => 'Кол-во',
        'unit_price' => 'Цена за единицу',
        'amount' => 'Сумма',
        'total' => 'Итого',
        'payment' => 'Оплата',
    ],

    'push.failed' => 'Не удалось отправить в каналы',
    'protected_origin' => 'Это бронирование управляется исходной OTA — даты и цена доступны только для чтения.',
    'guests_auto_calculated' => 'Рассчитывается автоматически, если пусто',
];
