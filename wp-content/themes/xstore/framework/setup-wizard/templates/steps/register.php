<?php if ( ! defined('ETHEME_FW')) exit('No direct script access allowed');
/**
 * Template "Register" - step for ET_Setup_Wizard.
 * @package ET_Setup_Wizard
 * @since 9.5.0
 * @version 1.0.0
 */

?>

<div class="wizard-step container-mini wizard-register">
	<div class="wizard-step-content">
        <div class="wizard-step-heading text-center">
            <h2><?php esc_html_e('Theme license', 'xstore'); ?></h2>
            <p>
                <?php esc_html_e('Activate your license to unlock the full potential of XStore and keep your website future-ready with premium features, demo imports, and automatic updates.', 'xstore'); ?>
            </p>
        </div>
        <div id="licence-form">
            <div>
                <form class="xstore-form" method="post" style="margin-bottom: 20px;gap: 10px;">
                    <span class="etheme-purchase-inner">
                        <input type="text" id="purchase-code" name="purchase-code" placeholder="Example: f20b1cdd-ee2a-1c32-a146-66eafe">
                        <?php ET_Setup_Wizard::get_tooltip(
                                '<ul>
                                                <li>1. '.sprintf(esc_html__( 'Log in to your Envato account and navigate to %s Downloads tab %s', 'xstore' ), '<a
                                                            href="https://themeforest.net/downloads">', '</a>').'
                                                </li>
                                                <li>2. '.sprintf(esc_html__( 'Locate the XStore theme in the list and click the corresponding %s Download %s button', 'xstore' ),
                                                        '<span>', '</span>').'
                                                </li>
                                                <li>3. '. sprintf(esc_html__( 'Select the %s"License Certificate & Purchase Code"%s option to download the file', 'xstore' ),
                                                        '<span>', '</span>') . '
                                                </li>
                                                <li>4. ' . sprintf(esc_html__( 'Open the downloaded document and copy the %s"Item Purchase Code"%s to your clipboard.', 'xstore' ),
                                                        '<span>', '</span>') . '
                                                </li>
                                            </ul>', true); ?>
                    </span>

                    <?php
                    
                    // var_dump(get_option( 'etheme_activated_data' ));
                    
                    ?>

                    <p>
                        <label for="is_dev"><input id="is_dev" name="is_dev" type="checkbox"><?php esc_html_e('Development domain', 'xstore'); ?></label>
                        <?php ET_Setup_Wizard::get_tooltip(esc_html__('If you bought a regular license, you can use our theme on one domain only. However, we allow you to activate the theme on two domains — one for your live (production) site and one for development (staging) — so you can get automatic updates on both.', 'xstore'), true); ?>
                    </p>
                    <p>
                        <label for="is_confirmed"><input id="is_confirmed" name="is_confirmed" type="checkbox"><?php esc_html_e('By confirming, I acknowledge that under Envato License Terms, each license is valid for one person and one project only. Multiple unregistered installations are a copyright violation. I authorize', 'xstore'); ?>
                            <a href="https://8theme.com/">8theme.com</a> 
                            <?php esc_html_e('to securely store my purchase code and user data for license management', 'xstore'); ?>
                        </label>
                    </p>
                    <div class="wizard-step-controllers text-center">
                        <button class="setup-button activate-license-btn" name="xstore-purchase-code"><svg width="1em" height="1em" viewBox="0 0 23 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M6 8C5.45 8 4.97917 7.80417 4.5875 7.4125C4.19583 7.02083 4 6.55 4 6C4 5.45 4.19583 4.97917 4.5875 4.5875C4.97917 4.19583 5.45 4 6 4C6.55 4 7.02083 4.19583 7.4125 4.5875C7.80417 4.97917 8 5.45 8 6C8 6.55 7.80417 7.02083 7.4125 7.4125C7.02083 7.80417 6.55 8 6 8ZM6 12C4.33333 12 2.91667 11.4167 1.75 10.25C0.583333 9.08333 0 7.66667 0 6C0 4.33333 0.583333 2.91667 1.75 1.75C2.91667 0.583333 4.33333 0 6 0C7.11667 0 8.12917 0.275 9.0375 0.825C9.94583 1.375 10.6667 2.1 11.2 3H20L23 6L18.5 10.5L16.5 9L14.5 10.5L12.375 9H11.2C10.6667 9.9 9.94583 10.625 9.0375 11.175C8.12917 11.725 7.11667 12 6 12ZM6 10C6.93333 10 7.75417 9.71667 8.4625 9.15C9.17083 8.58333 9.64167 7.86667 9.875 7H13L14.45 8.025L16.5 6.5L18.275 7.875L20.15 6L19.15 5H9.875C9.64167 4.13333 9.17083 3.41667 8.4625 2.85C7.75417 2.28333 6.93333 2 6 2C4.9 2 3.95833 2.39167 3.175 3.175C2.39167 3.95833 2 4.9 2 6C2 7.1 2.39167 8.04167 3.175 8.825C3.95833 9.60833 4.9 10 6 10Z" fill="currentColor"/>
                            </svg> <?php esc_attr_e('Activate theme', 'xstore'); ?></button>
		                <a href="<?php echo ET_Setup_Wizard::get_controls_url('child-theme'); ?>" class="setup-button-link wizard-controllers-button"><?php echo ET_Setup_Wizard::texts('skip'); ?></a>
                    </div>
                </form>
            </div>
        </div>
	</div>
<!--	<div class="wizard-step-controllers">-->
<!--		<a href="--><?php //echo ET_Setup_Wizard::get_controls_url('child-theme'); ?><!--" class="setup-button-link wizard-controllers-button">skip</a>-->
<!--	</div>-->
</div>