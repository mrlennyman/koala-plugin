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

const KOALA_LOC_INCOMPLETE_CACHE_KEY = 'koala_loc_incomplete_ids';

function koala_loc_incomplete_keys() {
    return ['town', 'postcode', 'lga', 'latitude', 'longitude'];
}

// IDs of koalas where any location field is missing or blank. One lean query on wp_posts with a
// NOT EXISTS subquery per field (instead of ~10 LEFT JOINs), cached for 10 minutes.
function koala_loc_incomplete_ids() {
    $cached = get_transient(KOALA_LOC_INCOMPLETE_CACHE_KEY);
    if (is_array($cached)) {
        return $cached;
    }

    global $wpdb;
    $statuses     = koala_loc_list_statuses();
    $placeholders = implode(',', array_fill(0, count($statuses), '%s'));
    $args         = array_merge(['koala'], $statuses);
    $conditions   = [];
    foreach (koala_loc_incomplete_keys() as $key) {
        $conditions[] = "NOT EXISTS (SELECT 1 FROM {$wpdb->postmeta} pm WHERE pm.post_id = p.ID AND pm.meta_key = %s AND pm.meta_value <> '')";
        $args[]       = $key;
    }
    $sql = "SELECT p.ID FROM {$wpdb->posts} p WHERE p.post_type = %s AND p.post_status IN ($placeholders) AND (" . implode(' OR ', $conditions) . ')';

    $ids = array_map('intval', (array) $wpdb->get_col($wpdb->prepare($sql, $args)));
    set_transient(KOALA_LOC_INCOMPLETE_CACHE_KEY, $ids, 10 * MINUTE_IN_SECONDS);
    return $ids;
}

function koala_loc_incomplete_count() {
    return count(koala_loc_incomplete_ids());
}

function koala_loc_incomplete_clear() {
    delete_transient(KOALA_LOC_INCOMPLETE_CACHE_KEY);
}

// Refresh the count when a koala, or one of the location fields, changes
add_action('save_post_koala', 'koala_loc_incomplete_clear');
add_action('deleted_post', 'koala_loc_incomplete_clear');   // the post is already gone, so its type cannot be checked
foreach (['trashed_post', 'untrashed_post'] as $koala_loc_incomplete_hook) {
    add_action($koala_loc_incomplete_hook, function ($post_id) {
        if (get_post_type($post_id) === 'koala') {
            koala_loc_incomplete_clear();
        }
    });
}
foreach (['added_post_meta', 'updated_post_meta', 'deleted_post_meta'] as $koala_loc_incomplete_hook) {
    add_action($koala_loc_incomplete_hook, function ($meta_id, $post_id, $meta_key) {
        if (in_array($meta_key, koala_loc_incomplete_keys(), true)) {
            koala_loc_incomplete_clear();
        }
    }, 10, 3);
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
    $ids      = koala_loc_incomplete_ids();
    $existing = $query->get('post__in');
    if (!empty($existing)) {
        $ids = array_values(array_intersect($ids, array_map('intval', (array) $existing)));
    }
    $query->set('post__in', $ids ? $ids : [0]);
});
