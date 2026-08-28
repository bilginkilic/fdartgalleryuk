<?php if ( ! defined('ETHEME_FW')) exit('No direct script access allowed');
/**
 * Template "Site title & tagline" - step for ET_Setup_Wizard.
 * @package ET_Setup_Wizard
 * @since 9.5.0
 * @version 1.0.0
 */

$is_activated = etheme_is_activated();

?>

<div class="wizard-step container-mini wizard-child-theme">
	<div class="wizard-step-content">
		<?php
		    $site_title = get_bloginfo('name');
            $site_tagline = get_bloginfo('description');
		?>
        <div class="wizard-step-heading text-center">
            <h2><?php esc_html_e('Website title & description', 'xstore'); ?></h2>
            <p>
                <?php esc_html_e('Metadata used to define how your site appears in search.', 'xstore') ?>
            </p>
        </div>
        <form id="et_setup-basis" class="text-center" action="<?php echo ET_Setup_Wizard::get_controls_url('basic-updated'); ?>" method="POST" style="max-width: 320px; margin: 0 auto;">
            <div class="child-theme-input" style="margin-bottom: 20px;">
                <label for="site_title">
                    <?php esc_html_e('SITE TITLE', 'xstore'); ?>
                </label>
                <span style="display: flex;align-items: center;gap: 10px;margin-right: calc(-10px - 1em);">
                    <input class="text-center" type="text" id="site_title" name="site_title" value="<?php echo esc_attr($site_title); ?>" placeholder="<?php echo esc_attr($site_title); ?>">
                    <?php ET_Setup_Wizard::get_tooltip(sprintf(esc_html__('Enter your website or brand name. This appears in the site header and is used in the %s tag. Keep it concise (under ~60 characters). SEO plugins may build page titles based on this.', 'xstore'), "&lt;title&gt;"), true); ?>
                </span>
            </div>
            <div class="child-theme-input" style="margin-bottom: 20px;">
                <label for="site_tagline">
                    <?php esc_html_e('TAGLINE', 'xstore'); ?>
                </label>
                <span style="display: flex;align-items: center;gap: 10px;margin-right: calc(-10px - 1em);">
                    <input class="text-center" type="text" id="site_tagline" name="site_tagline" value="<?php echo esc_attr($site_tagline); ?>" placeholder="<?php echo esc_attr($site_tagline); ?>">
                    <?php ET_Setup_Wizard::get_tooltip(__('A short slogan or description of your site. Typically 3–7 words (up to ~120 characters). Appears next to the site title and may serve as the default meta description for the homepage. Example: "Simple recipes for everyday inspiration"', 'xstore'), true); ?>
                </span>
            </div>
        </form>
	</div>
	<div class="wizard-step-controllers">
        <a href="" class="setup-button wizard-controllers-button update-site-basic"><?php esc_attr_e('Save & continue', 'xstore'); ?></a>
        <?php if($is_activated) :?>
            <a href="<?php echo ET_Setup_Wizard::get_controls_url('child-theme'); ?>" class="setup-button-link wizard-controllers-button"><?php echo ET_Setup_Wizard::texts('skip'); ?></a>
        <?php else:?>
            <a href="<?php echo ET_Setup_Wizard::get_controls_url('register'); ?>" class="setup-button-link wizard-controllers-button"><?php echo ET_Setup_Wizard::texts('skip'); ?></a>
        <?php endif;?>
	</div>
</div>
