<?php
/**
 * Description
 *
 * @package    wp-rocket.php
 * @since      9.5.0
 * @author     Stas
 * @link       http://xstore.8theme.com
 * @license    Themeforest Split Licence
 */

if (!function_exists('etheme_remove_elementor_css_from_exclude')) {
    /**
     * Remove from CSS exclude Elementor post file..
     *
     * @param array $excluded_files Excluded files.
     *
     * @return array
     */
    function etheme_remove_elementor_css_from_exclude($excluded_files)
    {
        $upload = wp_get_upload_dir();
        $basepath = wp_parse_url($upload['baseurl'], PHP_URL_PATH);

        if (empty($basepath)) {
            return $excluded_files;
        }

        $key = array_search($basepath . '/elementor/css/(.*).css', $excluded_files, true);

        if (false !== $key) {
            unset($excluded_files[$key]);
        }

        return $excluded_files;
    }

    add_action('rocket_exclude_css', 'etheme_remove_elementor_css_from_exclude');
}

if (!function_exists('etheme_delay_js_exclusions')) {
    /**
     * Exclusions JS files.
     *
     * @param array $exclude_delay_js Exclude files.
     * @return array
     */
    function etheme_delay_js_exclusions($exclude_delay_js)
    {
        if (!etheme_get_option('rocket_delay_js_exclusions', false)) {
            return $exclude_delay_js;
        }

        return wp_parse_args(
            $exclude_delay_js,
            array(
                '/jquery-?[0-9.](.*)(.min|.slim|.slim.min)?.js',
                'cart-fragments',
                'etheme-scripts',
                'js/general-all.min.js',
                'st-woo-swatches/public/js/*.js',
                'js/modules/woocommerce.min.js',
                'js/mini-cart.min.js',
                'automaticProductSlider',
                'js.cookie',
                'jquery.cookie',
                'cookie.min',
                'flying-pages',
                'imagesLoaded.js',
                'swiper',
                'swiperInit',
                'lazyload.js',
            )
        );
    }

    add_filter('rocket_delay_js_exclusions', 'etheme_delay_js_exclusions');
}

if (!function_exists('etheme_cache_reject_cookies')) {
    /**
     * Exclusions JS files.
     *
     * @param array $exclude_delay_js Exclude files.
     * @return array
     */
    function etheme_cache_reject_cookies($cookies)
    {
        $cookies[] = 'xstore_wishlist_ids';
        $cookies[] = 'xstore_wishlist_u';
        $cookies[] = 'xstore_compare_ids';
        $cookies[] = 'xstore_compare_u';
        return $cookies;
    }

    add_filter('rocket_cache_reject_cookies', 'etheme_cache_reject_cookies');
}

if ( !function_exists('etheme_exclude_inline_js') ) {
    function etheme_exclude_inline_js($inline_js)
    {
        $inline_js[] = 'etheme-ajaxify-loading';
        $inline_js[] = 'etheme-elementor-lazyBg';
        return $inline_js;
    }

    add_filter('rocket_excluded_inline_js_content', 'etheme_exclude_inline_js' );
}

if (!function_exists('etheme_rejected_uri_exclusions')) {
    /**
     * Add xstore uris to the wp_rocket rejected uri
     *
     * @param array $uris List of rejected uri.
     */
    function etheme_rejected_uri_exclusions($uris)
    {
        $urls = array();

        $theme_features = [
            'wishlist',
            'compare',
            'waitlist',
        ];
        foreach ($theme_features as $theme_feature) {
            if (get_theme_mod('xstore_'.$theme_feature, false) && get_theme_mod('xstore_'.$theme_feature.'_page', '')) {
                $urls[] = get_permalink(absint( get_theme_mod('xstore_'.$theme_feature.'_page', '') ));
            }
        }

        if ($urls) {
            foreach ($urls as $url) {
                $uri = wp_parse_url($url, PHP_URL_PATH);

                if ($uri) {
                    $uris[] = $uri;
                }
            }
        }

        return $uris;
    }

    add_filter('rocket_cache_reject_uri', 'etheme_rejected_uri_exclusions');
}
