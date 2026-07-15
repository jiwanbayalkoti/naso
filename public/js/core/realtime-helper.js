/**
 * Real-time helper — polling-based live updates with optional Echo/Reverb hook.
 */
(function (window, $) {
    'use strict';

    if (!$) {
        return;
    }

    const RealtimeHelper = {
        intervals: {},
        refreshInterval: 30000,

        init(options) {
            const settings = $.extend(
                {
                    refreshInterval: 30000,
                },
                options || {}
            );

            this.refreshInterval = settings.refreshInterval;
        },

        /**
         * Start polling callbacks on an interval.
         */
        startPolling(name, callback, intervalMs) {
            this.stopPolling(name);

            if (typeof callback === 'function') {
                callback();
            }

            this.intervals[name] = setInterval(callback, intervalMs || this.refreshInterval);
        },

        stopPolling(name) {
            if (this.intervals[name]) {
                clearInterval(this.intervals[name]);
                delete this.intervals[name];
            }
        },

        stopAll() {
            Object.keys(this.intervals).forEach((name) => this.stopPolling(name));
        },

        /**
         * Dispatch a custom DOM event for module listeners.
         */
        emit(eventName, detail) {
            document.dispatchEvent(new CustomEvent(eventName, { detail: detail || {} }));
        },

        /**
         * Listen for delivery updates (from polling or future WebSocket).
         */
        onDeliveryUpdated(callback) {
            document.addEventListener('naso:delivery-updated', function (event) {
                if (typeof callback === 'function') {
                    callback(event.detail || {});
                }
            });
        },

        /**
         * Initialize Laravel Echo when Reverb/Pusher is configured (future).
         */
        initEcho() {
            if (!window.Echo || !window.Pusher) {
                return false;
            }

            try {
                window.Echo.channel('deliveries').listen('.delivery.updated', (payload) => {
                    this.emit('naso:delivery-updated', payload);
                });

                return true;
            } catch (error) {
                console.warn('Echo initialization failed:', error);
                return false;
            }
        },
    };

    window.RealtimeHelper = RealtimeHelper;
})(window, window.jQuery);
