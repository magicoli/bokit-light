<?php

return [
    'title' => 'Installation',
    'step_of' => 'Schritt :current von :total',
    'continue' => 'Weiter',
    'processing' => 'Wird verarbeitet...',
    'error' => 'Ein Fehler ist aufgetreten',
    'network_error' => 'Netzwerkfehler:',

    'steps' => [
        'welcome' => 'Willkommen',
        'setup' => 'Objekte & Einheiten konfigurieren',
        'complete' => 'Installation abgeschlossen',
    ],

    'welcome' => [
        'intro' => 'Dieser Assistent hilft Ihnen bei der Erstkonfiguration Ihres Kalendersystems.',
        'installed_title' => 'Was installiert wird:',
        'installed' => [
            'Datenbanktabellen',
            'Cache-System',
            'Sitzungsverwaltung',
            'Kalender-Sync-System',
        ],
        'configured_title' => 'Was konfiguriert wird:',
        'configured' => [
            'Authentifizierungssystem',
            'Initialer Admin-Benutzer',
            'Anfängliche Objekte und Mieteinheiten',
        ],
        'duration' => 'Die Installation sollte weniger als 5 Minuten dauern',
    ],

    'setup' => [
        'hint' => 'Erstellen Sie Ihre Objekte (Organisationen oder Unternehmen), deren Mieteinheiten (Wohnungen, Villen, Ferienhäuser) und konfigurieren Sie die Kalendersynchronisationsquellen für jede Einheit.',
        'add_property' => 'Weiteres Objekt hinzufügen',
        'add_unit' => 'Einheit hinzufügen',
        'add_source' => 'Quelle hinzufügen',
        'property_name' => 'Objektname',
        'property_name_placeholder' => 'Mein Unternehmen',
        'property_slug_placeholder' => 'mein-unternehmen',
        'unit_name' => 'Name der Einheit',
        'unit_name_placeholder' => 'Meine Unterkunft',
        'unit_slug_placeholder' => 'meine-unterkunft',
        'slug' => 'Slug',
        'website' => 'Webseite',
        'optional' => '(optional)',
        'source_url_placeholder' => 'https://calendar.example.com/meine-unterkunft.ics',
    ],

    'complete' => [
        'heading' => 'Installation abgeschlossen!',
        'lead' => 'Ihr :app-Kalenderverwaltungssystem ist einsatzbereit.',
        'configured_title' => 'Was konfiguriert wurde:',
        'database' => 'Datenbankstruktur erstellt',
        'auth' => 'Authentifizierungsmethode konfiguriert',
        'admin' => 'Administratorkonto erstellt',
        'properties' => 'Objekte und Mieteinheiten konfiguriert',
        'sources' => 'Kalendersynchronisationsquellen hinzugefügt',
        'next_title' => 'Nächste Schritte',
        'next' => 'Klicken Sie auf die Schaltfläche unten, um auf Ihren Kalender zuzugreifen und mit der Verwaltung Ihres Mietkalenders zu beginnen.',
        'go' => 'Zum Kalender',
        'finalizing' => 'Wird abgeschlossen...',
        'failed' => 'Ein Fehler ist aufgetreten. Bitte laden Sie die Seite neu und versuchen Sie es erneut.',
    ],
];
