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

    function insideRowHtml(from, to, fee) {
        return (
            '<tr class="pricing-slab-row" data-kind="inside">' +
            '<td><input type="number" step="0.01" min="0" class="form-control form-control-sm slab-from" value="' + (from ?? 0) + '"></td>' +
            '<td><input type="number" step="0.01" min="0" class="form-control form-control-sm slab-to" value="' + (to ?? '') + '" placeholder="open"></td>' +
            '<td><input type="number" step="0.01" min="0" class="form-control form-control-sm slab-fee" value="' + (fee ?? 0) + '"></td>' +
            '<td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger btn-remove-slab">Remove</button></td>' +
            '</tr>'
        );
    }

    function outsideRowHtml(label, from, to, fee) {
        return (
            '<tr class="pricing-slab-row" data-kind="outside">' +
            '<td><input type="text" class="form-control form-control-sm slab-label" value="' + (label || '') + '" placeholder="short"></td>' +
            '<td><input type="number" step="0.01" min="0" class="form-control form-control-sm slab-from" value="' + (from ?? 0) + '"></td>' +
            '<td><input type="number" step="0.01" min="0" class="form-control form-control-sm slab-to" value="' + (to ?? '') + '" placeholder="open"></td>' +
            '<td><input type="number" step="0.01" min="0" class="form-control form-control-sm slab-fee" value="' + (fee ?? 0) + '"></td>' +
            '<td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger btn-remove-slab">Remove</button></td>' +
            '</tr>'
        );
    }

    function collectSlabs($table, withLabel) {
        const rows = [];
        $table.find('tbody tr.pricing-slab-row').each(function () {
            const $row = $(this);
            const from = parseFloat($row.find('.slab-from').val());
            const toRaw = $row.find('.slab-to').val();
            const fee = parseFloat($row.find('.slab-fee').val());
            const to = toRaw === '' || toRaw === null || typeof toRaw === 'undefined'
                ? null
                : parseFloat(toRaw);

            if (Number.isNaN(from) || Number.isNaN(fee)) {
                return;
            }

            const item = {
                from_km: from,
                to_km: to === null || Number.isNaN(to) ? null : to,
                fee: fee,
            };

            if (withLabel) {
                item.label = String($row.find('.slab-label').val() || '').trim();
            }

            rows.push(item);
        });
        return rows;
    }

    function togglePricingMode() {
        const mode = $('#settings-pricing-mode').val();
        $('.pricing-zone-fields').toggleClass('d-none', mode !== 'zone_slabs');
        $('.pricing-linear-fields').toggleClass('d-none', mode !== 'linear');
    }

    function buildPayload($form) {
        return {
            app_name: $form.find('[name="app_name"]').val(),
            support_email: $form.find('[name="support_email"]').val(),
            support_phone: $form.find('[name="support_phone"]').val(),
            shop_registration_enabled: $form.find('[name="shop_registration_enabled"]').is(':checked') ? 1 : 0,
            rider_registration_enabled: $form.find('[name="rider_registration_enabled"]').is(':checked') ? 1 : 0,
            dashboard_refresh_interval: Number($form.find('[name="dashboard_refresh_interval"]').val() || 30),
            delivery_offer_timeout_minutes: Number($form.find('[name="delivery_offer_timeout_minutes"]').val() || 15),
            delivery_base_fee: Number($form.find('[name="delivery_base_fee"]').val() || 0),
            delivery_fee_per_km: Number($form.find('[name="delivery_fee_per_km"]').val() || 0),
            delivery_min_fee: Number($form.find('[name="delivery_min_fee"]').val() || 0),
            platform_commission_percent: Number($form.find('[name="platform_commission_percent"]').val() || 0),
            delivery_pricing: {
                mode: $('#settings-pricing-mode').val() || 'zone_slabs',
                valley: {
                    lat: Number($('#valley-lat').val() || 27.7172),
                    lng: Number($('#valley-lng').val() || 85.3240),
                    radius_km: Number($('#valley-radius').val() || 18),
                },
                inside_valley: collectSlabs($('#inside-slabs-table'), false),
                outside_valley: collectSlabs($('#outside-slabs-table'), true),
            },
        };
    }

    $(function () {
        const $form = $('#settings-form');
        const $logoInput = $('#settings-logo-input');

        if (!$form.length) {
            return;
        }

        togglePricingMode();
        $('#settings-pricing-mode').on('change', togglePricingMode);

        $('#btn-add-inside-slab').on('click', function () {
            $('#inside-slabs-table tbody').append(insideRowHtml(0, '', 100));
        });

        $('#btn-add-outside-slab').on('click', function () {
            $('#outside-slabs-table tbody').append(outsideRowHtml('long', 80, '', 800));
        });

        $form.on('click', '.btn-remove-slab', function () {
            const $tbody = $(this).closest('tbody');
            if ($tbody.find('tr.pricing-slab-row').length <= 1) {
                window.NotificationHelper.warning('At least one slab/category is required.');
                return;
            }
            $(this).closest('tr').remove();
        });

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
            $('[data-error="delivery_pricing"]').text('').hide();

            const payload = buildPayload($form);

            if (!payload.delivery_pricing.inside_valley.length || !payload.delivery_pricing.outside_valley.length) {
                $('[data-error="delivery_pricing"]').text('Add at least one inside and one outside slab.').show();
                return;
            }

            window.AjaxHelper.request({
                url: $form.data('update-url'),
                method: 'POST',
                data: JSON.stringify($.extend({}, payload, { _method: 'PUT' })),
                contentType: 'application/json',
                processData: false,
                success: function (response) {
                    window.NotificationHelper.success(response.message || 'Settings saved.');
                    if (response.data?.app_name) {
                        $('#sidebar-brand-name').text(response.data.app_name);
                    }
                },
                error: function (xhr, textStatus, errorThrown, handled) {
                    if (handled?.errors) {
                        window.FormHelper.showErrors($form, handled.errors);
                        const pricingError = handled.errors['delivery_pricing']
                            || handled.errors['delivery_pricing.inside_valley']
                            || handled.errors['delivery_pricing.outside_valley'];
                        if (pricingError) {
                            $('[data-error="delivery_pricing"]').text(
                                Array.isArray(pricingError) ? pricingError[0] : pricingError
                            ).show();
                        }
                    }
                },
            });
        });
    });
}(window, window.jQuery));
