<?php
/**
 * Plugin Name: Bokit Connector
 * Plugin URI: https://bokit.click
 * Description: Authentication bridge for Bokit calendar application
 * Version: 0.1.0
 * Author: Olivier van Helden
 * Author URI: https://magiiic.com
 * License: AGPL-3.0-or-later
 * Text Domain: bokit-connector
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

define('BOKIT_CONNECTOR_VERSION', '0.1.0');
define('BOKIT_CONNECTOR_PLUGIN_DIR', plugin_dir_path(__FILE__));

/**
 * Register REST API endpoint for authentication
 */
add_action('rest_api_init', function () {
    register_rest_route('bokit/v1', '/auth', [
        'methods' => 'POST',
        'callback' => 'bokit_connector_authenticate_user',
        'permission_callback' => '__return_true',
        'args' => [
            'username' => [
                'required' => true,
                'type' => 'string',
                'description' => 'WordPress username or email',
            ],
            'password' => [
                'required' => true,
                'type' => 'string',
                'description' => 'WordPress password',
            ],
        ],
    ]);
});

/**
 * Authenticate user and return user data
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function bokit_connector_authenticate_user($request) {
    $username = $request->get_param('username');
    $password = $request->get_param('password');
    
    // Verify credentials (wp_authenticate accepts username or email)
    $user = wp_authenticate($username, $password);
    
    if (is_wp_error($user)) {
        return new WP_Error(
            'invalid_credentials',
            'Invalid username or password',
            ['status' => 401]
        );
    }
    
    // Return user data
    return new WP_REST_Response([
        'id' => $user->ID,
        'username' => $user->user_login,
        'name' => $user->display_name,
        'email' => $user->user_email,
        'roles' => $user->roles,
    ], 200);
}
