<?php

namespace VIWEC\INC;
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Compatible {

    protected static $instance = null;
    protected $correios_tracking_code;

    protected $yith_tracking_code;

    private function __construct() {
        /*-------------------Claudio Sanches - Correios for WooCommerce-------------------*/
        add_filter( 'viwec_register_replace_shortcode', [ $this, 'woocommerce_correios' ], 10, 3 );
        add_filter( 'viwec_register_replace_shortcode', [ $this, 'woocommerce_yith_tracking_code' ], 10, 3 );
        add_filter( 'woocommerce_api_create_order', array( $this, 'legacy_orders_update' ), 200, 2 );
        add_filter( 'woocommerce_api_edit_order', array( $this, 'legacy_orders_update' ), 200, 2 );
        add_filter( 'viwec_register_email_type', array( $this, 'register_email_type' ) );
        add_filter( 'viwec_live_edit_shortcodes', array( $this, 'register_render_preview_shortcode' ), 20 );
        add_filter( 'viwec_register_preview_shortcode', array( $this, 'register_render_preview_shortcode' ), 20 );
        /*^-------------------Claudio Sanches - Correios for WooCommerce-------------------*/

    }

    public static function init() {
        if ( null == self::$instance ) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /*begin-------------------Claudio Sanches - Correios for WooCommerce-------------------*/

    public function register_email_type( $emails ) {
        $emails['correios_tracking'] = [
            'name' => __( 'Correios Tracking Code', 'xstore-core' ),
        ];

        $emails['yith_tracking'] = [
            'name' => __( 'YITH Tracking Code', 'xstore-core' ),
        ];

        return $emails;
    }

    public function register_render_preview_shortcode( $sc ) {

        $sc['correios_tracking'] = array(
            '{correios_tracking_code}' => 'WC_Correios_Tracking_Code',
        );

        $sc['yith_tracking'] = array(
            '{yith_tracking_code}' => 'TRACK#1234567890',
        );

        return $sc;
    }

    public function legacy_orders_update( $order_id, $data ) {
        if ( isset( $data['correios_tracking_code'] ) ) {
            $this->correios_tracking_code = $data['correios_tracking_code'];
        }

        if ( isset( $data['yith_tracking_code'] ) ) {
            $this->yith_tracking_code = $data['yith_tracking_code'];
        }
    }

    public function woocommerce_correios( $shortcodes, $object, $args ) {
        if ( empty( $args ) ) {
            return $shortcodes;
        }

        if ( isset( $args['email'] ) && is_a( $args['email'], 'WC_Correios_Tracking_Email' ) ) {

            $tracking_code = ! empty( $_POST['tracking_code'] ) ? sanitize_text_field( $_POST['tracking_code'] ) : $this->correios_tracking_code;

            if ( empty( $tracking_code ) ) {
                $tracking_codes = wc_correios_get_tracking_codes( $object );
            } else {
                $tracking_codes = array( $tracking_code );
            }

//			$tracking_codes = $args['email']->get_tracking_codes( $tracking_codes );

            $_tracking_code = implode( $tracking_codes );

            $shortcodes['correios_tracking_code'] = [ '{correios_tracking_code}' => $_tracking_code ];
        }

        return $shortcodes;
    }
    /*end-------------------Claudio Sanches - Correios for WooCommerce-------------------*/

    public function woocommerce_yith_tracking_code( $shortcodes, $object, $args )
    {
        if (empty($args)) {
            return $shortcodes;
        }

        if(class_exists('Yith_WooCommerce_Order_Tracking')){
            $tracking_code = ! empty( $_POST['ywot_tracking_code'] ) ? sanitize_text_field( $_POST['ywot_tracking_code'] ) : $this->yith_tracking_code;

            if ( empty( $tracking_code ) ) {
                $tracking_code = $object->get_meta( 'ywot_tracking_code' );
            }

            $shortcodes['yith_tracking_code'] = [ '{yith_tracking_code}' => $tracking_code ];
        }

        return $shortcodes;
    }
}