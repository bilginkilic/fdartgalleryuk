<?php
/**
 * Description
 *
 * @package    ajax-functions.php
 * @since      8.0.0
 * @author     andrey
 * @link       http://xstore.8theme.com
 * @license    Themeforest Split Licence
 */

defined( 'ABSPATH' ) || exit( 'Direct script access denied.' );


// **********************************************************************//
// ! Ajax response for shortcodes/VC elements loading
// **********************************************************************//
add_action( 'wp_ajax_et_ajax_element', 'et_ajax_element');
add_action( 'wp_ajax_nopriv_et_ajax_element', 'et_ajax_element');
if ( ! function_exists( 'et_ajax_element' ) ) {
	function et_ajax_element(){
		if ( ! isset( $_POST[ 'element' ] ) ) wp_die(esc_html__('Element parameter required', 'xstore'), 400);

		$element = $_POST[ 'element' ];

		$allowed_elements = array(
			'banner',
			'etheme_carousel',
			'etheme_brands',
			'et_category',
			'etheme_categories',
			'etheme_categories_lists',
			'follow',
			'icon_box',
			'instagram',
			'et_looks',
			'etheme_products',
			'team_member',
			'et_the_look',
			'et_offer',
			'title',
			'twitter',
			'quick_view',
			'button',
			'counter',
			'dropcap',
			'mark',
			'blockquote',
			'checklist',
			'countdown',
			'qrcode',
			'tooltip',
			'share',
			'block',
			'et_fancy_button',
			'et_blog',
			'et_blog_timeline',
			'et_blog_list',
			'et_blog_carousel',
			'menu',
			'etheme_brands_list',
			'etheme_post_meta',
			'et_menu_list',
			'et_menu_list_item',
			'et_icons_list',
			'etheme_slider',
			'etheme_slider_item',
			'etheme_scroll_text',
			'etheme_scroll_text_item',
			'portfolio',
			'portfolio_recent'
		);
		
		$allowed_widgets = array(
			'About_Author',
			'Apply_All_Filters',
			'Brands_Filter',
			'Brands',
			'Categories_Filter',
			'Clear_all_filters',
			'Featured_Posts',
			'Instagram',
			'Layered_Nav_Filters',
			'Menu',
			'Posts_Tabs',
			'Price_Filter',
			'Product_Status_Filter',
			'Products',
			'QR_Code',
			'Recent_Comments',
			'Recent_Posts',
			'Search',
			'Socials',
			'Static_Block',
			'Swatches_Filter',
			'Twitter'
		);

		if (
			!in_array($element, $allowed_elements)
			&& ! !in_array($element, $allowed_widgets)
		){
			die();
		}

		$atts = '';
		$args = ( isset( $_POST['args'] ) ) ? $_POST['args'] : array() ;
		$args['ajax'] = false;
		$args['ajax_loaded'] = true;

        if ( class_exists('XStoreCore\Modules\WooCommerce\XStore_Wishlist')) {
            $wishlist_instance = XStoreCore\Modules\WooCommerce\XStore_Wishlist::get_instance();
            add_filter('etheme_wishlist_btn_output', array($wishlist_instance, 'old_wishlist_btn_filter'), 10, 2);
        }
		
		if (isset($_POST[ 'type' ]) ) {
			if ($_POST[ 'type' ] == 'widget'){
				$widget_args = (isset($_POST[ 'widget_args' ])) ? et_normalize_widget_args($_POST[ 'widget_args' ]) : array();
				the_widget( 'ETC\App\Models\Widgets\\' .$element,$args, $widget_args );
				wp_die();
			}
		}
		
		foreach ( $args as $key => $value ) {
			// ! Do it because js change data type
			if ( $value === 'false' ) $value = false;
			
			$atts .= esc_attr($key) . '="' . esc_attr($value) . '" ';
		}
		
		add_filter('etheme_output_shortcodes_inline_css', '__return_true');
		
		add_filter( 'woocommerce_available_variation', 'etheme_available_variation_gallery', 90, 3 );
		add_filter( 'sten_wc_archive_loop_available_variation', 'etheme_available_variation_gallery', 90, 3 );
		add_filter( 'etheme_output_shortcodes_inline_css', function() { return true; } );
		
		// this add variation gallery filters at loop start and remove it after loop end
//        if ( !$_POST['archiveVariationGallery'] ) {
		add_filter( 'woocommerce_product_loop_start', 'remove_et_variation_gallery_filter' );
		add_filter( 'woocommerce_product_loop_end', 'add_et_variation_gallery_filter' );
//        }
		
		add_filter('woocommerce_get_availability_class', 'etheme_wc_get_availability_class', 20, 2);
		
		if ( isset( $_POST[ 'content' ] ) && ! empty( $_POST[ 'content' ] ) ) {
			$content = wp_unslash($_POST['content']);
			$content = wp_kses_post($content);

			if(et_content_chack_allowed_shortcodes($content, $allowed_elements)){
				$content = do_shortcode( $content ) . '[/' . esc_attr($element) . ']';
			} else {
				$content = '[/' . esc_attr($element) . ']';
			}

		} else {
			$content = '';
		}
		echo do_shortcode( '[' . esc_attr($element) . ' ' . $atts . ' ]' . $content );
		wp_die();
	}
}

function et_content_chack_allowed_shortcodes( $content, $allowed ) {
    if ( ! preg_match_all('/\[(\/?)([a-zA-Z0-9_-]+)([^\]]*)\]/', $content, $m) ) {
        return true;
    }

    foreach ( $m[2] as $tag ) {
        $tag = strtolower($tag);
        if ( $tag === '' ) continue;

        if ( ! in_array($tag, $allowed, true) ) return false;
    }
    return true;
}

