<?php
/**
 * Filters handler class - COMPLETE WITH ATTRIBUTES
 */

if (!defined('ABSPATH')) {
    exit;
}

class BESM_Filters
{

    private $post_type;
    private $post_handler;
    private $cache_group = 'besm_filters';
    private $cache_time = 3600; // 1 hour

    public function __construct($post_type = '')
    {
        $this->post_type = $post_type;
        $this->post_handler = new BESM_Post_Handler();
    }

    /**
     * Sanitize a request-like array ($_GET or a parsed query string) into the
     * filters structure build_query_args() understands. Used for "select all
     * matching across pages" and CSV export.
     */
    public static function sanitize_request_filters($src)
    {
        $filters = array();

        if (!empty($src['search'])) {
            $filters['search'] = sanitize_text_field($src['search']);
        }

        $filters['post_status'] = isset($src['post_status']) ? sanitize_text_field($src['post_status']) : 'any';

        if (!empty($src['product_type'])) {
            $filters['product_type'] = sanitize_text_field($src['product_type']);
        }

        if (isset($src['taxonomies']) && is_array($src['taxonomies'])) {
            $filters['taxonomies'] = array();
            foreach ($src['taxonomies'] as $tax => $terms) {
                if (!empty($terms)) {
                    $filters['taxonomies'][sanitize_key($tax)] = array_map('intval', (array) $terms);
                }
            }
        }

        if (isset($src['meta']) && is_array($src['meta'])) {
            $filters['meta'] = array();
            foreach ($src['meta'] as $k => $v) {
                if (is_array($v) || $v === '') {
                    continue;
                }
                $filters['meta'][sanitize_key($k)] = sanitize_text_field($v);
            }
        }

        return $filters;
    }

    // Build query args based on filters
    public function build_query_args($filters = array())
    {
        $args = array(
            'post_type' => $this->post_type,
            'posts_per_page' => isset($filters['per_page']) ? intval($filters['per_page']) : 50,
            'paged' => isset($filters['paged']) ? intval($filters['paged']) : 1,
            'post_status' => 'any',
            'orderby' => 'date',
            'order' => 'DESC'
        );

        // Post status filter - exclude trash by default
        if (!empty($filters['post_status'])) {
            if ($filters['post_status'] === 'any') {
                $args['post_status'] = array('publish', 'draft', 'pending', 'private', 'future');
            } else {
                $args['post_status'] = sanitize_key($filters['post_status']);
            }
        } else {
            $args['post_status'] = array('publish', 'draft', 'pending', 'private', 'future');
        }

        // Search — WordPress core already matches title, excerpt and content.
        if (!empty($filters['search'])) {
            $args['s'] = sanitize_text_field($filters['search']);
        }

        // Taxonomy filters (includes attributes)
        if (!empty($filters['taxonomies']) && is_array($filters['taxonomies'])) {
            $tax_query = array('relation' => 'AND');

            foreach ($filters['taxonomies'] as $taxonomy => $term_ids) {
                if (empty($term_ids)) {
                    continue;
                }

                if (!is_array($term_ids)) {
                    $term_ids = array($term_ids);
                }

                $term_ids = array_filter(array_map('intval', $term_ids));

                if (!empty($term_ids)) {
                    $tax_query[] = array(
                        'taxonomy' => sanitize_key($taxonomy),
                        'field' => 'term_id',
                        'terms' => $term_ids,
                        'operator' => 'IN'
                    );
                }
            }

            if (count($tax_query) > 1) {
                $args['tax_query'] = $tax_query;
            }
        }

        // Meta filters with type detection
        if (!empty($filters['meta']) && is_array($filters['meta'])) {
            $meta_query = array('relation' => 'AND');

            foreach ($filters['meta'] as $meta_key => $meta_value) {
                if ($meta_value === '' || $meta_value === null) {
                    continue;
                }

                $sanitized_key = sanitize_key($meta_key);

                if (is_numeric($meta_value)) {
                    $meta_query[] = array(
                        'key' => $sanitized_key,
                        'value' => floatval($meta_value),
                        'compare' => '=',
                        'type' => 'NUMERIC'
                    );
                } elseif (in_array(strtolower($meta_value), array('yes', 'no', 'true', 'false', '1', '0'))) {
                    $meta_query[] = array(
                        'key' => $sanitized_key,
                        'value' => sanitize_text_field($meta_value),
                        'compare' => '='
                    );
                } else {
                    $meta_query[] = array(
                        'key' => $sanitized_key,
                        'value' => sanitize_text_field($meta_value),
                        'compare' => 'LIKE'
                    );
                }
            }

            if (count($meta_query) > 1) {
                $args['meta_query'] = $meta_query;
            }
        }

        // WooCommerce product type filter
        if ($this->post_type === 'product' && !empty($filters['product_type']) && function_exists('wc_get_product')) {
            $product_type = sanitize_text_field($filters['product_type']);

            if (!isset($args['tax_query'])) {
                $args['tax_query'] = array('relation' => 'AND');
            }

            $args['tax_query'][] = array(
                'taxonomy' => 'product_type',
                'field' => 'slug',
                'terms' => $product_type
            );
        }

        return apply_filters('besm_query_args', $args, $filters);
    }

