<?php
/**
 * Full export / import as a ZIP bundle (CSV + image files).
 *
 * Solves the cross-site image problem: because a remote host cannot download
 * images from a local (localhost) site, the actual image FILES are packaged
 * inside the ZIP. On import the files are side-loaded into the destination
 * media library, featured/gallery images are re-linked by the new attachment
 * IDs, and in-content image URLs are rewritten to the destination site.
 *
 * ZIP layout:
 *   data.csv          — ID + enabled fields (featured/gallery stored as filenames)
 *   images/<file>     — original image files referenced by the exported posts
 *   manifest.json     — { site_url, upload_url, images:[{file,alt}] }
 */

if (!defined('ABSPATH')) {
    exit;
}

class BESM_Migrate
{
    private $post_handler;

    public function __construct()
    {
        $this->post_handler = new BESM_Post_Handler();
    }

    private function available()
    {
        return class_exists('ZipArchive');
    }

    /* ============================ EXPORT ============================ */

    /** Stream a ZIP bundle of the filtered posts + their images. */
    public function export()
    {
        if (!current_user_can('edit_posts')) {
            wp_die(esc_html__('دسترسی غیرمجاز', 'bulk-edit-seo'), '', array('response' => 403));
        }
        check_admin_referer('besm_export_zip');

        if (!$this->available()) {
            wp_die(esc_html__('افزونه ZipArchive روی سرور فعال نیست.', 'bulk-edit-seo'));
        }

        $settings  = get_option('besm_settings', array());
        $post_type = isset($settings['active_post_type']) ? $settings['active_post_type'] : '';
        $enabled   = isset($settings['enabled_fields']) && is_array($settings['enabled_fields']) ? $settings['enabled_fields'] : array();

        if (empty($post_type)) {
            wp_die(esc_html__('ابتدا یک نوع پست را در تنظیمات انتخاب کنید.', 'bulk-edit-seo'));
        }

        $filters = BESM_Filters::sanitize_request_filters($_GET);
        $filters['per_page'] = -1;
        $builder = new BESM_Filters($post_type);
        $args = $builder->build_query_args($filters);
        $args['posts_per_page'] = -1;
        $args['fields'] = 'ids';
        $post_ids = get_posts($args);

        // Columns: ID + exportable enabled fields.
        $skip = array('url', 'product_attributes');
        $columns = array('ID');
        foreach ($enabled as $f) {
            if (in_array($f, $skip, true)) {
                continue;
            }
            $columns[] = $f;
            if ($f === 'featured_image') {
                $columns[] = 'featured_image_alt';
            }
        }

        $upload_dir = wp_get_upload_dir();
        $images = array();      // basename => absolute file path
        $image_meta = array();  // basename => alt

        // Build CSV rows in memory.
        $rows = array();
        $rows[] = $columns;

        foreach ($post_ids as $pid) {
            $data = $this->post_handler->get_post_data($pid, $enabled);
            if (!$data) {
                continue;
            }

            $row = array();
            foreach ($columns as $col) {
                $row[] = $this->export_cell($col, $data, $pid, $images, $image_meta);
            }
            $rows[] = $row;

            // Collect in-content images that live in this site's uploads.
            if (!empty($data['content'])) {
                $this->collect_content_images($data['content'], $upload_dir, $images, $image_meta);
            }
        }

        // Write CSV to a temp string.
        $fh = fopen('php://temp', 'r+');
        fwrite($fh, "\xEF\xBB\xBF");
        foreach ($rows as $r) {
            fputcsv($fh, $r);
        }
        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);

        // Manifest.
        $manifest = array(
            'site_url'   => home_url(),
            'upload_url' => $upload_dir['baseurl'],
            'generated'  => gmdate('c'),
            'images'     => array(),
        );
        foreach ($images as $base => $path) {
            $manifest['images'][] = array('file' => $base, 'alt' => isset($image_meta[$base]) ? $image_meta[$base] : '');
        }

