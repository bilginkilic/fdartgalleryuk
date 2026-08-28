<?php
/**
 * Stretch Container feature for Elementor containers
 *
 * @package    Container-stretch.php
 * @since      5.5.4
 * @author     Stas
 * @link       http://xstore.8theme.com
 * @license    Themeforest Split Licence
 */


namespace ETC\App\Controllers\Elementor\Modules;

use Elementor\Plugin;
use Elementor\Widget_Base;
use Elementor\Controls_Manager;


class Container_Stretch {
	
	function __construct() {

		// modify column setting to add stretch container option
		add_action( 'elementor/element/container/section_effects/before_section_start', array( $this, 'register_controls' ), 10, 2 );
	}
	
	/**
	 * After column_layout callback
	 *
	 * @param  object $element
	 * @param  array $args
	 * @return void
	 */
	public function register_controls( $element, $args ) {
        $element->start_controls_section(
            'et_extra_layout',
            array(
                'label' => sprintf(__( '%s Layout', 'xstore-core' ), apply_filters('etheme_theme_label', 'XSTORE')),
                'tab'   => Controls_Manager::TAB_ADVANCED,
            )
        );

        $element->add_control(
            'etheme_section_stretch',
            array(
                'label'        => esc_html__( 'Stretch Container', 'xstore-core' ),
                'description'  => esc_html__( 'Enable this option to expand the container using CSS.', 'xstore-core' ),
                'type'         => Controls_Manager::SELECT,
                'default'      => '',
                'options' => array(
                    ''                => esc_html__( 'Disabled', 'xstore-core' ),
                    'stretch'         => esc_html__( 'Stretch Container', 'xstore-core' ),
                    'stretch-content' => esc_html__( 'Stretch Container and Content', 'xstore-core' ),
                ),
                'render_type'  => 'template',
                'prefix_class' => 'et-section-',
            )
        );

        $element->end_controls_section();
	}
}