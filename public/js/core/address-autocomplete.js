/**
 * Address autocomplete + backend geocoding helpers.
 */
(function (window, $) {
    'use strict';

    if (!$) {
        return;
    }

    const AddressAutocomplete = {
        debounceTimers: {},

        apiUrl(path, params) {
            const base = window.NASO_API_BASE || '/api';
            const query = new URLSearchParams(params).toString();
            return `${base}${path}${query ? `?${query}` : ''}`;
        },

        async apiGet(path, params) {
            if (window.AjaxHelper) {
                return window.AjaxHelper.get(this.apiUrl(path, params), null, {
                    showLoader: false,
                    preventDuplicate: false,
                });
            }

            const response = await fetch(this.apiUrl(path, params), {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            return response.json();
        },

        async reverseGeocode(lat, lng) {
            const response = await this.apiGet('/geocode/reverse', {
                latitude: lat,
                longitude: lng,
            });

            const data = response.data || response;
            if (!data || !data.address) {
                throw new Error(response.message || 'Unable to resolve address for this location.');
            }

            return data;
        },

        initField($input, options) {
            if ($input.data('autocomplete-ready')) {
                return;
            }

            const settings = Object.assign(
                {
                    target: 'delivery',
                    minLength: 3,
                },
                options || {}
            );

            const $wrapper = $('<div class="address-autocomplete position-relative"></div>');
            $input.wrap($wrapper);
            const $list = $('<div class="address-autocomplete-list list-group d-none"></div>');
            $input.after($list);
            $input.data('autocomplete-ready', true);

            const hideList = () => $list.addClass('d-none').empty();

            $input.on('input', () => {
                const query = String($input.val() || '').trim();
                const key = $input.attr('id') || 'field';

                clearTimeout(this.debounceTimers[key]);
                if (query.length < settings.minLength) {
                    hideList();
                    return;
                }

                this.debounceTimers[key] = setTimeout(async () => {
                    try {
                        const response = await this.apiGet('/geocode/search', { q: query });
                        const items = response.data || [];
                        $list.empty();

                        if (!items.length) {
                            hideList();
                            return;
                        }

                        items.forEach((item) => {
                            const $button = $(`
                                <button type="button" class="list-group-item list-group-item-action">
                                    <div class="fw-semibold">${item.label || 'Location'}</div>
                                    <div class="small text-muted">${item.address || ''}</div>
                                </button>
                            `);

                            $button.on('click', async () => {
                                hideList();
                                await this.applySuggestion($input, item, settings.target);
                            });

                            $list.append($button);
                        });

                        $list.removeClass('d-none');
                    } catch (_) {
                        hideList();
                    }
                }, 300);
            });

            $input.on('blur', () => {
                setTimeout(hideList, 200);
            });
        },

        async applySuggestion($input, item, target) {
            let address = item.address || item.label || '';
            let lat = item.latitude;
            let lng = item.longitude;

            if ((!lat || !lng) && item.id && item.provider) {
                const response = await this.apiGet('/geocode/place', {
                    provider: item.provider,
                    id: item.id,
                });
                const data = response.data || {};
                address = data.address || address;
                lat = data.latitude;
                lng = data.longitude;
            }

            $input.val(address);

            if (target === 'delivery') {
                $('#delivery_latitude').val(lat || '');
                $('#delivery_longitude').val(lng || '');
            } else if (target === 'shop') {
                $('#shop_latitude').val(lat || '');
                $('#shop_longitude').val(lng || '');
            }
        },

        initDeliveryForm() {
            const $pickup = $('#pickup_address');
            const $delivery = $('#delivery_address');

            if ($pickup.length) {
                this.initField($pickup, { target: 'pickup' });
            }

            if ($delivery.length) {
                this.initField($delivery, { target: 'delivery' });
            }
        },

        initShopForm() {
            const $address = $('#shop_address');

            if ($address.length) {
                this.initField($address, { target: 'shop' });
            }
        },
    };

    window.AddressAutocomplete = AddressAutocomplete;

    $(document).ready(function () {
        AddressAutocomplete.initDeliveryForm();
        AddressAutocomplete.initShopForm();
    });
})(window, window.jQuery);
