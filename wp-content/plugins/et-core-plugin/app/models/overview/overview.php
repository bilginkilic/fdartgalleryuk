<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 *
 * XStore Overview widget
 * @since 5.1.2
 * @version 1.0.0
 */
class Etheme_Overview_Box {
    public $is_theme_active = false;
    public $is_admin = false;
    public $is_woocommerce = false;
    public $get_system_requirements = true;
    public $is_update_available = false;
    public $settings = array();
    private $notices;

    public $is_subscription = false;
    public function __construct() {
        if ( !defined('ETHEME_CODE_IMAGES') ) return;

        $this->settings = array(
            'title_logo' => ETHEME_CODE_IMAGES . 'wp-icon-color.svg',
            'title_text' => 'XStore',
            'version' => false,
            'show_pages' => array(
                'welcome',
                'system_requirements',
                'demos',
                'plugins',
                'patcher',
                'open_ai',
                'customize',
                'email_builder',
                'sales_booster',
                'custom_fonts',
                'social',
                'support',
                'changelog',
                'sponsors'
            ),
            'hide_overview_widget' => false,
            'hide_updates' => false
        );
        $xstore_branding_settings = get_option( 'xstore_white_label_branding_settings', array() );
        if ( count($xstore_branding_settings) && isset($xstore_branding_settings['control_panel'])) {
            if ( $xstore_branding_settings['control_panel']['icon'] )
                $this->settings['title_logo'] = $xstore_branding_settings['control_panel']['icon'];
            if ( $xstore_branding_settings['control_panel']['label'] )
                $this->settings['title_text'] = $xstore_branding_settings['control_panel']['label'];

            if ( isset( $xstore_branding_settings['control_panel']['theme_version'] ) && ! empty( $xstore_branding_settings['control_panel']['theme_version'] ) )
                $this->settings['version'] = $xstore_branding_settings['control_panel']['theme_version'];

            if ( isset($xstore_branding_settings['control_panel']['hide_updates']) && $xstore_branding_settings['control_panel']['hide_updates'] == 'on' )
                $this->settings['hide_updates'] = true;

            if ( isset($xstore_branding_settings['control_panel']['hide_overview_widget']) && $xstore_branding_settings['control_panel']['hide_overview_widget'] == 'on' )
                $this->settings['hide_overview_widget'] = true;

                $show_pages_parsed = array();
                foreach ( $this->settings['show_pages'] as $show_page ) {
                    if ( isset($xstore_branding_settings['control_panel']['page_'.$show_page])){
                        $show_pages_parsed[] = $show_page;
                    }
                }

                $this->settings['show_pages'] = $show_pages_parsed;
        }
        $this->settings = (object) $this->settings;
        if ( $this->settings->hide_overview_widget ) return;
        // Register Dashboard Widgets.
        add_action( 'wp_dashboard_setup', [ $this, 'register_dashboard_widgets' ] );

        // Invalidate recent posts cache on content changes
        add_action( 'save_post', [ $this, 'clear_recent_posts_cache' ] );
        add_action( 'deleted_post', [ $this, 'clear_recent_posts_cache' ] );
        add_action( 'transition_post_status', [ $this, 'clear_recent_posts_cache_on_status_change' ], 10, 3 );
    }

    public function register_dashboard_widgets() {
        wp_add_dashboard_widget(
            'xstore_dashboard_overview',
            sprintf(esc_html__( '%s Overview', 'xstore-core' ), $this->settings->title_text),
            array( $this, 'render' ),
            null,
            null,
            'column3',
            'high'
        );
    }

    /**
     * Render meta box output.
     */
    public function render() {
        $this->get_system_requirements = $this->get_system_requirements();
        $this->is_update_available = $this->is_update_available();
        wp_enqueue_style( 'xstore-dashboard-overview', ET_CORE_URL . '/app/models/overview/assets/css/style.css', array(), ET_CORE_VERSION );
        
        $system_logs = count($this->get_system_requirements) ? $this->get_system_requirements : array();

        $transient_key = 'etheme_xstore_dashboard_overview_recent_posts_ids';

        $cached = get_transient($transient_key);
        if ( $cached !== false ) {
            $recently_edited_ids = $cached;
        }
        else {
            $recently_edited_args = array(
                'post_type'               => array('post', 'portfolio', 'page'),
                'post_status'             => array('publish', 'draft'),
                'posts_per_page'          => 3,
                'orderby'                 => 'modified',
                'order'                   => 'DESC',
                'fields'                  => 'ids',
                'no_found_rows'           => true,
                'ignore_sticky_posts'     => true,
                'update_post_meta_cache'  => false,
                'update_post_term_cache'  => false,
                'lazy_load_term_meta'     => false,
                'cache_results'           => false,
            );
            $recently_edited_ids = array_map( 'intval', (array) get_posts( $recently_edited_args ) );
            set_transient($transient_key, $recently_edited_ids, 12 * HOUR_IN_SECONDS );
        }
        $white_label_settings = $this->settings;

        include __DIR__ . '/template-parts/html-overview.php';
    }

    public function get_system_requirements(){
        if (
            ! defined('ETHEME_CODE')
            || ! is_user_logged_in()
            || ! current_user_can('administrator')
        ){
            return array();
        }

        if( ! class_exists('Etheme_System_Requirements') ) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once( apply_filters('etheme_file_url', ETHEME_CODE . 'system-requirements.php') );
        }
        $system = new \Etheme_System_Requirements();
        return method_exists($system, 'system_logs') ? $system->system_logs() : array(
            array(
                'type' => 'warning',
                'message' => esc_html__('Please, update your theme to the latest version', 'xstore-core')
            )
        );
    }

    public function is_update_available(){
        if (! class_exists('ETheme_Version_Check') && defined('ETHEME_CODE') && is_user_logged_in() ){
            require_once( apply_filters('etheme_file_url', ETHEME_CODE . 'version-check.php') );
        }
        $check_update = new \ETheme_Version_Check(false);

        $this->is_subscription = $check_update->is_subscription;

        return $check_update->is_update_available();
    }

    /**
     * Clear cached recent post IDs when content changes
     */
    public function clear_recent_posts_cache() {
        delete_transient('etheme_xstore_dashboard_overview_recent_posts_ids');
    }

    /**
     * Bridge for hooks that pass arguments
     */
    public function clear_recent_posts_cache_on_status_change($new_status, $old_status, $post) {
        $this->clear_recent_posts_cache();
    }
}

new Etheme_Overview_Box();