function et_normalize_widget_args($args){
	$args = stripslashes($args);
	$args = json_decode( $args, true);

	if (!is_null($args)){
		$args = str_replace('u0022', '"',$args );
	}

	if (isset($args['before_title'])){
		$args['before_title'] = html_entity_decode($args['before_title']);
	}
	if (isset($args['after_title'])){
		$args['after_title'] = html_entity_decode($args['after_title']);
	}
	if (isset($args['before_widget'])){
		$args['before_widget'] = html_entity_decode($args['before_widget']);
	}
	if (isset($args['after_widget'])){
		$args['after_widget'] = html_entity_decode($args['after_widget']);
	}
	return $args;
}

// **********************************************************************//
// ! Ajax holder for shortcodes/VC elements loading
// **********************************************************************//
if ( ! function_exists( 'et_ajax_element_holder' ) ) {
	function et_ajax_element_holder($element = false, $atts = array(), $extra = '', $content = false, $type = 'element', $widget = array()){
		if ( ! $element ) return '';

		if ( $content ) {
			$content = '<span class="hidden et-element-content"><!--[if IE 6] --[et-ajax]--' . $content . '--[!et-ajax]-- ![endif]--></span>';
		}
		
		if ( ( is_array($widget) || is_object($widget) ) && count($widget)){
			$content .= '<span class="hidden et-element-args_widget"><!--[if IE 6] --[et-ajax]--' . esc_js(json_encode($widget, JSON_HEX_QUOT) ). '--[!et-ajax]-- ![endif]--></span>';
		}
		
		$output = '
			<div class="et-load-block lazy-loading et-ajax-element type-'.$type.'" data-type="'.$type.'" data-extra="' . $extra . '" data-element="' . $element . '">
				<!--googleoff: index-->
				<!--noindex-->
				' . etheme_loader(false, 'no-lqip') . '
				<span class="hidden et-element-args"><!--[if IE 6] --[et-ajax]--' . json_encode( $atts ) . '--[!et-ajax]-- ![endif]--></span>
				' . $content . '
				<!--/noindex-->
				<!--googleon: index-->
			</div>
		';
		return $output;
	}
}

add_filter('et_ajax_widgets', 'et_ajax_widgets');
if (! function_exists('et_ajax_widgets')){
	function et_ajax_widgets($ajax){
		if (isset($_GET['et_ajax'])){
			return false;
		}
//		if ( get_query_var('is_mobile', false) && get_theme_mod('sidebar_for_mobile', 'off_canvas') == 'off_canvas' ) {
			//return false;
//		}
		return $ajax;
	}
}

// post ajax
add_action( 'wp_ajax_et_ajax_blog_element', 'et_ajax_blog_element');
add_action( 'wp_ajax_nopriv_et_ajax_blog_element', 'et_ajax_blog_element');
if ( ! function_exists( 'et_ajax_blog_element' ) ) {
	function et_ajax_blog_element(){

		$element = $_POST[ 'element' ];

		$allowed_elements = array(
			'banner',
			'etheme_carousel',
			'etheme_brands',
			'et_category',
			'etheme_categories',
			'etheme_categories_lists',
			'follow',
			'icon_box',
			'instagram',
			'et_looks',
			'etheme_products',
			'team_member',
			'et_the_look',
			'et_offer',
			'title',
			'twitter',
			'quick_view',
			'button',
			'counter',
			'dropcap',
			'mark',
			'blockquote',
			'checklist',
			'countdown',
			'qrcode',
			'tooltip',
			'share',
			'block',
			'et_fancy_button',
			'et_blog',
			'et_blog_timeline',
			'et_blog_list',
			'et_blog_carousel',
			'menu',
			'etheme_brands_list',
			'etheme_post_meta',
			'et_menu_list',
			'et_menu_list_item',
			'et_icons_list',
			'etheme_slider',
			'etheme_slider_item',
			'etheme_scroll_text',
			'etheme_scroll_text_item',
			'portfolio',
			'portfolio_recent'
		);
		
		$allowed_widgets = array(
			'About_Author',
			'Apply_All_Filters',
			'Brands_Filter',
			'Brands',
			'Categories_Filter',
			'Clear_all_filters',
			'Featured_Posts',
			'Instagram',
			'Layered_Nav_Filters',
			'Menu',
			'Posts_Tabs',
			'Price_Filter',
			'Product_Status_Filter',
			'Products',
			'QR_Code',
			'Recent_Comments',
			'Recent_Posts',
			'Search',
			'Socials',
			'Static_Block',
			'Swatches_Filter',
			'Twitter'
		);

		if (
			!in_array($element, $allowed_elements)
			&& ! !in_array($element, $allowed_widgets)
		){
			die();
		}
		
		$atts = '';
		$args = ( isset( $_POST['args'] ) ) ? $_POST['args'] : array() ;
		$args['ajax'] = false;
		$args['ajax_loaded'] = true; // not used yet
		add_filter('etheme_output_shortcodes_inline_css', '__return_true');
		foreach ( $args as $key => $value ) {
			// ! Do it because js change data type
			if ( $value === 'false' ) $value = false;
			
			$atts .= esc_attr($key) . '="' . esc_attr($value) . '" ';
		}
		echo do_shortcode( '[' . esc_attr($element) . ' ' . $atts . ' paged="' . esc_attr($_POST[ 'paged' ]) . '" html_type="true" ]' );
		die();
	}
}