<?php

/**
 * Plugin Name: Bokit Connector
 * Plugin URI: https://bokit.click
 * Description: Authentication bridge and data API for Bokit calendar application
 * Version: 0.3.0
 * Author: Olivier van Helden
 * Author URI: https://magiiic.com
 * License: AGPL-3.0-or-later
 * Text Domain: bokit-connector
 */

// If this file is called directly, abort.
if (! defined('WPINC')) {
    exit();
}

define('BOKIT_CONNECTOR_VERSION', '0.3.0');
define('BOKIT_CONNECTOR_PLUGIN_DIR', plugin_dir_path(__FILE__));

/**
 * Register REST API endpoints
 */
add_action('rest_api_init', 'bokit_connector_register_rest_routes');
function bokit_connector_register_rest_routes()
{
    // Authentication
    register_rest_route('bokit/v1', '/auth', [
        'methods' => 'POST',
        'callback' => 'bokit_connector_authenticate_user',
        'permission_callback' => '__return_true',
        'args' => [
            'username' => ['required' => true, 'type' => 'string'],
            'password' => ['required' => true, 'type' => 'string'],
        ],
    ]);

    register_rest_route('bokit/v1', '/status', [
        'methods' => 'GET',
        'callback' => 'bokit_connector_get_status',
        'permission_callback' => '__return_true',
    ]);

    // HBook bookings
    register_rest_route('bokit/v1', '/bookings/hbook', [
        'methods' => 'GET',
        'callback' => 'bokit_connector_get_hbook_bookings',
        'permission_callback' => 'bokit_connector_check_permission',
        'args' => [
            'from' => ['type' => 'string', 'default' => ''],
            'to' => ['type' => 'string', 'default' => ''],
        ],
    ]);

    // Multipass prestations
    register_rest_route('bokit/v1', '/bookings/multipass', [
        'methods' => 'GET',
        'callback' => 'bokit_connector_get_multipass_bookings',
        'permission_callback' => 'bokit_connector_check_permission',
        'args' => [
            'from' => ['type' => 'string', 'default' => ''],
            'to' => ['type' => 'string', 'default' => ''],
        ],
    ]);
}

/**
 * Permission check: must be logged-in WP user with bokit_manager role (or admin)
 */
function bokit_connector_check_permission(WP_REST_Request $request): bool
{
    if (! is_user_logged_in()) {
        return false;
    }
    $user = wp_get_current_user();

    return array_intersect(['administrator', 'bokit_manager'], (array) $user->roles) !== [];
}

/**
 * GET /wp-json/bokit/v1/status
 */
function bokit_connector_get_status(): WP_REST_Response
{
    return new WP_REST_Response([
        'status' => 'OK',
        'version' => BOKIT_CONNECTOR_VERSION,
    ]);
}

/**
 * POST /wp-json/bokit/v1/auth
 */
function bokit_connector_authenticate_user(WP_REST_Request $request)
{
    $user = wp_authenticate(
        $request->get_param('username'),
        $request->get_param('password')
    );

    if (is_wp_error($user)) {
        return new WP_Error('authentication_failed', $user->get_error_message(), ['status' => 401]);
    }

    return new WP_REST_Response([
        'id' => $user->ID,
        'username' => $user->user_login,
        'name' => $user->display_name,
        'email' => $user->user_email,
        'roles' => $user->roles,
    ], 200);
}

/**
 * GET /wp-json/bokit/v1/bookings/hbook
 *
 * Returns hbook bookings with origin=website (direct bookings with real prices).
 * Beds24/Lodgify-synced bookings are excluded (they have no price in hbook).
 *
 * Unit mapping (accom_id / accom_num → unit name):
 *   3539 / 1 → Sun
 *   3539 / 2 → Moon
 *   3539 / 3 → Violeta
 *   3539 / 4 → Zandoli
 *   3573 / 1 → Zetoil
 */
