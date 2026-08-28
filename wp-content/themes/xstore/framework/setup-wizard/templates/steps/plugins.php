<?php if ( ! defined('ETHEME_FW')) exit('No direct script access allowed');
/**
 * Template "plugins" - step for ET_Setup_Wizard.
 * @package ET_Setup_Wizard
 * @since 9.5.0
 * @version 1.0.0
 */
$plugins_class_file = get_template_directory() . '/framework/panel/classes/plugins.php';
if ( file_exists( $plugins_class_file ) ) {
	require_once $plugins_class_file;
}

$engine = $_GET['engine'];

$classes['et_step-reset'] = 'hidden';
$classes['et_navigate-next'] = 'hidden';
$classes['et_step-requirements'] = 'hidden';
$plugins = new Plugins();// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
$plugins = $plugins->get_popup_plugin_list($_GET['version']);// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

if(!count($plugins)){
	wp_safe_redirect( ET_Setup_Wizard::get_controls_url('install&engine=' . $engine . '&version=' . $_GET['version']) );
}

$global_admin_class = EthemeAdmin::get_instance();

$elementor_only_plugins = array(
    'elementor',
    'pro-elements'
);

$wpb_only_plugins = array(
    'js_composer',
    'wwp-vc-gmaps', 
    'mpc-massive',
);

?>

<div class="wizard-step wizard-plugins">
	<div class="wizard-step-content">
		<?php if ( count( $plugins ) ): ?>
            <div class="et_popup-step et_step-plugins">
                <div class="wizard-step-heading text-center">
                    <h2><?php echo esc_html__('Plugin installation & activation', 'xstore'); ?></h2>
                    <p><?php echo esc_html__('This demo requires some plugins to be installed.', 'xstore'); ?></p>
                </div>
                <div class="container-mini">
                    <ul class="et_popup-import-plugins et_setup-wizard-form with-scroll">
                       <li class="flex justify-content-between align-items-center hidden">
                           <label for="all-plugins">
                               <span class="flex align-items-center"><input id="all-plugins" class="all-plugins hidden" type="checkbox" checked>ALL PLUGINS</span>
                           </label>
                        </li>
                        <?php foreach ($plugins as $key => $value): ?>
                            <?php if($engine  == 'elementor' && in_array($key, $wpb_only_plugins)) continue; ?>
                            <?php if($engine  == 'wpb' && in_array($key, $elementor_only_plugins)) continue; ?>
                            <li class="et_popup-import-plugin flex justify-content-between align-items-center selected-to-install">
                                <?php
                                $notify_color = '#FF6F00';
                                $notify_bg_color = 'rgb(198 40 40 / 10%)'; ?>
                                <div class="flex align-items-center" style="width: 100%;">
                                <input id="<?php echo esc_attr($value['slug']); ?>" class="plugin-setup hidden" type="checkbox" checked>
                                <?php
                                    if ( in_array($value['slug'], array('js_composer', 'et-core-plugin', 'elementor', 'woocommerce'))) {
                                        $disable_next = true;
//                                        $notify_color = '#FF6F00';
//                                        $notify_bg_color = 'rgb(198 40 40 / 10%)';
                                    }

        //							if (strlen($value['name']) >= 27 ){
        //								echo substr( esc_html($value['name']), 0, - (strlen($value['name'])-25) ) . '...';
        //							} else {
                                ?>
                                    <label for="<?php echo esc_attr($value['slug']); ?>">
                                        <img src="<?php echo esc_html( ETHEME_BASE_URI . ETHEME_CODE ); ?>setup-wizard/img/plugins/<?php echo esc_attr($value['slug']) ?>.png" alt="<?php echo esc_attr( esc_html($value['name']) ); ?>">
                                        <?php echo esc_html($value['name']); ?>
                                    </label>
                                    <?php
        //							}
                                    ?>

                                    <span style="margin-left: 10px; margin-right: auto; display: inline-block; font-size: .75em; padding: 3px 5px; border-radius: 3px; background-color: <?php echo esc_attr($notify_bg_color); ?>; color: <?php echo esc_attr($notify_color); ?>">
                                        <?php echo esc_html__('Required', 'xstore'); ?>
                                    </span>

                                    <span
                                            class="plugins-install-btn selected-to-install"
                                            data-slug="<?php echo esc_attr($value['slug']); ?>"
                                            data-type="<?php echo esc_attr($value['btn_type']); ?>"
                                            data-process-text="<?php echo str_replace(array('install', 'activate', 'update'), array(esc_html__('Installing', 'xstore'), esc_html__('Activating', 'xstore'), esc_html__('Updating', 'xstore')), $value['btn_type']); ?>"
                                            data-success-text="<?php echo str_replace(array('install', 'activate', 'update'), array(esc_html__('Installed', 'xstore'), esc_html__('Activated', 'xstore'), esc_html__('Updated', 'xstore')), $value['btn_type']); ?>"
                                            style="line-height: 1.4;">
                                        <?php $global_admin_class->get_loader() ?>
                                        <span class="setup-button-link" style="font-size: 1em"><?php echo esc_html($value['btn_text']); ?></span>
                                    </span>
                                </div>
                            </li>
                        <?php endforeach ?>
                    </ul>
                    <span class="hidden et_plugin-nonce" data-plugin-nonce="<?php echo wp_create_nonce( 'envato_setup_nonce' ); ?>"></span>
                </div>
            </div>
		<?php endif ?>
	</div>
	<div class="wizard-step-controllers">
<!--        <a href="" class="setup-button et-button no-loader wizard-controllers-button plugins-install-btn-all install-with-all">-->
        <a href="" class="setup-button et-button no-loader wizard-controllers-button plugins-install-btn-all">
            <?php $global_admin_class->get_loader(true); ?>
            <?php echo esc_html__('Install & activate all plugins', 'xstore'); ?>
        </a>

        <a href="<?php echo ET_Setup_Wizard::get_controls_url('install&engine=' . $engine . '&version=' . $_GET['version']); ?>" class="setup-button setup-button-arrow plugins-next-btn wizard-controllers-button hidden"><?php echo esc_html__('Continue', 'xstore'); ?> <svg class="arrow-icon" xmlns="http://www.w3.org/2000/svg" width="1.3em" height="1.3em" viewBox="0 0 32 32">
                <g fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" stroke-miterlimit="10">
                    <circle class="arrow-icon--circle" cx="16" cy="16" r="15.12"></circle>
                    <path class="arrow-icon--arrow" d="M16.14 9.93L22.21 16l-6.07 6.07M8.23 16h13.98"></path>
                </g>
            </svg></a>
    </div>
</div>