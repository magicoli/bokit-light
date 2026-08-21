<?php

return [
    'label' => 'Reserva',
    'plural_label' => 'Reservas',
    'empty_state' => 'No hay reservas',
    'field' => [
        'status' => 'Estado',
        'guest_name' => 'Nombre',
        'check_in' => 'Entrada',
        'check_out' => 'Salida',
        'guests' => 'Personas',
        'adults' => 'Adultos',
        'children' => 'Niños',
        'source_name' => 'Fuente',
        'price' => 'Precio',
        'commission' => 'Comisión',
        'notes' => 'Notas',
        'unit_name' => 'Unidad',
        'ota_url' => 'URL OTA',
        'ota_link' => 'Enlace OTA',
        'actions' => 'Acciones',
        'metadata' => 'Datos sin procesar',
        'metadata.status' => 'Estado',
        'metadata.api_source' => 'Fuente',
        'metadata.email' => 'Correo electrónico',
        'metadata.guests' => 'Teléfono',
        'is_manual' => 'Reserva manual',
        'group_id' => 'ID de grupo',
        'unit_id' => 'ID de unidad',
        'property_id' => 'ID de propiedad',
        'uid' => 'UID',
        'country' => 'País',
        'comments' => 'Comentarios',
        'paid' => 'Pagado',
        'balance' => 'Saldo',
        'deposit' => 'Depósito',
        'created_at' => 'Creado',
        'updated_at' => 'Actualizado',
        'deleted_at' => 'Eliminado',
        'group' => 'Grupo',
    ],
    'status' => [

        // Canonical statuses (see Booking::STATUSES)
        'confirmed' => 'Confirmada',
        'option' => 'Opción',
        'quote' => 'Presupuesto',
        'blocked' => 'Bloqueada',
        'cancelled' => 'Cancelada',
        'vanished' => 'Desaparecida',
        'deleted' => 'Eliminada',
        'undefined' => 'Sin definir',
        'unavailable' => 'No disponible',
        'cancelled_by_owner' => 'Cancelada por el propietario',
        'cancelled_by_guest' => 'Cancelada por el huésped',
    ],

    'tag.new' => 'Nueva',

    'title' => [
        // One-line booking title (widgets, mail subjects)
        'units' => ':count unidad|:count unidades',
        'dates' => 'del :from al :to',
    ],

    'widget' => [
        // Dashboard widgets
        'ongoing' => 'Estancias en curso',
        'upcoming' => 'Próximas estancias',
        'options' => 'Opciones pendientes',
        'quotes' => 'Presupuestos pendientes',
        'see_all' => 'Ver todo',
    ],

    'filter' => [
        // Display filters
        'show_cancelled' => 'Mostrar canceladas',
        'show_quotes' => 'Mostrar presupuestos',
        'effective_only' => 'Solo reservas efectivas',
        'period' => 'Periodo',
    ],

    'period' => [
        'ongoing' => 'En curso',
        'upcoming' => 'Próximas',
        'current' => 'En curso o próximas',
        'past' => 'Pasadas',
    ],

    'section' => [
        // Form sections
        'guests' => 'Huéspedes',
        'pricing' => 'Precios',
        'metadata' => 'Metadatos',
        'sources' => 'Fuentes',
        'group' => 'Reserva de grupo',
        'invoice' => 'Detalle de factura',
    ],

    'source' => [
        // Sources
        'origin' => 'Origen',
        'beds24' => 'Abrir en Beds24',
        'external_id' => 'ID externo',
        'last_seen' => 'Visto por última vez',
        'pending_origin' => 'Origen (no conectado)',
        'not_connected' => 'no conectado',
    ],

    'group' => [
        'none' => 'Sin grupo',
        'total' => 'Total',
    ],

    'invoice' => [
        // Invoice detail
        'description' => 'Descripción',
        'qty' => 'Cant.',
        'unit_price' => 'Precio unitario',
        'amount' => 'Importe',
        'total' => 'Total',
        'payment' => 'Pago',
    ],

    'push.failed' => 'Fallo al enviar a los canales',
    'protected_origin' => 'La OTA de origen gestiona esta reserva — las fechas y el precio son de solo lectura.',
    'guests_auto_calculated' => 'Calculado automáticamente si está vacío',
];
