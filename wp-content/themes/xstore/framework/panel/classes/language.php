<?php if ( ! defined( 'ABSPATH' ) ) exit( 'No direct script access allowed' );
/**
 * Etheme Admin Panel Language Class.
 *
 *
 * @since   7.2.0
 * @version 1.0.1
 *
 */
class ETC_Language{
	
	// ! Main construct/ add actions
	function __construct(){
	}
	
	public function et_switch_language(){
		$_POST['value'] = $_POST['value'] == 'false' ? false : true;
		update_option( 'etheme_builtin_language', $_POST['value']);
		die();
	}
    public function et_built_in_language_select(){
       
        if (isset($_POST['language']) && ! empty($_POST['language'])){
            ETC_Languages::setup_translation($_POST['language']);
        }

		if (isset($_POST['admin_panel_language'])){
            ETC_Languages::setup_admin_translation($_POST['admin_panel_language']);
        }

		update_option( 'etheme_builtin_language_last_sync', false);
    }

	public function et_built_in_language_sync() {
		if (isset($_POST['language']) && ! empty($_POST['language'])){
            ETC_Languages::setup_translation($_POST['language']);
        }
		if (isset($_POST['admin_panel_language'])){
            ETC_Languages::setup_admin_translation($_POST['admin_panel_language']);
        }
		update_option( 'etheme_builtin_language_last_sync', time());
	}
}

new ETC_Language();