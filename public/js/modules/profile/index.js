(function (window, $) {
    'use strict';

    if (!$ || !window.AjaxHelper || !window.FormHelper) {
        return;
    }

    function renderAvatarPreview(url, fallbackInitial) {
        const $preview = $('#profile-avatar-preview');
        if (!$preview.length) {
            return;
        }

        if (url) {
            $preview.html('<img src="' + url + '" alt="Profile photo" class="profile-avatar-image">');
            $('#topbar-user-avatar').html('<img src="' + url + '" alt="" class="user-avatar-image">');
        } else if (fallbackInitial) {
            $preview.html('<span class="profile-avatar-initial">' + fallbackInitial + '</span>');
            $('#topbar-user-avatar').text(fallbackInitial);
        }
    }

    $(function () {
        const $profileForm = $('#profile-form');
        const $passwordForm = $('#password-form');
        const $avatarInput = $('#profile-avatar-input');

        if ($avatarInput.length && $profileForm.length) {
            $avatarInput.on('change', function () {
                const file = this.files && this.files[0];
                if (!file) {
                    return;
                }

                const formData = new FormData();
                formData.append('avatar', file);
                formData.append('_token', $('meta[name="csrf-token"]').attr('content'));

                window.AjaxHelper.post($profileForm.data('avatar-url'), formData, {
                    processData: false,
                    contentType: false,
                    preventDuplicate: false,
                    loaderMessage: 'Uploading photo...',
                    success: function (response) {
                        window.NotificationHelper.success(response.message || 'Profile photo updated.');
                        const avatarUrl = response.data?.user?.avatar_url;
                        const name = response.data?.user?.name || '';
                        renderAvatarPreview(avatarUrl, name.charAt(0).toUpperCase());
                        $avatarInput.val('');
                        $('[data-error="avatar"]').text('').hide();
                    },
                    error: function (xhr, textStatus, errorThrown, handled) {
                        const message = handled?.errors?.avatar?.[0]
                            || handled?.message
                            || 'Upload failed.';
                        $('[data-error="avatar"]').text(message).show();
                        if (window.NotificationHelper) {
                            window.NotificationHelper.error(message);
                        }
                    },
                });
            });
        }

        if ($profileForm.length) {
            $profileForm.on('submit', function (event) {
                event.preventDefault();
                window.FormHelper.clearErrors($profileForm);

                window.AjaxHelper.put($profileForm.data('update-url'), $profileForm.serialize(), {
                    success: function (response) {
                        window.NotificationHelper.success(response.message || 'Profile updated.');
                        const name = response.data?.user?.name;
                        if (name) {
                            $('.user-name').text(name);
                            if (!$('#topbar-user-avatar img').length) {
                                $('#topbar-user-avatar').text(name.charAt(0).toUpperCase());
                            }
                        }
                    },
                    error: function (xhr, textStatus, errorThrown, handled) {
                        if (handled?.errors) {
                            window.FormHelper.showErrors($profileForm, handled.errors);
                        }
                    },
                });
            });
        }

        if ($passwordForm.length) {
            $passwordForm.on('submit', function (event) {
                event.preventDefault();
                window.FormHelper.clearErrors($passwordForm);

                window.AjaxHelper.put($profileForm.data('password-url'), $passwordForm.serialize(), {
                    success: function (response) {
                        window.NotificationHelper.success(response.message || 'Password updated.');
                        $passwordForm[0].reset();
                    },
                    error: function (xhr, textStatus, errorThrown, handled) {
                        if (handled?.errors) {
                            window.FormHelper.showErrors($passwordForm, handled.errors);
                        }
                    },
                });
            });
        }
    });
}(window, window.jQuery));
