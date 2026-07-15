/**
 * Centralized AJAX helper with CSRF, loading states, error handling,
 * and duplicate submit prevention.
 */
(function (window, $) {
    'use strict';

    if (!$) {
        return;
    }

    const AjaxHelper = {
        pendingRequests: new Set(),
        activeSubmits: new Set(),

        /**
         * Get CSRF token from meta tag.
         */
        getCsrfToken() {
            return $('meta[name="csrf-token"]').attr('content') || '';
        },

        /**
         * Show global loading overlay.
         */
        showLoading(message) {
            const $overlay = $('#global-loading-overlay');
            if (message) {
                $overlay.find('.loading-text').text(message);
            }
            $overlay.addClass('is-visible').attr('aria-hidden', 'false');
        },

        /**
         * Hide global loading overlay.
         */
        hideLoading() {
            $('#global-loading-overlay')
                .removeClass('is-visible')
                .attr('aria-hidden', 'true')
                .find('.loading-text')
                .text('Please wait...');
        },

        /**
         * Generate a unique request key for deduplication.
         */
        buildRequestKey(url, method, data) {
            const payload = typeof data === 'string' ? data : JSON.stringify(data || {});
            return `${method.toUpperCase()}:${url}:${payload}`;
        },

        /**
         * Core AJAX request method.
         */
        request(options) {
            const defaults = {
                url: '',
                method: 'GET',
                data: null,
                dataType: 'json',
                showLoader: true,
                loaderMessage: 'Please wait...',
                preventDuplicate: true,
                headers: {},
                beforeSend: null,
                success: null,
                error: null,
                complete: null,
            };

            const settings = $.extend(true, {}, defaults, options);
            const method = (settings.method || 'GET').toUpperCase();
            const requestKey = settings.preventDuplicate
                ? this.buildRequestKey(settings.url, method, settings.data)
                : null;

            if (requestKey && this.pendingRequests.has(requestKey)) {
                return $.Deferred().reject({
                    duplicate: true,
                    message: 'Duplicate request prevented.',
                }).promise();
            }

            if (requestKey) {
                this.pendingRequests.add(requestKey);
            }

            const ajaxOptions = {
                url: settings.url,
                type: method,
                data: settings.data,
                dataType: settings.dataType,
                processData: settings.processData !== false,
                contentType: settings.contentType !== false ? settings.contentType : false,
                headers: $.extend(
                    {
                        'X-CSRF-TOKEN': this.getCsrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'application/json',
                    },
                    settings.headers
                ),
                beforeSend: (xhr) => {
                    if (settings.showLoader) {
                        this.showLoading(settings.loaderMessage);
                    }

                    if (typeof settings.beforeSend === 'function') {
                        settings.beforeSend(xhr);
                    }
                },
                success: (response, textStatus, xhr) => {
                    if (typeof settings.success === 'function') {
                        settings.success(response, textStatus, xhr);
                    }
                },
                error: (xhr, textStatus, errorThrown) => {
                    const handled = this.handleError(xhr, textStatus, errorThrown);

                    if (typeof settings.error === 'function') {
                        settings.error(xhr, textStatus, errorThrown, handled);
                    }
                },
                complete: (xhr, textStatus) => {
                    if (requestKey) {
                        this.pendingRequests.delete(requestKey);
                    }

                    if (settings.showLoader) {
                        this.hideLoading();
                    }

                    if (typeof settings.complete === 'function') {
                        settings.complete(xhr, textStatus);
                    }
                },
            };

            return $.ajax(ajaxOptions);
        },

        get(url, data, options) {
            return this.request($.extend({}, options, { url, method: 'GET', data }));
        },

        post(url, data, options) {
            return this.request($.extend({}, options, { url, method: 'POST', data }));
        },

        put(url, data, options) {
            return this.postWithMethod(url, data, 'PUT', options);
        },

        patch(url, data, options) {
            return this.postWithMethod(url, data, 'PATCH', options);
        },

        postWithMethod(url, data, method, options) {
            let payload = data;

            if (data instanceof FormData) {
                data.append('_method', method);
                payload = data;
            } else if (typeof data === 'object' && data !== null) {
                payload = $.extend({}, data, { _method: method });
            } else {
                payload = (data ? data + '&' : '') + '_method=' + method;
            }

            return this.request($.extend({}, options, { url, method: 'POST', data: payload }));
        },

        delete(url, data, options) {
            return this.request($.extend({}, options, { url, method: 'DELETE', data }));
        },

        /**
         * Submit a form via AJAX with duplicate prevention.
         */
        submitForm($form, options) {
            const formId = $form.attr('id') || $form.data('form-key') || $form.get(0);

            if (this.activeSubmits.has(formId)) {
                return $.Deferred().reject({
                    duplicate: true,
                    message: 'Form is already being submitted.',
                }).promise();
            }

            this.activeSubmits.add(formId);

            const method = ($form.attr('method') || 'POST').toUpperCase();
            let url = $form.attr('action');
            const hasFiles = $form.find('input[type="file"]').toArray().some((input) => input.files.length > 0);
            let data = hasFiles ? new FormData($form[0]) : $form.serialize();
            let processData = !hasFiles;
            let contentType = hasFiles ? false : 'application/x-www-form-urlencoded; charset=UTF-8';

            if (!hasFiles && (method === 'PUT' || method === 'PATCH' || method === 'DELETE')) {
                data += (data ? '&' : '') + '_method=' + method;
            }

            const settings = $.extend(
                {
                    url,
                    method: method === 'GET' ? 'GET' : 'POST',
                    data,
                    processData,
                    contentType,
                    showLoader: true,
                    loaderMessage: 'Saving...',
                    complete: () => {
                        this.activeSubmits.delete(formId);
                    },
                },
                options
            );

            const request = this.request(settings);

            request.always(() => {
                this.activeSubmits.delete(formId);
            });

            return request;
        },

        /**
         * Handle AJAX errors consistently.
         */
        handleError(xhr, textStatus, errorThrown) {
            const response = xhr.responseJSON || {};
            let message = response.message || 'An unexpected error occurred. Please try again.';

            if (xhr.status === 0) {
                message = 'Network error. Please check your connection.';
            } else if (xhr.status === 401) {
                message = response.message || 'Your session has expired. Please log in again.';
                if (window.NotificationHelper) {
                    window.NotificationHelper.error(message);
                }
                setTimeout(() => {
                    const loginUrl = document.querySelector('meta[name="login-url"]')?.content;
                    window.location.href = loginUrl || '/login';
                }, 1500);
            } else if (xhr.status === 403) {
                message = response.message || 'You do not have permission to perform this action.';
            } else if (xhr.status === 404) {
                message = response.message || 'The requested resource was not found.';
            } else if (xhr.status === 422) {
                message = response.message || 'Please correct the validation errors.';
            } else if (xhr.status === 419) {
                message = 'Session expired. Please refresh the page and try again.';
            } else if (xhr.status >= 500) {
                message = response.message || 'Server error. Please try again later.';
            }

            if (window.NotificationHelper && xhr.status !== 401) {
                window.NotificationHelper.error(message);
            }

            return {
                status: xhr.status,
                message,
                errors: response.errors || {},
                response,
                textStatus,
                errorThrown,
            };
        },

        /**
         * Bind sidebar toggle for admin layout.
         */
        initSidebar() {
            const $wrapper = $('#admin-wrapper');
            const $sidebar = $('#admin-sidebar');
            const $backdrop = $('#sidebar-backdrop');

            $('#sidebar-toggle').on('click', () => {
                $wrapper.toggleClass('sidebar-open');
                $sidebar.toggleClass('show');
                $backdrop.toggleClass('show');
            });

            $('#sidebar-close, #sidebar-backdrop').on('click', () => {
                $wrapper.removeClass('sidebar-open');
                $sidebar.removeClass('show');
                $backdrop.removeClass('show');
            });
        },
    };

    window.AjaxHelper = AjaxHelper;

    $(document).ready(function () {
        if ($('#admin-wrapper').length) {
            AjaxHelper.initSidebar();
        }

        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': AjaxHelper.getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
    });
})(window, window.jQuery);