function bokit_connector_get_hbook_bookings(WP_REST_Request $request): WP_REST_Response
{
    global $wpdb;

    $unit_map = [
        '3539_1' => 'Sun',
        '3539_2' => 'Moon',
        '3539_3' => 'Violeta',
        '3539_4' => 'Zandoli',
        '3573_1' => 'Zetoil',
    ];

    $excluded_origins = ['Beds24', 'Lodgify', 'Lodgify Moon', 'Lodgify Sun',
        'Lodgify Violeta', 'Lodgify Zandoli', 'Lodgify Zetoil', 'Zandoli'];
    $placeholders = implode(',', array_fill(0, count($excluded_origins), '%s'));

    $where = "r.status NOT IN ('cancelled','deleted') AND r.origin NOT IN ($placeholders)";
    $params = $excluded_origins;

    $from = $request->get_param('from');
    $to = $request->get_param('to');
    if ($from) {
        $where .= ' AND r.check_in >= %s';
        $params[] = $from;
    }
    if ($to) {
        $where .= ' AND r.check_in <= %s';
        $params[] = $to;
    }

    $query = $wpdb->prepare(
        "SELECT r.id, r.check_in, r.check_out, r.accom_id, r.accom_num,
                r.price, r.deposit, r.paid, r.status, r.origin,
                c.info as guest_info
         FROM {$wpdb->prefix}hb_resa r
         LEFT JOIN {$wpdb->prefix}hb_customers c ON c.id = r.customer_id
         WHERE $where
         ORDER BY r.check_in",
        ...$params
    );

    $rows = $wpdb->get_results($query, ARRAY_A);

    $bookings = array_map(function ($r) use ($unit_map) {
        $key = "{$r['accom_id']}_{$r['accom_num']}";
        $guest = [];
        if ($r['guest_info']) {
            $guest = json_decode($r['guest_info'], true) ?? [];
        }

        return [
            'id' => (int) $r['id'],
            'check_in' => $r['check_in'],
            'check_out' => $r['check_out'],
            'accom_id' => (int) $r['accom_id'],
            'accom_num' => (int) $r['accom_num'],
            'unit' => $unit_map[$key] ?? null,
            'price' => (float) $r['price'],
            'deposit' => (float) $r['deposit'],
            'paid' => (float) $r['paid'],
            'status' => $r['status'],
            'origin' => $r['origin'],
            'guest_name' => trim(($guest['first_name'] ?? '').' '.($guest['last_name'] ?? '')),
            'guest_email' => $guest['email'] ?? '',
            'guest_phone' => $guest['phone'] ?? '',
        ];
    }, $rows);

    return new WP_REST_Response($bookings, 200);
}

/**
 * GET /wp-json/bokit/v1/bookings/multipass
 *
 * Returns multipass prestations with their per-unit details.
 *
 * Resource ID → unit name:
 *   9586 → Moon
 *   9587 → Sun
 *   9588 → Violeta
 *   9589 → Zandoli
 *   9590 → Zetoil
 */
