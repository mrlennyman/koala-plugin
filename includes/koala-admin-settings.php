<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin settings page for Koala CPT Plugin.
 * Exposes the Google Maps API key and the koala_sighting_form_settings
 * option (already consumed by public-sighting-form.php and
 * koala-sighting-form.php) through an accessible wp-admin UI.
 */

add_action('admin_menu', function () {
    add_options_page(
        __('Koala Plugin Settings', 'koala-plugin'),
        __('Koala Plugin', 'koala-plugin'),
        'manage_options',
        'koala-plugin-settings',
        'koala_plugin_render_settings_page'
    );
});

add_action('admin_init', function () {
    register_setting('koala_plugin_settings_group', 'koala_maps_api_key', [
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => '',
    ]);

    register_setting('koala_plugin_settings_group', 'koala_sighting_form_settings', [
        'type' => 'array',
        'sanitize_callback' => 'koala_plugin_sanitize_sighting_form_settings',
        'default' => [],
    ]);

    // Google Maps API section
    add_settings_section(
        'koala_maps_api_section',
        __('Google Maps API', 'koala-plugin'),
        function () {
            echo '<p id="koala_maps_api_section_desc">' . esc_html__('Required for the koala location, release, and sighting maps to load.', 'koala-plugin') . '</p>';
        },
        'koala-plugin-settings'
    );

    add_settings_field(
        'koala_maps_api_key',
        '<label for="koala_maps_api_key">' . esc_html__('Google Maps API Key', 'koala-plugin') . '</label>',
        function () {
            $value = get_option('koala_maps_api_key', '');
            echo '<input type="text" id="koala_maps_api_key" name="koala_maps_api_key" value="' . esc_attr($value) . '" class="regular-text" autocomplete="off" aria-describedby="koala_maps_api_key_desc" />';
            echo '<p id="koala_maps_api_key_desc" class="description">' . esc_html__('Your Google Maps JavaScript API key (Places library enabled). Stored as a site option, not hardcoded in plugin files.', 'koala-plugin') . '</p>';
            if ('' === $value) {
                echo '<p class="description" style="color:#b32d2e;">' . esc_html__('No API key set — maps will not load until one is entered.', 'koala-plugin') . '</p>';
            }
        },
        'koala-plugin-settings',
        'koala_maps_api_section'
    );

    // Map defaults section
    add_settings_section(
        'koala_map_defaults_section',
        __('Public Sighting Map Defaults', 'koala-plugin'),
        function () {
            echo '<p id="koala_map_defaults_section_desc">' . esc_html__('Default map center and boundary box used by the public sighting form.', 'koala-plugin') . '</p>';
        },
        'koala-plugin-settings'
    );

    // Mirrors the fallback defaults in public-sighting-form.php / koala-sighting-form.php,
    // so the settings page shows the values actually in effect rather than blank fields.
    $coord_fields = [
        'default_latitude' => [__('Default Latitude', 'koala-plugin'), '-28.8136'],
        'default_longitude' => [__('Default Longitude', 'koala-plugin'), '153.2773'],
        'nsw_bounds_north' => [__('Bounds: North', 'koala-plugin'), '-28.0'],
        'nsw_bounds_south' => [__('Bounds: South', 'koala-plugin'), '-37.5'],
        'nsw_bounds_east' => [__('Bounds: East', 'koala-plugin'), '154.0'],
        'nsw_bounds_west' => [__('Bounds: West', 'koala-plugin'), '141.0'],
    ];

    foreach ($coord_fields as $key => list($label, $default_value)) {
        add_settings_field(
            'koala_sighting_form_settings_' . $key,
            '<label for="koala_sighting_form_settings_' . esc_attr($key) . '">' . esc_html($label) . '</label>',
            function () use ($key, $default_value) {
                $settings = get_option('koala_sighting_form_settings', []);
                $value = $settings[$key] ?? $default_value;
                echo '<input type="text" inputmode="decimal" id="koala_sighting_form_settings_' . esc_attr($key) . '" name="koala_sighting_form_settings[' . esc_attr($key) . ']" value="' . esc_attr($value) . '" class="small-text" />';
            },
            'koala-plugin-settings',
            'koala_map_defaults_section'
        );
    }

    // Field labels section
    add_settings_section(
        'koala_field_labels_section',
        __('Public Sighting Form: Field Labels', 'koala-plugin'),
        function () {
            echo '<p id="koala_field_labels_section_desc">' . esc_html__('Customize the visible label text for each field on the public sighting form.', 'koala-plugin') . '</p>';
        },
        'koala-plugin-settings'
    );

    $default_labels = [
        'first_name' => 'First Name',
        'last_name' => 'Last Name',
        'email' => 'Email',
        'phone' => 'Phone',
        'date_sighting' => 'Date of Sighting',
        'time_sighting' => 'Time of Sighting',
        'koala_address' => 'Koala Address',
        'where_found' => 'Where Found',
        'life_stage' => 'Life Stage',
        'animal_condition' => 'Animal Condition',
        'sex' => 'Sex',
        'pouch_condition' => 'Does the koala have young?',
        'fate' => 'Is the koala alive or dead?',
        'koala_notes' => 'Please tell us all you can about the koala',
    ];

    foreach ($default_labels as $key => $default_label) {
        add_settings_field(
            'koala_sighting_form_settings_label_' . $key,
            '<label for="koala_sighting_form_settings_label_' . esc_attr($key) . '">' . esc_html($default_label) . '</label>',
            function () use ($key, $default_label) {
                $settings = get_option('koala_sighting_form_settings', []);
                $value = $settings['field_labels'][$key] ?? $default_label;
                echo '<input type="text" id="koala_sighting_form_settings_label_' . esc_attr($key) . '" name="koala_sighting_form_settings[field_labels][' . esc_attr($key) . ']" value="' . esc_attr($value) . '" class="regular-text" />';
            },
            'koala-plugin-settings',
            'koala_field_labels_section'
        );
    }

    // Field toggles section
    add_settings_section(
        'koala_field_toggles_section',
        __('Public Sighting Form: Field Visibility', 'koala-plugin'),
        function () {
            echo '<p id="koala_field_toggles_section_desc">' . esc_html__('Control which optional fields appear on the public sighting form.', 'koala-plugin') . '</p>';
        },
        'koala-plugin-settings'
    );

    add_settings_field(
        'koala_sighting_form_settings_field_enabled_phone',
        '<label for="koala_sighting_form_settings_field_enabled_phone">' . esc_html__('Show Phone Field', 'koala-plugin') . '</label>',
        function () {
            $settings = get_option('koala_sighting_form_settings', []);
            $checked = $settings['field_enabled']['phone'] ?? true;
            echo '<input type="checkbox" id="koala_sighting_form_settings_field_enabled_phone" name="koala_sighting_form_settings[field_enabled][phone]" value="1" ' . checked($checked, true, false) . ' aria-describedby="koala_field_enabled_phone_desc" />';
            echo '<p id="koala_field_enabled_phone_desc" class="description">' . esc_html__('When enabled, the phone field is required and validated as a 10-digit number.', 'koala-plugin') . '</p>';
        },
        'koala-plugin-settings',
        'koala_field_toggles_section'
    );
});

