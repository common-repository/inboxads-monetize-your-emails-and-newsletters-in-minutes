<?php

/**
 * InboxAds Plugin Integration - ALO EasyMail.
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
 * Class which handles the InboxAds integration with ALO EasyMail.
 */
class InboxAds_Plugin_ALO_EasyMail extends InboxAds_Plugin {

	/**
	 * Supported plugin slug.
	 *
	 * @var string
	 */
	protected $plugin = 'alo-easymail/alo-easymail.php';

	/**
	 * Initialize.
	 */
	public function init() {
		add_filter( 'alo_easymail_newsletter_content', array( $this, 'newsletter_content' ), 10, 3 );
	}

	/**
	 * Replace email placeholder with recipient email address.
	 *
	 * @param string $content
	 * @param object $newsletter
	 * @param object $recipient
	 * @return string
	 */
	public function newsletter_content( $content, $newsletter, $recipient ) {
		return str_replace( '{{email}}', $recipient->email, $content );
	}
}

new InboxAds_Plugin_ALO_EasyMail();
