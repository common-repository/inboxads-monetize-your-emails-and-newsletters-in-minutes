
/**
 * Add custom tinyMCE button for all editors.
 */

( function( $ ) {
	var wpr_processed = false;

	function addEditorButton() {
		$( tinyMCE.editors ).each( function( index, editor ) {
			if ( editor.wpr_processed || ! editor.settings.toolbar2.match( /code mailpoet_shortcodes/ ) ) {
				// Not a text area editor, or already initialized.
				return;
			}

			if ( ! editor.settings.plugins.match( /inboxads/ ) && ! editor.settings.toolbar2.match( /inboxads/ ) ) {
				// Add plugin.
				editor.settings.plugins += ' inboxads';

				// Add toolbar button.
				editor.settings.toolbar2 += ' inboxads';

				// Refresh editor.
				editor.render();
			}

			editor.wpr_processed = true;
		} );

		if ( ! wpr_processed && $('.mailpoet_content_region .mailpoet_region_content > div').length ) {
			$('.mailpoet_content_region .mailpoet_region_content').append(`
				<div style="clear:both">
				<br/>
				<h4>You can add only on top of Text Content Blocks</h4>
				<div id="automation_editor_block_inboxads" class="mailpoet_widget mailpoet_droppable_block mailpoet_droppable_widget">
					<div id="inboxads_draggable" class="mailpoet_widget_icon">
						<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
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
						</svg>
					</div>
					<div class="mailpoet_widget_title">inboxAds</div>
				</div>
				</div>
				<style>
				.inboxads_hovered {
					background: #c0ccd8;
					border-radius: 4px;
				}
				</style>
			`);

			$( '#inboxads_draggable' ).draggable({
				revert: 'invalid',
				revertDuration: 0,
				containment: 'document',
				helper: 'clone',
				cursor: 'move',
				start: function() {
					
					$( '#mailpoet_editor_content .mailpoet_text_content' ).droppable({
						greedy: true,
						accept: '#inboxads_draggable',
						hoverClass: 'inboxads_hovered',
						drop: function( event, ui ) {
							tinyMCE.get($(this).attr('id')).focus();
		
							var editor = tinyMCE.activeEditor;
							var last_id = tinyMCE.DOM.uniqueId();
							editor.dom.add(editor.getBody(), 'div', {'id': last_id}, '');
							var new_node = editor.dom.select('div#' + last_id);
							editor.selection.select(new_node[0]);
							$('#' + last_id).parent('.mailpoet_text_content').html('');
		
							$('#wpr-inboxads-trigger').click();
							$('#TB_window').addClass('wpr-inboxads-panel');
						}
					});

				}
			});

			wpr_processed = true;
		}
	}

	$(function(){
		setInterval( addEditorButton, 1000 );
	});
	
} )( jQuery );
