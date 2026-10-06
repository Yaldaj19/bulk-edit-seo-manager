<?php
/**
 * AJAX handler class - FIXED
 */

if (!defined('ABSPATH')) {
    exit;
}

class BESM_Ajax
{

    public function __construct()
    {
        // Settings actions
        add_action('wp_ajax_besm_save_settings', array($this, 'save_settings'));
        add_action('wp_ajax_besm_get_post_type_fields', array($this, 'get_post_type_fields'));

        // Editor actions
        add_action('wp_ajax_besm_bulk_save', array($this, 'bulk_save'));
        add_action('wp_ajax_besm_bulk_action', array($this, 'bulk_action'));

        // CSV import / export
        add_action('wp_ajax_besm_import_csv', array($this, 'import_csv'));
        add_action('admin_post_besm_export_csv', array($this, 'export_csv'));

        // Full ZIP bundle (CSV + image files)
        add_action('wp_ajax_besm_import_zip', array($this, 'import_zip'));
        add_action('admin_post_besm_export_zip', array($this, 'export_zip'));
    }

    // Export a full ZIP bundle (CSV + images).
    public function export_zip()
    {
        $migrate = new BESM_Migrate();
        $migrate->export(); // streams + exits
    }

    // Import a full ZIP bundle and bulk-save, side-loading images.
    public function import_zip()
    {
        check_ajax_referer('besm_editor_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('دسترسی غیرمجاز', 'bulk-edit-seo')));
        }

        if (empty($_FILES['zip'])) {
            wp_send_json_error(array('message' => __('فایلی انتخاب نشده است', 'bulk-edit-seo')));
        }

        $migrate = new BESM_Migrate();
        $results = $migrate->import($_FILES['zip']);

        $failed = isset($results['failed_count']) ? (int) $results['failed_count'] : 0;
        $imgs   = isset($results['images']) ? (int) $results['images'] : 0;

        if (!empty($results['success'])) {
            $msg = sprintf(__('%1$d پست به‌روز شد و %2$d تصویر در رسانه ساخته شد.', 'bulk-edit-seo'), $results['saved_count'], $imgs);
            if ($failed > 0) {
                $msg .= ' ' . sprintf(__('%d پست با آن ID در این سایت پیدا نشد (ساخته نشد).', 'bulk-edit-seo'), $failed);
            }
            wp_send_json_success(array(
                'message'      => $msg,
                'saved_count'  => $results['saved_count'],
                'failed_count' => $failed,
                'images'       => $imgs,
            ));
        } else {
            $msg = isset($results['message']) ? $results['message'] : __('خطا در ورود بسته', 'bulk-edit-seo');
            if ($failed > 0) {
                $msg .= ' ' . sprintf(__('(%d پست با آن ID پیدا نشد.)', 'bulk-edit-seo'), $failed);
            }
            wp_send_json_error(array(
                'message' => $msg,
                'errors'  => isset($results['errors']) ? $results['errors'] : array(),
            ));
        }
    }

    // Export the filtered result set as a CSV download.
    public function export_csv()
    {
        $csv = new BESM_CSV();
        $csv->export(); // streams + exits
    }

    // Import a CSV file and bulk-save the rows.
    public function import_csv()
    {
        check_ajax_referer('besm_editor_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('دسترسی غیرمجاز', 'bulk-edit-seo')));
        }

        if (empty($_FILES['csv'])) {
            wp_send_json_error(array('message' => __('فایلی انتخاب نشده است', 'bulk-edit-seo')));
        }

        $csv     = new BESM_CSV();
        $results = $csv->import($_FILES['csv']);

        if (!empty($results['success'])) {
            $msg = sprintf(__('تعداد %d مورد از CSV ذخیره شد', 'bulk-edit-seo'), $results['saved_count']);
            if (!empty($results['failed_count'])) {
                $msg .= ' ' . sprintf(__('%d پست با آن ID پیدا نشد', 'bulk-edit-seo'), (int) $results['failed_count']);
            }
            wp_send_json_success(array(
                'message'      => $msg,
                'saved_count'  => $results['saved_count'],
                'failed_count' => $results['failed_count'],
                'skipped'      => isset($results['skipped']) ? $results['skipped'] : 0,
            ));
        } else {
            wp_send_json_error(array(
                'message' => isset($results['message']) ? $results['message'] : __('خطا در ورود CSV', 'bulk-edit-seo'),
                'errors'  => isset($results['errors']) ? $results['errors'] : array(),
            ));
        }
    }

    // Save settings
    public function save_settings()
    {
        check_ajax_referer('besm_settings_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('دسترسی غیرمجاز', 'bulk-edit-seo')));
        }

        $post_type = isset($_POST['post_type']) ? sanitize_text_field(wp_unslash($_POST['post_type'])) : '';
        $enabled_fields = isset($_POST['enabled_fields']) && is_array($_POST['enabled_fields']) ? array_map('sanitize_text_field', wp_unslash($_POST['enabled_fields'])) : array();
        $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 50;

        if (empty($post_type)) {
            wp_send_json_error(array('message' => __('نوع پست انتخاب نشده است', 'bulk-edit-seo')));
        }

        $settings = array(
            'active_post_type' => $post_type,
            'enabled_fields' => $enabled_fields,
            'items_per_page' => $per_page
        );

        update_option('besm_settings', $settings);

        wp_send_json_success(array(
            'message' => __('تنظیمات با موفقیت ذخیره شد', 'bulk-edit-seo'),
            'settings' => $settings
        ));
    }

