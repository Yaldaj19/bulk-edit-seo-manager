<?php
/**
 * Uninstall handler for Bulk Edit SEO Manager.
 *
 * Runs only when the plugin is deleted from the WordPress admin.
 * Removes plugin options so no orphaned data is left behind.
 */

// Exit if not called by WordPress during uninstall.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin settings (single site).
delete_option('besm_settings');

// Multisite: remove the option from every site.
if (is_multisite()) {
    global $wpdb;

    $blog_ids = $wpdb->get_col("SELECT blog_id FROM {$wpdb->blogs}");

    foreach ($blog_ids as $blog_id) {
        switch_to_blog($blog_id);
        delete_option('besm_settings');
        restore_current_blog();
    }
}
