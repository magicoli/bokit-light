<?php

return [
    'field.beds24_api_key' => 'Clé API Beds24',
    'field.beds24_api_key_help' => 'Clé API au niveau du compte. Laisser vide pour utiliser la clé globale.',
    'field.beds24_prop_key' => 'Clé de propriété Beds24',
    'field.beds24_prop_key_help' => 'propKey telle que définie dans les paramètres de la propriété Beds24.',
    'field.beds24_invite_code' => "Code d'invitation API v2",
    'field.beds24_invite_code_help' => 'Nécessaire pour pousser des réservations vers Beds24 (synchro bidirectionnelle). Générez un code avec le scope « bookings » via le bouton ci-contre et collez-le ici : il sera échangé immédiatement contre un jeton permanent.',
    'action.generate_invite_code' => 'Générer un code',
    'field.beds24_v2_connected' => 'API v2 connectée ✓',
    'field.beds24_v2_not_connected' => 'API v2 non connectée',
    'notification.invite_code_exchanged' => "Code échangé — l'API v2 est connectée. Enregistrez la propriété pour conserver le jeton.",
    'notification.invite_code_failed' => "Échec de l'échange du code d'invitation",
    'section.beds24' => 'Intégration Beds24',
    'section.beds24_description' => 'Paramètres du channel manager pour cette propriété.',
];
