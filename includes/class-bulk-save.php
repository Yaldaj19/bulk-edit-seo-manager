<?php
/**
 * Bulk save handler class - COMPLETELY FIXED
 */

if (!defined('ABSPATH')) {
    exit;
}

class BESM_Bulk_Save
{

    private $image_manager;
    private $saved_count = 0;
    private $failed_count = 0;
    private $errors = array();

    public function __construct()
    {
        $this->image_manager = new BESM_Image();
    }

    // Save multiple posts
    public function save_posts($posts_data)
    {
        if (empty($posts_data) || !is_array($posts_data)) {
            return array(
                'success' => false,
                'message' => __('داده‌ای برای ذخیره وجود ندارد', 'bulk-edit-seo'),
                'saved_count' => 0,
                'failed_count' => 0,
                'errors' => array()
            );
        }

        // Security check
        if (!current_user_can('edit_posts')) {
            return array(
                'success' => false,
                'message' => __('دسترسی غیرمجاز', 'bulk-edit-seo'),
                'saved_count' => 0,
                'failed_count' => 0,
                'errors' => array()
            );
        }

        foreach ($posts_data as $post_id => $post_data) {
            $result = $this->save_single_post($post_id, $post_data);

            if ($result) {
                $this->saved_count++;
            } else {
                $this->failed_count++;
            }
        }

        return array(
            'success' => $this->saved_count > 0,
            'saved_count' => $this->saved_count,
            'failed_count' => $this->failed_count,
            'errors' => $this->errors
        );
    }

    // Save single post
    private function save_single_post($post_id, $post_data)
    {
        $post_id = intval($post_id);

        if (!$post_id || !get_post($post_id)) {
            $this->errors[$post_id] = __('پست یافت نشد', 'bulk-edit-seo');
            return false;
        }

        // Check user permission
        if (!current_user_can('edit_post', $post_id)) {
            $this->errors[$post_id] = __('عدم دسترسی به ویرایش این پست', 'bulk-edit-seo');
            return false;
        }

        try {
            // Check if it's a variation
            $post_type = get_post_type($post_id);

            if ($post_type === 'product_variation') {
                return $this->save_variation($post_id, $post_data);
            }

            // Update basic post data
            $this->update_post_data($post_id, $post_data);

            // Update meta fields
            $this->update_meta_fields($post_id, $post_data);

            // Update taxonomies
            $this->update_taxonomies($post_id, $post_data);

            // Update images
            $this->update_images($post_id, $post_data);

            // Update SEO fields
            $this->update_seo_fields($post_id, $post_data);

            // Update WooCommerce specific fields
            if (function_exists('wc_get_product')) {
                $this->update_woocommerce_fields($post_id, $post_data);
            }

            // Clear cache
            clean_post_cache($post_id);

            return true;

        } catch (Exception $e) {
            $this->errors[$post_id] = $e->getMessage();
            return false;
        }
    }

