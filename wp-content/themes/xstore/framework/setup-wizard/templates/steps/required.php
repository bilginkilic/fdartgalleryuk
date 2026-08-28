<?php if ( ! defined('ETHEME_FW')) exit('No direct script access allowed');
/**
 * Template "required" - step for ET_Setup_Wizard.
 * @package ET_Setup_Wizard
 * @since 9.5.0
 * @version 1.0.0
 */

?>

<div class="wizard-step wizard-required">
	<div class="wizard-step-content">
        <div class="et_popup-step et_step-required active">
            <div>
                <svg width="63.9" height="63" viewBox="0 0 71 70" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M58.47 20.4401C59.3979 20.4401 60.15 19.6879 60.15 18.7601C60.15 17.8322 59.3979 17.0801 58.47 17.0801C57.5422 17.0801 56.79 17.8322 56.79 18.7601C56.79 19.6879 57.5422 20.4401 58.47 20.4401Z" fill="#222222"></path>
                    <path d="M59.6601 22.8199C59.5201 23.2399 59.5201 23.7299 59.7301 24.1499C61.3401 27.5799 62.1801 31.2199 62.1801 34.9999C62.1801 36.6799 62.0401 38.4299 61.6901 40.0399C61.6201 40.4599 61.6901 40.9499 61.9701 41.2999C62.2501 41.6499 62.6001 41.9299 63.0201 41.9999C63.1601 41.9999 63.2301 42.0699 63.3701 42.0699C64.1401 42.0699 64.8401 41.5099 65.0501 40.7399C65.4001 38.8499 65.6101 36.8899 65.6101 34.9999C65.6101 30.6599 64.7001 26.5299 62.8101 22.6799C62.3901 21.8399 61.4101 21.4899 60.5701 21.9099C60.1501 22.0499 59.8001 22.3999 59.6601 22.8199Z" fill="#222222"></path>
                    <path d="M29.21 55.6502H42.72C43.63 55.6502 44.4 54.8802 44.4 53.9702V48.5802C44.4 47.6702 43.63 46.9002 42.72 46.9002H41.74V29.6102C41.74 28.7002 40.97 27.9302 40.06 27.9302H29.21C28.3 27.9302 27.53 28.7002 27.53 29.6102V35.0002C27.53 35.9102 28.3 36.6802 29.21 36.6802H30.19V46.8302H29.21C28.3 46.8302 27.53 47.6002 27.53 48.5102V53.9002C27.53 54.8802 28.3 55.6502 29.21 55.6502ZM40.06 50.2602H41.04V52.2902H30.96V50.2602H31.94C32.85 50.2602 33.62 49.4902 33.62 48.5802V35.0002C33.62 34.0902 32.85 33.3202 31.94 33.3202H30.96V31.2902H38.38V48.5102C38.38 49.4902 39.15 50.2602 40.06 50.2602Z" fill="#222222"></path>
                    <path d="M36 25.8999C39.15 25.8999 41.74 23.3099 41.74 20.1599C41.74 17.0099 39.15 14.4199 36 14.4199C32.85 14.4199 30.26 17.0099 30.26 20.1599C30.26 23.3099 32.85 25.8999 36 25.8999ZM33.62 20.0899C33.62 18.7599 34.67 17.7099 36 17.7099C37.33 17.7099 38.38 18.7599 38.38 20.0899C38.38 21.4199 37.33 22.4699 36 22.4699C34.67 22.4699 33.62 21.4199 33.62 20.0899Z" fill="#222222"></path>
                    <path d="M36.0001 0C17.0301 0 1.00009 16.03 1.00009 35C1.00009 41.37 2.82009 48.23 5.90009 53.34L1.07009 67.76C0.860086 68.39 1.00009 69.02 1.49009 69.51C1.84009 69.86 2.26009 70 2.68009 70C2.89009 70 3.03009 70 3.24009 69.93L17.6601 65.1C22.8401 68.18 29.7001 70 36.0001 70C54.9701 70 71.0001 53.97 71.0001 35C71.0001 16.03 54.9701 0 36.0001 0ZM9.33009 53.69C9.47009 53.2 9.40009 52.71 9.12009 52.22C6.11009 47.46 4.36009 41.02 4.36009 35C4.43009 17.85 18.8501 3.43 36.0001 3.43C53.1501 3.43 67.5701 17.92 67.5701 35C67.5701 52.08 53.1501 66.57 36.0001 66.57C29.9101 66.57 23.4701 64.75 18.7801 61.81C18.5001 61.67 18.2201 61.53 17.8701 61.53C17.6601 61.53 17.5201 61.53 17.3101 61.6L5.41009 65.59L9.33009 53.69Z" fill="#222222"></path>
                </svg>
            </div>
            <h3 class="et_demo-required-theme-plugin">
				<?php echo sprintf(esc_html__('This demo requires next versions of XStore theme v.%s and XStore Core plugin v.%s', 'xstore'),
					'<span class="min-theme-version">{{{min_theme_version}}}</span>',
					'<span class="min-plugin-version">{{{min_plugin_version}}}</span>');
				?></h3>
            <h3 class="et_demo-required-theme"><?php echo sprintf(esc_html__('This demo requires next version of XStore theme v.%s', 'xstore'),
					'<span class="min-theme-version">{{{min_theme_version}}}</span>'); ?></h3>
            <h3 class="et_demo-required-plugin"><?php echo sprintf(esc_html__('This demo requires next version of XStore Core plugin v.%s', 'xstore'),
					'<span class="min-plugin-version">{{{min_plugin_version}}}</span>'); ?></h3>
            <a class="et-button et-button-green no-loader" href="<?php echo ( is_multisite() && ! is_network_admin() ) ? network_admin_url( 'update-core.php?force-check=1' ): admin_url( 'update-core.php?force-check=1' ); ?>" target="_blank"><?php echo esc_html__('Update now', 'xstore'); ?></a>
        </div>
	</div>
	<div class="wizard-step-controllers">
		<a href="<?php echo ET_Setup_Wizard::get_controls_url('register'); ?>" class="wizard-controllers-button"><?php echo ET_Setup_Wizard::texts('skip'); ?></a>
	</div>
</div>