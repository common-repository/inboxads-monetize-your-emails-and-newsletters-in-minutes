<?php

/**
 * Helper functions.
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
 * Get instance of API class.
 *
 * @return InboxAds_API
 */
function inboxads_api() {
	return InboxAds_API::get_instance();
}

/**
 * Return array of ad zone sizes in "formatID => name" form.
 *
 * @return array
 */
function inboxads_formats() {
	$formats = array();

	foreach ( inboxads_api()->get_formats() as $format ) {
		$formats[ $format['formatID'] ] = $format['name'];
	}

	return $formats;
}

function inboxads_replace_zone_tags( $code ) {
	$replacements = apply_filters(
		'wpr_inboxads_zone_tags',
		array(
			'{{timestamp}}' => current_time( 'YmdHis' ),
		)
	);

	$site_domain = apply_filters( 'wpr_inboxads_site_domain', basename( get_option( 'siteurl' ) ) );

	$replacements[ '?z=' ] = '&z=';
	
	if ( get_option('permalink_structure') ) {
		$replacements[ 'http://1box.' . $site_domain ] = get_site_url() . '/inboxads';
		$replacements[ '/&z=' ] = '/z__';
		$replacements[ '&o=' ] = '/o__';
		$replacements[ '&u=' ] = '/u__';
		$replacements[ '&t=' ] = '/t__';
	} else {
		$replacements[ 'http://1box.' . $site_domain ] = get_site_url() . '/?inboxads=';
	}

	$find    = array();
	$replace = array();
	foreach ( $replacements as $_find => $_replace ) {
		$find[]    = $_find;
		$replace[] = $_replace;
	}

	return str_replace( $find, $replace, $code );
}

/**
 * Return code for ad zone.
 *
 * @param string $newsletter
 * @param array  $options
 * @return string
 */
function inboxads_display_zone( $newsletter, $options ) {
	$newsletters = get_option( 'wpr_inboxads_newsletters', array() );

	if ( ! is_array( $newsletters ) || empty( $newsletters[ $newsletter ] ) ) {
		// Newsletter for this plugin not found.
		$zone_errors = get_option( 'wpr_inboxads_zone_errors', array() );
		if ( empty( $zone_errors ) || ! is_array( $zone_errors ) ) {
			$zone_errors = array();
		}
		$error = sprintf(
			/* translators: %s: Newsletter name. */
			esc_html__( 'Ad zone could not be displayed. Please make sure a newsletter is created for the "%s" plugin under Tools > InboxAds!', 'wpr' ),
			$newsletter
		);
		if ( ! in_array( $error, $zone_errors ) ) {
			$zone_errors[] = $error;
			update_option( 'wpr_inboxads_zone_errors', $zone_errors );
		}

		// Do not output anything.
		return '';
	}

	$code = inboxads_api()->get_code(
		array(
			'name'         => sanitize_text_field( $options['name'] ),
			'formatID'     => absint( $options['size'] ),
			'newsletterID' => $newsletters[ $newsletter ],
		)
	);

	// Filter the zone code.
	$code = apply_filters( 'wpr_inboxads_zone_code', $code, $newsletter, $options );

	// Replace placeholder tags.
	return inboxads_replace_zone_tags( $code );
}
