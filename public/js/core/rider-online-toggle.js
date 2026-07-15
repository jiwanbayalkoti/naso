/**
 * Rider Online / Offline toggle for web topbar + profile page.
 */
(function (window, $) {
    'use strict';

    if (!$) {
        return;
    }

    const RiderOnlineToggle = {
        busy: false,

        init() {
            this.bind($('#rider-online-toggle'));
            this.bind($('#rider-online-toggle-profile'));

            $(document).on('rider:online-changed', (_event, isOnline) => {
                this.renderAll(!!isOnline);
            });
        },

        bind($root) {
            if (!$root.length) {
                return;
            }

            const self = this;
            $root.on('click', '.rider-online-btn, #rider-online-switch', function (event) {
                event.preventDefault();
                self.toggle($root);
            });
        },

        preferredOnlineFromResponse(data) {
            if (!data || typeof data !== 'object') {
                return null;
            }
            if (typeof data.wants_online !== 'undefined') {
                return !!data.wants_online;
            }
            if (typeof data.is_online !== 'undefined') {
                return !!data.is_online;
            }
            return null;
        },

        toggle($root) {
            if (this.busy) {
                return;
            }

            const url = $root.data('toggle-url');
            if (!url) {
                return;
            }

            const currentlyOnline = String($root.attr('data-online')) === '1';
            const nextLabel = currentlyOnline ? 'Offline' : 'Online';
            const self = this;

            const doToggle = () => {
                self.busy = true;
                self.setLoading(true);

                const onDone = () => {
                    self.busy = false;
                    self.setLoading(false);
                };

                const onSuccess = (response) => {
                    const data = response.data || response || {};
                    const isOnline = self.preferredOnlineFromResponse(data);
                    if (isOnline === null) {
                        onDone();
                        return;
                    }

                    self.renderAll(isOnline);
                    $(document).trigger('rider:online-changed', [isOnline]);

                    if (window.NotificationHelper) {
                        window.NotificationHelper.success(
                            isOnline
                                ? 'You are Online. Offers can now reach you.'
                                : 'You are Offline. You will not receive new offers.'
                        );
                    }

                    onDone();
                };

                if (window.AjaxHelper) {
                    window.AjaxHelper.post(url, {}, {
                        showLoader: true,
                        loaderMessage: 'Updating status...',
                        success: onSuccess,
                        error: onDone,
                    });
                } else {
                    $.post(url)
                        .done((response) => onSuccess(response))
                        .fail(onDone);
                }
            };

            if (window.NotificationHelper && window.NotificationHelper.confirm) {
                window.NotificationHelper.confirm({
                    title: 'Go ' + nextLabel + '?',
                    text: currentlyOnline
                        ? 'You will stop receiving new delivery offers until you go Online again.'
                        : 'Shops and admins will see you as available for deliveries while this tab stays open.',
                    icon: 'question',
                    confirmButtonText: 'Yes, go ' + nextLabel.toLowerCase(),
                }).then((result) => {
                    if (result.isConfirmed) {
                        doToggle();
                    }
                });
            } else {
                doToggle();
            }
        },

        setLoading(isLoading) {
            $('.rider-online-btn').prop('disabled', !!isLoading);
            $('#rider-online-switch').prop('disabled', !!isLoading);
        },

        renderAll(isOnline) {
            $('[data-toggle-url].rider-online-toggle, #rider-online-toggle, #rider-online-toggle-profile').each(function () {
                const $root = $(this);
                $root.attr('data-online', isOnline ? '1' : '0');

                const $btn = $root.find('.rider-online-btn');
                $btn
                    .toggleClass('is-online', isOnline)
                    .toggleClass('is-offline', !isOnline)
                    .attr(
                        'title',
                        isOnline
                            ? 'You prefer Online — click to go Offline'
                            : 'You are Offline — click to go Online'
                    );

                $root.find('.rider-online-label').text(isOnline ? 'Online' : 'Offline');
                $root.find('.rider-online-hint').text(
                    isOnline ? 'Tap to go offline' : 'Tap to go online'
                );

                const $switch = $root.find('#rider-online-switch, .form-check-input');
                if ($switch.length) {
                    $switch.prop('checked', isOnline);
                }

                $root.find('.rider-online-status-text').text(
                    isOnline
                        ? 'Preferred Online — live while logged in'
                        : 'Offline — turn Online when you want offers'
                );
            });

            const $sender = $('#rider-location-sender');
            if ($sender.length) {
                $sender.attr('data-online', isOnline ? '1' : '0');
            }
        },
    };

    window.RiderOnlineToggle = RiderOnlineToggle;

    $(document).ready(function () {
        RiderOnlineToggle.init();
    });
})(window, window.jQuery);
