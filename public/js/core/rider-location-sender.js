/**
 * Sends rider GPS location + presence heartbeat while the rider is online.
 */
(function (window, $) {
    'use strict';

    if (!$ || !navigator.geolocation) {
        return;
    }

    const RiderLocationSender = {
        watchId: null,
        heartbeatTimer: null,
        lastSentAt: 0,
        minIntervalMs: 15000,
        heartbeatIntervalMs: 60000,
        url: null,
        heartbeatUrl: null,
        toggleOnlineUrl: null,

        init() {
            const $root = $('#rider-location-sender');
            if (!$root.length) {
                return;
            }

            this.url = $root.data('url');
            this.heartbeatUrl = $root.data('heartbeat');
            this.toggleOnlineUrl = $root.data('toggle-online');
            const isOnline = String($root.data('online')) === '1';

            if (!this.url && !this.heartbeatUrl) {
                return;
            }

            this.setOnline(isOnline);

            $(document).on('rider:online-changed', (_event, isOnline) => {
                this.setOnline(!!isOnline);
            });
        },

        setOnline(isOnline) {
            const $root = $('#rider-location-sender');
            if ($root.length) {
                $root.attr('data-online', isOnline ? '1' : '0');
            }

            if (isOnline) {
                if (this.url) {
                    this.start(this.url);
                }
                this.startHeartbeat();
            } else {
                this.stop();
                this.stopHeartbeat();
            }
        },

        startHeartbeat() {
            this.stopHeartbeat();
            if (!this.heartbeatUrl) {
                return;
            }

            const beat = () => {
                if (window.AjaxHelper) {
                    window.AjaxHelper.post(this.heartbeatUrl, {}, {
                        showLoader: false,
                        preventDuplicate: false,
                    });
                } else {
                    $.post(this.heartbeatUrl);
                }
            };

            beat();
            this.heartbeatTimer = window.setInterval(beat, this.heartbeatIntervalMs);
        },

        stopHeartbeat() {
            if (this.heartbeatTimer !== null) {
                window.clearInterval(this.heartbeatTimer);
                this.heartbeatTimer = null;
            }
        },

        sendPosition(url, position) {
            const payload = {
                latitude: position.coords.latitude,
                longitude: position.coords.longitude,
            };

            if (window.AjaxHelper) {
                return window.AjaxHelper.post(url, payload, {
                    showLoader: false,
                    preventDuplicate: false,
                });
            }

            return $.post(url, payload);
        },

        sendCurrentLocation() {
            const url = this.url;
            if (!url) {
                return Promise.resolve();
            }

            return new Promise((resolve) => {
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        this.lastSentAt = Date.now();
                        Promise.resolve(this.sendPosition(url, position)).finally(resolve);
                    },
                    () => resolve(),
                    {
                        enableHighAccuracy: true,
                        maximumAge: 5000,
                        timeout: 20000,
                    }
                );
            });
        },

        async prepareForLiveDelivery() {
            if (!this.url && !this.heartbeatUrl) {
                return;
            }

            const $root = $('#rider-location-sender');
            const isOnline = String($root.data('online')) === '1';

            if (!isOnline && this.toggleOnlineUrl) {
                try {
                    if (window.AjaxHelper) {
                        await window.AjaxHelper.post(this.toggleOnlineUrl, {}, {
                            showLoader: false,
                            preventDuplicate: false,
                        });
                    } else {
                        await $.post(this.toggleOnlineUrl);
                    }
                    $(document).trigger('rider:online-changed', [true]);
                } catch (_) {
                    this.setOnline(true);
                }
            } else {
                this.setOnline(true);
            }

            await this.sendCurrentLocation();
        },

        start(url) {
            this.stop();
            this.url = url;

            const sendPosition = (position) => {
                const now = Date.now();
                if (now - this.lastSentAt < this.minIntervalMs) {
                    return;
                }

                this.lastSentAt = now;
                this.sendPosition(url, position);
            };

            const handleError = () => {};

            this.sendCurrentLocation();

            this.watchId = navigator.geolocation.watchPosition(sendPosition, handleError, {
                enableHighAccuracy: true,
                maximumAge: 10000,
                timeout: 20000,
            });
        },

        stop() {
            if (this.watchId !== null) {
                navigator.geolocation.clearWatch(this.watchId);
                this.watchId = null;
            }
        },
    };

    window.RiderLocationSender = RiderLocationSender;

    $(document).ready(function () {
        RiderLocationSender.init();
    });
})(window, window.jQuery);