        // Build ZIP in a temp file.
        $tmp = wp_tempnam('besm-export.zip');
        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            wp_die(esc_html__('ساخت فایل ZIP ممکن نشد.', 'bulk-edit-seo'));
        }
        $zip->addFromString('data.csv', $csv);
        $zip->addFromString('manifest.json', wp_json_encode($manifest));
        foreach ($images as $base => $path) {
            if (is_readable($path)) {
                $zip->addFile($path, 'images/' . $base);
            }
        }
        $zip->close();

        // CRITICAL: discard any buffered output (stray PHP notices/warnings,
        // whitespace, other plugins) so they can't corrupt the binary stream.
        @ini_set('display_errors', '0');
        while (ob_get_level()) {
            ob_end_clean();
        }

        if (!empty($_GET['besm_dl'])) {
            setcookie('besm_download', sanitize_text_field(wp_unslash($_GET['besm_dl'])), time() + 60, '/');
        }

        $filename = $post_type . '-bundle-' . gmdate('Y-m-d-His') . '.zip';
        nocache_headers();
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($tmp));
        readfile($tmp);
        @unlink($tmp);
        exit;
    }

    /** Resolve a CSV cell for export; registers referenced image files. */
    private function export_cell($col, $data, $pid, &$images, &$image_meta)
    {
        if ($col === 'ID') {
            return $pid;
        }

        if ($col === 'featured_image') {
            if (is_array($data['featured_image'] ?? null) && !empty($data['featured_image']['id'])) {
                return $this->register_attachment($data['featured_image']['id'], $images, $image_meta);
            }
            return '';
        }

        if ($col === 'featured_image_alt') {
            return is_array($data['featured_image'] ?? null) ? ($data['featured_image']['alt'] ?? '') : '';
        }

        if ($col === 'gallery') {
            if (!empty($data['gallery']) && is_array($data['gallery'])) {
                $names = array();
                foreach ($data['gallery'] as $g) {
                    if (!empty($g['id'])) {
                        $names[] = $this->register_attachment($g['id'], $images, $image_meta);
                    }
                }
                return implode('|', array_filter($names));
            }
            return '';
        }

        $value = isset($data[$col]) ? $data[$col] : '';
        if (is_array($value)) {
            if (!empty($value) && is_object($value[0]) && isset($value[0]->name)) {
                return implode('|', wp_list_pluck($value, 'name'));
            }
            return '';
        }
        return (string) $value;
    }

    /** Register an attachment's original file; return its basename. */
    private function register_attachment($att_id, &$images, &$image_meta)
    {
        $path = get_attached_file($att_id);
        if (!$path || !file_exists($path)) {
            return '';
        }
        $base = wp_basename($path);
        $images[$base] = $path;
        $image_meta[$base] = get_post_meta($att_id, '_wp_attachment_image_alt', true);
        return $base;
    }

    /** Find <img> files inside content that belong to this site's uploads. */
    private function collect_content_images($content, $upload_dir, &$images, &$image_meta)
    {
        if (!preg_match_all('/src=["\']([^"\']+)["\']/i', $content, $m)) {
            return;
        }
        foreach ($m[1] as $url) {
            if (strpos($url, $upload_dir['baseurl']) !== 0) {
                continue;
            }
            $rel  = ltrim(substr($url, strlen($upload_dir['baseurl'])), '/');
            $path = trailingslashit($upload_dir['basedir']) . $rel;
            if (file_exists($path)) {
                $base = wp_basename($path);
                if (!isset($images[$base])) {
                    $images[$base] = $path;
                }
            }
        }
    }

    /* ============================ IMPORT ============================ */

    /**
     * Import a ZIP bundle: side-load images, re-link featured/gallery,
     * rewrite in-content URLs, then bulk-save every row.
     *
     * @return array result summary
     */
    public function import($file)
    {
        if (!$this->available()) {
            return array('success' => false, 'message' => esc_html__('افزونه ZipArchive روی سرور فعال نیست.', 'bulk-edit-seo'));
        }
        if (empty($file) || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return array('success' => false, 'message' => esc_html__('فایلی آپلود نشده است.', 'bulk-edit-seo'));
        }
        if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'zip') {
            return array('success' => false, 'message' => esc_html__('فقط فایل ZIP مجاز است.', 'bulk-edit-seo'));
        }

        $zip = new ZipArchive();
        if ($zip->open($file['tmp_name']) !== true) {
            return array('success' => false, 'message' => esc_html__('فایل ZIP معتبر نیست.', 'bulk-edit-seo'));
        }

        $tmp_dir = trailingslashit(get_temp_dir()) . 'besm-import-' . wp_generate_password(8, false);
        wp_mkdir_p($tmp_dir);
        $zip->extractTo($tmp_dir);
        $zip->close();

        $csv_path = $tmp_dir . '/data.csv';
        $manifest_path = $tmp_dir . '/manifest.json';
        if (!file_exists($csv_path)) {
            $this->rrmdir($tmp_dir);
            return array('success' => false, 'message' => esc_html__('فایل data.csv در بسته پیدا نشد.', 'bulk-edit-seo'));
        }

        $manifest = file_exists($manifest_path) ? json_decode(file_get_contents($manifest_path), true) : array();
        $alt_map = array();
        if (!empty($manifest['images'])) {
            foreach ($manifest['images'] as $img) {
                if (!empty($img['file'])) {
                    $alt_map[$img['file']] = isset($img['alt']) ? $img['alt'] : '';
                }
            }
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        // Side-load every image once; map basename => new attachment id/url.
        $img_map = array();
        $images_dir = $tmp_dir . '/images';
        if (is_dir($images_dir)) {
            foreach (scandir($images_dir) as $f) {
                if ($f === '.' || $f === '..') {
                    continue;
                }
                $img_map[$f] = $this->sideload($images_dir . '/' . $f, isset($alt_map[$f]) ? $alt_map[$f] : '');
            }
        }

        $new_upload = wp_get_upload_dir();
        $old_upload_url = isset($manifest['upload_url']) ? $manifest['upload_url'] : '';

        // Parse CSV and build posts_data for BESM_Bulk_Save.
        $handle = fopen($csv_path, 'r');
        $header = fgetcsv($handle);
        if ($header) {
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
            $header = array_map('trim', $header);
        }
        $id_index = $header ? array_search('ID', $header, true) : false;
        if ($id_index === false) {
            fclose($handle);
            $this->rrmdir($tmp_dir);
            return array('success' => false, 'message' => esc_html__('ستون ID در data.csv نیست.', 'bulk-edit-seo'));
        }

        $posts_data = array();
        $bool_fields = array('robots_noindex', 'robots_nofollow', 'manage_stock');

        while (($cells = fgetcsv($handle)) !== false) {
            if (count(array_filter($cells, 'strlen')) === 0) {
                continue;
            }
            $pid = isset($cells[$id_index]) ? intval($cells[$id_index]) : 0;
            if (!$pid) {
                continue;
            }

            $fields = array();
            foreach ($header as $i => $key) {
                if ($key === 'ID' || !isset($cells[$i])) {
                    continue;
                }
                $val = $cells[$i];

                if ($key === 'featured_image') {
                    $fields['featured_image'] = ($val !== '' && isset($img_map[$val]['id'])) ? $img_map[$val]['id'] : 0;
                } elseif ($key === 'gallery') {
                    $ids = array();
                    foreach (array_filter(explode('|', $val)) as $base) {
                        if (isset($img_map[$base]['id'])) {
                            $ids[] = $img_map[$base]['id'];
                        }
                    }
                    $fields['gallery'] = implode(',', $ids);
                } elseif ($key === 'content') {
                    $fields['content'] = $this->rewrite_content($val, $old_upload_url, $new_upload['baseurl'], $img_map);
                } elseif (taxonomy_exists($key)) {
                    $tids = $this->resolve_terms($val, $key);
                    if ($tids) {
                        $fields['taxonomies'][$key] = $tids;
                    }
                } elseif (in_array($key, $bool_fields, true)) {
                    $truthy = in_array(strtolower(trim($val)), array('1', 'yes', 'true', 'بله'), true);
                    $fields[$key] = ($key === 'manage_stock') ? ($truthy ? 'yes' : 'no') : ($truthy ? '1' : '');
                } else {
                    $fields[$key] = $val;
                }
            }

            if ($fields) {
                $posts_data[$pid] = $fields;
            }
        }
        fclose($handle);
        $this->rrmdir($tmp_dir);

        if (empty($posts_data)) {
            return array('success' => false, 'message' => esc_html__('هیچ ردیف معتبری برای ذخیره پیدا نشد.', 'bulk-edit-seo'));
        }

        $saver   = new BESM_Bulk_Save();
        $results = $saver->save_posts($posts_data);

        return array(
            'success'      => $results['success'],
            'saved_count'  => $results['saved_count'],
            'failed_count' => $results['failed_count'],
            'images'       => count($img_map),
            'errors'       => $results['errors'],
        );
    }

    /** Side-load one file into the media library. Returns {id,url}. */
    private function sideload($path, $alt)
    {
        // Reuse an existing attachment with the same filename if present.
        $base = wp_basename($path);
        $existing = $this->find_attachment_by_filename($base);
        if ($existing) {
            if ($alt !== '') {
                update_post_meta($existing, '_wp_attachment_image_alt', sanitize_text_field($alt));
            }
            return array('id' => $existing, 'url' => wp_get_attachment_url($existing));
        }

        $tmp_copy = wp_tempnam($base);
        copy($path, $tmp_copy);
        $file_array = array('name' => $base, 'tmp_name' => $tmp_copy);

        $att_id = media_handle_sideload($file_array, 0);
        if (is_wp_error($att_id)) {
            @unlink($tmp_copy);
            return array('id' => 0, 'url' => '');
        }
        if ($alt !== '') {
            update_post_meta($att_id, '_wp_attachment_image_alt', sanitize_text_field($alt));
        }
        return array('id' => $att_id, 'url' => wp_get_attachment_url($att_id));
    }

    private function find_attachment_by_filename($basename)
    {
        global $wpdb;
        $like = '%/' . $wpdb->esc_like($basename);
        $id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s LIMIT 1",
            $like
        ));
        return $id ? (int) $id : 0;
    }

    /** Rewrite in-content image URLs from the source site to this one. */
    private function rewrite_content($content, $old_upload_url, $new_upload_url, $img_map)
    {
        if ($content === '') {
            return $content;
        }

        // Per-file rewrite (handles any renamed basenames too).
        foreach ($img_map as $base => $info) {
            if (empty($info['url']) || $base === '') {
                continue;
            }
            if ($old_upload_url) {
                // Replace the old URL ending in this basename with the new URL.
                $content = preg_replace(
                    '#' . preg_quote($old_upload_url, '#') . '[^"\']*' . preg_quote($base, '#') . '#',
                    $info['url'],
                    $content
                );
            }
        }

        // Fallback: swap the upload base URL wholesale.
        if ($old_upload_url && $old_upload_url !== $new_upload_url) {
            $content = str_replace($old_upload_url, $new_upload_url, $content);
        }

        return $content;
    }

    private function resolve_terms($value, $taxonomy)
    {
        $ids = array();
        foreach (array_filter(array_map('trim', explode('|', (string) $value)), 'strlen') as $name) {
            $term = get_term_by('name', $name, $taxonomy);
            if (!$term) {
                $term = get_term_by('slug', sanitize_title($name), $taxonomy);
            }
            if ($term && !is_wp_error($term)) {
                $ids[] = (int) $term->term_id;
            }
        }
        return array_unique($ids);
    }

    private function rrmdir($dir)
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $f) {
            if ($f === '.' || $f === '..') {
                continue;
            }
            $p = $dir . '/' . $f;
            is_dir($p) ? $this->rrmdir($p) : @unlink($p);
        }
        @rmdir($dir);
    }
}
