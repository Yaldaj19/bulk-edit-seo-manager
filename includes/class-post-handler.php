<?php
/**
 * Post type handler class
 */

if (!defined('ABSPATH')) {
    exit;
}

class BESM_Post_Handler
{

    // Get all available post types (exclude page)
    public function get_all_post_types()
    {
        $args = array(
            'public' => true,
            '_builtin' => false
        );

        $post_types = get_post_types($args, 'objects');

        // Add built-in post type
        $post_types['post'] = get_post_type_object('post');

        // Add product if WooCommerce is active
        if (class_exists('WooCommerce')) {
            $post_types['product'] = get_post_type_object('product');
        }

        // Remove page type
        if (isset($post_types['page'])) {
            unset($post_types['page']);
        }

        // Format for select dropdown
        $formatted = array();
        foreach ($post_types as $slug => $post_type) {
            $formatted[$slug] = $post_type->labels->name;
        }

        return $formatted;
    }

    // Get available fields for post type
    public function get_available_fields($post_type)
    {
        $fields = array();

        // Basic fields (available for all post types)
        $fields['title'] = array('type' => 'text', 'label' => __('عنوان', 'bulk-edit-seo'));
        $fields['content'] = array('type' => 'textarea', 'label' => __('محتوا', 'bulk-edit-seo'));
        $fields['excerpt'] = array('type' => 'textarea', 'label' => __('خلاصه', 'bulk-edit-seo'));
        $fields['slug'] = array('type' => 'text', 'label' => __('نامک', 'bulk-edit-seo'));
        $fields['url'] = array('type' => 'text', 'label' => __('پیوند یکتا', 'bulk-edit-seo'), 'readonly' => true);
        $fields['post_status'] = array('type' => 'select', 'label' => __('وضعیت انتشار', 'bulk-edit-seo'));
        $fields['featured_image'] = array('type' => 'image', 'label' => __('تصویر شاخص', 'bulk-edit-seo'));

        // Taxonomies
        $taxonomies = $this->get_post_type_taxonomies($post_type);
        foreach ($taxonomies as $taxonomy) {
            $tax_object = get_taxonomy($taxonomy);
            $fields[$taxonomy] = array('type' => 'taxonomy', 'label' => $tax_object->label);
        }

        // SEO Fields - Yoast
        if (defined('WPSEO_VERSION')) {
            $fields['yoast_title'] = array('type' => 'text', 'label' => __('عنوان سئو (Yoast)', 'bulk-edit-seo'));
            $fields['yoast_description'] = array('type' => 'textarea', 'label' => __('توضیحات سئو (Yoast)', 'bulk-edit-seo'));
            $fields['yoast_focus_keyword'] = array('type' => 'text', 'label' => __('کلمه کلیدی (Yoast)', 'bulk-edit-seo'));
            $fields['yoast_canonical'] = array('type' => 'text', 'label' => __('لینک کانونیکال (Yoast)', 'bulk-edit-seo'));
        }

        // SEO Fields - Rank Math
        if (defined('RANK_MATH_VERSION')) {
            $fields['rankmath_title'] = array('type' => 'text', 'label' => __('عنوان سئو (Rank Math)', 'bulk-edit-seo'));
            $fields['rankmath_description'] = array('type' => 'textarea', 'label' => __('توضیحات سئو (Rank Math)', 'bulk-edit-seo'));
            $fields['rankmath_focus_keyword'] = array('type' => 'text', 'label' => __('کلمه کلیدی (Rank Math)', 'bulk-edit-seo'));
            $fields['rankmath_canonical'] = array('type' => 'text', 'label' => __('لینک کانونیکال (Rank Math)', 'bulk-edit-seo'));
        }

        // SEO Fields - SEOPress
        if (defined('SEOPRESS_VERSION')) {
            $fields['seopress_title'] = array('type' => 'text', 'label' => __('عنوان سئو (SEOPress)', 'bulk-edit-seo'));
            $fields['seopress_description'] = array('type' => 'textarea', 'label' => __('توضیحات سئو (SEOPress)', 'bulk-edit-seo'));
            $fields['seopress_focus_keyword'] = array('type' => 'text', 'label' => __('کلمه کلیدی (SEOPress)', 'bulk-edit-seo'));
            $fields['seopress_canonical'] = array('type' => 'text', 'label' => __('لینک کانونیکال (SEOPress)', 'bulk-edit-seo'));
        }

        // Social meta (OpenGraph / Twitter) — mapped to the active SEO plugin
        if (!empty($this->social_meta_map())) {
            $fields['og_title'] = array('type' => 'text', 'label' => __('عنوان OpenGraph', 'bulk-edit-seo'));
            $fields['og_description'] = array('type' => 'textarea', 'label' => __('توضیحات OpenGraph', 'bulk-edit-seo'));
            $fields['twitter_title'] = array('type' => 'text', 'label' => __('عنوان توییتر', 'bulk-edit-seo'));
            $fields['twitter_description'] = array('type' => 'textarea', 'label' => __('توضیحات توییتر', 'bulk-edit-seo'));
        }

        // Robots Meta
        $fields['robots_noindex'] = array('type' => 'checkbox', 'label' => __('No Index', 'bulk-edit-seo'));
        $fields['robots_nofollow'] = array('type' => 'checkbox', 'label' => __('No Follow', 'bulk-edit-seo'));

        // WooCommerce specific fields
        if ($post_type === 'product' && class_exists('WooCommerce')) {
            $fields['short_description'] = array('type' => 'textarea', 'label' => __('توضیحات کوتاه', 'bulk-edit-seo'));
            $fields['sku'] = array('type' => 'text', 'label' => __('شناسه محصول (SKU)', 'bulk-edit-seo'));
            $fields['regular_price'] = array('type' => 'number', 'label' => __('قیمت', 'bulk-edit-seo'));
            $fields['sale_price'] = array('type' => 'number', 'label' => __('قیمت فروش ویژه', 'bulk-edit-seo'));
            $fields['stock_status'] = array('type' => 'select', 'label' => __('وضعیت موجودی', 'bulk-edit-seo'));
            $fields['stock_quantity'] = array('type' => 'number', 'label' => __('تعداد موجودی', 'bulk-edit-seo'));
            $fields['manage_stock'] = array('type' => 'checkbox', 'label' => __('مدیریت موجودی', 'bulk-edit-seo'));
            $fields['product_type'] = array('type' => 'select', 'label' => __('نوع محصول', 'bulk-edit-seo'));
            $fields['gallery'] = array('type' => 'gallery', 'label' => __('گالری تصاویر', 'bulk-edit-seo'));
            $fields['product_attributes'] = array('type' => 'attributes', 'label' => __('ویژگی‌های محصول', 'bulk-edit-seo'));
        }

        // Custom meta fields
        $meta_keys = $this->get_post_type_meta_keys($post_type);
        foreach ($meta_keys as $meta_key) {
            if (!isset($fields[$meta_key])) {
                $fields[$meta_key] = array('type' => 'text', 'label' => $meta_key);
            }
        }

        return apply_filters('besm_available_fields', $fields, $post_type);
    }

