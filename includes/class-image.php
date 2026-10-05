<?php
/**
 * Image management class - ENHANCED SECURITY
 */

if (!defined('ABSPATH')) {
    exit;
}

class BESM_Image
{

    // Upload and replace image
    public function upload_and_replace($post_id, $file, $image_type = 'featured')
    {
        if (!$post_id || empty($file)) {
            return array('success' => false, 'message' => __('اطلاعات نامعتبر است', 'bulk-edit-seo'));
        }

        // FIXED: Enhanced validation
        $validation = $this->validate_image($file);
        if (is_wp_error($validation)) {
            return array('success' => false, 'message' => $validation->get_error_message());
        }

        // Get old attachment
        $old_attachment_id = 0;
        if ($image_type === 'featured') {
            $old_attachment_id = get_post_thumbnail_id($post_id);
        }

        // Delete old attachment with same filename
        if ($old_attachment_id) {
            $this->delete_attachment_and_files($old_attachment_id);
        } else {
            // Check for attachments with same filename
            $this->delete_by_filename($file['name'], $post_id);
        }

        // Upload new image
        $attachment_id = $this->handle_upload($file, $post_id);

        if (is_wp_error($attachment_id)) {
            return array('success' => false, 'message' => $attachment_id->get_error_message());
        }

        // Set as featured image
        if ($image_type === 'featured') {
            set_post_thumbnail($post_id, $attachment_id);

            // Get post title for alt text
            $post_title = get_the_title($post_id);
            update_post_meta($attachment_id, '_wp_attachment_image_alt', $post_title);
        }

        $image_url = wp_get_attachment_image_url($attachment_id, 'thumbnail');

        return array(
            'success' => true,
            'message' => __('تصویر با موفقیت آپلود شد', 'bulk-edit-seo'),
            'attachment_id' => $attachment_id,
            'image_url' => $image_url
        );
    }

    // Handle file upload
    private function handle_upload($file, $post_id)
    {
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        // Handle upload
        $upload = wp_handle_upload($file, array('test_form' => false));

        if (isset($upload['error'])) {
            return new WP_Error('upload_error', $upload['error']);
        }

        // Prepare attachment data
        $attachment = array(
            'post_mime_type' => $upload['type'],
            'post_title' => sanitize_file_name(pathinfo($upload['file'], PATHINFO_FILENAME)),
            'post_content' => '',
            'post_status' => 'inherit'
        );

        // Insert attachment
        $attachment_id = wp_insert_attachment($attachment, $upload['file'], $post_id);

        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        // Generate metadata
        $attach_data = wp_generate_attachment_metadata($attachment_id, $upload['file']);
        wp_update_attachment_metadata($attachment_id, $attach_data);

        return $attachment_id;
    }

    // Delete attachment and all files
    private function delete_attachment_and_files($attachment_id)
    {
        if (!$attachment_id) {
            return false;
        }

        $file_path = get_attached_file($attachment_id);
        $metadata = wp_get_attachment_metadata($attachment_id);

        // Delete attachment from database
        wp_delete_attachment($attachment_id, true);

        // Delete all size files
        if ($file_path && file_exists($file_path)) {
            @unlink($file_path);

            // Delete all sizes
            if (!empty($metadata['sizes']) && is_array($metadata['sizes'])) {
                $base_dir = dirname($file_path);

                foreach ($metadata['sizes'] as $size => $size_data) {
                    $size_file = $base_dir . '/' . $size_data['file'];
                    if (file_exists($size_file)) {
                        @unlink($size_file);
                    }
                }
            }
        }

        return true;
    }

    // Delete by filename
    private function delete_by_filename($filename, $post_id)
    {
        global $wpdb;

        $filename_clean = sanitize_file_name($filename);
        $filename_without_ext = pathinfo($filename_clean, PATHINFO_FILENAME);

        // Find attachments with similar filenames
        $attachments = $wpdb->get_results($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} 
            WHERE post_type = 'attachment' 
            AND post_parent = %d 
            AND post_title LIKE %s",
            $post_id,
            '%' . $wpdb->esc_like($filename_without_ext) . '%'
        ));

        if ($attachments) {
            foreach ($attachments as $attachment) {
                $this->delete_attachment_and_files($attachment->ID);
            }
        }
    }

    // Remove image
    public function remove_image($post_id, $attachment_id, $image_type = 'featured')
    {
        if (!$post_id || !$attachment_id) {
            return array('success' => false, 'message' => __('اطلاعات نامعتبر است', 'bulk-edit-seo'));
        }

        // Remove featured image relationship
        if ($image_type === 'featured') {
            delete_post_thumbnail($post_id);
        }

        // Delete attachment
        $this->delete_attachment_and_files($attachment_id);

        return array(
            'success' => true,
            'message' => __('تصویر با موفقیت حذف شد', 'bulk-edit-seo')
        );
    }

    // Update alt text
    public function update_alt_text($attachment_id, $alt_text)
    {
        if (!$attachment_id) {
            return false;
        }

        update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($alt_text));
        return true;
    }

    // Get alt text
    public function get_alt_text($attachment_id)
    {
        if (!$attachment_id) {
            return '';
        }

        return get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
    }

    // Handle gallery images
    public function update_gallery($post_id, $attachment_ids)
    {
        if (!$post_id) {
            return false;
        }

        // ✅ اطمینان از اینکه آرایه است
        if (!is_array($attachment_ids)) {
            $attachment_ids = array();
        }

        // ✅ فیلتر و پاکسازی
        $attachment_ids = array_filter(array_map('intval', $attachment_ids));
        $attachment_ids = array_unique($attachment_ids);

        // For WooCommerce products
        if (function_exists('wc_get_product')) {
            $product = wc_get_product($post_id);
            if ($product) {
                // ✅ CRITICAL FIX: باید حتما save بشه
                $product->set_gallery_image_ids($attachment_ids);
                $product->save();

                // ✅ ADDED: Clear cache
                wc_delete_product_transients($post_id);

                return true;
            }
        }

        // For regular posts
        if (empty($attachment_ids)) {
            delete_post_meta($post_id, '_product_image_gallery');
        } else {
            update_post_meta($post_id, '_product_image_gallery', implode(',', $attachment_ids));
        }

        return true;
    }

