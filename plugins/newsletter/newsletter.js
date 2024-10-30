( function($) {

    $(document).on('click', '.ZoneFormats li', function () {
		$('.ZonePreview .imgp').html('<img src="https://publishers.inboxads.com/Content/Images/Previews/rec_' + $(this).attr('rel') + '.png" />');
		var offset = $(this).children('div').offset();
		var margin = offset.top - $('.ZoneFormats').offset().top;
		if (margin + $('.ZonePreview').outerHeight() > $('.ZoneFormats').outerHeight()) {
			margin = $('.ZoneFormats').outerHeight() - $('.ZonePreview').outerHeight();
		}
		$('.ZonePreview').addClass('on').animate({ 'margin-top': margin }, 200);

		$('.ZoneFormats li').removeClass('on');
		$(this).addClass('on');
		var id = $(this).attr('rel');
		$('.idsnl #options-size').val(id);
    });
    
    $(function(){
        setTimeout(function() {
            var id = $('.idsnl #options-size').val();
            $('.ZoneFormats li[rel="'+id+'"]').click();
        }, 300);

        $('.idsnl .btn').click(function(){
            if ($('.idsnl #options-name').val().length === 0) {
                $('.idsnl #options-name').parent('.formitem').addClass('haserror');
                $('#newsletter-builder-sidebar').animate({
                    scrollTop: 0
                }, 'fast');
            } else {
                $('#tnpc-block-options-save').click();
            }
        });

        $('.idsnl #options-name').keyup(function(){
            $(this).parent('.formitem').removeClass('haserror');
        });
    });

})(jQuery);