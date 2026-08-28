<?php

defined( 'ABSPATH' ) || exit;

final class XStore_Options_Admin {
    public const VERSION   = '0.1.0';
    public const PAGE_SLUG = 'xstore-options';
    public const REST_NS   = 'xstore-options/v1';
    public const CAP       = 'edit_theme_options';
    private const IMPORT_EXPORT_SECTION_ID = 'import_export';

    private static array $exclude_panels = array( 'header-builder', 'single_product_builder' );

    private static array $exclude_sections = array( 'header-builder', 'single_product_builder', 'customizer-user-preferences' );

    private static ?array $schema_cache = null;

    private static string $page_hook = '';

    public static function init(): void {
        add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ), 99 );
        add_filter('etheme_theme_options_permalink', array( __CLASS__, 'admin_top_bar_menu_filters' ), 99, 3 );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
        add_action( 'rest_api_init', array( __CLASS__, 'rest_api_init' ) );
    }

    public static function admin_top_bar_menu_filters($url, $panel_or_section, $section = false): string {
        $url_modified = add_query_arg('page', self::PAGE_SLUG, admin_url('admin.php'));
        switch ($panel_or_section) {
            case 'static_front_page':
            case 'title_tagline':
            case 'custom_css':
            case 'product_tabs': // single product builder not yet implemented
                break;
            case 'main':
                $url = $url_modified;
                break;
            case 'widgets':
                $url = admin_url('widgets.php');
                break;
            case 'nav_menus':
                $url = admin_url('nav-menus.php');
                break;
            default:
                $url = add_query_arg('tab', ($section ? $section : $panel_or_section), $url_modified);
                break;
        }
        return $url;
    }

    public static function admin_menu(): void {
        if ( class_exists( 'EthemeAdmin' ) ) {
            $parent_slug = 'et-panel-welcome';
            $path = function_exists( 'wp_normalize_path' ) ? wp_normalize_path( __FILE__ ) : (string) __FILE__;
            if ( false !== strpos( $path, '/et-core-plugin/packages/' ) ) {
                $parent_slug = 'et-panel-theme-options';
            }

            self::$page_hook = (string) add_submenu_page(
                    $parent_slug,
                    esc_html__( 'Theme Options', 'xstore-core' ),
                    esc_html__( 'Theme Options', 'xstore-core' ),
                    self::CAP,
                    self::PAGE_SLUG,
                    array( __CLASS__, 'render_page' )
            );
            return;
        }

        self::$page_hook = (string) add_menu_page(
                esc_html__( 'XStore Options', 'xstore-core' ),
                esc_html__( 'XStore Options', 'xstore-core' ),
                self::CAP,
                self::PAGE_SLUG,
                array( __CLASS__, 'render_page' ),
                'dashicons-admin-generic',
                52.1
        );
    }

    public static function render_page(): void {
        if ( ! current_user_can( self::CAP ) ) {
            wp_die( esc_html__( 'Limited access.', 'xstore-core' ) );
        }

        echo '<div id="xstore-options-admin-root"></div>';
    }

    public static function enqueue( string $hook ): void {
        if ( self::$page_hook === '' || $hook !== self::$page_hook ) {
            return;
        }

        wp_enqueue_style( 'dashicons' );
        wp_enqueue_media();
        if ( function_exists( 'wp_enqueue_editor' ) ) {
            wp_enqueue_editor();
        }

        $handle = 'xstore-options-admin-mirror';

        $build_dir  = trailingslashit( plugin_dir_path( __FILE__ ) ) . 'build/';
        $asset_file = $build_dir . 'index.asset.php';

        $script_src = plugins_url( 'assets/admin.js', __FILE__ );
        $style_src  = plugins_url( 'assets/admin.css', __FILE__ );
        $deps       = array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n' );
        $ver        = self::VERSION;

        wp_enqueue_script('etheme_panel_global');
        wp_enqueue_style('etheme_admin_panel_css');

        if ( file_exists( $asset_file ) ) {
            $asset = include $asset_file;
            if ( is_array( $asset ) ) {
                $deps = isset( $asset['dependencies'] ) && is_array( $asset['dependencies'] ) ? $asset['dependencies'] : $deps;
                $ver  = isset( $asset['version'] ) ? (string) $asset['version'] : $ver;
            }

            $script_src = plugins_url( 'build/index.js', __FILE__ );

            if ( file_exists( $build_dir . 'index.css' ) ) {
                $style_src = plugins_url( 'build/index.css', __FILE__ );
            } elseif ( file_exists( $build_dir . 'style-index.css' ) ) {
                $style_src = plugins_url( 'build/style-index.css', __FILE__ );
                if ( is_rtl() && file_exists( $build_dir . 'style-index-rtl.css' ) ) {
                    $style_src = plugins_url( 'build/style-index-rtl.css', __FILE__ );
                }
            } else {
                $style_src = plugins_url( 'assets/admin.css', __FILE__ );
            }
        }

        $code_editor_settings = false;
        if ( function_exists( 'wp_enqueue_code_editor' ) ) {
            $code_editor_settings = wp_enqueue_code_editor(
                    array(
                            'type' => 'text/css',
                    )
            );
            if ( $code_editor_settings && ! in_array( 'code-editor', $deps, true ) ) {
                $deps[] = 'code-editor';
            }
        }

        $legacy_url = admin_url( 'customize.php' );
        if ( class_exists( 'EthemeAdmin' ) ) {
            $parent_slug = 'et-panel-welcome';
            $path        = function_exists( 'wp_normalize_path' ) ? wp_normalize_path( __FILE__ ) : (string) __FILE__;
            if ( false !== strpos( $path, '/et-core-plugin/packages/' ) ) {
                $parent_slug = 'et-panel-theme-options';
            }
            $legacy_url = admin_url( 'admin.php?page=' . $parent_slug );
        }

        wp_enqueue_style( $handle, $style_src, array( 'wp-components', 'etheme_admin_panel_css' ), $ver );
        wp_enqueue_script( $handle, $script_src, $deps, $ver, true );
        wp_add_inline_style(
                $handle,
                '#toplevel_page_et-panel-theme-options .wp-submenu-wrap{display:none!important;}'
        );
        wp_add_inline_script(
                $handle,
                '(function(){var r=document.getElementById("toplevel_page_et-panel-theme-options");if(!r)return;var c=r.classList.contains("current")||r.classList.contains("wp-has-current-submenu");if(!c)return;var k=function(){var a=r.querySelectorAll(".et_adm-mega-menu-holder.opened");for(var i=0;i<a.length;i++){a[i].classList.remove("opened");}};k();r.addEventListener("mouseenter",k,true);r.addEventListener("mouseleave",k,true);document.addEventListener("mouseenter",function(e){var t=e.target;if(!t||!t.closest)return;var h=t.closest("#toplevel_page_et-panel-theme-options .et_adm-mega-menu-holder");if(h)h.classList.remove("opened");},true);document.addEventListener("mouseleave",function(e){var t=e.target;if(!t||!t.closest)return;var h=t.closest("#toplevel_page_et-panel-theme-options .et_adm-mega-menu-holder");if(h)h.classList.remove("opened");},true);})();',
                'after'
        );

        $bootstrap = array(
                'root'  => esc_url_raw( rest_url() ),
                'nonce' => wp_create_nonce( 'wp_rest' ),
                'tab'   => isset( $_GET['tab'] ) ? sanitize_key( (string) $_GET['tab'] ) : '',
                'codeEditor' => $code_editor_settings ? $code_editor_settings : null,
                'legacyUrl' => esc_url_raw( $legacy_url ),
                'customizerUrl' => esc_url_raw( admin_url( 'customize.php' ) ),
        );

        wp_add_inline_script(
                $handle,
                'window.XStoreOptionsAdmin=' . wp_json_encode( $bootstrap ) . ';',
                'before'
        );
    }

    public static function rest_api_init(): void {
        register_rest_route(
                self::REST_NS,
                '/schema',
                array(
                        'methods'             => 'GET',
                        'permission_callback' => array( __CLASS__, 'can_manage' ),
                        'callback'            => array( __CLASS__, 'rest_schema' ),
                )
        );

        register_rest_route(
                self::REST_NS,
                '/values',
                array(
                        'methods'             => 'GET',
                        'permission_callback' => array( __CLASS__, 'can_manage' ),
                        'callback'            => array( __CLASS__, 'rest_values' ),
                )
        );

        register_rest_route(
                self::REST_NS,
                '/fonts',
                array(
                        'methods'             => 'GET',
                        'permission_callback' => array( __CLASS__, 'can_manage' ),
                        'callback'            => array( __CLASS__, 'rest_fonts' ),
                )
        );

        register_rest_route(
                self::REST_NS,
                '/save',
                array(
                        'methods'             => 'POST',
                        'permission_callback' => array( __CLASS__, 'can_manage' ),
                        'callback'            => array( __CLASS__, 'rest_save' ),
                        'args'                => array(
                                'changes' => array(
                                        'type'     => 'object',
                                        'required' => true,
                                ),
                        ),
                )
        );

        register_rest_route(
                self::REST_NS,
                '/reset-section',
                array(
                        'methods'             => 'POST',
                        'permission_callback' => array( __CLASS__, 'can_manage' ),
                        'callback'            => array( __CLASS__, 'rest_reset_section' ),
                        'args'                => array(
                                'section' => array(
                                        'type'     => 'string',
                                        'required' => true,
                                ),
                        ),
                )
        );

        register_rest_route(
                self::REST_NS,
                '/reset-all',
                array(
                        'methods'             => 'POST',
                        'permission_callback' => array( __CLASS__, 'can_manage' ),
                        'callback'            => array( __CLASS__, 'rest_reset_all' ),
                )
        );

        register_rest_route(
                self::REST_NS,
                '/page-logo',
                array(
                        'methods'             => 'POST',
                        'permission_callback' => array( __CLASS__, 'can_manage' ),
                        'callback'            => array( __CLASS__, 'rest_page_logo' ),
                )
        );

        register_rest_route(
                self::REST_NS,
                '/page-switcher',
                array(
                        'methods'             => 'POST',
                        'permission_callback' => array( __CLASS__, 'can_manage' ),
                        'callback'            => array( __CLASS__, 'rest_page_switcher' ),
                )
        );

        register_rest_route(
                self::REST_NS,
                '/page-colormode',
                array(
                        'methods'             => 'POST',
                        'permission_callback' => array( __CLASS__, 'can_manage' ),
                        'callback'            => array( __CLASS__, 'rest_page_colormode' ),
                )
        );

        register_rest_route(
                self::REST_NS,
                '/theme-links',
                array(
                        'methods'             => 'POST',
                        'permission_callback' => array( __CLASS__, 'can_manage' ),
                        'callback'            => array( __CLASS__, 'rest_theme_links' ),
                )
        );

        register_rest_route(
                self::REST_NS,
                '/page-footer',
                array(
                        'methods'             => 'POST',
                        'permission_callback' => array( __CLASS__, 'can_manage' ),
                        'callback'            => array( __CLASS__, 'rest_page_footer' ),
                )
        );

        register_rest_route(
                self::REST_NS,
                '/search',
                array(
                        'methods'             => 'GET',
                        'permission_callback' => array( __CLASS__, 'can_manage' ),
                        'callback'            => array( __CLASS__, 'rest_search' ),
                        'args'                => array(
                                'q'     => array(
                                        'type'     => 'string',
                                        'required' => true,
                                ),
                                'limit' => array(
                                        'type'    => 'integer',
                                        'default' => 250,
                                ),
                        ),
                )
        );

        register_rest_route(
                self::REST_NS,
                '/export',
                array(
                        'methods'             => 'GET',
                        'permission_callback' => array( __CLASS__, 'can_manage' ),
                        'callback'            => array( __CLASS__, 'rest_export' ),
                )
        );

        register_rest_route(
                self::REST_NS,
                '/import',
                array(
                        'methods'             => 'POST',
                        'permission_callback' => array( __CLASS__, 'can_manage' ),
                        'callback'            => array( __CLASS__, 'rest_import' ),
                        'args'                => array(
                                'values' => array(
                                        'type'     => 'object',
                                        'required' => true,
                                ),
                        ),
                )
        );
    }

    public static function can_manage(): bool {
        return current_user_can( self::CAP );
    }

    public static function rest_page_colormode(): string {
        $dark_light_default = get_option('et_panel_dark_light_default', 'light');
        return $dark_light_default;
    }

    public static function rest_theme_links(): array {
        $backup_url = 'https://www.8theme.com';
        $xstore_branding_settings = get_option( 'xstore_white_label_branding_settings', array() );

        if ( is_array( $xstore_branding_settings ) && count( $xstore_branding_settings ) ) {
            if ( isset( $xstore_branding_settings['plugins_data'] ) ) {
                if (isset($xstore_branding_settings['plugins_data']['documentation_url']) && !empty($xstore_branding_settings['plugins_data']['documentation_url'])) {
                    $documentation_url = $xstore_branding_settings['plugins_data']['documentation_url'];
                    add_filter('etheme_documentation_url', function ($url) use ($documentation_url) {
                        return $documentation_url;
                    });
                }
                if (isset($xstore_branding_settings['plugins_data']['support_url']) && !empty($xstore_branding_settings['plugins_data']['support_url'])) {
                    $support_url = $xstore_branding_settings['plugins_data']['support_url'];
                    add_filter('etheme_support_forum_url', function ($url) use ($support_url) {
                        return $support_url;
                    });
                }
            }
        }
        $theme_links = array(
                'documentation' => array(
                        'title' => esc_html__('Documentation', 'xstore-core'),
                        'link' => function_exists('etheme_documentation_url') ? etheme_documentation_url(false, false) : $backup_url,
                ),
                'support_forum' => array(
                        'title' => esc_html__('Support Forum', 'xstore-core'),
                        'link' => is_callable( 'etheme_support_forum_url' ) ? etheme_support_forum_url() : $backup_url,
                ),
                'tutorials' => array(
                        'title' => esc_html__('Video Tutorials', 'xstore-core'),
                        'link' => 'https://www.youtube.com/watch?v=i7STFGZapx8&list=PLMqMSqDgPNmCCyem_z9l2ZJ1owQUaFCE3&index=1',
                ),
                'contacts' => array(
                        'title' => esc_html__('Contacts', 'xstore-core'),
                        'link' => function_exists('etheme_contact_us_url') ? etheme_contact_us_url() : $backup_url,
                ),
                'faq' => array(
                        'title' => esc_html__('FAQ', 'xstore-core'),
                        'link' => function_exists('etheme_support_forum_url') && has_filter('etheme_support_forum_url') ? etheme_support_forum_url() : 'https://www.8theme.com/faq/',
                ),
        );
        return $theme_links;
    }

    public static function rest_page_footer(): WP_REST_Response {
        $xstore_branding_settings = get_option( 'xstore_white_label_branding_settings', array() );

        $settings                = array();
        $settings['hide_footer'] = false;
        $settings['author_uri']  = 'https://www.8theme.com/';
        $theme_label = 'XStore';

        if ( is_array( $xstore_branding_settings ) && count( $xstore_branding_settings ) ) {
            if ( isset( $xstore_branding_settings['control_panel'] ) ) {
                if ( $xstore_branding_settings['control_panel']['label'] ) {
                    $theme_label = $xstore_branding_settings['control_panel']['label'];
                }
                if ( isset( $xstore_branding_settings['control_panel']['hide_footer'] ) && $xstore_branding_settings['control_panel']['hide_footer'] ) {
                    $settings['hide_footer'] = true;
                }
            }
            if ( isset( $xstore_branding_settings['plugins_data'] ) ) {
                if (isset($xstore_branding_settings['plugins_data']['author_uri']) && !empty($xstore_branding_settings['plugins_data']['author_uri'])) {
                    $settings['author_uri'] = $xstore_branding_settings['plugins_data']['author_uri'];
                }
                if (isset($xstore_branding_settings['plugins_data']['author']) && !empty($xstore_branding_settings['plugins_data']['author'])) {
                    $author_name = $xstore_branding_settings['plugins_data']['author'];
                    add_filter('etheme_theme_author_name', function ($author) use ($author_name) {
                        return $author_name;
                    });
                }
            }
        }

        if ( $settings['hide_footer'] ) {
            return wp_send_json_success( array( 'html' => '' ) );
        }

        $theme_links = self::rest_theme_links();

        $footer_links = array(
                array(
                        'title' => esc_html__( 'Documentation', 'xstore-core' ),
                        'link'  => $theme_links['documentation']['link'],
                ),
                array(
                        'title' => esc_html__( 'Support Forum', 'xstore-core' ),
                        'link'  => $theme_links['support_forum']['link'],
                ),
                array(
                        'title' => esc_html__( 'Video Tutorials', 'xstore-core' ),
                        'link'  => $theme_links['tutorials']['link'],
                ),
                array(
                        'title' => esc_html__( 'Contacts', 'xstore-core' ),
                        'link'  => $theme_links['contacts']['link'],
                ),
                array(
                        'title' => esc_html__( 'FAQ', 'xstore-core' ),
                        'link'  => $theme_links['faq']['link'],
                ),
        );

        $footer_socials = array(
                array(
                        'title' => esc_html__( 'Facebook', 'xstore-core' ),
                        'link'  => 'https://www.facebook.com/8theme/',
                        'icon'  => '<svg width="9" height="17" viewBox="0 0 9 17" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5.616 5.848V3.723C5.616 3.128 6.12 2.669 6.732 2.669H7.848V0H5.616C3.762 0 2.25 1.428 2.25 3.196V5.848H0V8.5H2.25V17H5.634V8.5H7.884L9 5.848H5.616Z" fill="currentColor"/></svg>',
                ),
                array(
                        'title' => esc_html__( 'Instagram', 'xstore-core' ),
                        'link'  => 'https://www.instagram.com/8theme/',
                        'icon'  => '<svg width="17" height="17" viewBox="0 0 17 17" fill="none" xmlns="http://www.w3.org/2000/svg"><g clip-path="url(#clip0_2684_2586)"><path d="M11.696 0H5.304C2.38 0 0 2.38 0 5.304V11.679C0 14.62 2.38 17 5.304 17H11.679C14.62 17 17 14.62 17 11.696V5.304C17 2.38 14.62 0 11.696 0ZM15.402 11.696C15.402 13.753 13.736 15.419 11.679 15.419H5.304C3.247 15.419 1.581 13.753 1.581 11.696V5.304C1.581 3.247 3.247 1.581 5.304 1.581H11.679C13.736 1.581 15.402 3.247 15.402 5.304V11.696ZM8.5 4.25C6.154 4.25 4.25 6.154 4.25 8.5C4.25 10.846 6.154 12.75 8.5 12.75C10.846 12.75 12.75 10.846 12.75 8.5C12.75 6.154 10.846 4.25 8.5 4.25ZM8.5 11.152C7.038 11.152 5.848 9.962 5.848 8.5C5.848 7.038 7.038 5.848 8.5 5.848C9.962 5.848 11.152 7.038 11.152 8.5C11.152 9.962 9.962 11.152 8.5 11.152ZM13.634 3.927C13.634 4.23654 13.3825 4.488 13.073 4.488C12.7635 4.488 12.512 4.23654 12.512 3.927C12.512 3.61746 12.7635 3.366 13.073 3.366C13.3825 3.366 13.634 3.61746 13.634 3.927Z" fill="currentColor"/></g><defs><clipPath id="clip0_2684_2586"><rect width="17" height="17" fill="var(--et_admin_white2dark-color, #fff)"/></clipPath></defs></svg>',
                ),
                array(
                        'title' => esc_html__( 'Telegram', 'xstore-core' ),
                        'link'  => 'https://t.me/etheme',
                        'icon'  => '<svg width="16" height="15" viewBox="0 0 16 15" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M0.282679 7.19685L3.96935 8.65885L5.39601 13.535C5.48735 13.8474 5.84668 13.9628 6.08535 13.7553L8.14068 11.9753C8.35601 11.789 8.66268 11.7798 8.88801 11.9533L12.5947 14.8128C12.85 15.0098 13.2113 14.861 13.2753 14.5338L15.9907 0.656807C16.0607 0.299099 15.7293 0.000181913 15.4087 0.131932L0.278013 6.33339C-0.0953207 6.48639 -0.0913207 7.0481 0.282679 7.19685ZM5.16668 7.88039L12.372 3.16502C12.5013 3.08072 12.6347 3.26631 12.5233 3.3761L6.57668 9.24889C6.36735 9.45572 6.23268 9.73197 6.19468 10.0323L5.99201 11.6275C5.96535 11.8407 5.68335 11.8612 5.62801 11.6558L4.84868 8.74739C4.75935 8.41589 4.88935 8.06101 5.16535 7.88039H5.16668Z" fill="currentColor"/></svg>',
                ),
                array(
                        'title' => esc_html__( 'Youtube', 'xstore-core' ),
                        'link'  => 'https://www.youtube.com/watch?v=Eq16hs-1PUs&list=PLMqMSqDgPNmCCyem_z9l2ZJ1owQUaFCE3',
                        'icon'  => '<svg width="17" height="17" viewBox="0 0 17 17" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M15.419 8.602C15.249 7.837 14.62 7.276 13.872 7.191C12.087 6.987 10.285 6.987 8.49999 6.987C6.71499 6.987 4.91299 6.987 3.12799 7.191C2.37999 7.276 1.75099 7.837 1.58099 8.602C1.32599 9.69 1.32599 10.88 1.32599 12.002C1.32599 13.124 1.32599 14.314 1.58099 15.402C1.75099 16.167 2.37999 16.728 3.12799 16.813C4.91299 17 6.69799 17 8.49999 17C10.285 17 12.087 17 13.872 16.796C14.62 16.711 15.249 16.15 15.419 15.385C15.674 14.297 15.674 13.107 15.674 11.985C15.674 10.88 15.674 9.69 15.419 8.602ZM5.42299 9.452H4.38599V14.96H3.41699V9.452H2.39699V8.551H5.42299V9.452ZM8.04099 14.96H7.17399V14.45C6.83399 14.841 6.49399 15.045 6.18799 15.045C5.91599 15.045 5.71199 14.926 5.62699 14.688C5.57599 14.535 5.55899 14.314 5.55899 13.974V10.183H6.42599V13.702C6.42599 13.906 6.42599 14.008 6.42599 14.042C6.44299 14.178 6.51099 14.246 6.62999 14.246C6.79999 14.246 6.98699 14.11 7.17399 13.838V10.183H8.04099V14.96ZM11.339 13.532C11.339 13.974 11.305 14.297 11.254 14.501C11.135 14.858 10.914 15.045 10.557 15.045C10.251 15.045 9.94499 14.875 9.65599 14.518V14.96H8.78899V8.551H9.65599V10.642C9.92799 10.302 10.234 10.115 10.557 10.115C10.897 10.115 11.135 10.302 11.254 10.659C11.305 10.846 11.339 11.169 11.339 11.628V13.532ZM14.603 12.733H12.869V13.583C12.869 14.025 13.022 14.246 13.311 14.246C13.532 14.246 13.651 14.127 13.702 13.906C13.702 13.855 13.719 13.668 13.719 13.311H14.603V13.43C14.603 13.702 14.586 13.906 14.586 13.991C14.552 14.178 14.484 14.365 14.382 14.518C14.144 14.858 13.787 15.045 13.328 15.045C12.869 15.045 12.529 14.875 12.274 14.552C12.087 14.314 12.002 13.94 12.002 13.43V11.747C12.002 11.237 12.087 10.863 12.274 10.625C12.529 10.302 12.869 10.132 13.311 10.132C13.753 10.132 14.093 10.302 14.331 10.625C14.518 10.863 14.603 11.237 14.603 11.747V12.733ZM13.311 10.897C13.022 10.897 12.869 11.118 12.869 11.56V12.002H13.736V11.56C13.736 11.118 13.6 10.897 13.311 10.897ZM10.081 10.897C9.94499 10.897 9.79199 10.965 9.65599 11.101V14.025C9.80899 14.178 9.94499 14.246 10.081 14.246C10.336 14.246 10.455 14.025 10.455 13.6V11.56C10.472 11.118 10.336 10.897 10.081 10.897ZM10.574 6.562C10.897 6.562 11.22 6.375 11.577 5.967V6.494H12.461V1.649H11.577V5.338C11.39 5.61 11.203 5.746 11.016 5.746C10.897 5.746 10.829 5.678 10.812 5.542C10.795 5.508 10.795 5.406 10.795 5.202V1.649H9.92799V5.474C9.92799 5.814 9.96199 6.052 10.013 6.188C10.098 6.443 10.285 6.562 10.574 6.562ZM4.47099 3.859V6.494H5.43999V3.859L6.61299 0H5.62699L4.96399 2.55L4.28399 0H3.26399C3.46799 0.595 3.68899 1.207 3.89299 1.802C4.18199 2.72 4.38599 3.4 4.47099 3.859ZM7.92199 6.562C8.36399 6.562 8.70399 6.392 8.94199 6.069C9.12899 5.831 9.21399 5.44 9.21399 4.93V3.23C9.21399 2.72 9.12899 2.329 8.94199 2.091C8.70399 1.768 8.36399 1.598 7.92199 1.598C7.47999 1.598 7.13999 1.768 6.90199 2.091C6.71499 2.329 6.62999 2.72 6.62999 3.23V4.93C6.62999 5.44 6.71499 5.831 6.90199 6.069C7.13999 6.392 7.47999 6.562 7.92199 6.562ZM7.49699 3.06C7.49699 2.618 7.63299 2.397 7.92199 2.397C8.21099 2.397 8.34699 2.618 8.34699 3.06V5.1C8.34699 5.542 8.21099 5.763 7.92199 5.763C7.63299 5.763 7.49699 5.542 7.49699 5.1V3.06Z" fill="currentColor"></path>
</svg>',
                ),
        );

        ob_start();
        ?>
        <div class="etheme-page-footer">
            <a href="<?php echo esc_url( $settings['author_uri'] ); ?>" rel="nofollow" target="_blank" class="logo">
                <svg width="84" height="18" viewBox="0 0 84 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="1" y="1" width="16" height="16" rx="2" fill="var(--et_admin_white2dark-color, #fff)" stroke="currentColor" stroke-width="2"></rect>
                    <circle cx="11.5715" cy="9.00001" r="2.28571" stroke="var(--et_admin_dark2white-color, #222)" stroke-width="2"></circle>
                    <circle cx="6.4286" cy="9.00001" r="2.28571" stroke="var(--et_admin_dark2white-color, #222)" stroke-width="2"></circle>
                    <path d="M26.7146 16.0568C26.0108 16.0568 25.659 15.8669 25.659 15.487V4.46385H22.2731C21.9145 4.46385 21.7353 4.18219 21.7353 3.61893V3.10806C21.7353 2.5448 21.9145 2.26314 22.2731 2.26314H31.654C32.0125 2.26314 32.1918 2.5448 32.1918 3.10806V3.61893C32.1918 4.18219 32.0125 4.46385 31.654 4.46385H28.288V15.487C28.288 15.8669 27.9361 16.0568 27.2324 16.0568H26.7146ZM34.9568 16.0568C34.2531 16.0568 33.9012 15.8669 33.9012 15.487V2.79367C33.9012 2.41377 34.2531 2.22385 34.9568 2.22385H35.4746C36.1784 2.22385 36.5302 2.41377 36.5302 2.79367V7.88279H42.5452V2.79367C42.5452 2.41377 42.8969 2.22385 43.6008 2.22385H44.1187C44.8226 2.22385 45.1743 2.41377 45.1743 2.79367V15.487C45.1743 15.8669 44.8226 16.0568 44.1187 16.0568H43.6008C42.8969 16.0568 42.5452 15.8669 42.5452 15.487V10.1031H36.5302V15.487C36.5302 15.8669 36.1784 16.0568 35.4746 16.0568H34.9568ZM49.0462 16.0175C48.5551 16.0175 48.3092 15.7752 48.3092 15.2905V2.99016C48.3092 2.5055 48.5551 2.26314 49.0462 2.26314H56.0172C56.3757 2.26314 56.5549 2.5448 56.5549 3.10806V3.61893C56.5549 4.18219 56.3757 4.46385 56.0172 4.46385H50.9184V7.88279H55.4595C55.818 7.88279 55.9972 8.16445 55.9972 8.72771V9.23858C55.9972 9.80184 55.818 10.0835 55.4595 10.0835H50.9184V13.8168H56.0371C56.3956 13.8168 56.5748 14.0985 56.5748 14.6617V15.1726C56.5748 15.7359 56.3956 16.0175 56.0371 16.0175H49.0462ZM65.3959 12.5396C65.0908 12.5396 64.8649 12.4283 64.7187 12.2056L61.8904 7.27367C61.7977 7.1427 61.7112 7.01166 61.6315 6.88069C61.5655 6.74971 61.4989 6.61217 61.4323 6.46806L61.3527 6.48771C61.3663 6.65798 61.3726 6.82831 61.3726 6.99858C61.3726 7.15578 61.3726 7.31954 61.3726 7.48981V15.487C61.3726 15.8669 61.0209 16.0568 60.317 16.0568H59.8788C59.1754 16.0568 58.8232 15.8669 58.8232 15.487V2.79367C58.8232 2.41377 59.1754 2.22385 59.8788 2.22385H60.8149C61.0938 2.22385 61.3265 2.26314 61.512 2.34174C61.7112 2.40726 61.8506 2.52515 61.9303 2.69543L65.3959 8.72771C65.4493 8.81938 65.5023 8.9242 65.5552 9.04209C65.6218 9.14691 65.6747 9.25823 65.7145 9.37613C65.7544 9.25823 65.8079 9.14034 65.8739 9.02244C65.9404 8.90455 65.9934 8.79973 66.0332 8.70806L69.4988 2.69543C69.5921 2.52515 69.7315 2.40726 69.917 2.34174C70.1162 2.26314 70.349 2.22385 70.6141 2.22385H71.5303C72.2343 2.22385 72.5859 2.41377 72.5859 2.79367V15.487C72.5859 15.8669 72.2343 16.0568 71.5303 16.0568H71.0922C70.3888 16.0568 70.0365 15.8669 70.0365 15.487V7.52911C70.0365 7.35884 70.0365 7.19507 70.0365 7.03788C70.0502 6.86761 70.0565 6.69728 70.0565 6.527L69.9768 6.50736C69.9108 6.65147 69.8374 6.78901 69.7577 6.91999C69.6917 7.05096 69.612 7.18199 69.5187 7.31297L66.7104 12.2056C66.5647 12.4283 66.3388 12.5396 66.0332 12.5396H65.3959ZM76.3065 16.0175C75.8148 16.0175 75.5695 15.7752 75.5695 15.2905V2.99016C75.5695 2.5055 75.8148 2.26314 76.3065 2.26314H83.2775C83.636 2.26314 83.8152 2.5448 83.8152 3.10806V3.61893C83.8152 4.18219 83.636 4.46385 83.2775 4.46385H78.1787V7.88279H82.7198C83.0783 7.88279 83.2575 8.16445 83.2575 8.72771V9.23858C83.2575 9.80184 83.0783 10.0835 82.7198 10.0835H78.1787V13.8168H83.2974C83.6559 13.8168 83.8351 14.0985 83.8351 14.6617V15.1726C83.8351 15.7359 83.6559 16.0175 83.2974 16.0175H76.3065Z" fill="currentColor"></path>
                </svg>
            </a>

            <ul class="etheme-page-footer-main-menu">
                <?php foreach ( $footer_links as $footer_link ) : ?>
                    <li>
                        <a href="<?php echo esc_url( $footer_link['link'] ); ?>" rel="nofollow" target="_blank">
                            <?php echo esc_html( $footer_link['title'] ); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <ul class="socials">
                <?php foreach ( $footer_socials as $footer_social ) : ?>
                    <li>
                        <a href="<?php echo esc_url( $footer_social['link'] ); ?>" rel="nofollow" target="_blank" class="mtips mtips-top">
                            <?php if ( ! empty( $footer_social['icon'] ) ) : ?>
                                <?php echo '<span>' . $footer_social['icon'] . '</span>'; ?>
                            <?php endif; ?>
                            <span class="mt-mes"><?php echo esc_html( $footer_social['title'] ); ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="etheme-page-copyrights">
                <p>
                    <?php
                    echo sprintf(
                            esc_html__( 'We appreciate your choice of the %s Theme. Your flawless experience is our privilege.', 'xstore-core' ),
                            apply_filters( 'etheme_theme_label', $theme_label )
                    );
                    ?>
                </p>
                <p>
                    <a href="https://1.envato.market/2rXmmA" target="_blank" rel="nofollow">
                        <?php
                        echo sprintf(
                                esc_html__( 'Buy %s License', 'xstore-core' ),
                                apply_filters( 'etheme_theme_label', $theme_label )
                        );
                        ?>
                    </a>
                </p>
            </div>
        </div>
        <?php

        return wp_send_json_success( array( 'html' => ob_get_clean() ) );
    }

    public static function rest_page_switcher(): WP_REST_Response {
        ob_start(); ?>

        <span class="switcher <?php echo esc_attr(self::rest_page_colormode()); ?>-mode">
                    <span class="on">Light</span>
                    <span class="off">Dark</span>
                    <i>
                        <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" x="0px" y="0px" viewBox="0 0 35 35" style="enable-background:new 0 0 35 35;" xml:space="preserve" class="light" width="1em" height="1em" fill="currentColor">
                          <g id="Sun">
                              <g>
                                  <path style="fill-rule:evenodd;clip-rule:evenodd;" d="M6,17.5C6,16.672,5.328,16,4.5,16h-3C0.672,16,0,16.672,0,17.5    S0.672,19,1.5,19h3C5.328,19,6,18.328,6,17.5z M7.5,26c-0.414,0-0.789,0.168-1.061,0.439l-2,2C4.168,28.711,4,29.086,4,29.5    C4,30.328,4.671,31,5.5,31c0.414,0,0.789-0.168,1.06-0.44l2-2C8.832,28.289,9,27.914,9,27.5C9,26.672,8.329,26,7.5,26z M17.5,6    C18.329,6,19,5.328,19,4.5v-3C19,0.672,18.329,0,17.5,0S16,0.672,16,1.5v3C16,5.328,16.671,6,17.5,6z M27.5,9    c0.414,0,0.789-0.168,1.06-0.439l2-2C30.832,6.289,31,5.914,31,5.5C31,4.672,30.329,4,29.5,4c-0.414,0-0.789,0.168-1.061,0.44    l-2,2C26.168,6.711,26,7.086,26,7.5C26,8.328,26.671,9,27.5,9z M6.439,8.561C6.711,8.832,7.086,9,7.5,9C8.328,9,9,8.328,9,7.5    c0-0.414-0.168-0.789-0.439-1.061l-2-2C6.289,4.168,5.914,4,5.5,4C4.672,4,4,4.672,4,5.5c0,0.414,0.168,0.789,0.439,1.06    L6.439,8.561z M33.5,16h-3c-0.828,0-1.5,0.672-1.5,1.5s0.672,1.5,1.5,1.5h3c0.828,0,1.5-0.672,1.5-1.5S34.328,16,33.5,16z     M28.561,26.439C28.289,26.168,27.914,26,27.5,26c-0.828,0-1.5,0.672-1.5,1.5c0,0.414,0.168,0.789,0.439,1.06l2,2    C28.711,30.832,29.086,31,29.5,31c0.828,0,1.5-0.672,1.5-1.5c0-0.414-0.168-0.789-0.439-1.061L28.561,26.439z M17.5,29    c-0.829,0-1.5,0.672-1.5,1.5v3c0,0.828,0.671,1.5,1.5,1.5s1.5-0.672,1.5-1.5v-3C19,29.672,18.329,29,17.5,29z M17.5,7    C11.71,7,7,11.71,7,17.5S11.71,28,17.5,28S28,23.29,28,17.5S23.29,7,17.5,7z M17.5,25c-4.136,0-7.5-3.364-7.5-7.5    c0-4.136,3.364-7.5,7.5-7.5c4.136,0,7.5,3.364,7.5,7.5C25,21.636,21.636,25,17.5,25z"></path>
                              </g>
                          </g>
                        </svg>
                        <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" x="0px" y="0px" viewBox="0 0 100 100" enable-background="new 0 0 100 100" xml:space="preserve" class="dark" width="1em" height="1em" fill="currentColor">
                          <path d="M96.76,66.458c-0.853-0.852-2.15-1.064-3.23-0.534c-6.063,2.991-12.858,4.571-19.655,4.571  C62.022,70.495,50.88,65.88,42.5,57.5C29.043,44.043,25.658,23.536,34.076,6.47c0.532-1.08,0.318-2.379-0.534-3.23  c-0.851-0.852-2.15-1.064-3.23-0.534c-4.918,2.427-9.375,5.619-13.246,9.491c-9.447,9.447-14.65,22.008-14.65,35.369  c0,13.36,5.203,25.921,14.65,35.368s22.008,14.65,35.368,14.65c13.361,0,25.921-5.203,35.369-14.65  c3.872-3.871,7.064-8.328,9.491-13.246C97.826,68.608,97.611,67.309,96.76,66.458z"></path>
                        </svg>
                    </i>
                </span>

        <?php
        return wp_send_json_success( array('html' => ob_get_clean() ) );
    }

    public static function rest_page_logo(): WP_REST_Response {
        $settings = array();
        ob_start(); ?>
        <svg width="323" height="29" viewBox="0 0 323 29" fill="none" xmlns="http://www.w3.org/2000/svg">
            <g clip-path="url(#clip0_3592_8)">
                <path d="M19.9396 11.2768L27.6513 0.109177H24.6482C24.5358 0.109177 24.4362 0.128447 24.3495 0.176632C24.3078 0.192688 24.2692 0.218388 24.2339 0.247284C24.1183 0.34042 24.0155 0.459276 23.9159 0.613439L18.6967 8.36362L18.186 9.1184C18.0059 9.38771 18.1669 9.15115 17.8004 9.67125L17.4278 10.2371L16.6828 11.2768L24.6482 23.3629C24.6932 23.3693 24.7414 23.3726 24.7927 23.3726H27.9114L19.9396 11.2768Z" fill="currentColor"/>
                <path d="M10.6611 11.4244L2.98232 0.108879H6.11544C6.34284 0.108879 6.51049 0.146832 6.6187 0.222423C6.72676 0.298328 6.8243 0.406539 6.91103 0.547058L12.9826 9.8658C13.0582 9.6384 13.1719 9.38967 13.3236 9.11898L19.0546 0.612298C19.1518 0.460802 19.2572 0.339103 19.371 0.246888C19.4846 0.154987 19.6226 0.108879 19.7851 0.108879H22.7883L15.0769 11.2782L23.048 23.3728H19.9309C19.6929 23.3728 19.5062 23.3107 19.371 23.1862C19.2355 23.062 19.1248 22.924 19.0383 22.7721L12.804 13.0152C12.7283 13.2426 12.6309 13.459 12.5118 13.6645L6.44023 22.7721C6.34284 22.924 6.23165 23.062 6.10744 23.1862C5.98276 23.3107 5.80712 23.3728 5.57971 23.3728H2.65753L10.6611 11.4244Z" fill="currentColor"/>
                <path d="M42.12 3.92932C42.025 4.08826 41.9239 4.20736 41.8182 4.28702C41.7118 4.3663 41.5739 4.40613 41.4046 4.40613C41.2239 4.40613 41.0148 4.31613 40.7765 4.13575C40.5383 3.95575 40.2358 3.75698 39.8704 3.53984C39.5043 3.32269 39.0646 3.12393 38.5507 2.94354C38.0367 2.76316 37.414 2.67316 36.6825 2.67316C35.9932 2.67316 35.3835 2.76623 34.8542 2.95159C34.3234 3.13695 33.8815 3.38856 33.5264 3.70681C33.171 4.02468 32.9037 4.39809 32.7233 4.8274C32.543 5.25672 32.4533 5.7205 32.4533 6.21875C32.4533 6.85487 32.6096 7.38223 32.9221 7.80082C33.2346 8.21941 33.6482 8.57749 34.1626 8.87392C34.6765 9.17072 35.2594 9.42808 35.9112 9.64523C36.5631 9.86238 37.231 10.0879 37.9142 10.3208C38.5982 10.554 39.2661 10.8164 39.9179 11.1078C40.5697 11.3996 41.1526 11.7677 41.6666 12.2127C42.1813 12.6581 42.5941 13.2042 42.9074 13.8503C43.2199 14.4971 43.3762 15.2922 43.3762 16.2355C43.3762 17.232 43.2061 18.1672 42.8676 19.0415C42.5283 19.9162 42.0327 20.6768 41.3809 21.3233C40.7291 21.9698 39.9286 22.4787 38.9796 22.8495C38.0306 23.2206 36.9529 23.4059 35.7443 23.4059C34.2713 23.4059 32.9275 23.1386 31.7138 22.6032C30.5002 22.0678 29.4642 21.3447 28.6056 20.4329L29.496 18.9699C29.5806 18.8535 29.684 18.7554 29.8058 18.6758C29.9276 18.5965 30.0628 18.5567 30.2114 18.5567C30.4339 18.5567 30.6882 18.6758 30.9747 18.9144C31.2608 19.153 31.6185 19.4153 32.0478 19.7014C32.4771 19.9875 32.9964 20.2498 33.6061 20.4884C34.2154 20.727 34.9599 20.8461 35.84 20.8461C36.5707 20.8461 37.2233 20.7458 37.7955 20.5439C38.3676 20.3429 38.8525 20.0591 39.25 19.6934C39.6475 19.328 39.9524 18.8906 40.1646 18.3817C40.3767 17.8731 40.4824 17.3063 40.4824 16.6805C40.4824 15.9915 40.3262 15.4274 40.0137 14.9874C39.7004 14.5477 39.2898 14.1793 38.7812 13.8821C38.2727 13.5857 37.6921 13.3337 37.0402 13.1269C36.3884 12.9204 35.7205 12.7083 35.0373 12.4911C34.3533 12.274 33.6854 12.022 33.0336 11.7359C32.3817 11.4498 31.8015 11.0787 31.2925 10.623C30.784 10.1672 30.373 9.59736 30.0605 8.91375C29.7476 8.23014 29.5914 7.38491 29.5914 6.37768C29.5914 5.57229 29.7476 4.79332 30.0605 4.04039C30.373 3.28784 30.8261 2.62031 31.4197 2.03704C32.0133 1.45454 32.7475 0.98769 33.6218 0.638034C34.4965 0.288377 35.4999 0.113358 36.6351 0.113358C37.9065 0.113358 39.0677 0.314803 40.1171 0.717693C41.1664 1.12058 42.0825 1.70347 42.8676 2.46636L42.12 3.92932Z" fill="currentColor"/>
                <path d="M62.8206 0.367653V2.95925H55.4598V23.1516H52.3753V2.95925H44.9816V0.367653H62.8206Z" fill="currentColor"/>
                <path d="M85.6682 11.7677C85.6682 13.4742 85.3985 15.0406 84.8578 16.466C84.317 17.8915 83.5541 19.1185 82.5683 20.1468C81.5826 21.1747 80.3976 21.9724 79.0143 22.5396C77.631 23.1068 76.1022 23.3902 74.4278 23.3902C72.7527 23.3902 71.2262 23.1068 69.8482 22.5396C68.4703 21.9724 67.2884 21.1747 66.3026 20.1468C65.3168 19.1185 64.554 17.8915 64.0132 16.466C63.4732 15.0406 63.2028 13.4742 63.2028 11.7677C63.2028 10.0611 63.4732 8.49515 64.0132 7.06934C64.554 5.6439 65.3168 4.41417 66.3026 3.38052C67.2884 2.34725 68.4703 1.54415 69.8482 0.971988C71.2262 0.39944 72.7527 0.113358 74.4278 0.113358C76.1022 0.113358 77.631 0.39944 79.0143 0.971988C80.3976 1.54415 81.5826 2.34725 82.5683 3.38052C83.5541 4.41417 84.317 5.6439 84.8578 7.06934C85.3985 8.49515 85.6682 10.0611 85.6682 11.7677ZM82.5048 11.7677C82.5048 10.3687 82.3141 9.11251 81.9318 7.99958C81.5504 6.88666 81.0096 5.94607 80.3103 5.17744C79.611 4.40881 78.7623 3.81788 77.7666 3.40465C76.7701 2.99103 75.6572 2.78461 74.4278 2.78461C73.2084 2.78461 72.1009 2.99103 71.1044 3.40465C70.1079 3.81788 69.2577 4.40881 68.553 5.17744C67.8475 5.94607 67.3045 6.88666 66.923 7.99958C66.5416 9.11251 66.3509 10.3687 66.3509 11.7677C66.3509 13.1667 66.5416 14.4202 66.923 15.5277C67.3045 16.6357 67.8475 17.5736 68.553 18.3422C69.2577 19.1108 70.1079 19.6991 71.1044 20.107C72.1009 20.5152 73.2084 20.719 74.4278 20.719C75.6572 20.719 76.7701 20.5152 77.7666 20.107C78.7623 19.6991 79.611 19.1108 80.3103 18.3422C81.0096 17.5736 81.5504 16.6357 81.9318 15.5277C82.3141 14.4202 82.5048 13.1667 82.5048 11.7677Z" fill="currentColor"/>
                <path d="M93.2679 13.6439V23.1516H90.1995V0.367653H96.6388C98.0804 0.367653 99.3258 0.513566 100.375 0.805011C101.424 1.09645 102.291 1.51811 102.975 2.06883C103.658 2.62031 104.164 3.28516 104.493 4.06451C104.821 4.84348 104.986 5.71552 104.986 6.67985C104.986 7.48563 104.859 8.23779 104.605 8.93749C104.35 9.63719 103.981 10.2653 103.499 10.8217C103.017 11.3782 102.429 11.8527 101.735 12.2445C101.04 12.637 100.253 12.9338 99.3733 13.1349C99.7547 13.3574 100.094 13.681 100.391 14.105L107.037 23.1516H104.302C103.74 23.1516 103.327 22.9345 103.062 22.4998L97.1474 14.3593C96.9674 14.105 96.7713 13.9219 96.5592 13.8109C96.347 13.6994 96.0291 13.6439 95.6056 13.6439H93.2679ZM93.2679 11.4019H96.4956C97.3964 11.4019 98.1891 11.2936 98.8724 11.076C99.5563 10.8589 100.129 10.5513 100.59 10.1538C101.051 9.75629 101.398 9.28217 101.631 8.73068C101.864 8.17997 101.981 7.57027 101.981 6.90236C101.981 5.54586 101.533 4.52293 100.637 3.83396C99.7417 3.14499 98.409 2.80031 96.6388 2.80031H93.2679V11.4019Z" fill="currentColor"/>
                <path d="M124.399 0.367653V2.87997H113.461V10.4479H122.317V12.8645H113.461V20.6397H124.399V23.1516H110.36V0.367653H124.399Z" fill="currentColor"/>
                <path d="M150.577 1.0675V2.416H142.873V23H141.292V2.416H133.542V1.0675H150.577ZM152.56 23V0.447498H154.032V10.2125C154.745 9.33416 155.556 8.63666 156.466 8.12C157.385 7.593 158.414 7.3295 159.55 7.3295C160.398 7.3295 161.142 7.46383 161.782 7.7325C162.433 8.00116 162.971 8.38866 163.394 8.895C163.818 9.40133 164.138 10.011 164.355 10.724C164.572 11.437 164.681 12.243 164.681 13.142V23H163.208V13.142C163.208 11.6953 162.878 10.5638 162.216 9.7475C161.555 8.92083 160.542 8.5075 159.178 8.5075C158.166 8.5075 157.225 8.771 156.357 9.298C155.489 9.81466 154.714 10.5277 154.032 11.437V23H152.56ZM174.059 7.3295C174.927 7.3295 175.728 7.47933 176.461 7.779C177.205 8.07866 177.846 8.51783 178.383 9.0965C178.931 9.66483 179.355 10.3675 179.654 11.2045C179.964 12.0415 180.119 13.0025 180.119 14.0875C180.119 14.3148 180.083 14.4698 180.011 14.5525C179.949 14.6352 179.845 14.6765 179.701 14.6765H168.556V14.971C168.556 16.1283 168.691 17.1462 168.959 18.0245C169.228 18.9028 169.61 19.6417 170.106 20.241C170.602 20.83 171.202 21.2743 171.904 21.574C172.607 21.8737 173.392 22.0235 174.26 22.0235C175.035 22.0235 175.707 21.9408 176.275 21.7755C176.844 21.5998 177.319 21.4087 177.701 21.202C178.094 20.985 178.404 20.7938 178.631 20.6285C178.859 20.4528 179.024 20.365 179.127 20.365C179.262 20.365 179.365 20.4167 179.437 20.52L179.84 21.016C179.592 21.326 179.262 21.6153 178.848 21.884C178.445 22.1527 177.991 22.3852 177.484 22.5815C176.988 22.7675 176.451 22.9173 175.872 23.031C175.304 23.1447 174.73 23.2015 174.152 23.2015C173.098 23.2015 172.137 23.0207 171.269 22.659C170.401 22.287 169.657 21.7497 169.037 21.047C168.417 20.3443 167.936 19.4867 167.595 18.474C167.265 17.451 167.099 16.2833 167.099 14.971C167.099 13.8653 167.254 12.8475 167.564 11.9175C167.885 10.9772 168.339 10.1712 168.928 9.4995C169.528 8.8175 170.256 8.28533 171.114 7.903C171.982 7.52066 172.963 7.3295 174.059 7.3295ZM174.074 8.43C173.279 8.43 172.566 8.554 171.935 8.802C171.305 9.05 170.757 9.4065 170.292 9.8715C169.838 10.3365 169.466 10.8945 169.176 11.5455C168.897 12.1965 168.711 12.925 168.618 13.731H178.786C178.786 12.9043 178.673 12.1655 178.445 11.5145C178.218 10.8532 177.898 10.2952 177.484 9.8405C177.071 9.38583 176.575 9.03966 175.996 8.802C175.418 8.554 174.777 8.43 174.074 8.43ZM182.994 23V7.5775H183.8C184.069 7.5775 184.224 7.70666 184.265 7.965L184.405 10.197C184.704 9.77333 185.025 9.38583 185.366 9.0345C185.707 8.68316 186.068 8.3835 186.451 8.1355C186.843 7.87716 187.257 7.68083 187.691 7.5465C188.135 7.40183 188.6 7.3295 189.086 7.3295C190.212 7.3295 191.111 7.66016 191.783 8.3215C192.454 8.9725 192.909 9.88183 193.147 11.0495C193.333 10.4088 193.591 9.856 193.922 9.391C194.263 8.926 194.65 8.54366 195.084 8.244C195.518 7.934 195.988 7.70666 196.495 7.562C197.011 7.407 197.538 7.3295 198.076 7.3295C198.84 7.3295 199.528 7.45866 200.137 7.717C200.747 7.965 201.264 8.337 201.687 8.833C202.121 9.329 202.452 9.93866 202.679 10.662C202.907 11.3853 203.02 12.212 203.02 13.142V23H201.532V13.142C201.532 11.6333 201.207 10.4863 200.556 9.701C199.905 8.90533 198.975 8.5075 197.766 8.5075C197.228 8.5075 196.712 8.60566 196.216 8.802C195.73 8.99833 195.296 9.29283 194.914 9.6855C194.542 10.0678 194.242 10.5483 194.015 11.127C193.798 11.7057 193.689 12.3773 193.689 13.142V23H192.217V13.142C192.217 11.6437 191.917 10.4967 191.318 9.701C190.718 8.90533 189.845 8.5075 188.698 8.5075C187.861 8.5075 187.086 8.76066 186.373 9.267C185.66 9.763 185.025 10.4553 184.467 11.344V23H182.994ZM212.395 7.3295C213.263 7.3295 214.063 7.47933 214.797 7.779C215.541 8.07866 216.182 8.51783 216.719 9.0965C217.267 9.66483 217.69 10.3675 217.99 11.2045C218.3 12.0415 218.455 13.0025 218.455 14.0875C218.455 14.3148 218.419 14.4698 218.347 14.5525C218.285 14.6352 218.181 14.6765 218.037 14.6765H206.892V14.971C206.892 16.1283 207.026 17.1462 207.295 18.0245C207.564 18.9028 207.946 19.6417 208.442 20.241C208.938 20.83 209.537 21.2743 210.24 21.574C210.943 21.8737 211.728 22.0235 212.596 22.0235C213.371 22.0235 214.043 21.9408 214.611 21.7755C215.179 21.5998 215.655 21.4087 216.037 21.202C216.43 20.985 216.74 20.7938 216.967 20.6285C217.194 20.4528 217.36 20.365 217.463 20.365C217.597 20.365 217.701 20.4167 217.773 20.52L218.176 21.016C217.928 21.326 217.597 21.6153 217.184 21.884C216.781 22.1527 216.326 22.3852 215.82 22.5815C215.324 22.7675 214.787 22.9173 214.208 23.031C213.64 23.1447 213.066 23.2015 212.488 23.2015C211.434 23.2015 210.473 23.0207 209.605 22.659C208.737 22.287 207.993 21.7497 207.373 21.047C206.753 20.3443 206.272 19.4867 205.931 18.474C205.6 17.451 205.435 16.2833 205.435 14.971C205.435 13.8653 205.59 12.8475 205.9 11.9175C206.22 10.9772 206.675 10.1712 207.264 9.4995C207.863 8.8175 208.592 8.28533 209.45 7.903C210.318 7.52066 211.299 7.3295 212.395 7.3295ZM212.41 8.43C211.614 8.43 210.901 8.554 210.271 8.802C209.641 9.05 209.093 9.4065 208.628 9.8715C208.173 10.3365 207.801 10.8945 207.512 11.5455C207.233 12.1965 207.047 12.925 206.954 13.731H217.122C217.122 12.9043 217.008 12.1655 216.781 11.5145C216.554 10.8532 216.233 10.2952 215.82 9.8405C215.407 9.38583 214.911 9.03966 214.332 8.802C213.753 8.554 213.113 8.43 212.41 8.43ZM246.544 12.026C246.544 13.7207 246.291 15.2603 245.785 16.645C245.278 18.0193 244.565 19.1973 243.646 20.179C242.726 21.1503 241.626 21.9047 240.344 22.442C239.063 22.969 237.642 23.2325 236.082 23.2325C234.542 23.2325 233.132 22.969 231.85 22.442C230.569 21.9047 229.468 21.1503 228.549 20.179C227.629 19.1973 226.911 18.0193 226.394 16.645C225.888 15.2603 225.635 13.7207 225.635 12.026C225.635 10.3417 225.888 8.81233 226.394 7.438C226.911 6.05333 227.629 4.87533 228.549 3.904C229.468 2.92233 230.569 2.16283 231.85 1.6255C233.132 1.08816 234.542 0.819497 236.082 0.819497C237.642 0.819497 239.063 1.08816 240.344 1.6255C241.626 2.1525 242.726 2.90683 243.646 3.8885C244.565 4.87016 245.278 6.05333 245.785 7.438C246.291 8.81233 246.544 10.3417 246.544 12.026ZM244.901 12.026C244.901 10.4967 244.689 9.12233 244.266 7.903C243.842 6.68366 243.243 5.65033 242.468 4.803C241.703 3.95566 240.778 3.30983 239.693 2.8655C238.608 2.41083 237.404 2.1835 236.082 2.1835C234.78 2.1835 233.586 2.41083 232.501 2.8655C231.427 3.30983 230.497 3.95566 229.711 4.803C228.936 5.65033 228.332 6.68366 227.898 7.903C227.474 9.12233 227.262 10.4967 227.262 12.026C227.262 13.5657 227.474 14.9452 227.898 16.1645C228.332 17.3735 228.936 18.4017 229.711 19.249C230.497 20.0963 231.427 20.7422 232.501 21.1865C233.586 21.6308 234.78 21.853 236.082 21.853C237.404 21.853 238.608 21.6308 239.693 21.1865C240.778 20.7422 241.703 20.0963 242.468 19.249C243.243 18.4017 243.842 17.3735 244.266 16.1645C244.689 14.9452 244.901 13.5657 244.901 12.026ZM249.888 28.456V7.5775H250.694C250.828 7.5775 250.936 7.6085 251.019 7.6705C251.102 7.72216 251.148 7.82033 251.159 7.965L251.298 10.352C251.98 9.422 252.776 8.68316 253.685 8.1355C254.605 7.58783 255.633 7.314 256.77 7.314C258.671 7.314 260.149 7.97016 261.203 9.2825C262.257 10.5948 262.784 12.5633 262.784 15.188C262.784 16.3143 262.634 17.3683 262.334 18.35C262.045 19.3213 261.616 20.1687 261.048 20.892C260.479 21.605 259.777 22.1682 258.94 22.5815C258.113 22.9948 257.157 23.2015 256.072 23.2015C255.018 23.2015 254.109 23.0103 253.344 22.628C252.579 22.2457 251.918 21.6773 251.36 20.923V28.456H249.888ZM256.351 8.5075C255.328 8.5075 254.398 8.77616 253.561 9.3135C252.734 9.8405 252.001 10.5793 251.36 11.53V19.621C251.949 20.5097 252.6 21.1348 253.313 21.4965C254.036 21.8582 254.858 22.039 255.778 22.039C256.687 22.039 257.483 21.8737 258.165 21.543C258.847 21.2123 259.415 20.7473 259.87 20.148C260.335 19.5383 260.681 18.815 260.908 17.978C261.146 17.1307 261.265 16.2007 261.265 15.188C261.265 12.894 260.841 11.2097 259.994 10.135C259.157 9.05 257.942 8.5075 256.351 8.5075ZM269.609 23.248C268.565 23.248 267.749 22.9587 267.16 22.38C266.581 21.8013 266.292 20.9075 266.292 19.6985V9.1585H264.075C263.961 9.1585 263.868 9.1275 263.796 9.0655C263.724 9.0035 263.688 8.91566 263.688 8.802V8.2285L266.323 8.0425L266.695 2.5245C266.705 2.4315 266.741 2.34883 266.803 2.2765C266.875 2.20416 266.968 2.168 267.082 2.168H267.78V8.058H272.631V9.1585H267.78V19.621C267.78 20.0447 267.831 20.4115 267.935 20.7215C268.048 21.0212 268.198 21.2692 268.384 21.4655C268.58 21.6618 268.808 21.8065 269.066 21.8995C269.324 21.9925 269.603 22.039 269.903 22.039C270.275 22.039 270.595 21.9873 270.864 21.884C271.133 21.7703 271.365 21.6515 271.562 21.5275C271.758 21.3932 271.918 21.2743 272.042 21.171C272.166 21.0573 272.264 21.0005 272.337 21.0005C272.419 21.0005 272.502 21.0522 272.585 21.1555L272.988 21.8065C272.595 22.2405 272.094 22.5918 271.484 22.8605C270.885 23.1188 270.26 23.248 269.609 23.248ZM277.264 7.5775V23H275.791V7.5775H277.264ZM277.915 2.261C277.915 2.447 277.873 2.62266 277.791 2.788C277.718 2.943 277.62 3.0825 277.496 3.2065C277.372 3.3305 277.228 3.42866 277.062 3.501C276.897 3.57333 276.721 3.6095 276.535 3.6095C276.349 3.6095 276.174 3.57333 276.008 3.501C275.843 3.42866 275.698 3.3305 275.574 3.2065C275.45 3.0825 275.352 2.943 275.28 2.788C275.207 2.62266 275.171 2.447 275.171 2.261C275.171 2.075 275.207 1.89933 275.28 1.734C275.352 1.55833 275.45 1.4085 275.574 1.2845C275.698 1.1605 275.843 1.06233 276.008 0.989998C276.174 0.917665 276.349 0.881498 276.535 0.881498C276.721 0.881498 276.897 0.917665 277.062 0.989998C277.228 1.06233 277.372 1.1605 277.496 1.2845C277.62 1.4085 277.718 1.55833 277.791 1.734C277.873 1.89933 277.915 2.075 277.915 2.261ZM287.503 7.3295C288.609 7.3295 289.596 7.52066 290.464 7.903C291.342 8.275 292.081 8.80716 292.68 9.4995C293.28 10.1918 293.734 11.0288 294.044 12.0105C294.365 12.9818 294.525 14.072 294.525 15.281C294.525 16.49 294.365 17.5802 294.044 18.5515C293.734 19.5228 293.28 20.3547 292.68 21.047C292.081 21.7393 291.342 22.2715 290.464 22.6435C289.596 23.0155 288.609 23.2015 287.503 23.2015C286.398 23.2015 285.406 23.0155 284.527 22.6435C283.659 22.2715 282.92 21.7393 282.311 21.047C281.711 20.3547 281.252 19.5228 280.931 18.5515C280.621 17.5802 280.466 16.49 280.466 15.281C280.466 14.072 280.621 12.9818 280.931 12.0105C281.252 11.0288 281.711 10.1918 282.311 9.4995C282.92 8.80716 283.659 8.275 284.527 7.903C285.406 7.52066 286.398 7.3295 287.503 7.3295ZM287.503 22.039C288.423 22.039 289.224 21.884 289.906 21.574C290.598 21.2537 291.172 20.799 291.626 20.21C292.091 19.621 292.437 18.9132 292.665 18.0865C292.892 17.2495 293.006 16.3143 293.006 15.281C293.006 14.258 292.892 13.328 292.665 12.491C292.437 11.654 292.091 10.941 291.626 10.352C291.172 9.75266 290.598 9.29283 289.906 8.9725C289.224 8.65216 288.423 8.492 287.503 8.492C286.584 8.492 285.778 8.65216 285.085 8.9725C284.403 9.29283 283.83 9.75266 283.365 10.352C282.91 10.941 282.564 11.654 282.326 12.491C282.099 13.328 281.985 14.258 281.985 15.281C281.985 16.3143 282.099 17.2495 282.326 18.0865C282.564 18.9132 282.91 19.621 283.365 20.21C283.83 20.799 284.403 21.2537 285.085 21.574C285.778 21.884 286.584 22.039 287.503 22.039ZM297.315 23V7.5775H298.121C298.39 7.5775 298.545 7.70666 298.586 7.965L298.726 10.29C299.428 9.40133 300.245 8.68833 301.175 8.151C302.115 7.60333 303.159 7.3295 304.306 7.3295C305.153 7.3295 305.897 7.46383 306.538 7.7325C307.189 8.00116 307.726 8.38866 308.15 8.895C308.573 9.40133 308.894 10.011 309.111 10.724C309.328 11.437 309.436 12.243 309.436 13.142V23H307.964V13.142C307.964 11.6953 307.633 10.5638 306.972 9.7475C306.31 8.92083 305.298 8.5075 303.934 8.5075C302.921 8.5075 301.981 8.771 301.113 9.298C300.245 9.81466 299.47 10.5277 298.788 11.437V23H297.315ZM321.496 9.484C321.423 9.62866 321.31 9.701 321.155 9.701C321.041 9.701 320.891 9.639 320.705 9.515C320.53 9.38066 320.292 9.236 319.992 9.081C319.703 8.91566 319.341 8.771 318.907 8.647C318.484 8.51266 317.962 8.4455 317.342 8.4455C316.784 8.4455 316.272 8.52816 315.807 8.6935C315.353 8.8485 314.96 9.06033 314.629 9.329C314.309 9.59766 314.056 9.91283 313.87 10.2745C313.694 10.6258 313.606 10.9978 313.606 11.3905C313.606 11.8762 313.73 12.2792 313.978 12.5995C314.226 12.9198 314.552 13.1937 314.955 13.421C315.358 13.6483 315.812 13.8447 316.319 14.01C316.835 14.1753 317.362 14.3407 317.9 14.506C318.437 14.6713 318.959 14.8573 319.465 15.064C319.982 15.2603 320.442 15.5083 320.845 15.808C321.248 16.1077 321.573 16.4745 321.821 16.9085C322.069 17.3425 322.193 17.8695 322.193 18.4895C322.193 19.1612 322.069 19.7863 321.821 20.365C321.584 20.9437 321.232 21.4448 320.767 21.8685C320.313 22.2922 319.749 22.628 319.078 22.876C318.406 23.124 317.641 23.248 316.784 23.248C315.699 23.248 314.764 23.0775 313.978 22.7365C313.193 22.3852 312.49 21.9305 311.87 21.3725L312.227 20.8455C312.278 20.7628 312.335 20.7008 312.397 20.6595C312.459 20.6182 312.547 20.5975 312.661 20.5975C312.795 20.5975 312.96 20.6802 313.157 20.8455C313.353 21.0108 313.606 21.1917 313.916 21.388C314.237 21.574 314.629 21.7497 315.094 21.915C315.57 22.0803 316.153 22.163 316.846 22.163C317.497 22.163 318.07 22.0752 318.566 21.8995C319.062 21.7135 319.476 21.4655 319.806 21.1555C320.137 20.8455 320.385 20.4838 320.55 20.0705C320.726 19.6468 320.814 19.2025 320.814 18.7375C320.814 18.2208 320.69 17.792 320.442 17.451C320.194 17.11 319.868 16.8207 319.465 16.583C319.062 16.3453 318.602 16.1438 318.086 15.9785C317.579 15.8132 317.052 15.6478 316.505 15.4825C315.967 15.3172 315.44 15.1363 314.924 14.94C314.417 14.7437 313.963 14.4957 313.56 14.196C313.157 13.8963 312.831 13.5347 312.583 13.111C312.335 12.677 312.211 12.1397 312.211 11.499C312.211 10.9513 312.33 10.4243 312.568 9.918C312.805 9.41166 313.141 8.96733 313.575 8.585C314.02 8.20266 314.557 7.89783 315.187 7.6705C315.818 7.44316 316.525 7.3295 317.311 7.3295C318.251 7.3295 319.083 7.46383 319.806 7.7325C320.54 8.00116 321.212 8.4145 321.821 8.9725L321.496 9.484Z" fill="currentColor"/>
            </g>
            <defs>
                <clipPath id="clip0_3592_8">
                    <rect width="323" height="29" fill="currentColor"/>
                </clipPath>
            </defs>
        </svg>
        <?php $settings['logo'] = ob_get_clean();
        $xstore_branding_settings = get_option( 'xstore_white_label_branding_settings', array() );
        if ( count( $xstore_branding_settings ) ) {
            if ( isset( $xstore_branding_settings['control_panel']['logo'] ) && ! empty( $xstore_branding_settings['control_panel']['logo'] ) ) {
                $settings['available'] = false;
                ob_start(); ?>

                <img src="<?php echo esc_url( $xstore_branding_settings['control_panel']['logo'] ); ?>" alt="panel-logo">

                <?php $settings['logo'] = ob_get_clean();
            }
//            if ( isset($xstore_branding_settings['control_panel']['hide_updates']) && $xstore_branding_settings['control_panel']['hide_updates'] == 'on' ){
//                $settings['available'] = false;
//            }
//            if ( isset( $xstore_branding_settings['control_panel']['theme_version'] ) && ! empty( $xstore_branding_settings['control_panel']['theme_version'] ) ) {
//                $settings['version'] = $xstore_branding_settings['control_panel']['theme_version'];
//            }
        }
        ob_start();
        ?>
        <div class="logo-img"><a href="<?php echo esc_url(admin_url( 'admin.php?page=et-panel-welcome' )); ?>">
                <?php echo $settings['logo']; ?>
            </a></div>
        <?php
        return wp_send_json_success( array('html' => ob_get_clean() ) );
    }

    public static function rest_search( WP_REST_Request $request ): WP_REST_Response {
        $q_raw = (string) $request->get_param( 'q' );
        $q     = trim( wp_unslash( $q_raw ) );

        $limit = (int) $request->get_param( 'limit' );
        $limit = max( 1, min( 1000, $limit ) );

        if ( $q === '' ) {
            return rest_ensure_response(
                    array(
                            'q'       => $q,
                            'limit'   => $limit,
                            'matches' => array(),
                    )
            );
        }

        $schema = self::build_schema();
        $fields = isset( $schema['fieldsBySetting'] ) && is_array( $schema['fieldsBySetting'] ) ? $schema['fieldsBySetting'] : array();

        $lower = static function ( string $s ): string {
            return function_exists( 'mb_strtolower' ) ? mb_strtolower( $s, 'UTF-8' ) : strtolower( $s );
        };

        $needle  = $lower( $q );
        $matches = array();

        foreach ( $fields as $setting => $field ) {
            if ( ! is_array( $field ) ) {
                continue;
            }
            if ( isset( $field['type'] ) && (string) $field['type'] === 'custom' ) {
                continue;
            }

            $setting_key = sanitize_key( (string) $setting );
            if ( $setting_key === '' ) {
                continue;
            }

            $hay = trim(
                    (string) ( $field['label'] ?? '' ) . ' ' .
                    (string) ( $field['description'] ?? '' ) . ' ' .
                    (string) ( $field['tooltip'] ?? '' ) . ' ' .
                    (string) $setting_key
            );

            if ( $hay === '' ) {
                continue;
            }

            if ( strpos( $lower( $hay ), $needle ) === false ) {
                continue;
            }

            $matches[] = array(
                    'setting' => $setting_key,
                    'section' => isset( $field['section'] ) ? sanitize_key( (string) $field['section'] ) : '',
            );

            if ( count( $matches ) >= $limit ) {
                break;
            }
        }

        return rest_ensure_response(
                array(
                        'q'       => $q,
                        'limit'   => $limit,
                        'matches' => $matches,
                )
        );
    }

    public static function rest_schema(): WP_REST_Response {
        return rest_ensure_response( self::build_schema() );
    }

    public static function rest_values(): WP_REST_Response {
        $schema = self::build_schema();
        $values = array();

        foreach ( $schema['fieldsBySetting'] as $setting => $field ) {
            $raw               = get_theme_mod( $setting, $field['default'] ?? null );
            $values[ $setting ] = self::sanitize_value( $field, $raw );
        }

        return rest_ensure_response(
                array(
                        'values' => $values,
                )
        );
    }

    public static function rest_export(): WP_REST_Response {
        $schema = self::build_schema();
        $values = array();

        foreach ( $schema['fieldsBySetting'] as $setting => $field ) {
            $raw               = get_theme_mod( $setting, $field['default'] ?? null );
            $values[ $setting ] = self::sanitize_value( $field, $raw );
        }

        $meta = array(
                'exportedAt' => gmdate( 'c' ),
                'site'       => home_url(),
                'theme'      => wp_get_theme()->get( 'Name' ),
                'plugin'     => 'xstore-options-admin-mirror',
                'version'    => self::VERSION,
        );

        return rest_ensure_response(
                array(
                        'meta'   => $meta,
                        'values' => $values,
                )
        );
    }

    public static function rest_import( WP_REST_Request $req ) {
        $schema = self::build_schema();
        $raw    = (array) $req->get_param( 'values' );

        if ( ! $raw ) {
            return new WP_Error( 'xstore_options_empty_import', 'Empty import payload.', array( 'status' => 400 ) );
        }

        $updated = array();
        $ignored = 0;

        foreach ( $raw as $setting => $value ) {
            $setting = sanitize_key( (string) $setting );
            if ( ! $setting ) {
                $ignored++;
                continue;
            }
            if ( ! isset( $schema['fieldsBySetting'][ $setting ] ) ) {
                $ignored++;
                continue;
            }

            $field     = $schema['fieldsBySetting'][ $setting ];
            $sanitized = self::sanitize_value( $field, $value );

            set_theme_mod( $setting, $sanitized );
            $updated_raw         = get_theme_mod( $setting, $field['default'] ?? null );
            $updated[ $setting ] = self::sanitize_value( $field, $updated_raw );
        }

        self::after_save();

        return rest_ensure_response(
                array(
                        'imported' => count( $updated ),
                        'ignored'  => $ignored,
                        'updated'  => $updated,
                )
        );
    }

    public static function rest_fonts(): WP_REST_Response {
        $out = array(
                'standard'  => array(),
                'google'    => array(),
                'variants'  => array(),
                'has_kirki' => false,
        );

        if ( ! class_exists( 'Kirki_Fonts' ) ) {
            return rest_ensure_response( $out );
        }

        $out['has_kirki'] = true;

        $standard_fonts = Kirki_Fonts::get_standard_fonts();
        if ( is_array( $standard_fonts ) ) {
            foreach ( $standard_fonts as $id => $font ) {
                if ( ! is_array( $font ) ) {
                    continue;
                }
                $out['standard'][] = array(
                        'id'      => (string) $id,
                        'label'   => isset( $font['label'] ) ? (string) $font['label'] : (string) $id,
                        'stack'   => isset( $font['stack'] ) ? (string) $font['stack'] : (string) $id,
                        'variant' => isset( $font['variant'] ) ? (string) $font['variant'] : '',
                );
            }
        }

        $google_fonts = Kirki_Fonts::get_google_fonts();
        if ( is_array( $google_fonts ) ) {
            foreach ( $google_fonts as $family => $font ) {
                if ( ! is_array( $font ) ) {
                    continue;
                }
                $out['google'][] = array(
                        'family'   => (string) $family,
                        'label'    => isset( $font['label'] ) ? (string) $font['label'] : (string) $family,
                        'category' => isset( $font['category'] ) ? (string) $font['category'] : '',
                        'variants' => isset( $font['variants'] ) && is_array( $font['variants'] ) ? array_values( $font['variants'] ) : array(),
                );
            }
        }

        if ( method_exists( 'Kirki_Fonts', 'get_all_variants' ) ) {
            $variants = Kirki_Fonts::get_all_variants();
            if ( is_array( $variants ) ) {
                $out['variants'] = $variants;
            }
        }

        return rest_ensure_response( $out );
    }

    public static function rest_save( WP_REST_Request $req ) {
        $schema  = self::build_schema();
        $changes = (array) $req->get_param( 'changes' );

        $updated = array();

        foreach ( $changes as $setting => $value ) {
            $setting = sanitize_key( (string) $setting );

            if ( ! isset( $schema['fieldsBySetting'][ $setting ] ) ) {
                continue;
            }

            $field     = $schema['fieldsBySetting'][ $setting ];
            $sanitized = self::sanitize_value( $field, $value );

            set_theme_mod( $setting, $sanitized );
            $updated_raw         = get_theme_mod( $setting, $field['default'] ?? null );
            $updated[ $setting ] = self::sanitize_value( $field, $updated_raw );
        }

        self::after_save();

        return rest_ensure_response(
                array(
                        'updated' => $updated,
                )
        );
    }

    public static function rest_reset_section( WP_REST_Request $req ) {
        $schema  = self::build_schema();
        $section = sanitize_key( (string) $req->get_param( 'section' ) );

        $fields = $schema['fieldsBySection'][ $section ] ?? null;
        if ( ! $fields || ! is_array( $fields ) ) {
            return new WP_Error( 'xstore_options_invalid_section', 'Invalid section.', array( 'status' => 400 ) );
        }

        $updated = array();

        foreach ( $fields as $field ) {
            $setting = isset( $field['setting'] ) ? sanitize_key( (string) $field['setting'] ) : '';
            if ( ! $setting ) {
                continue;
            }

            remove_theme_mod( $setting );
            $updated_raw         = get_theme_mod( $setting, $field['default'] ?? null );
            $updated[ $setting ] = self::sanitize_value( $field, $updated_raw );
        }

        self::after_save();

        return rest_ensure_response(
                array(
                        'updated' => $updated,
                )
        );
    }

    public static function rest_reset_all() {
        $schema  = self::build_schema();
        $updated = array();

        foreach ( $schema['fieldsBySetting'] as $setting => $field ) {
            remove_theme_mod( $setting );
            $updated_raw         = get_theme_mod( $setting, $field['default'] ?? null );
            $updated[ $setting ] = self::sanitize_value( $field, $updated_raw );
        }

        self::after_save();

        return rest_ensure_response(
                array(
                        'updated' => $updated,
                )
        );
    }

    private static function after_save(): void {
        if ( defined( 'ET_CORE_DIR' ) ) {
            $box_model_addon = trailingslashit( ET_CORE_DIR ) . 'app/models/customizer/addons/kirki-box-model/kirki-box-model.php';
            if ( file_exists( $box_model_addon ) ) {
                require_once $box_model_addon;
                if ( function_exists( 'kirki_box_model_kirki_field_mods' ) ) {
                    kirki_box_model_kirki_field_mods();
                }
            }
        }
        if ( class_exists( '\ETC\App\Controllers\Customizer' ) && class_exists( '\ETC\App\Models\Customizer' ) ) {
            $customizer = \ETC\App\Controllers\Customizer::get_instance( 'ETC\App\Models\Customizer' );
            if ( is_object( $customizer ) && method_exists( $customizer, 'customizer_style' ) ) {
                $customizer->customizer_style( 'kirki-styles' );
            }
        } elseif ( has_action( 'et_regenerate_multiple_style' ) ) {
            do_action( 'et_regenerate_multiple_style', 'kirki-styles' );
        } else {
            update_option( 'xstore_kirki_styles_render', 'generate', false );
        }

        if ( ! class_exists( 'Etheme_Customize_header_Builder' ) && defined( 'ET_CORE_DIR' ) ) {
            $builder_file = trailingslashit( ET_CORE_DIR ) . 'app/models/customizer/builder/class-customize-builder.php';
            if ( file_exists( $builder_file ) ) {
                require_once $builder_file;
            }
        }

        if ( class_exists( 'Etheme_Customize_header_Builder' ) ) {
            try {
                $builder = new \Etheme_Customize_header_Builder();
                if ( method_exists( $builder, 'generate_header_builder_style' ) ) {
                    $builder->generate_header_builder_style( 'all' );
                }
                if ( method_exists( $builder, 'generate_single_product_style' ) ) {
                    $builder->generate_single_product_style( 'all' );
                }
            } catch ( \Throwable $e ) {
            }
        } else {
            update_option( 'xstore_kirki_hb_render', 'generate', false );
            update_option( 'xstore_kirki_sp_render', 'generate', false );
        }

        delete_transient( 'xstore-menu-hash-latest-time' );
    }

    private static function build_schema(): array {
        if ( self::$schema_cache !== null ) {
            return self::$schema_cache;
        }

        $raw_panels        = apply_filters( 'et/customizer/add/panels', array() );
        $raw_sections      = apply_filters( 'et/customizer/add/sections', array() );
        $raw_fields_global = apply_filters( 'et/customizer/add/fields', array() );

        $panels_by_id = array();
        foreach ( (array) $raw_panels as $p ) {
            if ( ! is_array( $p ) ) {
                continue;
            }

            $id = isset( $p['id'] ) ? sanitize_key( (string) $p['id'] ) : '';
            if ( ! $id || in_array( $id, self::$exclude_panels, true ) ) {
                continue;
            }

            $panels_by_id[ $id ] = array(
                    'id'       => $id,
                    'title'    => self::normalize_text( $p['title'] ?? $id ),
                    'icon'     => self::sanitize_icon_classes( $p['icon'] ?? '' ),
                    'priority' => (int) ( $p['priority'] ?? 10 ),
                    'panel'    => isset( $p['panel'] ) ? sanitize_key( (string) $p['panel'] ) : '',
            );
        }

        $sections_by_id = array();
        foreach ( (array) $raw_sections as $s ) {
            if ( ! is_array( $s ) ) {
                continue;
            }

            $name  = isset( $s['name'] ) ? sanitize_key( (string) $s['name'] ) : '';
            $panel = isset( $s['panel'] ) ? sanitize_key( (string) $s['panel'] ) : '';

            if ( ! $name ) {
                continue;
            }
            if ( in_array( $name, self::$exclude_sections, true ) ) {
                continue;
            }
            if ( $panel && in_array( $panel, self::$exclude_panels, true ) ) {
                continue;
            }

            $sections_by_id[ $name ] = array(
                    'id'       => $name,
                    'title'    => self::normalize_text( $s['title'] ?? $name ),
                    'description' => self::sanitize_section_description( $s['description'] ?? '' ),
                    'icon'     => self::sanitize_icon_classes( $s['icon'] ?? '' ),
                    'panel'    => $panel,
                    'priority' => (int) ( $s['priority'] ?? 10 ),
            );
        }

        $sections_by_id[ self::IMPORT_EXPORT_SECTION_ID ] = array(
                'id'       => self::IMPORT_EXPORT_SECTION_ID,
                'title'    => self::normalize_text( __( 'Import / Export', 'xstore-core' ) ),
                'description' => '',
                'icon'     => self::sanitize_icon_classes( 'dashicons-download' ),
                'panel'    => '',
                'priority' => 999999,
        );

        $fields_by_section = array();
        $fields_by_setting = array();

        foreach ( array_keys( $sections_by_id ) as $section_id ) {
            $fields = array();

            if ( is_array( $raw_fields_global ) ) {
                foreach ( $raw_fields_global as $f ) {
                    if ( ! is_array( $f ) ) {
                        continue;
                    }
                    if ( sanitize_key( (string) ( $f['section'] ?? '' ) ) !== $section_id ) {
                        continue;
                    }
                    $fields[] = $f;
                }
            }

            $section_fields = apply_filters( 'et/customizer/add/fields/' . $section_id, array() );
            if ( is_array( $section_fields ) ) {
                $fields = array_merge( $fields, $section_fields );
            }

            $norm_map = array();

            foreach ( $fields as $f ) {
                if ( ! is_array( $f ) ) {
                    continue;
                }

                $type          = isset( $f['type'] ) ? sanitize_key( (string) $f['type'] ) : '';
                $setting       = isset( $f['settings'] ) ? sanitize_key( (string) $f['settings'] ) : '';
                $field_section = isset( $f['section'] ) ? sanitize_key( (string) $f['section'] ) : $section_id;

                if ( ! $type || ! $setting ) {
                    continue;
                }
                if ( ! isset( $sections_by_id[ $field_section ] ) ) {
                    continue;
                }
                if ( in_array( $field_section, self::$exclude_sections, true ) ) {
                    continue;
                }

                $normalized = array(
                        'setting'         => $setting,
                        'type'            => $type,
                        'section'         => $field_section,
                        'label'           => self::normalize_text( $f['label'] ?? '' ),
                        'description'     => $f['description'] ?? '',
                        'tooltip'         => $f['tooltip'] ?? '',
                        'default'         => $f['default'] ?? null,
                        'choices'         => self::deep_strip_tags( $f['choices'] ?? array() ),
                        'multiple'        => isset( $f['multiple'] ) ? (int) $f['multiple'] : null,
                        'placeholder'     => isset( $f['placeholder'] ) ? self::normalize_text( (string) $f['placeholder'] ) : '',
                        'priority'        => (int) ( $f['priority'] ?? 10 ),
                        'active_callback' => self::sanitize_active_callback( $f['active_callback'] ?? array() ),
                );

                if ( isset( $f['choices'] ) && is_array( $f['choices'] ) && ! empty( $f['choices']['setting'] ) ) {
                    $normalized['description'] = esc_html__('Select the text color for your content.', 'xstore-core');
                    $normalized['tooltip'] = '';
                }

                if ( $type === 'repeater' && isset( $f['fields'] ) && is_array( $f['fields'] ) ) {
                    $normalized['fields'] = self::deep_strip_tags( $f['fields'] );
                    $normalized['row_label'] = isset( $f['row_label'] ) && is_array( $f['row_label'] ) ? self::deep_strip_tags( $f['row_label'] ) : array();
                    $normalized['button_label'] = isset( $f['button_label'] ) ? self::normalize_text( (string) $f['button_label'] ) : '';
                    if ( $setting === 'mobile_panel_package_et-mobile' && isset( $normalized['fields']['link'] ) && is_array( $normalized['fields']['link'] ) ) {
                        $choices = $normalized['fields']['link']['choices'] ?? null;
                        if ( ! is_array( $choices ) || empty( $choices ) ) {
                            $normalized['fields']['link']['choices'] = self::get_pages_choices( true );
                        }
                    }
                }

                if ( $setting === 'site_sections' && isset( $normalized['fields']['staticblock'] ) ) {
                    if ( empty( $normalized['fields']['staticblock']['choices'] ) || ! is_array( $normalized['fields']['staticblock']['choices'] ) ) {
                        $normalized['fields']['staticblock']['choices'] = self::get_staticblocks_choices( true );
                    }
                }

                if ( $type === 'custom' ) {
                    $raw_default = (string) ( $f['default'] ?? '' );

                    if ( $raw_default !== '' && false !== strpos( $raw_default, 'et_edit' ) ) {
                        continue;
                    }

                    $title = self::normalize_text( $raw_default );
                    if ( $title === '' ) {
                        continue;
                    }

                    $normalized['is_separator'] = true;
                    $normalized['label']        = $title;
                    $normalized['description']  = '';
                    $normalized['tooltip']      = '';
                    $normalized['default']      = '';
                }

                $norm_map[ $setting ] = $normalized;
            }

            if ( $norm_map ) {
                uasort(
                        $norm_map,
                        static function ( $a, $b ) {
                            return ( $a['priority'] <=> $b['priority'] );
                        }
                );

                $fields_by_section[ $section_id ] = array_values( $norm_map );

                foreach ( $norm_map as $setting => $field ) {
                    $fields_by_setting[ $setting ] = $field;
                }
            }
        }

        foreach ( array_keys( $sections_by_id ) as $section_id ) {
            if ( $section_id === self::IMPORT_EXPORT_SECTION_ID ) {
                continue;
            }
            if ( empty( $fields_by_section[ $section_id ] ) ) {
                unset( $sections_by_id[ $section_id ] );
            }
        }

        uasort(
                $sections_by_id,
                static function ( $a, $b ) {
                    return ( $a['priority'] <=> $b['priority'] );
                }
        );

        self::$schema_cache = array(
                'panelsById'      => $panels_by_id,
                'sections'        => array_values( $sections_by_id ),
                'fieldsBySection' => $fields_by_section,
                'fieldsBySetting' => $fields_by_setting,
        );

        return self::$schema_cache;
    }

    private static function get_staticblocks_choices( bool $with_none ): array {
        $args = array(
                'post_type'      => 'staticblocks',
                'post_status'    => 'publish',
                'posts_per_page' => 200,
                'no_found_rows'  => true,
                'orderby'        => 'title',
                'order'          => 'ASC',
        );

        $posts = get_posts( $args );
        $out   = array();

        foreach ( $posts as $p ) {
            $out[ (string) $p->ID ] = $p->post_title . ' (id - ' . $p->ID . ')';
        }

        if ( $with_none ) {
            $out[0] = esc_html__( 'None', 'xstore-core' );
        }

        return $out;
    }

    private static function get_pages_choices( bool $with_select_page ): array {
        $args = array(
                'post_type'      => 'page',
                'post_status'    => 'publish',
                'posts_per_page' => 200,
                'no_found_rows'  => true,
                'orderby'        => 'title',
                'order'          => 'ASC',
        );

        $posts = get_posts( $args );
        $out   = array();

        if ( $with_select_page ) {
            $out[0] = esc_html__( 'Select page', 'xstore-core' );
        }

        foreach ( $posts as $p ) {
            $out[ (string) $p->ID ] = $p->post_title . ' (id - ' . $p->ID . ')';
        }

        return $out;
    }

    private static function deep_strip_tags( $data ) {
        if ( is_array( $data ) ) {
            $out = array();
            foreach ( $data as $k => $v ) {
                $out[ $k ] = self::deep_strip_tags( $v );
            }
            return $out;
        }
        if ( is_string( $data ) ) {
            $str = wp_strip_all_tags( $data );
            $str = wp_kses_decode_entities( $str );
            return html_entity_decode( $str, ENT_QUOTES, 'UTF-8' );
        }
        return $data;
    }

    private static function normalize_text( $text ): string {
        $str = wp_strip_all_tags( (string) $text );
        $str = wp_kses_decode_entities( $str );
        return html_entity_decode( $str, ENT_QUOTES, 'UTF-8' );
    }

    private static function sanitize_section_description( $html ): string {
        $raw = trim( (string) $html );
        if ( $raw === '' ) {
            return '';
        }

        $raw = wp_specialchars_decode( $raw, ENT_QUOTES );

        $allowed = wp_kses_allowed_html( 'post' );
        if ( ! isset( $allowed['a'] ) || ! is_array( $allowed['a'] ) ) {
            $allowed['a'] = array();
        }
        $allowed['a']['href']   = true;
        $allowed['a']['rel']    = true;
        $allowed['a']['target'] = true;
        $allowed['a']['title']  = true;
        $allowed['a']['class']  = true;

        return trim( wp_kses( $raw, $allowed ) );
    }

    private static function sanitize_icon_classes( $icon ): string {
        $raw = trim( self::normalize_text( $icon ) );
        if ( $raw === '' ) {
            return '';
        }

        $tokens = preg_split( '/\s+/', $raw );
        if ( ! is_array( $tokens ) ) {
            return sanitize_html_class( $raw );
        }

        $out = array();
        foreach ( $tokens as $t ) {
            $t = sanitize_html_class( $t );
            if ( $t !== '' ) {
                $out[] = $t;
            }
        }

        $out = array_values( array_unique( $out ) );

        return implode( ' ', $out );
    }

    private static function sanitize_active_callback( $data ): array {
        if ( ! is_array( $data ) ) {
            return array();
        }

        $out = array();

        foreach ( $data as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }

            $setting  = isset( $row['setting'] ) ? sanitize_key( (string) $row['setting'] ) : '';
            $operator = isset( $row['operator'] ) ? (string) $row['operator'] : '==';
            $value    = $row['value'] ?? null;

            if ( ! $setting ) {
                continue;
            }

            $out[] = array(
                    'setting'  => $setting,
                    'operator' => $operator,
                    'value'    => $value,
            );
        }

        return $out;
    }

    private static function sanitize_value( array $field, $value ) {
        $type    = (string) ( $field['type'] ?? '' );
        $default = $field['default'] ?? null;

        if ( $type === 'toggle' ) {
            return ( ! empty( $value ) ) ? 1 : 0;
        }

        if ( $type === 'checkbox' ) {
            return ( ! empty( $value ) ) ? true : false;
        }

        if ( $type === 'slider' || $type === 'number' ) {
            if ( $value === '' || $value === null ) {
                return '';
            }
            if ( is_numeric( $value ) ) {
                return 0 + $value;
            }
            return is_numeric( $default ) ? ( 0 + $default ) : 0;
        }

        if ( $type === 'select' ) {
            $choices   = $field['choices'] ?? array();
            $allowed   = is_array( $choices ) ? array_keys( $choices ) : array();
            $multiple  = isset( $field['multiple'] ) ? (int) $field['multiple'] : 0;
            $is_multi  = $multiple > 1;

            if ( $is_multi ) {
                $raw = array();
                if ( is_array( $value ) ) {
                    $raw = $value;
                } elseif ( is_string( $value ) ) {
                    $raw = array_filter( array_map( 'trim', explode( ',', $value ) ) );
                } else {
                    return is_array( $default ) ? $default : array();
                }

                $selected = array();
                foreach ( $raw as $v ) {
                    $k = trim( (string) $v );
                    if ( $k !== '' ) {
                        $selected[] = $k;
                    }
                }
                $selected = array_values( array_unique( $selected ) );

                if ( $allowed ) {
                    $allowed_set = array_fill_keys( array_map( 'strval', $allowed ), true );
                    $out         = array();
                    foreach ( $allowed as $k ) {
                        $k = (string) $k;
                        if ( isset( $allowed_set[ $k ] ) && in_array( $k, $selected, true ) ) {
                            $out[] = $k;
                        }
                    }
                    $selected = $out;
                }

                if ( $multiple > 0 && count( $selected ) > $multiple ) {
                    $selected = array_slice( $selected, 0, $multiple );
                }

                return $selected;
            }

            $value = (string) $value;
            if ( is_array( $choices ) && array_key_exists( $value, $choices ) ) {
                return $value;
            }
            return is_string( $default ) ? $default : '';
        }

        if ( $type === 'radio-buttonset' || $type === 'radio-image' ) {
            $choices = $field['choices'] ?? array();
            $value   = (string) $value;

            if ( is_array( $choices ) && array_key_exists( $value, $choices ) ) {
                return $value;
            }
            return is_string( $default ) ? $default : '';
        }

        if ( $type === 'multicheck' ) {
            $choices = $field['choices'] ?? array();
            $allowed = is_array( $choices ) ? array_keys( $choices ) : array();

            $raw = array();
            if ( is_array( $value ) ) {
                $raw = $value;
            } elseif ( is_string( $value ) ) {
                $raw = array_filter( array_map( 'trim', explode( ',', $value ) ) );
            } else {
                return is_array( $default ) ? $default : array();
            }

            $selected = array();
            foreach ( $raw as $v ) {
                $k = sanitize_key( (string) $v );
                if ( $k !== '' ) {
                    $selected[] = $k;
                }
            }
            $selected = array_values( array_unique( $selected ) );

            if ( $allowed ) {
                $set = array_fill_keys( $selected, true );
                $out = array();
                foreach ( $allowed as $k ) {
                    $k = sanitize_key( (string) $k );
                    if ( isset( $set[ $k ] ) ) {
                        $out[] = $k;
                    }
                }
                return $out;
            }

            return $selected;
        }

        if ( $type === 'color' ) {
            $value = trim( (string) $value );
            if ( $value === '' ) {
                return '';
            }
            if ( sanitize_hex_color( $value ) ) {
                return $value;
            }
            if ( preg_match( '/^rgba?\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*(,\s*(0|1|0?\.\d+)\s*)?\)$/', $value ) ) {
                return $value;
            }
            return is_string( $default ) ? $default : '';
        }

        if ( $type === 'image' ) {
            if ( $value === '' || $value === null ) {
                return '';
            }
            if ( is_array( $value ) ) {
                return array(
                        'id'  => isset( $value['id'] ) ? absint( $value['id'] ) : 0,
                        'url' => isset( $value['url'] ) ? esc_url_raw( (string) $value['url'] ) : '',
                );
            }
            return esc_url_raw( (string) $value );
        }

        if ( $type === 'upload' ) {
            if ( $value === '' || $value === null ) {
                return '';
            }
            if ( is_array( $value ) ) {
                return isset( $value['url'] ) ? esc_url_raw( (string) $value['url'] ) : '';
            }
            return esc_url_raw( (string) $value );
        }

        if ( $type === 'textarea' || $type === 'etheme-textarea' ) {
            return sanitize_textarea_field( (string) $value );
        }

        if ( $type === 'editor' ) {
            return wp_kses_post( (string) $value );
        }

        if ( $type === 'code' ) {
            return wp_strip_all_tags( (string) $value );
        }

        if ( $type === 'background' ) {
            if ( ! is_array( $value ) ) {
                return is_array( $default ) ? $default : array();
            }

            return array(
                    'background-color'      => isset( $value['background-color'] ) ? sanitize_text_field( (string) $value['background-color'] ) : '',
                    'background-image'      => isset( $value['background-image'] ) ? esc_url_raw( (string) $value['background-image'] ) : '',
                    'background-repeat'     => isset( $value['background-repeat'] ) ? sanitize_text_field( (string) $value['background-repeat'] ) : '',
                    'background-position'   => isset( $value['background-position'] ) ? sanitize_text_field( (string) $value['background-position'] ) : '',
                    'background-size'       => isset( $value['background-size'] ) ? sanitize_text_field( (string) $value['background-size'] ) : '',
                    'background-attachment' => isset( $value['background-attachment'] ) ? sanitize_text_field( (string) $value['background-attachment'] ) : '',
            );
        }

        if ( $type === 'typography' && class_exists( 'Kirki_Field_Typography' ) && method_exists( 'Kirki_Field_Typography', 'sanitize' ) ) {
            $out = Kirki_Field_Typography::sanitize( $value );

            if ( is_array( $out ) && array_key_exists( 'letter-spacing', $out ) ) {
                $ls = $out['letter-spacing'];
                if ( is_string( $ls ) ) {
                    $ls = trim( $ls );
                }
                if ( is_numeric( $ls ) && (float) $ls !== 0.0 ) {
                    $out['letter-spacing'] = (string) $ls . 'px';
                }
            }

            return $out;
        }

        if ( $type === 'dimensions' || $type === 'multicolor' || $type === 'typography' ) {
            if ( ! is_array( $value ) ) {
                return is_array( $default ) ? $default : array();
            }
            $out = array();
            foreach ( $value as $k => $v ) {
                $k = sanitize_key( (string) $k );
                if ( is_string( $v ) ) {
                    $out[ $k ] = sanitize_text_field( $v );
                } else {
                    $out[ $k ] = $v;
                }
            }
            return $out;
        }

        if ( $type === 'sortable' ) {
            $choices = $field['choices'] ?? array();
            if ( ! is_array( $value ) ) {
                return is_array( $default ) ? $default : array();
            }
            $out = array();
            foreach ( $value as $v ) {
                $v = sanitize_key( (string) $v );
                if ( is_array( $choices ) && ! array_key_exists( $v, $choices ) ) {
                    continue;
                }
                $out[] = $v;
            }
            return array_values( array_unique( $out ) );
        }

        if ( $type === 'repeater' ) {
            if ( ! is_array( $value ) ) {
                return is_array( $default ) ? $default : array();
            }
            $out = array();
            foreach ( $value as $row ) {
                if ( ! is_array( $row ) ) {
                    continue;
                }
                $clean = array();
                foreach ( $row as $k => $v ) {
                    $k = sanitize_key( (string) $k );
                    if ( is_string( $v ) ) {
                        $clean[ $k ] = sanitize_text_field( $v );
                    } elseif ( is_bool( $v ) ) {
                        $clean[ $k ] = $v;
                    } elseif ( is_numeric( $v ) ) {
                        $clean[ $k ] = 0 + $v;
                    } else {
                        $clean[ $k ] = $v;
                    }
                }
                $out[] = $clean;
            }
            return $out;
        }


        if ( is_array( $value ) ) {
            $sanitize = static function ( $v ) use ( &$sanitize ) {
                if ( is_array( $v ) ) {
                    $out = array();
                    foreach ( $v as $k => $vv ) {
                        $out[ $k ] = $sanitize( $vv );
                    }
                    return $out;
                }
                if ( is_string( $v ) ) {
                    return sanitize_text_field( $v );
                }
                if ( is_bool( $v ) ) {
                    return $v;
                }
                if ( is_numeric( $v ) ) {
                    return 0 + $v;
                }
                return $v;
            };

            return $sanitize( $value );
        }

        return sanitize_text_field( (string) $value );
    }
}

XStore_Options_Admin::init();