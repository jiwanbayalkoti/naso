/**
 * Browser geolocation + reverse geocode + map picker for delivery forms.
 */
(function (window, $) {
    'use strict';

    if (!$) {
        return;
    }

    const LocationHelper = {
        pickerMap: null,
        pickerMarker: null,
        pickerTarget: null,

        fieldMap: {
            pickup: { address: '#pickup_address' },
            delivery: {
                address: '#delivery_address',
                lat: '#delivery_latitude',
                lng: '#delivery_longitude',
            },
            shop: {
                address: '#shop_address',
                lat: '#shop_latitude',
                lng: '#shop_longitude',
            },
        },

        getCurrentPosition() {
            return new Promise((resolve, reject) => {
                if (!navigator.geolocation) {
                    reject(new Error('Geolocation is not supported in this browser.'));
                    return;
                }

                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        resolve({
                            lat: position.coords.latitude,
                            lng: position.coords.longitude,
                        });
                    },
                    () => reject(new Error('Unable to get your current location.')),
                    {
                        enableHighAccuracy: true,
                        timeout: 20000,
                        maximumAge: 10000,
                    }
                );
            });
        },

        async reverseGeocode(lat, lng) {
            if (window.AddressAutocomplete) {
                const data = await window.AddressAutocomplete.reverseGeocode(lat, lng);
                return data.address;
            }

            return `${Number(lat).toFixed(6)}, ${Number(lng).toFixed(6)}`;
        },

        setFieldValues(target, address, lat, lng) {
            const fields = this.fieldMap[target];
            if (!fields) {
                return;
            }

            $(fields.address).val(address);
            if (fields.lat) {
                $(fields.lat).val(lat ?? '');
            }
            if (fields.lng) {
                $(fields.lng).val(lng ?? '');
            }
        },

        async applyToField(target) {
            const position = await this.getCurrentPosition();
            let address;
            let lat = position.lat;
            let lng = position.lng;

            if (window.AddressAutocomplete) {
                const data = await window.AddressAutocomplete.reverseGeocode(lat, lng);
                address = data.address;
                lat = data.latitude ?? lat;
                lng = data.longitude ?? lng;
            } else {
                address = await this.reverseGeocode(lat, lng);
            }

            this.setFieldValues(target, address, lat, lng);

            return { address, lat, lng };
        },

        async applyDefaultLocation(target) {
            try {
                await this.applyToField(target);
            } catch (_) {}
        },

        async applyDefaultDeliveryLocation() {
            await this.applyDefaultLocation('delivery');
        },

        bindButtons() {
            const self = this;

            $(document).on('click', '.btn-use-current-location', function () {
                const target = $(this).data('target');
                const $button = $(this);
                const originalHtml = $button.html();

                $button.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Locating...');

                self.applyToField(target)
                    .then(() => {
                        if (window.NotificationHelper) {
                            window.NotificationHelper.success('Location applied to address.');
                        }
                    })
                    .catch((error) => {
                        if (window.NotificationHelper) {
                            window.NotificationHelper.error(error.message || 'Location failed.');
                        }
                    })
                    .finally(() => {
                        $button.prop('disabled', false).html(originalHtml);
                    });
            });

            $(document).on('click', '.btn-pick-on-map', function () {
                self.openMapPicker($(this).data('target'));
            });

            $('#location-picker-modal').on('hidden.bs.modal', () => {
                self.pickerTarget = null;
            });

            $('#location-picker-apply').on('click', () => self.applyPickerSelection());
        },

        async openMapPicker(target) {
            this.pickerTarget = target;

            if (!window.GoogleMapsTracker) {
                return;
            }

            const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('location-picker-modal'));
            modal.show();

            const maps = await window.GoogleMapsTracker.loadMaps();
            const container = document.getElementById('location-picker-map');

            let center = { lat: 27.7172, lng: 85.3240 };

            try {
                center = await this.getCurrentPosition();
            } catch (_) {}

            if (!this.pickerMap) {
                this.pickerMap = new maps.Map(container, {
                    center,
                    zoom: 15,
                    mapTypeControl: false,
                    streetViewControl: false,
                });

                this.pickerMap.addListener('click', (event) => {
                    const position = {
                        lat: event.latLng.lat(),
                        lng: event.latLng.lng(),
                    };

                    if (this.pickerMarker) {
                        this.pickerMarker.setPosition(position);
                    } else {
                        this.pickerMarker = new maps.Marker({
                            map: this.pickerMap,
                            position,
                            draggable: true,
                        });
                    }
                });
            } else {
                this.pickerMap.setCenter(center);
            }

            if (this.pickerMarker) {
                this.pickerMarker.setMap(null);
                this.pickerMarker = null;
            }

            this.pickerMarker = new maps.Marker({
                map: this.pickerMap,
                position: center,
                draggable: true,
            });

            setTimeout(() => {
                maps.event.trigger(this.pickerMap, 'resize');
                this.pickerMap.setCenter(this.pickerMarker.getPosition());
            }, 250);
        },

        async applyPickerSelection() {
            if (!this.pickerMarker || !this.pickerTarget) {
                return;
            }

            const position = this.pickerMarker.getPosition();
            const lat = position.lat();
            const lng = position.lng();

            try {
                let address;
                let lat = position.lat();
                let lng = position.lng();

                if (window.AddressAutocomplete) {
                    const data = await window.AddressAutocomplete.reverseGeocode(lat, lng);
                    address = data.address;
                    lat = data.latitude ?? lat;
                    lng = data.longitude ?? lng;
                } else {
                    address = await this.reverseGeocode(lat, lng);
                }

                this.setFieldValues(this.pickerTarget, address, lat, lng);

                bootstrap.Modal.getInstance(document.getElementById('location-picker-modal'))?.hide();

                if (window.NotificationHelper) {
                    window.NotificationHelper.success('Map location applied to address.');
                }
            } catch (error) {
                if (window.NotificationHelper) {
                    window.NotificationHelper.error(error.message || 'Unable to apply map location.');
                }
            }
        },
    };

    window.LocationHelper = LocationHelper;

    $(document).ready(function () {
        LocationHelper.bindButtons();
    });
})(window, window.jQuery);
