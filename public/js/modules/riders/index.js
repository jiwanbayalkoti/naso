/**
 * Riders module — DataTable, modal CRUD, online toggle, filters.
 */
(function (window, $) {
    'use strict';

    if (!$) {
        return;
    }

    const RidersModule = {
        table: null,
        $module: null,
        routes: {},
        editId: null,
        searchTimer: null,

        init() {
            this.$module = $('#riders-module');
            if (!this.$module.length) {
                return;
            }

            this.routes = this.$module.data('routes') || {};
            this.initDataTable();
            this.initForm();
            this.bindEvents();
        },

        initDataTable() {
            const self = this;

            this.table = window.DataTableHelper.initDataTable('#riders-table', {
                url: this.routes.datatable,
                order: [[8, 'desc']],
                exportButtons: self.getExportButtons(),
                ajaxData(d) {
                    d.search_filter = $('#filter-search').val();
                    d.is_online = $('#filter-online').val();
                    d.is_available = $('#filter-available').val();
                    return d;
                },
                columns: [
                    { data: 'name', name: 'user.name' },
                    { data: 'email', name: 'user.email', defaultContent: '—' },
                    { data: 'phone', name: 'user.phone', defaultContent: '—' },
                    {
                        data: 'vehicle',
                        name: 'vehicle_type',
                        orderable: false,
                        defaultContent: '—',
                        render(data, type, row) {
                            const typeLabel = row.vehicle_type
                                ? row.vehicle_type.charAt(0).toUpperCase() + row.vehicle_type.slice(1)
                                : '';
                            const number = row.vehicle_number || '';
                            if (typeLabel && number) {
                                return typeLabel + ' · ' + number;
                            }
                            return typeLabel || number || '—';
                        },
                    },
                    {
                        data: 'is_online',
                        name: 'is_online',
                        searchable: false,
                        render(data) {
                            return data
                                ? '<span class="badge bg-success"><i class="fa-solid fa-circle me-1" style="font-size:0.5rem"></i>Online</span>'
                                : '<span class="badge bg-secondary">Offline</span>';
                        },
                    },
                    {
                        data: 'is_available',
                        name: 'is_available',
                        searchable: false,
                        render(data) {
                            return data
                                ? '<span class="badge bg-info">Available</span>'
                                : '<span class="badge bg-warning text-dark">Busy</span>';
                        },
                    },
                    {
                        data: 'rating',
                        name: 'rating',
                        searchable: false,
                        render(data) {
                            const rating = parseFloat(data) || 0;
                            return (
                                '<span class="text-warning"><i class="fa-solid fa-star"></i></span> ' +
                                rating.toFixed(2)
                            );
                        },
                    },
                    {
                        data: 'total_deliveries',
                        name: 'total_deliveries',
                        searchable: false,
                        defaultContent: '0',
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
                formSelector: '#rider-form',
                modalSelector: '#rider-modal',
                submitSelector: '#rider-form-submit',
                successMessage: 'Rider saved successfully.',
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

            window.FormHelper.bindModalReset('#rider-modal', '#rider-form');

            $('#rider-modal').on('hidden.bs.modal', () => {
                self.resetModalState();
            });
        },

        bindEvents() {
            const self = this;

            this.$module.on('click', '#btn-create-rider', () => self.openCreateModal());
            this.$module.on('click', '#btn-filter', () => self.applyFilters());
            this.$module.on('click', '#btn-reset-filters', () => self.resetFilters());

            this.$module.on('input', '#filter-search', () => {
                clearTimeout(self.searchTimer);
                self.searchTimer = setTimeout(() => self.applyFilters(), 400);
            });

            this.$module.on('change', '#filter-online, #filter-available', () => self.applyFilters());

            this.$module.on('click', '.btn-edit', function () {
                self.openEditModal($(this).data('id'));
            });

            this.$module.on('click', '.btn-delete', function () {
                self.deleteRecord($(this).data('id'), $(this).data('name') || 'rider');
            });

            this.$module.on('click', '.btn-toggle-online', function () {
                const id = $(this).data('id');
                const isOnline = $(this).data('online') === 1 || $(this).data('online') === '1';
                self.toggleOnline(id, isOnline);
            });
        },

        openCreateModal() {
            this.editId = null;
            $('#rider-modal-label').text('Add Rider');
            $('#rider-owner-fields').show();
            $('#rider-form').attr('action', this.routes.store);
            $('#rider-form').attr('method', 'POST');
            $('#rider-id').val('');
            window.FormHelper.reset($('#rider-form'), true);
            $('#rider_is_available').prop('checked', true);
            bootstrap.Modal.getOrCreateInstance(document.getElementById('rider-modal')).show();
        },

        openEditModal(id) {
            const self = this;
            const url = this.resolveRoute(this.routes.edit, id);

            window.AjaxHelper.get(url, null, {
                showLoader: true,
                loaderMessage: 'Loading rider...',
                success(response) {
                    const rider = response.data || response;
                    const user = rider.user || {};

                    self.editId = rider.id;
                    $('#rider-modal-label').text('Edit Rider');
                    $('#rider-owner-fields').hide();
                    $('#rider-form').attr('action', self.resolveRoute(self.routes.update, rider.id));
                    $('#rider-form').attr('method', 'PUT');
                    $('#rider-id').val(rider.id);
                    $('#rider_name').val(user.name || '');
                    $('#rider_email').val(user.email || '');
                    $('#rider_phone').val(user.phone || '');
                    $('#vehicle_type').val(rider.vehicle_type || '');
                    $('#vehicle_number').val(rider.vehicle_number || '');
                    $('#license_number').val(rider.license_number || '');
                    $('#rider_bank_name').val(rider.bank_name || '');
                    $('#rider_bank_account_name').val(rider.bank_account_name || '');
                    $('#rider_bank_account_number').val(rider.bank_account_number || '');
                    $('#rider_is_available').prop('checked', rider.is_available !== false);
                    window.FormHelper.clearErrors($('#rider-form'));
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('rider-modal')).show();
                },
            });
        },

        toggleOnline(id, isOnline) {
            const self = this;
            const url = this.resolveRoute(this.routes.toggleOnline, id);
            const action = isOnline ? 'offline' : 'online';

            window.NotificationHelper.confirm({
                title: 'Set rider ' + action + '?',
                text: 'This will update the rider\'s online status.',
                icon: 'question',
                confirmButtonText: 'Yes, set ' + action,
            }).then((result) => {
                if (!result.isConfirmed) {
                    return;
                }

                window.AjaxHelper.post(
                    url,
                    {},
                    {
                        loaderMessage: 'Updating status...',
                        success(response) {
                            window.NotificationHelper.success(
                                response.message || 'Rider status updated.'
                            );
                            const rider = response.data || {};
                            if (typeof rider.is_online !== 'undefined') {
                                $(document).trigger('rider:online-changed', [!!rider.is_online]);
                            }
                            window.DataTableHelper.reload(self.table, false);
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
                    loaderMessage: 'Deleting rider...',
                    success(response) {
                        window.NotificationHelper.success(
                            response.message || 'Rider deleted successfully.'
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
            $('#filter-online').val('');
            $('#filter-available').val('');
            this.applyFilters();
        },

        resetModalState() {
            this.editId = null;
            $('#rider-modal-label').text('Add Rider');
            $('#rider-owner-fields').show();
        },

        resolveRoute(template, id) {
            return (template || '').replace('__ID__', id);
        },
    };

    window.RidersModule = RidersModule;

    $(document).ready(function () {
        RidersModule.init();
    });
})(window, window.jQuery);
