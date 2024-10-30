
'use strict';

/* jshint unused: false */
/* global jQuery, tinyMCE, wpr_shortcode */

( function($) {

	// Add shortcode inside editor content.
	function addShortcode() {
		var _atts = [
			'name',
			'size',
			'type',
		];
		var atts = '';

		var output = true;

		$( _atts ).each( function( i, att ) {
			var $field = $( '#inboxads-zone-' + att );
			var value = $field.val();

			if ( ! $field || value.length === 0 ) {
				output = false;

				return;
			}

			atts += ' ' + att + '="' + value + '"';

			if ( 'name' === att || 'size' === att ) {
				// Reset the field.
				$field.val( '' );
				$('.ZoneFormats ul li, .ZonePreview').removeClass('on');
				$('.ZonePreview').css('margin-top', 0);
			}
		} );

		if ( ! output ) {
			return;
		}

		var contentToEditor = '[inboxads_zone ' + atts + ']';

		// Send the shortcode to the content editor.
		if ( window.send_to_editor ) {
			window.send_to_editor( contentToEditor );
		} else if ( tinyMCE.activeEditor ) {
			tinyMCE.activeEditor.insertContent( contentToEditor );
			$( '.wpr-inboxads-panel #TB_closeWindowButton' ).click();
		}
	}

	if (typeof(tinyMCE) !== 'undefined') {
		tinyMCE.PluginManager.add( 'inboxads', function( editor ) {
			if (typeof(editor) !== 'undefined' && typeof(editor.ui) !== 'undefined' && typeof(editor.ui.registry) !== 'undefined') {
				editor.ui.registry.addIcon( 'inboxads', `<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
																<rect width="24" height="24" fill="white"/>
																<path fill-rule="evenodd" clip-rule="evenodd" d="M13.8929 9.77435C13.8926 9.77451 13.8926 9.77481 13.8926 9.77542V15.1994H22.3906V9.77512C22.3906 9.77481 22.3903 9.77435 22.3903 9.77405L19.3604 11.9523L19.0375 12.1842L18.9492 12.2479L18.8965 12.2856L18.8858 12.2934L18.6036 12.4963C18.603 12.4969 18.6024 12.4969 18.6018 12.4972C18.6012 12.4972 18.6006 12.4975 18.6 12.4978C18.6 12.4982 18.5998 12.4989 18.5998 12.4996V12.5015C18.4241 12.5929 18.2509 12.6149 18.103 12.6067C18.0386 12.6033 17.9814 12.5933 17.9279 12.5816C17.7785 12.5485 17.6799 12.4963 17.6799 12.4963L17.2973 12.2215L16.923 11.9523L15.4079 10.863L13.8929 9.77405V9.77435Z" fill="#F47521"/>
																<path fill-rule="evenodd" clip-rule="evenodd" d="M18.0488 11.6986C18.064 11.7096 18.0753 11.7257 18.0872 11.7408H18.1944C18.206 11.7257 18.2171 11.7096 18.2328 11.6986L22.0121 8.98163C21.9422 8.93015 21.8651 8.89136 21.7833 8.8657C21.7166 8.84446 21.647 8.83179 21.575 8.83179H14.7064C14.5455 8.83179 14.3958 8.888 14.2695 8.98163L16.4854 10.5751L18.0488 11.6986Z" fill="#F47521"/>
																<mask id="mask0" mask-type="alpha" maskUnits="userSpaceOnUse" x="4" y="4" width="19" height="4">
																<path fill-rule="evenodd" clip-rule="evenodd" d="M4.51562 4.76843H22.4919V7.13579H4.51562V4.76843Z" fill="white"/>
																</mask>
																<g mask="url(#mask0)">
																<path fill-rule="evenodd" clip-rule="evenodd" d="M22.287 4.76843H4.72076C4.60751 4.76843 4.51562 4.86191 4.51562 4.97754V6.9264C4.51562 7.04203 4.60751 7.13582 4.72076 7.13582H22.2867C22.4001 7.13582 22.4919 7.04203 22.4919 6.9264V4.97723C22.4919 4.86191 22.4001 4.76843 22.287 4.76843" fill="#004CBA"/>
																</g>
																<mask id="mask1" mask-type="alpha" maskUnits="userSpaceOnUse" x="4" y="16" width="19" height="4">
																<path fill-rule="evenodd" clip-rule="evenodd" d="M4.51562 16.8642H22.4919V19.2315H4.51562V16.8642Z" fill="white"/>
																</mask>
																<g mask="url(#mask1)">
																<path fill-rule="evenodd" clip-rule="evenodd" d="M22.287 16.8642H4.72076C4.60751 16.8642 4.51562 16.9577 4.51562 17.0733V19.0222C4.51562 19.1378 4.60751 19.2316 4.72076 19.2316H22.2867C22.4001 19.2316 22.4919 19.1378 22.4919 19.0225V17.0733C22.4919 16.9577 22.4001 16.8642 22.287 16.8642" fill="#004CBA"/>
																</g>
																<path fill-rule="evenodd" clip-rule="evenodd" d="M11.9886 12.8324H8.5632C8.44857 12.8324 8.35547 12.9271 8.35547 13.0443V14.9876C8.35547 15.1048 8.44857 15.1995 8.5632 15.1995H11.9886C12.1033 15.1995 12.1964 15.1048 12.1964 14.9876V13.0443C12.1964 12.9271 12.1033 12.8324 11.9886 12.8324" fill="#004CBA"/>
																<path fill-rule="evenodd" clip-rule="evenodd" d="M12.1969 10.9575V9.01047C12.1969 8.89438 12.1048 8.80029 11.991 8.80029H1.71356C1.5997 8.80029 1.50781 8.89438 1.50781 9.01047V10.9575C1.50781 11.0736 1.5997 11.1675 1.71356 11.1675H11.991C12.1048 11.1675 12.1969 11.0736 12.1969 10.9575" fill="#004CBA"/>
															</svg>` );

				editor.ui.registry.addButton( 'inboxads', {
					text: wpr_shortcode.button_title,
					icon: 'inboxads',
					onAction: function() {
						$('#wpr-inboxads-trigger').click();
						$('#TB_window').addClass('wpr-inboxads-panel');
					}
				});
			} else {
				editor.addButton( 'inboxads', {
					title: wpr_shortcode.button_title,
					type: 'button',
					image: wpr_shortcode.assets + 'icon.png',
					// text: ' inboxAds',
					classes: 'inboxAdsShortcodeButton',
					onclick: function() {
						$('#wpr-inboxads-trigger').click();
						$('#TB_window').addClass('wpr-inboxads-panel');
					}
				});
			}
		});
	}

	var $form = $( '#wpr-inboxads-shortcode-form' );

	$form.on( 'submit', function( event ) {
		event.preventDefault();

		$('.shortcodeFormError').remove();

		var name = $('#inboxads-zone-name').val();
		var size = $('#inboxads-zone-size').val();

		if (name.length === 0) {
			$('#inboxads-zone-name').after('<div class="shortcodeFormError">Please enter a name for the newsletter zone!</div>');
		}

		if (size.length === 0) {
			$('.ZoneFormats').after('<div class="shortcodeFormError">Please select a zone format!</div>');
		}

		if (name.length === 0 || size.length === 0) {
			return;
		}

		addShortcode();
	} );

	// if (typeof(wp) !== 'undefined' && typeof(wp.richText) !== 'undefined' && typeof(wp.blockEditor) !== 'undefined') {
	// 	( function( wp ) {
	// 		var inboxAdsButton = function( props ) {
	// 			return wp.element.createElement(
	// 				wp.blockEditor.RichTextToolbarButton, {
	// 					icon: 'editor-code',
	// 					title: 'inboxAds',
	// 					onClick: function() {
	// 						$('#wpr-inboxads-trigger').click();
	// 						$('#TB_window').addClass('wpr-inboxads-panel');
	// 					},
	// 				}
	// 			);
	// 		}
	// 		wp.richText.registerFormatType(
	// 			'my-custom-format/sample-output', {
	// 				title: 'inboxAds',
	// 				tagName: 'iads',
	// 				className: null,
	// 				edit: inboxAdsButton,
	// 			}
	// 		);
	// 	} )( window.wp );
	// }

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
		$('#inboxads-zone-size').val(id);
	});

})(jQuery);
