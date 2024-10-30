<?php

/**
 * InboxAds Plugin Integration - MailPoet.
 *
 * @package WPR
 */

namespace WPR;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit;
}

/**
 * Class which handles the InboxAds integration with MailPoet.
 */
class InboxAds_Plugin_MailPoet extends InboxAds_Plugin {

	/**
	 * Supported plugin slug.
	 *
	 * @var string
	 */
	protected $plugin = 'mailpoet/mailpoet.php';

	/**
	 * Initialize.
	 */
	public function init() {
		wp_enqueue_script( 'jquery-ui-draggable' );
		wp_enqueue_script( 'jquery-ui-droppable' );

		add_filter( 'wpr_inboxads_zone_code', array( $this, 'replace_tags' ), 10, 2 );
		add_action( 'mailpoet_newsletter_editor_after_javascript', array( $this, 'add_scripts' ) );
		add_filter( 'mailpoet_rendering_post_process', array( $this, 'process_shortcodes' ) );
	}

	public function add_scripts() {
		// Insert scripts and styles directly for MailPoet.
		global $wpr_inboxads_localized, $wpr_inboxads_shortcode_builder;

		$thickbox_localized = array(
			'next'             => __( 'Next &gt;' ),
			'prev'             => __( '&lt; Prev' ),
			'image'            => __( 'Image' ),
			'of'               => __( 'of' ),
			'close'            => __( 'Close' ),
			'noiframes'        => __( 'This feature requires inline frames. You have iframes disabled or your browser does not support them.' ),
			'loadingAnimation' => includes_url( 'js/thickbox/loadingAnimation.gif' ),
		);

		$wpr_inboxads_shortcode_builder->display_panel();

		?>

		<script type="text/javascript">thickboxL10n = <?php echo json_encode( $thickbox_localized ); ?>;</script>
		<script type="text/javascript" src="<?php echo includes_url( 'js/thickbox/thickbox.js' ); ?>"></script>
		<script type="text/javascript" src="<?php echo WPR_ADS_URI; ?>plugins/shortcode-builder/admin.js"></script>
		<script type="text/javascript" src="<?php echo WPR_ADS_URI; ?>plugins/mailpoet/admin.js"></script>
		<script type="text/javascript">wpr_shortcode = <?php echo json_encode( $wpr_inboxads_localized ); ?>;</script>
		<link rel="stylesheet" type="text/css" href="<?php echo includes_url( 'js/thickbox/thickbox.css' ); ?>">
		<link rel="stylesheet" type="text/css" href="<?php echo WPR_ADS_URI; ?>plugins/shortcode-builder/shortcode-builder.css">
		<?php
	}

	/**
	 * Replace ad zone placeholder tags.
	 *
	 * @param string $code
	 * @param string $newsletter
	 * @return string
	 */
	public function replace_tags( $code, $newsletter ) {
		if ( 'MailPoet' !== $newsletter ) {
			return $code;
		}

		// Replace email tag with MailPoet's own tag.
		return str_replace( '{{email}}', '[subscriber:email]', $code );
	}

	/**
	 * Process email content shortcodes.
	 *
	 * @param string $content
	 * @return string
	 */
	public function process_shortcodes( $content ) {
		// Find all InboxAds shortcodes.
		preg_match_all( '/\[inboxads_zone(\s.*?)?\]/', $content, $shortcodes );

		// Replace shortcodes with their output.
		if ( ! empty( $shortcodes[0] ) ) {
			foreach ( $shortcodes[0] as $shortcode ) {
				$content = str_replace( $shortcode, do_shortcode( $shortcode ), $content );
			}
		}

		return $content;
	}
}

new InboxAds_Plugin_MailPoet();
