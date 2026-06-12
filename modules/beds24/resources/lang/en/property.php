<?php

return [
    'field.beds24_api_key' => 'Beds24 API key',
    'field.beds24_api_key_help' => 'Account-level API key. Leave empty to use the global key.',
    'field.beds24_prop_key' => 'Beds24 property key',
    'field.beds24_prop_key_help' => 'propKey as defined in the Beds24 property settings.',
    'field.beds24_invite_code' => 'API v2 invite code',
    'field.beds24_invite_code_help' => 'Required to push bookings to Beds24 (bidirectional sync). Generate a code with the "bookings" scope via the button and paste it here: it is exchanged immediately for a permanent token.',
    'action.generate_invite_code' => 'Generate a code',
    'field.beds24_v2_connected' => 'API v2 connected ✓',
    'field.beds24_v2_not_connected' => 'API v2 not connected',
    'notification.invite_code_exchanged' => 'Code exchanged — the v2 API is connected. Save the property to keep the token.',
    'notification.invite_code_failed' => 'Invite code exchange failed',
    'section.beds24' => 'Beds24 integration',
    'section.beds24_description' => 'Channel manager settings for this property.',
];
