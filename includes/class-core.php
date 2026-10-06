<?php
/**
 * Core initialization class
 */

if (!defined('ABSPATH')) {
    exit;
}

class BESM_Core
{

    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init()
    {
        // Load text domain
        add_action('init', array($this, 'load_textdomain'));

        // Admin hooks
        if (is_admin()) {
            add_action('admin_menu', array($this, 'register_menus'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));

            // FIXED: Initialize AJAX handler
            $this->init_ajax_handler();
        }
    }

    // FIXED: Initialize AJAX handler
    private function init_ajax_handler()
    {
        new BESM_Ajax();
    }

    public function load_textdomain()
    {
        load_plugin_textdomain(
            'bulk-edit-seo',
            false,
            dirname(BESM_PLUGIN_BASENAME) . '/languages'
        );
    }

    public function register_menus()
    {
        // Main menu - Settings as first page
        add_menu_page(
            __('ویرایش گروهی پست تایپ ها', 'bulk-edit-seo'),
            __('ویرایش گروهی پست تایپ ها', 'bulk-edit-seo'),
            'manage_options',
            'besm-settings',
            array($this, 'render_settings_page'),
            'dashicons-admin-settings',
            30
        );

        // Editor submenu
        add_submenu_page(
            'besm-settings',
            __('ویرایشگر گروهی', 'bulk-edit-seo'),
            __('ویرایشگر گروهی', 'bulk-edit-seo'),
            'manage_options',
            'besm-bulk-editor',
            array($this, 'render_editor_page')
        );

        // Rename first submenu to match parent
        global $submenu;
        if (isset($submenu['besm-settings'][0])) {
            $submenu['besm-settings'][0][0] = __('تنظیمات', 'bulk-edit-seo');
        }
    }

    public function render_editor_page()
    {
        if (!class_exists('BESM_Admin_Editor')) {
            require_once BESM_PLUGIN_DIR . 'admin/class-editor.php';
        }
        $editor = new BESM_Admin_Editor();
        $editor->render();
    }

    public function render_settings_page()
    {
        if (!class_exists('BESM_Admin_Settings')) {
            require_once BESM_PLUGIN_DIR . 'admin/class-settings.php';
        }
        $settings = new BESM_Admin_Settings();
        $settings->render();
    }

