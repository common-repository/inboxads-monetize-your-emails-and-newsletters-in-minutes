<?php
/*
 * @var $options array contains all the options the current block we're ediging contains
 * @var $controls NewsletterControls
 */

namespace WPR;

?>

<link href="https://use.typekit.net/ydf6dyx.css" rel="stylesheet" />
<link href="<?php echo WPR_ADS_URI . 'plugins/newsletter'; ?>/newsletter.css" rel="stylesheet" />
<script src="<?php echo WPR_ADS_URI . 'plugins/newsletter'; ?>/newsletter.js"></script>

<div class="idsnl">
	<div class="title"><?php esc_html_e( 'Create a Zone', 'wpr' ); ?></div>
	<div class="subtext"><?php esc_html_e( 'Fill out the form below to add a zone shortcode to your newsletter', 'wpr' ); ?></div>

	<div class="formitem">
		<label><?php esc_html_e( 'Name', 'wpr' ); ?>:</label>
		<span class="labeldesc"><?php esc_html_e( 'Enter a name for your zone', 'wpr' ); ?></span>
		<?php $controls->text( 'name' ); ?>
	</div>

	<div class="formitem" style="display:none">
		<?php $controls->select( 'size', inboxads_formats() ); ?>
	</div>

	<div class="formitem">
		<label><?php esc_html_e( 'Format', 'wpr' ); ?>:</label>
		<span class="labeldesc"><?php esc_html_e( 'Choose the desired size for your ad zone', 'wpr' ); ?></span>
		<div class="formelem">
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
	</div>

	<div class="formitem">
		<button type="button" class="btn btn-success"><?php esc_html_e( 'Add Zone Shortcode', 'wpr' ); ?></button>
	</div>
</div>
