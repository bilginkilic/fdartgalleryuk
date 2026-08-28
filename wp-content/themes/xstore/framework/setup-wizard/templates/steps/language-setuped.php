<?php if ( ! defined('ETHEME_FW')) exit('No direct script access allowed');
/**
 * Template "Language Setuped" - step for ET_Setup_Wizard.
 * @package ET_Setup_Wizard
 * @since 9.5.0
 * @version 1.0.0
 */

$is_activated = etheme_is_activated();
?>

<div class="wizard-step container-mini wizard-child-theme">
	<div class="wizard-step-controllers">
        <?php if($is_activated) :?>
		    <a href="<?php echo ET_Setup_Wizard::get_controls_url('child-theme'); ?>" class="setup-button wizard-controllers-button"><?php echo ET_Setup_Wizard::texts('next'); ?></a>
        <?php else:?>
		    <a href="<?php echo ET_Setup_Wizard::get_controls_url('register'); ?>" class="setup-button wizard-controllers-button"><?php echo ET_Setup_Wizard::texts('next'); ?></a>
        <?php endif;?>
    </div>
</div>
