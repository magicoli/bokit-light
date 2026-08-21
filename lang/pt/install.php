<?php

return [
    'title' => 'Instalação',
    'step_of' => 'Passo :current de :total',
    'continue' => 'Continuar',
    'processing' => 'A processar...',
    'error' => 'Ocorreu um erro',
    'network_error' => 'Erro de rede:',

    'steps' => [
        'welcome' => 'Boas-vindas',
        'setup' => 'Configurar propriedades e unidades',
        'complete' => 'Instalação concluída',
    ],

    'welcome' => [
        'intro' => 'Este assistente irá ajudá-lo a criar a configuração inicial do seu sistema de calendário.',
        'installed_title' => 'O que será instalado:',
        'installed' => [
            'Tabelas da base de dados',
            'Sistema de cache',
            'Gestão de sessões',
            'Sistema de sincronização de calendário',
        ],
        'configured_title' => 'O que será configurado:',
        'configured' => [
            'Sistema de autenticação',
            'Utilizador administrador inicial',
            'Propriedades e unidades de aluguer iniciais',
        ],
        'duration' => 'A instalação deverá demorar menos de 5 minutos',
    ],

    'setup' => [
        'hint' => 'Crie as suas propriedades (organizações ou empresas), as suas unidades de aluguer (apartamentos, vivendas, casas de campo) e configure as fontes de sincronização de calendário para cada unidade.',
        'add_property' => 'Adicionar outra propriedade',
        'add_unit' => 'Adicionar unidade',
        'add_source' => 'Adicionar fonte',
        'property_name' => 'Nome da propriedade',
        'property_name_placeholder' => 'A minha empresa',
        'property_slug_placeholder' => 'a-minha-empresa',
        'unit_name' => 'Nome da unidade',
        'unit_name_placeholder' => 'O meu alojamento',
        'unit_slug_placeholder' => 'o-meu-alojamento',
        'slug' => 'Slug',
        'website' => 'Sítio web',
        'optional' => '(opcional)',
        'source_url_placeholder' => 'https://calendar.example.com/o-meu-alojamento.ics',
    ],

    'complete' => [
        'heading' => 'Instalação concluída!',
        'lead' => 'O seu sistema de gestão de calendário :app está pronto a usar.',
        'configured_title' => 'O que foi configurado:',
        'database' => 'Estrutura da base de dados criada',
        'auth' => 'Método de autenticação configurado',
        'admin' => 'Conta de administrador criada',
        'properties' => 'Propriedades e unidades de aluguer configuradas',
        'sources' => 'Fontes de sincronização de calendário adicionadas',
        'next_title' => 'Próximos passos',
        'next' => 'Clique no botão abaixo para aceder ao seu calendário e começar a gerir o seu calendário de aluguer.',
        'go' => 'Ir para o calendário',
        'finalizing' => 'A finalizar...',
        'failed' => 'Ocorreu um erro. Atualize a página e tente novamente.',
    ],
];
