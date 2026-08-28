<?php
/**
 * Main XStore PJAX class
 * 
 * @package    PJAX
 * @since      9.5.5
 * @version    1.0.0
 */
class Etheme_PJAX {

    /**
     * Constructor.
     * Attaches the necessary WordPress hooks.
     */
    public function __construct() {
        // Actions 
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ], 30 );
        add_action( 'template_redirect', [ $this, 'handle_template_redirect' ] );

        //Filters always after actions!
        add_filter( 'paginate_links_output',[ $this, 'normalize_pagination_links' ], 10, 2 );
    }

    /**
     * Enqueue PJAX scripts only on the frontend.
     *
     * - Registers and enqueues the `jquery.pjax` library
     * - Registers and enqueues the theme-specific PJAX initializer
     */
    public function enqueue_scripts() {
        // Do not load in admin or during et_ajax requests
        if ( is_admin() || ( isset($_GET['et_ajax']) && $_GET['et_ajax'] ) ) {
            return;
        }

        // Load PJAX only on blog (posts archive) or portfolio archive pages
        if ( ! $this->is_pjax_page() ) {
            add_filter('etheme_pjax_config', '__return_false');
            return;
        }

        // Register and enqueue jquery-pjax
        wp_register_script(
            'jquery_pjax',
            ETHEME_BASE_URI . 'js/libs/jquery.pjax.min.js',
            [ 'jquery' ],
            null,
            true
        );
        wp_enqueue_script( 'jquery_pjax' );

        // Register and enqueue theme PJAX initializer
        wp_register_script(
            'etheme_pjax',
            ETHEME_BASE_URI . 'js/themePjax.min.js',
            apply_filters('etheme_elementor_force_localize_global_assets', 'etheme',  'general'),
            null,
            true
        );
        wp_enqueue_script( 'etheme_pjax' );
    }

    /**
     * Intercepts the template rendering for PJAX requests.
     *
     * - Checks if the request is a PJAX request
     * - Detects the container selector
     * - Loads the corresponding partial template
     * - Exits to prevent rendering the full layout
     */
    public function handle_template_redirect() {
        if ( ! $this->is_pjax() ) {
            return;
        }

        $container = $this->get_pjax_container();

        switch ( $container ) {
            case '.portfolio-wrapper':
                get_template_part('framework/features/pjax/templates/portfolio');
                break;
            case '.content':
                get_template_part('framework/features/pjax/templates/blog');
            break;

            case '.main-products-loop':
                get_template_part('framework/features/pjax/templates/shop');
            break;
            case '.content-page':
                get_template_part('framework/features/pjax/templates/shop-filters');
            break;
            case '.elementor-widget-woocommerce-etheme_archive_products':
                get_template_part('framework/features/pjax/templates/elementor-shop');
            break;
            case '[data-elementor-type=\"product-archive\"]':
                get_template_part('framework/features/pjax/templates/elementor-shop');
            break;
            case '.elementor-widget-archive-etheme_posts':
                get_template_part('framework/features/pjax/templates/elementor-blog');
            break;
            default:
            break;
        }
        exit;
    }

    /**
     * Checks whether the current request is a PJAX request.
     *
     * @return bool
     */
    public function is_pjax() {
        return ! empty( $_SERVER['HTTP_X_PJAX'] );
    }

    /**
     * Retrieves the PJAX container selector from the request headers.
     *
     * @return string
     */
    public function get_pjax_container() {
        return $_SERVER['HTTP_X_PJAX_CONTAINER'] ?? '';
    }

    /**
     * Check if current page is eligible for PJAX
     *
     * @return bool
     */
    public function is_pjax_page() {
        $blog_page_id = (int) get_option('page_for_posts');

        // Blog page
        if ( is_home() && ( ! is_front_page() || get_queried_object_id() === $blog_page_id ) ) {
            return true;
        }

        // Blog archive
        if ( is_post_type_archive('post') ) {
            return true;
        }

        // Portfolio archive
        if ( is_post_type_archive('portfolio') ) {
            return true;
        }

        // WooCommerce shop page
        if ( function_exists('is_shop') && is_shop() ) {
            return true;
        }

        // WooCommerce product categories
        if ( function_exists('is_product_category') && is_product_category() ) {
            return true;
        }

        // WooCommerce product brands (assuming "brand" taxonomy)
        if ( is_tax('brand') ) {
            return true;
        }

        return false;
    }

    /**
     * Clean paginate_links() HTML for PJAX and AJAX
     */
    public function normalize_pagination_links($html, $args){
        $html = preg_replace_callback(
            '#\bhref=("|\')([^"\']+)\1#i',
            function( $m ) {
                $q  = $m[1];
                $url = $m[2];

                $url = remove_query_arg( array( '_pjax', 'et_ajax' ), $url );

                $url = preg_replace( '/[?&]+$/', '', $url );

                return 'href=' . $q . $url . $q;
            },
            $html
        );
        return $html;
    }
}

// Initialize the class
new Etheme_PJAX();