// Get gallery images
    public function get_gallery_images($post_id)
    {
        if (!$post_id) {
            return array();
        }

        // For WooCommerce products
        if (function_exists('wc_get_product')) {
            $product = wc_get_product($post_id);
            if ($product) {
                $gallery_ids = $product->get_gallery_image_ids();
                return is_array($gallery_ids) ? $gallery_ids : array();
            }
        }

        // For regular posts
        $gallery = get_post_meta($post_id, '_product_image_gallery', true);
        if ($gallery) {
            $ids = explode(',', $gallery);
            return array_filter(array_map('intval', $ids));
        }

        return array();
    }

    // Clean up orphaned images for a post
    public function cleanup_orphaned_images($post_id)
    {
        global $wpdb;

        $featured_id = get_post_thumbnail_id($post_id);
        $gallery_ids = $this->get_gallery_images($post_id);

        $keep_ids = array_merge(array($featured_id), $gallery_ids);
        $keep_ids = array_filter($keep_ids);

        if (empty($keep_ids)) {
            return;
        }

        // Get all attachments for this post
        $all_attachments = $wpdb->get_results($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} 
            WHERE post_type = 'attachment' 
            AND post_parent = %d",
            $post_id
        ));

        if ($all_attachments) {
            foreach ($all_attachments as $attachment) {
                if (!in_array($attachment->ID, $keep_ids)) {
                    $this->delete_attachment_and_files($attachment->ID);
                }
            }
        }
    }

    // FIXED: Enhanced validation with real content check
    public function validate_image($file)
    {
        // Check if file exists
        if (!isset($file['tmp_name']) || !file_exists($file['tmp_name'])) {
            return new WP_Error('file_not_found', __('فایل یافت نشد', 'bulk-edit-seo'));
        }

        // Check file size
        $max_size = 10 * 1024 * 1024; // 10MB
        if ($file['size'] > $max_size) {
            return new WP_Error('file_too_large', __('حجم فایل نباید بیشتر از 10 مگابایت باشد.', 'bulk-edit-seo'));
        }

        // Check MIME type
        $allowed_types = array('image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp');
        $file_type = $file['type'];

        if (!in_array($file_type, $allowed_types)) {
            return new WP_Error('invalid_type', __('فرمت تصویر معتبر نیست. فقط JPG, PNG, GIF و WebP مجاز است.', 'bulk-edit-seo'));
        }

        // ENHANCED: Check actual file content using getimagesize
        $image_info = @getimagesize($file['tmp_name']);

        if ($image_info === false) {
            return new WP_Error('invalid_image', __('فایل آپلود شده یک تصویر معتبر نیست.', 'bulk-edit-seo'));
        }

        // Verify MIME type matches actual content
        $real_mime = $image_info['mime'];
        if (!in_array($real_mime, $allowed_types)) {
            return new WP_Error('mime_mismatch', __('نوع واقعی فایل با پسوند آن مطابقت ندارد.', 'bulk-edit-seo'));
        }

        // Check minimum dimensions (optional)
        $min_width = 50;
        $min_height = 50;

        if ($image_info[0] < $min_width || $image_info[1] < $min_height) {
            return new WP_Error('dimensions_too_small', sprintf(__('ابعاد تصویر باید حداقل %1$dx%2$d پیکسل باشد.', 'bulk-edit-seo'), $min_width, $min_height));
        }

        // Check for common malicious patterns in filename
        $filename = $file['name'];
        $dangerous_extensions = array('.php', '.exe', '.sh', '.bat', '.cmd', '.com');

        foreach ($dangerous_extensions as $ext) {
            if (stripos($filename, $ext) !== false) {
                return new WP_Error('dangerous_file', __('نام فایل حاوی پسوند غیرمجاز است.', 'bulk-edit-seo'));
            }
        }

        return true;
    }
}