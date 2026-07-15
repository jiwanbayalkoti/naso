/**
 * DataTable factory with server-side processing, export buttons, and responsive support.
 */
(function (window, $) {
    'use strict';

    if (!$ || !$.fn.DataTable) {
        return;
    }

    const DataTableHelper = {
        defaultButtons: ['copy', 'excel', 'pdf', 'print', 'colvis'],

        /**
         * Initialize a DataTable with common defaults.
         */
        initDataTable(selector, options) {
            const $table = $(selector);

            if (!$table.length) {
                return null;
            }

            if ($.fn.DataTable.isDataTable($table)) {
                $table.DataTable().destroy();
                $table.empty();
            }

            const defaults = {
                processing: true,
                serverSide: true,
                responsive: true,
                autoWidth: false,
                pageLength: 25,
                lengthMenu: [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, 'All'],
                ],
                dom:
                    "<'row align-items-center mb-3'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
                    "<'row'<'col-12'tr>>" +
                    "<'row align-items-center mt-3'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>" +
                    (options.buttons !== false ? 'B' : ''),
                buttons: this.buildButtons(options.exportButtons || this.defaultButtons),
                language: {
                    processing:
                        '<div class="datatable-processing"><span class="spinner-border spinner-border-sm me-2"></span>Loading...</div>',
                    emptyTable: 'No records found',
                    zeroRecords: 'No matching records found',
                    search: 'Search:',
                    lengthMenu: 'Show _MENU_ entries',
                    info: 'Showing _START_ to _END_ of _TOTAL_ entries',
                    infoEmpty: 'Showing 0 to 0 of 0 entries',
                    infoFiltered: '(filtered from _MAX_ total entries)',
                    paginate: {
                        first: 'First',
                        last: 'Last',
                        next: 'Next',
                        previous: 'Previous',
                    },
                },
                ajax: {
                    url: options.url || $table.data('url'),
                    type: options.method || 'GET',
                    headers: {
                        'X-CSRF-TOKEN': window.AjaxHelper
                            ? window.AjaxHelper.getCsrfToken()
                            : $('meta[name="csrf-token"]').attr('content'),
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'application/json',
                    },
                    data: function (d) {
                        if (typeof options.ajaxData === 'function') {
                            return options.ajaxData(d);
                        }
                        return d;
                    },
                    error: function (xhr) {
                        if (window.AjaxHelper) {
                            window.AjaxHelper.handleError(xhr);
                        }
                    },
                },
                columns: options.columns || [],
                order: options.order || [[0, 'desc']],
                drawCallback: function () {
                    if (typeof options.onDraw === 'function') {
                        options.onDraw(this.api());
                    }
                },
                initComplete: function () {
                    if (typeof options.onInit === 'function') {
                        options.onInit(this.api());
                    }
                },
            };

            const settings = $.extend(true, {}, defaults, options);

            if (options.serverSide === false) {
                delete settings.ajax;
            }

            if (options.buttons === false) {
                delete settings.buttons;
            }

            return $table.DataTable(settings);
        },

        /**
         * Build export button configuration.
         */
        buildButtons(buttons) {
            const buttonMap = {
                copy: {
                    extend: 'copy',
                    className: 'btn btn-sm btn-outline-secondary',
                    text: '<i class="fa-solid fa-copy me-1"></i> Copy',
                },
                excel: {
                    extend: 'excel',
                    className: 'btn btn-sm btn-outline-success',
                    text: '<i class="fa-solid fa-file-excel me-1"></i> Excel',
                },
                pdf: {
                    extend: 'pdf',
                    className: 'btn btn-sm btn-outline-danger',
                    text: '<i class="fa-solid fa-file-pdf me-1"></i> PDF',
                },
                print: {
                    extend: 'print',
                    className: 'btn btn-sm btn-outline-primary',
                    text: '<i class="fa-solid fa-print me-1"></i> Print',
                },
                colvis: {
                    extend: 'colvis',
                    className: 'btn btn-sm btn-outline-dark',
                    text: '<i class="fa-solid fa-table-columns me-1"></i> Columns',
                },
            };

            return (buttons || []).map((btn) => buttonMap[btn] || btn);
        },

        /**
         * Reload a DataTable instance.
         */
        reload(table, resetPaging) {
            if (table && typeof table.ajax === 'object') {
                table.ajax.reload(null, resetPaging !== false);
            }
        },

        /**
         * Destroy a DataTable instance safely.
         */
        destroy(selector) {
            const $table = $(selector);
            if ($table.length && $.fn.DataTable.isDataTable($table)) {
                $table.DataTable().clear().destroy();
            }
        },
    };

    window.DataTableHelper = DataTableHelper;
})(window, window.jQuery);
