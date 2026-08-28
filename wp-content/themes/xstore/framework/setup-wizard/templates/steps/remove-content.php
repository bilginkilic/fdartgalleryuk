<?php if ( ! defined('ETHEME_FW')) exit('No direct script access allowed');
/**
 * Template "remove-content" - step for ET_Setup_Wizard.
 * @package ET_Setup_Wizard
 * @since 9.5.0
 * @version 1.0.0
 */

?>

<div class="wizard-step wizard-remove-content">
	<div class="wizard-step-content">
        <h3 class="et_step-title text-center"><?php esc_html_e('Content Reset Required', 'xstore'); ?></h3>
        <p class="step-description et_notice text-left">
			<?php esc_html_e('Before installing a prebuilt website, it\'s recommended to use the "Content Reset" feature. This ensures a seamless and trouble-free experience when setting up your new website.', 'xstore') ?>
        </p>
        <p>
			<?php esc_html_e('It\'s crucial to exercise caution when using it, as it will erase data, customizations, images, and products. Be sure to back up any essential content before initiating a reset.', 'xstore'); ?>
        </p>
	</div>
	<div class="wizard-step-controllers">
        <a href="" class="wizard-controllers-button"><?php esc_html_e('Go to Content Reset', 'xstore'); ?></a>
		<a href="<?php echo ET_Setup_Wizard::get_controls_url('register'); ?>" class="wizard-controllers-button"><?php echo ET_Setup_Wizard::texts('skip'); ?></a>
	</div>
</div>