    // Save variation
    private function save_variation($variation_id, $post_data)
    {
        if (!function_exists('wc_get_product')) {
            $this->errors[$variation_id] = __('ووکامرس فعال نیست', 'bulk-edit-seo');
            return false;
        }

        $variation = wc_get_product($variation_id);

        if (!$variation || !$variation->is_type('variation')) {
            $this->errors[$variation_id] = __('Variation یافت نشد', 'bulk-edit-seo');
            return false;
        }

        try {
            // Update description
            if (isset($post_data['description']) || isset($post_data['content'])) {
                $description = isset($post_data['description']) ? $post_data['description'] : $post_data['content'];
                $variation->set_description(wp_kses_post($description));
            }

            // Update SKU
            if (isset($post_data['sku'])) {
                $variation->set_sku(sanitize_text_field($post_data['sku']));
            }

            // Update prices
            if (isset($post_data['regular_price'])) {
                $variation->set_regular_price(sanitize_text_field($post_data['regular_price']));
            }

            if (isset($post_data['sale_price'])) {
                $variation->set_sale_price(sanitize_text_field($post_data['sale_price']));
            }

            // Update stock
            if (isset($post_data['stock_status'])) {
                $variation->set_stock_status(sanitize_text_field($post_data['stock_status']));
            }

            if (isset($post_data['manage_stock'])) {
                $manage = $post_data['manage_stock'] === 'yes' || $post_data['manage_stock'] === '1';
                $variation->set_manage_stock($manage);
            }

            if (isset($post_data['stock_quantity'])) {
                $variation->set_stock_quantity(intval($post_data['stock_quantity']));
            }

            // Update variation image
            if (isset($post_data['featured_image'])) {
                $attachment_id = intval($post_data['featured_image']);
                if ($attachment_id) {
                    $variation->set_image_id($attachment_id);

                    // Update alt text
                    if (isset($post_data['featured_image_alt'])) {
                        $alt_text = sanitize_text_field($post_data['featured_image_alt']);
                    } else {
                        $parent = wc_get_product($variation->get_parent_id());
                        $alt_text = $parent ? $parent->get_name() : '';
                    }
                    update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text);
                } else {
                    $variation->set_image_id('');
                }
            }

            // Save variation
            $variation->save();

            return true;

        } catch (Exception $e) {
            $this->errors[$variation_id] = $e->getMessage();
            return false;
        }
    }

    // Update post data
    private function update_post_data($post_id, $data)
    {
        $post_update = array('ID' => $post_id);

        if (isset($data['title'])) {
            $post_update['post_title'] = sanitize_text_field($data['title']);
        }

        if (isset($data['content'])) {
            $post_update['post_content'] = wp_kses_post($data['content']);
        }

        if (isset($data['excerpt']) || isset($data['short_description'])) {
            $excerpt = isset($data['excerpt']) ? $data['excerpt'] : $data['short_description'];
            $post_update['post_excerpt'] = wp_kses_post($excerpt);
        }

        // ✅ FIXED: Slug با پشتیبانی فارسی
        if (isset($data['slug'])) {
            $slug_decoded = urldecode($data['slug']);

            // بررسی اینکه آیا فارسی است
            if (preg_match('/[\x{0600}-\x{06FF}]/u', $slug_decoded)) {
                // حفظ فارسی و فقط کاراکترهای غیرمجاز را حذف کن
                $post_update['post_name'] = sanitize_text_field($slug_decoded);
                $post_update['post_name'] = str_replace(' ', '-', $post_update['post_name']);
            } else {
                // برای انگلیسی از sanitize_title استفاده کن
                $post_update['post_name'] = sanitize_title($slug_decoded);
            }
        }

        if (isset($data['post_status'])) {
            $post_update['post_status'] = sanitize_key($data['post_status']);
        }

        if (count($post_update) > 1) {
            wp_update_post($post_update);
        }
    }

    // Update meta fields.
    // WooCommerce scalar fields (price, sku, stock…) are written through the
    // WooCommerce API in update_woocommerce_fields(), so they are NOT duplicated
    // here. This only handles arbitrary custom meta passed by integrators.
    private function update_meta_fields($post_id, $data)
    {
        if (isset($data['custom_meta']) && is_array($data['custom_meta'])) {
            foreach ($data['custom_meta'] as $key => $value) {
                update_post_meta($post_id, sanitize_key($key), sanitize_text_field($value));
            }
        }
    }

    // Update taxonomies
    private function update_taxonomies($post_id, $data)
    {
        if (isset($data['categories']) && !empty($data['categories'])) {
            $categories = is_array($data['categories']) ? $data['categories'] : explode(',', $data['categories']);
            $categories = array_map('intval', $categories);
            wp_set_post_terms($post_id, $categories, 'category');
        }

        if (isset($data['tags']) && !empty($data['tags'])) {
            $tags = is_array($data['tags']) ? $data['tags'] : explode(',', $data['tags']);
            wp_set_post_terms($post_id, $tags, 'post_tag');
        }

        // WooCommerce product categories
        if (isset($data['product_cat'])) {
            $cats = is_array($data['product_cat']) ? $data['product_cat'] : explode(',', $data['product_cat']);
            $cats = array_map('intval', $cats);
            wp_set_post_terms($post_id, $cats, 'product_cat');
        }

        // WooCommerce product tags
        if (isset($data['product_tag'])) {
            $tags = is_array($data['product_tag']) ? $data['product_tag'] : explode(',', $data['product_tag']);
            wp_set_post_terms($post_id, $tags, 'product_tag');
        }

        // Custom taxonomies
        if (isset($data['taxonomies']) && is_array($data['taxonomies'])) {
            foreach ($data['taxonomies'] as $taxonomy => $terms) {
                if (taxonomy_exists($taxonomy)) {
                    $terms = is_array($terms) ? $terms : explode(',', $terms);
                    wp_set_post_terms($post_id, $terms, $taxonomy);
                }
            }
        }
    }

    // ✅ COMPLETELY REWRITTEN: Update images
    private function update_images($post_id, $data)
    {
        // Featured image
        if (isset($data['featured_image'])) {
            $attachment_id = intval($data['featured_image']);
            if ($attachment_id) {
                set_post_thumbnail($post_id, $attachment_id);

                // Update alt text
                if (isset($data['featured_image_alt'])) {
                    $alt_text = sanitize_text_field($data['featured_image_alt']);
                } else {
                    $alt_text = get_the_title($post_id);
                }
                $this->image_manager->update_alt_text($attachment_id, $alt_text);
            } else {
                delete_post_thumbnail($post_id);
            }
        }

        // ✅ Gallery images
        if (isset($data['gallery'])) {
            $gallery_ids = $data['gallery'];

            // تبدیل به آرایه
            if (is_string($gallery_ids)) {
                $gallery_ids = explode(',', $gallery_ids);
            }

            // پاکسازی و تبدیل به int
            $gallery_ids = array_map('intval', array_filter($gallery_ids));
            $gallery_ids = array_unique($gallery_ids);

            // ذخیره در دیتابیس
            if (!empty($gallery_ids)) {
                // ✅ استفاده مستقیم از WooCommerce API
                if (function_exists('wc_get_product')) {
                    $product = wc_get_product($post_id);
                    if ($product) {
                        $product->set_gallery_image_ids($gallery_ids);
                        $product->save();

                        // پاک کردن cache
                        wc_delete_product_transients($post_id);
                    }
                } else {
                    // برای پست‌های عادی
                    update_post_meta($post_id, '_product_image_gallery', implode(',', $gallery_ids));
                }

                // ✅ آپدیت alt text برای gallery images
                if (isset($data['gallery_alts']) && is_array($data['gallery_alts'])) {
                    foreach ($gallery_ids as $gallery_id) {
                        if (isset($data['gallery_alts'][$gallery_id])) {
                            $alt_text = sanitize_text_field($data['gallery_alts'][$gallery_id]);
                            $this->image_manager->update_alt_text($gallery_id, $alt_text);
                        }
                    }
                }
            } else {
                // اگر خالی است، گالری را پاک کن
                if (function_exists('wc_get_product')) {
                    $product = wc_get_product($post_id);
                    if ($product) {
                        $product->set_gallery_image_ids(array());
                        $product->save();
                    }
                } else {
                    delete_post_meta($post_id, '_product_image_gallery');
                }
            }
        }
    }

    // ✅ COMPLETELY REWRITTEN: Update WooCommerce fields
    private function update_woocommerce_fields($post_id, $data)
    {
        $product = wc_get_product($post_id);

        if (!$product) {
            return;
        }

        // Product type
        if (isset($data['product_type'])) {
            wp_set_object_terms($post_id, sanitize_text_field($data['product_type']), 'product_type');
        }

        // Prices
        if (isset($data['regular_price'])) {
            $product->set_regular_price(sanitize_text_field($data['regular_price']));
        }

        if (isset($data['sale_price'])) {
            $product->set_sale_price(sanitize_text_field($data['sale_price']));
        }

        // Stock
        if (isset($data['stock_status'])) {
            $product->set_stock_status(sanitize_text_field($data['stock_status']));
        }

        if (isset($data['manage_stock'])) {
            $product->set_manage_stock($data['manage_stock'] === 'yes' || $data['manage_stock'] === '1');
        }

        if (isset($data['stock_quantity'])) {
            $product->set_stock_quantity(intval($data['stock_quantity']));
        }

        // SKU
        if (isset($data['sku'])) {
            $product->set_sku(sanitize_text_field($data['sku']));
        }

        // ✅ Attributes
        if (isset($data['attributes']) && is_array($data['attributes'])) {
            $attributes = array();

            foreach ($data['attributes'] as $attr_name => $attr_data) {
                $attribute = new WC_Product_Attribute();

                // تشخیص نوع attribute (global یا custom)
                if (strpos($attr_name, 'pa_') === 0) {
                    // Global/Taxonomy Attribute
                    $taxonomy_id = wc_attribute_taxonomy_id_by_name($attr_name);

                    if ($taxonomy_id) {
                        $attribute->set_id($taxonomy_id);
                        $attribute->set_name($attr_name);

                        // تبدیل term names به IDs
                        if (isset($attr_data['options'])) {
                            $term_ids = array();
                            $options = is_array($attr_data['options']) ? $attr_data['options'] : array($attr_data['options']);

                            foreach ($options as $term_name) {
                                $term = get_term_by('name', $term_name, $attr_name);
                                if (!$term) {
                                    $term = get_term_by('slug', sanitize_title($term_name), $attr_name);
                                }
                                if ($term) {
                                    $term_ids[] = $term->term_id;
                                }
                            }

                            if (!empty($term_ids)) {
                                $attribute->set_options($term_ids);
                            }
                        }
                    }
                } else {
                    // Custom Attribute
                    $attribute->set_name($attr_name);

                    if (isset($attr_data['options'])) {
                        if (is_array($attr_data['options'])) {
                            $attribute->set_options($attr_data['options']);
                        } elseif (is_string($attr_data['options'])) {
                            $options = array_map('trim', explode('|', $attr_data['options']));
                            $attribute->set_options($options);
                        }
                    }
                }

                // تنظیمات visibility و variation
                $attribute->set_visible(isset($attr_data['visible']) ? (bool)$attr_data['visible'] : true);
                $attribute->set_variation(isset($attr_data['variation']) ? (bool)$attr_data['variation'] : false);

                $attributes[] = $attribute;
            }

            if (!empty($attributes)) {
                $product->set_attributes($attributes);
            }
        }

        // ✅ باید save صدا زده بشه
        $product->save();
    }

    // Update SEO fields
    private function update_seo_fields($post_id, $data)
    {
        $post_handler = new BESM_Post_Handler();
        $post_handler->update_seo_data($post_id, $data);
    }

    // Get results
    public function get_results()
    {
        return array(
            'saved_count' => $this->saved_count,
            'failed_count' => $this->failed_count,
            'errors' => $this->errors
        );
    }
}