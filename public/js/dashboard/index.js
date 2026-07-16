/**
 * Dashboard AJAX data loader for stat cards, charts, tables, and lists.
 */
(function (window, $) {
    'use strict';

    if (!$) {
        return;
    }

    const Dashboard = {
        charts: {},

        dashboardGet(url, data) {
            return window.AjaxHelper
                ? window.AjaxHelper.get(url, data, { showLoader: false, preventDuplicate: false })
                : $.getJSON(url, data);
        },

        init() {
            this.loadStats();
            this.loadDeliveryTrendsChart();
            this.loadDeliveryStatusChart();
            this.loadLatestDeliveries();

            if (!$('#dashboard-page').data('rider-dashboard')) {
                this.loadPendingDeliveries();
                this.loadOnlineRiders();
            }

            this.bindChartFilters();
            this.bindLiveRefresh();
        },

        bindLiveRefresh() {
            const interval = parseInt(
                document.body.dataset.dashboardRefresh || '30000',
                10
            );

            if (window.RealtimeHelper) {
                window.RealtimeHelper.startPolling('dashboard', () => {
                    this.loadStats();
                    this.loadLatestDeliveries();
                    if (!$('#dashboard-page').data('rider-dashboard')) {
                        this.loadPendingDeliveries();
                        this.loadOnlineRiders();
                    }
                }, interval);
            }
        },

        bindChartFilters() {
            $('#chart-period').on('change', () => {
                this.loadDeliveryTrendsChart();
            });
        },

        loadStats() {
            const $container = $('#dashboard-stats');
            const url = $container.data('url');

            if (!url) {
                return;
            }

            const request = this.dashboardGet(url);

            request
                .done((response) => {
                    const stats = response.data || response;

                    $container.find('[data-stat]').each(function () {
                        const $card = $(this);
                        const key = $card.data('stat');
                        const value = stats[key] ?? stats[key + '_count'] ?? 0;
                        const trend = stats[key + '_trend'] || stats.trends?.[key];
                        const isMoney = $card.data('money') === 1 || $card.attr('data-money') === '1';

                        let display = value;
                        if (typeof value === 'number') {
                            display = isMoney
                                ? 'Rs ' + value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                                : value.toLocaleString();
                        }

                        $card.find('[data-value]').text(display);

                        if (trend !== undefined && trend !== null) {
                            const $trend = $card.find('[data-trend]');
                            const isUp = Number(trend) >= 0;
                            $trend
                                .removeClass('d-none up down')
                                .addClass(isUp ? 'up' : 'down')
                                .html(
                                    '<i class="fa-solid fa-arrow-' +
                                        (isUp ? 'up' : 'down') +
                                        ' me-1"></i>' +
                                        Math.abs(trend) +
                                        '% vs last period'
                                );
                        }

                        $card.find('.stat-card-loading').addClass('d-none');
                        $card.find('.stat-card-content').removeClass('d-none');
                    });

                    $container.find('[data-href]').off('click.dashboardEarning').on('click.dashboardEarning', function () {
                        const href = $(this).data('href');
                        if (href) {
                            window.location.href = href;
                        }
                    });
                })
                .fail(() => {
                    $container.find('.stat-card-loading').html(
                        '<p class="text-muted small mb-0">Unable to load stats</p>'
                    );
                });
        },

        loadDeliveryTrendsChart() {
            const $wrapper = $('#delivery-trends-chart');
            const url = $wrapper.data('url');
            const period = $('#chart-period').val() || 30;

            if (!url || !window.Chart) {
                return;
            }

            const request = this.dashboardGet(url, { period });

            request.done((response) => {
                const data = response.data || response;
                const labels = data.labels || [];
                const values = data.values || data.datasets?.[0]?.data || [];

                $wrapper.find('.chart-placeholder').addClass('d-none');
                const canvas = document.getElementById('delivery-trends-canvas');
                canvas.classList.remove('d-none');

                if (this.charts.deliveryTrends) {
                    this.charts.deliveryTrends.destroy();
                }

                this.charts.deliveryTrends = new Chart(canvas, {
                    type: 'line',
                    data: {
                        labels,
                        datasets: [
                            {
                                label: 'Deliveries',
                                data: values,
                                borderColor: '#2563eb',
                                backgroundColor: 'rgba(37, 99, 235, 0.1)',
                                fill: true,
                                tension: 0.4,
                                pointRadius: 3,
                                pointHoverRadius: 5,
                            },
                        ],
                    },
                    options: this.getChartOptions('Deliveries over time'),
                });
            });
        },

        loadDeliveryStatusChart() {
            const $wrapper = $('#delivery-status-chart');
            const url = $wrapper.data('url');

            if (!url || !window.Chart) {
                return;
            }

            const request = this.dashboardGet(url);

            request.done((response) => {
                const data = response.data || response;
                const labels = data.labels || ['Pending', 'In Transit', 'Delivered', 'Cancelled'];
                const values = data.values || [0, 0, 0, 0];
                const colors = data.colors || ['#d97706', '#2563eb', '#16a34a', '#dc2626'];

                $wrapper.find('.chart-placeholder').addClass('d-none');
                const canvas = document.getElementById('delivery-status-canvas');
                canvas.classList.remove('d-none');

                if (this.charts.deliveryStatus) {
                    this.charts.deliveryStatus.destroy();
                }

                this.charts.deliveryStatus = new Chart(canvas, {
                    type: 'doughnut',
                    data: {
                        labels,
                        datasets: [
                            {
                                data: values,
                                backgroundColor: colors,
                                borderWidth: 2,
                                borderColor: '#fff',
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                            },
                        },
                    },
                });
            });
        },

        loadLatestDeliveries() {
            const $tbody = $('#latest-deliveries-body');
            const url = $('#latest-deliveries').data('url');

            if (!url) {
                return;
            }

            const request = this.dashboardGet(url);

            request
                .done((response) => {
                    const deliveries = response.data || response;

                    if (!deliveries.length) {
                        $tbody.html(
                            '<tr><td colspan="5" class="empty-state py-4">' +
                                '<i class="fa-solid fa-box-open d-block"></i>' +
                                'No deliveries found</td></tr>'
                        );
                        return;
                    }

                    const rows = deliveries
                        .map((item) => {
                            const status = (item.status || 'pending').toLowerCase().replace(/\s+/g, '-');
                            return (
                                '<tr>' +
                                '<td><strong>' +
                                (item.tracking_number || item.id || '—') +
                                '</strong></td>' +
                                '<td>' +
                                (item.shop_name || item.shop || '—') +
                                '</td>' +
                                '<td>' +
                                (item.rider_name || item.rider || 'Unassigned') +
                                '</td>' +
                                '<td><span class="badge-status ' +
                                status +
                                '">' +
                                (item.status || 'Pending') +
                                '</span></td>' +
                                '<td>' +
                                (item.created_at || item.date || '—') +
                                '</td>' +
                                '</tr>'
                            );
                        })
                        .join('');

                    $tbody.html(rows);
                })
                .fail(() => {
                    $tbody.html(
                        '<tr><td colspan="5" class="text-center text-muted py-4">Unable to load deliveries</td></tr>'
                    );
                });
        },

        loadPendingDeliveries() {
            const $list = $('#pending-deliveries-list');
            const url = $list.data('url');

            if (!url) {
                return;
            }

            const request = this.dashboardGet(url);

            request
                .done((response) => {
                    const items = response.data || response;
                    $('#pending-count-badge').text(items.length);

                    if (!items.length) {
                        $list.html(
                            '<div class="empty-state py-3">' +
                                '<i class="fa-solid fa-check-circle d-block text-success"></i>' +
                                'No pending deliveries</div>'
                        );
                        return;
                    }

                    const html = items
                        .map(
                            (item) =>
                                '<div class="rider-item">' +
                                '<div class="flex-grow-1">' +
                                '<div class="fw-semibold">' +
                                (item.tracking_number || item.id) +
                                '</div>' +
                                '<small class="text-muted">' +
                                (item.shop_name || item.shop || '') +
                                '</small>' +
                                '</div>' +
                                '<span class="badge-status pending">' +
                                (item.status || 'Pending') +
                                '</span>' +
                                '</div>'
                        )
                        .join('');

                    $list.html(html);
                })
                .fail(() => {
                    $list.html('<div class="text-center text-muted py-3">Unable to load</div>');
                });
        },

        loadOnlineRiders() {
            const $list = $('#online-riders-list');
            const url = $list.data('url');

            if (!url) {
                return;
            }

            const request = this.dashboardGet(url);

            request
                .done((response) => {
                    const riders = response.data || response;
                    const onlineCount = riders.filter(
                        (r) => (r.status || '').toLowerCase() === 'online'
                    ).length;
                    $('#online-riders-count-badge').text(onlineCount + ' online');

                    if (!riders.length) {
                        $list.html(
                            '<div class="empty-state py-3">' +
                                '<i class="fa-solid fa-motorcycle d-block"></i>' +
                                'No riders available</div>'
                        );
                        return;
                    }

                    const html = riders
                        .map((rider) => {
                            const status = (rider.status || 'offline').toLowerCase();
                            const initials = (rider.name || 'R')
                                .split(' ')
                                .map((n) => n[0])
                                .join('')
                                .substring(0, 2)
                                .toUpperCase();

                            return (
                                '<div class="rider-item">' +
                                '<div class="rider-avatar">' +
                                initials +
                                '</div>' +
                                '<div class="flex-grow-1">' +
                                '<div class="fw-semibold">' +
                                (rider.name || 'Unknown') +
                                '</div>' +
                                '<small class="text-muted">' +
                                '<span class="rider-status-dot ' +
                                status +
                                '"></span>' +
                                (rider.status || 'Offline') +
                                (rider.active_deliveries
                                    ? ' · ' + rider.active_deliveries + ' active'
                                    : '') +
                                '</small>' +
                                '</div>' +
                                '</div>'
                            );
                        })
                        .join('');

                    $list.html(html);
                })
                .fail(() => {
                    $list.html('<div class="text-center text-muted py-3">Unable to load</div>');
                });
        },

        getChartOptions(title) {
            return {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false,
                    },
                    title: {
                        display: false,
                        text: title,
                    },
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0,
                        },
                        grid: {
                            color: 'rgba(226, 232, 240, 0.6)',
                        },
                    },
                    x: {
                        grid: {
                            display: false,
                        },
                    },
                },
            };
        },
    };

    window.Dashboard = Dashboard;

    $(document).ready(function () {
        if ($('#dashboard-stats').length) {
            Dashboard.init();
        }
    });
})(window, window.jQuery);
