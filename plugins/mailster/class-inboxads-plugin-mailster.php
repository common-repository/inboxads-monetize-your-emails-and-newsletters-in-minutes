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
class InboxAds_Plugin_Mailster extends InboxAds_Plugin {

	/**
	 * Name of the Mailster builder module.
	 *
	 * @var string
	 */
	public $module_name;

	/**
	 * Supported plugin slug.
	 *
	 * @var string
	 */
	protected $plugin = 'mailster/mailster.php';

	/**
	 * Initialize.
	 */
	public function init() {
		$this->module_name = __( 'InboxAds Zone', 'wpr' );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'mailster_iframe_script_styles', array( $this, 'enqueue_iframe_scripts' ) );
		add_action( 'wp_ajax_wpr_inboxads_plugin_mailster_get_code', array( __CLASS__, 'ajax_get_code' ) );
		add_filter( 'mailster_campaign_content', array( __CLASS__, 'campaign_content' ), 10, 3 );

		add_filter( 'mailster_replace_link', array( __CLASS__, 'inboxads_link_decrypt' ), 10, 4 );
	}

	public static function inboxads_link_decrypt($new_link, $base, $hash, $campaign_id) {
		$url = parse_url($new_link);

		if (!isset($url['query'])) {
			$part = explode('/', $url['path']);
			$url['query'] = $part[1] . '=' . $part[2] . '&k=' . $part['3'] . '&t=' . $part[4];
		}
		
		parse_str($url['query'], $params);
		$decoded = apply_filters( 'mailster_decode_link', base64_decode( strtr( $params['t'], '-_', '+/' ) ), $params['t'] );

		if (strstr($decoded, 'inboxads/api'))
			return $decoded;
		else
			return $new_link;
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook
	 */
	public function enqueue_scripts( $hook ) {
		global $post;

		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ) ) || ! $post || 'newsletter' !== get_post_type( $post ) ) {
			// Not on a Mailster newsletter page.
			return;
		}

		wp_enqueue_script( 'wpr-inboxads-plugin-mailster', WPR_ADS_URI . 'plugins/mailster/scripts.js', array( 'jquery' ), false, true );
		$js_data = array(
			'ajaxurl'       => admin_url( 'admin-ajax.php' ),
			'module_name'   => $this->module_name,
			'module_edit'   => '<a class="mailster-btn editbutton wpr-inboxads-edit" title="' . esc_attr( 'Edit zone', 'wpr' ) . '"></a>',
			'module_form'   => preg_replace( '/\s{2,}/', '', $this->get_module_form() ),
			'module_block'  => preg_replace( '/\s{2,}/', '', $this->get_module_block() ),
			'module_button' => preg_replace( '/\s{2,}/', '', $this->get_module_button() ),
		);
		wp_localize_script( 'wpr-inboxads-plugin-mailster', 'wpr_mailster_js', $js_data );
	}

	/**
	 * Wnqueue scripts and styles for the Mailster preview iframe.
	 */
	public function enqueue_iframe_scripts() {
		wp_register_style( 'wpr-inboxads-plugin-mailster', WPR_ADS_URI . 'plugins/mailster/style.css' );
		wp_print_styles( 'wpr-inboxads-plugin-mailster' );

		wp_register_style( 'wpr-inboxads-plugin-mailster-font', 'https://use.typekit.net/ydf6dyx.css' );
		wp_print_styles( 'wpr-inboxads-plugin-mailster-font' );
	}

	/**
	 * Return HTML string for the module block.
	 *
	 * @return string
	 */
	public function get_module_block() {
		ob_start();

		?>
		<module label="<?php echo esc_html( $this->module_name ); ?>" class="wpr-inboxads-module">
			<table width="{{wpr_ia_width}}" cellpadding="0" cellspacing="0" class="wrap body wpr-indexads-module-wrap">
				<tr>
					<td valign="top" align="left">

						<div class="wpr-inboxads-module-settings">
							<h3><?php esc_html_e( 'Create a Zone', 'wpr' ); ?></h3>
							<h4><?php esc_html_e( 'Fill out the form below to add a zone shortcode to your newsletter', 'wpr' ); ?></h4>

							<div class="settings-keeper">
								<span class="name"></span>
								<span class="size"></span>
							</div>

							<div class="form-keeper">
								<?php echo $this->get_module_form(); ?>
							</div>
						</div>

						<div class="wpr-inboxads-module-output"></div>

					</td>
				</tr>
			</table>
		</module>
		<?php

		return ob_get_clean();
	}

	/**
	 * Return HTML string for the module button.
	 *
	 * @return string
	 */
	public function get_module_button() {
		$i = '{{wpr_ia_index}}';

		ob_start();

		?>
		<li data-id="<?php echo $i; ?>" draggable="true">
			<a class="mailster-btn addmodule has-screenshot" style="background-image:url('<?php echo WPR_ADS_URI . 'plugins/mailster/inboxads.png' ?>');height:81px;" title="<?php esc_attr_e( 'Click to add an InboxAds Zone', 'wpr' ); ?>" data-id="<?php echo $i; ?>">
				<span><?php echo esc_html( $this->module_name ); ?></span><span class="hidden"><?php echo esc_html( strtolower( $this->module_name ) ); ?></span>
			</a>
		</li>
		<?php

		return ob_get_clean();
	}

	/**
	 * Return HTML string for the module settings form.
	 *
	 * @return string
	 */
	public function get_module_form() {
		ob_start();

		?>
		<form action="" class="wpr-inboxads-module-form">

			<fieldset>
				<label><?php esc_html_e( 'Name', 'wpr' ); ?>:</label>
				<input type="text" class="inboxads-zone-name" placeholder="<?php esc_html_e( 'eg. Middle Right Zone', 'wpr' ); ?>" required>
			</fieldset>

			<fieldset style="display:none">
				<label><?php esc_html_e( 'Size', 'wpr' ); ?></label>
				<select class="inboxads-zone-size" required>
					<?php foreach ( inboxads_formats() as $id => $label ) { ?>
						<option value="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php } ?>
				</select>
			</fieldset>

			<fieldset>
				<label><?php esc_html_e( 'Zone Format', 'wpr' ); ?>:</label>
				<span><?php esc_html_e( 'Choose the desired size for your ad zone', 'wpr' ); ?></span>
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
						<li rel="2" class="rec_300_250" onclick="parent.idsck(2)">
							300 x 250
							<div><div></div></div>
						</li><li rel="21" class="rec_366_280" onclick="parent.idsck(21)">
							336 x 280
							<div><div></div></div>
						</li>
					</ul>
					<span class="Subtitle">Landscape:</span>
					<ul>
						<li rel="27" class="rec_320_50" onclick="parent.idsck(27)">
							320 x 50
							<div><div></div></div>
						</li><li rel="22" class="rec_600_155" onclick="parent.idsck(22)">
							600 x 155
							<div><div></div></div>
						</li><li rel="10" class="src_600_300" onclick="parent.idsck(10)">
							600 x 300
							<div>
								<table>
									<tr><td></td><td></td><td></td><td></td></tr>
								</table>
							</div>
						</li><li rel="4" class="rec_728_90" onclick="parent.idsck(4)">
							728 x 90
							<div><div></div></div>
						</li><li rel="23" class="rec_970_90" onclick="parent.idsck(23)">
							970 x 90
							<div><div></div></div>
						</li><li rel="3" class="rec_970_250" onclick="parent.idsck(3)">
							970 x 250
							<div><div></div></div>
						</li>
					</ul>
					<span class="Subtitle">Tower:</span>
					<ul>
						<li rel="26" class="rec_160_600" onclick="parent.idsck(26)">
							160 x 600
							<div><div></div></div>
						</li><li rel="25" class="rec_300_600" onclick="parent.idsck(25)">
							300 x 600
							<div><div></div></div>
						</li><li rel="24" class="rec_300_1050" onclick="parent.idsck(24)">
							300 x 1050
							<div><div></div></div>
						</li>
					</ul>
				</div>
				<div class="ZoneJss"></div>
			</fieldset>

			<fieldset class="submit">
				<button type="submit"><?php esc_html_e( 'Add Zone Shortcode', 'wpr' ); ?></button>
			</fieldset>

		</form>
		<?php

		return ob_get_clean();
	}

	/**
	 * Remove ad zone module management code from campaign/mail content.
	 * Replace email placeholder with subscriber email address.
	 *
	 * @param string $content
	 * @param object $campaign
	 * @param object $subscriber
	 * @return string
	 */
	public static function campaign_content( $content, $campaign, $subscriber ) {
		if ( ! class_exists( 'DOMDocument' ) || ! class_exists( 'DomXPath' ) ) {
			return $content;
		}

		$doc = new \DOMDocument;
		$doc->loadHTML( $content );
		$xpath = new \DOMXPath( $doc );

		$module_settings = $xpath->query( "//*[contains(@class, 'wpr-inboxads-module-settings')]" );
		if ( $module_settings ) {
			// Remove module settings from ad zones so they don't appear in emails.
			foreach ( $module_settings as $module_setting ) {
				$module_setting->parentNode->removeChild( $module_setting ); // phpcs:ignore
			}
		}

		$content = $doc->saveHTML();

		if ( ! empty( $subscriber->email ) ) {
			$content = str_replace( '__inboxads_email__', $subscriber->email, $content );
		}

		return $content;
	}

	/**
	 * Retrieve ad zone code via AJAX.
	 */
	public static function ajax_get_code() {
		$options = array(
			'name' => ! empty( $_POST['name'] ) ? sanitize_text_field($_POST['name']) : '',
			'size' => ! empty( $_POST['size'] ) ? filter_var($_POST['size'], FILTER_SANITIZE_NUMBER_INT) : 0,
		);

		$code = inboxads_display_zone( 'Mailster', $options );

		if ( empty( $code ) ) {
			$code = __( 'The ad zone could not be created. Please make sure you have entered a name and size, and that your InboxAds account is connected.', 'wpr' );
		}

		return wp_send_json(
			array(
				'html' => str_replace( '{{email}}', '__inboxads_email__', $code ),
			)
		);
	}
}

new InboxAds_Plugin_Mailster();
