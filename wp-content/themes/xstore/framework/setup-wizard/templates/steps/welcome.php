<?php if ( ! defined('ETHEME_FW')) exit('No direct script access allowed');
/**
 * Template "Welcome" - step for ET_Setup_Wizard.
 * @package ET_Setup_Wizard
 * @since 9.5.0
 * @version 1.0.0
 */
?>

<?php
    $is_activated = etheme_is_activated();
?>

<div class="wizard-step wizard-welcome">
	<div class="wizard-step-content text-center">
        <div class="demos-preview">
            <?php foreach (array('car-parts', 'electronic-mega-market', 'minimal-fashion02') as $demo) { ?>
                <img class="lazyload lazyload-simple et-lazyload-fadeIn"
                src="<?php echo esc_html( ETHEME_BASE_URI . ETHEME_CODE ); ?>assets/images/placeholder-350x268.png"
                data-src="<?php echo apply_filters('etheme_protocol_url', ETHEME_BASE_URL . 'import/xstore-demos/' . esc_attr( $demo ) . '/screenshot.jpg'); ?>"
                data-old-src="<?php echo esc_html( ETHEME_BASE_URI . ETHEME_CODE ); ?>assets/images/placeholder-350x268.png"
                alt="<?php echo esc_attr( $demo ); ?>">
            <?php } ?>
        </div>
        <div class="container-mini">
            <br/><br/>
            <h2><?php esc_html_e('Welcome to the setup wizard for XStore', 'xstore'); ?></h2>
            <p><?php esc_html_e('Follow a few simple steps, and your professional-looking website will be live in just minutes.', 'xstore')?><?php ET_Setup_Wizard::get_tooltip(esc_html__('In the next steps, you’ll configure the basic settings, enable automatic updates, install the required plugins, and more.', 'xstore'), true); ?></p>
        </div>
	</div>
	<div class="wizard-step-controllers container-mini">
        <a href="<?php echo ET_Setup_Wizard::get_controls_url('language'); ?>" class="setup-button setup-button-arrow wizard-controllers-button"><?php esc_html_e('Let\'s go', 'xstore'); ?>
            <svg class="arrow-icon" xmlns="http://www.w3.org/2000/svg" width="1.3em" height="1.3em" viewBox="0 0 32 32">
                <g fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" stroke-miterlimit="10">
                    <circle class="arrow-icon--circle" cx="16" cy="16" r="15.12"></circle>
                    <path class="arrow-icon--arrow" d="M16.14 9.93L22.21 16l-6.07 6.07M8.23 16h13.98"></path>
                </g>
            </svg></a>
		<?php //elseif(!ET_Setup_Wizard::is_critical_requirements()): ?>
<!--			<a href="--><?php //echo ET_Setup_Wizard::get_controls_url('requirements'); ?><!--" class="wizard-controllers-button">next</a>-->
		<?php //elseif(ET_Setup_Wizard::is_installed_demo()): ?>
<!--			<a href="--><?php //echo ET_Setup_Wizard::get_controls_url('remove-content'); ?><!--" class="wizard-controllers-button">next</a>-->
		<?php //else: ?>
<!--			<a href="--><?php //echo ET_Setup_Wizard::get_controls_url('child-theme'); ?><!--" class="wizard-controllers-button">next</a>-->
		<?php // endif ?>
        <a href="<?php echo admin_url('admin.php?page=et-panel-welcome'); ?>" class="setup-button-link wizard-controllers-button"><?php echo esc_html__('I\'ll do this later', 'xstore'); ?></a>
	</div>
</div>
<?php if(ET_Setup_Wizard::is_critical_requirements()): ?>
    <script>
        alert('<?php esc_html_e('We have detected that there are critical system requirements that need to be optimized in order for the import process to works correctly.', 'xstore'); ?>');
        window.location.href = '<?php echo admin_url( 'admin.php?page=et-panel-system-requirements' ); ?>'; 
    </script>
<?php endif; ?>