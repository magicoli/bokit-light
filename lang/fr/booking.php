<?php

return [
    'label' => 'Réservation',
    'plural_label' => 'Réservations',
    'empty_state' => 'Aucune réservation',

    'field' => [
        'status' => 'Statut',
        'guest_name' => 'Nom',
        'check_in' => 'Arrivée',
        'check_out' => 'Départ',
        'guests' => 'Personnes',
        'adults' => 'Adultes',
        'children' => 'Enfants',
        'source_name' => 'Source',
        'price' => 'Prix',
        'commission' => 'Commission',
        'notes' => 'Notes',
        'unit_name' => 'Unité',
        'ota_url' => 'URL OTA',
        'ota_link' => 'Lien OTA',
        'actions' => 'Actions',
        'metadata' => 'Données brutes',
        'metadata.status' => 'Statut',
        'metadata.api_source' => 'Source',
        'metadata.email' => 'Email',
        'metadata.guests' => 'Téléphone',
        'is_manual' => 'Réservation manuelle',
        'group_id' => 'ID de groupe',
        'unit_id' => "ID d'unité",
        'property_id' => 'ID de propriété',
        'uid' => 'UID',
        'country' => 'Pays',
        'comments' => 'Commentaires',
        'paid' => 'Payé',
        'balance' => 'Solde',
        'deposit' => 'Acompte',
        'created_at' => 'Créée le',
        'updated_at' => 'Mise à jour',
        'deleted_at' => 'Supprimée le',
        'group' => 'Groupe',
    ],
    'status' => [
        // Canonical statuses (see Booking::STATUSES)
        'confirmed' => 'Confirmée',
        'option' => 'Option',
        'quote' => 'Devis',
        'blocked' => 'Bloqué',
        'cancelled' => 'Annulée',
        'vanished' => 'Disparue',
        'deleted' => 'Supprimée',
        'undefined' => 'Indéfini',
        'unavailable' => 'Indisponible',
        'cancelled_by_owner' => 'Annulée par le propriétaire',
        'cancelled_by_guest' => 'Annulée par le client',
    ],

    'tag.new' => 'Nouvelle',

    'title' => [
        // One-line booking title (widgets, mail subjects)
        'units' => ':count unité|:count unités',
        'dates' => 'du :from au :to',
    ],

    'widget' => [
        // Dashboard widgets
        'ongoing' => 'Séjours en cours',
        'upcoming' => 'Séjours à venir',
        'options' => 'Options en attente',
        'quotes' => 'Offres en attente',
        'see_all' => 'Tout voir',
    ],

    'filter' => [
        // Display filters
        'show_cancelled' => 'Afficher les annulées',
        'show_quotes' => 'Afficher les devis',
        'effective_only' => 'Réservations effectives uniquement',
        'period' => 'Période',
    ],

    'period' => [
        'ongoing' => 'En cours',
        'upcoming' => 'À venir',
        'current' => 'En cours ou à venir',
        'past' => 'Passées',
    ],

    'section' => [
        // Form sections
        'guests' => 'Personnes',
        'pricing' => 'Tarification',
        'metadata' => 'Métadonnées',
        'group' => 'Réservation groupée',
        'sources' => 'Sources',
        'invoice' => 'Détail de la facture',
    ],

    'source' => [
        // Sources
        'origin' => 'Origine',
        'beds24' => 'Ouvrir dans Beds24',
        'external_id' => 'ID externe',
        'last_seen' => 'Vu pour la dernière fois',
        'pending_origin' => 'Origine (non connectée)',
        'not_connected' => 'non connectée',
    ],

    'group' => [
        'none' => 'Sans groupe',
        'total' => 'Total',
    ],

    'invoice' => [
        // Invoice detail
        'description' => 'Description',
        'qty' => 'Qté',
        'unit_price' => 'Prix unitaire',
        'amount' => 'Montant',
        'total' => 'Total',
        'payment' => 'Paiement',
    ],

    'push.failed' => 'Échec de la transmission aux canaux',
    'protected_origin' => 'L\'OTA d\'origine gère cette réservation — dates et prix en lecture seule.',
    'guests_auto_calculated' => 'Calculé automatiquement si vide',
];
