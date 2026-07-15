/**
 * Deliveries module — DataTable, CRUD, assign rider, status updates.
 */
(function (window, $) {
    'use strict';

    if (!$) {
        return;
    }

    const STATUS_BADGE_MAP = {
        pending: 'pending',
        assigned: 'assigned',
        accepted: 'assigned',
        picked_up: 'in-transit',
        on_the_way: 'in-transit',
        delivered: 'delivered',
        completed: 'delivered',
        cancelled: 'cancelled',
    };

    const DeliveriesModule = {
        table: null,
        $module: null,
        routes: {},
        isRider: false,
        editId: null,
        assignDeliveryId: null,
        searchTimer: null,
        liveDeliveryStatuses: ['assigned', 'accepted', 'picked_up', 'on_the_way'],

        init() {
            this.$module = $('#deliveries-module');
            if (this.$module.length) {
                this.routes = this.$module.data('routes') || {};
                this.isRider = !!this.$module.data('is-rider');
                this.initDataTable();
                if (!this.isRider) {
                    this.initForm();
                    this.initAssignForm();
                }
                this.bindIndexEvents();
                this.bindLiveUpdates();
                window.FormHelper.initSelect2(this.$module);
                if (!this.isRider) {
                    this.handleEditQueryParam();
                }
            }

            if ($('#delivery-show-module').length) {
                this.routes = $.extend({}, this.routes, $('#delivery-show-module').data('routes') || {});
                this.initAssignForm();
                this.initShowPage();
            }
        },

        initDataTable() {
            const self = this;

            this.table = window.DataTableHelper.initDataTable('#deliveries-table', {
                url: this.routes.datatable,
                order: [[8, 'desc']],
                exportButtons: self.getExportButtons(),
                ajaxData(d) {
                    d.search_filter = $('#filter-search').val();
                    d.status = $('#filter-status').val();
                    d.shop_id = $('#filter-shop').val();
                    d.rider_id = $('#filter-rider').val();
                    d.date_from = $('#filter-date-from').val();
                    d.date_to = $('#filter-date-to').val();
                    return d;
                },
                columns: [
                    {
                        data: 'tracking_number',
                        name: 'tracking_number',
                        render(data) {
                            return '<strong>' + (data || '—') + '</strong>';
                        },
                    },
                    { data: 'shop_name', name: 'shop.name', defaultContent: '—' },
                    {
                        data: 'customer_name',
                        name: 'customer_name',
                        render(data, type, row) {
                            const phone = row.customer_phone ? '<br><small class="text-muted">' + row.customer_phone + '</small>' : '';
                            return (data || '—') + phone;
                        },
                    },
                    { data: 'rider_name', name: 'rider.user.name', defaultContent: '<span class="text-muted">Unassigned</span>' },
                    {
                        data: 'status',
                        name: 'status',
                        render(data) {
                            return self.renderStatusBadge(data);
                        },
                    },
                    {
                        data: 'priority',
                        name: 'priority',
                        render(data) {
                            const colors = { urgent: 'danger', high: 'warning text-dark', normal: 'secondary', low: 'light text-dark' };
                            const color = colors[data] || 'secondary';
                            return '<span class="badge bg-' + color + '">' + (data ? data.charAt(0).toUpperCase() + data.slice(1) : 'Normal') + '</span>';
                        },
                    },
                    {
                        data: 'delivery_fee',
                        name: 'delivery_fee',
                        render(data) {
                            return '$' + (parseFloat(data) || 0).toFixed(2);
                        },
                    },
                    {
                        data: 'payment_status',
                        name: 'payment_status',
                        render(data) {
                            const colors = { paid: 'success', pending: 'warning text-dark', failed: 'danger', refunded: 'info' };
                            const color = colors[data] || 'secondary';
                            return '<span class="badge bg-' + color + '">' + (data ? data.charAt(0).toUpperCase() + data.slice(1) : '—') + '</span>';
                        },
                    },
                    { data: 'created_at', name: 'created_at' },
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

        renderStatusBadge(status) {
            const label = (status || 'pending').replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
            const badgeClass = STATUS_BADGE_MAP[status] || 'pending';
            return '<span class="badge-status ' + badgeClass + '">' + label + '</span>';
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

        initForm() {
            const self = this;

            window.FormHelper.handleModalForm({
                formSelector: '#delivery-form',
                modalSelector: '#delivery-modal',
                submitSelector: '#delivery-form-submit',
                successMessage: 'Delivery saved successfully.',
                beforeSubmit($form) {
                    const isEdit = !!self.editId;
                    const url = isEdit
                        ? self.resolveRoute(self.routes.update, self.editId)
                        : self.routes.store;
                    $form.attr('action', url);
                    $form.attr('method', isEdit ? 'PUT' : 'POST');
                    return true;
                },
                onSuccess() {
                    window.DataTableHelper.reload(self.table, false);
                },
            });

            window.FormHelper.bindModalReset('#delivery-modal', '#delivery-form');

            $('#delivery-modal').on('shown.bs.modal', () => {
                window.FormHelper.initSelect2($('#delivery-modal'));
            });

            $('#delivery-modal').on('hidden.bs.modal', () => {
                self.editId = null;
                $('#delivery-modal-label').text('New Delivery');
            });
        },

        initAssignForm() {
            const self = this;

            $('#assign-rider-submit').on('click', function () {
                self.submitAssignRider($(this));
            });

            $('#assign-rider-modal').on('hidden.bs.modal', () => {
                self.assignDeliveryId = null;
                $('#assign-delivery-id').val('');
                $('#assign-tracking-label').text('—');
                $('#assign_notes').val('');
                $('#assign-no-riders').addClass('d-none');
                window.FormHelper.clearErrors($('#assign-rider-form'));
                self.resetRiderSelect();
            });
        },

        resetRiderSelect() {
            const $select = $('#assign_rider_id');

            if ($select.hasClass('select2-hidden-accessible')) {
                $select.select2('destroy');
            }

            $select.empty().append('<option value=""></option>');
            $select.prop('disabled', false);
        },

        loadAssignableRiders() {
            const self = this;
            const url = this.routes.assignableRiders;
            const $select = $('#assign_rider_id');
            const $empty = $('#assign-no-riders');

            if (!url) {
                return;
            }

            $select.prop('disabled', true);
            $empty.addClass('d-none');

            window.AjaxHelper.get(url, null, {
                showLoader: true,
                loaderMessage: 'Loading riders...',
                success(response) {
                    const riders = response.data || response || [];
                    self.populateRiderSelect($select, riders);
                    $empty.toggleClass('d-none', riders.length > 0);
                    $select.prop('disabled', riders.length === 0);
                },
                error() {
                    self.resetRiderSelect();
                    $empty.removeClass('d-none').text('Unable to load riders. Please refresh and try again.');
                },
            });
        },

        populateRiderSelect($select, riders) {
            if ($select.hasClass('select2-hidden-accessible')) {
                $select.select2('destroy');
            }

            $select.empty().append('<option value=""></option>');

            riders.forEach((rider) => {
                const name = rider.name || 'Rider';
                const status = rider.is_online ? 'Online' : 'Offline';
                const availability = rider.is_available === false ? ' · Busy' : '';

                $select.append(
                    $('<option></option>')
                        .val(rider.rider_id || rider.id)
                        .text(name + ' (' + status + ')' + availability)
                );
            });

            window.FormHelper.initSelect2($('#assign-rider-modal'), {
                dropdownParent: $('#assign-rider-modal'),
            });
        },

        bindIndexEvents() {
            const self = this;

            this.$module.on('click', '#btn-create-delivery', () => self.openCreateModal());
            this.$module.on('click', '#btn-filter', () => self.applyFilters());
            this.$module.on('click', '#btn-reset-filters', () => self.resetFilters());

            this.$module.on('input', '#filter-search', () => {
                clearTimeout(self.searchTimer);
                self.searchTimer = setTimeout(() => self.applyFilters(), 400);
            });

            this.$module.on('change', '#filter-status, #filter-shop, #filter-rider, #filter-date-from, #filter-date-to', () => {
                self.applyFilters();
            });

            this.$module.on('click', '.btn-edit', function () {
                self.openEditModal($(this).data('id'));
            });

            this.$module.on('click', '.btn-view', function () {
                self.openDetailModal($(this).data('id'));
            });

            this.$module.on('click', '.btn-assign', function () {
                self.openAssignModal($(this).data('id'), $(this).data('tracking'));
            });

            this.$module.on('click', '.btn-delete', function () {
                self.deleteRecord($(this).data('id'), $(this).data('tracking') || 'delivery');
            });

            this.$module.on('click', '.btn-status-option', function (e) {
                e.preventDefault();
                const id = $(this).data('id');
                const status = $(this).data('status');
                self.updateStatus(id, status);
            });

            this.$module.on('click', '.btn-rider-accept', function () {
                self.updateStatus($(this).data('id'), 'accepted', 'Accept this delivery assignment?');
            });

            this.$module.on('click', '.btn-rider-reject', function () {
                self.rejectAssignment($(this).data('id'), $(this).data('tracking'));
            });

            this.$module.on('click', '.btn-rider-status', function () {
                const status = $(this).data('status');
                const label = $(this).text().trim() || status.replace(/_/g, ' ');
                self.updateStatus($(this).data('id'), status, 'Mark delivery as "' + label + '"?');
            });
        },

        handleEditQueryParam() {
            const params = new URLSearchParams(window.location.search);
            const editId = params.get('edit');
            if (editId) {
                this.openEditModal(editId);
                window.history.replaceState({}, '', window.location.pathname);
            }
        },

        initShowPage() {
            const self = this;
            const $show = $('#delivery-show-module');
            const routes = $show.data('routes') || {};
            const deliveryId = $show.data('delivery-id');
            const isRider = !!$show.data('is-rider');

            this.assignDeliveryId = deliveryId;
            this.routes = $.extend({}, this.routes, routes);
            this.isRider = isRider;

            if (!isRider) {
                $('#show-status-form').on('submit', function (e) {
                    e.preventDefault();
                    const status = $('#show-status-select').val();
                    const notes = $('#show-status-notes').val();

                    window.AjaxHelper.post(
                        routes.status,
                        { status, notes },
                        {
                            loaderMessage: 'Updating status...',
                            success(response) {
                                window.NotificationHelper.success(
                                    response.message || 'Status updated successfully.'
                                );
                                window.location.reload();
                            },
                        }
                    );
                });

                $show.on('click', '.btn-assign', function () {
                    const id = $(this).data('id');
                    const tracking = $(this).data('tracking');
                    DeliveriesModule.openAssignModal(id, tracking);
                });

                $show.on('click', '.btn-edit-page', function () {
                    const id = $(this).data('id');
                    const indexUrl = routes.index || '/deliveries';
                    window.location.href = indexUrl + '?edit=' + id;
                });
            } else {
                $show.on('click', '.btn-rider-accept', function () {
                    self.updateStatus($(this).data('id'), 'accepted', 'Accept this delivery assignment?');
                });

                $show.on('click', '.btn-rider-reject', function () {
                    self.rejectAssignment($(this).data('id'), $(this).data('tracking'));
                });

                $show.on('click', '.btn-rider-status', function () {
                    const status = $(this).data('status');
                    const label = $(this).text().trim() || status.replace(/_/g, ' ');
                    self.updateStatus($(this).data('id'), status, 'Mark delivery as "' + label + '"?');
                });
            }
        },

        openCreateModal() {
            this.editId = null;
            $('#delivery-modal-label').text('New Delivery');
            $('#delivery-form').attr('action', this.routes.store);
            $('#delivery-form').attr('method', 'POST');
            $('#delivery-id').val('');
            window.FormHelper.reset($('#delivery-form'), true);

            const $shopField = $('#delivery_shop_id');
            const defaultShopId = $shopField.data('default-shop-id');
            const defaultPickup = $shopField.data('default-pickup-address');

            if (defaultShopId) {
                $shopField.val(defaultShopId);
            }

            $('#delivery_priority').val('normal');
            $('#payment_status').val('pending');
            $('#payment_method').val('cod');
            $('#cod_amount').val('0');
            $('#delivery_fee').val('');
            $('#fee-distance-hint').text('Calculated from distance');

            if (defaultPickup) {
                $('#pickup_address').val(defaultPickup);
            }

            $('#delivery_latitude').val('');
            $('#delivery_longitude').val('');

            bootstrap.Modal.getOrCreateInstance(document.getElementById('delivery-modal')).show();

            if (window.LocationHelper) {
                window.LocationHelper.applyDefaultDeliveryLocation();
            }

            this.bindFeeEstimate();
        },

        bindFeeEstimate() {
            const self = this;
            const $form = $('#delivery-form');
            if (!$form.length || $form.data('fee-bound')) {
                return;
            }
            $form.data('fee-bound', true);

            let timer = null;
            const run = function () {
                clearTimeout(timer);
                timer = setTimeout(function () {
                    self.estimateFee();
                }, 500);
            };

            $form.on('change blur', '#pickup_address, #delivery_address, #delivery_shop_id, #delivery_latitude, #delivery_longitude', run);
        },

        estimateFee() {
            const payload = {
                shop_id: $('#delivery_shop_id').val() || null,
                pickup_address: $('#pickup_address').val() || '',
                delivery_address: $('#delivery_address').val() || '',
                latitude: $('#delivery_latitude').val() || null,
                longitude: $('#delivery_longitude').val() || null,
            };

            if (!payload.pickup_address || !payload.delivery_address) {
                return;
            }

            window.AjaxHelper.post(this.routes.estimateFee || '/deliveries/estimate-fee', payload, {
                showLoader: false,
                success(response) {
                    const data = response.data || response;
                    if (data.delivery_fee != null) {
                        $('#delivery_fee').val(data.delivery_fee);
                    }
                    if (data.distance_km != null) {
                        $('#fee-distance-hint').text(
                            data.distance_km + ' km · fee Rs ' + data.delivery_fee
                        );
                    }
                },
            });
        },

        openEditModal(id) {
            const self = this;
            const url = this.resolveRoute(this.routes.edit, id);

            window.AjaxHelper.get(url, null, {
                showLoader: true,
                loaderMessage: 'Loading delivery...',
                success(response) {
                    const delivery = response.data || response;

                    self.editId = delivery.id;
                    $('#delivery-modal-label').text('Edit Delivery');
                    $('#delivery-form').attr('action', self.resolveRoute(self.routes.update, delivery.id));
                    $('#delivery-form').attr('method', 'PUT');
                    $('#delivery-id').val(delivery.id);
                    $('#delivery_shop_id').val(delivery.shop_id).trigger('change');
                    $('#delivery_priority').val(delivery.priority || 'normal');
                    $('#customer_name').val(delivery.customer_name || '');
                    $('#customer_phone').val(delivery.customer_phone || '');
                    $('#pickup_address').val(delivery.pickup_address || '');
                    $('#delivery_address').val(delivery.delivery_address || '');
                    $('#delivery_latitude').val(delivery.latitude || '');
                    $('#delivery_longitude').val(delivery.longitude || '');
                    $('#delivery_fee').val(delivery.delivery_fee || '0');
                    $('#cod_amount').val(delivery.cod_amount || '0');
                    $('#payment_method').val(delivery.payment_method || 'cod');
                    $('#payment_status').val(delivery.payment_status || 'pending');
                    $('#delivery_notes').val(delivery.notes || '');
                    if (delivery.distance_km) {
                        $('#fee-distance-hint').text(delivery.distance_km + ' km');
                    }
                    window.FormHelper.clearErrors($('#delivery-form'));
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('delivery-modal')).show();
                    self.bindFeeEstimate();
                },
            });
        },

        openDetailModal(id) {
            const self = this;
            const url = this.resolveRoute(this.routes.show, id);
            const $content = $('#delivery-detail-content');

            $content.html(
                '<div class="text-center py-5"><span class="spinner-border spinner-border-sm me-2"></span>Loading...</div>'
            );

            bootstrap.Modal.getOrCreateInstance(document.getElementById('delivery-detail-modal')).show();

            window.AjaxHelper.get(url, null, {
                showLoader: false,
                headers: { Accept: 'application/json' },
                success(response) {
                    const delivery = response.data || response;
                    $content.html(self.buildDetailHtml(delivery));
                },
                error() {
                    $content.html('<div class="alert alert-danger mb-0">Unable to load delivery details.</div>');
                },
            });
        },

        buildDetailHtml(delivery) {
            const timeline = (delivery.status_histories || delivery.statusHistories || [])
                .map((item) => {
                    const status = (item.status || '').replace(/_/g, ' ');
                    const date = item.created_at || '';
                    const notes = item.notes ? '<p class="text-muted small mb-0 mt-1">' + item.notes + '</p>' : '';
                    return (
                        '<div class="delivery-timeline-item">' +
                        '<div class="delivery-timeline-marker"><i class="fa-solid fa-circle"></i></div>' +
                        '<div class="delivery-timeline-content">' +
                        '<div class="d-flex justify-content-between">' +
                        '<span class="badge-status ' + (STATUS_BADGE_MAP[item.status] || 'pending') + '">' + status + '</span>' +
                        '<small class="text-muted">' + date + '</small>' +
                        '</div>' + notes +
                        '</div></div>'
                    );
                })
                .join('');

            return (
                '<div class="row g-3 mb-4">' +
                '<div class="col-md-6"><label class="text-muted small">Tracking</label><div><strong>' + (delivery.tracking_number || '—') + '</strong></div></div>' +
                '<div class="col-md-6"><label class="text-muted small">Status</label><div>' + this.renderStatusBadge(delivery.status) + '</div></div>' +
                '<div class="col-md-6"><label class="text-muted small">Shop</label><div>' + (delivery.shop_name || delivery.shop?.name || '—') + '</div></div>' +
                '<div class="col-md-6"><label class="text-muted small">Rider</label><div>' + (delivery.rider_name || delivery.rider?.user?.name || 'Unassigned') + '</div></div>' +
                '<div class="col-md-6"><label class="text-muted small">Customer</label><div>' + (delivery.customer_name || '—') + '<br><small class="text-muted">' + (delivery.customer_phone || '') + '</small></div></div>' +
                '<div class="col-md-6"><label class="text-muted small">Fee</label><div>$' + (parseFloat(delivery.delivery_fee) || 0).toFixed(2) + '</div></div>' +
                '<div class="col-12"><label class="text-muted small">Pickup</label><div>' + (delivery.pickup_address || '—') + '</div></div>' +
                '<div class="col-12"><label class="text-muted small">Delivery</label><div>' + (delivery.delivery_address || '—') + '</div></div>' +
                '</div>' +
                '<h6 class="mb-3"><i class="fa-solid fa-timeline me-1"></i> Status Timeline</h6>' +
                '<div class="delivery-timeline">' + (timeline || '<p class="text-muted">No history yet.</p>') + '</div>' +
                '<div class="mt-3 text-end">' +
                '<a href="' + this.resolveRoute(this.routes.show, delivery.id) + '" class="btn btn-sm btn-outline-primary">View Full Page</a>' +
                '</div>'
            );
        },

        openAssignModal(id, tracking) {
            this.assignDeliveryId = id;
            $('#assign-delivery-id').val(id);
            $('#assign-tracking-label').text(tracking || '#' + id);
            bootstrap.Modal.getOrCreateInstance(document.getElementById('assign-rider-modal')).show();
            this.loadAssignableRiders();
        },

        submitAssignRider($btn) {
            const self = this;
            const id = this.assignDeliveryId;
            if (!id) {
                return;
            }

            const url = this.resolveRoute(this.routes.assign, id);
            const riderId = $('#assign_rider_id').val();

            if (!riderId) {
                window.FormHelper.showErrors($('#assign-rider-form'), {
                    rider_id: ['Please select a rider.'],
                });
                return;
            }

            window.FormHelper.setSubmitLoading($btn, true);

            window.AjaxHelper.post(
                url,
                {
                    rider_id: riderId,
                    notes: $('#assign_notes').val(),
                },
                {
                    showLoader: false,
                    success(response) {
                        window.NotificationHelper.success(
                            response.message || 'Rider assigned successfully.'
                        );
                        bootstrap.Modal.getOrCreateInstance(document.getElementById('assign-rider-modal')).hide();
                        if (self.table) {
                            window.DataTableHelper.reload(self.table, false);
                        } else if ($('#delivery-show-module').length) {
                            window.location.reload();
                        }
                    },
                    error(xhr, textStatus, errorThrown, handled) {
                        if (handled && handled.errors) {
                            window.FormHelper.showErrors($('#assign-rider-form'), handled.errors);
                        }
                    },
                    complete() {
                        window.FormHelper.setSubmitLoading($btn, false);
                    },
                }
            );
        },

        maybePrepareRiderLiveTracking(status) {
            if (!this.liveDeliveryStatuses.includes(status)) {
                return;
            }

            if (window.RiderLocationSender && typeof window.RiderLocationSender.prepareForLiveDelivery === 'function') {
                window.RiderLocationSender.prepareForLiveDelivery();
            }
        },

        updateStatus(id, status, confirmText) {
            const self = this;
            const url = this.resolveRoute(this.routes.status, id);
            const label = status.replace(/_/g, ' ');

            window.NotificationHelper.confirm({
                title: 'Update status?',
                text: confirmText || ('Set delivery status to "' + label + '"?'),
                icon: 'question',
                confirmButtonText: 'Yes, update',
            }).then((result) => {
                if (!result.isConfirmed) {
                    return;
                }

                window.AjaxHelper.post(
                    url,
                    { status },
                    {
                        loaderMessage: 'Updating status...',
                        success(response) {
                            self.maybePrepareRiderLiveTracking(status);
                            window.NotificationHelper.success(
                                response.message || 'Status updated successfully.'
                            );
                            if (self.table) {
                                window.DataTableHelper.reload(self.table, false);
                            } else if ($('#delivery-show-module').length) {
                                window.location.reload();
                            }
                        },
                    }
                );
            });
        },

        rejectAssignment(id, tracking) {
            const self = this;
            const url = this.resolveRoute(this.routes.rejectAssignment, id);

            window.NotificationHelper.confirm({
                title: 'Reject assignment?',
                text: 'Reject delivery ' + (tracking || '') + '? Shop will need to assign another rider.',
                icon: 'warning',
                confirmButtonText: 'Yes, reject',
                confirmButtonColor: '#dc3545',
            }).then((result) => {
                if (!result.isConfirmed) {
                    return;
                }

                window.AjaxHelper.post(
                    url,
                    {},
                    {
                        loaderMessage: 'Rejecting assignment...',
                        success(response) {
                            window.NotificationHelper.success(
                                response.message || 'Assignment rejected.'
                            );
                            if (self.table) {
                                window.DataTableHelper.reload(self.table, false);
                            }
                        },
                    }
                );
            });
        },

        deleteRecord(id, name) {
            const self = this;
            const url = this.resolveRoute(this.routes.destroy, id);

            window.NotificationHelper.confirmDelete(name).then((result) => {
                if (!result.isConfirmed) {
                    return;
                }

                window.AjaxHelper.delete(url, null, {
                    loaderMessage: 'Deleting delivery...',
                    success(response) {
                        window.NotificationHelper.success(
                            response.message || 'Delivery deleted successfully.'
                        );
                        window.DataTableHelper.reload(self.table, false);
                    },
                });
            });
        },

        applyFilters() {
            window.DataTableHelper.reload(this.table, true);
        },

        resetFilters() {
            $('#filter-search').val('');
            $('#filter-status').val('');
            $('#filter-shop').val(null).trigger('change');
            $('#filter-rider').val(null).trigger('change');
            $('#filter-date-from').val('');
            $('#filter-date-to').val('');
            this.applyFilters();
        },

        bindLiveUpdates() {
            const self = this;

            if (!window.RealtimeHelper) {
                return;
            }

            window.RealtimeHelper.onDeliveryUpdated(function () {
                if (self.table) {
                    window.DataTableHelper.reload(self.table, false);
                }
            });
        },

        resolveRoute(template, id) {
            return (template || '').replace('__ID__', id);
        },
    };

    window.DeliveriesModule = DeliveriesModule;

    $(document).ready(function () {
        DeliveriesModule.init();
    });
})(window, window.jQuery);
