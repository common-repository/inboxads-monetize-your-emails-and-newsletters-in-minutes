<?php

/**
 * InboxAds Plugin Integration - Base.
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
class InboxAds_Plugin {

	/**
	 * API class instance.
	 *
	 * @var InboxAds_API
	 */
	private $api;

	/**
	 * Supported plugin slug.
	 *
	 * @var string
	 */
	protected $plugin;

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Get API instance.
		$this->api = InboxAds_API::get_instance();

		require_once ABSPATH . '/wp-admin/includes/plugin.php';

		if ( $this->api->is_connected() && ( is_plugin_active( $this->plugin ) || is_plugin_active_for_network( $this->plugin ) ) ) {
			$this->init();
		}
	}

	/**
	 * Initialize plugin.
	 */
	public function init() {
		// Override in child class.
	}
}
