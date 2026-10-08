<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Keeps a koala's location fields in step:
 *   koala_address, address (street only), town, postcode, lga, latitude, longitude, location_map
 *
 * - wp-admin: when the Location Map changes in a koala edit-screen save, derive the other fields.
 * - Front end: the location/release editors save through the AJAX handlers below.
 * Browser-only Google calls; nothing here calls Google from PHP.
 */

const KOALA_LOC_STREET_COPY_KEY = 'field_684b501618cf4';
const KOALA_LOC_CONFIRMED_KEY   = 'field_69c314ca25f17';
const KOALA_REL_STREET_COPY_KEY = 'field_68589b4bbcfa3';
const KOALA_REL_CONFIRMED_KEY   = 'field_69c315f087f2f';

/* ---------- helpers ---------- */

function koala_loc_street_from_address($address) {
    $address = trim((string) $address);
    if ($address === '') {
        return '';
    }
    return strpos($address, ',') !== false ? trim(explode(',', $address)[0]) : $address;
}

function koala_loc_coords_valid($lat, $lng) {
    if (!is_numeric($lat) || !is_numeric($lng)) {
        return false;
    }
    $lat = (float) $lat;
    $lng = (float) $lng;
    if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        return false;
    }
    return !($lat == 0.0 && $lng == 0.0);
}

function koala_loc_same($name, $a, $b) {
    if (in_array($name, ['latitude', 'longitude'], true) && is_numeric($a) && is_numeric($b)) {
        return abs((float) $a - (float) $b) < 0.0000005;
    }
    return trim((string) $a) === trim((string) $b);
}

function koala_loc_decode_map($value) {
    if (is_array($value)) {
        return $value;
    }
    if (!is_string($value) || $value === '') {
        return null;
    }
    $decoded = json_decode($value, true);
    if (!is_array($decoded)) {
        $decoded = json_decode(wp_unslash($value), true);
    }
    return is_array($decoded) ? $decoded : null;
}

function koala_loc_map_changed($old, $new) {
    $old = is_array($old) ? $old : [];
    if (empty($old['lat']) && empty($old['lng']) && empty($old['address'])) {
        return true;
    }
    if (!koala_loc_same('latitude', $old['lat'] ?? '', $new['lat'] ?? '')) {
        return true;
    }
    if (!koala_loc_same('longitude', $old['lng'] ?? '', $new['lng'] ?? '')) {
        return true;
    }
    return !koala_loc_same('map_address', $old['address'] ?? '', $new['address'] ?? '');
}

// Derive the plain fields from an ACF Google Map value (no Google call needed)
function koala_loc_map_to_fields($map) {
    $address = sanitize_text_field((string) ($map['address'] ?? ''));
    $street  = koala_loc_street_from_address($address);
    if ($street === '') {
        $street = trim(trim((string) ($map['street_number'] ?? '')) . ' ' . trim((string) ($map['street_name'] ?? '')));
        $street = sanitize_text_field($street);
    }
    return [
        'koala_address' => $address,
        'address'       => $street,
        'latitude'      => round((float) ($map['lat'] ?? 0), 6),
        'longitude'     => round((float) ($map['lng'] ?? 0), 6),
        'town'          => sanitize_text_field((string) ($map['city'] ?? '')),
        'postcode'      => sanitize_text_field((string) ($map['post_code'] ?? '')),
    ];
}

// A town maps to an LGA only when every other koala record with that town agrees on one
function koala_loc_lookup_lga($town, $exclude_post_id = 0) {
    global $wpdb;
    $town = trim((string) $town);
    if ($town === '' || !isset($wpdb)) {
        return '';
    }
    $sql = "SELECT MIN(TRIM(l.meta_value)) FROM {$wpdb->postmeta} t
            INNER JOIN {$wpdb->postmeta} l ON l.post_id = t.post_id AND l.meta_key = 'lga'
            INNER JOIN {$wpdb->posts} p ON p.ID = t.post_id
            WHERE t.meta_key = 'town' AND LOWER(TRIM(t.meta_value)) = %s
              AND TRIM(l.meta_value) <> ''
              AND p.post_type = 'koala' AND p.post_status NOT IN ('trash', 'auto-draft')
              AND p.ID <> %d
            GROUP BY LOWER(TRIM(l.meta_value))";
    $rows = $wpdb->get_col($wpdb->prepare($sql, strtolower($town), (int) $exclude_post_id));
    return (is_array($rows) && count($rows) === 1) ? (string) $rows[0] : '';
}

function koala_loc_field_key($name, $post_id) {
    if (!function_exists('get_field_object')) {
        return '';
    }
    $object = get_field_object($name, $post_id, false, false);
    return (is_array($object) && !empty($object['key'])) ? $object['key'] : '';
}

