<?php
/**
 * Settings page class - FIXED ENCODING
 */

if (!defined('ABSPATH')) {
    exit;
}

class BESM_Admin_Settings
{

    private $post_handler;
    private $settings;

    public function __construct()
    {
        $this->post_handler = new BESM_Post_Handler();
        $this->settings = get_option('besm_settings', array());
    }

    // Render settings page
    public function render()
    {
        // Handle form submission
        if (isset($_POST['besm_save_settings']) && check_admin_referer('besm_settings_action', 'besm_settings_nonce')) {
            $this->save_settings();
        }

        // Get all post types
        $post_types = $this->post_handler->get_all_post_types();
        $active_post_type = isset($this->settings['active_post_type']) ? $this->settings['active_post_type'] : '';
        $enabled_fields = isset($this->settings['enabled_fields']) ? $this->settings['enabled_fields'] : array();
        $per_page = isset($this->settings['items_per_page']) ? $this->settings['items_per_page'] : 50;

        // Get available fields for active post type
        $available_fields = array();
        if ($active_post_type) {
            $available_fields = $this->post_handler->get_available_fields($active_post_type);
        }

        // Include view
        include BESM_PLUGIN_DIR . 'admin/view-settings.php';
    }

    // Save settings
    private function save_settings()
    {
        // FIXED: Security check with proper message
        if (!current_user_can('manage_options')) {
            wp_die(
                esc_html__('دسترسی غیرمجاز', 'bulk-edit-seo'),
                esc_html__('خطا', 'bulk-edit-seo'),
                array('response' => 403)
            );
        }

        $post_type = isset($_POST['active_post_type']) ? sanitize_text_field(wp_unslash($_POST['active_post_type'])) : '';
        $enabled_fields = isset($_POST['enabled_fields']) && is_array($_POST['enabled_fields']) ? array_map('sanitize_text_field', wp_unslash($_POST['enabled_fields'])) : array();
        $per_page = isset($_POST['items_per_page']) ? intval($_POST['items_per_page']) : 50;

        if (empty($post_type)) {
            add_settings_error('besm_settings', 'post_type_required', __('لطفاً یک نوع پست انتخاب کنید', 'bulk-edit-seo'), 'error');
            return;
        }

        $settings = array(
            'active_post_type' => $post_type,
            'enabled_fields' => $enabled_fields,
            'items_per_page' => $per_page
        );

        update_option('besm_settings', $settings);
        $this->settings = $settings;

        add_settings_error('besm_settings', 'settings_saved', __('تنظیمات با موفقیت ذخیره شد', 'bulk-edit-seo'), 'success');
    }

    // Get field label in Persian - FIXED ENCODING
    public function get_field_label($field_key)
    {
        $labels = array(
            'title' => __('عنوان', 'bulk-edit-seo'),
            'content' => __('محتوا', 'bulk-edit-seo'),
            'excerpt' => __('خلاصه', 'bulk-edit-seo'),
            'featured_image' => __('تصویر شاخص', 'bulk-edit-seo'),
            'gallery' => __('گالری تصاویر', 'bulk-edit-seo'),
            'url' => __('پیوند یکتا (URL)', 'bulk-edit-seo'),
            'slug' => __('نامک', 'bulk-edit-seo'),
            'post_status' => __('وضعیت انتشار', 'bulk-edit-seo'),
            'categories' => __('دسته‌بندی‌ها', 'bulk-edit-seo'),
            'tags' => __('برچسب‌ها', 'bulk-edit-seo'),
            'product_type' => __('نوع محصول', 'bulk-edit-seo'),
            'regular_price' => __('قیمت', 'bulk-edit-seo'),
            'sale_price' => __('قیمت فروش ویژه', 'bulk-edit-seo'),
            'sku' => __('شناسه محصول (SKU)', 'bulk-edit-seo'),
            'stock_status' => __('وضعیت موجودی', 'bulk-edit-seo'),
            'manage_stock' => __('مدیریت موجودی', 'bulk-edit-seo'),
            'stock_quantity' => __('تعداد موجودی', 'bulk-edit-seo'),
            'product_attributes' => __('ویژگی‌های محصول', 'bulk-edit-seo'),
            'short_description' => __('توضیحات کوتاه', 'bulk-edit-seo'),
            'yoast_title' => __('عنوان سئو (Yoast)', 'bulk-edit-seo'),
            'yoast_description' => __('توضیحات سئو (Yoast)', 'bulk-edit-seo'),
            'yoast_focus_keyword' => __('کلمه کلیدی (Yoast)', 'bulk-edit-seo'),
            'yoast_canonical' => __('لینک کانونیکال (Yoast)', 'bulk-edit-seo'),
            'rankmath_title' => __('عنوان سئو (Rank Math)', 'bulk-edit-seo'),
            'rankmath_description' => __('توضیحات سئو (Rank Math)', 'bulk-edit-seo'),
            'rankmath_focus_keyword' => __('کلمه کلیدی (Rank Math)', 'bulk-edit-seo'),
            'rankmath_canonical' => __('لینک کانونیکال (Rank Math)', 'bulk-edit-seo'),
            'seopress_title' => __('عنوان سئو (SEOPress)', 'bulk-edit-seo'),
            'seopress_description' => __('توضیحات سئو (SEOPress)', 'bulk-edit-seo'),
            'seopress_focus_keyword' => __('کلمه کلیدی (SEOPress)', 'bulk-edit-seo'),
            'seopress_canonical' => __('لینک کانونیکال (SEOPress)', 'bulk-edit-seo'),
            'og_title' => __('عنوان OpenGraph', 'bulk-edit-seo'),
            'og_description' => __('توضیحات OpenGraph', 'bulk-edit-seo'),
            'twitter_title' => __('عنوان توییتر', 'bulk-edit-seo'),
            'twitter_description' => __('توضیحات توییتر', 'bulk-edit-seo'),
            'robots_noindex' => __('No Index (عدم نمایش در موتورهای جستجو)', 'bulk-edit-seo'),
            'robots_nofollow' => __('No Follow (عدم دنبال کردن لینک‌ها)', 'bulk-edit-seo'),
            'product_cat' => __('دسته‌بندی محصول', 'bulk-edit-seo'),
            'product_tag' => __('برچسب محصول', 'bulk-edit-seo')
        );

        return isset($labels[$field_key]) ? $labels[$field_key] : $field_key;
    }
}