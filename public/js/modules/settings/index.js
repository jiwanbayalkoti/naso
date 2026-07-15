(function (window, $) {
    'use strict';

    if (!$ || !window.AjaxHelper || !window.FormHelper) {
        return;
    }

    function renderLogoPreview(url) {
        const $preview = $('#settings-logo-preview');
        if (!$preview.length) {
            return;
        }

        if (url) {
            $preview.html('<img src="' + url + '" alt="App logo" class="settings-logo-image">');
            const $brand = $('#sidebar-brand');
            if ($brand.length) {
                $('#sidebar-brand-icon').remove();
                if (!$('#sidebar-brand-logo').length) {
                    $brand.prepend('<img src="' + url + '" alt="App logo" class="sidebar-brand-logo" id="sidebar-brand-logo">');
                } else {
                    $('#sidebar-brand-logo').attr('src', url);
                }
            }
        }
    }

    $(function () {
        const $form = $('#settings-form');
        const $logoInput = $('#settings-logo-input');

        if (!$form.length) {
            return;
        }

        if ($logoInput.length) {
            $logoInput.on('change', function () {
                const file = this.files && this.files[0];
                if (!file) {
                    return;
                }

                const formData = new FormData();
                formData.append('logo', file);
                formData.append('_token', $('meta[name="csrf-token"]').attr('content'));

                window.AjaxHelper.post($form.data('logo-url'), formData, {
                    processData: false,
                    contentType: false,
                    preventDuplicate: false,
                    loaderMessage: 'Uploading logo...',
                    success: function (response) {
                        window.NotificationHelper.success(response.message || 'Logo updated.');
                        renderLogoPreview(response.data?.app_logo_url);
                        $logoInput.val('');
                        $('[data-error="logo"]').text('').hide();
                    },
                    error: function (xhr, textStatus, errorThrown, handled) {
                        const message = handled?.errors?.logo?.[0]
                            || handled?.message
                            || 'Upload failed.';
                        $('[data-error="logo"]').text(message).show();
                        if (window.NotificationHelper) {
                            window.NotificationHelper.error(message);
                        }
                    },
                });
            });
        }

        $form.on('submit', function (event) {
            event.preventDefault();
            window.FormHelper.clearErrors($form);

            const payload = $form.serializeArray();
            ['shop_registration_enabled', 'rider_registration_enabled'].forEach(function (field) {
                const checked = $form.find('[name="' + field + '"]').is(':checked');
                const existing = payload.find(function (item) {
                    return item.name === field;
                });
                if (existing) {
                    existing.value = checked ? '1' : '0';
                } else {
                    payload.push({ name: field, value: checked ? '1' : '0' });
                }
            });

            window.AjaxHelper.put($form.data('update-url'), $.param(payload), {
                success: function (response) {
                    window.NotificationHelper.success(response.message || 'Settings saved.');
                    if (response.data?.app_name) {
                        $('#sidebar-brand-name').text(response.data.app_name);
                    }
                },
                error: function (xhr, textStatus, errorThrown, handled) {
                    if (handled?.errors) {
                        window.FormHelper.showErrors($form, handled.errors);
                    }
                },
            });
        });
    });
}(window, window.jQuery));
