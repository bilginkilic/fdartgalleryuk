<?php if ( ! defined('ETHEME_FW')) exit('No direct script access allowed');
/**
 * Template "Content" for ET_Setup_Wizard.
 * @package ET_Setup_Wizard
 * @since 9.5.0
 * @version 1.0.0
 */

$step = isset($_GET['step']) ? sanitize_key($_GET['step']) : 'welcome';
$template_path = get_template_directory() . '/framework/setup-wizard/templates/steps/';


include  get_template_directory() . '/framework/setup-wizard/templates/header.php';

if (file_exists($template_path . $step . '.php')) {
	include $template_path . $step . '.php';
} else {
	include $template_path . 'welcome.php';
}

include get_template_directory() . '/framework/setup-wizard/templates/footer.php';
