<?php

return [
    'title' => 'Installation',
    'step_of' => 'Step :current of :total',
    'continue' => 'Continue',
    'processing' => 'Processing...',
    'error' => 'An error occurred',
    'network_error' => 'Network error:',

    'steps' => [
        'welcome' => 'Welcome',
        'setup' => 'Configure Properties & Units',
        'complete' => 'Installation Complete',
    ],

    'welcome' => [
        'intro' => 'This wizard will help you create the initial configuration of your calendar system.',
        'installed_title' => 'What will be installed:',
        'installed' => [
            'Database tables',
            'Cache system',
            'Session management',
            'Calendar sync system',
        ],
        'configured_title' => 'What will be configured:',
        'configured' => [
            'Authentication system',
            'Initial admin user',
            'Initial properties and rental units',
        ],
        'duration' => 'Installation should take less than 5 minutes',
    ],

    'setup' => [
        'hint' => 'Create your properties (organizations or companies), their rental units (apartments, villas, cottages), and configure calendar synchronization sources for each unit.',
        'add_property' => 'Add Another Property',
        'add_unit' => 'Add Unit',
        'add_source' => 'Add Source',
        'property_name' => 'Property Name',
        'property_name_placeholder' => 'My Company',
        'property_slug_placeholder' => 'my-company',
        'unit_name' => 'Unit Name',
        'unit_name_placeholder' => 'My Accommodation',
        'unit_slug_placeholder' => 'my-accommodation',
        'slug' => 'Slug',
        'website' => 'Website',
        'optional' => '(optional)',
        'source_url_placeholder' => 'https://calendar.example.com/my-accommodation.ics',
    ],

    'complete' => [
        'heading' => 'Installation Complete!',
        'lead' => 'Your :app calendar management system is ready to use.',
        'configured_title' => "What's been configured:",
        'database' => 'Database structure created',
        'auth' => 'Authentication method configured',
        'admin' => 'Administrator account created',
        'properties' => 'Properties and rental units configured',
        'sources' => 'Calendar synchronization sources added',
        'next_title' => 'Next Steps',
        'next' => 'Click the button below to access your calendar and start managing your rental calendar.',
        'go' => 'Go to Calendar',
        'finalizing' => 'Finalizing...',
        'failed' => 'An error occurred. Please refresh the page and try again.',
    ],
];
