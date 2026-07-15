/**
 * Rider delivery offers — vertical card stack, newest at bottom.
 */
(function (window, $) {
    'use strict';

    if (!$) {
        return;
    }

    const POLL_INTERVAL_MS = 10000;
    const WATCH_INTERVAL_MS = 4000;
    const CARD_LIFETIME_MS = 15000;
    const DECLINE_SNOOZE_MS = 45000;
    const MAX_VISIBLE_CARDS = 3;
    const EXIT_ANIMATION_MS = 340;

    const RiderDeliveryOffers = {
        $root: null,
        $list: null,
        $count: null,
        urls: {},
        pollTimer: null,
        watchTimer: null,
        cardTimers: {},
        snoozedUntil: 0,
        snoozedOffers: {},
        isSubmitting: false,
        knownOffers: [],

        init() {
            this.$root = $('#rider-delivery-offers');
            if (!this.$root.length) {
                return;
            }

            this.$list = $('#rider-offer-stack-list');
            this.$count = $('#rider-offer-count');
            this.urls = {
                offers: this.$root.data('offers-url'),
                claim: this.$root.data('claim-url'),
            };

            this.bindEvents();
            this.pollOffers();
            this.startPolling();
            this.bindLiveUpdates();
        },

        bindEvents() {
            const self = this;

            this.$root.on('click', '.btn-offer-accept', function () {
                if ($(this).prop('disabled')) {
                    return;
                }
                self.acceptOffer($(this).data('uuid'), $(this));
            });

            this.$root.on('click', '.btn-offer-decline', function () {
                if ($(this).prop('disabled')) {
                    return;
                }
                self.declineOffer($(this).data('uuid'), $(this));
            });
        },

        bindLiveUpdates() {
            const self = this;

            if (window.RealtimeHelper) {
                window.RealtimeHelper.onDeliveryUpdated(function (detail) {
                    if (!detail || ['created', 'claimed', 'assigned'].includes(detail.event_type)) {
                        self.pollOffers();
                    }
                });
            }
        },

        startPolling() {
            this.pollTimer = setInterval(() => this.pollOffers(), POLL_INTERVAL_MS);
        },

        startWatch() {
            this.stopWatch();
            if (this.$list.children('.rider-offer-card').length) {
                this.watchTimer = setInterval(() => this.pollOffers(true), WATCH_INTERVAL_MS);
            }
        },

        stopWatch() {
            if (this.watchTimer) {
                clearInterval(this.watchTimer);
                this.watchTimer = null;
            }
        },

        pollOffers(silent) {
            const self = this;

            if (!this.urls.offers || !window.AjaxHelper || this.isSubmitting) {
                return;
            }

            window.AjaxHelper.get(this.urls.offers, null, {
                showLoader: false,
                success(response) {
                    const offers = response.data || [];
                    self.handleOffers(offers, silent);
                },
            });
        },

        filterSnoozedOffers(offers) {
            const now = Date.now();
            return offers.filter((offer) => {
                const until = this.snoozedOffers[offer.uuid] || 0;
                return until <= now;
            });
        },

        handleOffers(offers, silent) {
            if (Date.now() < this.snoozedUntil) {
                return;
            }

            const activeOffers = this.filterSnoozedOffers(offers);
            this.knownOffers = activeOffers;

            if (!activeOffers.length) {
                this.hideStack();
                return;
            }

            const visibleOffers = activeOffers.slice(-MAX_VISIBLE_CARDS);
            this.syncStack(visibleOffers, activeOffers.length);
            this.showStack();
            this.startWatch();
        },

        syncStack(visibleOffers, totalCount) {
            const self = this;
            const targetUuids = visibleOffers.map((offer) => offer.uuid);

            this.updateCount(totalCount);

            this.$list.children('.rider-offer-card').each(function () {
                const uuid = $(this).data('uuid');
                const $card = $(this);

                if ($card.hasClass('is-declining') || $card.hasClass('is-accepting') || $card.hasClass('is-removing')) {
                    return;
                }

                if (!targetUuids.includes(uuid)) {
                    self.removeCard($card);
                }
            });

            visibleOffers.forEach((offer) => {
                let $card = self.$list.find('[data-uuid="' + offer.uuid + '"]');

                if (!$card.length) {
                    $card = $(self.buildCardHtml(offer));
                    self.$list.append($card);
                    self.startCardTimer(offer.uuid, $card);
                }

                self.$list.append($card);
            });

            while (self.$list.children('.rider-offer-card').length > MAX_VISIBLE_CARDS) {
                const $oldest = self.$list.children('.rider-offer-card').first();
                if ($oldest.hasClass('is-declining') || $oldest.hasClass('is-accepting')) {
                    break;
                }
                self.removeCard($oldest);
            }
        },

        buildCardHtml(offer) {
            const fee = (parseFloat(offer.delivery_fee) || 0).toFixed(2);
            const tracking = offer.tracking_number
                ? '<div class="text-muted small mb-2">Tracking: ' + offer.tracking_number + '</div>'
                : '';
            const seconds = Math.round(CARD_LIFETIME_MS / 1000);

            return (
                '<div class="rider-offer-card is-entering" data-uuid="' + offer.uuid + '">' +
                '<div class="rider-offer-progress" aria-hidden="true">' +
                '<span style="--offer-duration:' + CARD_LIFETIME_MS + 'ms"></span></div>' +
                '<div class="d-flex justify-content-between align-items-start gap-2 mb-2">' +
                '<div><i class="fa-solid fa-store me-1 text-primary"></i><strong>' +
                (offer.shop_name || 'Shop') +
                '</strong></div>' +
                '<div class="text-success fw-bold">$' + fee + '</div>' +
                '</div>' +
                tracking +
                '<div class="mb-2">' +
                '<span class="text-muted small d-block">Pickup</span>' +
                '<p class="small mb-0 rider-offer-address">' + (offer.pickup_address || '—') + '</p>' +
                '</div>' +
                '<div class="mb-3">' +
                '<span class="text-muted small d-block">Delivery</span>' +
                '<p class="small mb-0 rider-offer-address">' + (offer.delivery_address || '—') + '</p>' +
                '</div>' +
                '<div class="rider-offer-card-footer">' +
                '<span class="rider-offer-timer text-muted small">' +
                '<i class="fa-regular fa-clock me-1"></i>Auto-hide in ' + seconds + 's</span>' +
                '<div class="d-flex gap-2">' +
                '<button type="button" class="btn btn-sm btn-light btn-offer-decline" data-uuid="' + offer.uuid + '">' +
                '<i class="fa-solid fa-forward me-1"></i>Not now</button>' +
                '<button type="button" class="btn btn-sm btn-success btn-offer-accept" data-uuid="' + offer.uuid + '">' +
                '<i class="fa-solid fa-check me-1"></i>Accept</button>' +
                '</div></div></div>'
            );
        },

        startCardTimer(uuid, $card) {
            const self = this;
            this.clearCardTimer(uuid);

            requestAnimationFrame(() => {
                $card.removeClass('is-entering');
            });

            this.cardTimers[uuid] = setTimeout(() => {
                const $activeCard = self.$list.find('[data-uuid="' + uuid + '"]');
                if (!$activeCard.length || $activeCard.hasClass('is-declining') || $activeCard.hasClass('is-accepting')) {
                    return;
                }

                self.snoozedOffers[uuid] = Date.now() + POLL_INTERVAL_MS;
                self.removeCard($activeCard, () => {
                    if (!self.$list.children('.rider-offer-card').length) {
                        self.snoozedUntil = Date.now() + POLL_INTERVAL_MS;
                    }
                    self.pollOffers(true);
                });
            }, CARD_LIFETIME_MS);
        },

        clearCardTimer(uuid) {
            if (this.cardTimers[uuid]) {
                clearTimeout(this.cardTimers[uuid]);
                delete this.cardTimers[uuid];
            }
        },

        setCardBusy($card, busy) {
            $card.find('button').prop('disabled', !!busy);
            $card.toggleClass('is-busy', !!busy);
        },

        removeCard($card, callback, mode) {
            const uuid = $card.data('uuid');
            this.clearCardTimer(uuid);

            if ($card.hasClass('is-removing') || $card.hasClass('is-declining') || $card.hasClass('is-accepting')) {
                return;
            }

            const exitClass = mode === 'decline'
                ? 'is-declining'
                : mode === 'accept'
                    ? 'is-accepting'
                    : 'is-removing';

            $card.addClass(exitClass);
            setTimeout(() => {
                $card.remove();
                this.updateStackVisibility();
                if (typeof callback === 'function') {
                    callback();
                }
            }, EXIT_ANIMATION_MS);
        },

        clearAllCards() {
            const self = this;
            this.$list.children('.rider-offer-card').each(function () {
                self.clearCardTimer($(this).data('uuid'));
            });
            this.$list.empty();
            this.cardTimers = {};
        },

        updateCount(totalCount) {
            if (!this.$count.length) {
                return;
            }

            const next = String(totalCount);
            if (this.$count.text() !== next) {
                this.$count.text(next).addClass('is-pulse');
                setTimeout(() => this.$count.removeClass('is-pulse'), 450);
            }
        },

        showStack() {
            if (this.$root.hasClass('is-visible')) {
                return;
            }

            this.$root.addClass('is-visible');
        },

        hideStack() {
            if (!this.$root.hasClass('is-visible')) {
                return;
            }

            this.$root.removeClass('is-visible');
            setTimeout(() => {
                if (!this.$root.hasClass('is-visible')) {
                    this.clearAllCards();
                    this.stopWatch();
                }
            }, 380);
        },

        updateStackVisibility() {
            if (!this.$list.children('.rider-offer-card').length) {
                this.hideStack();
            }
        },

        acceptOffer(uuid, $btn) {
            const self = this;
            const url = this.urls.claim.replace('__ID__', uuid);
            const $card = this.$list.find('[data-uuid="' + uuid + '"]');

            this.isSubmitting = true;
            this.setCardBusy($card, true);
            window.FormHelper.setSubmitLoading($btn, true);

            window.AjaxHelper.post(url, {}, {
                showLoader: false,
                success(response) {
                    if (window.RiderLocationSender && typeof window.RiderLocationSender.prepareForLiveDelivery === 'function') {
                        window.RiderLocationSender.prepareForLiveDelivery();
                    }
                    if ($card.length) {
                        self.removeCard($card, null, 'accept');
                    }
                    if (window.NotificationHelper) {
                        window.NotificationHelper.success(
                            response.message || 'Delivery accepted successfully.'
                        );
                    }
                    self.finishAction($btn);
                    self.pollOffers(true);
                },
                error(xhr, textStatus, errorThrown, handled) {
                    self.setCardBusy($card, false);
                    if (window.NotificationHelper) {
                        window.NotificationHelper.error(
                            handled?.message || 'Unable to accept this delivery.'
                        );
                    }
                    self.finishAction($btn);
                    self.pollOffers(true);
                },
            });
        },

        declineOffer(uuid, $btn) {
            const self = this;
            const $card = this.$list.find('[data-uuid="' + uuid + '"]');

            this.snoozedOffers[uuid] = Date.now() + DECLINE_SNOOZE_MS;
            this.setCardBusy($card, true);

            if (window.NotificationHelper) {
                window.NotificationHelper.info(
                    'Offer hidden for now. You may see it again shortly.',
                    'Skipped'
                );
            }

            if ($card.length) {
                this.removeCard($card, () => {
                    if (!self.$list.children('.rider-offer-card').length) {
                        self.snoozedUntil = Date.now() + 2500;
                    }
                    self.pollOffers(true);
                }, 'decline');
            } else {
                this.pollOffers(true);
            }
        },

        finishAction($btn) {
            this.isSubmitting = false;
            window.FormHelper.setSubmitLoading($btn, false);
        },
    };

    window.RiderDeliveryOffers = RiderDeliveryOffers;

    $(document).ready(function () {
        RiderDeliveryOffers.init();
    });
})(window, window.jQuery);
