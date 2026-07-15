/**
 * Menus module — DataTable and modal CRUD.
 */
(function (window, $) {
    'use strict';

    if (!$) {
        return;
    }

    const MenusModule = {
        table: null,
        $module: null,
        routes: {},
        editId: null,
        searchTimer: null,

        init() {
            this.$module = $('#menus-module');
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

            this.table = window.DataTableHelper.initDataTable('#menus-table', {
                url: this.routes.datatable,
                order: [[4, 'asc']],
                exportButtons: self.getExportButtons(),
                ajaxData(d) {
                    d.search_filter = $('#filter-search').val();
                    d.is_active = $('#filter-status').val();
                    return d;
                },
                columns: [
                    {
                        data: 'title',
                        name: 'title',
                        render(data, type, row) {
                            const icon = row.icon ? '<i class="' + row.icon + ' me-2"></i>' : '';
                            return icon + (data || '—');
                        },
                    },
                    { data: 'route_name', name: 'route_name', defaultContent: '—' },
                    { data: 'permission', name: 'permission', defaultContent: '—' },
                    { data: 'parent_title', name: 'parent.title', defaultContent: '—' },
                    { data: 'sort_order', name: 'sort_order' },
                    {
                        data: 'is_active',
                        name: 'is_active',
                        searchable: false,
                        render(data) {
                            return data
                                ? '<span class="badge bg-success">Active</span>'
                                : '<span class="badge bg-secondary">Inactive</span>';
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

        getExportButtons() {
            return ['copy', 'excel', 'csv', 'pdf', 'print', 'colvis'];
        },

        initForm() {
            const self = this;

            window.FormHelper.handleModalForm({
                formSelector: '#menu-form',
                modalSelector: '#menu-modal',
                submitSelector: '#menu-form-submit',
                successMessage: 'Menu saved successfully.',
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

            window.FormHelper.bindModalReset('#menu-modal', '#menu-form');

            $('#menu-modal').on('shown.bs.modal', () => {
                window.FormHelper.initSelect2($('#menu-modal'));
            });

            $('#menu-modal').on('hidden.bs.modal', () => {
                self.resetModalState();
            });
        },

        bindEvents() {
            const self = this;

            this.$module.on('click', '#btn-create-menu', () => self.openCreateModal());
            this.$module.on('click', '#btn-filter', () => self.applyFilters());
            this.$module.on('click', '#btn-reset-filters', () => self.resetFilters());

            this.$module.on('input', '#filter-search', () => {
                clearTimeout(self.searchTimer);
                self.searchTimer = setTimeout(() => self.applyFilters(), 400);
            });

            this.$module.on('change', '#filter-status', () => self.applyFilters());

            this.$module.on('click', '.btn-edit', function () {
                self.openEditModal($(this).data('id'));
            });

            this.$module.on('click', '.btn-delete', function () {
                self.deleteRecord($(this).data('id'), $(this).data('name') || 'menu');
            });
        },

        openCreateModal() {
            this.editId = null;
            $('#menu-modal-label').text('Add Menu');
            $('#menu-form').attr('action', this.routes.store);
            $('#menu-id').val('');
            window.FormHelper.reset($('#menu-form'), true);
            $('#menu_sort_order').val(0);
            $('#menu_is_active').prop('checked', true);
            bootstrap.Modal.getOrCreateInstance(document.getElementById('menu-modal')).show();
        },

        openEditModal(id) {
            const self = this;
            const url = this.resolveRoute(this.routes.edit, id);

            window.AjaxHelper.get(url, null, {
                showLoader: true,
                loaderMessage: 'Loading menu...',
                success(response) {
                    const menu = response.data || response;

                    self.editId = menu.id;
                    $('#menu-modal-label').text('Edit Menu');
                    $('#menu-form').attr('action', self.resolveRoute(self.routes.update, menu.id));
                    $('#menu-id').val(menu.id);
                    $('#menu_title').val(menu.title || '');
                    $('#menu_icon').val(menu.icon || '');
                    $('#menu_parent_id').val(menu.parent_id || '').trigger('change');
                    $('#menu_route_name').val(menu.route_name || '').trigger('change');
                    $('#menu_route_pattern').val(menu.route_pattern || '');
                    $('#menu_url').val(menu.url || '');
                    $('#menu_permission').val(menu.permission || '').trigger('change');
                    $('#menu_sort_order').val(menu.sort_order ?? 0);
                    $('#menu_is_active').prop('checked', !!menu.is_active);
                    window.FormHelper.clearErrors($('#menu-form'));
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('menu-modal')).show();
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
                    loaderMessage: 'Deleting menu...',
                    success(response) {
                        window.NotificationHelper.success(
                            response.message || 'Menu deleted successfully.'
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
            this.applyFilters();
        },

        resetModalState() {
            this.editId = null;
            $('#menu-modal-label').text('Add Menu');
        },

        resolveRoute(template, id) {
            return (template || '').replace('__ID__', id);
        },
    };

    window.MenusModule = MenusModule;

    $(document).ready(function () {
        MenusModule.init();
    });
})(window, window.jQuery);
