<?php if ( ! defined('ETHEME_FW')) exit('No direct script access allowed');
/**
 * Template "version-compare" - step for ET_Setup_Wizard.
 * @package ET_Setup_Wizard
 * @since 9.5.0
 * @version 1.0.0
 */

?>

<div class="wizard-step wizard-compare">
	<div class="wizard-step-content">
		<h1>version-compare theme</h1>
		<?php
		if ( get_template_directory() !== get_stylesheet_directory() ) {
			$theme = wp_get_theme( 'xstore' );
		} else {
			$theme = wp_get_theme();
		}

		if (defined('ET_CORE_THEME_MIN_VERSION')){
			$THEME_MIN_VERSION = ET_CORE_THEME_MIN_VERSION;
		} else {
			$THEME_MIN_VERSION = 0;
		}
		$required = '';

		if (defined('ET_CORE_THEME_MIN_VERSION')){
			if ( $theme->name == ('XStore') &&  version_compare( $theme->version, ET_CORE_THEME_MIN_VERSION, '<' ) ){
				$required = 'required';
			}
		}
		?>
		<div class="et_popup-step et_step-versions-compare hidden <?php echo esc_attr($required); ?>">
			<br/><h3 class="et_step-title text-center"><?php echo esc_html__('Versions Compare','xstore'); ?></h3>

			<div class="et-message et-info et-theme-version-info" data-theme-min-version="<?php echo esc_attr($THEME_MIN_VERSION); ?>">
				<?php echo esc_html($theme->name); ?> Core plugin requires the following theme: <a class="" href="https://xstore.8theme.com/update-history/" target="_blank"><strong>XStore v.{{{version}}}.</strong></a>
				To continue - update your theme. Please watch the <a class="" href="https://www.youtube.com/watch?v=kPo0fiNY4to&list=PLMqMSqDgPNmCCyem_z9l2ZJ1owQUaFCE3&index=2" target="_blank">video</a>.
			</div>
		</div>
	</div>
	<div class="wizard-step-controllers">
		<a href="<?php echo ET_Setup_Wizard::get_controls_url('register'); ?>" class="wizard-controllers-button"><?php echo ET_Setup_Wizard::texts('next'); ?></a>
	</div>
</div>