<?php
/**
 * Editor page class - FIXED COMPLETE
 */

if (!defined('ABSPATH')) {
    exit;
}

class BESM_Admin_Editor
{

    private $post_handler;
    private $filters;
    private $settings;

    public function __construct()
    {
        $this->post_handler = new BESM_Post_Handler();
        $this->settings = get_option('besm_settings', array());
    }

    // Render editor page
    public function render()
    {
        // Check if post type is selected
        $active_post_type = isset($this->settings['active_post_type']) ? $this->settings['active_post_type'] : '';

        if (empty($active_post_type)) {
            $this->render_no_settings_notice();
            return;
        }

        // Get enabled fields
        $enabled_fields = isset($this->settings['enabled_fields']) ? $this->settings['enabled_fields'] : array();

        if (empty($enabled_fields)) {
            $this->render_no_fields_notice();
            return;
        }

        // Initialize filters
        $this->filters = new BESM_Filters($active_post_type);

        // Get current filters from URL
        $current_filters = $this->get_current_filters();

        // Build query args
        $query_args = $this->filters->build_query_args($current_filters);

        // Get posts
        $posts_data = $this->post_handler->get_posts($active_post_type, $query_args);

        // Get field definitions
        $available_fields = $this->post_handler->get_available_fields($active_post_type);

        // Include view
        include BESM_PLUGIN_DIR . 'admin/view-editor.php';
    }

    // Check if post has variations
    public function post_has_variations($post_id)
    {
        return $this->post_handler->is_variable_product($post_id);
    }

    // Get post variations
    public function get_post_variations($post_id)
    {
        return $this->post_handler->get_product_variations($post_id);
    }

    // ✅ FIXED: Get current filters from URL
    private function get_current_filters()
    {
        $filters = array();

        // Search
        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $filters['search'] = sanitize_text_field(wp_unslash($_GET['search']));
        }

        // ✅ FIXED: Post status - handle 'any' properly
        if (isset($_GET['post_status'])) {
            $filters['post_status'] = sanitize_text_field(wp_unslash($_GET['post_status']));
        } else {
            // Default: any (exclude trash)
            $filters['post_status'] = 'any';
        }

        // Product type
        if (isset($_GET['product_type']) && !empty($_GET['product_type'])) {
            $filters['product_type'] = sanitize_text_field(wp_unslash($_GET['product_type']));
        }

        // ✅ FIXED: Taxonomies - proper handling of arrays
        if (isset($_GET['taxonomies']) && is_array($_GET['taxonomies'])) {
            $filters['taxonomies'] = array();

            foreach ($_GET['taxonomies'] as $taxonomy => $terms) {
                // Handle both single value and array
                if (!empty($terms)) {
                    $sanitized_tax = sanitize_key($taxonomy);

                    if (is_array($terms)) {
                        $filters['taxonomies'][$sanitized_tax] = array_map('intval', array_filter($terms));
                    } else {
                        $filters['taxonomies'][$sanitized_tax] = array(intval($terms));
                    }
                }
            }
        }

        // ✅ FIXED: Meta fields - proper sanitization
        if (isset($_GET['meta']) && is_array($_GET['meta'])) {
            $filters['meta'] = array();

            foreach (wp_unslash($_GET['meta']) as $key => $value) {
                if (is_array($value)) {
                    continue;
                }
                if ($value !== '' && $value !== null) {
                    $sanitized_key = sanitize_key($key);

                    // Detect if numeric
                    if (is_numeric($value)) {
                        $filters['meta'][$sanitized_key] = floatval($value);
                    } else {
                        $filters['meta'][$sanitized_key] = sanitize_text_field($value);
                    }
                }
            }
        }

        // Per page
        if (isset($_GET['per_page'])) {
            $per_page = intval($_GET['per_page']);
            // ✅ FIXED: Allow up to 500
            $filters['per_page'] = min(max($per_page, 10), 500);
        } else {
            $filters['per_page'] = isset($this->settings['items_per_page']) ? $this->settings['items_per_page'] : 50;
        }

