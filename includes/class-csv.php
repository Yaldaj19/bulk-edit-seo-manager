<?php
/**
 * CSV import / export handler.
 *
 * Export: streams the currently-filtered rows (ID + enabled fields) as a
 * UTF-8 CSV (with BOM so Excel shows Persian correctly).
 * Import: reads a CSV whose header row uses the same field keys and pushes
 * the rows through the existing BESM_Bulk_Save pipeline.
 */

if (!defined('ABSPATH')) {
    exit;
}

class BESM_CSV
{
    /** Fields that are stored/edited as booleans (checkbox). */
    private $boolean_fields = array('robots_noindex', 'robots_nofollow', 'manage_stock');

    /** Fields that must never be exported/imported as scalars. */
    private $skip_fields = array('url', 'product_attributes');

    private $post_handler;

    public function __construct()
    {
        $this->post_handler = new BESM_Post_Handler();
    }

    /* ----------------------------------------------------------------- *
     *  EXPORT
     * ----------------------------------------------------------------- */

    /**
     * Stream the filtered result set as a CSV download.
     * Called from the `admin_post_besm_export_csv` hook.
     */
    public function export()
    {
        if (!current_user_can('edit_posts')) {
            wp_die(esc_html__('دسترسی غیرمجاز', 'bulk-edit-seo'), '', array('response' => 403));
        }

        check_admin_referer('besm_export_csv');

        $settings    = get_option('besm_settings', array());
        $post_type   = isset($settings['active_post_type']) ? $settings['active_post_type'] : '';
        $enabled     = isset($settings['enabled_fields']) && is_array($settings['enabled_fields']) ? $settings['enabled_fields'] : array();

        if (empty($post_type)) {
            wp_die(esc_html__('ابتدا یک نوع پست را در تنظیمات انتخاب کنید.', 'bulk-edit-seo'));
        }

        // Columns: ID first, then every exportable enabled field.
        $columns = array('ID');
        foreach ($enabled as $field) {
            if (in_array($field, $this->skip_fields, true)) {
                continue;
            }
            $columns[] = $field;
            if ($field === 'featured_image') {
                $columns[] = 'featured_image_alt';
            }
        }

        // Build the query from the current request filters (no pagination).
        $filters              = $this->parse_filters();
        $filters['per_page']  = -1;
        $filters['paged']     = 1;

        $builder = new BESM_Filters($post_type);
        $args    = $builder->build_query_args($filters);
        $args['posts_per_page'] = -1;
        $args['fields']         = 'ids';

        $query_ids = get_posts($args);

        // Signal the browser (JS spinner) that the download is starting.
        if (!empty($_GET['besm_dl'])) {
            setcookie('besm_download', sanitize_text_field(wp_unslash($_GET['besm_dl'])), time() + 60, '/');
        }

        // Send download headers.
        $filename = $post_type . '-' . gmdate('Y-m-d-His') . '.csv';
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');

        // UTF-8 BOM for Excel.
        fwrite($out, "\xEF\xBB\xBF");

        fputcsv($out, $columns);

        foreach ($query_ids as $post_id) {
            $data = $this->post_handler->get_post_data($post_id, $enabled);
            if (!$data) {
                continue;
            }

            $row = array();
            foreach ($columns as $col) {
                $row[] = $this->cell_value($col, $data, $post_id);
            }
            fputcsv($out, $row);
        }

        fclose($out);
        exit;
    }

    /** Flatten one field value into a CSV cell string. */
    private function cell_value($col, $data, $post_id)
    {
        if ($col === 'ID') {
            return $post_id;
        }

        if ($col === 'featured_image') {
            if (is_array($data['featured_image'] ?? null) && isset($data['featured_image']['id'])) {
                return $data['featured_image']['id'];
            }
            return '';
        }

        if ($col === 'featured_image_alt') {
            if (is_array($data['featured_image'] ?? null) && isset($data['featured_image']['alt'])) {
                return $data['featured_image']['alt'];
            }
            return '';
        }

        if ($col === 'gallery') {
            if (!empty($data['gallery']) && is_array($data['gallery'])) {
                return implode(',', wp_list_pluck($data['gallery'], 'id'));
            }
            return '';
        }

        $value = isset($data[$col]) ? $data[$col] : '';

        // Taxonomy: value is an array of term objects -> join names with "|".
        if (is_array($value)) {
            if (!empty($value) && is_object($value[0]) && isset($value[0]->name)) {
                return implode('|', wp_list_pluck($value, 'name'));
            }
            return '';
        }

        // Booleans -> 1 / 0.
        if (in_array($col, $this->boolean_fields, true)) {
            return (!empty($value) && $value !== 'no') ? '1' : '0';
        }

        return (string) $value;
    }

    /* ----------------------------------------------------------------- *
     *  IMPORT
     * ----------------------------------------------------------------- */

