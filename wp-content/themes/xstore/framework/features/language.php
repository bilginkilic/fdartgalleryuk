<?php if ( ! defined( 'ABSPATH' ) ) exit( 'No direct script access allowed' );
/**
 * Etheme Admin Panel Language Class.
 *
 *
 * @since   9.5.3
 * @version 1.0.0
 *
 */

class ETC_Languages{

    public $selected_language = false;
    public $admin_panel_selected_language = false;
    public $is_enable = false;
    public $is_admin_enable = false;
    public $languages = array(
        'en'     => 'English',
        'ar'     => 'العربية',
        'bn_BD'  => 'বাংলা (বাংলাদেশ)',
        'bg_BG'  => 'Български',
        'zh_CN'  => '中文（简体）',
        'zh_TW'  => '中文（繁體）',
        'cs_CZ'  => 'Čeština',
        'da_DK'  => 'Dansk',
        'nl_NL'  => 'Nederlands',
        'fr_FR'  => 'Français',
        'de_DE'  => 'Deutsch',
        'el'     => 'Ελληνικά',
        'he_IL'  => 'עברית',
        'hi_IN'  => 'हिन्दी',
        'id_ID'  => 'Bahasa Indonesia',
        'it_IT'  => 'Italiano',
        'ja'     => '日本語',
        'ko_KR'  => '한국어',
        'lt_LT'  => 'Lietuvių',
        'nb_NO'  => 'Norsk bokmål',
        'fa_IR'  => 'فارسی',
        'pl_PL'  => 'Polski',
        'pt_BR'  => 'Português (Brasil)',
        'pt_PT'  => 'Português (Portugal)',
        'ro_RO'  => 'Română',
        'ru_RU'  => 'Русский',
        'es_ES'  => 'Español',
        'sv_SE'  => 'Svenska',
        'tr_TR'  => 'Türkçe',
        'uk'     => 'Українська',
        'vi'     => 'Tiếng Việt',
    );
	
	// ! Main construct/ add actions
	function __construct(){
        $this->selected_language = apply_filters('xstore_default_language', get_option('xstore_default_language', false));
        $this->admin_panel_selected_language = apply_filters('xstore_admin_panel_default_language', get_option('xstore_admin_panel_default_language', false));
        $this->is_enable = get_option('etheme_builtin_language', false);
        $this->is_admin_enable = apply_filters('xstore_admin_language', get_option('etheme_builtin_language', false));
        add_action( 'after_setup_theme', array($this, 'load_default_transtarions') );
	}
	
    public static function setup_translation($language){
		// update_option( 'WPLANG', $language );
		self::load_translation_files($language);
		update_option('xstore_default_language', $language);
	}

    public static function setup_admin_translation($language){
        if($language){
		    self::load_translation_files($language);
        }
		update_option('xstore_admin_panel_default_language', $language);
	}

	public static function load_translation_files($language) {
		foreach (array('xstore', 'xstore-core') as $key => $value) {
			self::load_translation_file($value . '-' . $language. '.mo');
		}
	}

	public static function get_upload_dir() {
		$upload_dir = wp_upload_dir();
		$local_dir  = trailingslashit( $upload_dir['basedir'] ) . 'languages/';
		return $local_dir;
	}

	public static function load_translation_file($file){
		$import_url  = apply_filters('etheme_protocol_url', 'https://www.8theme.com/import/xstore-demos/1/languages/');
		$remote_file_url = $import_url . $file;

		$upload_dir = wp_upload_dir();
		$local_dir  = trailingslashit( $upload_dir['basedir'] ) . 'languages/';
		$local_file = $local_dir . $file;

		if ( ! file_exists( $local_dir ) ) {
			wp_mkdir_p( $local_dir );
		}

		$response = wp_remote_get( $remote_file_url );

		if ( is_wp_error( $response ) ) {
			return;
		}

		$body = wp_remote_retrieve_body( $response );

		if ( empty( $body ) ) {
			return;
		}

		file_put_contents( $local_file, $body );
	}

    function load_default_transtarions() {

        if(is_admin() && $this->is_enable && $this->admin_panel_selected_language){
            $upload_dir = wp_upload_dir();
            $dir = trailingslashit( $upload_dir['basedir'] );
            // theme
            $mo_file = $dir . 'languages/xstore-' .$this->admin_panel_selected_language . '.mo';
            if(file_exists( $mo_file )){
                load_textdomain( 'xstore', $mo_file );
            }
            // plugin
            $mo_file = $dir . 'languages/xstore-core-' .$this->admin_panel_selected_language . '.mo';

            if(file_exists( $mo_file )){
                load_textdomain( 'xstore-core', $mo_file );
            }
        } elseif($this->is_enable && $this->selected_language){
            $upload_dir = wp_upload_dir();
            $dir = trailingslashit( $upload_dir['basedir'] );
            // theme
            $mo_file = $dir . 'languages/xstore-' .$this->selected_language . '.mo';
            if(file_exists( $mo_file )){
                load_textdomain( 'xstore', $mo_file );
            }
            // plugin
            $mo_file = $dir . 'languages/xstore-core-' .$this->selected_language . '.mo';

            if(file_exists( $mo_file )){
                load_textdomain( 'xstore-core', $mo_file );
            }
        }
    }

    public function language_select_form(){
        ?>
            <form id="et_setup-language" class="text-center" action="<?php //echo ET_Setup_Wizard::get_controls_url('language-setuped'); ?>" method="POST" style="max-width: 320px; margin: 0 auto; display:inline-block;">
            <div style="display: flex; flex-direction: column; align-items: flex-start; margin-bottom: 20px;">
                <h4><?php esc_html_e('Site language', 'xstore')?></h4>

                <span style="display: flex;align-items: center;gap: 10px;margin-right: calc(-10px - 1em); float: left; margin-right: 10px;">
                    <?php
                        echo '<select name="language" id="language">';
                            printf(
                                '<option value="%s" %s>%s</option>',
                                esc_attr( '' ),
                                selected( $this->selected_language, '', false ),
                                esc_html( 'Select language' )
                            );
                            foreach ( $this->languages as $value => $label ) {
                                printf(
                                    '<option value="%s" %s>%s</option>',
                                    esc_attr( $value ),
                                    selected( $this->selected_language, $value, false ),
                                    esc_html( $label )
                                );
                            }
                        echo '</select>';
                    ?>
                
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
            </div>    
                
            <div style="display: flex; flex-direction: column; align-items: flex-start;">
                <h4><?php esc_html_e('Dashboard language', 'xstore')?></h4>

                <span style="display: flex;align-items: center;gap: 10px;margin-right: calc(-10px - 1em);">
                    <?php
                        echo '<select name="admin_panel_language" id="admin_panel_language">';
                            printf(
                                '<option value="%s" %s>%s</option>',
                                esc_attr( '' ),
                                selected( $this->admin_panel_selected_language, '', false ),
                                esc_html( 'Inherit site language' )
                            );
                            foreach ( $this->languages as $value => $label ) {
                                printf(
                                    '<option value="%s" %s>%s</option>',
                                    esc_attr( $value ),
                                    selected( $this->admin_panel_selected_language, $value, false ),
                                    esc_html( $label )
                                );
                            }
                        echo '</select>';
                    ?>
                
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

            </div>
            </form>
        <?php
    }
}

new ETC_Languages();