<?php

/**
 * Plugin Name: Bokit Connector
 * Plugin URI: https://bokit.click
 * Description: Authentication bridge and data API for Bokit calendar application
 * Version: 0.6.8
 * Author: Olivier van Helden
 * Author URI: https://magiiic.com
 * License: AGPL-3.0-or-later
 * Text Domain: bokit-connector
 */

// #
// DEVELOPERS/AGENTS:
// bump version and deploy wp plugin after any change before any testing:
//   source .env && rsync --delete -Wavz wordpress/bokit-connector/ $LIVE_HOST:$LIVE_DOCUMENT_ROOT/wp-content/plugins/bokit-connector/
// #

// If this file is called directly, abort.
if (! defined('WPINC')) {
    exit();
}

define('BOKIT_CONNECTOR_VERSION', '0.6.8');
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

    // HBook units
    register_rest_route('bokit/v1', '/hbook-units', [
        'methods' => 'GET',
        'callback' => 'bokit_connector_get_hbook_units',
        'permission_callback' => 'bokit_connector_check_permission',
    ]);

    // Multipass units
    register_rest_route('bokit/v1', '/multipass-units', [
        'methods' => 'GET',
        'callback' => 'bokit_connector_get_multipass_units',
        'permission_callback' => 'bokit_connector_check_permission',
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

    return array_intersect(
        ['administrator', 'bokit_manager'],
        (array) $user->roles,
    ) !== [];
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
        $request->get_param('password'),
    );

    if (is_wp_error($user)) {
        return new WP_Error(
            'authentication_failed',
            $user->get_error_message(),
            ['status' => 401],
        );
    }

    return new WP_REST_Response(
        [
            'id' => $user->ID,
            'username' => $user->user_login,
            'name' => $user->display_name,
            'email' => $user->user_email,
            'roles' => $user->roles,
        ],
        200,
    );
}

/**
 * GET /wp-json/bokit/v1/bookings/hbook
 *
 * Returns two kinds of records (UNION):
 *
 * 1. Direct bookings: hb_resa WHERE origin='website'.
 *    uid = "hbook:{site_host}:{id}"
 *
 * 2. Automatically blocked individual units linked to group/package bookings
 *    (e.g. "Site entier"): hb_accom_blocked WHERE is_prepa_time=0, joined
 *    to their parent hb_resa. Guest info and price come from the parent booking.
 *    uid = "hbook:{site_host}:{parent_id}-{accom_id}_{accom_num}"
 *    group_hbook_id = parent hbook id (for linking in Bokit)
 *
 * The site host in the uid ensures uniqueness across multiple HBook installs
 * (e.g. different WP sites) and distinguishes from other booking plugins.
 *
 * unit_id is "{accom_id}_{accom_num}" — matches hbook_unit_id in Bokit source config.
 */
