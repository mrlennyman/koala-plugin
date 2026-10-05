<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Include public-sighting-form.php to register the shortcode
$public_sighting_form_file = plugin_dir_path(__FILE__) . 'public-sighting-form.php';
if (file_exists($public_sighting_form_file)) {
    require_once $public_sighting_form_file;
} else {
    error_log('koala-utils: Failed to include public-sighting-form.php at: ' . $public_sighting_form_file);
}

// Enqueue Google Maps API and scripts
add_action('wp_enqueue_scripts', function () {
    global $post;

    // Enqueue on Koala CPT pages, create-koala-entry page, or koala-sighting page with shortcode
    $is_koala_page    = is_a($post, 'WP_Post') && (is_singular('koala') || is_page('create-koala-entry') || is_page('koala-sighting'));
    $has_sighting_form = is_a($post, 'WP_Post') && (has_shortcode($post->post_content, 'koala_sighting_form') || has_shortcode($post->post_content, 'public_sighting_form'));

    if (!$is_koala_page && !$has_sighting_form) {
        return;
    }

    $maps_api_key = get_option('koala_maps_api_key', '');
    if ('' === $maps_api_key) {
        return;
    }

    wp_enqueue_script(
        'google-maps-api',
        add_query_arg(
            ['key' => $maps_api_key, 'libraries' => 'places'],
            'https://maps.googleapis.com/maps/api/js'
        ),
        [],
        null,
        true
    );

    // Set nonces for AJAX
    $ajax_data = [
        'ajaxUrl'       => admin_url('admin-ajax.php'),
        'locationNonce' => wp_create_nonce('koala_location_nonce'),
        'releaseNonce'  => wp_create_nonce('koala_release_location_nonce'),
    ];
    wp_localize_script('google-maps-api', 'koalaAjax', $ajax_data);

    // Inline script for koala_sighting_form or public_sighting_form
    if (is_page('create-koala-entry') || is_page('koala-sighting') || (is_object($post) && (has_shortcode($post->post_content, 'koala_sighting_form') || has_shortcode($post->post_content, 'public_sighting_form')))) {
        $init_function = has_shortcode($post->post_content, 'public_sighting_form') || is_page('koala-sighting') ? 'initPublicSightingMap' : 'initKoalaSightingMap';
        wp_add_inline_script('google-maps-api', "
            function initMaps() {
                if (typeof $init_function === 'function' && (document.getElementById('public-sighting-map') || document.getElementById('koala-map'))) {
                    try {
                        $init_function();
                    } catch (e) {
                        console.error('$init_function failed:', e);
                    }
                } else {
                    console.error('$init_function not found or map element missing');
                }
            }
            window.addEventListener('load', function() {
                if (typeof google !== 'undefined' && typeof google.maps !== 'undefined') {
                    initMaps();
                } else {
                    document.addEventListener('google-maps-loaded', initMaps);
                    setTimeout(() => {
                        if (typeof google === 'undefined') {
                            console.error('Google Maps API failed to load after timeout');
                        }
                    }, 5000);
                }
            });
        ");
    }
});

// AJAX handler for koala location map
add_action('wp_ajax_save_koala_location', 'save_koala_location_callback');
function save_koala_location_callback() {
    check_ajax_referer('koala_location_nonce', 'nonce');

    $post_id = intval($_POST['post_id']);
    if (!$post_id || !is_user_logged_in()) {
        error_log('koala-utils: save_koala_location - invalid post ID or not logged in');
        wp_send_json_error('Invalid post ID or not logged in');
    }
    if (!function_exists('update_field')) {
        error_log('koala-utils: save_koala_location - ACF update_field not available');
        wp_send_json_error('ACF not active');
    }

    $address   = sanitize_text_field($_POST['address']);
    $latitude  = round(floatval($_POST['latitude']), 6);
    $longitude = round(floatval($_POST['longitude']), 6);
    $town      = sanitize_text_field($_POST['town']);
    $lga       = sanitize_text_field($_POST['lga']);
    $postcode  = sanitize_text_field($_POST['postcode']);

    update_field('koala_address', $address, $post_id);
    update_field('latitude', $latitude, $post_id);
    update_field('longitude', $longitude, $post_id);
    update_field('town', $town, $post_id);
    update_field('lga', $lga, $post_id);
    update_field('postcode', $postcode, $post_id);

    // ✅ Clean street-only address (ACF key: field_684b501618cf4)
    $street_address = strpos($address, ',') !== false ? trim(explode(',', $address)[0]) : trim($address);
    update_field('field_684b501618cf4', $street_address, $post_id);

    // ✅ Save location confirmed by rescuer (ACF key: field_69c314ca25f17)
    // ACF checkbox requires array of selected values, or empty array to uncheck
    $location_confirmed = (isset($_POST['location_confirmed']) && $_POST['location_confirmed'] === '1') ? ['1'] : [];
    update_field('field_69c314ca25f17', $location_confirmed, $post_id);

    $location_map = [
        'address' => $address,
        'lat'     => $latitude,
        'lng'     => $longitude,
    ];
    update_field('location_map', $location_map, $post_id);
    delete_transient('koala_location_' . $post_id);
    wp_cache_delete($post_id, 'post_meta');
    wp_send_json_success('Location saved');
}

// AJAX handler for koala release map
add_action('wp_ajax_save_koala_release_location', function () {
    check_ajax_referer('koala_release_location_nonce', 'nonce');

    $post_id = intval($_POST['post_id']);
    if (!$post_id || !is_user_logged_in()) {
        error_log('koala-utils: save_koala_release_location - invalid post ID or not logged in');
        wp_send_json_error('Not logged in');
    }
    if (!function_exists('update_field')) {
        error_log('koala-utils: save_koala_release_location - ACF update_field not available');
        wp_send_json_error('ACF not active');
    }

    // Get and sanitize submitted fields
    $address   = sanitize_text_field($_POST['address']);
    $latitude  = round(floatval($_POST['latitude']), 6);
    $longitude = round(floatval($_POST['longitude']), 6);
    $town      = sanitize_text_field($_POST['town']);
    $postcode  = sanitize_text_field($_POST['postcode']);

    // Save fields
    update_field('field_67a94211a55ea', $address, $post_id); // release_address
    update_field('release_lat', $latitude, $post_id);
    update_field('release_long', $longitude, $post_id);
    update_field('release_town', $town, $post_id);
    update_field('release_post_code', $postcode, $post_id);

    // ✅ Clean street-only address (ACF key: field_68589b4bbcfa3)
    $street_address = strpos($address, ',') !== false ? trim(explode(',', $address)[0]) : trim($address);
    update_field('field_68589b4bbcfa3', $street_address, $post_id); // release_street_address

    // ✅ Save location confirmed by releaser (ACF key: field_69c315f087f2f)
    // ACF checkbox requires array of selected values, or empty array to uncheck
    $location_confirmed = (isset($_POST['location_confirmed']) && $_POST['location_confirmed'] === '1') ? ['1'] : [];
    update_field('field_69c315f087f2f', $location_confirmed, $post_id);

    delete_transient('koala_release_' . $post_id);
    wp_cache_delete($post_id, 'post_meta');
    wp_send_json_success('Location saved');
});

// Clear front-end transient cache when a koala post is saved via wp-admin (including ACF fields)
add_action('acf/save_post', function ($post_id) {
    if (get_post_type($post_id) !== 'koala') {
        return;
    }
    delete_transient('koala_location_' . $post_id);
    delete_transient('koala_release_' . $post_id);
    wp_cache_delete($post_id, 'post_meta');
});
