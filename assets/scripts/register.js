(function($) {
    $(window).load(function() {
        function validateRequired(input) {
            input.parent('.field').removeClass('hasError isRequired');
            var value = input.val();
            if (value.length === 0) {
                input.parent('.field').addClass('hasError isRequired');
                return false;
            }

            return true;
        }

        function validateEmail(input) {
            input.parent('.field').removeClass('hasError badEmail');
            var email = input.val();

            var re = /\S+@\S+\.\S+/;
            if (!re.test(String(email).toLowerCase())) {
                input.parent('.field').addClass('hasError badEmail');
                return false;
            }

            return true;
        }

        function validatePassword(input) {
            input.parent('.field').removeClass('hasError badPassword mismatchPassword');
            var value = input.val();
            var pass = /^(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}$/;

            if (!value.match(pass)) {
                input.parent('.field').addClass('hasError badPassword');
                return false;
            }

            var pass1 = input.parents('.card-password').find('.field input[type=password][name="wpr_iar[password]"]');
            var pass2 = input.parents('.card-password').find('.field input[type=password][name="wpr_iar[password2]"]');

            if (pass1.val() !== pass2.val()) {
                pass2.parent('.field').addClass('hasError mismatchPassword');
                return false;
            } else {
                pass2.parent('.field').removeClass('hasError mismatchPassword');
            }

            return true;
        }

        $('#wpr-inboxads-register > div [type=button].button-primary').on('click', function() {
            var hasError = false;

            $(this).parent('div').find('.field input[type=text]').each(function() {
                if (!validateRequired($(this)))
                    hasError = true;
            });

            $(this).parent('div').find('.field input[type=email]').each(function() {
                if (!validateEmail($(this)))
                    hasError = true;
            });

            if (!hasError) {
                var current = $(this).parent('div');
                current.addClass('d-none');

                var next = current.next('.d-none');
                next.removeClass('d-none');

                var id = next.attr('class');
                $('.steps li[data-id="' + id + '"]').addClass('on');
            }
        });

        $('#wpr-inboxads-register > div [type=button].button-text').on('click', function() {
            $(this).parent('div').addClass('d-none').prev('.d-none').removeClass('d-none');
        });

        $('#wpr-inboxads-register > div [type=submit].button-primary').on('click', function() {
            $('.registerError').html('');
            var hasError = false;

            $(this).parent('div').find('.field input[type=password]').each(function() {
                if (!validateRequired($(this)))
                    hasError = true;
                else if (!validatePassword($(this)))
                    hasError = true;
            });

            if (!hasError) {
                $.ajax({
                    'type': 'POST',
                    'url': $('#wpr-inboxads-register').attr('action'),
                    'data': $('#wpr-inboxads-register').serialize(),
                    'dataType': 'json'
                }).done(function(data) {
                    if (data.success === true) {
                        window.location.href = 'admin.php?page=wpr-inboxads&new=1';
                    } else {
                        $('.registerError').html('<span>' + data.error + '<br/>Please go back and update the registration form!</span>');
                    }
                });
            }
            
            return false;
        });

        $('#wpr-inboxads-register > div .field input[type=text]').on('blur', function() {
            validateRequired($(this));
        });

        $('#wpr-inboxads-register > div .field input[type=email]').on('blur', function() {
            validateEmail($(this));
        });

        $('#wpr-inboxads-register > div .field input[type=password]').on('blur', function() {
            if (validateRequired($(this)))
                validatePassword($(this));
        });

        $('#wpr-inboxads-register > div .partnerButton').on('click', function() {
            $('#wpr_iar_referral').val('');
            $('.partnerBox').slideToggle(function () {
                if ($('.partnerBox').is(':visible')) {
                    $('#wpr_iar_referral').focus();
                } else {
                    $('#wpr_iar_company').focus();
                }
            });
            return false;
        });

        $('.wpr-inboxads-install').on('click', function(event) {
            event.preventDefault();

            if ($(this).hasClass('disabled'))
                return;

            $(this).addClass('disabled').text('Installing...');
            
            $.ajax({
                url: ajaxurl,
                type: 'post',
                dataType: 'json',
                data: {
                    slug: $(this).data('slug'),
                    action: 'install-plugin',
                    _ajax_nonce: wp.updates.ajaxNonce
                }
            }).done( function() {
                location.reload();
            }).fail(function() {
                alert('Error!');
            });
        });

        $('.wpr-inboxads-activate').on('click', function(event) {
            event.preventDefault();

            if ($(this).hasClass('disabled'))
                return;

            $(this).addClass('disabled').text('Activating...');
            
            $.ajax({
                url: $(this).attr('href'),
                type: 'get'
            }).done( function() {
                location.reload();
            }).fail(function() {
                alert('Error!');
            });
        });

        $('.wpr-inboxads-acordeon li .header').on('click', function(event) {
            if ($(event.target).hasClass('button'))
                return;
            $(this).parents('li').find('.desc').slideToggle(200);
        });

        $('.wpr-inboxads-acordeon-show').on('click', function(event) {
            event.preventDefault();

            var text;
            if ($('.wpr-inboxads-acordeon.hidden').is(':visible'))
                text = 'Show All Supported Plugins';
            else
                text = 'Hide Unavailable Plugins';

            $('.wpr-inboxads-acordeon.hidden').slideToggle(200, function() {
                $('.wpr-inboxads-acordeon-show').text(text);
            });
        });
    });
}(jQuery));