    // ✅ FIXED: اضافه کردن enabled_fields به response
    public function get_post_type_fields()
    {
        check_ajax_referer('besm_settings_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('دسترسی غیرمجاز', 'bulk-edit-seo')));
        }

        $post_type = isset($_POST['post_type']) ? sanitize_text_field(wp_unslash($_POST['post_type'])) : '';

        if (empty($post_type)) {
            wp_send_json_error(array('message' => __('نوع پست معتبر نیست', 'bulk-edit-seo')));
        }

        $post_handler = new BESM_Post_Handler();
        $fields = $post_handler->get_available_fields($post_type);

        // ✅ FIXED: گرفتن فیلدهای فعال فعلی
        $settings = get_option('besm_settings', array());
        $enabled_fields = isset($settings['enabled_fields']) ? $settings['enabled_fields'] : array();

        wp_send_json_success(array(
            'fields' => $fields,
            'enabled_fields' => $enabled_fields  // ✅ اضافه شد
        ));
    }

    // Bulk save posts
    public function bulk_save()
    {
        check_ajax_referer('besm_editor_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('دسترسی غیرمجاز', 'bulk-edit-seo')));
        }

        $posts_data = isset($_POST['posts']) && is_array($_POST['posts']) ? wp_unslash($_POST['posts']) : array();

        if (empty($posts_data) || !is_array($posts_data)) {
            wp_send_json_error(array('message' => __('داده‌ای برای ذخیره وجود ندارد', 'bulk-edit-seo')));
        }

        $bulk_save = new BESM_Bulk_Save();
        $results = $bulk_save->save_posts($posts_data);

        if ($results['success']) {
            wp_send_json_success(array(
                'message' => sprintf(__('تعداد %d مورد با موفقیت ذخیره شد', 'bulk-edit-seo'), $results['saved_count']),
                'saved_count' => $results['saved_count'],
                'failed_count' => $results['failed_count']
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('خطا در ذخیره‌سازی', 'bulk-edit-seo'),
                'errors' => $results['errors']
            ));
        }
    }

    public function bulk_action()
    {
        check_ajax_referer('besm_editor_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('دسترسی غیرمجاز', 'bulk-edit-seo')));
        }

        $action = isset($_POST['bulk_action']) ? sanitize_text_field(wp_unslash($_POST['bulk_action'])) : '';

        // "Select all matching" across every page: re-run the filter query server-side.
        if (!empty($_POST['select_all_matching'])) {
            $settings  = get_option('besm_settings', array());
            $post_type = isset($settings['active_post_type']) ? $settings['active_post_type'] : '';

            $qs = isset($_POST['filters_qs']) ? wp_unslash($_POST['filters_qs']) : '';
            $parsed = array();
            parse_str(ltrim($qs, '?'), $parsed);

            $filters = BESM_Filters::sanitize_request_filters($parsed);
            $filters['per_page'] = -1;

            $builder = new BESM_Filters($post_type);
            $args = $builder->build_query_args($filters);
            $args['posts_per_page'] = -1;
            $args['fields'] = 'ids';

            $post_ids = $post_type ? get_posts($args) : array();
        } else {
            $post_ids = isset($_POST['post_ids']) && is_array($_POST['post_ids']) ? array_map('intval', wp_unslash($_POST['post_ids'])) : array();
        }

        if (empty($action) || empty($post_ids)) {
            wp_send_json_error(array('message' => __('عملیات یا آیتم‌ها انتخاب نشده‌اند', 'bulk-edit-seo')));
        }

        $success_count = 0;
        $failed_count = 0;

        foreach ($post_ids as $post_id) {
            if (!current_user_can('edit_post', $post_id)) {
                $failed_count++;
                continue;
            }

            switch ($action) {
                case 'publish':
                case 'draft':
                case 'private':
                case 'pending':
                    $result = wp_update_post(array(
                        'ID' => $post_id,
                        'post_status' => $action
                    ));
                    break;

                case 'trash':
                    if (!current_user_can('delete_post', $post_id)) {
                        $result = false;
                        break;
                    }
                    $result = wp_trash_post($post_id);
                    break;

                default:
                    $result = false;
                    break;
            }

            if ($result) {
                $success_count++;
            } else {
                $failed_count++;
            }
        }

        if ($success_count > 0) {
            wp_send_json_success(array(
                'message' => sprintf(__('عملیات با موفقیت روی %d مورد انجام شد', 'bulk-edit-seo'), $success_count),
                'success_count' => $success_count,
                'failed_count' => $failed_count
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('عملیات انجام نشد', 'bulk-edit-seo'),
                'failed_count' => $failed_count
            ));
        }
    }

}