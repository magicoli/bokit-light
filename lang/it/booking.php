<?php

return [
    'label' => 'Prenotazione',
    'plural_label' => 'Prenotazioni',
    'empty_state' => 'Nessuna prenotazione',
    'field' => [
        'status' => 'Stato',
        'guest_name' => 'Nome',
        'check_in' => 'Check-in',
        'check_out' => 'Check-out',
        'guests' => 'Persone',
        'adults' => 'Adulti',
        'children' => 'Bambini',
        'source_name' => 'Fonte',
        'price' => 'Prezzo',
        'commission' => 'Commissione',
        'notes' => 'Note',
        'unit_name' => 'Unità',
        'ota_url' => 'URL OTA',
        'ota_link' => 'Link OTA',
        'actions' => 'Azioni',
        'metadata' => 'Dati grezzi',
        'metadata.status' => 'Stato',
        'metadata.api_source' => 'Fonte',
        'metadata.email' => 'E-mail',
        'metadata.guests' => 'Telefono',
        'is_manual' => 'Prenotazione manuale',
        'group_id' => 'ID gruppo',
        'unit_id' => 'ID unità',
        'property_id' => 'ID struttura',
        'uid' => 'UID',
        'country' => 'Paese',
        'comments' => 'Commenti',
        'paid' => 'Pagato',
        'balance' => 'Saldo',
        'deposit' => 'Deposito',
        'created_at' => 'Creata',
        'updated_at' => 'Aggiornata',
        'deleted_at' => 'Eliminata',
        'group' => 'Gruppo',
    ],
    'status' => [

        // Canonical statuses (see Booking::STATUSES)
        'confirmed' => 'Confermata',
        'option' => 'Opzione',
        'quote' => 'Preventivo',
        'blocked' => 'Bloccata',
        'cancelled' => 'Cancellata',
        'vanished' => 'Scomparsa',
        'deleted' => 'Eliminata',
        'undefined' => 'Non definita',
        'unavailable' => 'Non disponibile',
        'cancelled_by_owner' => 'Cancellata dal proprietario',
        'cancelled_by_guest' => "Cancellata dall'ospite",
    ],

    'tag.new' => 'Nuova',

    'title' => [
        // One-line booking title (widgets, mail subjects)
        'units' => ':count unità|:count unità',
        'dates' => 'dal :from al :to',
    ],

    'widget' => [
        // Dashboard widgets
        'ongoing' => 'Soggiorni in corso',
        'upcoming' => 'Soggiorni in arrivo',
        'options' => 'Opzioni in sospeso',
        'quotes' => 'Preventivi in sospeso',
        'see_all' => 'Vedi tutto',
    ],

    'filter' => [
        // Display filters
        'show_cancelled' => 'Mostra cancellate',
        'show_quotes' => 'Mostra preventivi',
        'effective_only' => 'Solo prenotazioni effettive',
        'period' => 'Periodo',
    ],

    'period' => [
        'ongoing' => 'In corso',
        'upcoming' => 'In arrivo',
        'current' => 'In corso o in arrivo',
        'past' => 'Passate',
    ],

    'section' => [
        // Form sections
        'guests' => 'Ospiti',
        'pricing' => 'Prezzi',
        'metadata' => 'Metadati',
        'sources' => 'Fonti',
        'group' => 'Prenotazione di gruppo',
        'invoice' => 'Dettaglio fattura',
    ],

    'source' => [
        // Sources
        'origin' => 'Origine',
        'beds24' => 'Apri in Beds24',
        'external_id' => 'ID esterno',
        'last_seen' => 'Ultimo aggiornamento',
        'pending_origin' => 'Origine (non connessa)',
        'not_connected' => 'non connessa',
    ],

    'group' => [
        'none' => 'Nessun gruppo',
        'total' => 'Totale',
    ],

    'invoice' => [
        // Invoice detail
        'description' => 'Descrizione',
        'qty' => 'Qtà',
        'unit_price' => 'Prezzo unitario',
        'amount' => 'Importo',
        'total' => 'Totale',
        'payment' => 'Pagamento',
    ],

    'push.failed' => 'Invio ai canali non riuscito',
    'protected_origin' => "L'OTA di origine gestisce questa prenotazione — date e prezzo sono di sola lettura.",
    'guests_auto_calculated' => 'Calcolato automaticamente se vuoto',
];
