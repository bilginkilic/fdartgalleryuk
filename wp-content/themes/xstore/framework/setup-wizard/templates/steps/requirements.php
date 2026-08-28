<?php if ( ! defined('ETHEME_FW')) exit('No direct script access allowed');
/**
 * Template "requirements" - step for ET_Setup_Wizard.
 * @package ET_Setup_Wizard
 * @since 9.5.0
 * @version 1.0.0
 */

?>

<div class="wizard-step wizard-requirements">
	<div class="wizard-step-content">
		<?php
            $classes['et_step-reset'] = 'hidden';
            $system = class_exists('Etheme_System_Requirements') ? Etheme_System_Requirements::get_instance() : new Etheme_System_Requirements();
            $system->system_test(true);
            $result = $system->result();
		    $system->html();
		?>
	</div>
	<div class="wizard-step-controllers">
		<a href="<?php echo ET_Setup_Wizard::get_controls_url('register'); ?>" class="wizard-controllers-button"><?php echo ET_Setup_Wizard::texts('next'); ?></a>
	</div>
</div>