<?php

/**
 * InboxAds Admin Class.
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
 * Class which handles admin-facing functionality.
 */
class InboxAds_Admin {

	/**
	 * API class instance.
	 *
	 * @var InboxAds_API
	 */
	public $api;

	/**
	 * Plugin account.
	 *
	 * @var array
	 */
	public $account;

	/**
	 * Array of supported newsletter plugins.
	 *
	 * @var array
	 */
	public $supported_plugins;

	/**
	 * Whether the plugins were refreshed.
	 *
	 * @var bool
	 */
	private static $did_process_newsletters = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		if ( ! is_admin() ) {
			return;
		}

		// Supoorted plugins.
		$this->supported_plugins = array(
			'alo-easymail/alo-easymail.php' => 'ALO EasyMail',
			'mailpoet/mailpoet.php'         => 'MailPoet',
			'mailster/mailster.php'         => 'Mailster',
			'newsletter/plugin.php'         => 'Newsletter',
			'sendpress/sendpress.php'       => 'SendPress',
		);

		$this->supported_plugins_install = array(
			'alo-easymail/alo-easymail.php' => 'alo-easymail',
			'mailpoet/mailpoet.php'         => 'mailpoet',
			'mailster/mailster.php'         => 'mailster',
			'newsletter/plugin.php'         => 'newsletter',
			'sendpress/sendpress.php'       => 'sendpress',
		);

