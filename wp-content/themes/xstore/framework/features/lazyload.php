<?php
/**
 * Description
 *
 * @package    lazyload.php
 * @since      8.0.0
 * @author     Stas
 * @link       http://xstore.8theme.com
 * @license    Themeforest Split Licence
 */

defined( 'ABSPATH' ) || exit( 'Direct script access denied.' );

if ( ! class_exists( 'XStore_LazyLoad' ) ) :
	class XStore_LazyLoad {
		
		static function init() {
			$l_type = get_theme_mod( 'images_loading_type_et-desktop', 'lazy' );
			if ( $l_type == 'default' ) {
				return;
			}
			add_action( 'wp_head', array( __CLASS__, 'setup' ), 99 );
			
			add_filter(
				'wp_lazy_loading_enabled',
				function( $default, $tag_name = null  ) {
					if ( 'img' === $tag_name ) {
						return false;
					}
					return $default;
				},
				10,
				2
			);
		}
		static function setup() {
			add_filter( 'the_content', array( __CLASS__, 'add_image_placeholders' ), 9999 );
			add_filter( 'post_thumbnail_html', array( __CLASS__, 'add_image_placeholders' ), 11 );
			add_filter( 'woocommerce_single_product_image_html', array( __CLASS__, 'add_image_placeholders' ), 9999 );
			add_filter( 'woocommerce_single_product_image_thumbnail_html', array( __CLASS__, 'add_image_placeholders' ), 9999 );
		}

		static function add_image_placeholders( $content ) {
			if ( is_feed() || is_preview() ) return $content;
			if ( defined('DOING_AJAX') && DOING_AJAX ) return $content;
			if ( strpos( $content, '<img' ) === false ) return $content;

			static $dark_version = null, $l_type = null, $base_lazy;
			if ( $dark_version === null ) {
				$dark_version = (int) get_theme_mod('dark_styles', 0);
				$l_type       = get_query_var('et_img-loading-type', 'lazy'); // 'lazy' | 'lqip'
				$base_lazy    = ETHEME_BASE_URI . 'images/lazy' . ( $dark_version ? '-dark' : '' ) . '.png';
			}

			$content = preg_replace_callback('/<img\b[^>]*>/is', function($m) use ($dark_version) {
				$img_html = $m[0];

				if ( strpos($img_html,'data-src')!==false
				|| strpos($img_html,'data-original')!==false
				|| preg_match("/src=['\"]data:image/is",$img_html)
				|| strpos($img_html,'rev-slidebg')!==false
				|| strpos($img_html,'rs-lazyload')!==false
				|| strpos($img_html,'avatar')!==false
				|| strpos($img_html,'main-hover-slider-img')!==false ) {
					return $img_html;
				}

				if ( !preg_match('/\bwidth=["\'](\d+)["\']/i',$img_html,$mw)
				|| !preg_match('/\bheight=["\'](\d+)["\']/i',$img_html,$mh) ) {
					return $img_html;
				}
				$w = (int)$mw[1]; $h = (int)$mh[1];
				if ( $w !== $h && $w < 100 ) return $img_html;

				$lazy_image = function_exists('etheme_placeholder_image')
					? etheme_placeholder_image("{$w}x{$h}")
					: ETHEME_BASE_URI . 'images/lazy' . ( $dark_version ? '-dark' : '' ) . '.png';

				$out = preg_replace('/<img(.*?)\bsrc=/is', '<img$1src="' . esc_url($lazy_image) . '" data-src=', $img_html, 1);

				if ( preg_match('/\bsrcset=/i', $out) ) {
					$out = preg_replace('/\bsrcset=/i', 'data-srcset=', $out, 1);
				}

				$l_type     = get_query_var('et_img-loading-type', 'lazy');
				$lazy_class = 'lazyload lazyload-' . ($l_type === 'lazy' ? 'simple' : 'lqip');
				if ( preg_match('/\bclass=["\']/i', $out) ) {
					$out = preg_replace('/class=(["\'])(.*?)\1/is', 'class=$1'.$lazy_class.' $2$1', $out, 1);
				} else {
					$out = preg_replace('/^<img\b/is', '<img class="'.$lazy_class.'"', $out, 1);
				}

				return $out;
			}, $content);

			return $content;
		}

		private static function get_attr($tag, $name){
			return preg_match('/\b'.preg_quote($name,'/').'=(["\'])(.*?)\1/i', $tag, $m) ? $m[2] : null;
		}
		private static function set_attr($tag, $name, $val){
			if (preg_match('/\b'.preg_quote($name,'/').'=(["\'])(.*?)\1/i', $tag)) {
				return preg_replace('/\b'.preg_quote($name,'/').'=(["\'])(.*?)\1/i', $name.'="$2"', $name.'="'.esc_attr($val).'"', 1);
			}
			return preg_replace('/^<img\b/', '<img '.$name.'="'.esc_attr($val).'"', $tag, 1);
		}
		private static function ensure_attr($tag, $name, $val){
			return self::get_attr($tag, $name) ? $tag : self::set_attr($tag, $name, $val);
		}
		private static function remove_attr($tag, $name){
			return preg_replace('/\s+'.preg_quote($name,'/').'=(["\'])(.*?)\1/i', '', $tag);
		}
		
	}
	
	if ( ! (is_admin() || get_query_var('et_is_customize_preview', false) || ( defined('DOING_AJAX') && DOING_AJAX ) ) ) {
		add_action( 'init', array( 'XStore_LazyLoad', 'init' ) );
	}
endif;
