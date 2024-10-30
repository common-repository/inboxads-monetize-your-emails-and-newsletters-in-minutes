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
 * Class which handles shortcode functionality.
 */
class InboxAds_Shortcode_Builder {

	/** @var array */
	private $supported_plugins = array(
		'alo-easymail/alo-easymail.php' => 'ALO EasyMail',
		'mailpoet/mailpoet.php'         => 'MailPoet',
		'sendpress/sendpress.php'       => 'SendPress',
	);

	/** @var string */
	private $plugin;

	/**
	 * Constructor.
	 */
	function __construct() {
		// wp_register_script(
		// 	'inboxads-custom-javascript',
		// 	plugins_url( 'admin.js', __FILE__ ),
		// 	array( 'jquery', 'wp-rich-text', 'wp-element', 'wp-editor' )
		// );
		// wp_enqueue_script( 'inboxads-custom-javascript' );

		add_action( 'admin_init', array( $this, 'admin_init' ) );

		add_shortcode( 'inboxads_zone', array( __CLASS__, 'render_shortcode' ) );
	}

	/**
	 * Load admin panel & assets.
	 */
	function admin_init() {
		global $pagenow;

		if ( in_array( $pagenow, array( 'post.php', 'page.php', 'post-new.php', 'post-edit.php' ) ) ) {
			if (
				( isset( $_GET['post'] ) && 'newsletter' === get_post_type( $_GET['post'] ) ) ||
				( isset( $_GET['post_type'] ) && 'newsletter' === $_GET['post_type'] )
			) {
				// Shortcode used with ALO EasyMail.
				$this->plugin = array_values( $this->supported_plugins )[0];
			}
		} elseif ( 'admin.php' === $pagenow && isset( $_GET['page'] ) ) {
			if ( 'mailpoet-newsletter-editor' === $_GET['page'] ) {
				// Shortcode used with MailPoet.
				$this->plugin = array_values( $this->supported_plugins )[1];
			} elseif ( 'sp-emails' === $_GET['page'] ) {
				// Shortcode used with SendPress.
				$this->plugin = array_values( $this->supported_plugins )[2];
			}
		}

		// Add editor button and render panel HTML only if user can edit posts and pages, is on an editor page and has rich editing enabled.
		if (
			! in_array( $this->plugin, $this->supported_plugins ) ||
			( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'edit_pages' ) ) ||
			get_user_option( 'rich_editing' ) == 'false'
		) {
			return;
		}

		// Array of localized JS data.
		$localized = array(
			'assets'       => WPR_ADS_URI . 'plugins/shortcode-builder/',
			'button_title' => esc_html__( 'inboxAds', 'wpr' ),
		);

		if ( 'MailPoet' === $this->plugin ) {
			// MailPoet requires directly inserting scripts and styles into the template.
			global $wpr_inboxads_localized, $wpr_inboxads_shortcode_builder;
			$wpr_inboxads_localized = $localized;
			$wpr_inboxads_shortcode_builder = $this;
			return;
		}

		// Enqueue scripts and styles.
		add_filter( 'mce_external_plugins', array( $this, 'add_plugin' ), 99 );
		add_filter( 'mce_buttons', array( $this, 'reg_button' ), 99 );
		add_action( 'admin_footer', array( $this, 'display_panel' ) );

		wp_enqueue_style( 'wpr_inboxads_shortcode_builder', WPR_ADS_URI . 'plugins/shortcode-builder/shortcode-builder.css' );

