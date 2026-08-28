<?php if ( ! defined('ETHEME_FW')) exit('No direct script access allowed');
/**
 * Template "Footer" for ET_Setup_Wizard.
 * @package ET_Setup_Wizard
 * @since 9.5.0
 * @version 1.0.0
 */
?>

</div>
</main>
<input type="hidden" name="nonce_etheme-theme-actions" value="<?php echo wp_create_nonce( 'etheme_theme-actions' );?>">
<input id="nonce_etheme_panel_actions" type="hidden" name="nonce_etheme_panel_actions" value="<?php echo wp_create_nonce( 'nonce_etheme_panel_actions' ); ?>">
</div> <!-- .setup-wrapper -->
<script src="<?php echo esc_attr(get_template_directory_uri()); ?>/framework/setup-wizard/assets/js/scripts.js"></script>
</body>
</html>