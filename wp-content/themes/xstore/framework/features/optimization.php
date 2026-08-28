<?php
/**
 * Description
 *
 * @package    optimization.php
 * @since      1.0.0
 * @author     theme
 * @link       http://xstore.8theme.com
 * @license    Themeforest Split Licence
 */


class XStore_Optimization {
	
	public static $instance = null;
	
	function init(){
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_page_css_files' ), 99999 );
		add_action( 'wp_head', array($this, 'xstore_font_prefetch'));
		add_filter( 'wp_title', array($this,'wp_title'), 10, 2 );
		add_filter( 'et_menu_cache', array($this,'menu_cache'), 10, 2 );

        add_filter( 'wpseo_canonical', array($this, 'et_strip_et_ajax_from_canonical'), 10, 1 ); // Yoast example
        add_filter( 'get_canonical_url', array($this, 'et_strip_et_ajax_from_canonical'), 10, 1 ); // Core

        if (get_theme_mod( 'et_force_cache', false )){
			if ( ! defined( 'DONOTCACHEPAGE' ) ) {
				define( 'DONOTCACHEPAGE', 0 );
			}
			add_filter( 'rocket_override_donotcachepage', '__return_true', PHP_INT_MAX );
		}
	}
	function enqueue_page_css_files(){
        if ( isset( $_GET['et_ajax'] ) && $_GET['et_ajax']) return;

        $disable_theme_swiper_js = defined('ELEMENTOR_VERSION') && etheme_get_option( 'disable_theme_swiper_js', false );
	    // Dequeue theme swiper-slider (enqueued in theme-init.php)
        if ($disable_theme_swiper_js){
            wp_dequeue_script( 'et_swiper-slider' );
        }
		
		// and not preview/edit view
		if ( defined( 'ELEMENTOR_VERSION' ) ) {

            // Enqueue elementor or theme swiper-slider
            if ($disable_theme_swiper_js) {
                require_once(apply_filters('etheme_file_url', ETHEME_CODE . 'features/swiper.php'));
            }
			
			if ( !(
				is_preview() ||
				Elementor\Plugin::$instance->preview->is_preview_mode()
			)
			) {
				
				if ( get_theme_mod( 'disable_elementor_dialog_js', 1 ) ) {
					$scripts = wp_scripts();
					if ( ! ( $scripts instanceof WP_Scripts ) ) {
						return;
					}
					
					$handles_to_remove = [
						'elementor-dialog',
					];
					
					$handles_updated = false;
					
					foreach ( $scripts->registered as $dependency_object_id => $dependency_object ) {
						if ( 'elementor-frontend' === $dependency_object_id ) {
							if ( ! ( $dependency_object instanceof _WP_Dependency ) || empty( $dependency_object->deps ) ) {
								return;
							}
							
							foreach ( $dependency_object->deps as $dep_key => $handle ) {
								if ( in_array( $handle, $handles_to_remove ) ) { // phpcs:ignore
									unset( $dependency_object->deps[ $dep_key ] );
									$dependency_object->deps = array_values( $dependency_object->deps );
									$handles_updated         = true;
								}
							}
						}
					}
					
					if ( $handles_updated ) {
						wp_deregister_script( 'elementor-dialog' );
						wp_dequeue_script( 'elementor-dialog' );
					}
				}
			}
		}
		
		if ( get_theme_mod( 'disable_block_css', 0 ) ){
			wp_deregister_style( 'wp-block-library' );
			wp_deregister_style( 'wp-block-library-theme' );
			wp_deregister_style( 'wc-block-style' );
			wp_deregister_style( 'wc-blocks-vendors-style' );

			wp_dequeue_style( 'wp-block-library' );
			wp_dequeue_style( 'wp-block-library-theme' );
			wp_dequeue_style( 'wc-blocks-style' );
			wp_dequeue_style( 'wc-blocks-editor-style' );
		}
	}
	