    // Get filter options with caching
    public function get_filter_options()
    {
        $cache_key = 'filter_options_' . $this->post_type;
        $options = wp_cache_get($cache_key, $this->cache_group);

        if ($options !== false) {
            return $options;
        }

        $options = array(
            'taxonomies' => array(),
            'meta_keys' => array(),
            'product_types' => array(),
            'attributes' => array()
        );

        // Get taxonomies
        $taxonomies = $this->post_handler->get_post_type_taxonomies($this->post_type);
        foreach ($taxonomies as $taxonomy) {
            // Skip attribute taxonomies here (we'll handle them separately)
            if (strpos($taxonomy, 'pa_') === 0) {
                continue;
            }

            $terms = get_terms(array(
                'taxonomy' => $taxonomy,
                'hide_empty' => false,
                'orderby' => 'name',
                'order' => 'ASC',
                'number' => 100
            ));

            if (!is_wp_error($terms) && !empty($terms)) {
                $tax_object = get_taxonomy($taxonomy);
                $options['taxonomies'][$taxonomy] = array(
                    'label' => $tax_object->label,
                    'terms' => $terms
                );
            }
        }

        // Get meta keys
        $meta_keys = $this->get_common_meta_keys();
        $options['meta_keys'] = $this->get_meta_keys_with_values($meta_keys);

        // Get product types
        if ($this->post_type === 'product' && function_exists('wc_get_product_types')) {
            $options['product_types'] = wc_get_product_types();
        }

        // Get product attributes
        if ($this->post_type === 'product') {
            $options['attributes'] = $this->get_product_attributes();
        }

        wp_cache_set($cache_key, $options, $this->cache_group, $this->cache_time);

        return apply_filters('besm_filter_options', $options, $this->post_type);
    }

    // Get WooCommerce product attributes
    private function get_product_attributes()
    {
        if ($this->post_type !== 'product' || !function_exists('wc_get_attribute_taxonomies')) {
            return array();
        }

        $attributes = array();
        $attribute_taxonomies = wc_get_attribute_taxonomies();

        if (empty($attribute_taxonomies)) {
            return array();
        }

        foreach ($attribute_taxonomies as $attribute) {
            $taxonomy = wc_attribute_taxonomy_name($attribute->attribute_name);

            if (!taxonomy_exists($taxonomy)) {
                continue;
            }

            $terms = get_terms(array(
                'taxonomy' => $taxonomy,
                'hide_empty' => false,
                'orderby' => 'name',
                'order' => 'ASC',
                'number' => 100
            ));

            if (!is_wp_error($terms) && !empty($terms)) {
                $attributes[$taxonomy] = array(
                    'label' => $attribute->attribute_label,
                    'name' => $attribute->attribute_name,
                    'terms' => $terms,
                    'type' => $attribute->attribute_type
                );
            }
        }

        return $attributes;
    }