    public function enqueue_assets($hook)
    {
        // Only load on plugin pages
        if (strpos($hook, 'besm-') === false) {
            return;
        }

        // CSS
        wp_enqueue_style(
            'besm-admin-style',
            BESM_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            BESM_VERSION
        );

        // Additional table styles
        wp_enqueue_style(
            'besm-table-style',
            BESM_PLUGIN_URL . 'assets/css/table-styles.css',
            array('besm-admin-style'),
            BESM_VERSION
        );

        // JS based on page
        if (strpos($hook, 'besm-settings') !== false) {
            wp_enqueue_script(
                'besm-settings-script',
                BESM_PLUGIN_URL . 'assets/js/settings.js',
                array('jquery'),
                BESM_VERSION,
                true
            );

            wp_localize_script('besm-settings-script', 'besmSettings', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('besm_settings_nonce'),
                'strings' => array(
                    'saving' => __('در حال ذخیره...', 'bulk-edit-seo'),
                    'saved' => __('ذخیره شد', 'bulk-edit-seo'),
                    'error' => __('خطا در ذخیره‌سازی', 'bulk-edit-seo'),
                    'loadingFields' => __('در حال بارگذاری فیلدها...', 'bulk-edit-seo'),
                    'noFieldsFound' => __('فیلدی برای این نوع پست یافت نشد.', 'bulk-edit-seo'),
                    'loadError' => __('خطا در بارگذاری فیلدها. لطفاً دوباره تلاش کنید.', 'bulk-edit-seo')
                )
            ));
        } elseif (strpos($hook, 'besm-bulk-editor') !== false) {
            // Media uploader
            wp_enqueue_media();

            wp_enqueue_script(
                'besm-editor-script',
                BESM_PLUGIN_URL . 'assets/js/editor.js',
                array('jquery', 'jquery-ui-sortable'),
                BESM_VERSION,
                true
            );

            wp_localize_script('besm-editor-script', 'besmEditor', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('besm_editor_nonce'),
                'exportUrl' => admin_url('admin-post.php'),
                'exportNonce' => wp_create_nonce('besm_export_csv'),
                'strings' => array(
                    'saving' => __('در حال ذخیره‌سازی...', 'bulk-edit-seo'),
                    'saved' => __('تغییرات ذخیره شد', 'bulk-edit-seo'),
                    'error' => __('خطا در ذخیره‌سازی', 'bulk-edit-seo'),
                    'selectImage' => __('انتخاب تصویر', 'bulk-edit-seo'),
                    'useImage' => __('استفاده از این تصویر', 'bulk-edit-seo'),
                    'removeImage' => __('حذف تصویر', 'bulk-edit-seo'),
                    'confirmDelete' => __('آیا مطمئن هستید؟', 'bulk-edit-seo'),
                    'importConfirm' => __('فایل CSV روی آیتم‌های موجود (بر اساس ستون ID) اعمال می‌شود. ادامه می‌دهید؟', 'bulk-edit-seo'),
                    'importZipConfirm' => __('بسته‌ی ZIP وارد می‌شود: تصاویر در کتابخانه رسانه ساخته و به پست‌ها (بر اساس ستون ID) لینک می‌شوند. ادامه می‌دهید؟', 'bulk-edit-seo'),
                    'importing' => __('در حال ورود CSV...', 'bulk-edit-seo'),
                    'importingZip' => __('در حال ورود بسته و ساخت تصاویر...', 'bulk-edit-seo'),
                    'preparingExport' => __('در حال آماده‌سازی خروجی...', 'bulk-edit-seo'),
                    'frEmpty' => __('عبارت جستجو را وارد کنید.', 'bulk-edit-seo'),
                    'frDone' => __('جایگزینی انجام شد — تعداد:', 'bulk-edit-seo'),
                    'fillEmpty' => __('یک ستون و مقدار را انتخاب کنید.', 'bulk-edit-seo'),
                    'running' => __('در حال اجرا...', 'bulk-edit-seo'),
                    'actionError' => __('خطا در اجرای عملیات', 'bulk-edit-seo'),
                    'copyFilename' => __('کپی نام فایل', 'bulk-edit-seo'),
                    'copy' => __('کپی', 'bulk-edit-seo'),
                    'altPlaceholder' => __('متن جایگزین (Alt)', 'bulk-edit-seo'),
                    'selectGalleryImages' => __('انتخاب تصاویر گالری', 'bulk-edit-seo'),
                    'addToGallery' => __('افزودن به گالری', 'bulk-edit-seo'),
                    'noChanges' => __('هیچ تغییری برای ذخیره وجود ندارد.', 'bulk-edit-seo'),
                    'saveConfirm' => __('آیا از ذخیره %d مورد تغییر یافته اطمینان دارید؟', 'bulk-edit-seo'),
                    'bulkActionConfirm' => __('آیا از اعمال این عملیات روی %d مورد اطمینان دارید؟', 'bulk-edit-seo'),
                    'selectActionItem' => __('لطفاً یک عملیات و حداقل یک مورد انتخاب کنید', 'bulk-edit-seo'),
                    'saveError' => __('خطا در ذخیره', 'bulk-edit-seo'),
                    'errorShort' => __('خطا', 'bulk-edit-seo'),
                    'unsavedWarning' => __('شما %d تغییر ذخیره نشده دارید. آیا مطمئن هستید؟', 'bulk-edit-seo'),
                    'copyError' => __('خطا در کپی کردن. لطفاً دستی کپی کنید:', 'bulk-edit-seo')
                )
            ));
        }
    }
}