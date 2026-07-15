/**
 * Users module — DataTable, modal CRUD, role management.
 */
(function (window, $) {
    'use strict';

    if (!$) {
        return;
    }

    const UsersModule = {
        table: null,
        $module: null,
        routes: {},
        editId: null,
        searchTimer: null,

        init() {
            this.$module = $('#users-module');
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

            this.table = window.DataTableHelper.initDataTable('#users-table', {
                url: this.routes.datatable,
                order: [[6, 'desc']],
                exportButtons: self.getExportButtons(),
                ajaxData(d) {
                    d.search_filter = $('#filter-search').val();
                    d.role = $('#filter-role').val();
                    d.status = $('#filter-status').val();
                    return d;
                },
                columns: [
                    { data: 'name', name: 'name' },
                    { data: 'email', name: 'email' },
                    { data: 'phone', name: 'phone', defaultContent: '—' },
                    {
                        data: 'role',
                        name: 'roles.name',
                        render(data) {
                            if (!data) {
                                return '<span class="badge bg-secondary">—</span>';
                            }
                            const colors = { admin: 'danger', manager: 'primary', shop: 'info', rider: 'success' };
                            const color = colors[data.toLowerCase()] || 'secondary';
                            return '<span class="badge bg-' + color + '">' + data.charAt(0).toUpperCase() + data.slice(1) + '</span>';
                        },
                    },
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
                    { data: 'last_login_at', name: 'last_login_at', defaultContent: '—' },
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
                formSelector: '#user-form',
                modalSelector: '#user-modal',
                submitSelector: '#user-form-submit',
                successMessage: 'User saved successfully.',
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

            window.FormHelper.bindModalReset('#user-modal', '#user-form');

            $('#user-modal').on('shown.bs.modal', () => {
                window.FormHelper.initSelect2($('#user-modal'));
            });

            $('#user-modal').on('hidden.bs.modal', () => {
                self.resetModalState();
            });
        },

        bindEvents() {
            const self = this;

            this.$module.on('click', '#btn-create-user', () => self.openCreateModal());
            this.$module.on('click', '#btn-filter', () => self.applyFilters());
            this.$module.on('click', '#btn-reset-filters', () => self.resetFilters());

            this.$module.on('input', '#filter-search', () => {
                clearTimeout(self.searchTimer);
                self.searchTimer = setTimeout(() => self.applyFilters(), 400);
            });

            this.$module.on('change', '#filter-role, #filter-status', () => self.applyFilters());

            this.$module.on('click', '.btn-edit', function () {
                self.openEditModal($(this).data('id'));
            });

            this.$module.on('click', '.btn-delete', function () {
                self.deleteRecord($(this).data('id'), $(this).data('name') || 'user');
            });
        },

        openCreateModal() {
            this.editId = null;
            $('#user-modal-label').text('Add User');
            $('#user-password-fields').show();
            $('.create-required').show();
            $('#user-form').attr('action', this.routes.store);
            $('#user-id').val('');
            window.FormHelper.reset($('#user-form'), true);
            $('#user_is_active').prop('checked', true);
            bootstrap.Modal.getOrCreateInstance(document.getElementById('user-modal')).show();
        },

        openEditModal(id) {
            const self = this;
            const url = this.resolveRoute(this.routes.edit, id);

            window.AjaxHelper.get(url, null, {
                showLoader: true,
                loaderMessage: 'Loading user...',
                success(response) {
                    const user = response.data || response;
                    const role = user.role || (user.roles && user.roles[0] ? user.roles[0].name : '');

                    self.editId = user.id;
                    $('#user-modal-label').text('Edit User');
                    $('#user-password-fields').show();
                    $('.create-required').hide();
                    $('#user-form').attr('action', self.resolveRoute(self.routes.update, user.id));
                    $('#user-id').val(user.id);
                    $('#user_name').val(user.name || '');
                    $('#user_email').val(user.email || '');
                    $('#user_phone').val(user.phone || '');
                    $('#user_role').val(role).trigger('change');
                    $('#user_password').val('');
                    $('#user_password_confirmation').val('');
                    $('#user_is_active').prop('checked', !!user.is_active);
                    window.FormHelper.clearErrors($('#user-form'));
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('user-modal')).show();
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
                    loaderMessage: 'Deleting user...',
                    success(response) {
                        window.NotificationHelper.success(
                            response.message || 'User deleted successfully.'
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
            $('#filter-role').val('');
            $('#filter-status').val('');
            this.applyFilters();
        },

        resetModalState() {
            this.editId = null;
            $('#user-modal-label').text('Add User');
            $('.create-required').show();
        },

        resolveRoute(template, id) {
            return (template || '').replace('__ID__', id);
        },
    };

    window.UsersModule = UsersModule;

    $(document).ready(function () {
        UsersModule.init();
    });
})(window, window.jQuery);
