<?php

return [
    'title' => 'インストール',
    'step_of' => 'ステップ :current / :total',
    'continue' => '続ける',
    'processing' => '処理中...',
    'error' => 'エラーが発生しました',
    'network_error' => 'ネットワークエラー：',

    'steps' => [
        'welcome' => 'ようこそ',
        'setup' => '物件とユニットの設定',
        'complete' => 'インストール完了',
    ],

    'welcome' => [
        'intro' => 'このウィザードは、カレンダーシステムの初期設定を作成するお手伝いをします。',
        'installed_title' => 'インストールされる内容：',
        'installed' => [
            'データベーステーブル',
            'キャッシュシステム',
            'セッション管理',
            'カレンダー同期システム',
        ],
        'configured_title' => '設定される内容：',
        'configured' => [
            '認証システム',
            '初期管理者ユーザー',
            '初期物件と賃貸ユニット',
        ],
        'duration' => 'インストールは5分未満で完了する見込みです',
    ],

    'setup' => [
        'hint' => '物件（組織や会社）と、その賃貸ユニット（アパート、ヴィラ、コテージ）を作成し、各ユニットのカレンダー同期ソースを設定してください。',
        'add_property' => '別の物件を追加',
        'add_unit' => 'ユニットを追加',
        'add_source' => 'ソースを追加',
        'property_name' => '物件名',
        'property_name_placeholder' => '自分の会社',
        'property_slug_placeholder' => 'my-company',
        'unit_name' => 'ユニット名',
        'unit_name_placeholder' => '自分の宿泊施設',
        'unit_slug_placeholder' => 'my-accommodation',
        'slug' => 'スラッグ',
        'website' => 'ウェブサイト',
        'optional' => '（任意）',
        'source_url_placeholder' => 'https://calendar.example.com/my-accommodation.ics',
    ],

    'complete' => [
        'heading' => 'インストール完了！',
        'lead' => ':app カレンダー管理システムの準備が整いました。',
        'configured_title' => '設定された内容：',
        'database' => 'データベース構造を作成しました',
        'auth' => '認証方法を設定しました',
        'admin' => '管理者アカウントを作成しました',
        'properties' => '物件と賃貸ユニットを設定しました',
        'sources' => 'カレンダー同期ソースを追加しました',
        'next_title' => '次のステップ',
        'next' => '下のボタンをクリックしてカレンダーにアクセスし、賃貸カレンダーの管理を開始してください。',
        'go' => 'カレンダーへ移動',
        'finalizing' => '仕上げ中...',
        'failed' => 'エラーが発生しました。ページを更新して再度お試しください。',
    ],
];
