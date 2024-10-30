<?php

/**
 * Plugin Name: inboxAds - Monetize Your Emails and Newsletters in Minutes
 * Plugin URI:  https://inboxads.com/#more
 * Description: inboxAds - Generate extra revenue for your business every time you send out a newsletter or other mass mailing with inboxAds.
 * Author:      inboxAds
 * Author URI:  https://inboxads.com/
 * License:     GPL v3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Version:     1.0.19
 * Textdomain:  wpr
 */

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit;
}

// Define constants for plugin base path and URL.
define( 'WPR_ADS_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPR_ADS_URI', plugin_dir_url( __FILE__ ) );

/**
 * Perform plugin activation actions.
 */
register_activation_hook( __FILE__, 'wpr_inboxads_activate' );
function wpr_inboxads_activate() {
	// Check if an InboxAds account is connected.
	$connected = get_option( 'wpr_inboxads_account_connected', false );

	if ( empty( $connected ) ) {
		// Add activation redirect flag.
		add_option( 'wpr_inboxads_do_activation_redirect', true );
	}

	add_option( 'inboxads_flush_rewrite_rules_flag', true );
}

add_action( 'init', 'inboxads_api_rule' );
function inboxads_api_rule(){
    add_rewrite_rule( 'inboxads/(.*)', 'index.php?inboxadsp=$matches[1]', 'top' );
	
	if ( get_option( 'inboxads_flush_rewrite_rules_flag' ) ) {
        flush_rewrite_rules();
        delete_option( 'inboxads_flush_rewrite_rules_flag' );
    }
}

add_filter( 'query_vars', 'inboxads_query_vars' );
function inboxads_query_vars( $query_vars )
{
    $query_vars[] = 'inboxadsp';
    $query_vars[] = 'inboxads';
    $query_vars[] = 'z';
    $query_vars[] = 'u';
    $query_vars[] = 't';
    $query_vars[] = 'o';
    return $query_vars;
}

add_action( 'parse_request', 'inboxads_parse_request' );
function inboxads_parse_request( &$wp )
{
	$query_vars = [];
	
    if ( array_key_exists( 'inboxads', $wp->query_vars ) ) {
		$query_vars = $wp->query_vars;
	}
	
	if ( array_key_exists( 'inboxadsp', $wp->query_vars ) ) {
		$qv = 'inboxads=/' . str_replace(['/z__', '/o__', '/u__', '/t__'], ['/&z=', '&o=', '&u=', '&t='], $wp->query_vars['inboxadsp']);
		parse_str($qv, $query_vars);
	}
	
	if (!empty($query_vars)) {
		$url = 'http://1box.emaildisplay.com' 
			. $query_vars['inboxads'] 
			. '?z=' . $query_vars['z'] 
			. '&o=' . ($query_vars['o'] ?? 0) 
			. '&u=' . $query_vars['u'] 
			. '&t=' . $query_vars['t'];
		
		header('Location: ' . $url, true, 307);
        exit();
	}

    return;
}

// Load includes.
require_once WPR_ADS_PATH . 'inc/helpers.php';
require_once WPR_ADS_PATH . 'inc/class-inboxads-api.php';
require_once WPR_ADS_PATH . 'inc/class-inboxads-admin.php';

// Load plugins.
require_once WPR_ADS_PATH . 'plugins/class-inboxads-plugin.php';
require_once WPR_ADS_PATH . 'plugins/alo-easymail/class-inboxads-plugin-alo-easymail.php';
require_once WPR_ADS_PATH . 'plugins/mailpoet/class-inboxads-plugin-mailpoet.php';
require_once WPR_ADS_PATH . 'plugins/mailster/class-inboxads-plugin-mailster.php';
require_once WPR_ADS_PATH . 'plugins/newsletter/class-inboxads-plugin-newsletter.php';
require_once WPR_ADS_PATH . 'plugins/sendpress/class-inboxads-plugin-sendpress.php';
require_once WPR_ADS_PATH . 'plugins/shortcode-builder/class-inboxads-shortcode-builder.php';
