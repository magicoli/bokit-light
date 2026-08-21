<?php

return [
    'title' => 'Installazione',
    'step_of' => 'Passaggio :current di :total',
    'continue' => 'Continua',
    'processing' => 'Elaborazione...',
    'error' => 'Si è verificato un errore',
    'network_error' => 'Errore di rete:',

    'steps' => [
        'welcome' => 'Benvenuto',
        'setup' => 'Configura strutture e unità',
        'complete' => 'Installazione completata',
    ],

    'welcome' => [
        'intro' => 'Questa procedura guidata ti aiuterà a creare la configurazione iniziale del tuo sistema di calendario.',
        'installed_title' => 'Cosa verrà installato:',
        'installed' => [
            'Tabelle del database',
            'Sistema di cache',
            'Gestione delle sessioni',
            'Sistema di sincronizzazione calendario',
        ],
        'configured_title' => 'Cosa verrà configurato:',
        'configured' => [
            'Sistema di autenticazione',
            'Utente amministratore iniziale',
            'Strutture e unità in affitto iniziali',
        ],
        'duration' => "L'installazione dovrebbe richiedere meno di 5 minuti",
    ],

    'setup' => [
        'hint' => 'Crea le tue strutture (organizzazioni o aziende), le loro unità in affitto (appartamenti, ville, cottage) e configura le fonti di sincronizzazione del calendario per ogni unità.',
        'add_property' => 'Aggiungi un\'altra struttura',
        'add_unit' => 'Aggiungi unità',
        'add_source' => 'Aggiungi fonte',
        'property_name' => 'Nome della struttura',
        'property_name_placeholder' => 'La mia azienda',
        'property_slug_placeholder' => 'la-mia-azienda',
        'unit_name' => 'Nome unità',
        'unit_name_placeholder' => 'Il mio alloggio',
        'unit_slug_placeholder' => 'il-mio-alloggio',
        'slug' => 'Slug',
        'website' => 'Sito web',
        'optional' => '(facoltativo)',
        'source_url_placeholder' => 'https://calendar.example.com/il-mio-alloggio.ics',
    ],

    'complete' => [
        'heading' => 'Installazione completata!',
        'lead' => 'Il tuo sistema di gestione calendario :app è pronto per l\'uso.',
        'configured_title' => 'Cosa è stato configurato:',
        'database' => 'Struttura del database creata',
        'auth' => 'Metodo di autenticazione configurato',
        'admin' => 'Account amministratore creato',
        'properties' => 'Strutture e unità in affitto configurate',
        'sources' => 'Fonti di sincronizzazione calendario aggiunte',
        'next_title' => 'Prossimi passi',
        'next' => 'Fai clic sul pulsante qui sotto per accedere al tuo calendario e iniziare a gestire il tuo calendario degli affitti.',
        'go' => 'Vai al calendario',
        'finalizing' => 'Completamento in corso...',
        'failed' => 'Si è verificato un errore. Aggiorna la pagina e riprova.',
    ],
];
