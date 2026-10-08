<?php
/**
 * Full export / import as a ZIP bundle (CSV + image files) for cross-site transfer.
 *
 * Design notes (hardened for local -> host):
 *  - Image files are stored in the ZIP under ASCII-safe names (images/a1.webp)
 *    with a manifest mapping, so Persian/UTF-8 filenames never break across
 *    Windows -> Linux extraction.
 *  - Only ORIGINAL attachment files are bundled (never -WxH size variants);
 *    the destination regenerates sizes. In-content image URLs (original and
 *    size variants) are rewritten to the destination URLs on import.
 *  - Import re-links images to the post matched by ID, falling back to SLUG
 *    when the ID does not exist on the destination.
 *  - De-duplication: an image already present in the destination media library
 *    (matched by original filename) is reused instead of re-uploaded.
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
        // Always carry slug so the destination can match by slug when IDs differ.
        if (!in_array('slug', $columns, true)) {
            $columns[] = 'slug';
        }

        $upload = wp_get_upload_dir();
        $bundle = array();  // key => ['path','file','orig_name','alt','old_url']
        $byAtt  = array();  // attachment_id => key
        $idx    = array('n' => 0);

        $rows = array($columns);
        foreach ($post_ids as $pid) {
            $data = $this->post_handler->get_post_data($pid, $enabled);
            if (!$data) {
                continue;
            }
            $row = array();
            foreach ($columns as $col) {
                $row[] = $this->export_cell($col, $data, $pid, $bundle, $byAtt, $idx);
            }
            $rows[] = $row;

            if (!empty($data['content'])) {
                $this->register_content_images($data['content'], $upload, $bundle, $byAtt, $idx);
            }
        }

        // CSV into a temp string.
        $fh = fopen('php://temp', 'r+');
        fwrite($fh, "\xEF\xBB\xBF");
        foreach ($rows as $r) {
            fputcsv($fh, $r);
        }
        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);

        $manifest = array(
            'site_url'    => home_url(),
            'upload_url'  => $upload['baseurl'],
            'generated'   => gmdate('c'),
            'images'      => array(),
        );
        foreach ($bundle as $key => $info) {
            $manifest['images'][] = array(
                'key'       => $key,
                'file'      => $info['file'],
                'orig_name' => $info['orig_name'],
                'alt'       => $info['alt'],
                'old_url'   => $info['old_url'],
            );
        }

        $tmp = wp_tempnam('besm-export.zip');
        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            wp_die(esc_html__('ساخت فایل ZIP ممکن نشد.', 'bulk-edit-seo'));
        }
        $zip->addFromString('data.csv', $csv);
        $zip->addFromString('manifest.json', wp_json_encode($manifest));
        foreach ($bundle as $key => $info) {
            if (is_readable($info['path'])) {
                $zip->addFile($info['path'], 'images/' . $info['file']);
            }
        }
        $zip->close();

        // Keep the binary stream clean — never let a stray notice corrupt it.
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

    private function export_cell($col, $data, $pid, &$bundle, &$byAtt, &$idx)
    {
        if ($col === 'ID') {
            return $pid;
        }
        if ($col === 'featured_image') {
            if (is_array($data['featured_image'] ?? null) && !empty($data['featured_image']['id'])) {
                return $this->register_attachment((int) $data['featured_image']['id'], $bundle, $byAtt, $idx);
            }
            return '';
        }
        if ($col === 'featured_image_alt') {
            return is_array($data['featured_image'] ?? null) ? ($data['featured_image']['alt'] ?? '') : '';
        }
        if ($col === 'gallery') {
            if (!empty($data['gallery']) && is_array($data['gallery'])) {
                $keys = array();
                foreach ($data['gallery'] as $g) {
                    if (!empty($g['id'])) {
                        $k = $this->register_attachment((int) $g['id'], $bundle, $byAtt, $idx);
                        if ($k) {
                            $keys[] = $k;
                        }
                    }
                }
                return implode('|', $keys);
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

    /** Bundle an attachment's ORIGINAL file under an ASCII key. Returns the key. */
    private function register_attachment($att_id, &$bundle, &$byAtt, &$idx)
    {
        if (isset($byAtt[$att_id])) {
            return $byAtt[$att_id];
        }
        $path = get_attached_file($att_id);
        if (!$path || !file_exists($path)) {
            return '';
        }
        $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $key  = 'a' . (++$idx['n']);
        $bundle[$key] = array(
            'path'      => $path,
            'file'      => $key . ($ext ? '.' . $ext : ''),
            'orig_name' => wp_basename($path),
            'alt'       => (string) get_post_meta($att_id, '_wp_attachment_image_alt', true),
            'old_url'   => wp_get_attachment_url($att_id),
        );
        $byAtt[$att_id] = $key;
        return $key;
    }

    /** Find attachments referenced inside content and bundle their originals. */
    private function register_content_images($content, $upload, &$bundle, &$byAtt, &$idx)
    {
        // By wp-image-<id> class (most reliable).
        if (preg_match_all('/wp-image-(\d+)/', $content, $m)) {
            foreach (array_unique($m[1]) as $aid) {
                $this->register_attachment((int) $aid, $bundle, $byAtt, $idx);
            }
        }
        // By src URL (strip any -WxH size suffix to resolve the original).
        if (preg_match_all('/src=["\']([^"\']+)["\']/i', $content, $m2)) {
            foreach ($m2[1] as $url) {
                if (strpos($url, $upload['baseurl']) !== 0) {
                    continue;
                }
                $full = preg_replace('/-\d+x\d+(\.\w+)$/', '$1', $url);
                $aid  = attachment_url_to_postid($full);
                if (!$aid) {
                    $aid = attachment_url_to_postid($url);
                }
                if ($aid) {
                    $this->register_attachment((int) $aid, $bundle, $byAtt, $idx);
                }
            }
        }
    }

    /* ============================ IMPORT ============================ */

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
        if (!file_exists($csv_path)) {
            $this->rrmdir($tmp_dir);
            return array('success' => false, 'message' => esc_html__('فایل data.csv در بسته پیدا نشد.', 'bulk-edit-seo'));
        }

        $manifest = file_exists($tmp_dir . '/manifest.json')
            ? json_decode(file_get_contents($tmp_dir . '/manifest.json'), true)
            : array('images' => array());

        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        // Side-load each bundled image once (de-duplicated by original filename).
        // map key => ['id','old_url','new_url']
        $map = array();
        $created = 0;
        if (!empty($manifest['images'])) {
            foreach ($manifest['images'] as $img) {
                if (empty($img['key']) || empty($img['file'])) {
                    continue;
                }
                $src = $tmp_dir . '/images/' . $img['file'];
                if (!file_exists($src)) {
                    continue;
                }
                $orig = isset($img['orig_name']) ? $img['orig_name'] : wp_basename($img['file']);
                $alt  = isset($img['alt']) ? $img['alt'] : '';

                $existing = $this->find_attachment_by_filename($orig);
                if ($existing) {
                    $id = $existing;
                } else {
                    $id = $this->sideload($src, $orig, $alt);
                    if ($id) {
                        $created++;
                    }
                }
                if ($id) {
                    $map[$img['key']] = array(
                        'id'      => $id,
                        'old_url' => isset($img['old_url']) ? $img['old_url'] : '',
                        'new_url' => wp_get_attachment_url($id),
                    );
                }
            }
        }

        // Parse CSV rows.
        $handle = fopen($csv_path, 'r');
        $header = fgetcsv($handle);
        if ($header) {
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
            $header = array_map('trim', $header);
        }
        $id_index   = $header ? array_search('ID', $header, true) : false;
        $slug_index = $header ? array_search('slug', $header, true) : false;
        if ($id_index === false) {
            fclose($handle);
            $this->rrmdir($tmp_dir);
            return array('success' => false, 'message' => esc_html__('ستون ID در data.csv نیست.', 'bulk-edit-seo'));
        }

        $settings   = get_option('besm_settings', array());
        $post_type  = isset($settings['active_post_type']) ? $settings['active_post_type'] : 'post';
        $bool_fields = array('robots_noindex', 'robots_nofollow', 'manage_stock');

        $posts_data = array();
        $not_found  = 0;
        $matched_by_slug = 0;

        while (($cells = fgetcsv($handle)) !== false) {
            if (count(array_filter($cells, 'strlen')) === 0) {
                continue;
            }
            $csv_id = isset($cells[$id_index]) ? intval($cells[$id_index]) : 0;
            $slug   = ($slug_index !== false && isset($cells[$slug_index])) ? trim($cells[$slug_index]) : '';

            // Resolve the real target post on THIS site: by ID, else by slug.
            $target = 0;
            if ($csv_id && get_post($csv_id)) {
                $target = $csv_id;
            } elseif ($slug) {
                $found = get_posts(array('post_type' => $post_type, 'name' => $slug, 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids'));
                if (!empty($found)) {
                    $target = (int) $found[0];
                    $matched_by_slug++;
                }
            }
            if (!$target) {
                $not_found++;
                continue;
            }

            $fields = array();
            foreach ($header as $i => $key) {
                if ($key === 'ID' || !isset($cells[$i])) {
                    continue;
                }
                $val = $cells[$i];

                if ($key === 'featured_image') {
                    // Only set when we have a real mapped image — never clear an existing one.
                    if ($val !== '' && isset($map[$val]['id'])) {
                        $fields['featured_image'] = $map[$val]['id'];
                    }
                } elseif ($key === 'gallery') {
                    $ids = array();
                    foreach (array_filter(explode('|', $val)) as $k) {
                        if (isset($map[$k]['id'])) {
                            $ids[] = $map[$k]['id'];
                        }
                    }
                    if ($ids) {
                        $fields['gallery'] = implode(',', $ids);
                    }
                } elseif ($key === 'content') {
                    $fields['content'] = $this->rewrite_content($val, $map);
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
                $posts_data[$target] = $fields;
            }
        }
        fclose($handle);
        $this->rrmdir($tmp_dir);

        if (empty($posts_data)) {
            return array(
                'success'   => false,
                'message'   => esc_html__('هیچ پستی برای به‌روزرسانی پیدا نشد (نه با ID نه با نامک).', 'bulk-edit-seo'),
                'not_found' => $not_found,
            );
        }

        $saver   = new BESM_Bulk_Save();
        $results = $saver->save_posts($posts_data);

        return array(
            'success'       => $results['success'],
            'saved_count'   => $results['saved_count'],
            'failed_count'  => $results['failed_count'],
            'images'        => $created,
            'not_found'     => $not_found,
            'matched_slug'  => $matched_by_slug,
            'errors'        => $results['errors'],
        );
    }

    /** Side-load a file into the media library with a chosen filename. Returns new attachment id. */
    private function sideload($path, $name, $alt)
    {
        $tmp_copy = wp_tempnam($name);
        copy($path, $tmp_copy);
        $att_id = media_handle_sideload(array('name' => $name, 'tmp_name' => $tmp_copy), 0);
        if (is_wp_error($att_id)) {
            @unlink($tmp_copy);
            return 0;
        }
        if ($alt !== '') {
            update_post_meta($att_id, '_wp_attachment_image_alt', sanitize_text_field($alt));
        }
        return (int) $att_id;
    }

    /** Reuse an existing attachment that has the same original filename. */
    private function find_attachment_by_filename($basename)
    {
        global $wpdb;
        $like = '%/' . $wpdb->esc_like($basename);
        $id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s LIMIT 1",
            $like
        ));
        if ($id) {
            return (int) $id;
        }
        // Exact filename (no subdir) fallback.
        $id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value = %s LIMIT 1",
            $basename
        ));
        return $id ? (int) $id : 0;
    }

    /** Rewrite in-content image URLs (original + size variants) to destination URLs. */
    private function rewrite_content($content, $map)
    {
        if ($content === '' || empty($map)) {
            return $content;
        }
        foreach ($map as $m) {
            if (empty($m['old_url']) || empty($m['new_url'])) {
                continue;
            }
            $old = $m['old_url'];
            $new = $m['new_url'];

            // Size variants first: match {name}-WxH.ext for this original and
            // point them at the destination's regenerated sizes.
            $meta = wp_get_attachment_metadata($m['id']);
            if (!empty($meta['sizes']) && is_array($meta['sizes'])) {
                $old_dir  = dirname($old);
                $new_dir  = dirname($new);
                $old_pi   = pathinfo($old);
                $old_stem = isset($old_pi['filename']) ? $old_pi['filename'] : '';
                $old_ext  = isset($old_pi['extension']) ? $old_pi['extension'] : '';
                foreach ($meta['sizes'] as $size) {
                    if (empty($size['width']) || empty($size['height']) || empty($size['file'])) {
                        continue;
                    }
                    $old_size_url = $old_dir . '/' . $old_stem . '-' . $size['width'] . 'x' . $size['height'] . '.' . $old_ext;
                    $new_size_url = $new_dir . '/' . $size['file'];
                    $content = str_replace($old_size_url, $new_size_url, $content);
                }
            }
            // Then the original URL.
            $content = str_replace($old, $new, $content);
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