    // Get post type taxonomies
    public function get_post_type_taxonomies($post_type)
    {
        $taxonomies = get_object_taxonomies($post_type);

        // Remove nav_menu and other system taxonomies
        $exclude = array('nav_menu', 'link_category', 'post_format');
        $taxonomies = array_diff($taxonomies, $exclude);

        return $taxonomies;
    }

    // Get post type meta keys
    public function get_post_type_meta_keys($post_type, $limit = 50)
    {
        global $wpdb;

        $meta_keys = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT pm.meta_key
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE p.post_type = %s
            AND pm.meta_key NOT LIKE '\\_%%'
            AND pm.meta_key NOT LIKE '%%oembed%%'
            ORDER BY pm.meta_key
            LIMIT %d",
            $post_type,
            $limit
        ));

        return $meta_keys ? $meta_keys : array();
    }

    // Get posts with filters
    public function get_posts($post_type, $args = array())
    {
        $defaults = array(
            'post_type' => $post_type,
            'posts_per_page' => 50,
            'paged' => 1,
            'post_status' => 'any',
            'orderby' => 'date',
            'order' => 'DESC'
        );

        $args = wp_parse_args($args, $defaults);

        $query = new WP_Query($args);

        return array(
            'posts' => $query->posts,
            'total' => $query->found_posts,
            'max_pages' => $query->max_num_pages
        );
    }

    // Get post data for editing
    public function get_post_data($post_id, $fields = array())
    {
        $post = get_post($post_id);

        if (!$post) {
            return false;
        }

        $data = array(
            'ID' => $post->ID,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'excerpt' => $post->post_excerpt,
            'slug' => urldecode($post->post_name),
            'url' => get_permalink($post->ID),
            'post_type' => $post->post_type,
            'post_status' => $post->post_status
        );

        // Featured image
        $featured_id = get_post_thumbnail_id($post->ID);
        if ($featured_id) {
            $data['featured_image'] = array(
                'id' => $featured_id,
                'url' => wp_get_attachment_image_url($featured_id, 'thumbnail'),
                'alt' => get_post_meta($featured_id, '_wp_attachment_image_alt', true)
            );
        }

        // Taxonomies
        $taxonomies = $this->get_post_type_taxonomies($post->post_type);
        foreach ($taxonomies as $taxonomy) {
            $terms = wp_get_post_terms($post->ID, $taxonomy);
            if (!is_wp_error($terms)) {
                $data[$taxonomy] = $terms;
            }
        }

        // WooCommerce specific
        if ($post->post_type === 'product' && class_exists('WooCommerce')) {
            $product = wc_get_product($post->ID);
            if ($product) {
                $data['short_description'] = $product->get_short_description();
                $data['sku'] = $product->get_sku();
                $data['regular_price'] = $product->get_regular_price();
                $data['sale_price'] = $product->get_sale_price();
                $data['stock_status'] = $product->get_stock_status();
                $data['stock_quantity'] = $product->get_stock_quantity();
                $data['manage_stock'] = $product->get_manage_stock();
                $data['product_type'] = $product->get_type();

                // ✅ Gallery - اصلاح شده
                $gallery_ids = $product->get_gallery_image_ids();
                if (!empty($gallery_ids) && is_array($gallery_ids)) {
                    $data['gallery'] = array();
                    foreach ($gallery_ids as $gallery_id) {
                        $image_url = wp_get_attachment_image_url($gallery_id, 'thumbnail');
                        if ($image_url) {
                            $data['gallery'][] = array(
                                'id' => $gallery_id,
                                'url' => $image_url,
                                'alt' => get_post_meta($gallery_id, '_wp_attachment_image_alt', true)
                            );
                        }
                    }
                } else {
                    // ✅ اگر گالری خالی است
                    $data['gallery'] = array();
                }

                // Attributes
                $attributes = $product->get_attributes();
                if ($attributes) {
                    $data['product_attributes'] = array();
                    foreach ($attributes as $attribute) {
                        $data['product_attributes'][] = array(
                            'name' => $attribute->get_name(),
                            'options' => $attribute->get_options(),
                            'visible' => $attribute->get_visible(),
                            'variation' => $attribute->get_variation()
                        );
                    }
                }
            }
        }

        // Custom meta fields
        if (!empty($fields)) {
            foreach ($fields as $field) {
                if (!isset($data[$field])) {
                    $data[$field] = get_post_meta($post->ID, $field, true);
                }
            }
        }

        // Get SEO data
        $seo_data = $this->get_seo_data($post->ID);
        $data = array_merge($data, $seo_data);

        return apply_filters('besm_post_data', $data, $post->ID);
    }

    // Get WooCommerce product types
    public function get_product_types()
    {
        if (!function_exists('wc_get_product_types')) {
            return array();
        }

        return wc_get_product_types();
    }

    // Get stock status options
    public function get_stock_status_options()
    {
        return array(
            'instock' => __('موجود', 'bulk-edit-seo'),
            'outofstock' => __('ناموجود', 'bulk-edit-seo'),
            'onbackorder' => __('در انتظار تامین', 'bulk-edit-seo')
        );
    }

    // Validate post exists
    public function post_exists($post_id)
    {
        return get_post_status($post_id) !== false;
    }

    // Check if user can edit post
    public function user_can_edit($post_id)
    {
        return current_user_can('edit_post', $post_id);
    }

    // Get post type label
    public function get_post_type_label($post_type)
    {
        $post_type_object = get_post_type_object($post_type);
        return $post_type_object ? $post_type_object->labels->name : $post_type;
    }

    // Check if post type supports feature
    public function post_type_supports($post_type, $feature)
    {
        return post_type_supports($post_type, $feature);
    }

    // Check if product is variable
    public function is_variable_product($post_id)
    {
        if (!function_exists('wc_get_product')) {
            return false;
        }

        $product = wc_get_product($post_id);
        return $product && $product->is_type('variable');
    }

    // ✅ FIXED: Get product variations با اطلاعات کامل‌تر
    public function get_product_variations($product_id)
    {
        if (!function_exists('wc_get_product')) {
            return array();
        }

        $product = wc_get_product($product_id);
        if (!$product || !$product->is_type('variable')) {
            return array();
        }

        $variations = $product->get_available_variations();
        $variation_data = array();

        foreach ($variations as $variation) {
            $variation_id = $variation['variation_id'];
            $variation_obj = wc_get_product($variation_id);

            if (!$variation_obj) {
                continue;
            }

            // ✅ FIXED: ساخت نام بهتر برای variation
            $display_name = $this->get_variation_display_name($variation['attributes']);

            // اگر نام خالی بود، از attributes استفاده کن
            if (empty($display_name)) {
                $display_name = 'Variation #' . $variation_id;
            }

            $variation_data[] = array(
                'id' => $variation_id,
                'attributes' => $variation['attributes'],
                'display_name' => $display_name,
                'sku' => $variation_obj->get_sku(),
                'price' => $variation_obj->get_price(),
                'regular_price' => $variation_obj->get_regular_price(),
                'sale_price' => $variation_obj->get_sale_price(),
                'stock_quantity' => $variation_obj->get_stock_quantity(),
                'stock_status' => $variation_obj->get_stock_status(),
                'manage_stock' => $variation_obj->get_manage_stock(),
                'image_id' => $variation_obj->get_image_id(),
                'description' => $variation_obj->get_description(),
                'is_in_stock' => $variation_obj->is_in_stock(),
                'is_on_sale' => $variation_obj->is_on_sale()
            );
        }

        return $variation_data;
    }

    // ✅ بهبود یافته: ساخت نام بهتر برای variation
    private function get_variation_display_name($attributes)
    {
        if (empty($attributes) || !is_array($attributes)) {
            return 'Variation';
        }

        $names = array();

        foreach ($attributes as $key => $value) {
            // حذف پیشوند attribute_
            $attr_name = str_replace('attribute_', '', $key);

            // حذف pa_ (برای custom attributes)
            $attr_name = str_replace('pa_', '', $attr_name);

            // تبدیل - و _ به فاصله
            $attr_name = str_replace(array('-', '_'), ' ', $attr_name);

            // Capitalize
            $attr_name = ucwords($attr_name);

            // پردازش value
            if (!empty($value)) {
                $attr_value = str_replace(array('-', '_'), ' ', $value);
                $attr_value = ucfirst($attr_value);

                $names[] = $attr_name . ': ' . $attr_value;
            } else {
                $names[] = $attr_name . ': Any';
            }
        }

        return !empty($names) ? implode(' | ', $names) : 'Variation';
    }

    // Get variation data for editing
    public function get_variation_data($variation_id, $fields = array())
    {
        if (!function_exists('wc_get_product')) {
            return false;
        }

        $variation = wc_get_product($variation_id);
        if (!$variation) {
            return false;
        }

        $data = array(
            'ID' => $variation_id,
            'title' => $this->get_variation_display_name($variation->get_attributes()),
            'description' => $variation->get_description(),
            'sku' => $variation->get_sku(),
            'regular_price' => $variation->get_regular_price(),
            'sale_price' => $variation->get_sale_price(),
            'stock_status' => $variation->get_stock_status(),
            'stock_quantity' => $variation->get_stock_quantity(),
            'manage_stock' => $variation->get_manage_stock(),
            'post_type' => 'product_variation'
        );

        // Variation image
        $image_id = $variation->get_image_id();
        if ($image_id) {
            $data['featured_image'] = array(
                'id' => $image_id,
                'url' => wp_get_attachment_image_url($image_id, 'thumbnail'),
                'alt' => get_post_meta($image_id, '_wp_attachment_image_alt', true)
            );
        }

        return apply_filters('besm_variation_data', $data, $variation_id);
    }

    // Get SEO data (Yoast, RankMath compatibility)
    public function get_seo_data($post_id)
    {
        $seo_data = array();

        // Yoast SEO
        if (defined('WPSEO_VERSION')) {
            $seo_data['yoast_title'] = get_post_meta($post_id, '_yoast_wpseo_title', true);
            $seo_data['yoast_description'] = get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
            $seo_data['yoast_focus_keyword'] = get_post_meta($post_id, '_yoast_wpseo_focuskw', true);
            $seo_data['yoast_canonical'] = get_post_meta($post_id, '_yoast_wpseo_canonical', true);

            // Robots meta
            $noindex = get_post_meta($post_id, '_yoast_wpseo_meta-robots-noindex', true);
            $nofollow = get_post_meta($post_id, '_yoast_wpseo_meta-robots-nofollow', true);
            $seo_data['robots_noindex'] = ($noindex === '1' || $noindex === 'noindex');
            $seo_data['robots_nofollow'] = ($nofollow === '1' || $nofollow === 'nofollow');
        }

        // Rank Math
        if (defined('RANK_MATH_VERSION')) {
            $seo_data['rankmath_title'] = get_post_meta($post_id, 'rank_math_title', true);
            $seo_data['rankmath_description'] = get_post_meta($post_id, 'rank_math_description', true);
            $seo_data['rankmath_focus_keyword'] = get_post_meta($post_id, 'rank_math_focus_keyword', true);
            $seo_data['rankmath_canonical'] = get_post_meta($post_id, 'rank_math_canonical_url', true);

            // Robots meta
            $robots = get_post_meta($post_id, 'rank_math_robots', true);
            if (is_array($robots)) {
                $seo_data['robots_noindex'] = in_array('noindex', $robots);
                $seo_data['robots_nofollow'] = in_array('nofollow', $robots);
            }
        }

        // SEOPress
        if (defined('SEOPRESS_VERSION')) {
            $seo_data['seopress_title'] = get_post_meta($post_id, '_seopress_titles_title', true);
            $seo_data['seopress_description'] = get_post_meta($post_id, '_seopress_titles_desc', true);
            $seo_data['seopress_focus_keyword'] = get_post_meta($post_id, '_seopress_analysis_target_kw', true);
            $seo_data['seopress_canonical'] = get_post_meta($post_id, '_seopress_robots_canonical', true);

            // SEOPress stores "yes" to mean noindex / nofollow.
            $seo_data['robots_noindex'] = get_post_meta($post_id, '_seopress_robots_index', true) === 'yes';
            $seo_data['robots_nofollow'] = get_post_meta($post_id, '_seopress_robots_follow', true) === 'yes';
        }

        // Social meta (OpenGraph / Twitter)
        foreach ($this->social_meta_map() as $field => $meta_key) {
            $seo_data[$field] = get_post_meta($post_id, $meta_key, true);
        }

        return $seo_data;
    }

    /**
     * Map the generic social fields to the active SEO plugin's meta keys.
     * Priority: Yoast → Rank Math → SEOPress. Empty array if none active.
     */
    public function social_meta_map()
    {
        if (defined('WPSEO_VERSION')) {
            return array(
                'og_title' => '_yoast_wpseo_opengraph-title',
                'og_description' => '_yoast_wpseo_opengraph-description',
                'twitter_title' => '_yoast_wpseo_twitter-title',
                'twitter_description' => '_yoast_wpseo_twitter-description',
            );
        }

        if (defined('RANK_MATH_VERSION')) {
            return array(
                'og_title' => 'rank_math_facebook_title',
                'og_description' => 'rank_math_facebook_description',
                'twitter_title' => 'rank_math_twitter_title',
                'twitter_description' => 'rank_math_twitter_description',
            );
        }

        if (defined('SEOPRESS_VERSION')) {
            return array(
                'og_title' => '_seopress_social_fb_title',
                'og_description' => '_seopress_social_fb_desc',
                'twitter_title' => '_seopress_social_twitter_title',
                'twitter_description' => '_seopress_social_twitter_desc',
            );
        }

        return array();
    }

    // Update SEO data
    public function update_seo_data($post_id, $seo_data)
    {
        // Yoast SEO
        if (isset($seo_data['yoast_title'])) {
            update_post_meta($post_id, '_yoast_wpseo_title', sanitize_text_field($seo_data['yoast_title']));
        }
        if (isset($seo_data['yoast_description'])) {
            update_post_meta($post_id, '_yoast_wpseo_metadesc', sanitize_textarea_field($seo_data['yoast_description']));
        }
        if (isset($seo_data['yoast_focus_keyword'])) {
            update_post_meta($post_id, '_yoast_wpseo_focuskw', sanitize_text_field($seo_data['yoast_focus_keyword']));
        }
        if (isset($seo_data['yoast_canonical'])) {
            update_post_meta($post_id, '_yoast_wpseo_canonical', esc_url_raw($seo_data['yoast_canonical']));
        }

        // Yoast Robots
        if (isset($seo_data['robots_noindex']) && defined('WPSEO_VERSION')) {
            $value = $seo_data['robots_noindex'] ? '1' : '2';
            update_post_meta($post_id, '_yoast_wpseo_meta-robots-noindex', $value);
        }
        if (isset($seo_data['robots_nofollow']) && defined('WPSEO_VERSION')) {
            $value = $seo_data['robots_nofollow'] ? '1' : '2';
            update_post_meta($post_id, '_yoast_wpseo_meta-robots-nofollow', $value);
        }

        // Rank Math
        if (isset($seo_data['rankmath_title'])) {
            update_post_meta($post_id, 'rank_math_title', sanitize_text_field($seo_data['rankmath_title']));
        }
        if (isset($seo_data['rankmath_description'])) {
            update_post_meta($post_id, 'rank_math_description', sanitize_textarea_field($seo_data['rankmath_description']));
        }
        if (isset($seo_data['rankmath_focus_keyword'])) {
            update_post_meta($post_id, 'rank_math_focus_keyword', sanitize_text_field($seo_data['rankmath_focus_keyword']));
        }
        if (isset($seo_data['rankmath_canonical'])) {
            update_post_meta($post_id, 'rank_math_canonical_url', esc_url_raw($seo_data['rankmath_canonical']));
        }

        // Rank Math Robots
        if (defined('RANK_MATH_VERSION')) {
            $robots = array();
            if (!empty($seo_data['robots_noindex'])) {
                $robots[] = 'noindex';
            } else {
                $robots[] = 'index';
            }
            if (!empty($seo_data['robots_nofollow'])) {
                $robots[] = 'nofollow';
            } else {
                $robots[] = 'follow';
            }
            update_post_meta($post_id, 'rank_math_robots', $robots);
        }

        // SEOPress
        if (defined('SEOPRESS_VERSION')) {
            if (isset($seo_data['seopress_title'])) {
                update_post_meta($post_id, '_seopress_titles_title', sanitize_text_field($seo_data['seopress_title']));
            }
            if (isset($seo_data['seopress_description'])) {
                update_post_meta($post_id, '_seopress_titles_desc', sanitize_textarea_field($seo_data['seopress_description']));
            }
            if (isset($seo_data['seopress_focus_keyword'])) {
                update_post_meta($post_id, '_seopress_analysis_target_kw', sanitize_text_field($seo_data['seopress_focus_keyword']));
            }
            if (isset($seo_data['seopress_canonical'])) {
                update_post_meta($post_id, '_seopress_robots_canonical', esc_url_raw($seo_data['seopress_canonical']));
            }
            if (isset($seo_data['robots_noindex'])) {
                update_post_meta($post_id, '_seopress_robots_index', !empty($seo_data['robots_noindex']) ? 'yes' : '');
            }
            if (isset($seo_data['robots_nofollow'])) {
                update_post_meta($post_id, '_seopress_robots_follow', !empty($seo_data['robots_nofollow']) ? 'yes' : '');
            }
        }

        // Social meta (OpenGraph / Twitter)
        foreach ($this->social_meta_map() as $field => $meta_key) {
            if (isset($seo_data[$field])) {
                update_post_meta($post_id, $meta_key, sanitize_text_field($seo_data[$field]));
            }
        }
    }

    // Get post status options
    public function get_post_status_options()
    {
        return array(
            'publish' => __('منتشر شده', 'bulk-edit-seo'),
            'draft' => __('پیش‌نویس', 'bulk-edit-seo'),
            'pending' => __('در انتظار بررسی', 'bulk-edit-seo'),
            'private' => __('خصوصی', 'bulk-edit-seo'),
            'future' => __('زمان‌بندی شده', 'bulk-edit-seo')
        );
    }
}