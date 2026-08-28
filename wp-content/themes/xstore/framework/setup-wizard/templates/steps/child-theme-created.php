<?php if ( ! defined('ETHEME_FW')) exit('No direct script access allowed');
/**
 * Template "Child theme created" - step for ET_Setup_Wizard.
 * @package ET_Setup_Wizard
 * @since 9.5.0
 * @version 1.0.0
 */

$current_theme = wp_get_theme();

?>
<div class="wizard-step container-mini wizard-child-theme-created">
    <div class="wizard-step-content text-center">
        <div class="wizard-step-heading text-center">
            <span style="line-height: 1;padding: 10px;margin-bottom:15px;background: #C8E6C9;border-radius: 50%;display: inline-flex;align-items: center;">
                <svg width="2em" height="2em" viewBox="0 0 44 34" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M38.7608 0.260834L15.3525 23.6967L5.68909 14.0608L0.810791 18.9391L15.3594 33.4533L43.6408 5.13913L38.7608 0.260834Z" fill="#4CAF50"></path>
                </svg>
            </span>
            <h2>"<?php echo esc_html($current_theme->get('Name')); ?>" <?php esc_html_e('Child Theme successfully installed', 'xstore'); ?></h2>
            <p>
                <?php esc_attr_e('Child theme XStore Child created and activated! Folder is located in', 'xstore'); ?>
                wp-content/themes/<?php echo basename( $current_theme->get_stylesheet_directory() ); ?>
            </p>
        </div>
    </div>
    <div class="wizard-step-controllers">
        <a href="<?php echo ET_Setup_Wizard::get_controls_url('demos'); ?>" class="setup-button setup-button-arrow wizard-controllers-button"><?php echo ET_Setup_Wizard::texts('next'); ?>
            <svg class="arrow-icon" xmlns="http://www.w3.org/2000/svg" width="1.3em" height="1.3em" viewBox="0 0 32 32">
                <g fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" stroke-miterlimit="10">
                    <circle class="arrow-icon--circle" cx="16" cy="16" r="15.12"></circle>
                    <path class="arrow-icon--arrow" d="M16.14 9.93L22.21 16l-6.07 6.07M8.23 16h13.98"></path>
                </g>
            </svg></a>
    </div>
</div>