<?php
/**
 * Editor page view - FIXED COMPLETE
 */

if (!defined('ABSPATH')) {
    exit;
}

$post_type_label = $this->post_handler->get_post_type_label($active_post_type);
?>

<div class="wrap besm-wrap">
    <div class="besm-page-header">
        <h1>✏️ <?php echo esc_html( sprintf( __('ویرایش گروهی پست تایپ ها - %s', 'bulk-edit-seo'), $post_type_label ) ); ?></h1>
    </div>

    <!-- Complete Help Section at Top -->
    <div class="besm-help-box">
        <button type="button" class="besm-help-toggle active">
            <span class="dashicons dashicons-info"></span>
            <?php esc_html_e('راهنمای کامل استفاده', 'bulk-edit-seo'); ?>
            <span class="dashicons dashicons-arrow-down-alt2"></span>
        </button>
        <div class="besm-help-content" style="display: block;">
            <div class="besm-help-sections">
                <div class="besm-help-section">
                    <h3>🎯 <?php esc_html_e('شروع کار', 'bulk-edit-seo'); ?></h3>
                    <ul>
                        <li><?php echo wp_kses_post( __('<strong>انتخاب پست‌تایپ:</strong> ابتدا از تنظیمات، نوع پست مورد نظر را انتخاب کنید', 'bulk-edit-seo') ); ?></li>
                        <li><?php echo wp_kses_post( __('<strong>انتخاب فیلدها:</strong> فیلدهایی که می‌خواهید ویرایش کنید را فعال کنید', 'bulk-edit-seo') ); ?></li>
                        <li><?php echo wp_kses_post( __('<strong>فیلتر کردن:</strong> از فیلترها برای یافتن سریع‌تر آیتم‌های خاص استفاده کنید', 'bulk-edit-seo') ); ?></li>
                    </ul>
                </div>

                <div class="besm-help-section">
                    <h3>✏️ <?php esc_html_e('ویرایش', 'bulk-edit-seo'); ?></h3>
                    <ul>
                        <li><?php echo wp_kses_post( __('<strong>ویرایش مستقیم:</strong> فیلدها را مستقیماً در جدول ویرایش کنید', 'bulk-edit-seo') ); ?></li>
                        <li><?php echo wp_kses_post( __('<strong>انتخاب گروهی:</strong> با چک‌باکس «انتخاب همه» می‌توانید چند آیتم را همزمان انتخاب کنید', 'bulk-edit-seo') ); ?></li>
                        <li><?php echo wp_kses_post( __('<strong>عملیات گروهی:</strong> از منوی Bulk Actions برای تغییر وضعیت یا حذف گروهی استفاده کنید', 'bulk-edit-seo') ); ?></li>
                        <li><?php echo wp_kses_post( __('<strong>ذخیره نهایی:</strong> حتماً دکمه «ذخیره تغییرات» را بزنید', 'bulk-edit-seo') ); ?></li>
                    </ul>
                </div>

                <div class="besm-help-section">
                    <h3>🖼️ <?php esc_html_e('مدیریت تصاویر', 'bulk-edit-seo'); ?></h3>
                    <ul>
                        <li><?php echo wp_kses_post( __('<strong>تصویر شاخص:</strong> روی باکس تصویر کلیک کنید تا تصویر جدید انتخاب کنید', 'bulk-edit-seo') ); ?></li>
                        <li><?php echo wp_kses_post( __('<strong>جایگزینی خودکار:</strong> تصویر قدیمی با همان نام به طور خودکار حذف و جایگزین می‌شود', 'bulk-edit-seo') ); ?></li>
                        <li><?php echo wp_kses_post( __('<strong>Alt Text:</strong> متن جایگزین به صورت خودکار از عنوان محصول پر می‌شود', 'bulk-edit-seo') ); ?></li>
                        <li><?php echo wp_kses_post( __('<strong>ویرایش Alt:</strong> می‌توانید Alt Text را دستی ویرایش کنید', 'bulk-edit-seo') ); ?></li>
                        <li><?php echo wp_kses_post( __('<strong>گالری:</strong> روی دکمه + کلیک کنید تا تصاویر گالری اضافه کنید', 'bulk-edit-seo') ); ?></li>
                    </ul>
                </div>

                <div class="besm-help-section">
                    <h3>📦 <?php esc_html_e('محصولات متغیر', 'bulk-edit-seo'); ?></h3>
                    <ul>
                        <li><?php echo wp_kses_post( __('<strong>نمایش Variations:</strong> روی دکمه فلش کنار شناسه کلیک کنید', 'bulk-edit-seo') ); ?></li>
                        <li><?php echo wp_kses_post( __('<strong>ویرایش مستقل:</strong> هر Variation (سایز، رنگ و...) قابل ویرایش جداگانه است', 'bulk-edit-seo') ); ?></li>
                        <li><?php echo wp_kses_post( __('<strong>تصویر Variation:</strong> هر Variation می‌تواند تصویر مخصوص خود را داشته باشد', 'bulk-edit-seo') ); ?></li>
                        <li><?php echo wp_kses_post( __('<strong>قیمت‌گذاری:</strong> قیمت و موجودی هر Variation به صورت مجزا قابل تنظیم است', 'bulk-edit-seo') ); ?></li>
                    </ul>
                </div>

                <div class="besm-help-section">
                    <h3>🔍 <?php esc_html_e('فیلترهای پیشرفته', 'bulk-edit-seo'); ?></h3>
                    <ul>
                        <li><?php echo wp_kses_post( __('<strong>جستجو:</strong> در عنوان و محتوا جستجو کنید', 'bulk-edit-seo') ); ?></li>
                        <li><?php echo wp_kses_post( __('<strong>وضعیت:</strong> فیلتر بر اساس منتشر شده، پیش‌نویس، خصوصی و...', 'bulk-edit-seo') ); ?></li>
                        <li><?php echo wp_kses_post( __('<strong>نوع محصول:</strong> فیلتر محصولات ساده، متغیر، گروهی و...', 'bulk-edit-seo') ); ?></li>
                        <li><?php echo wp_kses_post( __('<strong>تگزونومی:</strong> فیلتر بر اساس دسته‌بندی و برچسب', 'bulk-edit-seo') ); ?></li>
                        <li><?php echo wp_kses_post( __('<strong>متافیلدها:</strong> فیلترهای پیشرفته متافیلدهای سفارشی (دکمه «فیلترهای پیشرفته»)', 'bulk-edit-seo') ); ?></li>
                    </ul>
                </div>

                <div class="besm-help-section">
                    <h3>📊 <?php esc_html_e('ابزارهای پیشرفته (CSV / جایگزینی / کپی ستون)', 'bulk-edit-seo'); ?></h3>
                    <ul>
                        <li><?php echo wp_kses_post( __('<strong>خروجی CSV:</strong> ردیف‌های فیلترشده‌ی فعلی را با ستون فیلدهای انتخابی دانلود می‌کند (با BOM برای نمایش درست فارسی در Excel)', 'bulk-edit-seo') ); ?></li>
                        <li><?php echo wp_kses_post( __('<strong>ورود CSV:</strong> همان فایل را پس از ویرایش در Excel/Sheets آپلود کنید؛ بر اساس ستون <code>ID</code> آیتم‌ها به‌روزرسانی می‌شوند', 'bulk-edit-seo') ); ?></li>
                        <li><?php echo wp_kses_post( __('<strong>جست‌وجو و جایگزینی:</strong> در یک ستون مشخص، یک عبارت را در همه‌ی ردیف‌ها پیدا و جایگزین می‌کند', 'bulk-edit-seo') ); ?></li>
                        <li><?php echo wp_kses_post( __('<strong>کپی به کل ستون (Fill-down):</strong> یک مقدار را روی تمام ردیف‌های یک ستون می‌ریزد', 'bulk-edit-seo') ); ?></li>
                        <li><?php echo wp_kses_post( __('<strong>توجه:</strong> جایگزینی و کپی ستون فقط در مرورگر اعمال می‌شوند؛ برای ثبت نهایی حتماً «ذخیره تغییرات» را بزنید', 'bulk-edit-seo') ); ?></li>
                        <li><?php echo wp_kses_post( __('<strong>انتقال کامل همراه تصاویر (ZIP):</strong> «خروجی کامل» یک فایل ZIP شامل CSV و فایل همه‌ی تصاویر (شاخص، گالری و داخل متن) می‌سازد. در سایت مقصد «ورود کامل» را بزن تا تصاویر در رسانه ساخته و به پست‌ها (بر اساس ID) لینک شوند و آدرس عکس‌های داخل متن بازنویسی شود — بدون نیاز به دسترسی مقصد به سایت مبدأ.', 'bulk-edit-seo') ); ?></li>
                        <li><?php echo wp_kses_post( __('<strong>نکته‌ی ID:</strong> ورود CSV/ZIP آیتم‌ها را بر اساس ستون <code>ID</code> به‌روزرسانی می‌کند؛ پس در سایت مقصد باید پست‌هایی با همان IDها وجود داشته باشند.', 'bulk-edit-seo') ); ?></li>
                    </ul>
                </div>

                <div class="besm-help-section">
                    <h3>🎨 <?php esc_html_e('فیلدهای سئو', 'bulk-edit-seo'); ?></h3>
                    <ul>
                        <li><?php echo wp_kses_post( __('<strong>یکپارچگی کامل:</strong> پلاگین با Yoast SEO، Rank Math و SEOPress کار می‌کند', 'bulk-edit-seo') ); ?></li>
                        <li><?php echo wp_kses_post( __('<strong>شبکه‌های اجتماعی:</strong> ویرایش عنوان و توضیحات OpenGraph و توییتر (بر اساس پلاگین سئوی فعال)', 'bulk-edit-seo') ); ?></li>
                        <li><?php echo wp_kses_post( __('<strong>عنوان سئو:</strong> عنوان متا را برای موتورهای جستجو تنظیم کنید', 'bulk-edit-seo') ); ?></li>
                        <li><?php echo wp_kses_post( __('<strong>توضیحات سئو:</strong> Meta Description برای نتایج گوگل', 'bulk-edit-seo') ); ?></li>
                        <li><?php echo wp_kses_post( __('<strong>کلمه کلیدی:</strong> Focus Keyword خود را تعیین کنید', 'bulk-edit-seo') ); ?></li>
                        <li><?php echo wp_kses_post( __('<strong>No Index/Follow:</strong> برای جلوگیری از ایندکس یا فالو لینک‌ها تیک بزنید', 'bulk-edit-seo') ); ?></li>
                    </ul>
                </div>

                <div class="besm-help-section">
                    <h3>⚠️ <?php esc_html_e('نکات مهم', 'bulk-edit-seo'); ?></h3>
                    <ul>
                        <li><?php echo wp_kses_post( __('<strong>ذخیره تغییرات:</strong> حتماً پس از ویرایش دکمه ذخیره را بزنید', 'bulk-edit-seo') ); ?></li>
                        <li><?php echo wp_kses_post( __('<strong>تصاویر:</strong> تصاویر با نام یکسان جایگزین می‌شوند و قدیمی کاملاً حذف می‌شود', 'bulk-edit-seo') ); ?></li>
                        <li><?php echo wp_kses_post( __('<strong>Variations:</strong> تغییرات روی Variations بلافاصله روی محصول اصلی تاثیر می‌گذارد', 'bulk-edit-seo') ); ?></li>
                        <li><?php echo wp_kses_post( __('<strong>اسکرول:</strong> هر سطر به صورت مستقل اسکرول افقی دارد', 'bulk-edit-seo') ); ?></li>
                        <li><?php echo wp_kses_post( __('<strong>تغییر پست‌تایپ:</strong> برای کار با پست‌تایپ دیگر به تنظیمات بروید', 'bulk-edit-seo') ); ?></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <?php $this->filters->render_filters($current_filters); ?>

    <!-- Editor Wrapper -->
    <div class="besm-editor-wrap">
        <!-- Header with Bulk Actions -->
        <div class="besm-editor-header">
            <div class="besm-header-info">
                <h2>
                    <?php
                    printf(
                            esc_html__('نمایش %1$d تا %2$d از %3$d مورد', 'bulk-edit-seo'),
                            $posts_data['total'] ? (($current_filters['paged'] - 1) * $current_filters['per_page']) + 1 : 0,
                            min($current_filters['paged'] * $current_filters['per_page'], $posts_data['total']),
                            $posts_data['total']
                    );
                    ?>
                </h2>
                <div class="besm-count-chips">
                    <span class="besm-chip">
                        <?php esc_html_e('کل', 'bulk-edit-seo'); ?>:
                        <strong><?php echo esc_html(number_format_i18n($posts_data['total'])); ?></strong>
                    </span>
                    <span class="besm-chip">
                        <?php esc_html_e('این صفحه', 'bulk-edit-seo'); ?>:
                        <strong><?php echo esc_html(number_format_i18n(count($posts_data['posts']))); ?></strong>
                    </span>
                    <span class="besm-chip besm-chip-selected" style="display:none;">
                        <?php esc_html_e('انتخاب‌شده', 'bulk-edit-seo'); ?>:
                        <strong class="besm-chip-selected-count">0</strong>
                    </span>
                </div>
            </div>
            <div class="besm-editor-actions">
                <a href="<?php echo admin_url('admin.php?page=besm-settings'); ?>" class="besm-btn besm-btn-ghost">
                    <span class="dashicons dashicons-admin-settings"></span>
                    <?php esc_html_e('تنظیمات', 'bulk-edit-seo'); ?>
                </a>
                <button type="button" id="besm-bulk-save" class="besm-btn besm-btn-secondary">
                    <span class="dashicons dashicons-cloud-upload"></span>
                    <?php esc_html_e('ذخیره تغییرات', 'bulk-edit-seo'); ?>
                </button>
            </div>
        </div>

        <?php
        // Columns usable by Find/Replace & Fill-down (scalar fields only).
        $besm_editable_cols = array();
        foreach ($enabled_fields as $besm_fk) {
            if (!isset($available_fields[$besm_fk])) {
                continue;
            }
            $besm_ft = isset($available_fields[$besm_fk]['type']) ? $available_fields[$besm_fk]['type'] : 'text';
            if (in_array($besm_ft, array('text', 'textarea', 'number'), true)) {
                $besm_editable_cols[$besm_fk] = $this->get_field_label($besm_fk);
            }
        }

        // Export link carries the current filters (the whole query string) + action + nonce.
        $besm_export_url = add_query_arg(
            array('action' => 'besm_export_csv', '_wpnonce' => wp_create_nonce('besm_export_csv')),
            admin_url('admin-post.php')
        );
        $besm_query = $_SERVER['QUERY_STRING'] ?? '';
        if (!empty($besm_query)) {
            $besm_export_url .= '&' . $besm_query;
        }

        // Full bundle (ZIP: CSV + images) export URL.
        $besm_export_zip_url = add_query_arg(
            array('action' => 'besm_export_zip', '_wpnonce' => wp_create_nonce('besm_export_zip')),
            admin_url('admin-post.php')
        );
        if (!empty($besm_query)) {
            $besm_export_zip_url .= '&' . $besm_query;
        }
        $besm_zip_ok = class_exists('ZipArchive');
        ?>

        <!-- Advanced tools: CSV + Find/Replace + Fill-down -->
        <div class="besm-tools-bar">

            <div class="besm-tool-group">
                <a href="<?php echo esc_url($besm_export_url); ?>" class="besm-btn besm-btn-ghost" title="<?php echo esc_attr__('خروجی CSV از همه‌ی ردیف‌های فیلترشده (در تمام صفحات)', 'bulk-edit-seo'); ?>">
                    <span class="dashicons dashicons-media-spreadsheet"></span>
                    <?php
                    /* translators: %s: total number of items */
                    printf(esc_html__('خروجی CSV (همه: %s)', 'bulk-edit-seo'), esc_html(number_format_i18n($posts_data['total'])));
                    ?>
                </a>
                <button type="button" id="besm-import-csv-btn" class="besm-btn besm-btn-ghost" title="<?php echo esc_attr__('ورود CSV و ذخیره گروهی', 'bulk-edit-seo'); ?>">
                    <span class="dashicons dashicons-upload"></span>
                    <?php esc_html_e('ورود CSV', 'bulk-edit-seo'); ?>
                </button>
                <input type="file" id="besm-import-csv-file" accept=".csv" style="display:none;">
            </div>

            <?php if ($besm_zip_ok): ?>
                <span class="besm-tools-divider" aria-hidden="true"></span>

                <!-- Full bundle: CSV + images (cross-site migration) -->
                <div class="besm-tool-group besm-tool-group-col">
                    <span class="besm-tool-group-label"><?php esc_html_e('انتقال کامل همراه تصاویر (ZIP)', 'bulk-edit-seo'); ?></span>
                    <div class="besm-tool-group-row">
                        <a href="<?php echo esc_url($besm_export_zip_url); ?>" class="besm-btn besm-btn-ghost" title="<?php echo esc_attr__('خروجی کامل: CSV + فایل تصاویر (تصویر شاخص، گالری و عکس‌های داخل متن)', 'bulk-edit-seo'); ?>">
                            <span class="dashicons dashicons-portfolio"></span>
                            <?php esc_html_e('خروجی کامل (ZIP + تصاویر)', 'bulk-edit-seo'); ?>
                        </a>
                        <button type="button" id="besm-import-zip-btn" class="besm-btn besm-btn-ghost" title="<?php echo esc_attr__('ورود بسته‌ی ZIP: تصاویر در رسانه ساخته و لینک می‌شوند', 'bulk-edit-seo'); ?>">
                            <span class="dashicons dashicons-upload"></span>
                            <?php esc_html_e('ورود کامل (ZIP + تصاویر)', 'bulk-edit-seo'); ?>
                        </button>
                        <input type="file" id="besm-import-zip-file" accept=".zip" style="display:none;">
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($besm_editable_cols)): ?>
                <span class="besm-tools-divider" aria-hidden="true"></span>

                <!-- Find & Replace -->
                <div class="besm-tool-group besm-tool-group-col">
                    <span class="besm-tool-group-label"><?php esc_html_e('جست‌وجو و جایگزینی در ستون', 'bulk-edit-seo'); ?></span>
                    <div class="besm-tool-group-row">
                        <select id="besm-fr-field" class="besm-filter-select">
                            <?php foreach ($besm_editable_cols as $ck => $cl): ?>
                                <option value="<?php echo esc_attr($ck); ?>"><?php echo esc_html($cl); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" id="besm-fr-find" class="besm-filter-input" placeholder="<?php echo esc_attr__('پیدا کن...', 'bulk-edit-seo'); ?>" style="width:120px;">
                        <input type="text" id="besm-fr-replace" class="besm-filter-input" placeholder="<?php echo esc_attr__('جایگزین با...', 'bulk-edit-seo'); ?>" style="width:120px;">
                        <button type="button" id="besm-fr-apply" class="besm-btn besm-btn-ghost"><?php esc_html_e('اعمال', 'bulk-edit-seo'); ?></button>
                    </div>
                </div>

                <span class="besm-tools-divider" aria-hidden="true"></span>

                <!-- Fill-down -->
                <div class="besm-tool-group besm-tool-group-col">
                    <span class="besm-tool-group-label"><?php esc_html_e('کپی یک مقدار به کل ستون', 'bulk-edit-seo'); ?></span>
                    <div class="besm-tool-group-row">
                        <select id="besm-fill-field" class="besm-filter-select">
                            <?php foreach ($besm_editable_cols as $ck => $cl): ?>
                                <option value="<?php echo esc_attr($ck); ?>"><?php echo esc_html($cl); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" id="besm-fill-value" class="besm-filter-input" placeholder="<?php echo esc_attr__('مقدار...', 'bulk-edit-seo'); ?>" style="width:160px;">
                        <button type="button" id="besm-fill-apply" class="besm-btn besm-btn-ghost"><?php esc_html_e('اعمال روی همه', 'bulk-edit-seo'); ?></button>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php if (empty($posts_data['posts'])): ?>
            <!-- No Posts Found -->
            <div class="besm-empty-state">
                <span class="dashicons dashicons-search"></span>
                <h3><?php esc_html_e('موردی یافت نشد', 'bulk-edit-seo'); ?></h3>
                <p><?php esc_html_e('لطفاً فیلترهای خود را تغییر دهید یا جستجوی دیگری انجام دهید.', 'bulk-edit-seo'); ?></p>
                <a href="?page=besm-bulk-editor" class="besm-btn besm-btn-primary"><?php esc_html_e('پاک کردن فیلترها', 'bulk-edit-seo'); ?></a>
            </div>
        <?php else: ?>
            <!-- Bulk Actions Bar -->
            <div class="besm-bulk-actions-bar">
                <div class="besm-bulk-select">
                    <label class="besm-checkbox-label">
                        <input type="checkbox" id="besm-select-all">
                        <span><?php esc_html_e('انتخاب همه', 'bulk-edit-seo'); ?></span>
                    </label>
                    <span class="besm-selected-count" style="display: none;">
                        <strong id="besm-count">0</strong> <?php esc_html_e('مورد انتخاب شده', 'bulk-edit-seo'); ?>
                    </span>

                    <?php if ($posts_data['max_pages'] > 1): ?>
                        <span class="besm-select-all-pages-wrap" style="display:none;">
                            <a href="#" id="besm-select-all-pages" data-total="<?php echo esc_attr($posts_data['total']); ?>">
                                <?php
                                /* translators: %s: total number of items across all pages */
                                printf(esc_html__('انتخاب همه‌ی %s مورد در تمام صفحات', 'bulk-edit-seo'), esc_html(number_format_i18n($posts_data['total'])));
                                ?>
                            </a>
                        </span>
                        <span class="besm-all-pages-selected" style="display:none;">
                            <span class="dashicons dashicons-yes-alt"></span>
                            <?php
                            /* translators: %s: total number of items */
                            printf(esc_html__('همه‌ی %s مورد (تمام صفحات) انتخاب شدند.', 'bulk-edit-seo'), '<strong>' . esc_html(number_format_i18n($posts_data['total'])) . '</strong>');
                            ?>
                            <a href="#" id="besm-clear-all-pages"><?php esc_html_e('لغو انتخاب', 'bulk-edit-seo'); ?></a>
                        </span>
                    <?php endif; ?>
                </div>
                <div class="besm-bulk-actions">
                    <select id="besm-bulk-action">
                        <option value=""><?php esc_html_e('عملیات گروهی', 'bulk-edit-seo'); ?></option>
                        <option value="publish"><?php esc_html_e('انتشار', 'bulk-edit-seo'); ?></option>
                        <option value="draft"><?php esc_html_e('پیش‌نویس', 'bulk-edit-seo'); ?></option>
                        <option value="private"><?php esc_html_e('خصوصی', 'bulk-edit-seo'); ?></option>
                        <option value="pending"><?php esc_html_e('در انتظار بررسی', 'bulk-edit-seo'); ?></option>
                        <option value="trash"><?php esc_html_e('انتقال به زباله‌دان', 'bulk-edit-seo'); ?></option>
                    </select>
                    <button type="button" id="besm-apply-bulk" class="besm-btn besm-btn-primary" disabled>
                        <?php esc_html_e('اعمال', 'bulk-edit-seo'); ?>
                    </button>
                </div>
            </div>

            <!-- Table with Row Scroll - REDESIGNED -->
            <div class="besm-table-wrap">
                <form id="besm-bulk-edit-form">
                    <table class="besm-edit-table">
                        <thead>
                        <tr>
                            <!-- ✅ ستون اول: Checkbox - تنها ستون ثابت -->
                            <th class="besm-checkbox-cell">
                                <input type="checkbox" class="besm-select-all-check">
                            </th>

                            <!-- ✅ ستون دوم: Header Scroll Container -->
                            <th>
                                <div class="besm-header-scroll-container" id="besm-header-scroll">
                                    <!-- ID Header -->
                                    <div class="besm-header-cell" data-field="id"><?php esc_html_e('شناسه', 'bulk-edit-seo'); ?></div>

                                    <!-- فیلدهای انتخاب شده -->
                                    <?php foreach ($enabled_fields as $field_key): ?>
                                        <?php if (isset($available_fields[$field_key])): ?>
                                            <div class="besm-header-cell"
                                                 data-field="<?php echo esc_attr($field_key); ?>">
                                                <?php echo esc_html($this->get_field_label($field_key)); ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php $besm_row_number = (($current_filters['paged'] - 1) * $current_filters['per_page']); ?>
                        <?php foreach ($posts_data['posts'] as $post): ?>
                            <?php
                            $post_data = $this->post_handler->get_post_data($post->ID, $enabled_fields);
                            $has_variations = $this->post_has_variations($post->ID);
                            $besm_row_number++;
                            ?>
                            <tr data-post-id="<?php echo esc_attr($post->ID); ?>"
                                class="besm-main-row <?php echo $has_variations ? 'has-variations' : ''; ?>">

                                <!-- Checkbox -->
                                <td class="besm-checkbox-cell">
                                    <input type="checkbox" class="besm-row-checkbox"
                                           value="<?php echo esc_attr($post->ID); ?>">
                                </td>

                                <!-- Scrollable Content -->
                                <td>
                                    <div class="besm-row-scroll-container"
                                         data-row-id="<?php echo esc_attr($post->ID); ?>">
                                        <!-- ✅ ID Field (حالا داخل scroll container است) -->
                                        <div class="besm-field-cell" data-field="id">
                                            <div class="besm-id-wrapper">
                                                <span class="besm-row-num" title="<?php echo esc_attr__('شماره ردیف', 'bulk-edit-seo'); ?>"><?php echo esc_html(number_format_i18n($besm_row_number)); ?></span>
                                                <strong class="besm-post-id" title="<?php echo esc_attr__('شناسه پست', 'bulk-edit-seo'); ?>"><?php echo esc_html($post->ID); ?></strong>

                                                <?php if ($has_variations): ?>
                                                    <button type="button"
                                                            class="besm-toggle-variations"
                                                            data-post-id="<?php echo esc_attr($post->ID); ?>"
                                                            title="<?php echo esc_attr__('نمایش Variations', 'bulk-edit-seo'); ?>">
                                                        <span class="dashicons dashicons-arrow-down-alt2"></span>
                                                        <span class="besm-variations-count">
                                                <?php
                                                $variations = $this->get_post_variations($post->ID);
                                                echo count($variations);
                                                ?>
                                            </span>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- بقیه فیلدها -->
                                        <?php foreach ($enabled_fields as $field_key): ?>
                                            <?php if (isset($available_fields[$field_key])): ?>
                                                <div class="besm-field-cell"
                                                     data-field="<?php echo esc_attr($field_key); ?>">
                                                    <?php
                                                    $field_data = $available_fields[$field_key];
                                                    $current_value = isset($post_data[$field_key]) ? $post_data[$field_key] : '';
                                                    $this->render_field_input($post->ID, $field_key, $field_data, $current_value);
                                                    ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                            </tr>

                            <!-- Variations -->
                            <?php if ($has_variations): ?>
                                <?php
                                $variations = $this->get_post_variations($post->ID);
                                if (!empty($variations)):
                                    ?>
                                    <?php foreach ($variations as $index => $variation): ?>
                                    <?php
                                    $variation_data = $this->post_handler->get_variation_data($variation['id'], $enabled_fields);
                                    ?>

                                    <tr class="besm-variation-row"
                                        data-post-id="<?php echo esc_attr($variation['id']); ?>"
                                        data-parent-id="<?php echo esc_attr($post->ID); ?>"
                                        data-variation-index="<?php echo esc_attr($index); ?>"
                                        style="display: none;">

                                        <!-- Empty Checkbox -->
                                        <td class="besm-checkbox-cell">
                                            <span class="besm-variation-indicator">└─</span>
                                        </td>

                                        <!-- Variation Content -->
                                        <td>
                                            <div class="besm-row-scroll-container"
                                                 data-row-id="<?php echo esc_attr($variation['id']); ?>">
                                                <!-- Variation ID -->
                                                <div class="besm-field-cell" data-field="id">
                                                    <div class="besm-variation-id-wrapper">
                                                        <span class="besm-variation-icon">🔹</span>
                                                        <span class="besm-variation-id"><?php echo esc_html($variation['id']); ?></span>
                                                    </div>
                                                </div>

                                                <!-- Variation Fields -->
                                                <?php foreach ($enabled_fields as $field_key): ?>
                                                    <?php if (isset($available_fields[$field_key])): ?>
                                                        <div class="besm-field-cell besm-variation-field"
                                                             data-field="<?php echo esc_attr($field_key); ?>">

                                                            <?php
                                                            $variation_editable = array(
                                                                    'sku',
                                                                    'regular_price',
                                                                    'sale_price',
                                                                    'stock_quantity',
                                                                    'stock_status',
                                                                    'manage_stock',
                                                                    'featured_image',
                                                                    'description',
                                                                    'content'
                                                            );

                                                            if ($field_key === 'title') {
                                                                echo '<div class="besm-variation-name-display">';
                                                                echo '<strong>' . esc_html($variation['display_name']) . '</strong>';
                                                                echo '</div>';

                                                            } elseif (in_array($field_key, $variation_editable)) {
                                                                $field_data = $available_fields[$field_key];
                                                                $current_value = isset($variation_data[$field_key]) ? $variation_data[$field_key] : '';
                                                                $this->render_field_input($variation['id'], $field_key, $field_data, $current_value);

                                                            } else {
                                                                echo '<span class="besm-variation-na">—</span>';
                                                            }
                                                            ?>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            <?php endif; ?>

                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </form>
            </div>

            <!-- Pagination -->
            <?php if ($posts_data['max_pages'] > 1): ?>
                <div class="besm-pagination">
                    <div class="besm-pagination-info">
                        <?php
                        printf(
                            esc_html__('صفحه %1$s از %2$s', 'bulk-edit-seo'),
                            esc_html($current_filters['paged']),
                            esc_html($posts_data['max_pages'])
                        );
                        ?>
                    </div>
                    <div class="besm-pagination-links">
                        <?php
                        $base_url = remove_query_arg('paged');

                        if ($current_filters['paged'] > 1) {
                            echo '<a href="' . esc_url(add_query_arg('paged', 1, $base_url)) . '">«</a>';
                            echo '<a href="' . esc_url(add_query_arg('paged', $current_filters['paged'] - 1, $base_url)) . '">‹</a>';
                        }

                        $start_page = max(1, $current_filters['paged'] - 2);
                        $end_page = min($posts_data['max_pages'], $current_filters['paged'] + 2);

                        for ($i = $start_page; $i <= $end_page; $i++) {
                            if ($i == $current_filters['paged']) {
                                echo '<span class="current">' . $i . '</span>';
                            } else {
                                echo '<a href="' . esc_url(add_query_arg('paged', $i, $base_url)) . '">' . $i . '</a>';
                            }
                        }

                        if ($current_filters['paged'] < $posts_data['max_pages']) {
                            echo '<a href="' . esc_url(add_query_arg('paged', $current_filters['paged'] + 1, $base_url)) . '">›</a>';
                            echo '<a href="' . esc_url(add_query_arg('paged', $posts_data['max_pages'], $base_url)) . '">»</a>';
                        }
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Floating Action Button -->
<div class="besm-fab" id="besm-fab">
    <span class="besm-fab-badge" id="besm-fab-badge">0</span>
    <button type="button" class="besm-fab-button" id="besm-fab-save" disabled>
        <span class="dashicons dashicons-cloud-upload"></span>
        <span class="besm-fab-text"><?php esc_html_e('ذخیره تغییرات', 'bulk-edit-seo'); ?></span>
    </button>
</div>

<!-- Simple Loading Overlay -->
<div class="besm-loading-overlay" id="besm-loading-overlay">
    <div class="besm-loading-spinner"></div>
</div>