<?php
/**
 * Settings page view
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap besm-settings-wrap">
    <div class="besm-header">
        <h1>⚙️ <?php esc_html_e('تنظیمات ویرایش گروهی پست تایپ ها', 'bulk-edit-seo'); ?></h1>
        <p class="besm-subtitle"><?php esc_html_e('برای شروع، ابتدا نوع پست و فیلدهای مورد نظر خود را انتخاب کنید', 'bulk-edit-seo'); ?></p>
    </div>

    <?php settings_errors('besm_settings'); ?>

    <div class="besm-settings-container">
        <form method="post" action="" id="besm-settings-form">
            <?php wp_nonce_field('besm_settings_action', 'besm_settings_nonce'); ?>

            <!-- Post Type Selection -->
            <div class="besm-settings-card">
                <div class="besm-card-header">
                    <span class="besm-card-icon">📦</span>
                    <h2><?php esc_html_e('انتخاب نوع پست', 'bulk-edit-seo'); ?></h2>
                </div>
                <p class="besm-card-description"><?php esc_html_e('یک نوع پست را برای ویرایش گروهی انتخاب کنید. در هر زمان فقط با یک نوع پست می‌توانید کار کنید.', 'bulk-edit-seo'); ?></p>

                <div class="besm-post-types-grid">
                    <?php foreach ($post_types as $pt_slug => $pt_name): ?>
                        <label class="besm-post-type-card <?php echo $active_post_type === $pt_slug ? 'active' : ''; ?>">
                            <input type="radio"
                                   name="active_post_type"
                                   value="<?php echo esc_attr($pt_slug); ?>"
                                    <?php checked($active_post_type, $pt_slug); ?>
                                   required>
                            <span class="besm-radio-custom"></span>
                            <span class="besm-post-type-name"><?php echo esc_html($pt_name); ?></span>
                            <span class="besm-post-type-slug"><?php echo esc_html($pt_slug); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Fields Selection -->
            <div class="besm-settings-card" id="fields-section"
                 style="<?php echo empty($active_post_type) ? 'display:none;' : ''; ?>">
                <div class="besm-card-header">
                    <span class="besm-card-icon">✅</span>
                    <h2><?php esc_html_e('انتخاب فیلدها', 'bulk-edit-seo'); ?></h2>
                </div>
                <p class="besm-card-description"><?php esc_html_e('فیلدهایی که می‌خواهید در لیست ویرایش نمایش داده شوند را انتخاب کنید.', 'bulk-edit-seo'); ?></p>

                <div class="besm-fields-grid" id="available-fields">
                    <?php if (!empty($available_fields)): ?>
                        <?php foreach ($available_fields as $field_key => $field_data): ?>
                            <label class="besm-field-card <?php echo in_array($field_key, $enabled_fields) ? 'active' : ''; ?>">
                                <input type="checkbox"
                                       name="enabled_fields[]"
                                       value="<?php echo esc_attr($field_key); ?>"
                                        <?php checked(in_array($field_key, $enabled_fields)); ?>>
                                <span class="besm-checkbox-custom">
                                    <svg width="12" height="10" viewBox="0 0 12 10" fill="none">
                                        <path d="M1 5L4.5 8.5L11 1.5" stroke="white" stroke-width="2"
                                              stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </span>
                                <div class="besm-field-info">
                                    <span class="besm-field-label"><?php echo esc_html($this->get_field_label($field_key)); ?></span>
                                    <?php if (isset($field_data['type'])): ?>
                                        <span class="besm-field-type"><?php echo esc_html($field_data['type']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="besm-no-fields">
                            <span class="dashicons dashicons-info"></span>
                            <p><?php esc_html_e('ابتدا یک نوع پست انتخاب کنید.', 'bulk-edit-seo'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Display Settings -->
            <div class="besm-settings-card">
                <div class="besm-card-header">
                    <span class="besm-card-icon">🎯</span>
                    <h2><?php esc_html_e('تنظیمات نمایش', 'bulk-edit-seo'); ?></h2>
                </div>

                <div class="besm-display-settings">
                    <label class="besm-setting-item">
                        <span class="besm-setting-label"><?php esc_html_e('تعداد نمایش در هر صفحه:', 'bulk-edit-seo'); ?></span>
                        <select name="items_per_page" class="besm-select">
                            <option value="25" <?php selected($per_page, 25); ?>>25</option>
                            <option value="50" <?php selected($per_page, 50); ?>>50</option>
                            <option value="100" <?php selected($per_page, 100); ?>>100</option>
                            <option value="200" <?php selected($per_page, 200); ?>>200</option>
                        </select>
                    </label>
                </div>
            </div>

            <!-- Save Button -->
            <div class="besm-settings-actions">
                <button type="submit" name="besm_save_settings" class="besm-btn besm-btn-primary">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php esc_html_e('ذخیره تنظیمات و تایید', 'bulk-edit-seo'); ?>
                </button>

                <?php if (!empty($active_post_type)): ?>
                    <a href="<?php echo admin_url('admin.php?page=besm-bulk-editor'); ?>"
                       class="besm-btn besm-btn-secondary">
                        <span class="dashicons dashicons-edit-large"></span>
                        <?php esc_html_e('رفتن به ویرایشگر', 'bulk-edit-seo'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php /* Settings styles are in assets/css/admin.css — no inline <style> needed */ ?>