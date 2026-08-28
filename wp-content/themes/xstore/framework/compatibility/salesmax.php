<?php

if ( !$salesmax_installed ) {
    $salemax_data = array(
        'name' => 'SalesMax',
        'slug' => 'salesmax',
//        'image_slug' => 'SalesMax.jpg',
//        'image_advanced_slug' => 'SalesMax-boosters.jpg',
        'image_slug' => 'SalesMax-boosters-promo.jpg',
        'image_advanced_slug' => 'SalesMax-boosters-promo.jpg',
        'source_menu_url' => 'https://www.8theme.com/salesmax/?utm_source=admin_panel&utm_medium=links&utm_id=xstore',
        'source_url' => 'https://www.8theme.com/salesmax/?utm_source=admin_panel&utm_medium=banners&utm_id=xstore'
    );
//    add_filter('etheme_plugins_list', function ($plugins) use ($salemax_data) {
//
//        $plugin_to_attach = 'contact-form-7';
//        if ( !array_key_exists($plugin_to_attach, $plugins)) return $plugins;
//        $salesmax_banner = [];
//        $salesmax_banner['name'] = $salemax_data['name'];
//        $salesmax_banner['slug'] = $salemax_data['slug'];
//        $salesmax_banner['source'] = $salemax_data['source_url'];
//        $salesmax_banner['source_type'] = 'banner';
//        $salesmax_banner['details_url'] = $salemax_data['source_url'];
//        $salesmax_banner['required'] = false;
////        $salesmax_banner['danger_label'] = esc_html__('Not included', 'xstore');
//        $salesmax_banner['version'] = null;
//        $salesmax_banner['version_custom_text'] = esc_html__('Boost your revenue and sales today', 'xstore');
//        $salesmax_banner['image_url'] = apply_filters('etheme_protocol_url', ETHEME_BASE_URL . 'import/xstore-demos/1/plugins/images/'.$salemax_data['image_slug']);
//        $salesmax_banner['premium'] = true;
//        $salesmax_banner['premium_label_custom_text'] = esc_html__('Premium Extension (PRO)', 'xstore');
//        $salesmax_banner['button_text'] = esc_html__( 'Explore SalesMax', 'xstore' );
//        $salesmax_banner['etheme_filters'] = ['premium'];
//
//        $woocommerce_position = array_search($plugin_to_attach, array_keys($plugins));
//        if ($woocommerce_position) {
//            $plugins = array_slice($plugins, 0, $woocommerce_position, true) +
//                array('salesmax' => $salesmax_banner) +
//                array_slice($plugins, $woocommerce_position, count($plugins) - $woocommerce_position, true);
//        } else {
//            $plugins = array_merge(array(
//                'salesmax' => $salesmax_banner
//            ), $plugins);
//        }
//        return $plugins;
//    });

    add_filter('etheme_sales_booster_list', function ($boosters_list) use ($salemax_data) {
        $salesmax_args = array(
            'salesmax' => array(
                'title' => $salemax_data['name'],
                'description' => __('By enabling this option, your customers can easily sign up for a mailing list of out-of-stock or unavailable items they are interested in. Don\'t miss out on the opportunity to offer a personalized shopping experience that keeps your customers coming back for more. Enable the waitlist feature today!', 'xstore'),
                'details_url' => $salemax_data['source_url'],
                'preview_url' => $salemax_data['source_url'],
                'image_url' => apply_filters('etheme_protocol_url', ETHEME_BASE_URL . 'import/xstore-demos/1/plugins/images/'.$salemax_data['image_advanced_slug']),
                'filters' => array(
                    'site', 'account', 'single-product', 'off-canvas', 'cart', 'checkout'
                ),
                'source_type' => 'banner',
                'hide_info' => true,
                'theme_mod' => true,
                'theme_mod_url' => $salemax_data['source_url'],
            )
        );
        return array_merge($boosters_list, $salesmax_args);
    });

//    add_action('top_bar_menu_after_xstore_sales_booster', function ($wp_admin_bar, $parent_id) use ($salemax_data) {
//        $wp_admin_bar->add_node(array(
//            'parent' => $parent_id,
//            'id' => 'et-top-bar-xstore-sales-booster-'.$salemax_data['slug'],
//            'title' => '<span class="dashicons dashicons-before dashicons-star-filled" style="vertical-align: 6px;margin-left: -4px;line-height: 1;"></span>' . sprintf(esc_html__('Get %s', 'xstore'), $salemax_data['name']),
//            'href' => $salemax_data['source_menu_url'],
//            'meta' => array(
//                'target' => '_blank'
//            )
//        ));
//    }, 10, 2);
//    add_action('admin_menu_pages_after_sales_booster', function ($parent_id) use ($salemax_data) {
//        add_submenu_page(
//            $parent_id,
//            esc_html__( 'Sales Booster', 'xstore' ),
//            '<span>'.'<span class="dashicons dashicons-before dashicons-star-filled" style="vertical-align: 2px;margin-left: -4px;font-size: 1em;height: 1em;transform: scale(0.7);"></span>'. sprintf(esc_html__('Get %s', 'xstore'), $salemax_data['name']).'</span>',
//            'manage_woocommerce',
//            $salemax_data['source_menu_url'],
//            ''
//        );
//    }, 10, 1);

    add_action( 'admin_enqueue_scripts', function () {
        wp_add_inline_style('etheme_admin_css',
            '#adminmenu #toplevel_page_et-panel-sales-booster a[href*="8theme.com/salesmax"] {
            background-color: #FF6600;
            border-radius: 3px;
            color: #fff;
            display: block;
            font-weight: 600;
            text-align: center;
            transition: all .3s;
            margin: 3px 7px 0;
            border: 2px solid transparent;
            padding: 3px 5px;
        }
        
        #adminmenu #toplevel_page_et-panel-sales-booster a[href*="8theme.com/salesmax"]:hover {
            background: #f77f2f;
            box-shadow: none;
        }
        
        .etheme-panel-page-sales-booster .xstore-panel-grid-item[data-slug="salesmax"] .xstore-panel-grid-item-content {border: none}
        .etheme-panel-page-sales-booster .xstore-panel-grid-item[data-slug="salesmax"] img {object-fit: contain;
    aspect-ratio: 306 / 231;
    object-position: center center;
    border: none;
    border-radius: 5px;}
    
    .etheme-panel-page-plugins .xstore-panel-grid-item[data-slug="salesmax"] img {
        aspect-ratio: 305 / 234;
        object-fit: cover;
        }');
    }, 200);
}