    /**
     * Parse an uploaded CSV and run it through BESM_Bulk_Save.
     * Called from the `wp_ajax_besm_import_csv` hook.
     *
     * @return array { success, saved_count, failed_count, skipped, errors }
     */
    public function import($file)
    {
        if (empty($file) || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return array('success' => false, 'message' => esc_html__('فایلی آپلود نشده است.', 'bulk-edit-seo'));
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            return array('success' => false, 'message' => esc_html__('فقط فایل CSV مجاز است.', 'bulk-edit-seo'));
        }

        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            return array('success' => false, 'message' => esc_html__('خواندن فایل ممکن نشد.', 'bulk-edit-seo'));
        }

        // Read header, stripping a UTF-8 BOM if present.
        $header = fgetcsv($handle);
        if (!$header || !is_array($header)) {
            fclose($handle);
            return array('success' => false, 'message' => esc_html__('فایل خالی یا نامعتبر است.', 'bulk-edit-seo'));
        }
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
        $header    = array_map('trim', $header);

        $id_index = array_search('ID', $header, true);
        if ($id_index === false) {
            fclose($handle);
            return array('success' => false, 'message' => esc_html__('ستون «ID» در فایل پیدا نشد. از همان ساختار خروجی CSV استفاده کنید.', 'bulk-edit-seo'));
        }

        $posts_data = array();
        $skipped    = 0;

        while (($cells = fgetcsv($handle)) !== false) {
            if (count(array_filter($cells, 'strlen')) === 0) {
                continue; // blank line
            }

            $post_id = isset($cells[$id_index]) ? intval($cells[$id_index]) : 0;
            if (!$post_id) {
                $skipped++;
                continue;
            }

            $fields = array();

            foreach ($header as $i => $key) {
                if ($key === 'ID' || !isset($cells[$i])) {
                    continue;
                }

                $value = $cells[$i];

                // Taxonomy column -> resolve names to existing term IDs.
                if (taxonomy_exists($key)) {
                    $ids = $this->resolve_term_ids($value, $key);
                    if (!empty($ids)) {
                        if (!isset($fields['taxonomies'])) {
                            $fields['taxonomies'] = array();
                        }
                        $fields['taxonomies'][$key] = $ids;
                    }
                    continue;
                }

                // Booleans.
                if (in_array($key, $this->boolean_fields, true)) {
                    $truthy = in_array(strtolower(trim($value)), array('1', 'yes', 'true', 'بله'), true);
                    if ($key === 'manage_stock') {
                        $fields[$key] = $truthy ? 'yes' : 'no';
                    } else {
                        $fields[$key] = $truthy ? '1' : '';
                    }
                    continue;
                }

                $fields[$key] = $value;
            }

            if (!empty($fields)) {
                $posts_data[$post_id] = $fields;
            }
        }

        fclose($handle);

        if (empty($posts_data)) {
            return array('success' => false, 'message' => esc_html__('هیچ ردیف معتبری برای ذخیره پیدا نشد.', 'bulk-edit-seo'));
        }

        $saver   = new BESM_Bulk_Save();
        $results = $saver->save_posts($posts_data);

        return array(
            'success'      => $results['success'],
            'saved_count'  => $results['saved_count'],
            'failed_count' => $results['failed_count'],
            'skipped'      => $skipped,
            'errors'       => $results['errors'],
        );
    }

    /** Convert a "Name A|Name B" cell to existing term IDs for a taxonomy. */
    private function resolve_term_ids($value, $taxonomy)
    {
        $ids = array();
        $names = array_filter(array_map('trim', explode('|', (string) $value)), 'strlen');

        foreach ($names as $name) {
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

    /* ----------------------------------------------------------------- *
     *  Shared: read filters from the current request (export only).
     * ----------------------------------------------------------------- */

    private function parse_filters()
    {
        $filters = array();

        if (!empty($_GET['search'])) {
            $filters['search'] = sanitize_text_field(wp_unslash($_GET['search']));
        }

        $filters['post_status'] = isset($_GET['post_status'])
            ? sanitize_text_field(wp_unslash($_GET['post_status']))
            : 'any';

        if (!empty($_GET['product_type'])) {
            $filters['product_type'] = sanitize_text_field(wp_unslash($_GET['product_type']));
        }

        if (isset($_GET['taxonomies']) && is_array($_GET['taxonomies'])) {
            $filters['taxonomies'] = array();
            foreach (wp_unslash($_GET['taxonomies']) as $tax => $terms) {
                if (!empty($terms)) {
                    $filters['taxonomies'][sanitize_key($tax)] = array_map('intval', (array) $terms);
                }
            }
        }

        if (isset($_GET['meta']) && is_array($_GET['meta'])) {
            $filters['meta'] = array();
            foreach (wp_unslash($_GET['meta']) as $key => $value) {
                if (is_array($value) || $value === '') {
                    continue;
                }
                $filters['meta'][sanitize_key($key)] = sanitize_text_field($value);
            }
        }

        return $filters;
    }
}
