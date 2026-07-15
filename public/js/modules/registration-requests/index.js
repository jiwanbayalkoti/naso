/**
 * Registration requests module — review, approve, reject.
 */
(function (window, $) {
    'use strict';

    if (!$) {
        return;
    }

    const RegistrationRequestsModule = {
        table: null,
        $module: null,
        routes: {},
        currentRequest: null,
        searchTimer: null,

        init() {
            this.$module = $('#registration-requests-module');
            if (!this.$module.length) {
                return;
            }

            this.routes = this.$module.data('routes') || {};
            this.initDataTable();
            this.bindEvents();
        },

        initDataTable() {
            const self = this;

            this.table = window.DataTableHelper.initDataTable('#registration-requests-table', {
                url: this.routes.datatable,
                order: [[5, 'desc']],
                ajaxData(d) {
                    d.search_filter = $('#filter-search').val();
                    d.type = $('#filter-type').val();
                    return d;
                },
                columns: [
                    {
                        data: 'type_label',
                        name: 'type',
                        render(data, type, row) {
                            const badge = row.type === 'shop' ? 'info' : 'success';
                            return '<span class="badge bg-' + badge + '">' + (data || row.type) + '</span>';
                        },
                    },
                    { data: 'name', name: 'name' },
                    { data: 'email', name: 'email', defaultContent: '—' },
                    { data: 'phone', name: 'phone', defaultContent: '—' },
                    {
                        data: 'documents_count',
                        name: 'documents_count',
                        searchable: false,
                        render(data) {
                            return '<span class="badge bg-secondary">' + (data || 0) + ' docs</span>';
                        },
                    },
                    { data: 'submitted_at', name: 'submitted_at' },
                    {
                        data: 'approval_status_label',
                        name: 'approval_status',
                        render() {
                            return '<span class="badge bg-warning text-dark">Pending</span>';
                        },
                    },
                    {
                        data: 'actions',
                        name: 'actions',
                        orderable: false,
                        searchable: false,
                        className: 'text-end',
                    },
                ],
            });
        },

        bindEvents() {
            const self = this;

            this.$module.on('click', '#btn-filter', () => self.applyFilters());
            this.$module.on('click', '#btn-reset-filters', () => self.resetFilters());

            this.$module.on('input', '#filter-search', () => {
                clearTimeout(self.searchTimer);
                self.searchTimer = setTimeout(() => self.applyFilters(), 400);
            });

            this.$module.on('change', '#filter-type', () => self.applyFilters());

            this.$module.on('click', '.btn-review, .btn-approve, .btn-reject', function () {
                const payload = {
                    id: $(this).data('id'),
                    type: $(this).data('type'),
                    name: $(this).data('name'),
                };

                if ($(this).hasClass('btn-review')) {
                    self.openReviewModal(payload);
                } else if ($(this).hasClass('btn-approve')) {
                    self.approveRequest(payload);
                } else {
                    self.openRejectModal(payload);
                }
            });

            $('#review-approve-btn').on('click', () => {
                if (self.currentRequest) {
                    self.approveRequest(self.currentRequest, true);
                }
            });

            $('#review-reject-btn').on('click', () => {
                if (self.currentRequest) {
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('review-modal')).hide();
                    self.openRejectModal(self.currentRequest);
                }
            });

            $('#reject-submit-btn').on('click', () => self.submitReject());
        },

        openReviewModal(request) {
            const self = this;
            this.currentRequest = request;
            const url = this.resolveRoute(this.routes.show, request);

            $('#review-content').html('<div class="text-center py-4 text-muted">Loading...</div>');
            bootstrap.Modal.getOrCreateInstance(document.getElementById('review-modal')).show();

            window.AjaxHelper.get(url, null, {
                showLoader: false,
                success(response) {
                    $('#review-content').html(self.renderReviewContent(response.data || response, request.type));
                },
                error() {
                    $('#review-content').html('<div class="alert alert-danger mb-0">Failed to load registration details.</div>');
                },
            });
        },

        renderReviewContent(data, type) {
            const docs = data.documents || [];
            let html = '<div class="row g-3">';

            html += '<div class="col-md-6"><strong>Type:</strong> ' + (type === 'shop' ? 'Shop' : 'Rider') + '</div>';
            html += '<div class="col-md-6"><strong>Status:</strong> <span class="badge bg-warning text-dark">' + (data.approval_status_label || 'Pending') + '</span></div>';

            if (type === 'shop') {
                html += '<div class="col-md-6"><strong>Shop Name:</strong> ' + (data.name || '—') + '</div>';
                html += '<div class="col-md-6"><strong>Owner:</strong> ' + (data.owner_name || data.user?.name || '—') + '</div>';
                html += '<div class="col-md-6"><strong>Email:</strong> ' + (data.user?.email || data.email || '—') + '</div>';
                html += '<div class="col-md-6"><strong>Phone:</strong> ' + (data.user?.phone || data.phone || '—') + '</div>';
                html += '<div class="col-md-6"><strong>PAN:</strong> ' + (data.pan_number || '—') + '</div>';
                html += '<div class="col-md-6"><strong>NID:</strong> ' + (data.nid_number || '—') + '</div>';
                html += '<div class="col-12"><strong>Address:</strong> ' + (data.address || '—') + ', ' + (data.city || '') + '</div>';
            } else {
                html += '<div class="col-md-6"><strong>Name:</strong> ' + (data.name || data.user?.name || '—') + '</div>';
                html += '<div class="col-md-6"><strong>Email:</strong> ' + (data.email || data.user?.email || '—') + '</div>';
                html += '<div class="col-md-6"><strong>Phone:</strong> ' + (data.phone || data.user?.phone || '—') + '</div>';
                html += '<div class="col-md-6"><strong>Vehicle:</strong> ' + (data.vehicle_type || '—') + ' / ' + (data.vehicle_number || '—') + '</div>';
                html += '<div class="col-md-6"><strong>License:</strong> ' + (data.license_number || '—') + '</div>';
                html += '<div class="col-md-6"><strong>NID:</strong> ' + (data.nid_number || '—') + '</div>';
            }

            html += '</div><hr><h6 class="mb-3">Uploaded Documents</h6>';

            if (!docs.length) {
                html += '<p class="text-muted mb-0">No documents uploaded.</p>';
            } else {
                html += '<div class="row g-3">';
                docs.forEach((doc) => {
                    html += '<div class="col-md-6"><div class="border rounded p-3 h-100">';
                    html += '<div class="fw-semibold">' + (doc.type_label || doc.type) + '</div>';
                    if (doc.document_number) {
                        html += '<div class="small text-muted">No: ' + doc.document_number + '</div>';
                    }
                    if (doc.file_url) {
                        html += '<a href="' + doc.file_url + '" target="_blank" class="btn btn-sm btn-outline-primary mt-2"><i class="fa-solid fa-file-arrow-down me-1"></i>View Document</a>';
                    }
                    html += '</div></div>';
                });
                html += '</div>';
            }

            return html;
        },

        approveRequest(request, closeReview) {
            const self = this;
            const url = this.resolveRoute(this.routes.approve, request);

            window.NotificationHelper.confirm(
                'Approve registration?',
                'Approve ' + (request.name || 'this registration') + '?',
                'Yes, approve'
            ).then((result) => {
                if (!result.isConfirmed) {
                    return;
                }

                window.AjaxHelper.post(url, null, {
                    loaderMessage: 'Approving registration...',
                    success(response) {
                        window.NotificationHelper.success(response.message || 'Registration approved.');
                        if (closeReview) {
                            bootstrap.Modal.getOrCreateInstance(document.getElementById('review-modal')).hide();
                        }
                        self.currentRequest = null;
                        window.DataTableHelper.reload(self.table, false);
                    },
                });
            });
        },

        openRejectModal(request) {
            this.currentRequest = request;
            $('#reject_reason').val('');
            window.FormHelper?.clearErrors($('#reject-form'));
            bootstrap.Modal.getOrCreateInstance(document.getElementById('reject-modal')).show();
        },

        submitReject() {
            const self = this;
            const reason = $('#reject_reason').val().trim();

            if (!reason) {
                window.NotificationHelper.error('Please provide a rejection reason.');
                return;
            }

            if (!this.currentRequest) {
                return;
            }

            const url = this.resolveRoute(this.routes.reject, this.currentRequest);

            window.AjaxHelper.post(url, { reason }, {
                loaderMessage: 'Rejecting registration...',
                success(response) {
                    window.NotificationHelper.success(response.message || 'Registration rejected.');
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('reject-modal')).hide();
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('review-modal')).hide();
                    self.currentRequest = null;
                    window.DataTableHelper.reload(self.table, false);
                },
                error(xhr, textStatus, errorThrown, handled) {
                    if (handled?.errors && window.FormHelper) {
                        window.FormHelper.showErrors($('#reject-form'), handled.errors);
                    }
                },
            });
        },

        applyFilters() {
            window.DataTableHelper.reload(this.table, true);
        },

        resetFilters() {
            $('#filter-search').val('');
            $('#filter-type').val('');
            this.applyFilters();
        },

        resolveRoute(template, request) {
            return (template || '')
                .replace('__TYPE__', request.type)
                .replace('__ID__', request.id);
        },
    };

    window.RegistrationRequestsModule = RegistrationRequestsModule;

    $(document).ready(function () {
        RegistrationRequestsModule.init();
    });
})(window, window.jQuery);
