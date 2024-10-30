/**
 * @file Mailster plugin admin scripts.
 */

/**
 * Initialize module.
 */
function addModule() {
	// Add custom module button.
	var $modulesList = jQuery( '#module-selector ul' );
	$modulesList.append( wpr_mailster_js.module_button.replace( /\{\{wpr_ia_index\}\}/g, $modulesList.children().length ) );

	// Add custom ad zone module.
	var $modulesArea = jQuery( 'textarea#modules' );
	$modulesArea.html( $modulesArea.html() + wpr_mailster_js.module_block );
}

/**
 * Document ready.
 */
( function( $ ) {

	addModule();

	var $iframe;
	var $previewTable;
	var $modules;
	var moduleWidth = 300;

	// Preview iframe loaded.
	$( window ).on( 'Mailster:enable', function() {
		$iframe = $( '#mailster_iframe' ).contents();
		$previewTable = $iframe.find( 'table.bodytbl' );

		if ( $iframe.length > 0 && $previewTable.length > 0 ) {
			// Get the template modules' width.
			moduleWidth = Math.max( 300, parseInt( $previewTable.find( 'table.wrap' ).attr( 'width' ), 10 ) );
		}

		// Get ad zone modules from preview.
		$modules = $iframe.find( 'module[label="' + wpr_mailster_js.module_name + '"]' );

		$modules.each( function() {
			var $settings = $( this ).find( '.wpr-inboxads-module-settings' );
			var savedName = $settings.find( '.settings-keeper span.name' ).text() || '';
			var savedSize = $settings.find( '.settings-keeper span.size' ).text() || 0;

			// Store settings as plain text inside the module markup.
			$settings.find( '.form-keeper' ).html( wpr_mailster_js.module_form );
			$settings.find( '.inboxads-zone-name' ).val( savedName );
			$settings.find( '.inboxads-zone-size' ).val( savedSize );
		} );
	} );

	// Preview iframe updated.
	$( window ).on( 'Mailster:refresh', function() {
		// Get ad zone modules from preview.
		$modules = $iframe.find( 'module[label="' + wpr_mailster_js.module_name + '"]' );

		if ( $modules.length === 0 ) {
			return;
		}

		// Set module wrapper width.
		$modules.find( 'table.wrap' ).attr( 'width', moduleWidth );

		$modules.each( function() {
			var $module = $( this );

			var $moduleSettings = $module.find( '.wpr-inboxads-module-settings' );

			// Add edit button if not already added.
			var $buttons = $module.find( 'modulebuttons > span' );

			var $editButton = $buttons.find( '.wpr-inboxads-edit' );

			if ( $editButton.length === 0 ) {
				$editButton = $( wpr_mailster_js.module_edit );
				$buttons.prepend( $editButton );
			}

			$editButton.off( 'click' ).on( 'click', function() {
				// Show module form when edit button is clicked.
				$moduleSettings.slideToggle( 400 );
			} );

			// Listen to module options form submit.
			$module.find( '.wpr-inboxads-module-form' ).off( 'submit' ).on( 'submit', function( event ) {
				event.preventDefault();

				var $form = $( this );

				var data = {
					action: 'wpr_inboxads_plugin_mailster_get_code',
					name: $form.find( '.inboxads-zone-name' ).val(),
					size: $form.find( '.inboxads-zone-size' ).val(),
				};

				$.ajax( {
					url: wpr_mailster_js.ajaxurl,
					type: 'POST',
					data: data,

					beforeSend: function() {
						$module.addClass( 'loading' );
					},

					complete: function() {
						$module.removeClass( 'loading' );
						$moduleSettings.find( '.settings-keeper span.name' ).text( data.name );
						$moduleSettings.find( '.settings-keeper span.size' ).text( data.size );
					},

					success: function( response, textStatus, jqXHR ) {
						if ( response && response.error ) {
							alert( response.error.message );
						} else if ( response && response.html ) {
							// Add the ad zone code to the module.
							$module.find( '.wpr-inboxads-module-output' ).html( response.html );
							// Hide the options form.
							$moduleSettings.slideUp( 400 );
							// Trigger Mailster events.
							setTimeout( function() {
								$( window ).trigger( 'Mailster:refresh' );
								$( window ).trigger( 'Mailster:save' );
							}, 1000 );
						}
					},

					error: function( jqXHR, textStatus, errorThrown ) {
						if ( jqXHR.responseJSON && jqXHR.responseJSON.message ) {
							alert( jqXHR.responseJSON.message );
						}
					}
				} );
			} );

			var scriptTag = `<script>		
			function idsck(id) {
				(function($) {
					var iframe = $('#mailster_iframe').contents();
					var li = iframe.find('module.wpr-inboxads-module .ZoneFormats li[rel="'+id+'"]');

					iframe.find('.ZonePreview .imgp').html('<img src="https://publishers.inboxads.com/Content/Images/Previews/rec_' + id + '.png" />');
					var offset = li.children('div').offset();
					var margin = offset.top - iframe.find('.ZoneFormats').offset().top;
					if (margin + iframe.find('.ZonePreview').outerHeight() > iframe.find('.ZoneFormats').outerHeight()) {
						margin = iframe.find('.ZoneFormats').outerHeight() - iframe.find('.ZonePreview').outerHeight();
					}
					iframe.find('.ZonePreview').addClass('on').animate({ 'margin-top': margin }, 200);

					iframe.find('.ZoneFormats li').removeClass('on');
					li.addClass('on');
					iframe.find('.inboxads-zone-size').val(id);
				})(jQuery);
			}
			</script>`;
			$iframe.find('.ZoneJss').html(scriptTag);
		} );
	} );

}( jQuery ) );