function bokit_connector_get_multipass_bookings(WP_REST_Request $request): WP_REST_Response
{
    global $wpdb;

    $resource_map = [
        9586 => 'Moon',
        9587 => 'Sun',
        9588 => 'Violeta',
        9589 => 'Zandoli',
        9590 => 'Zetoil',
    ];

    $from = $request->get_param('from');
    $to = $request->get_param('to');

    // Fetch prestations with their meta
    $prestations = $wpdb->get_results(
        "SELECT p.ID, p.post_title, p.post_status,
                MAX(CASE WHEN pm.meta_key='from'          THEN pm.meta_value END) as date_from_ts,
                MAX(CASE WHEN pm.meta_key='to'            THEN pm.meta_value END) as date_to_ts,
                MAX(CASE WHEN pm.meta_key='total'         THEN pm.meta_value END) as total,
                MAX(CASE WHEN pm.meta_key='deposit'       THEN pm.meta_value END) as deposit_raw,
                MAX(CASE WHEN pm.meta_key='paid'          THEN pm.meta_value END) as paid,
                MAX(CASE WHEN pm.meta_key='contact_name'  THEN pm.meta_value END) as contact_name,
                MAX(CASE WHEN pm.meta_key='contact_email' THEN pm.meta_value END) as contact_email,
                MAX(CASE WHEN pm.meta_key='contact_phone' THEN pm.meta_value END) as contact_phone,
                MAX(CASE WHEN pm.meta_key='flags'         THEN pm.meta_value END) as flags
         FROM {$wpdb->prefix}posts p
         JOIN {$wpdb->prefix}postmeta pm ON pm.post_id = p.ID
         WHERE p.post_type = 'mltp_prestation'
           AND p.post_status NOT IN ('trash','auto-draft')
         GROUP BY p.ID
         ORDER BY date_from_ts DESC",
        ARRAY_A
    );

    // Fetch details (per-unit lines)
    $details = $wpdb->get_results(
        "SELECT p.ID, p.post_title, p.post_status,
                MAX(CASE WHEN pm.meta_key='prestation_id' THEN pm.meta_value END) as prestation_id,
                MAX(CASE WHEN pm.meta_key='resource_id'   THEN pm.meta_value END) as resource_id,
                MAX(CASE WHEN pm.meta_key='from'          THEN pm.meta_value END) as date_from_ts,
                MAX(CASE WHEN pm.meta_key='to'            THEN pm.meta_value END) as date_to_ts,
                MAX(CASE WHEN pm.meta_key='subtotal'      THEN pm.meta_value END) as subtotal
         FROM {$wpdb->prefix}posts p
         JOIN {$wpdb->prefix}postmeta pm ON pm.post_id = p.ID
         WHERE p.post_type = 'mltp_detail'
           AND p.post_status NOT IN ('trash','auto-draft')
         GROUP BY p.ID",
        ARRAY_A
    );

    // Index details by prestation_id
    $details_by_prestation = [];
    foreach ($details as $d) {
        if ($d['prestation_id']) {
            $details_by_prestation[$d['prestation_id']][] = $d;
        }
    }

    $bookings = [];
    foreach ($prestations as $p) {
        $date_from = $p['date_from_ts'] ? date('Y-m-d', (int) $p['date_from_ts']) : null;
        $date_to = $p['date_to_ts'] ? date('Y-m-d', (int) $p['date_to_ts']) : null;

        // Apply date filter
        if ($from && $date_from && $date_from < $from) {
            continue;
        }
        if ($to && $date_from && $date_from > $to) {
            continue;
        }

        // Decode deposit (serialized array)
        $deposit = 0;
        if ($p['deposit_raw']) {
            $dep = @unserialize($p['deposit_raw']);
            $deposit = is_array($dep) ? (float) ($dep['amount'] ?? 0) : (float) $p['deposit_raw'];
        }

        // Per-unit details
        $units = [];
        foreach ($details_by_prestation[$p['ID']] ?? [] as $d) {
            $rid = (int) $d['resource_id'];
            if (! isset($resource_map[$rid])) {
                continue;
            }
            $units[] = [
                'detail_id' => (int) $d['ID'],
                'status' => $d['post_status'],
                'unit' => $resource_map[$rid],
                'resource_id' => $rid,
                'check_in' => $d['date_from_ts'] ? date('Y-m-d', (int) $d['date_from_ts']) : null,
                'check_out' => $d['date_to_ts'] ? date('Y-m-d', (int) $d['date_to_ts']) : null,
                'subtotal' => (float) ($d['subtotal'] ?? 0),
            ];
        }

        $bookings[] = [
            'id' => (int) $p['ID'],
            'title' => $p['post_title'],
            'status' => $p['post_status'],
            'check_in' => $date_from,
            'check_out' => $date_to,
            'total' => (float) $p['total'],
            'deposit' => $deposit,
            'paid' => (float) ($p['paid'] ?? 0),
            'contact_name' => $p['contact_name'],
            'contact_email' => $p['contact_email'],
            'contact_phone' => $p['contact_phone'],
            'units' => $units,
        ];
    }

    return new WP_REST_Response($bookings, 200);
}

add_action('init', 'bokit_connector_add_roles');

/**
 * Register Bokit Manager role for external API authentication.
 */
function bokit_connector_add_roles(): void
{
    add_role('bokit_manager', 'Bokit Manager', ['read' => true]);
}