    // Get common meta keys efficiently
    private function get_common_meta_keys()
    {
        global $wpdb;

        $cache_key = 'common_meta_keys_' . $this->post_type;
        $meta_keys = wp_cache_get($cache_key, $this->cache_group);

        if ($meta_keys !== false) {
            return $meta_keys;
        }

        $meta_keys = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT pm.meta_key
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE p.post_type = %s
            AND pm.meta_key NOT LIKE '\\_%%'
            AND pm.meta_key NOT LIKE '%%oembed%%'
            GROUP BY pm.meta_key
            HAVING COUNT(*) > 5
            ORDER BY COUNT(*) DESC
            LIMIT 30",
            $this->post_type
        ));

        wp_cache_set($cache_key, $meta_keys, $this->cache_group, $this->cache_time);

        return $meta_keys ? $meta_keys : array();
    }

    // Get meta keys with values
    private function get_meta_keys_with_values($meta_keys)
    {
        global $wpdb;

        if (empty($meta_keys)) {
            return array();
        }

        $meta_data = array();

        $placeholders = implode(',', array_fill(0, count($meta_keys), '%s'));
        $query_params = array_merge(array($this->post_type), $meta_keys);

        $query = $wpdb->prepare(
            "SELECT pm.meta_key, pm.meta_value, COUNT(*) as value_count
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE p.post_type = %s
            AND pm.meta_key IN ($placeholders)
            AND pm.meta_value != ''
            AND LENGTH(pm.meta_value) < 100
            GROUP BY pm.meta_key, pm.meta_value
            HAVING value_count > 2
            ORDER BY pm.meta_key, value_count DESC",
            $query_params
        );

        $results = $wpdb->get_results($query);

        foreach ($results as $row) {
            if (!isset($meta_data[$row->meta_key])) {
                $meta_data[$row->meta_key] = array(
                    'label' => $this->get_meta_key_label($row->meta_key),
                    'values' => array(),
                    'type' => 'text'
                );
            }

            if (count($meta_data[$row->meta_key]['values']) < 50) {
                $meta_data[$row->meta_key]['values'][] = $row->meta_value;
            }
        }

        foreach ($meta_data as $key => &$data) {
            $data['type'] = $this->guess_meta_type($data['values']);
        }

        return $meta_data;
    }

    private function get_meta_key_label($meta_key)
    {
        $label = str_replace('_product_', '', $meta_key);
        $label = str_replace('_', ' ', $label);
        $label = ucwords($label);

        $translations = array(
            'Price' => __('قیمت', 'bulk-edit-seo'),
            'Stock' => __('موجودی', 'bulk-edit-seo'),
            'Sku' => 'SKU',
            'Weight' => __('وزن', 'bulk-edit-seo'),
            'Length' => __('طول', 'bulk-edit-seo'),
            'Width' => __('عرض', 'bulk-edit-seo'),
            'Height' => __('ارتفاع', 'bulk-edit-seo'),
            'Color' => __('رنگ', 'bulk-edit-seo'),
            'Size' => __('سایز', 'bulk-edit-seo'),
            'Brand' => __('برند', 'bulk-edit-seo'),
            'Model' => __('مدل', 'bulk-edit-seo'),
            'Material' => __('جنس', 'bulk-edit-seo'),
            'Regular Price' => __('قیمت عادی', 'bulk-edit-seo'),
            'Sale Price' => __('قیمت فروش', 'bulk-edit-seo'),
            'Stock Quantity' => __('تعداد موجودی', 'bulk-edit-seo'),
            'Stock Status' => __('وضعیت موجودی', 'bulk-edit-seo'),
            'Product Type' => __('نوع محصول', 'bulk-edit-seo')
        );

        foreach ($translations as $en => $fa) {
            $label = str_replace($en, $fa, $label);
        }

        return $label;
    }

    // Enhanced type detection
    private function guess_meta_type($values)
    {
        if (empty($values)) {
            return 'text';
        }

        $numeric_count = 0;
        $boolean_count = 0;
        $total = count($values);

        foreach ($values as $value) {
            if (is_numeric($value)) {
                $numeric_count++;
            }

            $lower_value = strtolower(trim($value));
            if (in_array($lower_value, array('yes', 'no', 'true', 'false', '1', '0'))) {
                $boolean_count++;
            }
        }

        if ($numeric_count / $total >= 0.8) {
            return 'number';
        }

        if ($boolean_count / $total >= 0.8) {
            return 'select';
        }

        if ($total <= 15) {
            return 'select';
        }

        return 'text';
    }

    // Render filters - COMPLETE
    public function render_filters($current_filters = array())
    {
        $options = $this->get_filter_options();
        ?>
        <div class="besm-filters-container">
            <form method="get" class="besm-filters-form" id="besm-filters-form">
                <input type="hidden" name="page" value="besm-bulk-editor">

                <div class="besm-filters-row">
                    <!-- Search box -->
                    <div class="besm-filter-field">
                        <label class="besm-filter-label">
                            <span class="dashicons dashicons-search"></span>
                            <?php esc_html_e('جستجو', 'bulk-edit-seo'); ?>
                        </label>
                        <input type="text"
                               name="search"
                               class="besm-filter-input"
                               value="<?php echo esc_attr(isset($current_filters['search']) ? $current_filters['search'] : ''); ?>"
                               placeholder="<?php echo esc_attr__('جستجو در عنوان، محتوا و خلاصه...', 'bulk-edit-seo'); ?>">
                    </div>

                    <!-- Post status filter -->
                    <div class="besm-filter-field">
                        <label class="besm-filter-label">
                            <span class="dashicons dashicons-flag"></span>
                            <?php esc_html_e('وضعیت', 'bulk-edit-seo'); ?>
                        </label>
                        <select name="post_status" class="besm-filter-select">
                            <option value="any" <?php selected(isset($current_filters['post_status']) ? $current_filters['post_status'] : '', 'any'); ?>>
                                <?php esc_html_e('همه (بدون زباله‌دان)', 'bulk-edit-seo'); ?>
                            </option>
                            <option value="publish" <?php selected(isset($current_filters['post_status']) ? $current_filters['post_status'] : '', 'publish'); ?>>
                                <?php esc_html_e('منتشر شده', 'bulk-edit-seo'); ?>
                            </option>
                            <option value="draft" <?php selected(isset($current_filters['post_status']) ? $current_filters['post_status'] : '', 'draft'); ?>>
                                <?php esc_html_e('پیش‌نویس', 'bulk-edit-seo'); ?>
                            </option>
                            <option value="pending" <?php selected(isset($current_filters['post_status']) ? $current_filters['post_status'] : '', 'pending'); ?>>
                                <?php esc_html_e('در انتظار', 'bulk-edit-seo'); ?>
                            </option>
                            <option value="private" <?php selected(isset($current_filters['post_status']) ? $current_filters['post_status'] : '', 'private'); ?>>
                                <?php esc_html_e('خصوصی', 'bulk-edit-seo'); ?>
                            </option>
                            <option value="trash" <?php selected(isset($current_filters['post_status']) ? $current_filters['post_status'] : '', 'trash'); ?>>
                                <?php esc_html_e('زباله‌دان', 'bulk-edit-seo'); ?>
                            </option>
                        </select>
                    </div>

                    <?php if (!empty($options['product_types'])): ?>
                        <div class="besm-filter-field">
                            <label class="besm-filter-label">
                                <span class="dashicons dashicons-products"></span>
                                <?php esc_html_e('نوع محصول', 'bulk-edit-seo'); ?>
                            </label>
                            <select name="product_type" class="besm-filter-select">
                                <option value=""><?php esc_html_e('همه انواع', 'bulk-edit-seo'); ?></option>
                                <?php foreach ($options['product_types'] as $type_slug => $type_name): ?>
                                    <option value="<?php echo esc_attr($type_slug); ?>"
                                        <?php selected(isset($current_filters['product_type']) ? $current_filters['product_type'] : '', $type_slug); ?>>
                                        <?php echo esc_html($type_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <div class="besm-filter-field">
                        <label class="besm-filter-label">
                            <span class="dashicons dashicons-list-view"></span>
                            <?php esc_html_e('تعداد نمایش', 'bulk-edit-seo'); ?>
                        </label>
                        <select name="per_page" class="besm-filter-select">
                            <option value="25" <?php selected(isset($current_filters['per_page']) ? $current_filters['per_page'] : 50, 25); ?>>
                                25
                            </option>
                            <option value="50" <?php selected(isset($current_filters['per_page']) ? $current_filters['per_page'] : 50, 50); ?>>
                                50
                            </option>
                            <option value="100" <?php selected(isset($current_filters['per_page']) ? $current_filters['per_page'] : 50, 100); ?>>
                                100
                            </option>
                            <option value="200" <?php selected(isset($current_filters['per_page']) ? $current_filters['per_page'] : 50, 200); ?>>
                                200
                            </option>
                            <option value="500" <?php selected(isset($current_filters['per_page']) ? $current_filters['per_page'] : 50, 500); ?>>
                                500
                            </option>
                        </select>
                    </div>
                </div>

                <?php if (!empty($options['taxonomies']) || !empty($options['attributes']) || !empty($options['meta_keys'])): ?>
                    <div class="besm-filters-advanced">
                        <button type="button" class="besm-toggle-advanced">
                            <span class="dashicons dashicons-filter"></span>
                            <?php esc_html_e('فیلترهای پیشرفته', 'bulk-edit-seo'); ?>
                            <span class="dashicons dashicons-arrow-down-alt2"></span>
                        </button>

                        <div class="besm-advanced-content" style="display: none;">
                            <?php if (!empty($options['taxonomies'])): ?>
                                <div class="besm-advanced-section">
                                    <h4><span class="dashicons dashicons-category"></span> <?php esc_html_e('تگزونومی‌ها', 'bulk-edit-seo'); ?></h4>
                                    <div class="besm-filters-row">
                                        <?php foreach ($options['taxonomies'] as $tax_slug => $tax_data): ?>
                                            <div class="besm-filter-field">
                                                <label class="besm-filter-label"><?php echo esc_html($tax_data['label']); ?></label>
                                                <select name="taxonomies[<?php echo esc_attr($tax_slug); ?>][]"
                                                        class="besm-filter-select besm-multi-select"
                                                        multiple
                                                        data-placeholder="<?php echo esc_attr__('انتخاب کنید...', 'bulk-edit-seo'); ?>">
                                                    <?php foreach ($tax_data['terms'] as $term): ?>
                                                        <option value="<?php echo esc_attr($term->term_id); ?>"
                                                            <?php
                                                            if (isset($current_filters['taxonomies'][$tax_slug]) &&
                                                                in_array($term->term_id, (array)$current_filters['taxonomies'][$tax_slug])) {
                                                                echo 'selected';
                                                            }
                                                            ?>>
                                                            <?php echo esc_html($term->name); ?>
                                                            (<?php echo $term->count; ?>)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <small class="besm-field-hint"><?php esc_html_e('می‌توانید چند مورد انتخاب کنید (Ctrl+Click)', 'bulk-edit-seo'); ?></small>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($options['attributes'])): ?>
                                <div class="besm-advanced-section">
                                    <h4><span class="dashicons dashicons-tag"></span> <?php esc_html_e('ویژگی‌های محصول (Attributes)', 'bulk-edit-seo'); ?></h4>
                                    <div class="besm-filters-row">
                                        <?php foreach ($options['attributes'] as $attr_taxonomy => $attr_data): ?>
                                            <div class="besm-filter-field">
                                                <label class="besm-filter-label">
                                                    <?php echo esc_html($attr_data['label']); ?>
                                                    <span class="besm-field-type-badge">attribute</span>
                                                </label>
                                                <select name="taxonomies[<?php echo esc_attr($attr_taxonomy); ?>][]"
                                                        class="besm-filter-select besm-multi-select"
                                                        multiple
                                                        data-placeholder="<?php echo esc_attr__('انتخاب کنید...', 'bulk-edit-seo'); ?>">
                                                    <?php foreach ($attr_data['terms'] as $term): ?>
                                                        <option value="<?php echo esc_attr($term->term_id); ?>"
                                                            <?php
                                                            if (isset($current_filters['taxonomies'][$attr_taxonomy]) &&
                                                                in_array($term->term_id, (array)$current_filters['taxonomies'][$attr_taxonomy])) {
                                                                echo 'selected';
                                                            }
                                                            ?>>
                                                            <?php echo esc_html($term->name); ?>
                                                            (<?php echo $term->count; ?>)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <small class="besm-field-hint"><?php esc_html_e('می‌توانید چند مورد انتخاب کنید (Ctrl+Click)', 'bulk-edit-seo'); ?></small>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($options['meta_keys'])): ?>
                                <div class="besm-advanced-section">
                                    <h4><span class="dashicons dashicons-admin-settings"></span> <?php esc_html_e('متافیلدها', 'bulk-edit-seo'); ?></h4>
                                    <div class="besm-filters-row">
                                        <?php foreach ($options['meta_keys'] as $meta_key => $meta_data): ?>
                                            <div class="besm-filter-field">
                                                <label class="besm-filter-label">
                                                    <?php echo esc_html($meta_data['label']); ?>
                                                    <span class="besm-field-type-badge"><?php echo esc_html($meta_data['type']); ?></span>
                                                </label>
                                                <?php if ($meta_data['type'] === 'select'): ?>
                                                    <select name="meta[<?php echo esc_attr($meta_key); ?>]"
                                                            class="besm-filter-select">
                                                        <option value=""><?php esc_html_e('همه', 'bulk-edit-seo'); ?></option>
                                                        <?php foreach ($meta_data['values'] as $value): ?>
                                                            <option value="<?php echo esc_attr($value); ?>"
                                                                <?php
                                                                if (isset($current_filters['meta'][$meta_key]) &&
                                                                    $current_filters['meta'][$meta_key] == $value) {
                                                                    echo 'selected';
                                                                }
                                                                ?>>
                                                                <?php echo esc_html($value); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                <?php else: ?>
                                                    <input type="<?php echo $meta_data['type'] === 'number' ? 'number' : 'text'; ?>"
                                                           name="meta[<?php echo esc_attr($meta_key); ?>]"
                                                           class="besm-filter-input"
                                                           value="<?php echo isset($current_filters['meta'][$meta_key]) ? esc_attr($current_filters['meta'][$meta_key]) : ''; ?>"
                                                           placeholder="<?php echo esc_attr__('جستجو...', 'bulk-edit-seo'); ?>"
                                                        <?php echo $meta_data['type'] === 'number' ? 'step="0.01"' : ''; ?>>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="besm-filter-actions">
                    <button type="submit" class="besm-btn besm-btn-primary">
                        <span class="dashicons dashicons-filter"></span>
                        <?php esc_html_e('اعمال فیلتر', 'bulk-edit-seo'); ?>
                    </button>
                    <button type="button" id="besm-clear-filters" class="besm-btn besm-btn-ghost">
                        <span class="dashicons dashicons-image-rotate"></span>
                        <?php esc_html_e('پاک کردن فیلترها', 'bulk-edit-seo'); ?>
                    </button>
                </div>
            </form>
        </div>

        <?php
        $has_filters = !empty($current_filters['search']) ||
            !empty($current_filters['post_status']) ||
            !empty($current_filters['product_type']) ||
            !empty($current_filters['taxonomies']) ||
            !empty($current_filters['meta']);

        if ($has_filters):
            ?>
            <div class="besm-active-filters">
                <span class="besm-filter-badge-label">
                    <span class="dashicons dashicons-filter"></span>
                    <?php esc_html_e('فیلترهای فعال:', 'bulk-edit-seo'); ?>
                </span>

                <?php if (!empty($current_filters['search'])): ?>
                    <span class="besm-filter-badge">
                        <span class="dashicons dashicons-search"></span>
                        <?php esc_html_e('جستجو:', 'bulk-edit-seo'); ?> "<?php echo esc_html($current_filters['search']); ?>"
                        <a href="<?php echo esc_url(remove_query_arg('search')); ?>"
                           class="besm-remove-filter"
                           title="<?php echo esc_attr__('حذف این فیلتر', 'bulk-edit-seo'); ?>">×</a>
                    </span>
                <?php endif; ?>

                <?php if (!empty($current_filters['post_status']) && $current_filters['post_status'] !== 'any'): ?>
                    <span class="besm-filter-badge">
                        <span class="dashicons dashicons-flag"></span>
                        <?php esc_html_e('وضعیت:', 'bulk-edit-seo'); ?> <?php echo esc_html($current_filters['post_status']); ?>
                        <a href="<?php echo esc_url(remove_query_arg('post_status')); ?>"
                           class="besm-remove-filter"
                           title="<?php echo esc_attr__('حذف این فیلتر', 'bulk-edit-seo'); ?>">×</a>
                    </span>
                <?php endif; ?>

                <?php if (!empty($current_filters['product_type'])): ?>
                    <span class="besm-filter-badge">
                        <span class="dashicons dashicons-products"></span>
                        <?php esc_html_e('نوع:', 'bulk-edit-seo'); ?> <?php echo esc_html($current_filters['product_type']); ?>
                        <a href="<?php echo esc_url(remove_query_arg('product_type')); ?>"
                           class="besm-remove-filter"
                           title="<?php echo esc_attr__('حذف این فیلتر', 'bulk-edit-seo'); ?>">×</a>
                    </span>
                <?php endif; ?>

                <?php
                if (!empty($current_filters['taxonomies'])):
                    foreach ($current_filters['taxonomies'] as $tax_slug => $term_ids):
                        if (empty($term_ids)) continue;

                        $tax_obj = get_taxonomy($tax_slug);
                        if (!$tax_obj) continue;

                        $term_ids = is_array($term_ids) ? $term_ids : array($term_ids);
                        foreach ($term_ids as $term_id):
                            $term = get_term($term_id, $tax_slug);
                            if ($term && !is_wp_error($term)):
                                $icon = strpos($tax_slug, 'pa_') === 0 ? 'tag' : 'category';
                                ?>
                                <span class="besm-filter-badge">
                        <span class="dashicons dashicons-<?php echo $icon; ?>"></span>
                        <?php echo esc_html($tax_obj->labels->singular_name); ?>: <?php echo esc_html($term->name); ?>
                        <a href="<?php echo esc_url($this->remove_taxonomy_filter_url($tax_slug, $term_id)); ?>"
                           class="besm-remove-filter"
                           title="<?php echo esc_attr__('حذف این فیلتر', 'bulk-edit-seo'); ?>">×</a>
                    </span>
                            <?php
                            endif;
                        endforeach;
                    endforeach;
                endif;
                ?>

                <?php
                if (!empty($current_filters['meta'])):
                    foreach ($current_filters['meta'] as $meta_key => $meta_value):
                        if ($meta_value === '') continue;
                        ?>
                        <span class="besm-filter-badge">
                        <span class="dashicons dashicons-admin-settings"></span>
                        <?php echo esc_html($this->get_meta_key_label($meta_key)); ?>: <?php echo esc_html($meta_value); ?>
                        <a href="<?php echo esc_url($this->remove_meta_filter_url($meta_key)); ?>"
                           class="besm-remove-filter"
                           title="<?php echo esc_attr__('حذف این فیلتر', 'bulk-edit-seo'); ?>">×</a>
                    </span>
                    <?php
                    endforeach;
                endif;
                ?>

                <a href="?page=besm-bulk-editor" class="besm-btn besm-btn-small besm-btn-ghost">
                    <span class="dashicons dashicons-no-alt"></span>
                    <?php esc_html_e('پاک کردن همه', 'bulk-edit-seo'); ?>
                </a>
            </div>
        <?php endif; ?>
        <?php
    }

    private function remove_taxonomy_filter_url($taxonomy, $term_id)
    {
        $current_url = add_query_arg(array());

        if (!isset($_GET['taxonomies'][$taxonomy])) {
            return $current_url;
        }

        $terms = (array)$_GET['taxonomies'][$taxonomy];
        $terms = array_diff($terms, array($term_id));

        if (empty($terms)) {
            return remove_query_arg(array('taxonomies'));
        }

        return add_query_arg(array('taxonomies' => array($taxonomy => $terms)));
    }

    private function remove_meta_filter_url($meta_key)
    {
        $meta_filters = isset($_GET['meta']) ? $_GET['meta'] : array();
        unset($meta_filters[$meta_key]);

        if (empty($meta_filters)) {
            return remove_query_arg('meta');
        }

        return add_query_arg(array('meta' => $meta_filters));
    }
}