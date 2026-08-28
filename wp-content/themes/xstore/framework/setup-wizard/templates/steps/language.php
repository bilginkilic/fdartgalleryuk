<?php if ( ! defined('ETHEME_FW')) exit('No direct script access allowed');
/**
 * Template "Language Setuped" - step for ET_Setup_Wizard.
 * @package ET_Setup_Wizard
 * @since 9.5.0
 * @version 1.0.0
 */

$is_activated = etheme_is_activated();
?>

<div class="wizard-step wizard-child-theme">
	<div class="wizard-step-content">
		<?php
		    $site_title = get_bloginfo('name');
            $site_tagline = get_bloginfo('description');
		?>
        <div class="wizard-step-heading text-center">
            <h2><?php esc_html_e('Choose your site language', 'xstore'); ?></h2>
            <p>
                <?php esc_html_e('Select your preferred language to start working with XStore. You can always upload or edit your own translation files later using any translation plugin.', 'xstore') ?>
            </p>
        </div>

        <form id="et_setup-language" class="text-center" action="<?php echo ET_Setup_Wizard::get_controls_url('language-setuped'); ?>" method="POST" style="max-width: 320px; margin: 0 auto;">
        <span style="display: flex;align-items: center;gap: 10px;margin-right: calc(-10px - 1em);">
            <select name="language" id="language">
                    <option value="">Select language</option>
                    <option value="en">English</option>
                    <option value="ar">العربية</option>
                    <option value="bn_BD">বাংলা (বাংলাদেশ)</option>
                    <option value="bg_BG">Български</option>
                    <option value="zh_CN">中文（简体）</option>
                    <option value="zh_TW">中文（繁體）</option>
                    <option value="cs_CZ">Čeština</option>
                    <option value="da_DK">Dansk</option>
                    <option value="nl_NL">Nederlands</option>
                    <option value="fr_FR">Français</option>
                    <option value="de_DE">Deutsch</option>
                    <option value="el">Ελληνικά</option>
                    <option value="he_IL">עברית</option>
                    <option value="hi_IN">हिन्दी</option>
                    <option value="id_ID">Bahasa Indonesia</option>
                    <option value="it_IT">Italiano</option>
                    <option value="ja">日本語</option>
                    <option value="ko_KR">한국어</option>
                    <option value="lt_LT">Lietuvių</option>
                    <option value="nb_NO">Norsk bokmål</option>
                    <option value="fa_IR">فارسی</option>
                    <option value="pl_PL">Polski</option>
                    <option value="pt_BR">Português (Brasil)</option>
                    <option value="pt_PT">Português (Portugal)</option>
                    <option value="ro_RO">Română</option>
                    <option value="ru_RU">Русский</option>
                    <option value="es_ES">Español</option>
                    <option value="sv_SE">Svenska</option>
                    <option value="tr_TR">Türkçe</option>
                    <option value="uk">Українська</option>
                    <option value="vi">Tiếng Việt</option>
                </select>
            <span class="mtips mtips-lg text-left helping">
                    <svg width="1em" height="1em" viewBox="0 0 21 21" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M10.5 0C4.70996 0 0 4.70996 0 10.5C0 16.29 4.70996 21 10.5 21C16.29 21 21 16.29 21 10.5C21 4.70996 16.29 0 10.5 0ZM10.5 1.75C15.3433 1.75 19.25 5.65674 19.25 10.5C19.25 15.3433 15.3433 19.25 10.5 19.25C5.65674 19.25 1.75 15.3433 1.75 10.5C1.75 5.65674 5.65674 1.75 10.5 1.75ZM10.5 5.25C8.57568 5.25 7 6.82568 7 8.75H8.75C8.75 7.77246 9.52246 7 10.5 7C11.4775 7 12.25 7.77246 12.25 8.75C12.25 9.41992 11.8193 10.0146 11.1836 10.2266L10.8281 10.3359C10.1138 10.5718 9.625 11.2554 9.625 12.0039V13.125H11.375V12.0039L11.7305 11.8945C13.0771 11.4468 14 10.1685 14 8.75C14 6.82568 12.4243 5.25 10.5 5.25ZM9.625 14V15.75H11.375V14H9.625Z" fill="currentColor"></path></svg>
                    <span class="mt-mes">
                        <?php esc_html_e('Translation files will be available in your “Languages” folder,', 'xstore'); ?>
                        <?php echo esc_html(ETC_Languages::get_upload_dir()); ?>
                        <?php esc_html_e('so you can edit them anytime with a translation plugin (like PO Edit, WPML, etc.). These files are shared by other customers to help you get started. If you have any suggestions, improvements, or notice any mistakes in the translations, we’d really appreciate it if you share them on our', 'xstore'); ?>
                            <a href="https://www.8theme.com/glotpress/projects/xstore/xstore/" target="_blank"><?php esc_attr_e('GlotPress', 'xstore'); ?></a> <?php esc_attr_e('project!', 'xstore'); ?>
                    </span>
                </span>
            </span>  
        </form>
	</div>
	<div class="wizard-step-controllers">
        <a href="" class="setup-button wizard-controllers-button update-site-languages"><?php esc_html_e('Continue', 'xstore'); ?></a>
        <a href="<?php echo ET_Setup_Wizard::get_controls_url('basic'); ?>" class="setup-button-link wizard-controllers-button"><?php echo ET_Setup_Wizard::texts('skip'); ?></a>

        <?php if(false): ?>
            <?php if($is_activated) :?>
                <a href="<?php echo ET_Setup_Wizard::get_controls_url('basic'); ?>" class="setup-button-link wizard-controllers-button"><?php echo ET_Setup_Wizard::texts('skip'); ?></a>
            <?php else:?>
                <a href="<?php echo ET_Setup_Wizard::get_controls_url('register'); ?>" class="setup-button-link wizard-controllers-button"><?php echo ET_Setup_Wizard::texts('skip'); ?></a>
            <?php endif;?>
        <?php endif;?>
    </div>
</div>
