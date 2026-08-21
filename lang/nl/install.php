<?php

return [
    'title' => 'Installatie',
    'step_of' => 'Stap :current van :total',
    'continue' => 'Doorgaan',
    'processing' => 'Bezig met verwerken...',
    'error' => 'Er is een fout opgetreden',
    'network_error' => 'Netwerkfout:',

    'steps' => [
        'welcome' => 'Welkom',
        'setup' => 'Accommodaties & eenheden configureren',
        'complete' => 'Installatie voltooid',
    ],

    'welcome' => [
        'intro' => 'Deze wizard helpt u bij het maken van de initiële configuratie van uw kalendersysteem.',
        'installed_title' => 'Wat wordt geïnstalleerd:',
        'installed' => [
            'Databasetabellen',
            'Cachesysteem',
            'Sessiebeheer',
            'Kalendersynchronisatiesysteem',
        ],
        'configured_title' => 'Wat wordt geconfigureerd:',
        'configured' => [
            'Authenticatiesysteem',
            'Initiële beheerdersgebruiker',
            'Initiële accommodaties en verhuureenheden',
        ],
        'duration' => 'De installatie duurt naar verwachting minder dan 5 minuten',
    ],

    'setup' => [
        'hint' => 'Maak uw accommodaties (organisaties of bedrijven), hun verhuureenheden (appartementen, villa\'s, cottages) aan en configureer kalendersynchronisatiebronnen voor elke eenheid.',
        'add_property' => 'Nog een accommodatie toevoegen',
        'add_unit' => 'Eenheid toevoegen',
        'add_source' => 'Bron toevoegen',
        'property_name' => 'Naam van de accommodatie',
        'property_name_placeholder' => 'Mijn bedrijf',
        'property_slug_placeholder' => 'mijn-bedrijf',
        'unit_name' => 'Naam van de eenheid',
        'unit_name_placeholder' => 'Mijn accommodatie',
        'unit_slug_placeholder' => 'mijn-accommodatie',
        'slug' => 'Slug',
        'website' => 'Website',
        'optional' => '(optioneel)',
        'source_url_placeholder' => 'https://calendar.example.com/mijn-accommodatie.ics',
    ],

    'complete' => [
        'heading' => 'Installatie voltooid!',
        'lead' => 'Uw :app-kalenderbeheersysteem is klaar voor gebruik.',
        'configured_title' => 'Wat is geconfigureerd:',
        'database' => 'Databasestructuur aangemaakt',
        'auth' => 'Authenticatiemethode geconfigureerd',
        'admin' => 'Beheerdersaccount aangemaakt',
        'properties' => 'Accommodaties en verhuureenheden geconfigureerd',
        'sources' => 'Kalendersynchronisatiebronnen toegevoegd',
        'next_title' => 'Volgende stappen',
        'next' => 'Klik op de knop hieronder om toegang te krijgen tot uw kalender en uw verhuurkalender te beheren.',
        'go' => 'Naar kalender',
        'finalizing' => 'Wordt afgerond...',
        'failed' => 'Er is een fout opgetreden. Vernieuw de pagina en probeer het opnieuw.',
    ],
];
