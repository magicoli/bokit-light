<?php

return [
    'label' => '予約',
    'plural_label' => '予約',
    'empty_state' => '予約はありません',
    'field' => [
        'status' => 'ステータス',
        'guest_name' => '名前',
        'check_in' => 'チェックイン',
        'check_out' => 'チェックアウト',
        'guests' => '人数',
        'adults' => '大人',
        'children' => '子供',
        'source_name' => 'ソース',
        'price' => '料金',
        'commission' => '手数料',
        'notes' => 'メモ',
        'unit_name' => 'ユニット',
        'ota_url' => 'OTA URL',
        'ota_link' => 'OTAリンク',
        'actions' => '操作',
        'metadata' => '生データ',
        'metadata.status' => 'ステータス',
        'metadata.api_source' => 'ソース',
        'metadata.email' => 'メールアドレス',
        'metadata.guests' => '電話番号',
        'is_manual' => '手動予約',
        'group_id' => 'グループID',
        'unit_id' => 'ユニットID',
        'property_id' => '物件ID',
        'uid' => 'UID',
        'country' => '国',
        'comments' => 'コメント',
        'paid' => '支払済み',
        'balance' => '残高',
        'deposit' => 'デポジット',
        'created_at' => '作成日',
        'updated_at' => '更新日',
        'deleted_at' => '削除日',
        'group' => 'グループ',
    ],
    'status' => [

        // Canonical statuses (see Booking::STATUSES)
        'confirmed' => '確定',
        'option' => 'オプション',
        'quote' => '見積もり',
        'blocked' => 'ブロック',
        'cancelled' => 'キャンセル',
        'vanished' => '消失',
        'deleted' => '削除済み',
        'undefined' => '未定義',
        'unavailable' => '利用不可',
        'cancelled_by_owner' => 'オーナーによりキャンセル',
        'cancelled_by_guest' => 'ゲストによりキャンセル',
    ],

    'tag.new' => '新規',

    'title' => [
        // One-line booking title (widgets, mail subjects)
        'units' => ':count ユニット|:count ユニット',
        'dates' => ':from から :to まで',
    ],

    'widget' => [
        // Dashboard widgets
        'ongoing' => '滞在中',
        'upcoming' => '今後の滞在',
        'options' => '保留中のオプション',
        'quotes' => '保留中の見積もり',
        'see_all' => 'すべて表示',
    ],

    'filter' => [
        // Display filters
        'show_cancelled' => 'キャンセル済みを表示',
        'show_quotes' => '見積もりを表示',
        'effective_only' => '有効な予約のみ',
        'period' => '期間',
    ],

    'period' => [
        'ongoing' => '滞在中',
        'upcoming' => '今後',
        'current' => '滞在中または今後',
        'past' => '過去',
    ],

    'section' => [
        // Form sections
        'guests' => 'ゲスト',
        'pricing' => '料金設定',
        'metadata' => 'メタデータ',
        'sources' => 'ソース',
        'group' => 'グループ予約',
        'invoice' => '請求書の詳細',
    ],

    'source' => [
        // Sources
        'origin' => '発生元',
        'beds24' => 'Beds24で開く',
        'external_id' => '外部ID',
        'last_seen' => '最終確認',
        'pending_origin' => '発生元（未接続）',
        'not_connected' => '未接続',
    ],

    'group' => [
        'none' => 'グループなし',
        'total' => '合計',
    ],

    'invoice' => [
        // Invoice detail
        'description' => '説明',
        'qty' => '数量',
        'unit_price' => '単価',
        'amount' => '金額',
        'total' => '合計',
        'payment' => '支払い',
    ],

    'push.failed' => 'チャネルへの送信に失敗しました',
    'protected_origin' => 'この予約は発生元のOTAによって管理されています — 日付と料金は読み取り専用です。',
    'guests_auto_calculated' => '空欄の場合は自動計算されます',
];
