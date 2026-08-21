<?php

return [
    'label' => 'Reserva',
    'plural_label' => 'Reservas',
    'field' => [
        'status' => 'Estado',
        'guest_name' => 'Nome',
        'check_in' => 'Check-in',
        'check_out' => 'Check-out',
        'guests' => 'Pessoas',
        'adults' => 'Adultos',
        'children' => 'Crianças',
        'source_name' => 'Fonte',
        'price' => 'Preço',
        'commission' => 'Comissão',
        'notes' => 'Notas',
        'unit_name' => 'Unidade',
        'ota_url' => 'URL OTA',
        'ota_link' => 'Ligação OTA',
        'actions' => 'Ações',
        'metadata' => 'Dados brutos',
        'metadata.status' => 'Estado',
        'metadata.api_source' => 'Fonte',
        'metadata.email' => 'E-mail',
        'metadata.guests' => 'Telefone',
        'is_manual' => 'Reserva manual',
        'group_id' => 'ID do grupo',
        'unit_id' => 'ID da unidade',
        'property_id' => 'ID da propriedade',
        'uid' => 'UID',
        'country' => 'País',
        'comments' => 'Comentários',
        'paid' => 'Pago',
        'balance' => 'Saldo',
        'deposit' => 'Depósito',
        'created_at' => 'Criado',
        'updated_at' => 'Atualizado',
        'deleted_at' => 'Eliminado',
        'group' => 'Grupo',
    ],
    'status' => [

        // Canonical statuses (see Booking::STATUSES)
        'confirmed' => 'Confirmada',
        'option' => 'Opção',
        'quote' => 'Orçamento',
        'blocked' => 'Bloqueada',
        'cancelled' => 'Cancelada',
        'vanished' => 'Desaparecida',
        'deleted' => 'Eliminada',
        'undefined' => 'Indefinida',
        'unavailable' => 'Indisponível',
        'cancelled_by_owner' => 'Cancelada pelo proprietário',
        'cancelled_by_guest' => 'Cancelada pelo hóspede',
    ],

    'tag.new' => 'Nova',

    'title' => [
        // One-line booking title (widgets, mail subjects)
        'units' => ':count unidade|:count unidades',
        'dates' => 'de :from a :to',
    ],

    'widget' => [
        // Dashboard widgets
        'ongoing' => 'Estadias em curso',
        'upcoming' => 'Próximas estadias',
        'options' => 'Opções pendentes',
        'quotes' => 'Orçamentos pendentes',
        'see_all' => 'Ver tudo',
    ],

    'filter' => [
        // Display filters
        'show_cancelled' => 'Mostrar canceladas',
        'show_quotes' => 'Mostrar orçamentos',
        'effective_only' => 'Apenas reservas efetivas',
        'period' => 'Período',
    ],

    'period' => [
        'ongoing' => 'Em curso',
        'upcoming' => 'Próximas',
        'current' => 'Em curso ou próximas',
        'past' => 'Passadas',
    ],

    'section' => [
        // Form sections
        'guests' => 'Hóspedes',
        'pricing' => 'Preços',
        'metadata' => 'Metadados',
        'sources' => 'Fontes',
        'group' => 'Reserva de grupo',
        'invoice' => 'Detalhe da fatura',
    ],

    'source' => [
        // Sources
        'origin' => 'Origem',
        'beds24' => 'Abrir no Beds24',
        'external_id' => 'ID externo',
        'last_seen' => 'Visto pela última vez',
        'pending_origin' => 'Origem (não conectada)',
        'not_connected' => 'não conectada',
    ],

    'group' => [
        'none' => 'Sem grupo',
        'total' => 'Total',
    ],

    'invoice' => [
        // Invoice detail
        'description' => 'Descrição',
        'qty' => 'Qtd.',
        'unit_price' => 'Preço unitário',
        'amount' => 'Montante',
        'total' => 'Total',
        'payment' => 'Pagamento',
    ],

    'push.failed' => 'Falha ao enviar para os canais',
    'protected_origin' => 'A OTA de origem gere esta reserva — as datas e o preço são apenas de leitura.',
    'guests_auto_calculated' => 'Calculado automaticamente se vazio',
];
