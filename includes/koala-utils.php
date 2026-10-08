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

    $maps_api_key = koala_plugin_get_maps_key();
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

// Clear front-end transient cache when a koala post is saved via wp-admin (including ACF fields)
add_action('acf/save_post', function ($post_id) {
    if (get_post_type($post_id) !== 'koala') {
        return;
    }
    delete_transient('koala_location_' . $post_id);
    delete_transient('koala_release_' . $post_id);
    wp_cache_delete($post_id, 'post_meta');
});
