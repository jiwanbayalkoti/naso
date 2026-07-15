/**
 * Activity Logs module — read-only DataTable with filters.
 */
(function (window, $) {
    'use strict';

    if (!$) {
        return;
    }

    const ActivityLogsModule = {
        table: null,
        $module: null,
        routes: {},
        searchTimer: null,

        init() {
            this.$module = $('#activity-logs-module');
            if (!this.$module.length) {
                return;
            }

            this.routes = this.$module.data('routes') || {};
            this.initDataTable();
            this.bindEvents();
            window.FormHelper.initSelect2(this.$module);
        },

        initDataTable() {
            const self = this;

            this.table = window.DataTableHelper.initDataTable('#activity-logs-table', {
                url: this.routes.datatable,
                order: [[0, 'desc']],
                exportButtons: self.getExportButtons(),
                ajaxData(d) {
                    d.search_filter = $('#filter-search').val();
                    d.activity_type = $('#filter-type').val();
                    d.user_id = $('#filter-user').val();
                    d.date_from = $('#filter-date-from').val();
                    d.date_to = $('#filter-date-to').val();
                    return d;
                },
                columns: [
                    { data: 'created_at', name: 'created_at' },
                    { data: 'user_name', name: 'user.name', defaultContent: 'System' },
                    {
                        data: 'activity_type',
                        name: 'activity_type',
                        render(data) {
                            if (!data) {
                                return '—';
                            }
                            const label = data.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
                            return '<span class="badge bg-light text-dark border">' + label + '</span>';
                        },
                    },
                    {
                        data: 'description',
                        name: 'description',
                        render(data) {
                            if (!data) {
                                return '—';
                            }
                            const truncated = data.length > 80 ? data.substring(0, 80) + '…' : data;
                            return '<span title="' + $('<div>').text(data).html() + '">' + truncated + '</span>';
                        },
                    },
                    {
                        data: 'subject_type',
                        name: 'subject_type',
                        orderable: false,
                        render(data, type, row) {
                            if (!data) {
                                return '—';
                            }
                            const shortType = data.split('\\').pop();
                            const id = row.subject_id ? ' #' + row.subject_id : '';
                            return shortType + id;
                        },
                    },
                    { data: 'ip_address', name: 'ip_address', defaultContent: '—' },
                ],
            });
        },

        getExportButtons() {
            return [
                'copy',
                'excel',
                {
                    extend: 'csv',
                    className: 'btn btn-sm btn-outline-info',
                    text: '<i class="fa-solid fa-file-csv me-1"></i> CSV',
                },
                'pdf',
                'print',
                'colvis',
            ];
        },

        bindEvents() {
            const self = this;

            this.$module.on('click', '#btn-filter', () => self.applyFilters());
            this.$module.on('click', '#btn-reset-filters', () => self.resetFilters());

            this.$module.on('input', '#filter-search', () => {
                clearTimeout(self.searchTimer);
                self.searchTimer = setTimeout(() => self.applyFilters(), 400);
            });

            this.$module.on('change', '#filter-type, #filter-user, #filter-date-from, #filter-date-to', () => {
                self.applyFilters();
            });
        },

        applyFilters() {
            window.DataTableHelper.reload(this.table, true);
        },

        resetFilters() {
            $('#filter-search').val('');
            $('#filter-type').val('');
            $('#filter-user').val(null).trigger('change');
            $('#filter-date-from').val('');
            $('#filter-date-to').val('');
            this.applyFilters();
        },
    };

    window.ActivityLogsModule = ActivityLogsModule;

    $(document).ready(function () {
        ActivityLogsModule.init();
    });
})(window, window.jQuery);