// Write via update_field (so the change-history recorder sees each change), skipping unchanged values.
// Returns the names that actually changed.
function koala_loc_write_fields($post_id, array $values) {
    if (!function_exists('update_field') || !function_exists('get_field')) {
        return [];
    }
    $GLOBALS['koala_loc_syncing'] = true;
    $changed = [];
    foreach ($values as $name => $value) {
        // Fields addressed by key rather than name
        $aliases = [
            'street_copy'         => KOALA_LOC_STREET_COPY_KEY,
            'release_street_copy' => KOALA_REL_STREET_COPY_KEY,
            'release_address'     => 'field_67a94211a55ea',
        ];
        $selector = $aliases[$name] ?? $name;
        $current = get_field($selector, $post_id, false);
        if (!is_array($value) && koala_loc_same($name, $current, $value)) {
            continue;
        }
        update_field($selector, $value, $post_id);
        $changed[] = $name;
    }
    unset($GLOBALS['koala_loc_syncing']);
    return $changed;
}

function koala_loc_clear_caches($post_id) {
    delete_transient('koala_location_' . $post_id);
    delete_transient('koala_release_' . $post_id);
    wp_cache_delete($post_id, 'post_meta');
}

/* ---------- wp-admin: Location Map -> other fields ---------- */

function koala_loc_admin_context_ok($post_id) {
    if (!empty($GLOBALS['koala_loc_syncing']) || !is_numeric($post_id) || get_post_type($post_id) !== 'koala') {
        return false;
    }
    if (!is_admin() || wp_doing_ajax() || wp_doing_cron() || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
        return false;
    }
    if (wp_is_post_revision($post_id)) {
        return false;
    }
    // Only the koala edit screen: Quick Edit, bulk edit, imports and front-end forms never send this
    if (empty($_POST['_acf_screen']) || $_POST['_acf_screen'] !== 'post') {
        return false;
    }
    return !empty($_POST['acf']) && is_array($_POST['acf']);
}

// Before ACF saves: work out whether the map changed and which fields the editor changed themselves
add_action('acf/save_post', function ($post_id) {
    if (!koala_loc_admin_context_ok($post_id)) {
        return;
    }
    $posted  = wp_unslash($_POST['acf']);
    $map_key = koala_loc_field_key('location_map', $post_id);
    if ($map_key === '' || !isset($posted[$map_key])) {
        return;
    }
    $new_map = koala_loc_decode_map($posted[$map_key]);
    if (!$new_map || !koala_loc_coords_valid($new_map['lat'] ?? null, $new_map['lng'] ?? null)) {
        return;
    }
    $old_map = get_field('location_map', $post_id, false);
    if (!koala_loc_map_changed($old_map, $new_map)) {
        return;
    }

    $manual = [];
    foreach (['koala_address', 'address', 'town', 'postcode', 'lga', 'latitude', 'longitude'] as $name) {
        $key = koala_loc_field_key($name, $post_id);
        if ($key !== '' && isset($posted[$key]) && is_scalar($posted[$key])
            && !koala_loc_same($name, get_field($name, $post_id, false), $posted[$key])) {
            $manual[$name] = true;
        }
    }
    $GLOBALS['koala_loc_plan'][(int) $post_id] = [
        'map'       => $new_map,
        'manual'    => $manual,
        'old_town'  => (string) get_field('town', $post_id, false),
    ];
}, 5);

// After ACF saves: fill in the fields the editor did not change themselves
add_action('acf/save_post', function ($post_id) {
    $post_id = (int) $post_id;
    if (empty($GLOBALS['koala_loc_plan'][$post_id]) || !koala_loc_admin_context_ok($post_id)) {
        return;
    }
    $plan = $GLOBALS['koala_loc_plan'][$post_id];
    unset($GLOBALS['koala_loc_plan'][$post_id]);

    $derived = koala_loc_map_to_fields($plan['map']);
    $writes  = [];
    $warn    = [];
    foreach (['koala_address', 'address', 'latitude', 'longitude', 'town', 'postcode'] as $name) {
        if (!empty($plan['manual'][$name])) {
            continue;
        }
        if ($derived[$name] === '' || $derived[$name] === null) {
            if (in_array($name, ['town', 'postcode'], true)) {
                $warn[] = sprintf('%s was not in the map result, so the existing value was kept.', ucfirst($name));
            }
            continue;
        }
        $writes[$name] = $derived[$name];
    }

    if (empty($plan['manual']['lga'])) {
        $town        = isset($writes['town']) ? $writes['town'] : (string) get_field('town', $post_id, false);
        $current_lga = trim((string) get_field('lga', $post_id, false));
        $town_moved  = strcasecmp(trim($town), trim($plan['old_town'])) !== 0;
        if ($town_moved || $current_lga === '') {
            $lga = koala_loc_lookup_lga($town, $post_id);
            if ($lga !== '') {
                $writes['lga'] = $lga;
            } else {
                $warn[] = 'LGA not set automatically. Check it and set it by hand if needed.';
            }
        }
    }

    $changed = koala_loc_write_fields($post_id, $writes);
    koala_loc_clear_caches($post_id);

    $notices = [];
    if ($changed) {
        $notices[] = ['info', 'Location synced from the map. Updated: ' . implode(', ', $changed) . '.'];
    }
    foreach ($warn as $text) {
        $notices[] = ['warning', $text];
    }
    if ($notices) {
        set_transient('koala_loc_notices_' . get_current_user_id() . '_' . $post_id, $notices, 120);
    }
}, 20);

