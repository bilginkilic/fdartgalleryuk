<?php if ( ! defined( 'ABSPATH' ) ) {
	exit( 'No direct script access allowed' );
}
/**
 * Template "Language" for 8theme dashboard.
 *
 * @since   9.5.3
 * @version 1.0.1
 */
?>

<?php
	$language_page_options = array();
	
    $language_page_options['is_enabled'] = get_option('etheme_builtin_language', false);
    $language_page_options['last_sync'] = get_option('etheme_builtin_language_last_sync', false);

    $_is_allowed = ( ! apply_filters('xstore_default_language', get_option('xstore_default_language', false)) ) ? 'allowed' : 'not-allowed'; 
    // $email_builder_page_options['is_enabled_dev_mode'] = get_option('etheme_built_in_email_builder_dev_mode', false);
?>

<h2 class="etheme-page-title etheme-page-title-type-2"><?php echo esc_html__('XStore Built-In Translations', 'xstore'); ?></h2>
<p>
    <?php echo esc_html__('This section provides access to the built-in translation system of the XStore theme. Currently, we include translations for 30+ languages, ready to use without installing any third-party translation plugins.', 'xstore'); ?>
    <br><br>

    <?php echo esc_html__('You can check the full list of available languages', 'xstore'); ?>
    <a href="https://www.8theme.com/glotpress/projects/xstore/xstore/"><?php echo esc_html__('here', 'xstore'); ?></a>.
    <br><br>

    <?php echo esc_html__('Our translation files are community-driven, which means that anyone can', 'xstore'); ?>
    <a href="https://www.8theme.com/glotpress/projects/xstore/xstore/"><?php echo esc_html__('contribute translations', 'xstore'); ?></a>
    <?php echo esc_html__('or improve existing ones for their language. Contributions are reviewed and included in upcoming theme updates.', 'xstore'); ?>
</p>
<p>
	<label class="et-panel-option-switcher<?php if ( $language_page_options['is_enabled']) { ?> switched<?php } ?>" for="et_language">
	    <input type="checkbox" id="et_language" name="et_language" <?php if ( $language_page_options['is_enabled']) { ?>checked<?php } ?>>
	    <span></span>
	</label>
</p>

<?php if ( $language_page_options['is_enabled'] ) : ?>
    <p class="et-message">
        <?php echo esc_html__('Translations activated. Select the language you want to use for your site in the dropdown, then click Save.', 'xstore'); ?>
    </p>

    <?php 
        $ETC_Languages = new ETC_Languages();
        $ETC_Languages->language_select_form();
    ?>
   <p></p>
    <p>
        <input class="etheme-language-save et-button et-button-green no-loader <?php echo esc_html($_is_allowed); ?>" type="submit" value="<?php esc_html_e('save', 'xstore') ?>">
   
        <input class="etheme-sync-save et-button et-button-green grey-btn-sync no-loader" type="submit" value="<?php esc_html_e('sync', 'xstore') ?>">
        
        <span class="mtips mtips-lg text-left helping">
            <svg width="1em" height="1em" viewBox="0 0 21 21" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M10.5 0C4.70996 0 0 4.70996 0 10.5C0 16.29 4.70996 21 10.5 21C16.29 21 21 16.29 21 10.5C21 4.70996 16.29 0 10.5 0ZM10.5 1.75C15.3433 1.75 19.25 5.65674 19.25 10.5C19.25 15.3433 15.3433 19.25 10.5 19.25C5.65674 19.25 1.75 15.3433 1.75 10.5C1.75 5.65674 5.65674 1.75 10.5 1.75ZM10.5 5.25C8.57568 5.25 7 6.82568 7 8.75H8.75C8.75 7.77246 9.52246 7 10.5 7C11.4775 7 12.25 7.77246 12.25 8.75C12.25 9.41992 11.8193 10.0146 11.1836 10.2266L10.8281 10.3359C10.1138 10.5718 9.625 11.2554 9.625 12.0039V13.125H11.375V12.0039L11.7305 11.8945C13.0771 11.4468 14 10.1685 14 8.75C14 6.82568 12.4243 5.25 10.5 5.25ZM9.625 14V15.75H11.375V14H9.625Z" fill="currentColor"></path></svg>
            <span class="mt-mes">
            <?php echo esc_html__('When you click the "Sync" button, the latest translation files (.po and .mo) for the selected language will be downloaded from the theme author\'s server. This applies to both the theme and the core plugin. We recommend performing this action after each theme update to ensure your translations remain up to date.', 'xstore'); ?>
        </span>


        <?php if($language_page_options['last_sync']): ?>
        
            <span style="margin-left: 5px;"><?php echo esc_html__('Last synced:', 'xstore'); ?> <?php echo date('Y-m-d H:i:s', $language_page_options['last_sync']); ?></span>
        
        <?php endif; ?>
    </p>

    <style>
        .not-allowed{
            border-color: #8c8f94;
            background: #8c8f94;
            cursor: not-allowed;
        }
        .grey-btn-sync{
            border-color: #8c8f94;
            background: #8c8f94;
        }
    </style>

<?php endif; ?>

<?php unset($language_page_options);