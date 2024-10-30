<?php

/**
 * InboxAds Plugin Integration - SendPress.
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
 * Class which handles the InboxAds integration with SendPress.
 */
class InboxAds_Plugin_SendPress extends InboxAds_Plugin {

	/**
	 * Supported plugin slug.
	 *
	 * @var string
	 */
	protected $plugin = 'sendpress/sendpress.php';

	/**
	 * Initialize.
	 */
	public function init() {
		add_filter( 'wpr_inboxads_zone_code', array( $this, 'replace_shortcode' ), 10, 3 );
		add_filter( 'spnl_subscriber_tags', array( $this, 'add_tags' ) );
	}

	/**
	 * Replace ad zone shortcode with custom SendPress tag.
	 *
	 * @param string $code
	 * @param string $newsletter
	 * @param array  $options
	 * @return string
	 */
	public function replace_shortcode( $code, $newsletter, $options ) {
		if ( 'SendPress' !== $newsletter || empty( $_GET['emailID'] ) ) {
			return $code;
		}

		$newsletters = get_option( 'wpr_inboxads_newsletters', array() );

		if ( ! is_array( $newsletters ) || empty( $newsletters['SendPress'] ) ) {
			return $code;
		}

		$zone_options = array(
			'name'         => sanitize_text_field( $options['name'] ),
			'formatID'     => absint( $options['size'] ),
			'newsletterID' => $newsletters[ $newsletter ],
		);

		// Create hash from zone options.
		$hash = md5( serialize( $zone_options ) );

		$template_hashes = get_option( 'wpr_inboxads_sendpress_zones', array() );
		if ( empty( $template_hashes ) || ! is_array( $template_hashes ) ) {
			$template_hashes = array();
		}

		if ( array_key_exists( $hash, $template_hashes ) && ! empty( get_option( 'wpr_inboxads_zone_' . $hash ) ) ) {
			// Zone already registered.
			$code = '{inboxads-zone-' . $hash . '}';
		} else {
			// Create a new zone and save the hash.
			$zone_code = inboxads_api()->get_code( $zone_options );

			if ( ! empty( $zone_code ) ) {
				$code = '{inboxads-zone-' . $hash . '}';

				// Save zone hash and options.
				$template_hashes[ $hash ] = $options;
				update_option( 'wpr_inboxads_sendpress_zones', $template_hashes );
			}
		}

		return $code;
	}

	/**
	 * Add custom tags.
	 *
	 * @param array $tags
	 * @return array
	 */
	public function add_tags( $tags ) {
		$template_hashes = get_option( 'wpr_inboxads_sendpress_zones', true );
		if ( empty( $template_hashes ) || ! is_array( $template_hashes ) ) {
			$template_hashes = array();
		}

		foreach ( array_keys( $template_hashes ) as $hash ) {
			$tags[] = array(
				'tag'         => 'inboxads-zone-' . $hash,
				'description' => esc_html__( 'An InboxAds zone.', 'wpr' ),
				'function'    => array( $this, 'do_tag' ),
				'internal'    => array( $this, 'do_tag' ),
				'copy'        => array( $this, 'copy_tag' ),
			);
		}

		return $tags;
	}

	/**
	 * Output custom tag ad zone code.
	 *
	 * @param int $template_id
	 * @param int $email_id
	 * @param int $subscriber_id
	 * @param string $tag
	 * @return string
	 */
	public function do_tag( $template_id, $email_id, $subscriber_id, $tag ) {
		$hash = str_replace( 'inboxads-zone-', '', $tag );
		$code = get_option( 'wpr_inboxads_zone_' . $hash );

		if ( class_exists( '\SendPress_Data' ) ) {
			$subscriber = \SendPress_Data::get_subscriber( $subscriber_id );

			if ( ! is_null( $subscriber ) ) {
				$code = str_replace( '{{email}}', $subscriber->email, $code );
			}
		}

		return inboxads_replace_zone_tags( $code );
	}
}

new InboxAds_Plugin_SendPress();
