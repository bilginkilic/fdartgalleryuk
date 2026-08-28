<?php
/**
 * Description
 *
 * @package    config-js.php
 * @since      1.0.0
 * @author     Stas
 * @link       http://xstore.8theme.com
 * @license    Themeforest Split Licence
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

$localize_package = [
    'etheme_ajax_search' => [
        'name'   => 'etheme_search_config',
        'params' => [
            'noResults'              => esc_html__( 'No results were found!', 'xstore' ),
            'product'                => esc_html__( 'Products', 'xstore' ),
            'page'                   => esc_html__( 'Pages', 'xstore' ),
            'post'                   => esc_html__( 'Posts', 'xstore' ),
            'etheme_portfolio'       => esc_html__( 'Portfolio', 'xstore' ),
            'product_found'          => esc_html__( '{{count}} Products found', 'xstore' ),
            'page_found'             => esc_html__( '{{count}} Pages found', 'xstore' ),
            'post_found'             => esc_html__( '{{count}} Posts found', 'xstore' ),
            'etheme_portfolio_found' => esc_html__( '{{count}} Portfolio found', 'xstore' ),
            'custom_post_type_found' => esc_html__( '{{count}} {{post_type}} found', 'xstore' ),
            'show_more'              => esc_html__( 'Show {{count}} more', 'xstore' ),
            'show_all'               => esc_html__( 'View all results', 'xstore' ),
            'items_found'            => esc_html__( '{{count}} items found', 'xstore' ),
            'item_found'             => esc_html__( '{{count}} item found', 'xstore' ),
        ]
    ],
    'etheme_facebook_sdk_config' => [
        'name'   => 'etheme_facebook_sdk_config',
        'params' => [
            'facebook_sdk' => array(
                'lang'   => get_locale(),
                'app_id' => get_option( 'etheme_facebook_app_id', get_theme_mod( 'facebook_app_id', '' ) ),
            )
        ],
    ],
    'etheme_lottie_config' => [
        'name'   => 'etheme_lottie_config',
        'params' => [
            'defaultAnimationUrl' => defined('ET_CORE_URL') ? ET_CORE_URL . 'app/assets/js/lottie-default.json' : '',
        ]
    ],
    'sidebar_canvas' => [
        'name'   => 'etheme_canvas_sidebar_config',
        'params' => apply_filters('etheme_canvas_sidebar_config', [
            'open_action'   => 'click',
            'close_action'   => 'click touchstart',
        ]),
    ],
	'etheme_pjax'=> [
		'name'   => 'etheme_pjax_config',
		'params' => apply_filters('etheme_pjax_config', [
            'is_etheme_pjax'   => !get_theme_mod( 'disable_pjax_navigation', false ),
        ]),
	]

];

return array(
	'etheme'                              => array(
		'title'     => esc_html__( 'Global etheme script', 'xstore' ),
		'name'      => 'etheme',
        'elementor-preview' => false,
		'file'      => '/js/etheme-scripts.min.js',
        'localize'  => $localize_package['etheme_pjax'],
		'deps'      => array( 'jquery' ),
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	'breadcrumbs-effect-mouse'            => array(
		'title'     => esc_html__( 'Breadcrumbs effect mouse', 'xstore' ),
		'name'      => 'breadcrumbs-effect-mouse',
		'file'      => '/js/modules/breadcrumbs/effect-mouse.min.js',
        'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	'breadcrumbs-effect-text-scroll'      => array(
		'title'     => esc_html__( 'Breadcrumbs effect text scroll', 'xstore' ),
		'name'      => 'breadcrumbs-effect-text-scroll',
		'file'      => '/js/modules/breadcrumbs/effect-text-scroll.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	'portfolio'                           => array(
		'title'     => esc_html__( 'Portfolio', 'xstore' ),
		'name'      => 'portfolio',
		'file'      => '/js/portfolio.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	'fixed-header'                        => array(
		'title'     => esc_html__( 'Fixed Header', 'xstore' ),
		'name'      => 'fixed-header',
		'file'      => '/js/modules/fixedHeader.min.js',
        'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	'fixed-footer'                        => array(
		'title'     => esc_html__( 'Fixed Footer', 'xstore' ),
		'name'      => 'fixed-footer',
		'file'      => '/js/modules/fixed-footer.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	'back-top'                            => array(
		'title'     => esc_html__( 'Back to top', 'xstore' ),
		'name'      => 'back-top',
        'elementor-preview' => false,
		'file'      => '/js/modules/back-top.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	// swiper js
	'et_swiper-slider'                    => array(
		'title'     => esc_html__( 'Swiper slider', 'xstore' ),
		'name'      => 'et_swiper-slider',
		'file'      => '/js/modules/swiper.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	// swiper-slider-init
	'et_swiper-slider-init'               => array(
		'title'     => esc_html__( 'Swiper slider init', 'xstore' ),
		'name'      => 'et_swiper-slider-init',
		'file'      => '/js/modules/swiperInit.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	// tabs js
	'etheme-tabs'                         => array(
		'title'     => esc_html__( 'Etheme Tabs', 'xstore' ),
		'name'      => 'etheme-tabs',
        'elementor-preview' => false,
		'file'      => '/js/modules/tabs.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	// libraries
	'fancy-select'                        => array(
		'title'     => esc_html__( 'Fancy select library', 'xstore' ),
		'name'      => 'fancy-select',
		'file'      => '/js/modules/libs/fancy.select.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
//	'etheme-parallax-hover-effect' => array(
//		'title' => esc_html__('Parallax Hover Effect library', 'xstore'),
//		'name' => 'etheme-parallax-hover-effect',
//		'file'=> '/js/modules/libs/parallaxHoverEffect.min.js',
//		'in_footer' => [
//            'in_footer' => true,
//            'strategy' => 'defer'
//        ]
//	),
	// sticky kit
	'sticky-kit'                          => array(
		'title'     => esc_html__( 'Sticky kit library', 'xstore' ),
		'name'      => 'sticky-kit',
        'elementor-preview' => false,
		'file'      => '/js/modules/libs/jquery.sticky-kit.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
    'etheme_waypoints'                          => array(
        'title'     => esc_html__( 'Waypoints', 'xstore' ),
        'name'      => 'etheme_waypoints',
        'elementor-preview' => false,
        'file'      => '/js/libs/waypoints.js',
        'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
    ),
	'etheme_optimize'                     => array(
		'title'     => esc_html__( 'Etheme Optimize', 'xstore' ),
		'name'      => 'etheme_optimize',
		'file'      => '/js/etheme.optimize.min.js',
		'deps'      => array(),
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	'et_imagesLoaded'                     => array(
		'title'     => esc_html__( 'Images loaded library', 'xstore' ),
		'name'      => 'et_imagesLoaded',
        'elementor-preview' => false,
		'file'      => '/js/libs/imagesLoaded.js',
		'version'   => '4.1.4',
		'deps'      => array(),
		'in_footer' => true
	),
	'et_slick_slider'                     => array(
		'title'     => esc_html__( 'Slick slider library', 'xstore' ),
		'name'      => 'et_slick_slider',
        'elementor-preview' => false,
		'file'      => '/js/libs/slick.min.js',
		'version'   => '1.8.1',
		'deps'      => array(),
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	'et_isotope'                          => array(
		'title'     => esc_html__( 'Isotope', 'xstore' ),
		'name'      => 'et_isotope',
		'file'      => '/js/modules/isotope.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	'jquery_lazyload'                     => array(
		'title'     => esc_html__( 'jQuery lazyload library', 'xstore' ),
		'name'      => 'jquery_lazyload',
        'elementor-preview' => false,
		'file'      => '/js/libs/jquery.lazyload.js',
		'version'   => '2.0.0',
		'deps'      => array(),
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	'et_flying_pages'                     => array(
		'title'     => esc_html__( 'Flying pages', 'xstore' ),
		'name'      => 'et_flying_pages',
		'deps'      => array(),
//        'elementor-preview' => false,
		'file'      => '/js/libs/flying-pages.min.js',
		'localize'  => [
			'name'   => 'FPConfig',
			'params' => [
				'delay'          => 3600,
				'ignoreKeywords' => [
					'wp-admin',
					'logout',
					'wp-login.php',
					'add-to-cart=',
					'customer-logout',
					'remove_item=',
					'apply_coupon=',
					'remove_coupon=',
					'undo_item=',
					'update_cart=',
					'proceed=',
					'removed_item=',
					'added-to-cart=',
					'order_again='
				],
				'maxRPS'         => 3,
				'hoverDelay'     => 50,
			],
		],
        'in_footer' => [
            'in_footer' => false,
            'strategy' => 'defer'
        ]
	),
	'jquery_pjax' => array(
		'title'     => esc_html__( 'jQuery pjax library', 'xstore' ),
		'name'      => 'jquery_pjax',
        'elementor-preview' => false,
		'file'      => '/js/libs/jquery.pjax.min.js',
		'version'   => '2.0.1',
		'deps'      => array(),
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	'photoswipe_optimize'                 => array(
		'title'     => esc_html__( 'Photoswipe library', 'xstore' ),
		'name'      => 'photoswipe_optimize',
//        'elementor-preview' => false,
		'file'      => '/js/photoswipe-optimize.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	// sticky sidebar
	'sticky_sidebar'                      => array(
		'title'     => esc_html__( 'Sticky sidebar', 'xstore' ),
		'name'      => 'sticky_sidebar',
        'elementor-preview' => false,
		'file'      => '/js/modules/stickySidebar.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	// off-canvas sidebar
	'canvas_sidebar'                      => array(
		'title'     => esc_html__( 'Sidebar off-canvas', 'xstore' ),
		'name'      => 'canvas_sidebar',
		'file'      => '/js/modules/sidebarCanvas.min.js',
        'elementor-preview' => false,
        'localize'  => $localize_package['sidebar_canvas'],
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	// widgets open/close
	'widgets_open_close'                  => array(
		'title'     => esc_html__( 'Widgets open/close', 'xstore' ),
		'name'      => 'widgets_open_close',
		'file'      => '/js/modules/widgetsOpenClose.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	// widgets show more
	'widgets_show_more'                   => array(
		'title'     => esc_html__( 'Widgets show more', 'xstore' ),
		'name'      => 'widgets_show_more',
        'elementor-preview' => false,
		'file'      => '/js/modules/widgetsShowMore.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	// product categories widget
	'product_categories_widget'           => array(
		'title'     => esc_html__( 'Product categories widget accordion', 'xstore' ),
		'name'      => 'product_categories_widget',
        'elementor-preview' => false,
		'file'      => '/js/modules/productCategoriesWidget.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	// nav_menu widget
	'nav_menu_widget'                     => array(
		'title'     => esc_html__( 'Navigation menu widget', 'xstore' ),
		'name'      => 'nav_menu_widget',
		'file'      => '/js/modules/customMenuAccordion.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	// post backstretch
	'backstretch_single_postImg'          => array(
		'title'     => esc_html__( 'Post backstretch', 'xstore' ),
		'name'      => 'backstretch_single_postImg',
		'version'   => '2.1.18',
		'file'      => '/js/postBackstretchImg.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	'comments_form_validation'            => array(
		'title'     => esc_html__( 'Comments form validation', 'xstore' ),
		'name'      => 'comments_form_validation',
		'file'      => '/js/modules/commentsForm.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	// woocommerce
	'etheme_mini_cart'                    => array(
		'title'     => esc_html__( 'Etheme Mini-cart', 'xstore' ),
		'name'      => 'etheme_mini_cart',
		'file'      => '/js/mini-cart.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	'mobile_panel'                        => array(
		'title'     => esc_html__( 'Mobile Panel', 'xstore' ),
		'name'      => 'mobile_panel',
        'elementor-preview' => false,
		'file'      => '/js/modules/mobilePanel.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	// header parts
	'ajax_search'                         => array(
		'title'     => esc_html__( 'Ajax search', 'xstore' ),
		'name'      => 'ajax_search',
        'elementor-preview' => false,
		'file'      => '/js/modules/ajaxSearch.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	// mobileMenu
	'mobile_menu'                         => array(
		'title'     => esc_html__( 'Mobile menu', 'xstore' ),
		'name'      => 'mobile_menu',
		'file'      => '/js/modules/mobileMenu.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	// All departments menu
	'all_departments_menu'                => array(
		'title'     => esc_html__( 'All departments menu', 'xstore' ),
		'name'      => 'all_departments_menu',
		'file'      => '/js/modules/all-departments-menu.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	// onePageMenu
	'one_page_menu'                       => array(
		'title'     => esc_html__( 'One page menu', 'xstore' ),
		'name'      => 'one_page_menu',
		'file'      => '/js/modules/onePageMenu.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	'mega_menu'                           => array(
		'title'     => esc_html__( 'Mega menu', 'xstore' ),
		'name'      => 'mega_menu',
		'file'      => '/js/modules/mega-menu.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	'menu_item_on_click'                  => array(
		'title'     => esc_html__( 'Menu item on click', 'xstore' ),
		'name'      => 'menu_item_on_click',
		'file'      => '/js/modules/menu-item-on-click.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	'menu_item_on_touch'                  => array(
		'title'     => esc_html__( 'Menu item on touch', 'xstore' ),
		'name'      => 'menu_item_on_touch',
		'file'      => '/js/modules/menu-item-on-touch.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	// menu_posts
	'menu_posts'                          => array(
		'title'     => esc_html__( 'Menu posts', 'xstore' ),
		'name'      => 'menu_posts',
		'file'      => '/js/modules/menuPosts.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	// promo text
	'promo_text_carousel'                 => array(
		'title'     => esc_html__( 'Promo text carousel', 'xstore' ),
		'name'      => 'promo_text_carousel',
		'file'      => '/js/modules/promoTextCarousel.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	// yith wishlist
	'et_wishlist'                         => array(
		'title'     => esc_html__( 'Wishlist', 'xstore' ),
		'name'      => 'et_wishlist',
		'file'      => '/js/modules/wishlist.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	// woocommerce
	'et_woocommerce'                      => array(
		'title'     => esc_html__( 'WooCommerce', 'xstore' ),
		'name'      => 'et_woocommerce',
        'elementor-preview' => false,
		'file'      => '/js/modules/woocommerce.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
    'etheme_product_in_cart_checker'      => array(
        'title'     => esc_html__( 'Checker product in Cart', 'xstore' ),
        'name'      => 'etheme_product_in_cart_checker',
        'file'      => '/js/modules/ajaxCheckerProductInCart.min.js',
        'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
    ),
    'et_product_hover_slider'                      => array(
        'title'     => esc_html__( 'Hover slider', 'xstore' ),
        'name'      => 'et_product_hover_slider',
        'elementor-preview' => false,
        'file'      => '/js/modules/libs/automaticProductSlider.min.js',
        'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
    ),
	'et_single_product'                   => array(
		'title'     => esc_html__( 'Single product', 'xstore' ),
		'name'      => 'et_single_product',
        'elementor-preview' => false,
		'file'      => '/js/modules/single-product.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	'et_single_product_sticky_images'     => array(
		'title'     => esc_html__( 'Single product sticky images', 'xstore' ),
		'name'      => 'et_single_product_sticky_images',
        'elementor-preview' => false,
		'file'      => '/js/modules/single-product-sticky-product-images.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	'et_single_product_vertical_gallery'  => array(
		'title'     => esc_html__( 'Single product vertical gallery', 'xstore' ),
		'name'      => 'et_single_product_vertical_gallery',
        'elementor-preview' => false,
		'file'      => '/js/modules/single-product-vertical-gallery.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
    'et_single_product_sticky_cart'   => array(
        'title'     => esc_html__( 'Single product sticky cart', 'xstore' ),
        'name'      => 'et_single_product_sticky_cart',
        'elementor-preview' => false,
        'file'      => '/js/modules/single-product-sticky-cart.min.js',
        'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
    ),
	'et_single_product_bought_together'   => array(
		'title'     => esc_html__( 'Single product bought together', 'xstore' ),
		'name'      => 'et_single_product_bought_together',
        'elementor-preview' => false,
		'file'      => '/js/modules/single-product-bought-together.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	// variation gallery
	'et_single_product_variation_gallery' => array(
		'title'     => esc_html__( 'Single product variation gallery', 'xstore' ),
		'name'      => 'et_single_product_variation_gallery',
		'file'      => '/js/modules/variation-gallery.min.js',
		'deps'      => array( 'jquery', 'wp-util' ),
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	'et_single_product_builder'           => array(
		'title'     => esc_html__( 'Single product builder', 'xstore' ),
		'name'      => 'et_single_product_builder',
        'elementor-preview' => false,
		'file'      => '/js/modules/single-product-builder.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	// request a quote, wishlist ask an estimated, waitlist popup
	'call_popup'                          => array(
		'title'     => esc_html__( 'Call popup (request quote)', 'xstore' ),
		'name'      => 'call_popup',
        'elementor-preview' => false,
		'file'      => '/js/modules/call-popup.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	// cart page
	'cart_page'                           => array(
		'title'     => esc_html__( 'Cart page', 'xstore' ),
		'name'      => 'cart_page',
		'file'      => '/js/modules/cart-page.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	// cart progress bar
	'cart_progress_bar'                   => array(
		'title'     => esc_html__( 'Cart progress bar', 'xstore' ),
		'name'      => 'cart_progress_bar',
        'elementor-preview' => false,
		'file'      => '/js/modules/cartProgressBar.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	// cart/checkout countdown
	'cart_checkout_countdown'             => array(
		'title'     => esc_html__( 'Cart/Checkout countdown', 'xstore' ),
		'name'      => 'cart_checkout_countdown',
        'elementor-preview' => false,
		'file'      => '/js/modules/cartCheckoutCountdown.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	// cart/checkout advanced layout
	'cart_checkout_advanced_layout'       => array(
		'title'     => esc_html__( 'Cart/Checkout advanced layout', 'xstore' ),
		'name'      => 'cart_checkout_advanced_layout',
//        'elementor-preview' => false,
		'file'      => '/js/modules/cartCheckoutAdvancedLayout.min.js',
		'in_footer' => true
	),
    // cart/checkout advanced labels
    'cart_checkout_advanced_labels'       => array(
        'title'     => esc_html__( 'Cart/Checkout advanced labels', 'xstore' ),
        'name'      => 'cart_checkout_advanced_labels',
        'elementor-preview' => false,
        'file'      => '/js/modules/cartCheckoutAdvancedLabels.min.js',
        'in_footer' => true
    ),
    // checkout product quantity
    'checkout_product_quantity'       => array(
        'title'     => esc_html__( 'Checkout product quantity', 'xstore' ),
        'name'      => 'checkout_product_quantity',
        'elementor-preview' => false,
        'file'      => '/js/modules/checkoutProductQuantity.min.js',
        'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
    ),
	// filters area
	'filters_area'                        => array(
		'title'     => esc_html__( 'Filters area', 'xstore' ),
		'name'      => 'filters_area',
		'file'      => '/js/modules/filtersArea.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	// elements
	'the_look'                            => array(
		'title'     => esc_html__( 'The look', 'xstore' ),
		'name'      => 'the_look',
		'file'      => '/js/modules/theLook.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	'et_countdown'                        => array(
		'title'     => esc_html__( 'Countdown', 'xstore' ),
		'name'      => 'et_countdown',
        'elementor-preview' => false,
		'file'      => '/js/modules/countdown.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	// compatibilities
	'et_yith_compare'                     => array(
		'title'     => esc_html__( 'Compare', 'xstore' ),
		'name'      => 'et_yith_compare',
		'file'      => '/js/modules/compare.min.js',
        'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	'et_sb_infinite_scroll_load_more'     => array(
		'title'     => esc_html__( 'Infinite scroll & ajax pagination', 'xstore' ),
		'name'      => 'et_sb_infinite_scroll_load_more',
		'file'      => '/js/modules/infinite_scroll_load_more.min.js',
        'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	// product reviews images
	'et_reviews_images'                   => array(
		'title'     => esc_html__( 'Reviews Images', 'xstore' ),
		'name'      => 'et_reviews_images',
		'deps'      => array(),
        'elementor-preview' => false,
		'file'      => '/js/modules/reviews-images.min.js',
        'in_footer' => [
            'in_footer' => false,
            'strategy' => 'defer'
        ]
	),
    // product reviews likes
    'et_reviews_likes'                   => array(
        'title'     => esc_html__( 'Reviews Likes', 'xstore' ),
        'name'      => 'et_reviews_likes',
        'elementor-preview' => false,
        'file'      => '/js/modules/reviews-likes.min.js',
        'in_footer' => [
            'in_footer' => false,
            'strategy' => 'defer'
        ]
    ),
    // product reviews criteria
    'et_reviews_criteria'                   => array(
        'title'     => esc_html__( 'Reviews Criteria', 'xstore' ),
        'name'      => 'et_reviews_criteria',
        'elementor-preview' => false,
        'file'      => '/js/modules/reviews-criteria.min.js',
        'in_footer' => [
            'in_footer' => false,
            'strategy' => 'defer'
        ]
    ),
	
	// old widgets but improved
	'etheme_advanced_tabs'                => array(
		'title'     => esc_html__( 'Etheme Advanced Tabs widget', 'xstore' ),
		'name'      => 'etheme_advanced_tabs',
        'elementor-preview' => false,
		'file'      => '/js/modules/ethemeAdvancedTabs.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	
	'etheme_general_tabs' => array(
		'title'     => esc_html__( 'Etheme General Tabs widget', 'xstore' ),
		'name'      => 'etheme_general_tabs',
        'elementor-preview' => false,
		'file'      => '/js/modules/ethemeGeneralTabs.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	
	// new widgets
	'etheme_countdown' => array(
		'title'     => esc_html__( 'Etheme Countdown Elementor widget', 'xstore' ),
		'name'      => 'etheme_countdown',
        'elementor-preview' => false,
		'file'      => '/js/modules/ethemeCountdown.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	
	'etheme_hotspot' => array(
		'title'     => esc_html__( 'Etheme Hotspot Elementor widget', 'xstore' ),
		'name'      => 'etheme_hotspot',
        'elementor-preview' => false,
		'file'      => '/js/modules/ethemeHotspot.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	
	'etheme_animated_headline' => array(
		'title'     => esc_html__( 'Etheme Animated Headline Elementor widget', 'xstore' ),
		'name'      => 'etheme_animated_headline',
        'elementor-preview' => false,
		'file'      => '/js/modules/ethemeAnimatedHeadline.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),

    'etheme_advanced_calculator' => array(
        'title'     => esc_html__( 'Etheme Advanced Calculator Elementor widget', 'xstore' ),
        'name'      => 'etheme_advanced_calculator',
        'elementor-preview' => false,
        'file'      => '/js/modules/ethemeAdvancedCalculator.min.js',
        'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
    ),
	
	'etheme_circle_progress_bar' => array(
		'title'     => esc_html__( 'Etheme Circle Progress Bar Elementor widget', 'xstore' ),
		'name'      => 'etheme_circle_progress_bar',
		'file'      => '/js/modules/ethemeCircleProgressBar.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	
	'etheme_linear_progress_bar' => array(
		'title'     => esc_html__( 'Etheme Linear Progress Bar Elementor widget', 'xstore' ),
		'name'      => 'etheme_linear_progress_bar',
        'elementor-preview' => false,
		'file'      => '/js/modules/ethemeLinearProgressBar.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	
	'etheme_vertical_timeline' => array(
		'title'     => esc_html__( 'Etheme Vertical Timeline Elementor widget', 'xstore' ),
		'name'      => 'etheme_vertical_timeline',
        'elementor-preview' => false,
		'file'      => '/js/modules/ethemeVerticalTimeline.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	
	'etheme_horizontal_timeline' => array(
		'title'     => esc_html__( 'Etheme Vertical Horizontal Elementor widget', 'xstore' ),
		'name'      => 'etheme_horizontal_timeline',
        'elementor-preview' => false,
		'file'      => '/js/modules/ethemeHorizontalTimeline.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	
	'etheme_product_filters' => array(
		'title'     => esc_html__( 'Etheme Product filters Elementor widget', 'xstore' ),
		'name'      => 'etheme_product_filters',
        'elementor-preview' => false,
		'file'      => '/js/modules/ethemeProductFilters.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	
	'etheme_gallery' => array(
		'title'     => esc_html__( 'Etheme Gallery Elementor widget', 'xstore' ),
		'name'      => 'etheme_gallery',
        'elementor-preview' => false,
		'file'      => '/js/modules/ethemeGallery.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	
	'etheme_media_carousel' => array(
		'title'     => esc_html__( 'Etheme Media Carousel Elementor widget', 'xstore' ),
		'name'      => 'etheme_media_carousel',
        'elementor-preview' => false,
		'file'      => '/js/modules/ethemeMediaCarousel.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	
	'etheme_ajax_search' => array(
		'title'     => esc_html__( 'Etheme Search Elementor widget', 'xstore' ),
		'name'      => 'etheme_ajax_search',
        'elementor-preview' => false,
		'file'      => '/js/modules/ethemeSearch.min.js',
		'localize'  => $localize_package['etheme_ajax_search'],
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	
	'etheme_parallax_3d_hover_effect' => array(
		'title'     => esc_html__( 'Etheme Parallax 3d Hover Effect', 'xstore' ),
		'name'      => 'etheme_parallax_3d_hover_effect',
        'elementor-preview' => false,
		'file'      => '/js/modules/ethemeParallax3dHoverEffect.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	
	'etheme_parallax_hover_effect' => array(
		'title'     => esc_html__( 'Etheme Parallax Hover Effect', 'xstore' ),
		'name'      => 'etheme_parallax_hover_effect',
        'elementor-preview' => false,
		'file'      => '/js/modules/ethemeParallaxHoverEffect.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	
	'etheme_parallax_scroll_effect' => array(
		'title'     => esc_html__( 'Etheme Parallax Scroll Effect', 'xstore' ),
		'name'      => 'etheme_parallax_scroll_effect',
        'elementor-preview' => false,
		'file'      => '/js/modules/ethemeParallaxScrollEffect.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	
	'etheme_parallax_floating_effect' => array(
		'title'     => esc_html__( 'Etheme Parallax Floating Effect', 'xstore' ),
		'name'      => 'etheme_parallax_floating_effect',
        'elementor-preview' => false,
		'file'      => '/js/modules/ethemeParallaxFloatingEffect.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	
	'etheme_facebook_sdk' => array(
		'title'     => esc_html__( 'Etheme Facebook Sdk', 'xstore' ),
		'name'      => 'etheme_facebook_sdk',
        'elementor-preview' => false,
		'file'      => '/js/modules/ethemeFacebookSdk.min.js',
		'localize'  => $localize_package['etheme_facebook_sdk_config'],
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'async'
        ]
	),
	
	'etheme_twitter_feed' => array(
		'title'     => esc_html__( 'Etheme Twitter Feed', 'xstore' ),
		'name'      => 'etheme_twitter_feed',
        'elementor-preview' => false,
		'file'      => '/js/modules/ethemeTwitterFeed.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	
	'etheme_elementor_slider' => array(
		'title'     => esc_html__( 'Etheme Elementor Slider', 'xstore' ),
		'name'      => 'etheme_elementor_slider',
        'elementor-preview' => false,
		'file'      => '/js/modules/ethemeElementorSlider.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	
	'etheme_image_comparison' => array(
		'title'     => esc_html__( 'Etheme Image Comparison', 'xstore' ),
		'name'      => 'etheme_image_comparison',
        'elementor-preview' => false,
		'file'      => '/js/modules/ethemeImageComparison.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	
	'etheme_icon_list' => array(
		'title'     => esc_html__( 'Etheme Icon List', 'xstore' ),
		'name'      => 'etheme_icon_list',
        'elementor-preview' => false,
		'file'      => '/js/modules/ethemeIconList.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	
	'etheme_lottie' => array(
		'title'     => esc_html__( 'Etheme Lottie', 'xstore' ),
		'name'      => 'etheme_lottie',
        'elementor-preview' => false,
		'file'      => '/js/modules/ethemeLottie.min.js',
		'localize'  => $localize_package['etheme_lottie_config'],
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	
	'etheme_scroll_progress' => array(
		'title'     => esc_html__( 'Etheme Scroll Progress', 'xstore' ),
		'name'      => 'etheme_scroll_progress',
        'elementor-preview' => false,
		'file'      => '/js/modules/ethemeScrollProgress.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	
	'etheme_three_sixty_product_viewer' => array(
		'title'     => esc_html__( 'Etheme Three Sixty Product Viewer', 'xstore' ),
		'name'      => 'etheme_three_sixty_product_viewer',
        'elementor-preview' => false,
		'file'      => '/js/modules/ethemeThreeSixtyProductViewer.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	
	'etheme_modal_popup' => array(
		'title'     => esc_html__( 'Etheme Modal Popup', 'xstore' ),
		'name'      => 'etheme_modal_popup',
        'elementor-preview' => false,
		'file'      => '/js/modules/ethemeModalPopup.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	
	'etheme_post_product' => array(
		'title'     => esc_html__( 'Etheme Post Product', 'xstore' ),
		'name'      => 'etheme_post_product',
        'elementor-preview' => false,
		'file'      => '/js/modules/ethemePostProduct.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	
	'etheme_elementor_tabs' => array(
		'title'     => esc_html__( 'Etheme Elementor Tabs', 'xstore' ),
		'name'      => 'etheme_elementor_tabs',
        'elementor-preview' => false,
		'file'      => '/js/modules/ethemeElementorTabs.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	
	'etheme_content_switcher' => array(
		'title'     => esc_html__( 'Etheme Content Switcher', 'xstore' ),
		'name'      => 'etheme_content_switcher',
        'elementor-preview' => false,
		'file'      => '/js/modules/ethemeContentSwitcher.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
	
	'etheme_toggle_text' => array(
		'title'     => esc_html__( 'Etheme Toggle Text', 'xstore' ),
		'name'      => 'etheme_toggle_text',
        'elementor-preview' => false,
		'file'      => '/js/modules/ethemeToggleText.min.js',
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),

    'etheme_marquee' => array(
        'title'     => esc_html__( 'Etheme Marquee', 'xstore' ),
        'name'      => 'etheme_marquee',
        'elementor-preview' => false,
        'file'      => '/js/modules/ethemeMarquee.min.js',
        'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
    ),

    'etheme_elementor_sidebar' => array(
        'title'     => esc_html__( 'Etheme Sidebar', 'xstore' ),
        'name'      => 'etheme_elementor_sidebar',
        'elementor-preview' => false,
        'file'      => '/js/modules/ethemeElementorSidebar.min.js',
        'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
    ),

    'etheme_elementor_off_canvas' => array(
        'title'     => esc_html__( 'Etheme Elementor Off-Canvas', 'xstore' ),
        'name'      => 'etheme_elementor_off_canvas',
        'elementor-preview' => false,
        'file'      => '/js/modules/ethemeElementorOffCanvas.min.js',
        'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
    ),

    'etheme_elementor_horizontal_sidebar_toggle' => array(
        'title'     => esc_html__( 'Etheme Horizontal Sidebar Toggle', 'xstore' ),
        'name'      => 'etheme_elementor_horizontal_sidebar_toggle',
        'elementor-preview' => false,
        'file'      => '/js/modules/ethemeHorizontalSidebarToggle.min.js',
        'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
    ),

    'etheme_elementor_checkout_page' => array(
        'title'     => esc_html__( 'Etheme Checkout Page', 'xstore' ),
        'name'      => 'etheme_elementor_checkout_page',
        'elementor-preview' => false,
        'file'      => '/js/modules/ethemeCheckoutPage.min.js',
        'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
    ),

    'etheme_elementor_breadcrumbs_steps' => array(
        'title'     => esc_html__( 'Etheme Elementor Breadcrumbs Steps', 'xstore' ),
        'name'      => 'etheme_elementor_breadcrumbs_steps',
        'elementor-preview' => false,
        'file'      => '/js/modules/ethemeElementorBreadcrumbsSteps.min.js',
        'in_footer' => true
    ),

    'etheme_elementor_mega_menu' => array(
        'title'     => esc_html__( 'Etheme Mega Menu', 'xstore' ),
        'name'      => 'etheme_elementor_mega_menu',
        'elementor-preview' => false,
        'file'      => '/js/modules/ethemeElementorMegaMenu.min.js',
        'in_footer' => true
    ),

    'etheme_elementor_all_widgets_js' => array(
        'title'     => esc_html__( 'Etheme Elementor All widgets', 'xstore' ),
        'name'      => 'etheme_elementor_all_widgets_js',
        'file'      => '/js/elementor-all-widgets.min.js',
        'elementor-preview' => true,
        'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
    ),

    'etheme_general_all_js' => array(
        'title'     => esc_html__( 'Etheme All Scripts', 'xstore' ),
        'name'      => 'etheme_general_all_js',
        'file'      => '/js/general-all.min.js',
        'elementor-preview' => true,
        'localize_package'  => $localize_package,
        'deps'      => array( 'jquery' ),
        'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
    ),

	'etheme_elementor_sticky_column' => array(
		'title'     => esc_html__( 'Etheme Elementor Sticky Column', 'xstore' ),
		'name'      => 'etheme_elementor_sticky_column',
        'elementor-preview' => false,
		'file'      => '/js/modules/ethemeElementorStickyColumn.min.js',
		'deps'      => array( 'jquery', 'sticky-kit' ),
		'in_footer' => true
	),

    'etheme_elementor_wrapper_link' => array(
        'title'     => esc_html__( 'Etheme Elementor Wrapper link', 'xstore' ),
        'name'      => 'etheme_elementor_wrapper_link',
        'file'      => '/js/modules/ethemeElementorWrapperLink.min.js',
        'in_footer' => true
    ),

    'etheme_elementor_header_sticky' => array(
        'title'     => esc_html__( 'Etheme Elementor Header Sticky', 'xstore' ),
        'name'      => 'etheme_elementor_header_sticky',
        'elementor-preview' => false,
        'file'      => '/js/modules/ethemeElementorHeaderSticky.min.js',
        'in_footer' => true
    ),

    'etheme_woocommerce_all_scripts' => array(
        'title'     => esc_html__( 'Etheme WooCommerce Elements scripts', 'xstore' ),
        'name'      => 'etheme_woocommerce_all_scripts',
        'elementor-preview' => true,
        'file'      => '/js/woocommerce-all-scripts.min.js',
        'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
    ),

	'et_etspt'      => array(
		'title'     => esc_html__( 'Etheme shop pagination type', 'xstore' ),
		'name'      => 'et_etspt',
		'file'      => '/js/modules/etspt.min.js',
		'deps'      => array( 'jquery', 'ajaxFilters'),
		'in_footer' => [
            'in_footer' => true,
            'strategy' => 'defer'
        ]
	),
);