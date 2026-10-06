/**
 * Bulk editor JavaScript - FIXED: فقط آیتم‌های تغییر یافته ذخیره میشن
 */

(function ($) {
    'use strict';

    const BESMEditor = {

        mediaFrame: null,
        currentImageTarget: null,
        hasChanges: false,
        changedRows: new Set(), // ✅ ردیف‌هایی که تغییر کردن
        selectAllMatching: false, // انتخاب همه‌ی موارد منطبق در تمام صفحات

        init: function () {
            this.bindEvents();
            this.initImageUpload();
            this.initHeaderSync();
            this.initFAB();
        },

        bindEvents: function () {
            // Bulk save button (header)
            $('#besm-bulk-save').on('click', this.handleBulkSave.bind(this));

            // Copy filename button
            $(document).on('click', '.besm-copy-filename', this.copyFilename.bind(this));

            // Image upload triggers
            $(document).on('click', '.besm-image-preview', this.openMediaUploader.bind(this));

            // Image remove buttons
            $(document).on('click', '.besm-image-remove', this.removeImage.bind(this));

            // Gallery add button
            $(document).on('click', '.besm-gallery-add', this.addGalleryImage.bind(this));

            // Auto-fill alt text from title
            $(document).on('blur', 'input[name*="[title]"]', this.autoFillAlt.bind(this));

            // Toggle variations
            $(document).on('click', '.besm-toggle-variations', this.toggleVariations.bind(this));

            // Toggle help box
            $('.besm-help-toggle').on('click', this.toggleHelp.bind(this));

            // Toggle advanced filters
            $('.besm-toggle-advanced').on('click', this.toggleAdvancedFilters.bind(this));

            // Select all functionality
            $('#besm-select-all, .besm-select-all-check').on('change', this.toggleSelectAll.bind(this));
            $(document).on('change', '.besm-row-checkbox', this.updateBulkCount.bind(this));

            // Select all matching across every page
            $(document).on('click', '#besm-select-all-pages', this.selectAllPages.bind(this));
            $(document).on('click', '#besm-clear-all-pages', this.clearAllPages.bind(this));

            // Apply bulk action
            $('#besm-apply-bulk').on('click', this.applyBulkAction.bind(this));

            // ✅ FIXED: Track changes - بدون نیاز به checkbox
            $('.besm-edit-table').on('change input', 'input, textarea, select', this.trackChanges.bind(this));

            // Warn before leaving if unsaved changes
            this.initUnsavedWarning();

            // FAB click handler
            $('#besm-fab-save').on('click', this.handleFABSave.bind(this));

            // Advanced tools: CSV import, Find & Replace, Fill-down
            $('#besm-import-csv-btn').on('click', function () {
                $('#besm-import-csv-file').trigger('click');
            });
            $('#besm-import-csv-file').on('change', this.handleImportCsv.bind(this));
            $('#besm-fr-apply').on('click', this.applyFindReplace.bind(this));
            $('#besm-fill-apply').on('click', this.applyFillDown.bind(this));

            // Full ZIP bundle import (CSV + images)
            $('#besm-import-zip-btn').on('click', function () {
                $('#besm-import-zip-file').trigger('click');
            });
            $('#besm-import-zip-file').on('change', this.handleImportZip.bind(this));

            // Export links: show a spinner until the download actually starts
            $('.besm-export-link').on('click', this.handleExportClick.bind(this));
        },

        // Loading overlay helpers
        showOverlay: function (text) {
            $('#besm-loading-text').text(text || '');
            $('#besm-loading-overlay').addClass('active');
        },
        hideOverlay: function () {
            $('#besm-loading-overlay').removeClass('active');
        },

        // Export: append a token, show spinner, poll the download cookie set by the server.
        handleExportClick: function (e) {
            const a = e.currentTarget;
            if (!a.dataset.baseHref) {
                a.dataset.baseHref = a.getAttribute('href');
            }
            const base = a.dataset.baseHref;
            const token = 'dl' + new Date().getTime();
            a.setAttribute('href', base + (base.indexOf('?') > -1 ? '&' : '?') + 'besm_dl=' + token);

            this.showOverlay(besmEditor.strings.preparingExport);

            const started = new Date().getTime();
            const self = this;
            const timer = setInterval(function () {
                const hit = document.cookie.indexOf('besm_download=' + token) !== -1;
                if (hit || (new Date().getTime() - started) > 60000) {
                    clearInterval(timer);
                    self.hideOverlay();
                    // clear the cookie
                    document.cookie = 'besm_download=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';
                }
            }, 400);
            // do not preventDefault — let the browser follow the link and download
        },

        // Full ZIP bundle import → side-loads images + bulk save on the server
        handleImportZip: function (e) {
            const input = e.currentTarget;
            if (!input.files || !input.files.length) return;

            if (!confirm(besmEditor.strings.importZipConfirm)) {
                input.value = '';
                return;
            }

            const formData = new FormData();
            formData.append('action', 'besm_import_zip');
            formData.append('nonce', besmEditor.nonce);
            formData.append('zip', input.files[0]);

            this.showOverlay(besmEditor.strings.importingZip);

            $.ajax({
                url: besmEditor.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (response) {
                    if (response.success) {
                        BESMEditor.showNotice('success', response.data.message);
                        setTimeout(function () { location.reload(); }, 1800);
                    } else {
                        $('#besm-loading-overlay').removeClass('active');
                        BESMEditor.showNotice('error', response.data.message || besmEditor.strings.error);
                    }
                },
                error: function () {
                    $('#besm-loading-overlay').removeClass('active');
                    BESMEditor.showNotice('error', besmEditor.strings.error);
                },
                complete: function () {
                    input.value = '';
                }
            });
        },

        // CSV import → bulk save on the server
        handleImportCsv: function (e) {
            const input = e.currentTarget;
            if (!input.files || !input.files.length) return;

            const file = input.files[0];

            if (!confirm(besmEditor.strings.importConfirm)) {
                input.value = '';
                return;
            }

            const formData = new FormData();
            formData.append('action', 'besm_import_csv');
            formData.append('nonce', besmEditor.nonce);
            formData.append('csv', file);

            this.showOverlay(besmEditor.strings.importing);

            $.ajax({
                url: besmEditor.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (response) {
                    if (response.success) {
                        BESMEditor.showNotice('success', response.data.message);
                        setTimeout(function () { location.reload(); }, 1500);
                    } else {
                        $('#besm-loading-overlay').removeClass('active');
                        BESMEditor.showNotice('error', response.data.message || besmEditor.strings.error);
                    }
                },
                error: function () {
                    $('#besm-loading-overlay').removeClass('active');
                    BESMEditor.showNotice('error', besmEditor.strings.error);
                },
                complete: function () {
                    input.value = '';
                }
            });
        },

        // Find & Replace within one column (client-side; needs Save afterwards)
        applyFindReplace: function (e) {
            e.preventDefault();

            const field = $('#besm-fr-field').val();
            const find = $('#besm-fr-find').val();
            const replace = $('#besm-fr-replace').val();

            if (!find) {
                this.showNotice('warning', besmEditor.strings.frEmpty);
                return;
            }

            let count = 0;
            const self = this;

            $('.besm-edit-table [name$="[' + field + ']"]').each(function () {
                const $f = $(this);
                if ($f.is(':checkbox')) return;

                const val = String($f.val());
                if (val.indexOf(find) === -1) return;

                $f.val(val.split(find).join(replace));

                const postId = $f.closest('tr[data-post-id]').data('post-id');
                if (postId) {
                    self.markRowAsChanged(postId);
                    count++;
                }
            });

            this.showNotice(count ? 'success' : 'warning', besmEditor.strings.frDone + ' ' + count);
        },

        // Fill-down: set the whole column to one value (client-side; needs Save afterwards)
        applyFillDown: function (e) {
            e.preventDefault();

            const field = $('#besm-fill-field').val();
            const value = $('#besm-fill-value').val();

            if (!field) {
                this.showNotice('warning', besmEditor.strings.fillEmpty);
                return;
            }

            let count = 0;
            const self = this;

            $('.besm-edit-table [name$="[' + field + ']"]').each(function () {
                const $f = $(this);
                if ($f.is(':checkbox')) return;

                $f.val(value);

                const postId = $f.closest('tr[data-post-id]').data('post-id');
                if (postId) {
                    self.markRowAsChanged(postId);
                    count++;
                }
            });

            this.showNotice('success', besmEditor.strings.frDone + ' ' + count);
        },

        // ✅ Initialize FAB
        initFAB: function () {
            this.updateFAB();
        },

        // ✅ FIXED: Update FAB state
        updateFAB: function () {
            const $fab = $('#besm-fab');
            const $badge = $('#besm-fab-badge');
            const $button = $('#besm-fab-save');

            const changedCount = this.changedRows.size;

            if (changedCount > 0) {
                $fab.addClass('has-changes');
                $badge.text(changedCount);
                $button.prop('disabled', false);
            } else {
                $fab.removeClass('has-changes');
                $badge.text('0');
                $button.prop('disabled', true);
            }
        },

        // ✅ Handle FAB save
        handleFABSave: function (e) {
            e.preventDefault();
            this.handleBulkSave(e);
        },

        // ✅ Sync header با row scrolls
        initHeaderSync: function () {
            const $headerScroll = $('#besm-header-scroll');

            if ($headerScroll.length === 0) {
                console.warn('⚠️ Header scroll container not found');
                return;
            }

            let isHeaderScrolling = false;
            let isRowScrolling = false;

            // وقتی row اسکرول می‌شود → header sync شود
            $(document).on('scroll', '.besm-row-scroll-container', function (e) {
                if (isHeaderScrolling) return;

                isRowScrolling = true;
                const scrollLeft = $(this).scrollLeft();

                requestAnimationFrame(function () {
                    $headerScroll.scrollLeft(scrollLeft);
                    isRowScrolling = false;
                });
            });

            // وقتی header اسکرول می‌شود → همه rows sync شوند
            $headerScroll.on('scroll', function () {
                if (isRowScrolling) return;

                isHeaderScrolling = true;
                const scrollLeft = $(this).scrollLeft();

                requestAnimationFrame(function () {
                    $('.besm-row-scroll-container').scrollLeft(scrollLeft);
                    isHeaderScrolling = false;
                });
            });

        },

        toggleVariations: function (e) {
            e.preventDefault();
            e.stopPropagation();

            const $btn = $(e.currentTarget);
            const postId = $btn.data('post-id');

            const $variationRows = $('tr.besm-variation-row[data-parent-id="' + postId + '"]');

            if ($variationRows.length === 0) {
                console.warn('⚠️ No variations found for post:', postId);
                return;
            }

            // Toggle button state
            const isActive = $btn.hasClass('active');
            $btn.toggleClass('active');

            if (!isActive) {
                // باز کردن variations
                $variationRows.each(function (index) {
                    const $row = $(this);

                    setTimeout(function () {
                        $row.addClass('show').css('display', 'table-row');
                    }, index * 50);
                });
            } else {
                // بستن variations
                $variationRows.removeClass('show');

                setTimeout(function () {
                    $variationRows.css('display', 'none');
                }, 300);
            }
        },

        toggleHelp: function (e) {
            e.preventDefault();
            const $toggle = $(e.currentTarget);
            const $content = $('.besm-help-content');

            $toggle.toggleClass('active');
            $content.slideToggle(300);
        },

        toggleAdvancedFilters: function (e) {
            e.preventDefault();
            const $toggle = $(e.currentTarget);
            const $content = $('.besm-advanced-content');

            $toggle.toggleClass('active');
            $content.slideToggle(300);
        },

        toggleSelectAll: function (e) {
            const isChecked = $(e.currentTarget).prop('checked');
            this.selectAllMatching = false;
            $('.besm-all-pages-selected').hide();
            $('.besm-row-checkbox').prop('checked', isChecked);
            this.updateBulkCount();
        },

        updateBulkCount: function () {
            const count = $('.besm-row-checkbox:checked').length;
            const totalCheckboxes = $('.besm-row-checkbox').length;
            const allOnPage = (count === totalCheckboxes && count > 0);

            // Any partial change cancels the across-pages selection
            if (!allOnPage && this.selectAllMatching) {
                this.selectAllMatching = false;
                $('.besm-all-pages-selected').hide();
            }

            $('#besm-count').text(count);

            const selectedTotal = this.selectAllMatching
                ? ($('#besm-select-all-pages').data('total') || count)
                : count;
            $('.besm-chip-selected-count').text(selectedTotal);

            if (count > 0 || this.selectAllMatching) {
                $('.besm-selected-count').show();
                $('.besm-chip-selected').show();
                $('#besm-apply-bulk').prop('disabled', false);
            } else {
                $('.besm-selected-count').hide();
                $('.besm-chip-selected').hide();
                $('#besm-apply-bulk').prop('disabled', true);
            }

            $('#besm-select-all, .besm-select-all-check').prop('checked', allOnPage);

            // Offer "select all across pages" once the whole page is selected
            if (allOnPage && !this.selectAllMatching) {
                $('.besm-select-all-pages-wrap').show();
            } else {
                $('.besm-select-all-pages-wrap').hide();
            }
        },

        selectAllPages: function (e) {
            e.preventDefault();
            this.selectAllMatching = true;
            $('.besm-select-all-pages-wrap').hide();
            $('.besm-all-pages-selected').show();
            this.updateBulkCount();
        },

        clearAllPages: function (e) {
            e.preventDefault();
            this.selectAllMatching = false;
            $('.besm-all-pages-selected').hide();
            $('.besm-row-checkbox, #besm-select-all, .besm-select-all-check').prop('checked', false);
            this.updateBulkCount();
        },

        applyBulkAction: function (e) {
            e.preventDefault();

            const action = $('#besm-bulk-action').val();

            if (!action) {
                alert(besmEditor.strings.selectActionItem);
                return;
            }

            const data = {
                action: 'besm_bulk_action',
                nonce: besmEditor.nonce,
                bulk_action: action
            };
            let confirmCount;

            if (this.selectAllMatching) {
                // Act on EVERY matching item across all pages (server re-runs the filter)
                data.select_all_matching = 1;
                data.filters_qs = window.location.search;
                confirmCount = $('#besm-select-all-pages').data('total') || $('.besm-row-checkbox').length;
            } else {
                const selectedIds = [];
                $('.besm-row-checkbox:checked').each(function () {
                    selectedIds.push($(this).val());
                });

                if (selectedIds.length === 0) {
                    alert(besmEditor.strings.selectActionItem);
                    return;
                }
                data.post_ids = selectedIds;
                confirmCount = selectedIds.length;
            }

            if (!confirm(besmEditor.strings.bulkActionConfirm.replace('%d', confirmCount))) {
                return;
            }

            const $btn = $(e.currentTarget);
            const originalText = $btn.html();

            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> ' + besmEditor.strings.running);

            $.ajax({
                url: besmEditor.ajaxUrl,
                type: 'POST',
                data: data,
                success: function (response) {
                    if (response.success) {
                        BESMEditor.showNotice('success', response.data.message);
                        setTimeout(function () {
                            location.reload();
                        }, 1500);
                    } else {
                        BESMEditor.showNotice('error', response.data.message || besmEditor.strings.actionError);
                    }
                },
                error: function (xhr, status, error) {
                    console.error('AJAX Error:', error);
                    let message = besmEditor.strings.actionError;
                    if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        message = xhr.responseJSON.data.message;
                    }
                    BESMEditor.showNotice('error', message);
                },
                complete: function () {
                    $btn.prop('disabled', false).html(originalText);
                }
            });
        },

        initImageUpload: function () {
            if (typeof wp !== 'undefined' && wp.media) {
                this.setupMediaFrame();
            }
        },

        setupMediaFrame: function () {
            // Will be created when needed
        },

        openMediaUploader: function (e) {
            e.preventDefault();
            e.stopPropagation();

            const $target = $(e.currentTarget);
            const postId = $target.data('post-id');
            const imageType = $target.data('type') || 'featured';

            this.currentImageTarget = {
                element: $target,
                postId: postId,
                type: imageType
            };

            if (this.mediaFrame) {
                this.mediaFrame.open();
                return;
            }

            this.mediaFrame = wp.media({
                title: besmEditor.strings.selectImage,
                button: {
                    text: besmEditor.strings.useImage
                },
                multiple: imageType === 'gallery'
            });

            this.mediaFrame.on('select', this.handleImageSelect.bind(this));
            this.mediaFrame.open();
        },

        handleImageSelect: function () {
            if (!this.currentImageTarget) {
                console.error('❌ No target defined');
                return;
            }

            const selection = this.mediaFrame.state().get('selection');
            const $target = this.currentImageTarget.element;
            const postId = this.currentImageTarget.postId;
            const imageType = this.currentImageTarget.type;


            if (imageType === 'gallery') {
                this.handleGallerySelect(selection, $target, postId);
            } else {
                const attachment = selection.first().toJSON();
                this.updateImagePreview($target, attachment, postId);
            }
        },

        updateImagePreview: function ($target, attachment, postId) {
            const imageUrl = attachment.sizes && attachment.sizes.thumbnail ?
                attachment.sizes.thumbnail.url : attachment.url;

            $target.removeClass('no-image');
            $target.find('img').remove();
            $target.find('.dashicons').remove();

            const $img = $('<img>').attr('src', imageUrl);
            const $removeBtn = $('<button type="button" class="besm-image-remove" title="' + besmEditor.strings.removeImage + '"><span class="dashicons dashicons-no"></span></button>');

            $target.append($img).append($removeBtn);

            const $container = $target.closest('.besm-image-field');
            let $input = $container.find('input[type="hidden"]');

            if (!$input.length) {
                $input = $('<input type="hidden">').attr('name', 'posts[' + postId + '][featured_image]');
                $container.append($input);
            }

            $input.val(attachment.id);

            const $altInput = $container.find('.besm-alt-input input');
            if ($altInput.length && !$altInput.val()) {
                const postTitle = $('input[name="posts[' + postId + '][title]"]').val();
                if (postTitle) {
                    $altInput.val(postTitle);
                }
            }

            $target.data('attachment-id', attachment.id);

            const filename = attachment.filename || '';
            if (filename) {
                let $filenameDiv = $container.find('.besm-image-filename');
                if (!$filenameDiv.length) {
                    $filenameDiv = $('<div class="besm-image-filename"></div>');
                    $target.after($filenameDiv);
                }
                $filenameDiv.html(
                    '<span class="dashicons dashicons-media-default"></span>' +
                    '<span class="besm-filename-text" title="' + filename + '">' + filename + '</span>' +
                    '<button type="button" class="besm-copy-filename" data-filename="' + filename + '" title="' + besmEditor.strings.copyFilename + '">' +
                    '<span class="dashicons dashicons-admin-page"></span>' +
                    '</button>'
                );
            }

            // ✅ Mark row as changed
            this.markRowAsChanged(postId);
        },

        handleGallerySelect: function (selection, $target, postId) {
            const $galleryContainer = $target.closest('.besm-gallery-field');

            if ($galleryContainer.length === 0) {
                console.error('❌ Gallery container not found');
                return;
            }

            selection.each(function (attachment) {
                attachment = attachment.toJSON();

                const imageUrl = attachment.sizes && attachment.sizes.thumbnail ?
                    attachment.sizes.thumbnail.url : attachment.url;
                const filename = attachment.filename || '';

                const $wrapper = $('<div class="besm-gallery-item-wrapper">');
                const $item = $('<div class="besm-gallery-item besm-image-preview">');
                const $img = $('<img>').attr('src', imageUrl).attr('alt', attachment.alt || '');
                const $removeBtn = $('<button type="button" class="besm-image-remove" title="' + besmEditor.strings.removeImage + '"><span class="dashicons dashicons-no"></span></button>');

                $item.attr('data-attachment-id', attachment.id);
                $item.append($img).append($removeBtn);
                $wrapper.append($item);

                if (filename) {
                    const $filenameDiv = $('<div class="besm-gallery-filename">' +
                        '<span class="besm-filename-text" title="' + filename + '">' + filename + '</span>' +
                        '<button type="button" class="besm-copy-filename" data-filename="' + filename + '" title="' + besmEditor.strings.copy + '">' +
                        '<span class="dashicons dashicons-admin-page"></span>' +
                        '</button>' +
                        '</div>');
                    $wrapper.append($filenameDiv);
                }

                const $altInput = $('<input type="text" class="besm-gallery-alt-input" ' +
                    'name="posts[' + postId + '][gallery_alts][' + attachment.id + ']" ' +
                    'placeholder="' + besmEditor.strings.altPlaceholder + '" ' +
                    'value="' + (attachment.alt || '') + '" ' +
                    'style="width: 100%; padding: 4px 8px; border: 1px solid #e2e8f0; border-radius: 4px; font-size: 11px; margin-top: 4px;">');
                $wrapper.append($altInput);

                $galleryContainer.find('.besm-gallery-add').before($wrapper);
            });

            this.updateGalleryField($galleryContainer, postId);

            // ✅ Mark row as changed
            this.markRowAsChanged(postId);

        },

        updateGalleryField: function ($container, postId) {
            const ids = [];

            $container.find('.besm-gallery-item').each(function () {
                const id = $(this).data('attachment-id');
                if (id) {
                    ids.push(id);
                }
            });

            let $input = $container.find('.besm-gallery-ids');

            if ($input.length === 0) {
                $input = $('<input type="hidden" class="besm-gallery-ids">').attr('name', 'posts[' + postId + '][gallery]');
                $container.append($input);
            }

            $input.val(ids.join(','));

        },

        removeImage: function (e) {
            e.preventDefault();
            e.stopPropagation();

            if (!confirm(besmEditor.strings.confirmDelete)) {
                return;
            }

            const $btn = $(e.currentTarget);
            const $preview = $btn.closest('.besm-image-preview');

            if ($preview.hasClass('besm-gallery-item')) {
                // Gallery item
                const $wrapper = $preview.closest('.besm-gallery-item-wrapper');
                const $container = $wrapper.closest('.besm-gallery-field');
                const postId = $container.data('post-id');

                $wrapper.remove();
                this.updateGalleryField($container, postId);

                // ✅ Mark row as changed
                this.markRowAsChanged(postId);

            } else {
                // Featured image
                $preview.addClass('no-image');
                $preview.find('img').remove();
                $preview.find('.besm-image-remove').remove();
                $preview.append('<span class="dashicons dashicons-format-image"></span>');

                const $container = $preview.closest('.besm-image-field');
                $container.find('input[type="hidden"]').val('');
                $container.find('.besm-alt-input input').val('');
                $container.find('.besm-image-filename').remove();

                $preview.removeData('attachment-id');

                // ✅ Mark row as changed
                const postId = $preview.data('post-id');
                this.markRowAsChanged(postId);

            }
        },

        addGalleryImage: function (e) {
            e.preventDefault();
            e.stopPropagation();

            const $btn = $(e.currentTarget);
            const $container = $btn.closest('.besm-gallery-field');
            const postId = $container.data('post-id');


            this.currentImageTarget = {
                element: $container,
                postId: postId,
                type: 'gallery'
            };

            if (this.mediaFrame) {
                this.mediaFrame.close();
                this.mediaFrame = null;
            }

            this.mediaFrame = wp.media({
                title: besmEditor.strings.selectGalleryImages,
                button: {
                    text: besmEditor.strings.addToGallery
                },
                multiple: true
            });

            this.mediaFrame.on('select', this.handleImageSelect.bind(this));
            this.mediaFrame.open();
        },

        autoFillAlt: function (e) {
            const $titleInput = $(e.currentTarget);
            const title = $titleInput.val().trim();
            const postId = $titleInput.attr('name').match(/\[(\d+)\]/)[1];

            if (title) {
                const $altInput = $('input[name="posts[' + postId + '][featured_image_alt]"]');
                if ($altInput.length && !$altInput.val()) {
                    $altInput.val(title);
                }
            }
        },

        handleBulkSave: function (e) {
            e.preventDefault();

            if (this.changedRows.size === 0) {
                alert(besmEditor.strings.noChanges);
                return;
            }

            if (!confirm(besmEditor.strings.saveConfirm.replace('%d', this.changedRows.size))) {
                return;
            }

            const $btn = $(e.currentTarget);
            const $fab = $('#besm-fab');
            const $fabIcon = $('#besm-fab-save .dashicons');
            const originalText = $('.besm-fab-text').text();
            const originalIcon = $fabIcon.attr('class');

            const postsData = this.collectPostsData();


            // ✅ تغییر وضعیت FAB به saving
            $fab.addClass('saving').removeClass('has-changes success error');
            $fabIcon.removeClass().addClass('dashicons dashicons-update');
            $('.besm-fab-text').text(besmEditor.strings.saving);

            $('#besm-loading-overlay').addClass('active');

            $btn.prop('disabled', true);
            $('#besm-fab-save').prop('disabled', true);

            $.ajax({
                url: besmEditor.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'besm_bulk_save',
                    nonce: besmEditor.nonce,
                    posts: postsData
                },
                success: function (response) {
                    if (response.success) {
                        $fab.removeClass('saving').addClass('success');
                        $fabIcon.removeClass().addClass('dashicons dashicons-yes-alt');
                        $('.besm-fab-text').text(besmEditor.strings.saved);

                        BESMEditor.showNotice('success', response.data.message);

                        // ✅ پاک کردن لیست تغییرات
                        BESMEditor.changedRows.clear();

                        // ✅ پاک کردن کلاس changed از ردیف‌ها
                        $('.besm-main-row').removeClass('besm-row-changed');

                        setTimeout(function () {
                            $fab.removeClass('success has-changes');
                            $fabIcon.removeClass().addClass(originalIcon);
                            $('.besm-fab-text').text(originalText);
                            $('#besm-fab-badge').text('0');
                        }, 2000);

                        setTimeout(function () {
                            location.reload();
                        }, 2500);
                    } else {
                        $('#besm-loading-overlay').removeClass('active');
                        $fab.removeClass('saving').addClass('error');
                        $fabIcon.removeClass().addClass('dashicons dashicons-dismiss');
                        $('.besm-fab-text').text(besmEditor.strings.saveError);

                        BESMEditor.showNotice('error', response.data.message || besmEditor.strings.error);

                        setTimeout(function () {
                            $fab.removeClass('error').addClass('has-changes');
                            $fabIcon.removeClass().addClass(originalIcon);
                            $('.besm-fab-text').text(originalText);
                            BESMEditor.updateFAB();
                        }, 2000);
                    }
                },
                error: function (xhr, status, error) {
                    console.error('AJAX Error:', error);
                    $('#besm-loading-overlay').removeClass('active');

                    $fab.removeClass('saving').addClass('error');
                    $fabIcon.removeClass().addClass('dashicons dashicons-dismiss');
                    $('.besm-fab-text').text(besmEditor.strings.errorShort);

                    let message = besmEditor.strings.error;
                    if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        message = xhr.responseJSON.data.message;
                    }
                    BESMEditor.showNotice('error', message);

                    setTimeout(function () {
                        $fab.removeClass('error').addClass('has-changes');
                        $fabIcon.removeClass().addClass(originalIcon);
                        $('.besm-fab-text').text(originalText);
                        BESMEditor.updateFAB();
                    }, 2000);
                },
                complete: function () {
                    $btn.prop('disabled', false);
                }
            });
        },

        // ✅ COMPLETELY REWRITTEN: فقط آیتم‌های تغییر یافته
        collectPostsData: function () {
            const posts = {};

            // ✅ فقط ردیف‌هایی که در changedRows هستن
            this.changedRows.forEach(postId => {
                const $row = $('.besm-edit-table tbody tr[data-post-id="' + postId + '"]');

                if ($row.length === 0) return;

                posts[postId] = {};

                // جمع‌آوری همه فیلدها
                $row.find('input, textarea, select').each(function () {
                    const $field = $(this);
                    const name = $field.attr('name');

                    if (!name) return;

                    const match = name.match(/posts\[(\d+)\]\[([^\]]+)\]/);
                    if (match) {
                        const fieldPostId = match[1];
                        const fieldName = match[2];

                        if (parseInt(fieldPostId) !== parseInt(postId)) {
                            return;
                        }

                        let value = $field.val();

                        if ($field.attr('type') === 'checkbox') {
                            value = $field.is(':checked') ? 'yes' : 'no';
                        }

                        posts[postId][fieldName] = value;
                    }
                });

                // Gallery
                const $galleryContainer = $row.find('.besm-gallery-field');
                if ($galleryContainer.length) {
                    const galleryIds = [];
                    const galleryAlts = {};

                    $galleryContainer.find('.besm-gallery-item').each(function () {
                        const attachmentId = parseInt($(this).data('attachment-id'));

                        if (attachmentId) {
                            galleryIds.push(attachmentId);

                            const $wrapper = $(this).closest('.besm-gallery-item-wrapper');
                            const $altInput = $wrapper.find('.besm-gallery-alt-input');

                            if ($altInput.length) {
                                galleryAlts[attachmentId] = $altInput.val() || '';
                            }
                        }
                    });

                    if (galleryIds.length > 0) {
                        posts[postId]['gallery'] = galleryIds.join(',');
                        posts[postId]['gallery_alts'] = galleryAlts;
                    }
                }
            });

            return posts;
        },

        // ✅ FIXED: Track changes - هر تغییر ردیف رو mark می‌کنه
        trackChanges: function (e) {
            const $field = $(e.currentTarget);
            const $row = $field.closest('tr[data-post-id]');

            if ($row.length === 0) return;

            const postId = $row.data('post-id');

            if (!postId) return;

            // ✅ اضافه کردن به لیست تغییرات
            this.markRowAsChanged(postId);
        },

        // ✅ NEW: Mark row as changed
        markRowAsChanged: function (postId) {
            if (!postId) return;

            this.changedRows.add(postId.toString());

            // ✅ افزودن کلاس به ردیف برای visual feedback
            const $row = $('tr[data-post-id="' + postId + '"]');
            if ($row.length) {
                $row.addClass('besm-row-changed');
            }

            this.updateFAB();

        },

        initUnsavedWarning: function () {
            const self = this;

            $(window).on('beforeunload', function () {
                if (self.changedRows.size > 0) {
                    return besmEditor.strings.unsavedWarning.replace('%d', self.changedRows.size);
                }
            });
        },

        showNotice: function (type, message) {
            const $notice = $('<div class="besm-notice ' + type + '"></div>');

            let icon = 'info';
            if (type === 'success') icon = 'yes-alt';
            if (type === 'error') icon = 'dismiss';
            if (type === 'warning') icon = 'warning';

            $notice.html('<span class="dashicons dashicons-' + icon + '"></span><span>' + message + '</span>');

            $('.besm-editor-wrap').before($notice);

            $('html, body').animate({
                scrollTop: $notice.offset().top - 100
            }, 500);

            setTimeout(function () {
                $notice.fadeOut(function () {
                    $(this).remove();
                });
            }, 5000);
        },

        copyFilename: function (e) {
            e.preventDefault();
            e.stopPropagation();

            const $btn = $(e.currentTarget);
            const filename = $btn.data('filename');

            if (!filename) {
                console.error('❌ No filename found');
                return;
            }

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(filename).then(() => {
                    this.showCopySuccess($btn);
                }).catch(err => {
                    console.error('❌ Clipboard error:', err);
                    this.fallbackCopy(filename, $btn);
                });
            } else {
                this.fallbackCopy(filename, $btn);
            }
        },

        fallbackCopy: function (text, $btn) {
            const $temp = $('<textarea>');
            $('body').append($temp);
            $temp.val(text).select();

            try {
                document.execCommand('copy');
                this.showCopySuccess($btn);
            } catch (err) {
                console.error('❌ Copy failed:', err);
                alert(besmEditor.strings.copyError + ' ' + text);
            }

            $temp.remove();
        },

        showCopySuccess: function ($btn) {
            const originalHtml = $btn.html();

            $btn.addClass('copied')
                .html('<span class="dashicons dashicons-yes"></span>');

            setTimeout(function () {
                $btn.removeClass('copied').html(originalHtml);
            }, 1500);
        }
    };

    $(document).ready(function () {
        BESMEditor.init();
    });
})(jQuery);