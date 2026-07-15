/**
 * Form helper for modal forms, validation display, and reset on success.
 */
(function (window, $) {
    'use strict';

    if (!$) {
        return;
    }

    const FormHelper = {
        /**
         * Convert Laravel dot notation to HTML input name.
         */
        toInputName(field) {
            const parts = field.split('.');

            if (parts.length === 1) {
                return field;
            }

            return parts[0] + parts.slice(1).map((part) => `[${part}]`).join('');
        },

        /**
         * Clear validation errors from a form.
         */
        clearErrors($form) {
            $form.find('.is-invalid').removeClass('is-invalid');
            $form.find('.invalid-feedback').text('').hide();
            $form.find('[data-field]').text('').hide();
        },

        /**
         * Display Laravel validation errors on form fields.
         */
        showErrors($form, errors) {
            this.clearErrors($form);

            if (!errors || typeof errors !== 'object') {
                return;
            }

            Object.keys(errors).forEach((field) => {
                const messages = errors[field];
                const message = Array.isArray(messages) ? messages[0] : messages;
                const inputName = this.toInputName(field);
                const $input = $form.find('[name="' + inputName + '"]');
                const $feedback = $form.find('[data-error="' + field + '"], [data-field="' + field + '"]');

                $input.addClass('is-invalid');

                if ($feedback.length) {
                    $feedback.text(message).show();
                } else if ($input.length) {
                    let $inlineFeedback = $input.siblings('.invalid-feedback');
                    if (!$inlineFeedback.length) {
                        $inlineFeedback = $input.closest('.input-group').siblings('.invalid-feedback');
                    }
                    if (!$inlineFeedback.length) {
                        $inlineFeedback = $('<div class="invalid-feedback d-block"></div>');
                        $input.closest('.mb-3, .form-group').append($inlineFeedback);
                    }
                    $inlineFeedback.text(message).show();
                }
            });
        },

        /**
         * Reset form fields to initial state.
         */
        reset($form, clearHidden) {
            $form[0].reset();
            this.clearErrors($form);

            if (clearHidden) {
                $form.find('input[type="hidden"]').not('[name="_token"], [name="_method"]').val('');
            }

            $form.find('select').each(function () {
                const $select = $(this);
                if ($select.hasClass('select2-hidden-accessible')) {
                    $select.val(null).trigger('change');
                }
            });
        },

        /**
         * Set submit button loading state.
         */
        setSubmitLoading($button, isLoading, loadingText) {
            if (!$button || !$button.length) {
                return;
            }

            if (isLoading) {
                $button.data('original-html', $button.html());
                $button.prop('disabled', true).addClass('is-loading');
                $button.html(
                    '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>' +
                        (loadingText || 'Saving...')
                );
            } else {
                const originalHtml = $button.data('original-html');
                if (originalHtml) {
                    $button.html(originalHtml);
                }
                $button.prop('disabled', false).removeClass('is-loading');
            }
        },

        /**
         * Handle modal form submission via AJAX.
         */
        handleModalForm(options) {
            const settings = $.extend(
                {
                    formSelector: null,
                    modalSelector: null,
                    submitSelector: '.modal-submit-btn',
                    method: null,
                    url: null,
                    resetOnSuccess: true,
                    closeOnSuccess: true,
                    successMessage: 'Record saved successfully.',
                    onSuccess: null,
                    onError: null,
                    beforeSubmit: null,
                },
                options
            );

            const $form = $(settings.formSelector);
            const $modal = $(settings.modalSelector);
            const $submitBtn = $modal.find(settings.submitSelector);

            $submitBtn.off('click.formHelper').on('click.formHelper', function (e) {
                e.preventDefault();
                FormHelper.submitModal($form, $modal, settings);
            });

            $form.off('submit.formHelper').on('submit.formHelper', function (e) {
                e.preventDefault();
                FormHelper.submitModal($form, $modal, settings);
            });
        },

        /**
         * Submit modal form.
         */
        submitModal($form, $modal, settings) {
            if (typeof settings.beforeSubmit === 'function') {
                const proceed = settings.beforeSubmit($form);
                if (proceed === false) {
                    return;
                }
            }

            const $submitBtn = $modal.find(settings.submitSelector);
            this.setSubmitLoading($submitBtn, true);

            const url = settings.url || $form.attr('action');
            const method = settings.method || $form.attr('method') || 'POST';
            let data = $form.serialize();

            if (['PUT', 'PATCH', 'DELETE'].includes(method.toUpperCase())) {
                data += (data ? '&' : '') + '_method=' + method.toUpperCase();
            }

            const request = window.AjaxHelper
                ? window.AjaxHelper.request({
                      url,
                      method: method.toUpperCase() === 'GET' ? 'GET' : 'POST',
                      data,
                      showLoader: false,
                      success: (response) => {
                          if (settings.successMessage && window.NotificationHelper) {
                              window.NotificationHelper.success(
                                  response.message || settings.successMessage
                              );
                          }

                          if (settings.resetOnSuccess) {
                              FormHelper.reset($form, true);
                          }

                          if (settings.closeOnSuccess && $modal.length) {
                              bootstrap.Modal.getOrCreateInstance($modal[0]).hide();
                          }

                          if (typeof settings.onSuccess === 'function') {
                              settings.onSuccess(response, $form, $modal);
                          }
                      },
                      error: (xhr, textStatus, errorThrown, handled) => {
                          if (handled && handled.errors) {
                              FormHelper.showErrors($form, handled.errors);
                          }

                          if (typeof settings.onError === 'function') {
                              settings.onError(xhr, handled, $form, $modal);
                          }
                      },
                      complete: () => {
                          FormHelper.setSubmitLoading($submitBtn, false);
                      },
                  })
                : $.ajax({
                      url,
                      type: 'POST',
                      data,
                      complete: () => {
                          FormHelper.setSubmitLoading($submitBtn, false);
                      },
                  });

            return request;
        },

        /**
         * Initialize Select2 on form elements.
         */
        initSelect2($context, options) {
            const defaults = {
                theme: 'bootstrap-5',
                width: '100%',
                allowClear: true,
                placeholder: 'Select an option',
            };

            ($context || $(document)).find('.select2').each(function () {
                const $el = $(this);
                const settings = $.extend({}, defaults, options || {}, $el.data());

                if ($el.hasClass('select2-hidden-accessible')) {
                    $el.select2('destroy');
                }

                $el.select2(settings);
            });
        },

        /**
         * Reset modal form when modal is hidden.
         */
        bindModalReset(modalSelector, formSelector) {
            const $modal = $(modalSelector);
            const $form = $(formSelector);

            $modal.on('hidden.bs.modal', function () {
                FormHelper.reset($form, true);
            });
        },
    };

    window.FormHelper = FormHelper;

    $(document).ready(function () {
        FormHelper.initSelect2();
    });
})(window, window.jQuery);
