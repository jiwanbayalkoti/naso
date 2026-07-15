/**
 * Audit Logs module — read-only DataTable with filters and detail view.
 */
(function (window, $) {
    'use strict';

    if (!$) {
        return;
    }

    const AuditLogsModule = {
        table: null,
        $module: null,
        routes: {},
        searchTimer: null,

        init() {
            this.$module = $('#audit-logs-module');
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

            this.table = window.DataTableHelper.initDataTable('#audit-logs-table', {
                url: this.routes.datatable,
                order: [[0, 'desc']],
                exportButtons: self.getExportButtons(),
                ajaxData(d) {
                    d.search_filter = $('#filter-search').val();
                    d.event = $('#filter-event').val();
                    d.user_id = $('#filter-user').val();
                    d.auditable_type = $('#filter-model').val();
                    d.date_from = $('#filter-date-from').val();
                    d.date_to = $('#filter-date-to').val();
                    return d;
                },
                columns: [
                    { data: 'created_at', name: 'created_at' },
                    { data: 'user_name', name: 'user.name', defaultContent: 'System' },
                    {
                        data: 'event',
                        name: 'event',
                        render(data) {
                            const colors = {
                                created: 'success',
                                updated: 'primary',
                                deleted: 'danger',
                                restored: 'info',
                            };
                            const color = colors[data] || 'secondary';
                            return '<span class="badge bg-' + color + '">' + (data ? data.charAt(0).toUpperCase() + data.slice(1) : '—') + '</span>';
                        },
                    },
                    {
                        data: 'auditable_type',
                        name: 'auditable_type',
                        render(data) {
                            return data ? data.split('\\').pop() : '—';
                        },
                    },
                    { data: 'auditable_id', name: 'auditable_id', defaultContent: '—' },
                    {
                        data: 'changes_summary',
                        name: 'changes_summary',
                        orderable: false,
                        searchable: false,
                        render(data, type, row) {
                            if (data) {
                                return (
                                    '<button type="button" class="btn btn-sm btn-link p-0 btn-view-audit">' +
                                    data +
                                    '</button>'
                                );
                            }
                            const oldCount = row.old_values ? Object.keys(row.old_values).length : 0;
                            const newCount = row.new_values ? Object.keys(row.new_values).length : 0;
                            if (oldCount || newCount) {
                                return (
                                    '<button type="button" class="btn btn-sm btn-link p-0 btn-view-audit">View ' +
                                    (oldCount + newCount) +
                                    ' field(s)</button>'
                                );
                            }
                            return '—';
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

            this.$module.on('change', '#filter-event, #filter-user, #filter-model, #filter-date-from, #filter-date-to', () => {
                self.applyFilters();
            });

            this.$module.on('click', '.btn-view-audit', function () {
                const rowData = self.table.row($(this).closest('tr')).data();
                if (rowData) {
                    self.showAuditDetail(rowData);
                }
            });
        },

        showAuditDetail(data) {
            const $content = $('#audit-detail-content');
            let html = '<div class="row g-3">';

            if (data.old_values && Object.keys(data.old_values).length) {
                html += '<div class="col-md-6"><h6 class="text-danger">Old Values</h6><pre class="bg-light p-3 rounded small mb-0">' +
                    this.escapeHtml(JSON.stringify(data.old_values, null, 2)) + '</pre></div>';
            }

            if (data.new_values && Object.keys(data.new_values).length) {
                html += '<div class="col-md-6"><h6 class="text-success">New Values</h6><pre class="bg-light p-3 rounded small mb-0">' +
                    this.escapeHtml(JSON.stringify(data.new_values, null, 2)) + '</pre></div>';
            }

            html += '</div>';

            if (!data.old_values && !data.new_values) {
                html = '<p class="text-muted mb-0">No change data available.</p>';
            }

            $content.html(html);
            bootstrap.Modal.getOrCreateInstance(document.getElementById('audit-detail-modal')).show();
        },

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        applyFilters() {
            window.DataTableHelper.reload(this.table, true);
        },

        resetFilters() {
            $('#filter-search').val('');
            $('#filter-event').val('');
            $('#filter-user').val(null).trigger('change');
            $('#filter-model').val('');
            $('#filter-date-from').val('');
            $('#filter-date-to').val('');
            this.applyFilters();
        },
    };

    window.AuditLogsModule = AuditLogsModule;

    $(document).ready(function () {
        AuditLogsModule.init();
    });
})(window, window.jQuery);