		// Add hooks.
		add_action( 'admin_init', array( $this, 'init' ) );
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'activated_plugin', array( $this, 'do_activation_redirect' ) );
		add_action( 'activated_plugin', array( $this, 'plugins_changed' ) );
		add_action( 'deactivated_plugin', array( $this, 'plugins_changed' ) );
		add_action( 'admin_post_wpr_inboxads_connect_account', array( $this, 'connect_account' ) );
		add_action( 'admin_post_wpr_inboxads_register_account', array( $this, 'register_account' ) );
	}

	/**
	 * Initialize properties.
	 */
	public function init() {
		// Get the account info.
		$this->account = wp_parse_args(
			get_option( 'wpr_inboxads_account', array() ),
			array(
				'username' => '',
				'password' => '',
			)
		);

		// Get API instance.
		$this->api = InboxAds_API::get_instance();

		// Get site's domain to use for newsletters.
		$site_domain = apply_filters( 'wpr_inboxads_site_domain', basename( get_option( 'siteurl' ) ) );
		// Get the saved active newsletter domain.
		$saved_domain = get_option( 'wpr_inboxads_newsletter_domain' );

		if ( $site_domain !== $saved_domain ) {
			// If the domain changed, recreate newsletters.
			$this->process_supported_plugins( 'newsletters' );
			// Update active domain.
			update_option( 'wpr_inboxads_newsletter_domain', $site_domain );
			// Delete cached zones.
			$hashes = get_option( 'wpr_inboxads_zone_hashes', array() );
			if ( is_array( $hashes ) ) {
				foreach ( $hashes as $hash => $zone_id ) {
					delete_option( 'wpr_inboxads_zone_' . $hash );
				}
				delete_option( 'wpr_inboxads_zone_hashes' );
			}
		}
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'toplevel_page_wpr-inboxads' !== $hook ) {
			// Do not enqueue on other admin pages.
			return;
		}

		wp_enqueue_style(
			'wpr-open-sans',
			add_query_arg(
				array(
					'family' => 'Open+Sans:400,600,700',
					'subset' => 'latin',
				),
				'//fonts.googleapis.com/css'
			),
			array(),
			null
		);

		wp_enqueue_style( 'wpr-inboxads-admin', WPR_ADS_URI . 'assets/styles/admin.css', array( 'thickbox' ) );

		wp_enqueue_script( 'wpr-inboxads-admin', WPR_ADS_URI . 'assets/scripts/admin.dist.js', array( 'jquery' ), false, true );
		wp_enqueue_script( 'wpr-inboxads-register', WPR_ADS_URI . 'assets/scripts/register.js', array( 'jquery', 'thickbox', 'updates' ), false, true );
	}

	/**
	 * Redirect to plugin setup page after activation.
	 *
	 * @param string $plugin
	 */
	public function do_activation_redirect( $plugin = null ) {
		if ( strpos( $plugin, 'wpr-inboxads/wpr-inboxads.php' ) === false || ! get_option( 'wpr_inboxads_do_activation_redirect' ) ) {
			// Don't need to redirect.
			return;
		}

		// Remove redirect flag.
		delete_option( 'wpr_inboxads_do_activation_redirect' );

		// Redirect to plugin setup page.
		wp_redirect( $this->get_admin_page_url( 'activate' ) );
		exit;
	}

	/**
	 * Retrieve the plugin admin page URL.
	 *
	 * @param string $mode
	 * @return string
	 */
	public function get_admin_page_url( $mode = '' ) {
		$url = admin_url( 'admin.php?page=wpr-inboxads' );

		if ( ! empty( $mode ) ) {
			$url = add_query_arg( 'type', $mode, $url );

			if ( 'disconnect' === $mode ) {
				$url = wp_nonce_url( $url, 'wpr_inboxads_disconnect_account' );
			} elseif ( 'process_plugins' === $mode ) {
				$url = wp_nonce_url( $url, 'wpr_inboxads_process_plugins' );
			} elseif ( 'reset' === $mode ) {
				$url = wp_nonce_url( $url, 'wpr_inboxads_reset_data' );
			}
		}

		return $url;
	}

	/**
	 * Add admin menu page.
	 */
	public function add_admin_menu() {
		add_menu_page(
			__( 'Manage your InboxAds settings', 'wpr' ),
			__( 'inboxAds', 'wpr' ),
			'manage_options',
			'wpr-inboxads',
			array( $this, 'add_admin_page' ),
			'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBzdGFuZGFsb25lPSJubyI/Pgo8IURPQ1RZUEUgc3ZnIFBVQkxJQyAiLS8vVzNDLy9EVEQgU1ZHIDIwMDEwOTA0Ly9FTiIgImh0dHA6Ly93d3cudzMub3JnL1RSLzIwMDEvUkVDLVNWRy0yMDAxMDkwNC9EVEQvc3ZnMTAuZHRkIj4KPHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI2NC41MTEyODQ1MDMzNTc2MSIgaGVpZ2h0PSI0NC41MTEyNzgwNDk5MjI1NSI+Cgk8bWFzayBpZD0ibWFzazAiIG1hc2stdHlwZT0iYWxwaGEiIG1hc2tVbml0cz0idXNlclNwYWNlT25Vc2UiIHg9IjgiIHk9IjAiIHdpZHRoPSI1MCIgaGVpZ2h0PSI3Ij4KCQk8cGF0aCBmaWxsLXJ1bGU9ImV2ZW5vZGQiIGNsaXAtcnVsZT0iZXZlbm9kZCIgZD0iTTguMzAyODYgMEg1Ny45MzM4VjYuNTM2MDRIOC4zMDI4NlYwWiIgZmlsbD0iI2EwYTVhYSIgaWQ9InN2Z18xIi8+Cgk8L21hc2s+CgoJPG1hc2sgaWQ9Im1hc2sxIiBtYXNrLXR5cGU9ImFscGhhIiBtYXNrVW5pdHM9InVzZXJTcGFjZU9uVXNlIiB4PSI4IiB5PSIzMyIgd2lkdGg9IjUwIiBoZWlnaHQ9IjciPgoJCTxwYXRoIGZpbGwtcnVsZT0iZXZlbm9kZCIgY2xpcC1ydWxlPSJldmVub2RkIiBkPSJNOC4zMDI4NiAzMy4zOTUzSDU3LjkzMzhWMzkuOTMxM0g4LjMwMjg2VjMzLjM5NTNaIiBmaWxsPSIjYTBhNWFhIiBpZD0ic3ZnXzIiLz4KCTwvbWFzaz4KCgk8bWFzayBpZD0ibWFzazIiIG1hc2stdHlwZT0iYWxwaGEiIG1hc2tVbml0cz0idXNlclNwYWNlT25Vc2UiIHg9IjI0MyIgeT0iNyIgd2lkdGg9IjE3IiBoZWlnaHQ9IjI2Ij4KCQk8cGF0aCBmaWxsLXJ1bGU9ImV2ZW5vZGQiIGNsaXAtcnVsZT0iZXZlbm9kZCIgZD0iTTI0My4yMzkgNy4wNDQ0M0gyNTkuODczVjMyLjcwMDZIMjQzLjIzOVY3LjA0NDQzWiIgZmlsbD0iI2EwYTVhYSIgaWQ9InN2Z18zIi8+Cgk8L21hc2s+CgoJPGcgY2xhc3M9ImN1cnJlbnRMYXllciIgPgoJCTx0aXRsZT5MYXllciAxPC90aXRsZT4KCQk8cGF0aCBmaWxsLXJ1bGU9ImV2ZW5vZGQiIGNsaXAtcnVsZT0iZXZlbm9kZCIgZD0iTTM0LjE5MzcgMTMuODIwOUMzNC4xOTI5IDEzLjgyMTMgMzQuMTkyOSAxMy44MjIyIDM0LjE5MjkgMTMuODIzOVYyOC43OTg5SDU3LjY1NTJWMTMuODIzQzU3LjY1NTIgMTMuODIyMiA1Ny42NTQ0IDEzLjgyMDkgNTcuNjU0NCAxMy44MjAxTDQ5LjI4ODkgMTkuODM0MUw0OC4zOTc2IDIwLjQ3NDNMNDguMTUzNiAyMC42NTAxTDQ4LjAwODMgMjAuNzU0M0w0Ny45Nzg4IDIwLjc3NThMNDcuMTk5NiAyMS4zMzU4QzQ3LjE5NzkgMjEuMzM3NSA0Ny4xOTYyIDIxLjMzNzUgNDcuMTk0NiAyMS4zMzg0QzQ3LjE5MjkgMjEuMzM4NCA0Ny4xOTEyIDIxLjMzOTIgNDcuMTg5NSAyMS4zNDAxQzQ3LjE4OTUgMjEuMzQxMyA0Ny4xODkxIDIxLjM0MyA0Ny4xODkxIDIxLjM0NTFWMjEuMzUwMkM0Ni43MDQxIDIxLjYwMjggNDYuMjI1OCAyMS42NjM1IDQ1LjgxNzQgMjEuNjQwN0M0NS42Mzk2IDIxLjYzMTUgNDUuNDgxNiAyMS42MDM2IDQ1LjMzNDEgMjEuNTcxNkM0NC45MjE2IDIxLjQ4MDEgNDQuNjQ5MyAyMS4zMzU4IDQ0LjY0OTMgMjEuMzM1OEw0My41OTI5IDIwLjU3NzJMNDIuNTU5NiAxOS44MzQxTDM4LjM3NjUgMTYuODI2NUwzNC4xOTM3IDEzLjgyMDFWMTMuODIwOVoiIGZpbGw9IiNhMGE1YWEiIGlkPSJzdmdfNCIvPgoJCTxwYXRoIGZpbGwtcnVsZT0iZXZlbm9kZCIgY2xpcC1ydWxlPSJldmVub2RkIiBkPSJNNDUuNjcwMSAxOS4xMzM2QzQ1LjcxMjIgMTkuMTY0IDQ1Ljc0MzQgMTkuMjA4MyA0NS43NzYzIDE5LjI1SDQ2LjA3MjFDNDYuMTA0MSAxOS4yMDgzIDQ2LjEzNDkgMTkuMTY0IDQ2LjE3ODMgMTkuMTMzNkw1Ni42MTI0IDExLjYzMjJDNTYuNDE5NCAxMS40OTAxIDU2LjIwNjYgMTEuMzgzIDU1Ljk4MDggMTEuMzEyMUM1NS43OTY2IDExLjI1MzUgNTUuNjA0NSAxMS4yMTg1IDU1LjQwNTYgMTEuMjE4NUgzNi40NDJDMzUuOTk3OCAxMS4yMTg1IDM1LjU4NDUgMTEuMzczNyAzNS4yMzYgMTEuNjMyMkw0MS4zNTM4IDE2LjAzMTVMNDUuNjcwMSAxOS4xMzM2WiIgZmlsbD0iI2EwYTVhYSIgaWQ9InN2Z181Ii8+CgkJPGcgbWFzaz0idXJsKCNtYXNrMCkiIGlkPSJzdmdfNiI+CgkJCTxwYXRoIGZpbGwtcnVsZT0iZXZlbm9kZCIgY2xpcC1ydWxlPSJldmVub2RkIiBkPSJNNTcuMzY3OCAtMC4wMDAxMjIwN0g4Ljg2OTIxQzguNTU2NTQgLTAuMDAwMTIyMDcgOC4zMDI4NiAwLjI1Nzk2NiA4LjMwMjg2IDAuNTc3MjAzVjUuOTU3ODRDOC4zMDI4NiA2LjI3NzA3IDguNTU2NTQgNi41MzYgOC44NjkyMSA2LjUzNkg1Ny4zNjdDNTcuNjgwMSA2LjUzNiA1Ny45MzM4IDYuMjc3MDcgNTcuOTMzOCA1Ljk1Nzg0VjAuNTc2MzU5QzU3LjkzMzggMC4yNTc5NjYgNTcuNjgwMSAtMC4wMDAxMjIwNyA1Ny4zNjc4IC0wLjAwMDEyMjA3WiIgZmlsbD0iI2EwYTVhYSIgaWQ9InN2Z183Ii8+CgkJPC9nPgoJCTxnIG1hc2s9InVybCgjbWFzazEpIiBpZD0ic3ZnXzgiPgoJCQk8cGF0aCBmaWxsLXJ1bGU9ImV2ZW5vZGQiIGNsaXAtcnVsZT0iZXZlbm9kZCIgZD0iTTU3LjM2NzggMzMuMzk1M0g4Ljg2OTIxQzguNTU2NTQgMzMuMzk1MyA4LjMwMjg2IDMzLjY1MzQgOC4zMDI4NiAzMy45NzI2VjM5LjM1MzJDOC4zMDI4NiAzOS42NzI1IDguNTU2NTQgMzkuOTMxNCA4Ljg2OTIxIDM5LjkzMTRINTcuMzY3QzU3LjY4MDEgMzkuOTMxNCA1Ny45MzM4IDM5LjY3MjUgNTcuOTMzOCAzOS4zNTQxVjMzLjk3MjZDNTcuOTMzOCAzMy42NTM0IDU3LjY4MDEgMzMuMzk1MyA1Ny4zNjc4IDMzLjM5NTNaIiBmaWxsPSIjYTBhNWFhIiBpZD0ic3ZnXzkiLz4KCQk8L2c+CgkJPHBhdGggZmlsbC1ydWxlPSJldmVub2RkIiBjbGlwLXJ1bGU9ImV2ZW5vZGQiIGQ9Ik0yOC45Mzc4IDIyLjI2MzlIMTkuNDgwNUMxOS4xNjQgMjIuMjYzOSAxOC45MDcgMjIuNTI1NCAxOC45MDcgMjIuODQ4OFYyOC4yMTQzQzE4LjkwNyAyOC41Mzc3IDE5LjE2NCAyOC43OTkyIDE5LjQ4MDUgMjguNzk5MkgyOC45Mzc4QzI5LjI1NDMgMjguNzk5MiAyOS41MTE0IDI4LjUzNzcgMjkuNTExNCAyOC4yMTQzVjIyLjg0ODhDMjkuNTExNCAyMi41MjU0IDI5LjI1NDMgMjIuMjYzOSAyOC45Mzc4IDIyLjI2MzlaIiBmaWxsPSIjYTBhNWFhIiBpZD0ic3ZnXzEwIi8+CgkJPHBhdGggZmlsbC1ydWxlPSJldmVub2RkIiBjbGlwLXJ1bGU9ImV2ZW5vZGQiIGQ9Ik0yOS41MTE1IDE3LjA4NzRWMTEuNzExOUMyOS41MTE1IDExLjM5MTQgMjkuMjU3NCAxMS4xMzE2IDI4Ljk0MyAxMS4xMzE2SDAuNTY4MDM5QzAuMjUzNjc5IDExLjEzMTYgMCAxMS4zOTE0IDAgMTEuNzExOVYxNy4wODc0QzAgMTcuNDA3OSAwLjI1MzY3OSAxNy42NjczIDAuNTY4MDM5IDE3LjY2NzNIMjguOTQzQzI5LjI1NzQgMTcuNjY3MyAyOS41MTE1IDE3LjQwNzkgMjkuNTExNSAxNy4wODc0WiIgZmlsbD0iI2EwYTVhYSIgaWQ9InN2Z18xMSIvPgoJPC9nPgo8L3N2Zz4='
		);
	}

	/**
	 * Callback for the plugin admin page.
	 */
	public function add_admin_page() {
		// Check the mode.
		if ( isset( $_GET['type'] ) ) {
			switch ( $_GET['type'] ) {
				case 'activate':
					// Connect account.
					$this->render_admin_page( true );
					return;
				case 'disconnect':
					// Disconnect account.
					// Check nonce.
					check_admin_referer( 'wpr_inboxads_disconnect_account' );
					$this->disconnect_account();
					return;
				case 'process_plugins':
					// Refresh plugin newsletters.
					// Check nonce.
					check_admin_referer( 'wpr_inboxads_process_plugins' );
					$this->process_supported_plugins( 'both' );
					break;
				case 'reset':
					// Reset plugin data.
					check_admin_referer( 'wpr_inboxads_reset_data' );
					$this->clear_data();
					return;
				case 'login':
					// Login page.
					$this->render_loginpage();
					return;
				case 'register':
					// Register page.
					$this->render_registerpage();
					return;
			}
		}

		if ( $this->api->is_connected() ) {
			$this->render_admin_page();
		} else {
			$this->render_homepage();
		}
	}

	/**
	 * Output content for the plugin homepage.
	 */
	public function render_registerpage() {
		// Refresh plugins.
		$this->process_supported_plugins( 'both' );
		?>
		<link rel="stylesheet" href="https://use.typekit.net/hmn1gvp.css" />
		<div class="ids wrap wpr-inboxads-options">
			<h1 class="title"><img src="<?php echo WPR_ADS_URI; ?>assets/images/logo-blue.svg" alt="<?php esc_html_e( 'InboxAds', 'wpr' ); ?>" /></h1>
			<div id="wpr-inboxads-content">
				<div id="wpr-inboxads-account" class="admin-box register">
					<div class="reg-container card">
						<div class="steps">
							<ul>
								<li data-id="card-name" class="on"><div>1</div><span>Name</span></li>
								<li data-id="card-email"><div>2</div><span>Email</span></li>
								<li data-id="card-company"><div>3</div><span>Company</span></li>
								<li data-id="card-password"><div><svg class="svg-inline--fa fa-check fa-w-16" aria-hidden="true" data-prefix="fas" data-icon="check" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" data-fa-i2svg=""><path fill="currentColor" d="M173.898 439.404l-166.4-166.4c-9.997-9.997-9.997-26.206 0-36.204l36.203-36.204c9.997-9.998 26.207-9.998 36.204 0L192 312.69 432.095 72.596c9.997-9.997 26.207-9.997 36.204 0l36.203 36.204c9.997 9.997 9.997 26.206 0 36.204l-294.4 294.401c-9.998 9.997-26.207 9.997-36.204-.001z"></path></svg><!-- <i class="fas fa-check"></i> --></div><span>Done!</span></li>
							</ul>
						</div>
						<form id="wpr-inboxads-register" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="wpr_inboxads_register_account">
							<?php wp_nonce_field( 'wpr_inboxads_register_account' ); ?>

							<div class="card-name">
								<img src="<?php echo WPR_ADS_URI; ?>assets/images/name.svg" alt="<?php esc_html_e( 'Name', 'wpr' ); ?>" />
								<h2 class="title blue"><?php esc_html_e( 'Your Full Name', 'wpr' ); ?></h2>
								<div class="field">
									<label for="wpr_iar_firstname" class="screen-reader-text"><?php esc_html_e( 'First Name', 'wpr' ); ?></label>
									<input id="wpr_iar_firstname" name="wpr_iar[firstname]" type="text" placeholder="<?php esc_attr_e( 'First Name', 'wpr' ); ?>" value="" class="regular-text" required data-lpignore="true" />
								</div>
								<div class="field">
									<label for="wpr_iar_lastname" class="screen-reader-text"><?php esc_html_e( 'First Name', 'wpr' ); ?></label>
									<input id="wpr_iar_lastname" name="wpr_iar[lastname]" type="text" placeholder="<?php esc_attr_e( 'Last Name', 'wpr' ); ?>" value="" class="regular-text" required data-lpignore="true" />
								</div>
								<input type="button" class="button button-primary" value="<?php esc_attr_e( 'Next step', 'wpr' ); ?>">
							</div>

							<div class="card-email d-none">
								<img src="<?php echo WPR_ADS_URI; ?>assets/images/email.svg" alt="<?php esc_html_e( 'Email', 'wpr' ); ?>" />
								<h2 class="title blue"><?php esc_html_e( 'Your Email Address', 'wpr' ); ?></h2>
								<div class="field">
									<label for="wpr_iar_email" class="screen-reader-text"><?php esc_html_e( 'Email', 'wpr' ); ?></label>
									<input id="wpr_iar_email" name="wpr_iar[email]" type="email" placeholder="<?php esc_attr_e( 'your@email.com', 'wpr' ); ?>" value="<?php echo wp_get_current_user()->user_email; ?>" class="regular-text" required data-lpignore="true" />
								</div>
								<input type="button" class="button button-primary" value="<?php esc_attr_e( 'Next step', 'wpr' ); ?>">
								<input type="button" class="button button-text" value="<?php esc_attr_e( 'Back to Name', 'wpr' ); ?>">
							</div>

							<div class="card-company d-none">
								<img src="<?php echo WPR_ADS_URI; ?>assets/images/company.svg" alt="<?php esc_html_e( 'Company', 'wpr' ); ?>" />
								<h2 class="title blue"><?php esc_html_e( 'Company Name', 'wpr' ); ?></h2>
								<div class="field">
									<label for="wpr_iar_company" class="screen-reader-text"><?php esc_html_e( 'Company', 'wpr' ); ?></label>
									<input id="wpr_iar_company" name="wpr_iar[company]" type="text" placeholder="<?php esc_attr_e( 'inboxAds', 'wpr' ); ?>" value="" class="regular-text" required data-lpignore="true" />
								</div>
								<a href="#" class="partnerButton">Partner Program <span class="hint1" title="If you are joining via our Partner Program, use this to enter the code obtained.">
									<svg class="svg-inline--fa fa-question-circle fa-w-16" aria-hidden="true" data-prefix="fas" data-icon="question-circle" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" data-fa-i2svg="">
										<path fill="currentColor" d="M504 256c0 136.997-111.043 248-248 248S8 392.997 8 256C8 119.083 119.043 8 256 8s248 111.083 248 248zM262.655 90c-54.497 0-89.255 22.957-116.549 63.758-3.536 5.286-2.353 12.415 2.715 16.258l34.699 26.31c5.205 3.947 12.621 3.008 16.665-2.122 17.864-22.658 30.113-35.797 57.303-35.797 20.429 0 45.698 13.148 45.698 32.958 0 14.976-12.363 22.667-32.534 33.976C247.128 238.528 216 254.941 216 296v4c0 6.627 5.373 12 12 12h56c6.627 0 12-5.373 12-12v-1.333c0-28.462 83.186-29.647 83.186-106.667 0-58.002-60.165-102-116.531-102zM256 338c-25.365 0-46 20.635-46 46 0 25.364 20.635 46 46 46s46-20.636 46-46c0-25.365-20.635-46-46-46z"></path>
									</svg>
								</span></a>
								<div class="partnerBox">
									<span>Enter the referral code you obtained:</span>
									<input id="wpr_iar_referral" name="wpr_iar[referral]" type="text" placeholder="" value="" class="regular-text" data-lpignore="true" />
								</div>
								<input type="button" class="button button-primary" value="<?php esc_attr_e( 'Sign Up', 'wpr' ); ?>">
								<input type="button" class="button button-text" value="<?php esc_attr_e( 'Back to Email', 'wpr' ); ?>">
							</div>

							<div class="card-password d-none">
								<img src="<?php echo WPR_ADS_URI; ?>assets/images/password.svg" alt="<?php esc_html_e( 'Company', 'wpr' ); ?>" />
								<h2 class="title"><?php esc_html_e( 'Set a Password', 'wpr' ); ?></h2>
								<div class="field">
									<label for="wpr_iar_password"><?php esc_html_e( 'Password', 'wpr' ); ?></label>
									<input id="wpr_iar_password" name="wpr_iar[password]" type="password" placeholder="" value="" class="regular-text" required data-lpignore="true" />
								</div>
								<div class="field">
									<label for="wpr_iar_password2"><?php esc_html_e( 'Confirm password', 'wpr' ); ?></label>
									<input id="wpr_iar_password2" name="wpr_iar[password2]" type="password" placeholder="" value="" class="regular-text" required data-lpignore="true" />
								</div>
								<span class="info">
									The password must have at least <strong>eight characters</strong>,<br/> 
									<strong>one capital letter</strong> and <strong>one number</strong>.</span>
								<input type="submit" name="submit" id="submit" class="button button-primary sign-in" value="<?php esc_attr_e( 'Save and continue', 'wpr' ); ?>">
								<input type="button" class="button button-text" value="<?php esc_attr_e( 'Back to Company', 'wpr' ); ?>">

								<div class="registerError"></div>
							</div>

						</form>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Output content for the plugin homepage.
	 */
	public function render_loginpage() {
		// Refresh plugins.
		$this->process_supported_plugins( 'both' );
		?>
		<link rel="stylesheet" href="https://use.typekit.net/hmn1gvp.css" />
		<div class="ids wrap wpr-inboxads-options">
			<h1 class="title"><img src="<?php echo WPR_ADS_URI; ?>assets/images/logo-blue.svg" alt="<?php esc_html_e( 'InboxAds', 'wpr' ); ?>" /></h1>
			<div id="wpr-inboxads-content">
				<div id="wpr-inboxads-account" class="admin-box">
					<div class="reg-container">
						<img class="mb-2" src="<?php echo WPR_ADS_URI; ?>assets/images/connect.svg" alt="<?php esc_html_e( 'Welcome', 'wpr' ); ?>" />
						<h2 class="title blue"><?php esc_html_e( 'Connect with your inboxAds account', 'wpr' ); ?></h2>
						<form id="wpr-inboxads-account" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="wpr_inboxads_connect_account">
							<?php wp_nonce_field( 'wpr_inboxads_connect_account' ); ?>

							<?php if ( ! empty( $_GET['login_error'] ) ) { ?>
								<div class="message message-error">
									<strong><?php esc_html_e( 'Error', 'wpr' ); ?>!</strong>
									<p><?php echo '1' === $_GET['login_error'] ? esc_html__( 'Username or password incorrect.', 'wpr' ) : esc_html__( 'An error occurred. Please try again later.', 'wpr' ); ?></p>
								</div>
							<?php } ?>

							<div class="body">

								<div class="field">
									<label for="wpr_ia_username" class="screen-reader-text"><?php esc_html_e( 'Email address', 'wpr' ); ?></label>
									<input id="wpr_ia_username" name="wpr_ia[username]" type="text" placeholder="<?php esc_attr_e( 'Email address', 'wpr' ); ?>" value="<?php echo $this->account['username']; ?>" class="regular-text" required data-lpignore="true" />
								</div>

								<div class="field">
									<label for="wpr_ia_password" class="screen-reader-text"><?php esc_html_e( 'Password', 'wpr' ); ?></label>
									<input id="wpr_ia_password" name="wpr_ia[password]" type="password" placeholder="<?php esc_attr_e( 'Password', 'wpr' ); ?>" value="<?php echo $this->account['password']; ?>" class="regular-text" required data-lpignore="true" />
								</div>

								<div class="field">
									<input type="submit" name="submit" id="submit" class="button button-primary sign-in" value="<?php esc_attr_e( 'Connect', 'wpr' ); ?>">
									<a href="<?php echo esc_url( $this->get_admin_page_url( 'register' ) ); ?>" class="button button-primary button-text">Create a new account</a>
								</div>

							</div>
						</form>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Output content for the plugin homepage.
	 */
	public function render_homepage() {
		// Refresh plugins.
		$this->process_supported_plugins( 'both' );
		?>
		<link rel="stylesheet" href="https://use.typekit.net/hmn1gvp.css" />
		<div class="ids wrap wpr-inboxads-options">
			<h1 class="title"><img src="<?php echo WPR_ADS_URI; ?>assets/images/logo-blue.svg" alt="<?php esc_html_e( 'InboxAds', 'wpr' ); ?>" /></h1>
			<div id="wpr-inboxads-content">
				<div id="wpr-inboxads-account" class="admin-box">
					<div class="reg-container">
						<img src="<?php echo WPR_ADS_URI; ?>assets/images/welcome.svg" alt="<?php esc_html_e( 'Welcome', 'wpr' ); ?>" />
						<h2 class="title"><?php esc_html_e( 'Welcome!', 'wpr' ); ?></h2>
						<p class="desc">To start using the plugin, please connect your inboxAds account, or create a new one below:</p>
						<a href="<?php echo esc_url( $this->get_admin_page_url( 'login' ) ); ?>" class="button button-primary">Connect</a>
						<a href="<?php echo esc_url( $this->get_admin_page_url( 'register' ) ); ?>" class="button button-primary button-outline">Create an inboxAds account</a>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Output content for the plugin admin page.
	 *
	 * @param bool $activate Whether the activation modal should be displayed.
	 */
	public function render_admin_page( $activate = false ) {
		// Refresh plugins.
		$this->process_supported_plugins( 'both' );

		?>
		<div class="wrap wpr-inboxads-options">
			<h1 class="title"><img src="<?php echo WPR_ADS_URI; ?>assets/images/logo-blue.svg" alt="<?php esc_html_e( 'InboxAds', 'wpr' ); ?>"></h1>

			<div id="wpr-inboxads-content">

				<div id="wpr-inboxads-account" class="admin-box">
					<?php if ( $this->api->is_connected() ) { ?>
						<h2 class="title"><?php esc_html_e( 'Hello!', 'wpr' ); ?></h2>

						<p class="desc">
							<?php esc_html_e( 'Welcome to inboxAds WordPress plugin. You are now ready to monetize your emails.', 'wpr' ); ?>
						</p>

						<p class="status">
							<strong class="active"><?php esc_html_e( 'Connected Account', 'wpr' ); ?></strong>: <?php echo $this->account['username']; ?>
						</p>

						<div class="submit">
							<a href="<?php echo esc_url( $this->get_admin_page_url( 'disconnect' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Disconnect', 'wpr' ); ?></a>
						</div>
					<?php } else { ?>
						<h2 class="title"><?php esc_html_e( 'Not Connected', 'wpr' ); ?></h2>

						<p class="desc">
							<?php echo nl2br( esc_html__( "You need an inboxAds account to use our WordPress plugin. You can login using an existing account \r\n or create a new one.", 'wpr' ) ); ?>
						</p>

						<div class="submit">
							<a href="<?php echo esc_url( $this->get_admin_page_url( 'activate' ) ); ?>" class="button button-primary modal-trigger" data-modal="wpr-inboxads-activate"><?php esc_html_e( 'Connect', 'wpr' ); ?></a>
						</div>
					<?php } ?>
				</div>

				<div id="wpr-inboxads-plugin" class="admin-box">
					<h2 class="title"><?php esc_html_e( 'inboxAds Plugin Status', 'wpr' ); ?></h2>
					<div class="wpr-inboxads-plugin-container">

					<?php

					$supported_plugins = ( $this->api->is_connected() ) ? $this->get_supported_plugins() : array();
					$found_installed = array_search(true, array_column($supported_plugins, 'installed'));
					$found_active = array_search(true, array_column($supported_plugins, 'active'));

					if ( !empty( $supported_plugins ) )
					{
						if ($found_installed === false)
						{
							?>
							<div class="wpr-inboxads-notification">
								<h4><?php esc_html_e( 'Action required', 'wpr' ); ?></h4>
								<span><?php esc_html_e( 'We couldn\'t find any installed plugins. Please install one of the supported plugins to get started.', 'wpr' ); ?></span>
							</div>
							<?php
						}
						else if ($found_active === false)
						{
							?>
							<div class="wpr-inboxads-notification">
								<h4><?php esc_html_e( 'Action required', 'wpr' ); ?></h4>
								<span><?php esc_html_e( 'We couldn\'t find any active plugins. Please activate one of the installed plugins to get started.', 'wpr' ); ?></span>
							</div>
							<?php
						}
						else if ($found_installed !== false && $found_active !== false)
						{
							?>
							<div class="wpr-inboxads-notification happy">
								<h4><?php esc_html_e( 'Ready to monetize', 'wpr' ); ?></h4>
								<span><?php esc_html_e( 'We have detected the following compatible plugins are installed & ready to be used.', 'wpr' ); ?></span>
							</div>
							<?php
						}
						?>
						
						<?php
						if (!empty($supported_plugins)) {
							$newsletters = get_option( 'wpr_inboxads_newsletters', array() );
							foreach ($supported_plugins as $key => &$entry) {
								$supported_plugins_group[$entry['installed']][$key] = $entry;
							}
							krsort($supported_plugins_group);
							foreach ($supported_plugins_group as $installed => $group) {
								usort($group, function($a, $b) {
									return $b['active'] - $a['active'];
								});
								echo '<ul class="wpr-inboxads-acordeon '.(!$installed && $found_installed !== false ? 'hidden' : '').'">';
								foreach ($group as $plugin) {
									echo '<li class="'.($plugin['active'] ? 'active' : '').'"><div class="header">';

									if ($plugin['installed'] === false)
									{
										echo (strstr($plugin['install'], 'mailster') ? '
											<a class="button button-primary button-outline" target="_blank" href="https://mailster.co/">'.__( 'Install', 'wpr' ).'</a>
										' : '
											<a class="wpr-inboxads-install button button-primary button-outline" data-slug="'.$plugin['install'].'" href="'.wp_nonce_url(self_admin_url('update.php?action=install-plugin&plugin='.$plugin['install']), 'install-plugin_'.$plugin['install']).'">'.__( 'Install', 'wpr' ).'</a>
										');
									}
									else if ($plugin['active'] === false)
									{
										echo '<a class="wpr-inboxads-activate button button-primary button-outline" href="'.wp_nonce_url(self_admin_url('plugins.php?action=activate&plugin='.$plugin['slug'].'&plugin_status=all&paged=1&s'), 'activate-plugin_'.$plugin['slug']).'">
											'.__( 'Activate', 'wpr' ).'
										</a>';
									}
									else
									{
										echo '<div class="active-status"><span class="icon active"></span> Active</div>';
									}

									echo '<span>'.$plugin['name'].'</span> '.$plugin['version'];
									echo '</div>';
									?>

									<div class="desc">
										<?php if ( $plugin['active'] ) { ?>
										<div class="api-status">
											<?php if ( !empty( $newsletters[ $plugin['name'] ] ) ) { ?>
												<strong><?php esc_html_e( 'Newsletter ID', 'wpr' ); ?>:</strong> <span><?php echo $newsletters[ $plugin['name'] ]; ?></span>
											<?php } else { ?>
												<a href="<?php echo esc_url( $this->get_admin_page_url( 'process_plugins' ) ); ?>"><?php esc_html_e( 'Create newsletter', 'wpr' ); ?></a>
											<?php } ?>
										</div>
										<?php } ?>
										<h3>How to use:</h3>
										<?php
										switch ($plugin['install'])
										{
											case 'alo-easymail':
											?>
											<ol>
												<li>In your Admin Dashboard, the plugin appears as <strong>Newsletters</strong></li>
												<li>Click on <strong>Add New</strong> or <strong>Edit</strong> an existing newsletter</li>
												<li>In the <strong>Main Content</strong> area you will see the inboxAds button:</li>
											</ol>
											<img src="<?php echo WPR_ADS_URI; ?>assets/images/alo-easymail.png" />
											<?php
											break;

											case 'mailster':
											?>
											<ol>
												<li>In your Admin Dashboard, the plugin appears as <strong>Newsletters</strong></li>
												<li>Go to <strong>Campaigns</strong> and either <strong>Create</strong> or <strong>Edit</strong></li>
												<li>In the <strong>Template</strong> window, in the <strong>Modules</strong> column, scroll down untill you see inboxAds:</li>
											</ol>
											<img src="<?php echo WPR_ADS_URI; ?>assets/images/mailster.png" />
											<?php
											break;

											case 'mailpoet':
											?>
											<ol>
												<li>In the MailPoet Dashboard select <strong>Newsletters</strong> and then <strong>Add New</strong></li>
												<li>Select the type of email you want to use</li>
												<li>Choose a template</li>
												<li>In the Content section, you will see the inboxAds component.<br>
													<strong>Very important: you can add only on top of Text Content Blocks.</strong></li>
											</ol>
											<img src="<?php echo WPR_ADS_URI; ?>assets/images/mailpoet.png" />
											<?php
											break;

											case 'newsletter':
											?>
											<ol>
												<li>In the plugin Dashboard select <strong>Newsletters</strong> and then <strong>Create Newsletter</strong></li>
												<li>Choose <strong>Responsive Drag & Drop Composer</strong></li>
												<li>In the <strong>Content</strong> section, you will see the inboxAds component:</li>
											</ol>
											<img src="<?php echo WPR_ADS_URI; ?>assets/images/newsletter.png" />
											<?php
											break;

											case 'sendpress':
											?>
											<ol>
												<li>In the plugin Dashboard go to the <strong>Emails</strong> tab</li>
												<li>Choose a type of content to <strong>Create</strong> or <strong>Edit</strong></li>
												<li>Choose <strong>Use New System</strong> and pick <strong>Responsive Starter</strong> template</li>
												<li>In the <strong>Main Content</strong> area you will see the inboxAds button:</li>
											</ol>
											<img src="<?php echo WPR_ADS_URI; ?>assets/images/sendpress.png" />
											<?php
											break;
										}
										?>
									</div>

									<?php
									echo '</li>';
								}
								echo '</ul>';

								if (!$installed && $found_installed !== false && isset($supported_plugins_group[0])) {
									echo '<button class="button button-primary wpr-inboxads-acordeon-show">Show All Supported Plugins</button>';
								}
							}
						}
					}
					?>
					</div>

					<!-- <button id="wpr-supported-plugins" class="button button-primary modal-trigger" data-modal="wpr-inboxads-plugins"><?php esc_html_e( 'All Supported Plugins', 'wpr' ); ?></button> -->

					<p class="desc">
						<?php esc_html_e( 'Not listed? We are constantly working to add more supported plugins to the list. We are happy to hear your suggestions.', 'wpr' ); ?>
						<br>
						<a href="mailto:support@inboxads.com" class="link link-primary"><?php esc_html_e( 'Get in Touch', 'wpr' ); ?></a>
					</p>
				</div>

				<div id="wpr-inboxads-stats" class="admin-box">
					<h2 class="title"><?php esc_html_e( 'Performance Chart', 'wpr' ); ?></h2>

					<p class="desc" style="margin-bottom:0">
						<?php _e( 'Gain quick insights over the last 7-day overall performance. Data is updated every 24 hours.<br/>For more in-depth stats, go to your inboxAds publisher dashboard.', 'wpr' ); ?>
					</p>

					<?php if ( $this->api->is_connected() ) { ?>
						<p class="desc" style="margin-bottom:30px">
							<a href="https://publishers.inboxads.com/?utm_source=wpplugin" class="button button-primary" target="_blank"><?php esc_html_e( 'Go to Dashboard', 'wpr' ); ?></a>
						</p>
					<?php } ?>

					<?php
					$stats_data = inboxads_api()->get_stats();
					$has_data   = ( $stats_data['totals']['views'] > 0 || $stats_data['totals']['revenue'] > 0 );
					?>

					<div class="chart-legend">
						<div class="legend legend-impressions">
							<?php esc_html_e( 'Impressions', 'wpr' ); ?><strong class="value"><?php echo ( $stats_data['totals']['views'] > 0 ) ? ': ' . $stats_data['totals']['views'] : ''; ?></strong>
						</div>
						<div class="legend legend-revenue"
							><?php esc_html_e( 'Revenue', 'wpr' ); ?><strong class="value"><?php echo ( $stats_data['totals']['revenue'] > 0 ) ? ': $' . number_format_i18n( $stats_data['totals']['revenue'], 2 ) : ''; ?></strong>
						</div>
					</div>

					<div class="stats-chart <?php echo ( $this->api->is_connected() && $has_data ) ? 'stats-active' : 'stats-empty'; ?>">
						<canvas id="wpr-inboxads-chart"></canvas>
						<script type="text/javascript">var inboxads_chart_data = <?php echo json_encode( $stats_data ); ?>;</script>
					</div>
				</div>

				<div id="wpr-inboxads-options" class="admin-box">
					<h2 class="title"><?php esc_html_e( 'Delete Data', 'wpr' ); ?></h2>

					<p class="desc"><?php esc_html_e( 'Use this option to erase all plugin data.', 'wpr' ); ?></p>

					<div class="submit">
						<button class="link link-primary" id="wpr-delete-data"><?php esc_html_e( 'Delete All Data', 'wpr' ); ?></button>

						<div id="wpr-delete-data-warning">
							<strong>
								<svg width="16" height="14" viewBox="0 0 16 14" fill="none" xmlns="http://www.w3.org/2000/svg">
									<path d="M15.8199 12.0316C16.3326 12.9064 15.6891 14 14.665 14H1.33484C0.308813 14 -0.331798 12.9047 0.179925 12.0316L6.84509 0.65584C7.35806 -0.219406 8.64287 -0.21782 9.15492 0.65584L15.8199 12.0316ZM8 9.67969C7.29431 9.67969 6.72223 10.2428 6.72223 10.9375C6.72223 11.6322 7.29431 12.1953 8 12.1953C8.7057 12.1953 9.27778 11.6322 9.27778 10.9375C9.27778 10.2428 8.7057 9.67969 8 9.67969ZM6.78687 5.15851L6.99292 8.87726C7.00256 9.05127 7.14873 9.1875 7.32576 9.1875H8.67426C8.85128 9.1875 8.99745 9.05127 9.00709 8.87726L9.21314 5.15851C9.22356 4.97055 9.07153 4.8125 8.88031 4.8125H7.11967C6.92845 4.8125 6.77645 4.97055 6.78687 5.15851Z" fill="#F47521"/>
								</svg>
								<?php esc_html_e( 'Warning!', 'wpr' ); ?>
							</strong>

							<p class="desc">
								<?php esc_html_e( 'This will erase all the data associated to the inboxAds plugins, including all zones, newsletters & reporting. There is no way to retrieve the data, so please confirm the action by clicking the button below.', 'wpr' ); ?>
							</p>

							<p class="buttons">
								<button id="wpr-delete-data-cancel" class="link link-primary"><?php esc_html_e( 'Cancel', 'wpr' ); ?></button>
								<a href="<?php echo esc_url( $this->get_admin_page_url( 'reset' ) ); ?>" class="link"><?php esc_html_e( 'Delete Data', 'wpr' ); ?></a>
							</p>
						</div>
					</div>
				</div>

				<div id="wpr-inboxads-modal-wrap"<?php echo $activate ? ' class="visible"' : ''; ?>>
					<?php

					$this->render_admin_activate_modal( $activate );

					//$this->render_supported_plugins_modal();

					?>
				</div>

			</div>

			<?php if ( ! empty( $_GET['new'] ) ) { ?>
				<div class="toast">
					<div>
						<svg aria-hidden="true" focusable="false" data-prefix="fas" data-icon="times" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 352 512" class="svg-inline--fa fa-times fa-w-11 fa-2x">
							<path fill="currentColor" d="M242.72 256l100.07-100.07c12.28-12.28 12.28-32.19 0-44.48l-22.24-22.24c-12.28-12.28-32.19-12.28-44.48 0L176 189.28 75.93 89.21c-12.28-12.28-32.19-12.28-44.48 0L9.21 111.45c-12.28 12.28-12.28 32.19 0 44.48L109.28 256 9.21 356.07c-12.28 12.28-12.28 32.19 0 44.48l22.24 22.24c12.28 12.28 32.2 12.28 44.48 0L176 322.72l100.07 100.07c12.28 12.28 32.2 12.28 44.48 0l22.24-22.24c12.28-12.28 12.28-32.19 0-44.48L242.72 256z" class=""></path>
						</svg>
					</div>
					<?php esc_html_e( 'Password saved successfully.', 'wpr' ); ?>
					<script>
						(function($) {
							$('.toast').delay(3000).fadeOut(200, function(){ $(this).remove(); });
							$('.toast > div').click(function(){
								$('.toast').fadeOut(200, function(){ $(this).remove(); });
							});
						}(jQuery));
					</script>
				</div>
			<?php } ?>

		</div>
		<?php
	}

	/**
	 * Output content for the plugin activation modal.
	 *
	 * @param bool $activate Whether the activation modal should be displayed.
	 */
	public function render_admin_activate_modal( $activate = false ) {
		?>
		<div id="wpr-inboxads-activate" class="modal <?php echo $activate ? 'visible' : ''; ?>">
			<a class="close" href="<?php echo $this->get_admin_page_url(); ?>">
				<svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
					<path d="M11.7784 13.743L13.743 11.7784C14.0857 11.4358 14.0857 10.8854 13.743 10.5428L10.2002 7L13.743 3.45717C14.0857 3.11455 14.0857 2.56418 13.743 2.22156L11.7784 0.256964C11.4358 -0.0856548 10.8854 -0.0856548 10.5428 0.256964L7 3.79979L3.45717 0.256964C3.11455 -0.0856548 2.56418 -0.0856548 2.22156 0.256964L0.256964 2.22156C-0.0856548 2.56418 -0.0856548 3.11455 0.256964 3.45717L3.79979 7L0.256964 10.5428C-0.0856548 10.8854 -0.0856548 11.4358 0.256964 11.7784L2.22156 13.743C2.56418 14.0857 3.11455 14.0857 3.45717 13.743L7 10.2002L10.5428 13.743C10.8818 14.082 11.4358 14.082 11.7784 13.743Z" fill="#6AAE19"/>
				</svg>
			</a>

			<div class="inner">

				<div class="header">
					<svg width="74" height="70" viewBox="0 0 74 70" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path d="M63.8877 53.7302C63.6666 53.9822 63.5334 54.3302 63.5334 54.6769C63.5334 55.0235 63.6556 55.3702 63.8877 55.6235C64.1187 55.8635 64.4375 56.0102 64.7552 56.0102C65.0729 56.0102 65.3905 55.8635 65.6227 55.6235C65.8426 55.3702 65.977 55.0235 65.977 54.6769C65.977 54.3302 65.8426 53.9822 65.6227 53.7302C65.1706 53.2503 64.352 53.2369 63.8877 53.7302Z" fill="#F47521"/>
						<path d="M28.8809 51.0277L24.436 48.5504V48.0344C26.4251 46.8491 27.824 44.8958 28.6047 42.2146C29.7887 41.5306 30.5449 40.1973 30.5449 38.7013V37.368C30.5449 36.1321 30.0184 34.9868 29.1387 34.2348C28.3763 29.1949 25.1532 26.6403 19.5488 26.6403C19.2837 26.6403 19.0246 26.6523 18.7717 26.6736C17.7149 26.763 16.1705 26.6696 14.8327 25.6857C14.3317 25.3177 13.9542 24.9603 13.7123 24.6244C13.3018 24.0537 12.6114 23.855 12.0005 24.1324C11.3884 24.407 11.028 25.0763 11.105 25.7963C11.1563 26.2923 11.2333 26.8736 11.3493 27.5096C11.5851 28.8096 11.5851 28.8096 11.254 29.5869C11.1294 29.8816 10.9755 30.2402 10.7946 30.7242C10.3902 31.8042 10.1031 32.9868 9.93815 34.2521C9.07068 35.0041 8.55264 36.1441 8.55264 37.368V38.7013C8.55264 40.1973 9.30893 41.5306 10.4928 42.2159C11.2736 44.8985 12.6725 46.8518 14.6616 48.0357V48.5371L10.0554 51.013C8.37425 52.0143 7.33084 53.9343 7.33084 56.0249V57.7902C7.33084 58.8608 7.33084 61.3674 19.5488 61.3674C31.7667 61.3674 31.7667 58.8608 31.7667 57.7902V56.1315C31.7667 53.9583 30.661 52.0023 28.8809 51.0277ZM29.3231 57.4888C28.562 57.9942 25.4611 58.6995 19.5488 58.6995C13.6365 58.6995 10.5356 57.9942 9.77443 57.4888V56.0235C9.77443 54.9102 10.3316 53.8863 11.1807 53.3796L15.8944 50.8463C16.6299 50.4543 17.1052 49.6237 17.1052 48.7291V46.3238L16.3672 45.9758C14.5138 45.1065 13.3091 43.4439 12.6835 40.8906L12.5088 40.1813L11.8661 39.9626C11.3542 39.7893 10.9962 39.2693 10.9962 38.7013V37.368C10.9962 36.884 11.2491 36.4361 11.6572 36.1987L12.2302 35.8654L12.2974 35.1614C12.4184 33.9094 12.6738 32.7561 13.0574 31.7348C13.2199 31.3015 13.358 30.9775 13.4704 30.7122C13.8845 29.7402 14.0348 29.2389 13.931 28.2109C15.3666 29.1069 17.0918 29.4896 18.9672 29.3336C19.1566 29.3162 19.3509 29.3069 19.55 29.3069C24.1244 29.3069 26.3579 31.1149 26.794 35.1721L26.8698 35.8694L27.4391 36.1961C27.8472 36.432 28.1014 36.88 28.1014 37.368V38.7013C28.1014 39.2706 27.7434 39.7893 27.2314 39.9626L26.5888 40.1813L26.4141 40.8906C25.7885 43.4439 24.5838 45.1065 22.7303 45.9758L21.9924 46.3238V48.7397C21.9924 49.6277 22.4432 50.433 23.1702 50.8397L27.7739 53.4049L27.7825 53.4103C28.733 53.9289 29.3231 54.9729 29.3231 56.1315V57.4888Z" fill="#F47521"/>
						<path d="M37.8756 33.3681H50.0936C50.7692 33.3681 51.3154 32.7721 51.3154 32.0348C51.3154 31.2975 50.7692 30.7015 50.0936 30.7015H37.8756C37.2 30.7015 36.6538 31.2975 36.6538 32.0348C36.6538 32.7721 37.2 33.3681 37.8756 33.3681Z" fill="#F47521"/>
						<path d="M39.0974 53.3676H37.8756C37.2 53.3676 36.6538 53.9636 36.6538 54.7009C36.6538 55.4382 37.2 56.0342 37.8756 56.0342H39.0974C39.7731 56.0342 40.3192 55.4382 40.3192 54.7009C40.3192 53.9636 39.7731 53.3676 39.0974 53.3676Z" fill="#F47521"/>
						<path d="M46.4283 53.3676H43.9847C43.309 53.3676 42.7629 53.9636 42.7629 54.7009C42.7629 55.4382 43.309 56.0342 43.9847 56.0342H46.4283C47.1039 56.0342 47.6501 55.4382 47.6501 54.7009C47.6501 53.9636 47.1039 53.3676 46.4283 53.3676Z" fill="#F47521"/>
						<path d="M52.5371 53.3676H51.3153C50.6397 53.3676 50.0935 53.9636 50.0935 54.7009C50.0935 55.4382 50.6397 56.0342 51.3153 56.0342H52.5371C53.2128 56.0342 53.7589 55.4382 53.7589 54.7009C53.7589 53.9636 53.2128 53.3676 52.5371 53.3676Z" fill="#F47521"/>
						<path d="M59.868 53.3676H57.4244C56.7487 53.3676 56.2026 53.9636 56.2026 54.7009C56.2026 55.4382 56.7487 56.0342 57.4244 56.0342H59.868C60.5436 56.0342 61.0898 55.4382 61.0898 54.7009C61.0898 53.9636 60.5436 53.3676 59.868 53.3676Z" fill="#F47521"/>
						<path d="M64.7551 38.7013H37.8756C37.2 38.7013 36.6538 39.2973 36.6538 40.0346C36.6538 40.7719 37.2 41.3679 37.8756 41.3679H64.7551C65.4308 41.3679 65.9769 40.7719 65.9769 40.0346C65.9769 39.2973 65.4308 38.7013 64.7551 38.7013Z" fill="#F47521"/>
						<path d="M64.7551 46.7011H37.8756C37.2 46.7011 36.6538 47.2971 36.6538 48.0344C36.6538 48.7717 37.2 49.3677 37.8756 49.3677H64.7551C65.4308 49.3677 65.9769 48.7717 65.9769 48.0344C65.9769 47.2971 65.4308 46.7011 64.7551 46.7011Z" fill="#F47521"/>
						<path d="M67.5921 13.3446H45.2064V3.09558C45.2064 1.39429 43.9394 0.0116577 42.3804 0.0116577H30.9261C29.3683 0.0116577 28.1013 1.39429 28.1013 3.09558V13.3446H5.71556C2.56455 13.3446 0 16.1432 0 19.5818V63.1073C0 66.5446 2.56455 69.3432 5.71556 69.3432H67.5934C70.7431 69.3432 73.3077 66.5446 73.3077 63.106V19.5818C73.3077 16.1432 70.7431 13.3446 67.5921 13.3446ZM30.5449 3.09558C30.5449 2.86625 30.7171 2.67825 30.9261 2.67825H42.3804C42.5905 2.67825 42.7628 2.86625 42.7628 3.09558V13.3446V20.9284C42.7628 21.1564 42.5905 21.3444 42.3804 21.3444H30.9261C30.7171 21.3444 30.5449 21.1564 30.5449 20.9284V13.3446V3.09558ZM70.8641 63.106C70.8641 65.0753 69.3967 66.6766 67.5921 66.6766H5.71556C3.91097 66.6766 2.44359 65.0753 2.44359 63.106V19.5818C2.44359 17.6125 3.91097 16.0112 5.71556 16.0112H28.1013V20.9284C28.1013 22.6284 29.3683 24.011 30.9261 24.011H42.3804C43.9394 24.011 45.2064 22.6284 45.2064 20.9284V16.0112H67.5921C69.3967 16.0112 70.8641 17.6125 70.8641 19.5818V63.106Z" fill="#F47521"/>
						<path d="M36.6538 14.678C39.3491 14.678 41.541 12.286 41.541 9.34476C41.541 6.4035 39.3491 4.01157 36.6538 4.01157C33.9585 4.01157 31.7666 6.4035 31.7666 9.34476C31.7666 12.286 33.9585 14.678 36.6538 14.678ZM36.6538 6.67816C38.0014 6.67816 39.0974 7.87413 39.0974 9.34476C39.0974 10.8154 38.0014 12.0114 36.6538 12.0114C35.3062 12.0114 34.2102 10.8154 34.2102 9.34476C34.2102 7.87413 35.3062 6.67816 36.6538 6.67816Z" fill="#F47521"/>
					</svg>

					<h2 class="title"><?php esc_html_e( 'Connect with your inboxAds account', 'wpr' ); ?></h2>

					<p class="desc"><?php esc_html_e( 'Use your existing account or create a new one.', 'wpr' ); ?></p>
				</div>

				<form id="wpr-inboxads-account" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="wpr_inboxads_connect_account">
					<?php wp_nonce_field( 'wpr_inboxads_connect_account' ); ?>

					<?php if ( ! empty( $_GET['login_error'] ) ) { ?>
						<div class="message message-error">
							<strong><?php esc_html_e( 'Error', 'wpr' ); ?></strong>:
							<p><?php echo '1' === $_GET['login_error'] ? esc_html__( 'Username or password incorrect.', 'wpr' ) : esc_html__( 'An error occurred. Please try again later.', 'wpr' ); ?></p>
						</div>
					<?php } ?>

					<div class="body">

						<div class="field">
							<label for="wpr_ia_username" class="screen-reader-text"><?php esc_html_e( 'Username', 'wpr' ); ?></label>
							<input id="wpr_ia_username" name="wpr_ia[username]" type="text" placeholder="<?php esc_attr_e( 'Username', 'wpr' ); ?>" value="<?php echo $this->account['username']; ?>" class="regular-text" required>
						</div>

						<div class="field">
							<label for="wpr_ia_password" class="screen-reader-text"><?php esc_html_e( 'Password', 'wpr' ); ?></label>
							<input id="wpr_ia_password" name="wpr_ia[password]" type="password" placeholder="<?php esc_attr_e( 'Password', 'wpr' ); ?>" value="<?php echo $this->account['password']; ?>" class="regular-text" required>
						</div>

						<div class="field">
							<input type="submit" name="submit" id="submit" class="button button-primary sign-in" value="<?php esc_attr_e( 'Sign In', 'wpr' ); ?>">
							<br>
							<a class="create-account link link-primary" href="https://inboxads.com/register?utm_source=wpplugin" target="_blank"><?php esc_html_e( 'Create an Account', 'wpr' ); ?></a>
						</div>

					</div>

				</form>

			</div>
		</div>
		<?php
	}

	/**
	 * Output content for the supported plugins modal.
	 */
	public function render_supported_plugins_modal() {
		?>
		<div id="wpr-inboxads-plugins" class="modal">
			<div class="inner">

				<div class="header">
					<h2 class="title"><?php esc_html_e( 'All Supported Plugins', 'wpr' ); ?></h2>
				</div>

				<div class="plugins-list-scroll-wrap">

					<div class="plugins-list-scroll-inner">
						<ul id="wpr-inboxads-plugins-list" class="wpr-inboxads-plugins-list">
							<?php

							// Get list of supported newsletter plugins.
							$plugins = $this->get_supported_plugins();

							foreach ( $plugins as $plugin ) {
								?>
								<li class="plugin <?php echo $plugin['active'] ? 'active' : 'inactive'; ?>">

									<strong><?php echo esc_html( $plugin['name'] ); ?></strong>

									<?php if ( $plugin['version'] ) { ?>
										<small><?php echo esc_html( $plugin['version'] ); ?></small>
									<?php } ?>

									<br>

									<small class="status">
										<?php if ( ! $plugin['installed'] ) { ?>
											<span class="icon"></span> <?php esc_html_e( 'Not Installed', 'wpr' ); ?>
										<?php } elseif ( $plugin['active'] ) { ?>
											<span class="icon active"></span> <?php esc_html_e( 'Active', 'wpr' ); ?>
										<?php } else { ?>
											<span class="icon installed"></span> <?php esc_html_e( 'Inactive', 'wpr' ); ?>
										<?php } ?>
									</small>

								</li>
								<?php
							}

							?>
						</ul>
					</div>

				</div>

				<div class="footer">
					<a class="close link link-primary" href="<?php echo $this->get_admin_page_url(); ?>"><?php esc_html_e( 'Go Back', 'wpr' ); ?></a>
				</div>

			</div>

		</div>
		<?php
	}

	/**
	 * Attempt to connect to InboxAds account.
	 */
	public function connect_account() {
		// Check nonce.
		check_admin_referer( 'wpr_inboxads_connect_account' );

		if ( ! empty( $_POST['wpr_ia']['username'] ) && ! empty( $_POST['wpr_ia']['password'] ) ) {
			$this->api->set_credentials(
				array(
					'username' => sanitize_text_field( $_POST['wpr_ia']['username'] ),
					'password' => filter_var( $_POST['wpr_ia']['password'], FILTER_SANITIZE_STRING ),
				)
			);

			if ( $this->api->is_connected() ) {
				// Account is connected.
				$account = array(
					'username' => sanitize_text_field( $_POST['wpr_ia']['username'] ),
					'password' => wp_hash_password( $_POST['wpr_ia']['password'] ),
				);

				update_option( 'wpr_inboxads_account', $account );

				// Refresh plugin newsletters.
				$this->process_supported_plugins( 'both' );
			} else {
				// Account not connected.
				delete_option( 'wpr_inboxads_account' );

				if ( 401 == $this->api->auth_code ) {
					// 401 status code means the username or password is incorrect.
					$error_code = 1;
				} else {
					// Other status codes indicate issue with API or connection.
					$error_code = 2;
				}

				// Redirect back to setup page.
				wp_redirect( add_query_arg( 'login_error', $error_code, $this->get_admin_page_url( 'login' ) ) );
				exit;
			}
		}

		wp_redirect( $this->get_admin_page_url() );
		exit;
	}

	/**
	 * Register a new InboxAds account.
	 */
	public function register_account() {
		// Check nonce.
		check_admin_referer( 'wpr_inboxads_register_account' );

		$form = [
			'Firstname' => sanitize_text_field( $_POST['wpr_iar']['firstname'] ),
			'Lastname' => sanitize_text_field( $_POST['wpr_iar']['lastname'] ),
			'Email' => sanitize_text_field( $_POST['wpr_iar']['email'] ),
			'Company' => sanitize_text_field( $_POST['wpr_iar']['company'] ),
			'Password' => sanitize_text_field( $_POST['wpr_iar']['password'] )
		];

		$referral = sanitize_text_field( $_POST['wpr_iar']['referral'] );
		if (!empty($referral)) {
			$form['Referral'] = $referral;
		}

		$response = $this->api->register($form);
		$json = json_decode($response);

		if ($json->success == true)
		{
			$account = [
				'username' => $form['Email'],
				'password' => $form['Password']
			];

			$this->api->set_credentials($account);

			if ($this->api->is_connected())
			{
				update_option( 'wpr_inboxads_account', $account );
				$this->process_supported_plugins('both');
			}
		}

		echo $response;

		exit;
	}

	/**
	 * Disconnect from InboxAds account.
	 */
	public function disconnect_account() {
		// Delete token, username and password.
		delete_option( 'wpr_inboxads_account' );
		delete_option( 'wpr_inboxads_account_connected' );
		// Delete newsletters.
		delete_option( 'wpr_inboxads_newsletters' );
		delete_option( 'wpr_inboxads_active_plugins' );

		// Unset credentials and token.
		$this->api->disconnect_account();

		// Refresh properties.
		$this->init();

		$this->render_homepage();
	}

	/**
	 * Get array of supported plugins.
	 *
	 * @param string $type Which type of plugins to fetch ('all', 'active', 'installed').
	 * @return array
	 */
	public function get_supported_plugins( $type = 'all' ) {
		$plugins = array();

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$installed_plugins = get_plugins();

		foreach ( $this->supported_plugins as $plugin => $name ) {
			$installed = array_key_exists( $plugin, $installed_plugins );
			if ( ! $installed && 'installed' === $type ) {
				continue;
			}

			$active = ( is_plugin_active( $plugin ) || is_plugin_active_for_network( $plugin ) );
			if ( ! $active && 'active' === $type ) {
				continue;
			}

			$plugins[] = array(
				'name'      => $name,
				'installed' => $installed,
				'active'    => $active,
				'version'   => ! empty( $installed_plugins[ $plugin ]['Version'] ) ? $installed_plugins[ $plugin ]['Version'] : null,
				'install'	=> $this->supported_plugins_install[ $plugin ],
				'slug'		=> $plugin
			);
		}

		return apply_filters(
			'wpr_inboxads_supported_plugins',
			$plugins,
			$type
		);
	}

	/**
	 * Create API newsletter for the supported active newsletter plugins.
	 *
	 * @param string $refresh Can be 'plugins', 'newsletters', or 'both'. Whether to skip cache and refresh.
	 */
	public function process_supported_plugins( $refresh = '' ) {
		if ( ! $this->api->is_connected() ) {
			return;
		}

		if ( self::$did_process_newsletters ) {
			// Newsletters already processed.
			return;
		}

		// Get site's domain to use for newsletters.
		$site_domain = apply_filters( 'wpr_inboxads_site_domain', basename( get_option( 'siteurl' ) ) );

		// Get array of active, supported newsletter plugins.
		$plugins = array_map(
			function( $plugin ) {
				return $plugin['name'];
			},
			$this->get_supported_plugins( 'active' )
		);

		// Get array of cached plugins.
		$plugins_cached = ( in_array( $refresh, array( 'plugins', 'both' ) ) ) ? array() : get_option( 'wpr_inboxads_active_plugins', array() );

		if ( $plugins == $plugins_cached ) {
			// Active plugin list has not changed.
			return;
		}

		// Update plugin cache.
		update_option( 'wpr_inboxads_active_plugins', $plugins );

		// Get array of existing newsletters.
		$newsletters = ( in_array( $refresh, array( 'newsletters', 'both' ) ) ) ? array() : get_option( 'wpr_inboxads_newsletters', array() );
		if ( ! is_array( $newsletters ) ) {
			$newsletters = array();
		}

		$newsletters_initial = $newsletters;

		// Get newsletters registered for the account.
		$api_newsletters = $this->api->list_newsletters();

		// Prepare array for newly created newsletters.
		$new_newsletters = array();

		// Create newsletter for each plugin.
		foreach ( $plugins as $plugin ) {
			/* translators: %1$s: site URL, %2$s: plugin name. */
			$name = sprintf( __( '%1$s %2$s Plugin', 'wpr' ), $site_domain, $plugin );

			if ( array_key_exists( $plugin, $newsletters ) ) {
				// Newsletter for this plugin already created.
				continue;
			}

			foreach ( $api_newsletters as $_newsletter ) {
				// Check registered newsletters for a match for this plugin.
				if ( $name !== $_newsletter['name'] || $site_domain !== $_newsletter['domain'] || ! $_newsletter['status'] ) {
					continue;
				}

				$newsletters[ $plugin ] = $_newsletter['newsletterID'];

				// Don't go any further.
				continue 2;
			}

			$created = $this->api->create_newsletter(
				array(
					'name'   => $name,
					'domain' => $site_domain,
				)
			);

			if ( $created ) {
				$new_newsletters[ $plugin ] = $name;
			}
		}

		if ( ! empty( $new_newsletters ) ) {
			// Get newsletter ID for newly created newsletters.
			foreach ( $this->api->list_newsletters() as $_newsletter ) {
				if ( ! in_array( $_newsletter['name'], $new_newsletters ) || $site_domain !== $_newsletter['domain'] ) {
					continue;
				}

				$plugin = array_search( $_newsletter['name'], $new_newsletters );

				if ( ! $plugin ) {
					continue;
				}

				$newsletters[ $plugin ] = $_newsletter['newsletterID'];
			}
		}

		if ( $newsletters != $newsletters_initial ) {
			// Update newsletters list.
			update_option( 'wpr_inboxads_newsletters', $newsletters );
		}

		if ( in_array( $refresh, array( 'newsletters', 'both' ) ) ) {
			self::$did_process_newsletters = true;
		}
	}

	/**
	 * Refresh newsletters when a supported newsletter plugin is activated or deactivated.
	 *
	 * @param string $plugin
	 */
	public function plugins_changed( $plugin ) {
		if ( ! array_key_exists( $plugin, $this->supported_plugins ) ) {
			// The plugin is not supported.
			return;
		}

		$this->process_supported_plugins( 'both' );
	}

	/**
	 * Display admin notices.
	 */
	public function admin_notices() {
		$zone_errors = get_option( 'wpr_inboxads_zone_errors', array() );
		if ( empty( $zone_errors ) || ! is_array( $zone_errors ) ) {
			return;
		}

		foreach ( $zone_errors as $error ) {
			printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $error ) );
		}

		delete_option( 'wpr_inboxads_zone_errors' );
	}

	/**
	 * Clear the plugin's data and reset account.
	 */
	public function clear_data() {
		// Define options the plugin uses.
		$options = array(
			'account',
			'account_connected',
			'active_plugins',
			'newsletters',
			'newsletter_domain',
			'zone_hashes',
			'zone_errors',
			'sendpress_zones',
		);

		// Add individual zone options to options array.
		$hashes = get_option( 'wpr_inboxads_zone_hashes', array() );
		if ( is_array( $hashes ) ) {
			foreach ( $hashes as $hash => $zone_id ) {
				$options[] = 'zone_' . $hash;
			}
		}

		// Delete options.
		foreach ( $options as $option ) {
			delete_option( 'wpr_inboxads_' . $option );
		}

		// Disconnect account.
		$this->disconnect_account();
	}
}

new InboxAds_Admin();