add_action('admin_notices', function () {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->base !== 'post' || $screen->post_type !== 'koala') {
        return;
    }
    $post_id = isset($_GET['post']) ? (int) $_GET['post'] : 0;
    $key     = 'koala_loc_notices_' . get_current_user_id() . '_' . $post_id;
    $notices = get_transient($key);
    if (!$notices) {
        return;
    }
    delete_transient($key);
    foreach ($notices as $notice) {
        printf('<div class="notice notice-%s is-dismissible" role="status"><p>%s</p></div>', esc_attr($notice[0]), esc_html($notice[1]));
    }
});

/* ---------- Maps key: wp-config constant wins, Settings option is the fallback ---------- */

function koala_plugin_get_maps_key() {
    if (defined('KRS_MAPS_BROWSER_KEY') && KRS_MAPS_BROWSER_KEY) {
        return (string) KRS_MAPS_BROWSER_KEY;
    }
    return (string) get_option('koala_maps_api_key', '');
}

// Gives the wp-admin ACF Location Map field the same browser key
add_filter('acf/fields/google_map/api', function ($api) {
    $key = koala_plugin_get_maps_key();
    if ($key !== '') {
        $api['key'] = $key;
    }
    return $api;
});

/* ---------- Front-end AJAX ---------- */

function koala_loc_post($name) {
    return isset($_POST[$name]) && is_scalar($_POST[$name]) ? sanitize_text_field(wp_unslash($_POST[$name])) : '';
}

// Registered at priority 1 so this handler answers before any other handler on the same action
add_action('wp_ajax_save_koala_location', 'koala_plugin_ajax_save_location', 1);
function koala_plugin_ajax_save_location() {
    check_ajax_referer('koala_location_nonce', 'nonce');

    $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
    if (!$post_id || !is_user_logged_in() || get_post_type($post_id) !== 'koala') {
        wp_send_json_error('Invalid post ID or not logged in');
    }
    if (!function_exists('update_field')) {
        wp_send_json_error('ACF not active');
    }

    $lat = isset($_POST['latitude']) ? $_POST['latitude'] : null;
    $lng = isset($_POST['longitude']) ? $_POST['longitude'] : null;
    if (!koala_loc_coords_valid($lat, $lng)) {
        wp_send_json_error('Latitude must be between -90 and 90 and longitude between -180 and 180. Nothing was saved.');
    }
    $lat = round((float) $lat, 6);
    $lng = round((float) $lng, 6);

    $address  = koala_loc_post('address');
    $town     = koala_loc_post('town');
    $lga      = koala_loc_post('lga');
    $postcode = koala_loc_post('postcode');
    $notices  = [];

    $existing_town = trim((string) get_field('town', $post_id, false));
    $existing_pc   = trim((string) get_field('postcode', $post_id, false));
    $existing_lga  = trim((string) get_field('lga', $post_id, false));

    // Never write a blank over an existing value
    $final_town = $town !== '' ? $town : $existing_town;
    $final_pc   = $postcode !== '' ? $postcode : $existing_pc;
    if ($town === '' && $existing_town !== '') {
        $notices[] = 'Town was not found for this location, so the existing town was kept.';
    }
    if ($postcode === '' && $existing_pc !== '') {
        $notices[] = 'Postcode was not found for this location, so the existing postcode was kept.';
    }

    $final_lga = $lga !== '' ? $lga : $existing_lga;
    if ($lga === '') {
        $town_moved = strcasecmp($final_town, $existing_town) !== 0;
        if ($town_moved || $existing_lga === '') {
            $found = koala_loc_lookup_lga($final_town, $post_id);
            if ($found !== '') {
                $final_lga = $found;
            } else {
                $notices[] = 'LGA not set automatically. Check it and set it in wp-admin if needed.';
            }
        }
    }

    $street = koala_loc_street_from_address($address);
    if ($street === '') {
        $street = koala_loc_post('street_address');
    }

    $existing_map = get_field('location_map', $post_id, false);
    $existing_map = is_array($existing_map) ? $existing_map : [];
    $state        = koala_loc_post('state');
    $country      = koala_loc_post('country');
    $location_map = [
        'address'   => $address,
        'lat'       => $lat,
        'lng'       => $lng,
        'zoom'      => !empty($existing_map['zoom']) ? (int) $existing_map['zoom'] : 15,
        'name'      => koala_loc_post('place_name') !== '' ? koala_loc_post('place_name') : $street,
        'city'      => $final_town,
        'state'     => $state !== '' ? $state : (string) ($existing_map['state'] ?? ''),
        'post_code' => $final_pc,
        'country'   => $country !== '' ? $country : (string) ($existing_map['country'] ?? ''),
    ];
    foreach (['street_number' => 'street_number', 'street_name' => 'street_name', 'state_short' => 'state_short', 'country_short' => 'country_short'] as $post_key => $map_key) {
        if (koala_loc_post($post_key) !== '') {
            $location_map[$map_key] = koala_loc_post($post_key);
        }
    }

    $values = [
        'koala_address' => $address,
        'latitude'      => $lat,
        'longitude'     => $lng,
        'town'          => $final_town,
        'lga'           => $final_lga,
        'postcode'      => $final_pc,
        'address'       => $street,
        'street_copy'   => $street,
        'location_map'  => $location_map,
    ];
    koala_loc_write_fields($post_id, $values);

    // ACF checkbox needs an array of selected values, or an empty array to untick
    $confirmed = (isset($_POST['location_confirmed']) && $_POST['location_confirmed'] === '1') ? ['1'] : [];
    update_field(KOALA_LOC_CONFIRMED_KEY, $confirmed, $post_id);

    koala_loc_clear_caches($post_id);
    wp_send_json_success([
        'message' => 'Location saved',
        'notices' => $notices,
        'handler' => 'koala-plugin',
    ]);
}

