<?php

return [
    'title' => 'Installation',
    'step_of' => 'Étape :current sur :total',
    'continue' => 'Continuer',
    'processing' => 'Traitement…',
    'error' => 'Une erreur est survenue',
    'network_error' => 'Erreur réseau :',

    'steps' => [
        'welcome' => 'Bienvenue',
        'setup' => 'Configurer les propriétés et les unités',
        'complete' => 'Installation terminée',
    ],

    'welcome' => [
        'intro' => 'Cet assistant vous guide dans la configuration initiale de votre calendrier.',
        'installed_title' => 'Ce qui sera installé :',
        'installed' => [
            'Les tables de la base de données',
            'Le système de cache',
            'La gestion des sessions',
            'La synchronisation des calendriers',
        ],
        'configured_title' => 'Ce qui sera configuré :',
        'configured' => [
            "Le mode d'authentification",
            'Le premier administrateur',
            'Les premières propriétés et unités de location',
        ],
        'duration' => "L'installation prend moins de cinq minutes",
    ],

    'setup' => [
        'hint' => 'Créez vos propriétés (organisations ou sociétés), leurs unités de location (appartements, villas, gîtes), et les sources de synchronisation de chaque unité.',
        'add_property' => 'Ajouter une propriété',
        'add_unit' => 'Ajouter une unité',
        'add_source' => 'Ajouter une source',
        'property_name' => 'Nom de la propriété',
        'property_name_placeholder' => 'Ma société',
        'property_slug_placeholder' => 'ma-societe',
        'unit_name' => "Nom de l'unité",
        'unit_name_placeholder' => 'Mon hébergement',
        'unit_slug_placeholder' => 'mon-hebergement',
        'slug' => 'Identifiant',
        'website' => 'Site web',
        'optional' => '(facultatif)',
        'source_url_placeholder' => 'https://calendrier.example.com/mon-hebergement.ics',
    ],

    'complete' => [
        'heading' => 'Installation terminée !',
        'lead' => 'Votre gestionnaire de calendriers :app est prêt à servir.',
        'configured_title' => 'Ce qui a été configuré :',
        'database' => 'La base de données est en place',
        'auth' => "Le mode d'authentification est configuré",
        'admin' => 'Le compte administrateur est créé',
        'properties' => 'Les propriétés et les unités sont configurées',
        'sources' => 'Les sources de synchronisation sont ajoutées',
        'next_title' => 'Et maintenant',
        'next' => 'Ouvrez votre calendrier pour commencer à gérer vos locations.',
        'go' => 'Ouvrir le calendrier',
        'finalizing' => 'Finalisation…',
        'failed' => 'Une erreur est survenue. Rechargez la page et réessayez.',
    ],
];
