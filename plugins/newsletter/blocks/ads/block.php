<?php
/*
 * Name: inboxAds
 * Section: content
 * Description: InboxAds block
 */

namespace WPR;

/* @var $options array */
/* @var $wpdb wpdb */

$default_size = array_keys( inboxads_formats() )[10];

$default_options = array(
	'size' => $default_size,
	'name' => '',
);

$options = array_merge( $default_options, $options );

if ($options['name'] != '') {
	echo inboxads_display_zone( 'Newsletter', $options );
} else {
	echo '<div></div>';
}