        // Paged
        if (isset($_GET['paged'])) {
            $filters['paged'] = max(1, intval($_GET['paged']));
        } else {
            $filters['paged'] = 1;
        }

        // ✅ NEW: Store original URL for reference
        $filters['_original_url'] = add_query_arg(array());

        return $filters;
    }

    // ✅ NEW: Get filter summary
    public function get_filter_summary($current_filters)
    {
        $summary = array();

        if (!empty($current_filters['search'])) {
            $summary[] = __('جستجو:', 'bulk-edit-seo') . ' "' . esc_html($current_filters['search']) . '"';
        }

        if (!empty($current_filters['post_status']) && $current_filters['post_status'] !== 'any') {
            $summary[] = __('وضعیت:', 'bulk-edit-seo') . ' ' . esc_html($current_filters['post_status']);
        }

        if (!empty($current_filters['product_type'])) {
            $summary[] = __('نوع:', 'bulk-edit-seo') . ' ' . esc_html($current_filters['product_type']);
        }

        if (!empty($current_filters['taxonomies'])) {
            $tax_count = 0;
            foreach ($current_filters['taxonomies'] as $terms) {
                $tax_count += is_array($terms) ? count($terms) : 1;
            }
            if ($tax_count > 0) {
                $summary[] = sprintf(__('%d فیلتر تگزونومی', 'bulk-edit-seo'), $tax_count);
            }
        }

        if (!empty($current_filters['meta'])) {
            $meta_count = count($current_filters['meta']);
            $summary[] = sprintf(__('%d فیلتر متافیلد', 'bulk-edit-seo'), $meta_count);
        }

        return implode(' • ', $summary);
    }

    // ✅ NEW: Generate clean filter URL
    public function get_clean_filter_url($filters_to_keep = array())
    {
        $base_url = admin_url('admin.php?page=besm-bulk-editor');

        if (empty($filters_to_keep)) {
            return $base_url;
        }

        return add_query_arg($filters_to_keep, $base_url);
    }

    // Render no settings notice
    private function render_no_settings_notice()
    {
        ?>
        <div class="wrap besm-wrap">
            <h1><?php esc_html_e('ویرایش گروهی پست‌ها', 'bulk-edit-seo'); ?></h1>
            <div class="besm-notice warning">
                <span class="dashicons dashicons-warning"></span>
                <div>
                    <strong><?php esc_html_e('تنظیمات انجام نشده است!', 'bulk-edit-seo'); ?></strong>
                    <p><?php esc_html_e('لطفاً ابتدا به صفحه تنظیمات بروید و یک نوع پست انتخاب کنید.', 'bulk-edit-seo'); ?></p>
                    <a href="<?php echo admin_url('admin.php?page=besm-settings'); ?>" class="button button-primary">
                        <?php esc_html_e('رفتن به تنظیمات', 'bulk-edit-seo'); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
    }

    // Render no fields notice
    private function render_no_fields_notice()
    {
        ?>
        <div class="wrap besm-wrap">
            <h1><?php esc_html_e('ویرایش گروهی پست‌ها', 'bulk-edit-seo'); ?></h1>
            <div class="besm-notice warning">
                <span class="dashicons dashicons-warning"></span>
                <div>
                    <strong><?php esc_html_e('فیلدی انتخاب نشده است!', 'bulk-edit-seo'); ?></strong>
                    <p><?php esc_html_e('لطفاً به صفحه تنظیمات بروید و فیلدهای مورد نظر را انتخاب کنید.', 'bulk-edit-seo'); ?></p>
                    <a href="<?php echo admin_url('admin.php?page=besm-settings'); ?>" class="button button-primary">
                        <?php esc_html_e('رفتن به تنظیمات', 'bulk-edit-seo'); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
    }

    // Render field input
    public function render_field_input($post_id, $field_key, $field_data, $current_value = '')
    {
        $field_type = isset($field_data['type']) ? $field_data['type'] : 'text';
        $field_name = "posts[{$post_id}][{$field_key}]";

        switch ($field_type) {
            case 'text':
                $this->render_text_field($field_name, $current_value, $field_data);
                break;

            case 'textarea':
                $this->render_textarea_field($field_name, $current_value, $field_data);
                break;

            case 'number':
                $this->render_number_field($field_name, $current_value, $field_data);
                break;

            case 'select':
                $this->render_select_field($field_name, $current_value, $field_data, $field_key);
                break;

            case 'checkbox':
                $this->render_checkbox_field($field_name, $current_value, $field_data);
                break;

            case 'image':
                $this->render_image_field($post_id, $field_name, $current_value);
                break;

            case 'gallery':
                $this->render_gallery_field($post_id, $field_name, $current_value);
                break;

            case 'taxonomy':
                $this->render_taxonomy_field($post_id, $field_name, $field_key, $current_value);
                break;

            default:
                $this->render_text_field($field_name, $current_value, $field_data);
                break;
        }
    }

    // Render text field
    private function render_text_field($name, $value, $field_data)
    {
        $readonly = isset($field_data['readonly']) && $field_data['readonly'] ? 'readonly' : '';

        if (strpos($name, '[slug]') !== false) {
            $value = urldecode($value);
        }
        ?>
        <input type="text"
               name="<?php echo esc_attr($name); ?>"
               value="<?php echo esc_attr($value); ?>"
                <?php echo $readonly; ?>>
        <?php
    }

    // Render textarea field
    private function render_textarea_field($name, $value, $field_data)
    {
        ?>
        <textarea name="<?php echo esc_attr($name); ?>" rows="3"><?php echo esc_textarea($value); ?></textarea>
        <?php
    }

    // Render number field
    private function render_number_field($name, $value, $field_data)
    {
        ?>
        <input type="number"
               name="<?php echo esc_attr($name); ?>"
               value="<?php echo esc_attr($value); ?>"
               step="0.01">
        <?php
    }

    // Render select field
    private function render_select_field($name, $value, $field_data, $field_key)
    {
        $options = array();

        if ($field_key === 'product_type') {
            $options = $this->post_handler->get_product_types();
        } elseif ($field_key === 'stock_status') {
            $options = $this->post_handler->get_stock_status_options();
        } elseif ($field_key === 'post_status') {
            $options = $this->post_handler->get_post_status_options();
        } elseif (isset($field_data['options'])) {
            $options = $field_data['options'];
        }

        ?>
        <select name="<?php echo esc_attr($name); ?>">
            <option value=""><?php esc_html_e('-- انتخاب کنید --', 'bulk-edit-seo'); ?></option>
            <?php foreach ($options as $opt_value => $opt_label): ?>
                <option value="<?php echo esc_attr($opt_value); ?>" <?php selected($value, $opt_value); ?>>
                    <?php echo esc_html($opt_label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    // Render checkbox field
    private function render_checkbox_field($name, $value, $field_data)
    {
        $checked = $value === 'yes' || $value === '1' || $value === 1;
        ?>
        <label>
            <input type="checkbox"
                   name="<?php echo esc_attr($name); ?>"
                   value="yes"
                    <?php checked($checked); ?>>
            <?php esc_html_e('فعال', 'bulk-edit-seo'); ?>
        </label>
        <?php
    }

    // Render image field
    private function render_image_field($post_id, $name, $value)
    {
        $image_url = '';
        $attachment_id = 0;
        $alt_text = '';
        $filename = '';

        if (is_array($value)) {
            $attachment_id = isset($value['id']) ? $value['id'] : 0;
            $image_url = isset($value['url']) ? $value['url'] : '';
            $alt_text = isset($value['alt']) ? $value['alt'] : '';
        } elseif (is_numeric($value)) {
            $attachment_id = $value;
            $image_url = wp_get_attachment_image_url($value, 'thumbnail');
            $alt_text = get_post_meta($value, '_wp_attachment_image_alt', true);
        }

        // ✅ گرفتن نام فایل
        if ($attachment_id) {
            $file_path = get_attached_file($attachment_id);
            $filename = $file_path ? basename($file_path) : '';
        }

        $has_image = !empty($image_url);
        ?>
        <div class="besm-image-field">
            <div class="besm-image-preview <?php echo $has_image ? '' : 'no-image'; ?>"
                 data-post-id="<?php echo esc_attr($post_id); ?>"
                 data-type="featured"
                 <?php if ($attachment_id): ?>data-attachment-id="<?php echo esc_attr($attachment_id); ?>"<?php endif; ?>>
                <?php if ($has_image): ?>
                    <img src="<?php echo esc_url($image_url); ?>" alt="">
                    <button type="button" class="besm-image-remove" title="<?php echo esc_attr__('حذف تصویر', 'bulk-edit-seo'); ?>">
                        <span class="dashicons dashicons-no"></span>
                    </button>
                <?php else: ?>
                    <span class="dashicons dashicons-format-image"></span>
                <?php endif; ?>
            </div>

            <?php if ($has_image && $filename): ?>
                <div class="besm-image-filename">
                    <span class="dashicons dashicons-media-default"></span>
                    <span class="besm-filename-text" title="<?php echo esc_attr($filename); ?>">
                    <?php echo esc_html($filename); ?>
                </span>
                    <button type="button" class="besm-copy-filename" data-filename="<?php echo esc_attr($filename); ?>"
                            title="<?php echo esc_attr__('کپی نام فایل', 'bulk-edit-seo'); ?>">
                        <span class="dashicons dashicons-admin-page"></span>
                    </button>
                </div>
            <?php endif; ?>

            <input type="hidden" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($attachment_id); ?>">
            <div class="besm-alt-input">
                <input type="text"
                       name="posts[<?php echo esc_attr($post_id); ?>][featured_image_alt]"
                       value="<?php echo esc_attr($alt_text); ?>"
                       placeholder="<?php echo esc_attr__('متن جایگزین (Alt)', 'bulk-edit-seo'); ?>">
            </div>
        </div>
        <?php
    }

    // Render gallery field
    private function render_gallery_field($post_id, $name, $current_value)
    {
        $gallery_images = array();

        // پردازش درست current_value
        if (is_array($current_value) && !empty($current_value)) {
            $gallery_images = $current_value;
        } elseif (is_string($current_value) && !empty($current_value)) {
            $ids = explode(',', $current_value);
            foreach ($ids as $id) {
                $id = intval(trim($id));
                if ($id > 0) {
                    $image_url = wp_get_attachment_image_url($id, 'thumbnail');
                    if ($image_url) {
                        $gallery_images[] = array(
                                'id' => $id,
                                'url' => $image_url,
                                'alt' => get_post_meta($id, '_wp_attachment_image_alt', true)
                        );
                    }
                }
            }
        }

        ?>
        <div class="besm-gallery-field" data-post-id="<?php echo esc_attr($post_id); ?>">
            <?php if (!empty($gallery_images) && is_array($gallery_images)): ?>
                <?php foreach ($gallery_images as $image): ?>
                    <?php if (isset($image['id']) && isset($image['url'])): ?>
                        <?php
                        $file_path = get_attached_file($image['id']);
                        $filename = $file_path ? basename($file_path) : '';
                        $alt_text = isset($image['alt']) ? $image['alt'] : '';
                        ?>
                        <div class="besm-gallery-item-wrapper">
                            <div class="besm-gallery-item besm-image-preview"
                                 data-attachment-id="<?php echo esc_attr($image['id']); ?>">
                                <img src="<?php echo esc_url($image['url']); ?>"
                                     alt="<?php echo esc_attr($alt_text); ?>">
                                <button type="button" class="besm-image-remove" title="<?php echo esc_attr__('حذف تصویر', 'bulk-edit-seo'); ?>">
                                    <span class="dashicons dashicons-no"></span>
                                </button>
                            </div>

                            <?php if ($filename): ?>
                                <div class="besm-gallery-filename">
                                <span class="besm-filename-text" title="<?php echo esc_attr($filename); ?>">
                                    <?php echo esc_html($filename); ?>
                                </span>
                                    <button type="button" class="besm-copy-filename"
                                            data-filename="<?php echo esc_attr($filename); ?>" title="<?php echo esc_attr__('کپی', 'bulk-edit-seo'); ?>">
                                        <span class="dashicons dashicons-admin-page"></span>
                                    </button>
                                </div>
                            <?php endif; ?>

                            <!-- ✅ CRITICAL: Alt text input برای هر تصویر گالری -->
                            <input type="text"
                                   class="besm-gallery-alt-input"
                                   name="posts[<?php echo esc_attr($post_id); ?>][gallery_alts][<?php echo esc_attr($image['id']); ?>]"
                                   value="<?php echo esc_attr($alt_text); ?>"
                                   placeholder="<?php echo esc_attr__('متن جایگزین (Alt)', 'bulk-edit-seo'); ?>"
                                   style="width: 100%; padding: 4px 8px; border: 1px solid #e2e8f0; border-radius: 4px; font-size: 11px; margin-top: 4px;">
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>

            <div class="besm-gallery-add" title="<?php echo esc_attr__('افزودن تصویر', 'bulk-edit-seo'); ?>">
                <span class="dashicons dashicons-plus-alt"></span>
            </div>

            <!-- Hidden input برای ذخیره IDs -->
            <input type="hidden"
                   name="<?php echo esc_attr($name); ?>"
                   class="besm-gallery-ids"
                   value="<?php
                   if (!empty($gallery_images) && is_array($gallery_images)) {
                       $ids = array_column($gallery_images, 'id');
                       echo esc_attr(implode(',', $ids));
                   }
                   ?>">
        </div>
        <?php
    }

    // Render taxonomy field
    private function render_taxonomy_field($post_id, $name, $taxonomy, $current_value)
    {
        $terms = get_terms(array(
                'taxonomy' => $taxonomy,
                'hide_empty' => false
        ));

        if (is_wp_error($terms) || empty($terms)) {
            echo '<span>—</span>';
            return;
        }

        $current_term_ids = array();
        if (is_array($current_value)) {
            $current_term_ids = wp_list_pluck($current_value, 'term_id');
        }

        ?>
        <select name="<?php echo esc_attr($name); ?>[]" multiple>
            <?php foreach ($terms as $term): ?>
                <option value="<?php echo esc_attr($term->term_id); ?>"
                        <?php echo in_array($term->term_id, $current_term_ids) ? 'selected' : ''; ?>>
                    <?php echo esc_html($term->name); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    // Get field label
    public function get_field_label($field_key)
    {
        $labels = array(
                'title' => __('عنوان', 'bulk-edit-seo'),
                'content' => __('محتوا', 'bulk-edit-seo'),
                'excerpt' => __('خلاصه', 'bulk-edit-seo'),
                'featured_image' => __('تصویر شاخص', 'bulk-edit-seo'),
                'gallery' => __('گالری', 'bulk-edit-seo'),
                'url' => 'URL',
                'slug' => __('نامک', 'bulk-edit-seo'),
                'post_status' => __('وضعیت', 'bulk-edit-seo'),
                'product_cat' => __('دسته‌بندی', 'bulk-edit-seo'),
                'product_tag' => __('برچسب', 'bulk-edit-seo'),
                'category' => __('دسته‌بندی', 'bulk-edit-seo'),
                'post_tag' => __('برچسب', 'bulk-edit-seo'),
                'product_type' => __('نوع محصول', 'bulk-edit-seo'),
                'regular_price' => __('قیمت', 'bulk-edit-seo'),
                'sale_price' => __('قیمت ویژه', 'bulk-edit-seo'),
                'sku' => 'SKU',
                'stock_status' => __('موجودی', 'bulk-edit-seo'),
                'stock_quantity' => __('تعداد', 'bulk-edit-seo'),
                'manage_stock' => __('مدیریت موجودی', 'bulk-edit-seo'),
                'short_description' => __('توضیحات کوتاه', 'bulk-edit-seo'),
                'product_attributes' => __('ویژگی‌ها', 'bulk-edit-seo'),
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
                'robots_noindex' => 'No Index',
                'robots_nofollow' => 'No Follow'
        );

        return isset($labels[$field_key]) ? $labels[$field_key] : $field_key;
    }
}