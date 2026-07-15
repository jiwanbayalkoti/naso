/**
 * Shops module — DataTable, modal CRUD, filters.
 */
(function (window, $) {
    'use strict';

    if (!$) {
        return;
    }

    const ShopsModule = {
        table: null,
        $module: null,
        routes: {},
        editId: null,
        searchTimer: null,

        init() {
            this.$module = $('#shops-module');
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

            this.table = window.DataTableHelper.initDataTable('#shops-table', {
                url: this.routes.datatable,
                order: [[7, 'desc']],
                exportButtons: self.getExportButtons(),
                ajaxData(d) {
                    d.search_filter = $('#filter-search').val();
                    d.status = $('#filter-status').val();
                    d.city = $('#filter-city').val();
                    return d;
                },
                columns: [
                    { data: 'name', name: 'name' },
                    { data: 'email', name: 'email', defaultContent: '—' },
                    { data: 'phone', name: 'phone', defaultContent: '—' },
                    { data: 'city', name: 'city', defaultContent: '—' },
                    { data: 'owner_name', name: 'user.name', defaultContent: '—' },
                    {
                        data: 'is_active',
                        name: 'is_active',
                        orderable: true,
                        searchable: false,
                        render(data) {
                            return data
                                ? '<span class="badge bg-success">Active</span>'
                                : '<span class="badge bg-secondary">Inactive</span>';
                        },
                    },
                    {
                        data: 'deliveries_count',
                        name: 'deliveries_count',
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
                formSelector: '#shop-form',
                modalSelector: '#shop-modal',
                submitSelector: '#shop-form-submit',
                successMessage: 'Shop saved successfully.',
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

            window.FormHelper.bindModalReset('#shop-modal', '#shop-form');

            $('#shop-modal').on('hidden.bs.modal', () => {
                self.resetModalState();
            });
        },

        bindEvents() {
            const self = this;

            this.$module.on('click', '#btn-create-shop', () => self.openCreateModal());
            this.$module.on('click', '#btn-filter', () => self.applyFilters());
            this.$module.on('click', '#btn-reset-filters', () => self.resetFilters());

            this.$module.on('input', '#filter-search', () => {
                clearTimeout(self.searchTimer);
                self.searchTimer = setTimeout(() => self.applyFilters(), 400);
            });

            this.$module.on('change', '#filter-status, #filter-city', () => self.applyFilters());

            this.$module.on('click', '.btn-edit', function () {
                const id = $(this).data('id');
                self.openEditModal(id);
            });

            this.$module.on('click', '.btn-delete', function () {
                const id = $(this).data('id');
                const name = $(this).data('name') || 'shop';
                self.deleteRecord(id, name);
            });
        },

        openCreateModal() {
            this.editId = null;
            $('#shop-modal-label').text('Add Shop');
            $('#shop-owner-fields').show();
            $('#shop-form').attr('action', this.routes.store);
            $('#shop-id').val('');
            window.FormHelper.reset($('#shop-form'), true);
            $('#shop_is_active').prop('checked', true);
            $('#shop_latitude').val('');
            $('#shop_longitude').val('');
            bootstrap.Modal.getOrCreateInstance(document.getElementById('shop-modal')).show();

            if (window.LocationHelper) {
                window.LocationHelper.applyDefaultLocation('shop');
            }
        },

        openEditModal(id) {
            const self = this;
            const url = this.resolveRoute(this.routes.edit, id);

            window.AjaxHelper.get(url, null, {
                showLoader: true,
                loaderMessage: 'Loading shop...',
                success(response) {
                    const shop = response.data || response;
                    self.editId = shop.id;
                    $('#shop-modal-label').text('Edit Shop');
                    $('#shop-owner-fields').hide();
                    $('#shop-form').attr('action', self.resolveRoute(self.routes.update, shop.id));
                    $('#shop-id').val(shop.id);
                    $('#shop_name').val(shop.name || '');
                    $('#shop_email').val(shop.email || '');
                    $('#shop_phone').val(shop.phone || '');
                    $('#shop_city').val(shop.city || '');
                    $('#shop_address').val(shop.address || '');
                    $('#shop_latitude').val(shop.latitude || '');
                    $('#shop_longitude').val(shop.longitude || '');
                    $('#shop_description').val(shop.description || '');
                    $('#shop_is_active').prop('checked', !!shop.is_active);
                    window.FormHelper.clearErrors($('#shop-form'));
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('shop-modal')).show();
                },
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
                    loaderMessage: 'Deleting shop...',
                    success(response) {
                        window.NotificationHelper.success(
                            response.message || 'Shop deleted successfully.'
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
            $('#filter-city').val('');
            this.applyFilters();
        },

        resetModalState() {
            this.editId = null;
            $('#shop-modal-label').text('Add Shop');
            $('#shop-owner-fields').show();
        },

        resolveRoute(template, id) {
            return (template || '').replace('__ID__', id);
        },
    };

    window.ShopsModule = ShopsModule;

    $(document).ready(function () {
        ShopsModule.init();
    });
})(window, window.jQuery);