	public function xstore_font_prefetch() {
        if ( !apply_filters('etheme_should_enqueue_icons_style', true) ) return;
		$icons_type = ( etheme_get_option('bold_icons', 0) ) ? 'bold' : 'light';
		
		if ( apply_filters('etheme_preload_woff_icons', true)) : ?>
			<link rel="prefetch" as="font" href="<?php echo esc_url( get_template_directory_uri() ); ?>/fonts/xstore-icons-<?php echo esc_attr($icons_type); ?>.woff?v=<?php echo esc_attr( ETHEME_THEME_VERSION ); ?>" type="font/woff">
		<?php endif;
		
		if ( apply_filters('etheme_preload_woff2_icons', true)) : ?>
			<link rel="prefetch" as="font" href="<?php echo esc_url( get_template_directory_uri() ); ?>/fonts/xstore-icons-<?php echo esc_attr($icons_type); ?>.woff2?v=<?php echo esc_attr( ETHEME_THEME_VERSION ); ?>" type="font/woff2">
		<?php
		endif;
	}
	
	public function wp_title($title, $sep ) {
		global $paged, $page;
		
		if ( is_feed() ) {
			return $title;
		}
		
		// Add the site name.
		$title .= get_bloginfo( 'name', 'display' );
		
		// Add the site description for the home/front page.
		$site_description = get_bloginfo( 'description', 'display' );
		if ( $site_description && ( is_home() || is_front_page() ) ) {
			$title = "$title $sep $site_description";
		}
		
		// Add a page number if necessary.
		if ( ( $paged >= 2 || $page >= 2 ) && ! is_404() ) {
			$title = "$title $sep " . sprintf( esc_html__( 'Page %s', 'xstore' ), max( $paged, $page ) );
		}
		
		return $title;
	}

	/**
	 * Returns the instance.
	 *
	 * @return object
	 * @since  8.3.6
	 */
	public static function get_instance( $shortcodes = array() ) {
		
		if ( null == self::$instance ) {
			self::$instance = new self( $shortcodes );
		}
		
		return self::$instance;
	}

	/**
	 * Add menu cache.
	 *
	 * @return string
	 * @since  9.1.1
     * @version 1.0.1
	 */
	public function menu_cache($args, $id){
		if (is_object($id) && isset($id->term_id) ) {
			$id = $id->term_id;
		}
        $id = (string)$id;
	    $group = 'etheme_menu-cache';
	    $output = '';

	    if (
		    $id
            && get_theme_mod('menu_cache', 0)
            // @todo make prevent menus from cache as option like ajaxify menus setting
            && !in_array($id, apply_filters('etheme_prevent_menus_from_cache', array()))
            && !in_array($id, get_theme_mod('menus_ajaxify', array()))
        ){
	        // local page cache
		    $output = wp_cache_get( $id, $group );
		    if (!$output || empty($output)){
			    // global object cache
			    $output = $this->menu_as_string($args, true);
                wp_cache_set($id, $output, $group);
		    }
		    return $output;
	    } else {
		    $output = $this->menu_as_string($args);
		    if ($id){
			    wp_cache_delete($id, $group);
		    }
	    }
	    return $output;
    }

    public function et_strip_et_ajax_from_canonical( $url ) {
        if ( isset( $_GET['et_ajax'] ) ) {
            if ( empty( $url ) ) {
                $url = home_url( add_query_arg( [] ) );
            }
            $url = remove_query_arg( 'et_ajax', $url );
        }
        return $url;
    }

	/**
	 * Add menu object cache.
	 *
	 * @return string
	 * @since  9.1.1
	 * @version 1.0.0
	 */
    public function menu_as_string($args = array(), $cache = false){
	    if ($cache){
		    ob_start();
		    echo wp_nav_menu( $args );
		    return ob_get_clean();
	    } else {
		    return wp_nav_menu( $args );
	    }
    }
}
$optimization = new XStore_Optimization();
$optimization->init();