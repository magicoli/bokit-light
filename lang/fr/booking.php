<?php

return [
    'field.status' => 'Statut',
    'field.guest_name' => 'Nom',
    'field.check_in' => 'Arrivée',
    'field.check_out' => 'Départ',
    'field.guests' => 'Personnes',
    'field.adults' => 'Adultes',
    'field.children' => 'Enfants',
    'field.source_name' => 'Source',
    'field.price' => 'Prix',
    'field.commission' => 'Commission',
    'field.notes' => 'Notes',
    'field.unit_name' => 'Unité',
    'field.ota_url' => 'URL OTA',
    'field.ota_link' => 'Lien OTA',
    'field.actions' => 'Actions',
    'field.metadata' => 'Données brutes',
    'field.metadata.status' => 'Statut',
    'field.metadata.api_source' => 'Source',
    'field.metadata.email' => 'Email',
    'field.metadata.guests' => 'Téléphone',
    'field.is_manual' => 'Réservation manuelle',
    'field.group_id' => 'ID de groupe',
    'field.unit_id' => "ID d'unité",
    'field.property_id' => 'ID de propriété',
    'field.uid' => 'UID',
    'field.country' => 'Pays',
    'field.comments' => 'Commentaires',
    'field.paid' => 'Payé',
    'field.balance' => 'Solde',
    'field.deposit' => 'Acompte',
    'field.created_at' => 'Créée le',
    'field.updated_at' => 'Mise à jour',
    'field.deleted_at' => 'Supprimée le',

    // Canonical statuses (see Booking::STATUSES)
    'status.confirmed' => 'Confirmée',
    'status.option' => 'Option',
    'status.quote' => 'Devis',
    'status.blocked' => 'Bloqué',
    'status.cancelled' => 'Annulée',
    'status.vanished' => 'Disparue',
    'status.deleted' => 'Supprimée',
    'status.undefined' => 'Indéfini',
    'status.unavailable' => 'Indisponible',
    'status.cancelled_by_owner' => 'Annulée par le propriétaire',
    'status.cancelled_by_guest' => 'Annulée par le client',

    'tag.new' => 'Nouvelle',

    // One-line booking title (widgets, mail subjects)
    'title.units' => ':count unité|:count unités',
    'title.dates' => 'du :from au :to',

    // Dashboard widgets
    'widget.ongoing' => 'Séjours en cours',
    'widget.upcoming' => 'Séjours à venir',
    'widget.options' => 'Options en attente',
    'widget.quotes' => 'Offres en attente',
    'widget.see_all' => 'Tout voir',

    // Display filters
    'filter.show_cancelled' => 'Afficher les annulées',
    'filter.show_quotes' => 'Afficher les devis',
    'filter.effective_only' => 'Réservations effectives uniquement',

    // Form sections
    'section.guests' => 'Personnes',
    'section.pricing' => 'Tarification',
    'section.metadata' => 'Métadonnées',

    // Sources
    'section.sources' => 'Sources',
    'source.origin' => 'Origine',
    'source.external_id' => 'ID externe',
    'source.last_seen' => 'Vu pour la dernière fois',
    'source.pending_origin' => 'Origine (non connectée)',

    'source.not_connected' => 'non connectée',

    'field.group' => 'Groupe',
    'group.none' => 'Sans groupe',

    'section.group' => 'Réservation groupée',
    'group.total' => 'Total',

    // Invoice detail
    'section.invoice' => 'Détail de la facture',
    'invoice.description' => 'Description',
    'invoice.qty' => 'Qté',
    'invoice.unit_price' => 'Prix unitaire',
    'invoice.amount' => 'Montant',
    'invoice.total' => 'Total',
    'invoice.payment' => 'Paiement',

    // Helper text
    'guests_auto_calculated' => 'Calculé automatiquement si vide',
];