function koala_plugin_sanitize_sighting_form_settings($input) {
    $output = [];
    $numeric_keys = ['default_latitude', 'default_longitude', 'nsw_bounds_north', 'nsw_bounds_south', 'nsw_bounds_east', 'nsw_bounds_west'];

    foreach ($numeric_keys as $key) {
        if (isset($input[$key]) && $input[$key] !== '') {
            $output[$key] = (string) floatval($input[$key]);
        }
    }

    if (isset($input['field_labels']) && is_array($input['field_labels'])) {
        $output['field_labels'] = array_map('sanitize_text_field', $input['field_labels']);
    }

    $output['field_enabled'] = [
        'phone' => !empty($input['field_enabled']['phone']),
        'koala_notes' => true,
    ];

    return $output;
}

add_action('admin_notices', function () {
    if (!current_user_can('manage_options') || '' !== get_option('koala_maps_api_key', '')) {
        return;
    }
    $screen = get_current_screen();
    if ($screen && 'settings_page_koala-plugin-settings' === $screen->id) {
        return;
    }
    printf(
        '<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
        esc_html__('Koala Plugin: no Google Maps API key is set, so koala maps will not load.', 'koala-plugin'),
        esc_url(admin_url('options-general.php?page=koala-plugin-settings')),
        esc_html__('Add one now', 'koala-plugin')
    );
});

function koala_plugin_render_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form action="options.php" method="post">
            <?php
            settings_fields('koala_plugin_settings_group');
            do_settings_sections('koala-plugin-settings');
            submit_button(__('Save Settings', 'koala-plugin'));
            ?>
        </form>
    </div>
    <?php
}