function bokit_connector_get_hbook_bookings(
    WP_REST_Request $request,
): WP_REST_Response {
    global $wpdb;

    $from = $request->get_param('from');
    $to = $request->get_param('to');

    $resa_where = "r.status NOT IN ('cancelled','deleted') AND r.origin = 'website'";
    $date_clause_resa = '';
    $date_clause_block = '';
    $params = [];

    if ($from) {
        $date_clause_resa .= ' AND r.check_in >= %s';
        $date_clause_block .= ' AND b.from_date >= %s';
        $params[] = $from;
    }
    if ($to) {
        $date_clause_resa .= ' AND r.check_in <= %s';
        $date_clause_block .= ' AND b.from_date <= %s';
        $params[] = $to;
    }

    // UNION needs date params twice (once per subquery).
    $all_params = array_merge($params, $params);

    $sql = "
        SELECT
            r.uid  AS hbook_uid,
            r.id   AS hbook_id,
            NULL   AS group_hbook_uid,
            r.check_in,
            r.check_out,
            r.accom_id,
            r.accom_num,
            r.adults,
            r.children,
            n.num_name AS unit_name,
            r.price,
            r.deposit,
            r.paid,
            r.status,
            r.customer_id,
            r.received_on,
            r.updated_on,
            c.info AS guest_info
        FROM {$wpdb->prefix}hb_resa r
        LEFT JOIN {$wpdb->prefix}hb_accom_num_name n
              ON n.accom_id = r.accom_id AND n.accom_num = r.accom_num
        LEFT JOIN {$wpdb->prefix}hb_customers c ON c.id = r.customer_id
        WHERE {$resa_where}{$date_clause_resa}

        UNION ALL

        SELECT
            r.uid  AS hbook_uid,
            r.id   AS hbook_id,
            r.uid  AS group_hbook_uid,
            b.from_date AS check_in,
            b.to_date   AS check_out,
            b.accom_id,
            b.accom_num,
            r.adults,
            r.children,
            n.num_name AS unit_name,
            r.price,
            r.deposit,
            r.paid,
            r.status,
            r.customer_id,
            r.received_on,
            r.updated_on,
            c.info AS guest_info
        FROM {$wpdb->prefix}hb_accom_blocked b
        INNER JOIN {$wpdb->prefix}hb_resa r
              ON r.id = b.linked_resa_id
             AND r.status NOT IN ('cancelled','deleted')
             AND r.origin = 'website'
        LEFT JOIN {$wpdb->prefix}hb_accom_num_name n
              ON n.accom_id = b.accom_id AND n.accom_num = b.accom_num
        LEFT JOIN {$wpdb->prefix}hb_customers c ON c.id = r.customer_id
        WHERE b.is_prepa_time = 0{$date_clause_block}

        ORDER BY check_in
    ";

    $query = $all_params ? $wpdb->prepare($sql, ...$all_params) : $sql;

    $rows = $wpdb->get_results($query, ARRAY_A);

    $bookings = array_map(function ($r) {
        $guest = [];
        if ($r['guest_info']) {
            $guest = json_decode($r['guest_info'], true) ?? [];
        }

        return [
            // hbook_uid: HBook's own uid from wp_hb_resa (iCal-format, stable).
            // Shared by ALL rows of the same booking/group — used as Bokit uid.
            'hbook_uid' => $r['hbook_uid'],
            // is_blocked: true for automatically-blocked unit rows (Part 2 / group members).
            // false for the direct booking row (Part 1 — solo or group summary).
            'is_blocked' => $r['group_hbook_uid'] !== null,
            'id' => (int) $r['hbook_id'],
            'check_in' => $r['check_in'],
            'check_out' => $r['check_out'],
            'unit_id' => "{$r['accom_id']}_{$r['accom_num']}",
            'unit' => $r['unit_name'] ?? null,
            'adults' => (int) $r['adults'],
            'children' => (int) $r['children'],
            'price' => (float) $r['price'],
            'deposit' => (float) $r['deposit'],
            'paid' => (float) $r['paid'],
            'status' => $r['status'],
            'customer_id' => (int) $r['customer_id'],
            // Source timestamps: when the reservation was created and last
            // modified in HBook (parent hb_resa values for blocked rows).
            'created_at' => $r['received_on'],
            'updated_at' => $r['updated_on'],
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
 */
function bokit_connector_get_multipass_bookings(
    WP_REST_Request $request,
): WP_REST_Response {
    global $wpdb;
    // Build resource map dynamically from mltp_resource posts
    $resources = $wpdb->get_results(
        "SELECT ID, post_title
         FROM {$wpdb->prefix}posts
         WHERE post_type = 'mltp_resource'
           AND post_status = 'publish'",
        ARRAY_A,
    );

    $resource_map = [];
    foreach ($resources as $r) {
        $resource_map[(int) $r['ID']] = $r['post_title'];
    }

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
                COALESCE(
                    MAX(CASE WHEN pm.meta_key='contact_email'  THEN pm.meta_value END),
                    MAX(CASE WHEN pm.meta_key='customer_email' THEN pm.meta_value END),
                    MAX(CASE WHEN pm.meta_key='attendee_email' THEN pm.meta_value END)
                ) as contact_email,
                MAX(CASE WHEN pm.meta_key='contact_phone' THEN pm.meta_value END) as contact_phone,
                MAX(CASE WHEN pm.meta_key='flags'         THEN pm.meta_value END) as flags,
                MAX(CASE WHEN pm.meta_key='origin'        THEN pm.meta_value END) as origin,
                MAX(CASE WHEN pm.meta_key='adults'        THEN pm.meta_value END) as adults,
                MAX(CASE WHEN pm.meta_key='children'      THEN pm.meta_value END) as children,
                MAX(CASE WHEN pm.meta_key='babies'        THEN pm.meta_value END) as babies
         FROM {$wpdb->prefix}posts p
         JOIN {$wpdb->prefix}postmeta pm ON pm.post_id = p.ID
         WHERE p.post_type = 'mltp_prestation'
           AND p.post_status NOT IN ('trash','auto-draft')
         GROUP BY p.ID
         ORDER BY date_from_ts DESC",
        ARRAY_A,
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
        ARRAY_A,
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
        $date_from = $p['date_from_ts']
            ? date('Y-m-d', (int) $p['date_from_ts'])
            : null;
        $date_to = $p['date_to_ts']
            ? date('Y-m-d', (int) $p['date_to_ts'])
            : null;

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
            $deposit = is_array($dep)
                ? (float) ($dep['amount'] ?? 0)
                : (float) $p['deposit_raw'];
        }

        // All details (units, services, fees)
        $units = [];
        foreach ($details_by_prestation[$p['ID']] ?? [] as $d) {
            $rid = (int) $d['resource_id'];
            $units[] = [
                'detail_id' => (int) $d['ID'],
                'status' => $d['post_status'],
                'unit' => $resource_map[$rid] ?? null,
                'resource_id' => $rid,
                'check_in' => $d['date_from_ts']
                    ? date('Y-m-d', (int) $d['date_from_ts'])
                    : null,
                'check_out' => $d['date_to_ts']
                    ? date('Y-m-d', (int) $d['date_to_ts'])
                    : null,
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
            'origin' => bokit_connector_resolve_origin(
                $p['origin'] ?? null,
                $p['contact_email'] ?? null,
            ),
            'adults' => $p['adults'] !== null ? (int) $p['adults'] : null,
            'children' => $p['children'] !== null ? (int) $p['children'] : null,
            'babies' => $p['babies'] !== null ? (int) $p['babies'] : null,
            'contact_name' => $p['contact_name'],
            'contact_email' => $p['contact_email'],
            'contact_phone' => $p['contact_phone'],
            'units' => $units,
        ];
    }

    return new WP_REST_Response($bookings, 200);
}

/**
 * Resolve the booking origin/canal from the explicit origin meta or, when absent,
 * from the contact email domain.
 *
 * Multipass does not always store an 'origin' meta key. Guest emails from OTAs
 * follow predictable patterns:
 *
 *   *@guest.booking.com  → bookingcom
 *
 *   *@airbnb.com         → airbnb
 *
 *   *@guest.airbnb.com   → airbnb
 */
function bokit_connector_resolve_origin(
    ?string $origin,
    ?string $email,
): ?string {
    if ($origin !== null && $origin !== '') {
        return $origin;
    }

    if (! $email) {
        return null;
    }

    $domain = strtolower(substr($email, strpos($email, '@') + 1));

    if (str_ends_with($domain, 'booking.com')) {
        return 'bookingcom';
    }

    if (str_ends_with($domain, 'airbnb.com')) {
        return 'airbnb';
    }

    return null;
}

/**
 * GET /wp-json/bokit/v1/hbook-units
 *
 * Returns individual HBook accommodation units (real bookable slots).
 *
 * Data source: wp_hb_accom_num_name joined with wp_posts.
 *
 * Filtering:
 * - Only published hb_accommodation posts.
 * - Only rows where num_name is NOT purely numeric.
 *   Multi-unit package types (e.g. "3 gîtes", "Zetoil + 1 gîte") have num_name='1'
 *   because they contain only one slot; real units have proper names (Sun, Moon…).
 *
 * NOTE: HBook provides HbAvailableAccom->get_available_accom() which may handle
 * additional edge cases (disabled units, capacity constraints, etc.). If filtering
 * issues arise, consider switching to that method instead of the raw SQL approach.
 *
 * Returned id format: "{accom_id}_{accom_num}" — used as hbook_accom in booking imports.
 */
function bokit_connector_get_hbook_units(): WP_REST_Response
{
    global $wpdb;

    $rows = $wpdb->get_results(
        "SELECT n.accom_id, n.accom_num, n.num_name, p.post_title
         FROM {$wpdb->prefix}hb_accom_num_name n
         INNER JOIN {$wpdb->posts} p ON p.ID = n.accom_id
         WHERE p.post_type   = 'hb_accommodation'
           AND p.post_status = 'publish'
           AND n.num_name NOT REGEXP '^[0-9]+$'
         ORDER BY n.accom_id, n.accom_num",
    );

    $units = array_map(function ($row) {
        return [
            'id' => $row->accom_id.'_'.$row->accom_num,
            'accom_id' => (int) $row->accom_id,
            'accom_num' => (int) $row->accom_num,
            'name' => $row->num_name,
            'post_title' => $row->post_title,
        ];
    }, $rows);

    return new WP_REST_Response(['units' => $units], 200);
}

/**
 * GET /wp-json/bokit/v1/multipass-units
 *
 * Returns all Multipass units (resources) that can be booked.
 *
 * Multipass stores bookable units as "resources" in the mltp_resource post type.
 * Each resource has a post_title (the unit name) and an ID that is referenced
 * in mltp_detail.postmeta.resource_id.
 * Resource types (categories) are stored in the 'resource-type' taxonomy.
 */
function bokit_connector_get_multipass_units(): WP_REST_Response
{
    global $wpdb;

    // Fetch all published Multipass resources (units) with their types
    $resources = $wpdb->get_results(
        "SELECT p.ID, p.post_title
         FROM {$wpdb->prefix}posts p
         WHERE p.post_type = 'mltp_resource'
           AND p.post_status = 'publish'
         ORDER BY p.post_title",
        ARRAY_A,
    );

    $units = array_map(function ($resource) {
        $resource_id = (int) $resource['ID'];
        $name = $resource['post_title'];

        // Get resource type (category) from taxonomy
        $terms = get_the_terms($resource_id, 'resource-type');
        $type_name = '';
        if ($terms && ! is_wp_error($terms)) {
            $first_term = reset($terms);
            $type_name = $first_term->name ?? '';
        }

        // Format: "Unit Name (Type)" if type exists and is different from name
        $label = $type_name && strtolower($type_name) !== strtolower($name)
            ? "{$name} ({$type_name})"
            : $name;

        return [
            'id' => $resource_id,
            'name' => $name,
            'post_title' => $name,
            'type' => $type_name,
        ];
    }, $resources);

    return new WP_REST_Response(['units' => $units], 200);
}

add_action('init', 'bokit_connector_add_roles');

/**
 * Register Bokit Manager role for external API authentication.
 */
function bokit_connector_add_roles(): void
{
    add_role('bokit_manager', 'Bokit Manager', ['read' => true]);
}
