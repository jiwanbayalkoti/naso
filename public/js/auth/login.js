/**
 * Login form AJAX handler.
 */
(function (window, $) {
    'use strict';

    if (!$) {
        return;
    }

    $(document).ready(function () {
        const $form = $('#login-form');
        if (!$form.length) {
            return;
        }

        const $submitBtn = $('#login-submit');
        const $email = $('#email');
        const $password = $('#password');

        $('#toggle-password').on('click', function () {
            const $input = $password;
            const $icon = $(this).find('i');
            const isPassword = $input.attr('type') === 'password';

            $input.attr('type', isPassword ? 'text' : 'password');
            $icon.toggleClass('fa-eye fa-eye-slash');
        });

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
                      loaderMessage: 'Signing in...',
                      success: function (response) {
                          if (window.NotificationHelper) {
                              window.NotificationHelper.success(
                                  response.message || 'Login successful. Redirecting...'
                              );
                          }

                          const redirectUrl = response.data?.redirect
                              || response.redirect
                              || $form.data('redirect')
                              || '/dashboard';
                          setTimeout(function () {
                              window.location.href = redirectUrl;
                          }, 500);
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
