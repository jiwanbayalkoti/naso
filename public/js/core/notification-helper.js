/**
 * Notification helpers wrapping Toastr and SweetAlert2.
 */
(function (window) {
    'use strict';

    const NotificationHelper = {
        /**
         * Configure Toastr defaults.
         */
        initToastr() {
            if (!window.toastr) {
                return;
            }

            toastr.options = {
                closeButton: true,
                debug: false,
                newestOnTop: true,
                progressBar: true,
                positionClass: 'toast-top-right',
                preventDuplicates: true,
                onclick: null,
                showDuration: 300,
                hideDuration: 1000,
                timeOut: 4000,
                extendedTimeOut: 1000,
                showEasing: 'swing',
                hideEasing: 'linear',
                showMethod: 'fadeIn',
                hideMethod: 'fadeOut',
            };
        },

        success(message, title) {
            if (window.toastr) {
                toastr.success(message, title || 'Success');
            }
        },

        error(message, title) {
            if (window.toastr) {
                toastr.error(message, title || 'Error');
            }
        },

        warning(message, title) {
            if (window.toastr) {
                toastr.warning(message, title || 'Warning');
            }
        },

        info(message, title) {
            if (window.toastr) {
                toastr.info(message, title || 'Info');
            }
        },

        /**
         * Show a SweetAlert2 confirmation dialog.
         */
        confirm(options) {
            const defaults = {
                title: 'Are you sure?',
                text: 'This action cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#2563eb',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, proceed',
                cancelButtonText: 'Cancel',
                reverseButtons: true,
            };

            if (!window.Swal) {
                return Promise.resolve(window.confirm(options.text || defaults.text));
            }

            return Swal.fire($.extend({}, defaults, options));
        },

        /**
         * Show delete confirmation dialog.
         */
        confirmDelete(itemName) {
            return this.confirm({
                title: 'Delete ' + (itemName || 'item') + '?',
                text: 'This record will be permanently removed.',
                icon: 'warning',
                confirmButtonText: 'Yes, delete it',
                confirmButtonColor: '#dc2626',
            });
        },

        /**
         * Show success alert via SweetAlert2.
         */
        alertSuccess(title, text) {
            if (!window.Swal) {
                window.alert(text || title);
                return Promise.resolve();
            }

            return Swal.fire({
                icon: 'success',
                title: title || 'Success',
                text: text || '',
                confirmButtonColor: '#2563eb',
            });
        },

        /**
         * Show error alert via SweetAlert2.
         */
        alertError(title, text) {
            if (!window.Swal) {
                window.alert(text || title);
                return Promise.resolve();
            }

            return Swal.fire({
                icon: 'error',
                title: title || 'Error',
                text: text || 'Something went wrong.',
                confirmButtonColor: '#2563eb',
            });
        },

        /**
         * Show loading state in SweetAlert2.
         */
        showLoading(title) {
            if (!window.Swal) {
                return;
            }

            Swal.fire({
                title: title || 'Processing...',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    Swal.showLoading();
                },
            });
        },

        close() {
            if (window.Swal) {
                Swal.close();
            }
        },
    };

    window.NotificationHelper = NotificationHelper;

    if (window.jQuery) {
        window.jQuery(document).ready(function () {
            NotificationHelper.initToastr();
        });
    } else {
        NotificationHelper.initToastr();
    }
})(window);