// Release location: same safety rules (valid coordinates, no blanks over existing values)
add_action('wp_ajax_save_koala_release_location', 'koala_plugin_ajax_save_release_location', 1);
function koala_plugin_ajax_save_release_location() {
    check_ajax_referer('koala_release_location_nonce', 'nonce');

    $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
    if (!$post_id || !is_user_logged_in() || get_post_type($post_id) !== 'koala') {
        wp_send_json_error('Not logged in');
    }
    if (!function_exists('update_field')) {
        wp_send_json_error('ACF not active');
    }

    $lat = isset($_POST['latitude']) ? $_POST['latitude'] : null;
    $lng = isset($_POST['longitude']) ? $_POST['longitude'] : null;
    if (!koala_loc_coords_valid($lat, $lng)) {
        wp_send_json_error('Latitude must be between -90 and 90 and longitude between -180 and 180. Nothing was saved.');
    }

    $address  = koala_loc_post('address');
    $town     = koala_loc_post('town');
    $postcode = koala_loc_post('postcode');
    $street   = koala_loc_street_from_address($address);
    $notices  = [];
    if ($town === '' && trim((string) get_field('release_town', $post_id, false)) !== '') {
        $notices[] = 'Town was not found for this location, so the existing town was kept.';
    }
    if ($postcode === '' && trim((string) get_field('release_post_code', $post_id, false)) !== '') {
        $notices[] = 'Postcode was not found for this location, so the existing postcode was kept.';
    }

    $values = [
        'release_address'     => $address,
        'release_lat'         => round((float) $lat, 6),
        'release_long'        => round((float) $lng, 6),
        'release_street_copy' => $street,
    ];
    if ($town !== '') {
        $values['release_town'] = $town;
    }
    if ($postcode !== '') {
        $values['release_post_code'] = $postcode;
    }
    koala_loc_write_fields($post_id, $values);

    $confirmed = (isset($_POST['location_confirmed']) && $_POST['location_confirmed'] === '1') ? ['1'] : [];
    update_field(KOALA_REL_CONFIRMED_KEY, $confirmed, $post_id);

    koala_loc_clear_caches($post_id);
    wp_send_json_success([
        'message' => 'Location saved',
        'notices' => $notices,
        'handler' => 'koala-plugin',
    ]);
}

// Lets the editor preview the LGA that will be used when Google does not return one
add_action('wp_ajax_koala_lookup_lga', function () {
    check_ajax_referer('koala_location_nonce', 'nonce');
    if (!is_user_logged_in()) {
        wp_send_json_error('Not logged in');
    }
    $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
    wp_send_json_success(['lga' => koala_loc_lookup_lga(koala_loc_post('town'), $post_id)]);
});
