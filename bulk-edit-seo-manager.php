<?php
/**
 * Plugin Name: ویرایش گروهی پست تایپ ها
 * Plugin URI: https://github.com/Yaldaj19/bulk-edit-seo-manager
 * Description: ویرایشگر پیشرفته گروهی برای پست‌ها، محصولات و پست‌تایپ‌های سفارشی با تمرکز بر بهینه‌سازی سئو
 * Version: 1.6.0
 * Author: YJ19
 * Author URI: https://yaldajahanshahi.ir
 * Text Domain: bulk-edit-seo
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('BESM_VERSION', '1.6.0');
define('BESM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BESM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BESM_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'BESM_';
    $base_dir = BESM_PLUGIN_DIR . 'includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . 'class-' . strtolower(str_replace('_', '-', $relative_class)) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Admin classes autoloader
spl_autoload_register(function ($class) {
    $prefix = 'BESM_Admin_';
    $base_dir = BESM_PLUGIN_DIR . 'admin/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . 'class-' . strtolower(str_replace('_', '-', $relative_class)) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Initialize plugin
function besm_init()
{
    if (class_exists('BESM_Core')) {
        $besm = new BESM_Core();
        $besm->init();
    }
}

add_action('plugins_loaded', 'besm_init');

// Activation hook - FIXED: اضافه کردن slug به فیلدهای پیش‌فرض
register_activation_hook(__FILE__, function () {
    // ✅ FIXED: slug اضافه شد، product_tag و url حذف شدند
    $defaults = array(
        'active_post_type' => 'product',
        'items_per_page' => 50,
        'enabled_fields' => array(
            'title',
            'slug',              // ✅ اضافه شد
            'featured_image',
            'gallery',
            'product_cat',
            'regular_price'
        )
    );

    // فقط اگر تنظیمات وجود نداشت اضافه کن
    if (!get_option('besm_settings')) {
        add_option('besm_settings', $defaults);
    } else {
        // ✅ FIXED: اگر تنظیمات قبلی وجود داشت، فیلدهای پیش‌فرض رو آپدیت کن
        $existing = get_option('besm_settings');

        // اگر enabled_fields خالی بود یا نداشت، از defaults استفاده کن
        if (empty($existing['enabled_fields']) || !is_array($existing['enabled_fields'])) {
            $existing['enabled_fields'] = $defaults['enabled_fields'];
            update_option('besm_settings', $existing);
        }
    }

    flush_rewrite_rules();
});

// Deactivation hook
register_deactivation_hook(__FILE__, function () {
    flush_rewrite_rules();
});