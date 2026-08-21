<?php

return [
    'title' => 'Instalación',
    'step_of' => 'Paso :current de :total',
    'continue' => 'Continuar',
    'processing' => 'Procesando...',
    'error' => 'Se ha producido un error',
    'network_error' => 'Error de red:',

    'steps' => [
        'welcome' => 'Bienvenida',
        'setup' => 'Configurar propiedades y unidades',
        'complete' => 'Instalación completada',
    ],

    'welcome' => [
        'intro' => 'Este asistente te ayudará a crear la configuración inicial de tu sistema de calendario.',
        'installed_title' => 'Qué se instalará:',
        'installed' => [
            'Tablas de la base de datos',
            'Sistema de caché',
            'Gestión de sesiones',
            'Sistema de sincronización de calendarios',
        ],
        'configured_title' => 'Qué se configurará:',
        'configured' => [
            'Sistema de autenticación',
            'Usuario administrador inicial',
            'Propiedades y unidades de alquiler iniciales',
        ],
        'duration' => 'La instalación debería tardar menos de 5 minutos',
    ],

    'setup' => [
        'hint' => 'Crea tus propiedades (organizaciones o empresas), sus unidades de alquiler (apartamentos, villas, casitas) y configura las fuentes de sincronización de calendario para cada unidad.',
        'add_property' => 'Añadir otra propiedad',
        'add_unit' => 'Añadir unidad',
        'add_source' => 'Añadir fuente',
        'property_name' => 'Nombre de la propiedad',
        'property_name_placeholder' => 'Mi empresa',
        'property_slug_placeholder' => 'mi-empresa',
        'unit_name' => 'Nombre de la unidad',
        'unit_name_placeholder' => 'Mi alojamiento',
        'unit_slug_placeholder' => 'mi-alojamiento',
        'slug' => 'Slug',
        'website' => 'Sitio web',
        'optional' => '(opcional)',
        'source_url_placeholder' => 'https://calendar.example.com/mi-alojamiento.ics',
    ],

    'complete' => [
        'heading' => '¡Instalación completada!',
        'lead' => 'Tu sistema de gestión de calendario :app está listo para usarse.',
        'configured_title' => 'Qué se ha configurado:',
        'database' => 'Estructura de base de datos creada',
        'auth' => 'Método de autenticación configurado',
        'admin' => 'Cuenta de administrador creada',
        'properties' => 'Propiedades y unidades de alquiler configuradas',
        'sources' => 'Fuentes de sincronización de calendario añadidas',
        'next_title' => 'Próximos pasos',
        'next' => 'Haz clic en el botón de abajo para acceder a tu calendario y empezar a gestionar tu calendario de alquiler.',
        'go' => 'Ir al calendario',
        'finalizing' => 'Finalizando...',
        'failed' => 'Se ha producido un error. Actualiza la página e inténtalo de nuevo.',
    ],
];
