<?php

/**
 * InboxAds API Class.
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
 * Class which handles API communication.
 */
class InboxAds_API {

	/**
	 * Class instance.
	 *
	 * @var InboxAds_API
	 */
	private static $instance;

	/**
	 * Username and password for the API.
	 *
	 * @var array
	 */
	private $credentials;

	/**
	 * API bearer token.
	 *
	 * @var string
	 */
	private $token;

	/**
	 * API endpoint base URL.
	 *
	 * @var string
	 */
	private $url = 'http://fabric.inboxads.com/';

	/**
	 * Authentication request status code.
	 *
	 * @var int
	 */
	public $auth_code;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->token = get_option( 'wpr_inboxads_account_connected', false );

		$this->maybe_authenticate();
	}

	/**
	 * Get class instance.
	 *
	 * @return InboxAds_API
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new InboxAds_API();
		}

		return self::$instance;
	}

	/**
	 * Set credentials for connecting to the API.
	 *
	 * @param array $credentials
	 */
	public function set_credentials( $credentials = array() ) {
		if ( empty( $credentials ) ) {
			$credentials = get_option( 'wpr_inboxads_account', array() );
		}

		$this->credentials = wp_parse_args(
			$credentials,
			array(
				'username' => '',
				'password' => '',
			)
		);

		$this->maybe_authenticate();
	}

	/**
	 * Disconnect account. Unset credentials and token.
	 */
	public function disconnect_account() {
		$this->credentials = array(
			'username' => '',
			'password' => '',
		);

		$this->token = null;
	}

	/**
	 * Check if the account is connected (token is available).
	 *
	 * @return bool
	 */
	public function is_connected() {
		return ! empty( $this->token );
	}

	/**
	 * Register new account.
	 * 
	 * @param array $form
	 */
	public function register( $form = array() ) {
		$data = array(
			'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
			'body' => wp_json_encode($form)
		);

		$res = wp_remote_post(
			$this->url . 'customer/register',
			$data
		);

		return $res['body'];
	}

	/**
	 * Authenticate if no API token is saved.
	 */
	public function maybe_authenticate() {
		if ( $this->is_connected() || empty( $this->credentials ) ) {
			return;
		}

		$data = array(
			'body' => http_build_query(
				array(
					'Username' => $this->credentials['username'],
					'Password' => $this->credentials['password'],
				),
				'',
				'&'
			),
		);

		$res = wp_remote_post(
			$this->url . 'authentication/login',
			$data
		);

		$this->auth_code = ( is_wp_error( $res ) || empty( $res['response']['code'] ) ) ? 400 : $res['response']['code'];

		if ( 200 == $this->auth_code ) {
			// Login successful.
			$data = json_decode( $res['body'], true );

			$this->token = ( isset( $data['access_token'] ) ) ? $data['access_token'] : '';

			update_option( 'wpr_inboxads_account_connected', $this->token );
		} else {
			// Login failed.
			$this->token = null;

			delete_option( 'wpr_inboxads_account_connected' );
		}
	}

	/**
	 * Perform API request.
	 *
	 * @param string $method
	 * @param string $endpoint
	 * @param array $data
	 * @return \WPR\CurlResponse|bool
	 */
	public function do_call( $method, $endpoint, $data = array() ) {
		if ( empty( $this->token ) ) {
			// Not connected to API.
			return false;
		}

		if ( 'POST' === $method ) {
			$data = wp_json_encode( $data );
		}

		return wp_remote_request(
			$this->url . $endpoint,
			array(
				'method'  => $method,
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->token,
					'Content-Type'  => 'application/json',
				),
				'body'    => $data,
			)
		);
	}

	/**
	 * Create or update a newsletter.
	 *
	 * @param array $newsletter {
	 *    Array of attributes for the newsletter:
	 *    @type string "name"
	 *    @type string "domain"
	 * }
	 * @return bool
	 */
	public function create_newsletter( $newsletter ) {
		if ( empty( $newsletter['name'] ) || empty( $newsletter['domain'] ) ) {
			return false;
		}

		$res = $this->do_call(
			'POST',
			'newsletter',
			$newsletter
		);

		return ( ! empty( $res['response']['code'] ) && 200 == $res['response']['code'] );
	}

	/**
	 * Return array of registered newsletters.
	 *
	 * @param string $from
	 * @param string $to
	 * @return array
	 */
	public function list_newsletters( $from = '', $to = '' ) {
		$res = $this->do_call(
			'GET',
			'newsletter/list',
			array(
				'From' => $from,
				'To'   => $to,
			)
		);

		return ( ! empty( $res['response']['code'] ) && 200 == $res['response']['code'] ) ? json_decode( $res['body'], true ) : array();
	}

	/**
	 * Get list of ad zone formats.
	 *
	 * @return array
	 */
	public function get_formats() {
		// Get formats from cache.
		$formats = get_transient( 'wpr_inboxads_formats' );

		if ( ! $formats || ! is_array( $formats ) ) {
			// Retrieve formats from API.
			$res = $this->do_call(
				'GET',
				'format/list'
			);

			if ( ! empty( $res['response']['code'] ) && 200 == $res['response']['code'] ) {
				$formats = json_decode( $res['body'], true );

				// Cache format list for a week.
				set_transient( 'wpr_inboxads_formats', $formats, 60 * 60 * 24 * 7 );
			} else {
				// Fallback formats.
				$formats = array(
					array(
						'formatID' => 26,
						'name'     => '160 x 600 - Small Skyscraper',
						'width'    => 160,
						'height'   => 600,
					),
					array(
						'formatID' => 24,
						'name'     => '300 x 1050 - Large Skyscraper',
						'width'    => 300,
						'height'   => 1050,
					),
					array(
						'formatID' => 2,
						'name'     => '300 x 250 - Medium Rectangle',
						'width'    => 300,
						'height'   => 250,
					),
					array(
						'formatID' => 25,
						'name'     => '300 x 600 - Skyscraper',
						'width'    => 300,
						'height'   => 600,
					),
					array(
						'formatID' => 27,
						'name'     => '320 x 50 - Mobile Leaderboard',
						'width'    => 320,
						'height'   => 50,
					),
					array(
						'formatID' => 21,
						'name'     => '336 x 280 - Large Rectangle',
						'width'    => 336,
						'height'   => 280,
					),
					array(
						'formatID' => 22,
						'name'     => '600 x 155 - Medium Leaderboard',
						'width'    => 600,
						'height'   => 155,
					),
					array(
						'formatID' => 10,
						'name'     => '600 x 300 - 4 Creatives',
						'width'    => 200,
						'height'   => 0,
					),
					array(
						'formatID' => 4,
						'name'     => '728 x 90 - Leaderboard',
						'width'    => 728,
						'height'   => 90,
					),
					array(
						'formatID' => 3,
						'name'     => '970 x 250 - Large Leaderboard',
						'width'    => 970,
						'height'   => 250,
					),
					array(
						'formatID' => 23,
						'name'     => '970 x 90 - Large Leaderboard',
						'width'    => 970,
						'height'   => 90,
					),
				);

			}
		}

		return $formats;
	}

	/**
	 * Retrieve an ad zone's code.
	 *
	 * @param array $zone {
	 *    Array of attributes for the zone:
	 *    @type string "zoneID"
	 *    @type string "name"
	 *    @type int "formatID"
	 *    @type string "newsletterID"
	 * }
	 * @return string
	 */
	public function get_code( $zone ) {
		// Generate hash from zone attributes.
		$hash = md5( serialize( $zone ) );

		// Get code from DB if it exists.
		$code = get_option( 'wpr_inboxads_zone_' . $hash, '' );
		if ( ! empty( $code ) ) {
			return $code;
		}

		// Get saved hashes.
		$hashes = get_option( 'wpr_inboxads_zone_hashes', array() );
		if ( ! is_array( $hashes ) ) {
			$hashes = array();
		}

		$zone_id = null;

		if ( ! array_key_exists( $hash, $hashes ) ) {
			// Create a new ad zone.
			$created = $this->create_zone( $zone );

			if ( $created ) {
				// Get the zone ID from the API.
				$zone = $this->get_zone( $zone );

				if ( $zone ) {
					$zone_id = $zone['zoneID'];

					// Save the hash value in the DB.
					$hashes[ $hash ] = $zone['zoneID'];
					update_option( 'wpr_inboxads_zone_hashes', $hashes );
				}
			}
		} else {
			// Ad zone already created.
			$zone_id = $hashes[ $hash ];
		}

		if ( ! $zone_id ) {
			return '';
		}

		// Retrieve ad zone code from API.
		$res = $this->do_call(
			'GET',
			'code/' . $zone_id
		);

		if ( ! empty( $res['response']['code'] ) && 200 == $res['response']['code'] ) {
			$code = $res['body'];

			if ( ! empty( $code ) ) {
				// Save code in the DB.
				update_option( 'wpr_inboxads_zone_' . $hash, $code );
			}
		}

		return $code;
	}

	/**
	 * Retrieve an ad zone by its properties.
	 *
	 * @param array $zone {
	 *    Array of attributes for the zone:
	 *    @type string "name"
	 *    @type int "formatID"
	 *    @type string "newsletterID"
	 * }
	 * @return array|false
	 */
	public function get_zone( $zone ) {
		$data = false;

		$res = $this->do_call(
			'GET',
			'zone/list'
		);

		if ( ! empty( $res['response']['code'] ) && 200 == $res['response']['code'] ) {
			$zones = json_decode( $res['body'], true );

			foreach ( $zones as $_zone ) {
				foreach ( $zone as $prop => $val ) {
					if ( $val != $_zone[ $prop ] ) {
						// Not the zone we're looking for.
						continue 2;
					}
				}

				$data = $_zone;
				break;
			}
		}

		return $data;
	}

	/**
	 * Get an ad zone by ID.
	 *
	 * @param array $id
	 * @return array|false
	 */
	public function get_zone_by_id( $id ) {
		$res = $this->do_call(
			'GET',
			'zone/' . $id
		);

		return ( ! empty( $res['response']['code'] ) && 200 == $res['response']['code'] ) ? json_decode( $res['body'], true ) : false;
	}

	/**
	 * Create or update an ad zone.
	 *
	 * @param array $zone {
	 *    @type string "zoneID"
	 *    @type string "name"
	 *    @type int "formatID"
	 *    @type string "newsletterID"
	 * }
	 * @return bool
	 */
	public function create_zone( $zone ) {
		if ( empty( $zone['name'] ) || empty( $zone['newsletterID'] ) || empty( $zone['formatID'] ) ) {
			return false;
		}

		$res = $this->do_call(
			'POST',
			'zone',
			$zone
		);

		return ( ! empty( $res['response']['code'] ) && 200 == $res['response']['code'] );
	}

	/**
	 * Return array of stats data.
	 *
	 * @return array
	 */
	public function get_stats() {
		// Attributes.
		$atts = array(
			'views',
			'revenue',
		);

		// Stats.
		$stats = array(
			'date'   => array(),
			'totals' => array(),
		);
		foreach ( $atts as $stat ) {
			$stats[ $stat ]           = array();
			$stats['totals'][ $stat ] = 0;
		}

		// If no account is connected, return empty data and delete cached stats.
		if ( ! $this->is_connected() ) {
			delete_transient( 'wpr_inboxads_stats' );
			return $stats;
		}

		// Get cached stats, if available.
		$cached_stats = get_transient( 'wpr_inboxads_stats' );
		if ( ! empty( $cached_stats ) && is_array( $cached_stats ) ) {
			return $cached_stats;
		}

		// Caching flag.
		$cache_results = false;

		// Current site domain.
		$site_domain = apply_filters( 'wpr_inboxads_site_domain', basename( get_option( 'siteurl' ) ) );

		$date_format = 'Y-m-d';

		// Get current date object.
		$date_obj = new \DateTime( current_time( $date_format ) );

		// Create array of dates to query (last 7 days).
		$dates_query = array(
			$date_obj->format( $date_format ),
		);
		for ( $i = 0; $i < 7; $i++ ) {
			$date_past = $date_obj->modify( '-1 day' );
			// Add past day to array of dates to query.
			$dates_query[]   = $date_past->format( $date_format );
			$stats['date'][] = $date_past->format( 'd M' );
		}

		// Add stats from each newsletter.
		foreach ( $dates_query as $index => $date ) {
			if ( 0 === $index ) {
				// Skip current day.
				continue;
			}

			// Get 0-based index.
			$date_index = $index - 1;

			// Set default value.
			foreach ( $atts as $stat ) {
				$stats[ $stat ][ $date_index ] = 0;
			}

			// Get newsletters for current interval.
			$newsletters = $this->list_newsletters( $date, $date );

			if ( empty( $newsletters ) ) {
				continue;
			}

			// Enable caching results.
			$cache_results = true;

			foreach ( $newsletters as $newsletter ) {
				if ( $site_domain !== $newsletter['domain'] ) {
					continue;
				}

				foreach ( $atts as $stat ) {
					$stat_value = ( isset( $newsletter[ $stat ] ) ) ? absint( $newsletter[ $stat ] ) : 0;

					// Add the stat's value to the current date key.
					$stats[ $stat ][ $date_index ] += $stat_value;

					// Add the stat's value to the totals.
					$stats['totals'][ $stat ] += $stat_value;
				}
			}
		}

		// Reverse array.
		$stats = array_map(
			function( $array ) {
				return array_reverse( $array );
			},
			$stats
		);

		// Cache results for 12 hours.
		if ( $cache_results ) {
			set_transient( 'wpr_inboxads_stats', $stats, 60 * 60 * 12 );
		}

		return $stats;
	}
}
