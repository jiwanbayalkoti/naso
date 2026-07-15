/**
 * Registration form AJAX handler (shop & rider).
 */
(function (window, $) {
    'use strict';

    if (!$) {
        return;
    }

    $(document).ready(function () {
        const $form = $('#register-shop-form, #register-rider-form');
        if (!$form.length) {
            return;
        }

        const $submitBtn = $('#register-submit');

        $form.on('submit', function (e) {
            e.preventDefault();

            if (window.FormHelper) {
                window.FormHelper.clearErrors($form);
            }

            $submitBtn.prop('disabled', true);
            $submitBtn.find('.btn-text').addClass('d-none');
            $submitBtn.find('.btn-spinner').removeClass('d-none');

            const request = window.AjaxHelper
                ? window.AjaxHelper.submitForm($form, {
                      showLoader: true,
                      loaderMessage: 'Submitting registration...',
                      success: function (response) {
                          if (window.NotificationHelper) {
                              window.NotificationHelper.success(
                                  response.message || 'Registration submitted successfully.'
                              );
                          }

                          const redirectUrl = response.data?.redirect || response.redirect || '/registration/pending';
                          setTimeout(function () {
                              window.location.href = redirectUrl;
                          }, 600);
                      },
                      error: function (xhr, textStatus, errorThrown, handled) {
                          if (handled && handled.errors && window.FormHelper) {
                              window.FormHelper.showErrors($form, handled.errors);
                          }
                      },
                      complete: function () {
                          $submitBtn.prop('disabled', false);
                          $submitBtn.find('.btn-text').removeClass('d-none');
                          $submitBtn.find('.btn-spinner').addClass('d-none');
                      },
                  })
                : null;

            if (!request) {
                $form.off('submit').submit();
            }
        });
    });
})(window, window.jQuery);
