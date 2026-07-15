/**
 * In-app notifications for shop users (delivery completed alerts).
 */
(function (window, $) {
    'use strict';

    if (!$) {
        return;
    }

    const AppNotifications = {
        $root: null,
        urls: {},
        lastUnreadCount: 0,
        seenNotificationIds: new Set(),
        pollTimer: null,

        init() {
            this.$root = $('#app-notifications');
            if (!this.$root.length) {
                return;
            }

            this.urls = {
                index: this.$root.data('index-url'),
                unread: this.$root.data('unread-url'),
                readAll: this.$root.data('read-all-url'),
                read: this.$root.data('read-url'),
            };

            this.bindEvents();
            this.refresh(true);
            this.startPolling();
            this.bindLiveUpdates();
        },

        bindLiveUpdates() {
            const self = this;

            if (!window.RealtimeHelper) {
                return;
            }

            window.RealtimeHelper.onDeliveryUpdated(function (detail) {
                if (!detail) {
                    return;
                }

                if (detail.event_type === 'status_updated' && detail.status === 'completed') {
                    self.refresh(true);
                }
            });
        },

        bindEvents() {
            const self = this;

            this.$root.on('show.bs.dropdown', () => {
                self.loadList();
            });

            $('#notification-mark-all-read').on('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                self.markAllAsRead();
            });

            this.$root.on('click', '.notification-item', function (e) {
                e.preventDefault();
                const id = $(this).data('id');
                const url = $(this).data('url');
                self.markAsRead(id, url);
            });
        },

        startPolling() {
            // Mobile live web: poll less often to reduce network lag.
            const isMobile = window.matchMedia('(max-width: 991.98px)').matches;
            const interval = isMobile ? 25000 : 15000;
            this.pollTimer = setInterval(() => this.refresh(true), interval);
        },

        refresh(showToastForNew) {
            const self = this;

            if (!this.urls.unread || !window.AjaxHelper) {
                return;
            }

            window.AjaxHelper.get(this.urls.unread, null, {
                showLoader: false,
                success(response) {
                    const count = response.data?.count ?? 0;
                    self.updateBadge(count);

                    if (showToastForNew && count > self.lastUnreadCount) {
                        self.loadList(true);
                    } else if (showToastForNew && count > 0 && self.lastUnreadCount === 0) {
                        self.loadList(true);
                    }

                    self.lastUnreadCount = count;
                },
            });
        },

        loadList(showToastForNew) {
            const self = this;

            if (!this.urls.index || !window.AjaxHelper) {
                return;
            }

            window.AjaxHelper.get(this.urls.index, { limit: 20 }, {
                showLoader: false,
                success(response) {
                    const items = response.data || [];
                    self.renderList(items);

                    if (showToastForNew) {
                        self.toastNewItems(items);
                    }
                },
                error() {
                    $('#notification-list').html(
                        '<div class="text-center text-muted py-4 small">Unable to load notifications.</div>'
                    );
                },
            });
        },

        toastNewItems(items) {
            items.forEach((item) => {
                if (item.read_at || this.seenNotificationIds.has(item.id)) {
                    return;
                }

                this.seenNotificationIds.add(item.id);

                if (window.NotificationHelper && item.type === 'delivery_completed') {
                    window.NotificationHelper.success(item.message, 'Delivery Completed');
                }
            });
        },

        renderList(items) {
            const $list = $('#notification-list');

            if (!items.length) {
                $list.html('<div class="text-center text-muted py-4 small">No notifications yet.</div>');
                return;
            }

            const html = items.map((item) => {
                const isUnread = !item.read_at;
                const time = item.created_at ? new Date(item.created_at).toLocaleString() : '';
                const icon = item.type === 'delivery_completed'
                    ? 'fa-circle-check text-success'
                    : 'fa-bell text-primary';

                return (
                    '<a href="#" class="notification-item' + (isUnread ? ' is-unread' : '') + '"' +
                    ' data-id="' + item.id + '"' +
                    ' data-url="' + (item.url || '') + '">' +
                    '<div class="notification-item-icon"><i class="fa-solid ' + icon + '"></i></div>' +
                    '<div class="notification-item-body">' +
                    '<div class="notification-item-message">' + (item.message || '') + '</div>' +
                    '<div class="notification-item-time">' + time + '</div>' +
                    '</div></a>'
                );
            }).join('');

            $list.html(html);
        },

        updateBadge(count) {
            const $badge = $('#notification-unread-badge');

            if (count > 0) {
                $badge.text(count > 99 ? '99+' : count).removeClass('d-none');
            } else {
                $badge.addClass('d-none').text('0');
            }
        },

        markAsRead(id, url) {
            const readUrl = (this.urls.read || '').replace('__ID__', id);

            window.AjaxHelper.post(readUrl, {}, {
                showLoader: false,
                success: () => {
                    this.refresh();
                    if (url) {
                        window.location.href = url;
                    }
                },
            });
        },

        markAllAsRead() {
            window.AjaxHelper.post(this.urls.readAll, {}, {
                showLoader: false,
                success: () => {
                    this.lastUnreadCount = 0;
                    this.updateBadge(0);
                    this.loadList();
                },
            });
        },
    };

    window.AppNotifications = AppNotifications;

    $(document).ready(function () {
        AppNotifications.init();
    });
})(window, window.jQuery);