		wp_localize_script( 'jquery', 'wpr_shortcode', $localized );
	}

	/**
	 * Add the shortcode builder button in the editor toolbar.
	 */
	public function add_plugin( $plugin_array ) {
		$plugin_array['inboxads'] = WPR_ADS_URI . 'plugins/shortcode-builder/admin.js';
		return $plugin_array;
	}

	/**
	 * Add the shortcode builder button in the editor toolbar.
	 */
	public function reg_button( $buttons ) {
		array_push( $buttons, 'inboxads' );
		return $buttons;
	}

	/**
	 * Output the panel HTML
	 */
	public function display_panel() {
		?>
		<link rel="stylesheet" type="text/css" href="https://use.typekit.net/ydf6dyx.css">
		
		<a href="#TB_inline?width=600&amp;height=800&amp;inlineId=wpr-inboxads-panel" id="wpr-inboxads-trigger" class="thickbox" title="<?php esc_attr_e( 'Create a Zone', 'wpr' ); ?>"></a>

		<div id="wpr-inboxads-panel" style="display: none;">
			<div id="wpr-inboxads-wrap">
				<div class="wpr-panel-body">
					<form action="" id="wpr-inboxads-shortcode-form">

						<div class="header">
							<?php esc_html_e( 'Fill out the form below to add a zone shortcode to your newsletter', 'wpr' ); ?>
						</div>

						<div class="wpr-input-row">
							<label for="inboxads-zone-name"><?php esc_html_e( 'Name', 'wpr' ); ?>:</label>
							<span class="inboxads-label-desc"><?php esc_html_e( 'Enter a name for your zone', 'wpr' ); ?></span>
							<input type="text" id="inboxads-zone-name" placeholder="<?php esc_attr_e( 'eg. Middle Right Zone', 'wpr' ); ?>">
						</div>

						<div class="wpr-input-row" style="display:none">
							<label for="inboxads-zone-type"><?php esc_html_e( 'Newsletter', 'wpr' ); ?></label>
							<select id="inboxads-zone-type" disabled>
								<option value=""><?php esc_html_e( 'Select a newsletter...', 'wpr' ); ?></option>
								<?php

								foreach ( $this->supported_plugins as $plugin => $name ) {
									if ( ! is_plugin_active( $plugin ) && ! is_plugin_active_for_network( $plugin ) ) {
										continue;
									}

									?>
									<option value="<?php echo esc_attr( $name ); ?>" <?php selected( $this->plugin, $name ); ?>><?php echo esc_html( $name ); ?></option>
									<?php
								}

								?>
							</select>
							<small class="desc"><?php esc_html_e( 'Newsletter this zone will be associated to.', 'wpr' ); ?></small>
						</div>

						<div class="wpr-input-row">
							<label for="inboxads-zone-size"><?php esc_html_e( 'Format', 'wpr' ); ?>:</label>
							<span class="inboxads-label-desc"><?php esc_html_e( 'Choose the desired size for your ad zone', 'wpr' ); ?></span>
							<select id="inboxads-zone-size" style="display:none">
								<option value=""><?php esc_html_e( 'Select a size...', 'wpr' ); ?></option>
								<?php foreach ( inboxads_formats() as $id => $label ) { ?>
									<option value="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></option>
								<?php } ?>
							</select>
							<div class="ZonePreview">
								<div>
									<img src="https://publishers.inboxads.com/Content/Images/Previews/rec.png" />
									<span>Zone Preview:</span>
									<div class="imgp"></div>
									<div class="txtp">
										Select an ad zone to see<br />
										a preview of its layout<br />
										and position.
									</div>
								</div>
							</div>
							<div class="ZoneFormats">
								<span class="Subtitle">Rectangle:</span>
								<ul>
									<li rel="2" class="rec_300_250">
										300 x 250
										<div><div></div></div>
									</li><li rel="21" class="rec_366_280">
										336 x 280
										<div><div></div></div>
									</li>
								</ul>
								<span class="Subtitle">Landscape:</span>
								<ul>
									<li rel="27" class="rec_320_50">
										320 x 50
										<div><div></div></div>
									</li><li rel="22" class="rec_600_155">
										600 x 155
										<div><div></div></div>
									</li><li rel="10" class="src_600_300">
										600 x 300
										<div>
											<table>
												<tr><td></td><td></td><td></td><td></td></tr>
											</table>
										</div>
									</li><li rel="4" class="rec_728_90">
										728 x 90
										<div><div></div></div>
									</li><li rel="23" class="rec_970_90">
										970 x 90
										<div><div></div></div>
									</li><li rel="3" class="rec_970_250">
										970 x 250
										<div><div></div></div>
									</li>
								</ul>
								<span class="Subtitle">Tower:</span>
								<ul>
									<li rel="26" class="rec_160_600">
										160 x 600
										<div><div></div></div>
									</li><li rel="25" class="rec_300_600">
										300 x 600
										<div><div></div></div>
									</li><li rel="24" class="rec_300_1050">
										300 x 1050
										<div><div></div></div>
									</li>
								</ul>
							</div>
						</div>

						<div class="footer">
							<input type="submit" id="wpr-inboxads-shortcode-add" class="button add-sc" value="<?php esc_attr_e( 'Add Zone Shortcode', 'wpr' ); ?>" />
							<!-- <input type="button" id="wpr-inboxads-shortcode-cancel" class="button cancel-sc" onclick="tb_remove()" value="<?php esc_attr_e( 'Cancel', 'wpr' ); ?>" /> -->
						</div>

					</form>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Return HTML for the ad zone shortcode.
	 *
	 * @param array $atts
	 * @return string
	 */
	public static function render_shortcode( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'name' => '',
				'size' => 0,
				'type' => '',
			),
			$atts
		);

		if ( empty( $atts['type'] ) || empty( $atts['name'] ) || ! is_numeric( $atts['size'] ) ) {
			return '';
		}

		$options = $atts;
		unset( $options['type'] );

		return inboxads_display_zone( $atts['type'], $options );
	}
}

new InboxAds_Shortcode_Builder();
