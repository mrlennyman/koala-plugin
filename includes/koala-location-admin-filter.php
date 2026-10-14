<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Koalas list (wp-admin): a "Location incomplete" view with a count, so staff can find and clear
 * gaps (town, postcode, LGA, latitude or longitude blank or missing) without waiting for a report.
 */

// Records where any of the location fields is missing or blank
function koala_loc_incomplete_meta_query() {
    $query = ['relation' => 'OR'];
    foreach (['town', 'postcode', 'lga', 'latitude', 'longitude'] as $key) {
        $query[] = [
            'relation' => 'OR',
            ['key' => $key, 'compare' => 'NOT EXISTS'],
            ['key' => $key, 'value' => '', 'compare' => '='],
        ];
    }
    return $query;
}

// The same statuses the list's "All" view covers (so trash and auto-drafts are not counted)
function koala_loc_list_statuses() {
    $statuses = array_values(get_post_stati(['show_in_admin_all_list' => true]));
    return $statuses ? $statuses : ['publish', 'draft', 'pending', 'private', 'future'];
}

function koala_loc_incomplete_count() {
    $query = new WP_Query([
        'post_type'      => 'koala',
        'post_status'    => koala_loc_list_statuses(),
        'fields'         => 'ids',
        'posts_per_page' => 1,
        'no_found_rows'  => false,
        'meta_query'     => koala_loc_incomplete_meta_query(),
    ]);
    return (int) $query->found_posts;
}

function koala_loc_filter_active() {
    return isset($_GET['koala_location']) && $_GET['koala_location'] === 'incomplete';
}

add_filter('views_edit-koala', function ($views) {
    $active = koala_loc_filter_active();
    if ($active) {
        // Only one view is current; WordPress does not know about this query argument
        foreach ($views as $key => $view) {
            $views[$key] = str_replace([' class="current"', ' aria-current="page"'], '', $view);
        }
    }
    $url = add_query_arg(['post_type' => 'koala', 'koala_location' => 'incomplete'], admin_url('edit.php'));
    $views['koala_location_incomplete'] = sprintf(
        '<a href="%s"%s>%s <span class="count">(%s)</span></a>',
        esc_url($url),
        $active ? ' class="current" aria-current="page"' : '',
        esc_html__('Location incomplete', 'koala-plugin'),
        esc_html(number_format_i18n(koala_loc_incomplete_count()))
    );
    return $views;
});

add_action('pre_get_posts', function ($query) {
    global $pagenow;
    if (!is_admin() || $pagenow !== 'edit.php' || !$query->is_main_query() || $query->get('post_type') !== 'koala' || !koala_loc_filter_active()) {
        return;
    }
    $ours     = koala_loc_incomplete_meta_query();
    $existing = $query->get('meta_query');
    $query->set('meta_query', $existing ? ['relation' => 'AND', $existing, $ours] : $ours);
});
