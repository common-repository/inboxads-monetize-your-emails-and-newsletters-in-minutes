<?php

/**
 * InboxAds Plugin Integration - Newsletter.
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
 * Class which handles the InboxAds integration with Newsletter.
 */
class InboxAds_Plugin_Newsletter extends InboxAds_Plugin {

	/**
	 * Supported plugin slug.
	 *
	 * @var string
	 */
	protected $plugin = 'newsletter/plugin.php';

	/**
	 * Initialize.
	 */
	public function init() {
		add_filter( 'newsletter_blocks_dir', array( $this, 'newsletter_blocks_dir' ) );
		add_filter( 'wpr_inboxads_zone_code', array( $this, 'replace_tags' ), 10, 2 );
	}

	/**
	 * Add custom folder to load builder blocks from.
	 *
	 * @param array $dirs
	 * @return array
	 */
	public function newsletter_blocks_dir( $dirs ) {
		$dirs[] = WPR_ADS_PATH . 'plugins/newsletter/blocks';

		return $dirs;
	}

	/**
	 * Replace ad zone placeholder tags.
	 *
	 * @param string $code
	 * @param string $newsletter
	 * @return string
	 */
	public function replace_tags( $code, $newsletter ) {
		if ( 'Newsletter' !== $newsletter ) {
			return $code;
		}

		// Replace email tag with Newsletter's own tag.
		return str_replace( '{{email}}', '{email}', $code );
	}
}

new InboxAds_Plugin_Newsletter();
