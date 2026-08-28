<?php if ( ! defined('ETHEME_FW')) exit('No direct script access allowed');
/**
 * Template "Child theme" - step for ET_Setup_Wizard.
 * @package ET_Setup_Wizard
 * @since 9.5.0
 * @version 1.0.0
 */

?>

<div class="wizard-step container-mini wizard-child-theme">
	<div class="wizard-step-content">
		<?php
		$theme = get_option('xstore_has_child') ? wp_get_theme(get_option('xstore_has_child') )->Name : 'Xstore child';
		$template = get_template();
		?>
        <div class="wizard-step-heading text-center">
            <h2><?php esc_html_e('Setup XStore child theme (optional)', 'xstore'); ?></h2>
            <p>
                <?php esc_html_e('A child theme lets you update XSTORE safely and keep your changes. We strongly recommend using it!', 'xstore') ?>
                <?php ET_Setup_Wizard::get_tooltip(esc_html__('A child theme lets you safely update XStore without losing your custom changes. Ideal for developers or anyone planning to modify code, styles, or templates.', 'xstore'), true); ?>
            </p>
        </div>
        <form id="et_create-child_theme-form" class="text-center" action="" method="POST" style="max-width: 320px; margin: 0 auto;">
            <div class="child-theme-input" style="margin-bottom: 20px;">
                <label for="theme_name">
                    <?php esc_html_e('Child theme name', 'xstore'); ?>
                </label>
                <input class="text-center" type="text" id="theme_name" name="theme_name" value="<?php echo esc_attr($theme); ?>" placeholder="<?php echo esc_attr($theme); ?>">
            </div>
            <div class="child-theme-input" style="margin-bottom: 20px;">
                <label for="theme_template">
                    <?php esc_html_e('Parent Theme Template', 'xstore'); ?>
                </label>
                <div style="display: flex;align-items: center;gap: 10px;margin-right: calc(-10px - 1em);">
                    <input class="text-center" type="text" id="theme_template" name="theme_template" value="<?php echo esc_attr($template); ?>" placeholder="<?php echo esc_attr($template); ?>" readonly>
                    <span class="mtips mtips-lg helping">
                        <svg width="1em" height="1em" viewBox="0 0 21 21" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M10.5 0C4.70996 0 0 4.70996 0 10.5C0 16.29 4.70996 21 10.5 21C16.29 21 21 16.29 21 10.5C21 4.70996 16.29 0 10.5 0ZM10.5 1.75C15.3433 1.75 19.25 5.65674 19.25 10.5C19.25 15.3433 15.3433 19.25 10.5 19.25C5.65674 19.25 1.75 15.3433 1.75 10.5C1.75 5.65674 5.65674 1.75 10.5 1.75ZM10.5 5.25C8.57568 5.25 7 6.82568 7 8.75H8.75C8.75 7.77246 9.52246 7 10.5 7C11.4775 7 12.25 7.77246 12.25 8.75C12.25 9.41992 11.8193 10.0146 11.1836 10.2266L10.8281 10.3359C10.1138 10.5718 9.625 11.2554 9.625 12.0039V13.125H11.375V12.0039L11.7305 11.8945C13.0771 11.4468 14 10.1685 14 8.75C14 6.82568 12.4243 5.25 10.5 5.25ZM9.625 14V15.75H11.375V14H9.625Z" fill="currentColor"></path></svg>
                        <span class="mt-mes">
                            <?php esc_html_e('Parent theme is defined by the ‘Template’ in child theme’s style.css; to change it, rename its folder in /wp-content/themes/ and restart setup from the first step to apply changes.', 'xstore'); ?>
                        </span>
                    </span>
                </div>
            </div>
        </form>
        <p class="et-success et-message hidden">
            <?php esc_html_e('Child Theme ', 'xstore'); ?>
            <strong class="new-theme-title"></strong>
            <?php esc_html_e('created and activated successfully! Folder is located in:', 'xstore'); ?>
            <strong class="new-theme-path"></strong>
        </p>
        <p class="et-error et-message hidden">
            <?php esc_html_e('Can not create or activate new child theme. Please contact our support.', 'xstore'); ?>
        </p>
	</div>
	<div class="wizard-step-controllers">
        <a href="" class="setup-button wizard-controllers-button create-child-theme"><?php esc_html_e('Install child theme', 'xstore'); ?></a>
		<a href="<?php echo ET_Setup_Wizard::get_controls_url('demos'); ?>" class="setup-button-link wizard-controllers-button"><?php echo ET_Setup_Wizard::texts('skip'); ?></a>
        <input type="hidden" name="nonce_etheme-create_child_theme" value="<?php echo wp_create_nonce( 'etheme-create_child_theme' ); ?>">
	</div>
</div>
