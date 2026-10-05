/**
 * Settings page JavaScript - FIXED COMPLETE
 */

(function ($) {
    'use strict';

    const BESMSettings = {

        init: function () {
            this.bindEvents();
            this.checkPostTypeSelection();
        },

        bindEvents: function () {
            // Post type change handler
            $('input[name="active_post_type"]').on('change', this.handlePostTypeChange.bind(this));

            // Field checkbox toggle
            $(document).on('change', 'input[name="enabled_fields[]"]', function () {
                $(this).closest('.besm-field-card').toggleClass('active', this.checked);
            });

            // Post type card toggle
            $(document).on('change', 'input[name="active_post_type"]', function () {
                $('.besm-post-type-card').removeClass('active');
                $(this).closest('.besm-post-type-card').addClass('active');
            });
        },

        checkPostTypeSelection: function () {
            const postType = $('input[name="active_post_type"]:checked').val();

            if (postType) {
                $('#fields-section').slideDown();
            } else {
                $('#fields-section').slideUp();
            }
        },

        handlePostTypeChange: function (e) {
            const postType = $(e.target).val();

            if (!postType) {
                $('#fields-section').slideUp();
                return;
            }

            this.loadPostTypeFields(postType);
        },

        loadPostTypeFields: function (postType) {
            const $fieldsContainer = $('#available-fields');
            const $fieldsSection = $('#fields-section');

            // Show loading state
            $fieldsContainer.html('<div class="besm-loading-state"><span class="dashicons dashicons-update spin"></span><p>' + besmSettings.strings.loadingFields + '</p></div>');
            $fieldsSection.slideDown();

            $.ajax({
                url: besmSettings.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'besm_get_post_type_fields',
                    nonce: besmSettings.nonce,
                    post_type: postType
                },
                success: function (response) {
                    if (response.success && response.data.fields) {
                        BESMSettings.renderFields(response.data.fields, response.data.enabled_fields || []);
                    } else {
                        $fieldsContainer.html('<div class="besm-no-fields"><span class="dashicons dashicons-warning"></span><p>' + besmSettings.strings.noFieldsFound + '</p></div>');
                    }
                },
                error: function () {
                    $fieldsContainer.html('<div class="besm-no-fields besm-error"><span class="dashicons dashicons-dismiss"></span><p>' + besmSettings.strings.loadError + '</p></div>');
                }
            });
        },

        // ✅ FIXED: اضافه کردن enabled_fields به عنوان پارامتر
        renderFields: function (fields, enabledFields) {
            const $fieldsContainer = $('#available-fields');
            let html = '';

            // ✅ FIXED: لیبل‌های کامل و درست فارسی
            const fieldLabels = {
                'title': 'عنوان',
                'content': 'محتوا',
                'excerpt': 'خلاصه',
                'featured_image': 'تصویر شاخص',
                'gallery': 'گالری تصاویر',
                'url': 'پیوند یکتا (URL)',
                'slug': 'نامک',  // ✅ اضافه شد
                'post_status': 'وضعیت انتشار',
                'categories': 'دسته‌بندی‌ها',
                'tags': 'برچسب‌ها',
                'product_cat': 'دسته‌بندی محصول',
                'product_tag': 'برچسب محصول',
                'product_type': 'نوع محصول',
                'regular_price': 'قیمت',
                'sale_price': 'قیمت فروش ویژه',
                'sku': 'شناسه محصول (SKU)',
                'stock_status': 'وضعیت موجودی',
                'manage_stock': 'مدیریت موجودی',
                'stock_quantity': 'تعداد موجودی',
                'product_attributes': 'ویژگی‌های محصول',
                'short_description': 'توضیحات کوتاه',
                'yoast_title': 'عنوان سئو (Yoast)',
                'yoast_description': 'توضیحات سئو (Yoast)',
                'yoast_focus_keyword': 'کلمه کلیدی (Yoast)',
                'yoast_canonical': 'لینک کانونیکال (Yoast)',
                'rankmath_title': 'عنوان سئو (Rank Math)',
                'rankmath_description': 'توضیحات سئو (Rank Math)',
                'rankmath_focus_keyword': 'کلمه کلیدی (Rank Math)',
                'rankmath_canonical': 'لینک کانونیکال (Rank Math)',
                'seopress_title': 'عنوان سئو (SEOPress)',
                'seopress_description': 'توضیحات سئو (SEOPress)',
                'seopress_focus_keyword': 'کلمه کلیدی (SEOPress)',
                'seopress_canonical': 'لینک کانونیکال (SEOPress)',
                'og_title': 'عنوان OpenGraph',
                'og_description': 'توضیحات OpenGraph',
                'twitter_title': 'عنوان توییتر',
                'twitter_description': 'توضیحات توییتر',
                'robots_noindex': 'No Index',
                'robots_nofollow': 'No Follow'
            };

            $.each(fields, function (key, field) {
                const label = fieldLabels[key] || key;
                const type = field.type || '';

                // ✅ FIXED: چک کردن اینکه آیا فیلد باید checked باشه
                const isChecked = enabledFields.includes(key) ? 'checked' : '';
                const isActive = enabledFields.includes(key) ? 'active' : '';

                html += '<label class="besm-field-card ' + isActive + '">';
                html += '<input type="checkbox" name="enabled_fields[]" value="' + key + '" ' + isChecked + '>';
                html += '<span class="besm-checkbox-custom">';
                html += '<svg width="12" height="10" viewBox="0 0 12 10" fill="none">';
                html += '<path d="M1 5L4.5 8.5L11 1.5" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>';
                html += '</svg></span>';
                html += '<div class="besm-field-info">';
                html += '<span class="besm-field-label">' + label + '</span>';
                if (type) {
                    html += '<span class="besm-field-type">' + type + '</span>';
                }
                html += '</div></label>';
            });

            $fieldsContainer.html(html);
        },

        showNotice: function (type, message) {
            const $notice = $('<div class="besm-notice ' + type + '"></div>');

            let icon = 'info';
            if (type === 'success') icon = 'yes-alt';
            if (type === 'error') icon = 'dismiss';
            if (type === 'warning') icon = 'warning';

            $notice.html('<span class="dashicons dashicons-' + icon + '"></span><span>' + message + '</span>');

            $('.besm-settings-wrap h1').after($notice);

            setTimeout(function () {
                $notice.fadeOut(function () {
                    $(this).remove();
                });
            }, 5000);
        }
    };

    // Initialize on document ready
    $(document).ready(function () {
        BESMSettings.init();
    });

})(jQuery);