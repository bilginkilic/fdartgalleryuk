<?php
namespace ETC\App\Controllers\Elementor\Theme_Builder\Search_Results;

/**
 * Seachable pages widget.
 *
 * @since      5.6.1
 * @package    ETC
 * @subpackage ETC/Controllers/Elementor
 */
class Pages extends \ETC\App\Controllers\Elementor\General\Posts {

    /**
     * Get widget name.
     *
     * @since 5.4
     * @access public
     *
     * @return string Widget name.
     */
    public function get_name() {
        return 'search-results-etheme_pages';
    }

    /**
     * Get widget title.
     *
     * @since 5.4
     * @access public
     *
     * @return string Widget title.
     */
    public function get_title() {
        return __( 'Searched Pages', 'xstore-core' );
    }

    /**
     * Get widget icon.
     *
     * @since 5.4
     * @access public
     *
     * @return string Widget icon.
     */
    public function get_icon() {
        return parent::get_icon() . ' et-elementor-search-results-widget-icon-only';
    }

    /**
     * Get widget categories.
     *
     * @return array Widget categories.
     * @since 5.4
     * @access public
     *
     */
    public function get_categories()
    {
        return ['theme-elements-archive'];
    }

    /**
     * Register widget controls.
     *
     * @since 5.4
     * @access protected
     */
    protected function register_controls() {
        parent::register_controls();

        $this->update_control('navigation', [
            'default' => 'pagination'
        ]);

        $this->update_control('section_query', [
            'type' => \Elementor\Controls_Manager::HIDDEN,
        ]);

        $this->update_control('query_type', [
            'type' => \Elementor\Controls_Manager::HIDDEN,
            'default'	=> 'search_query'
        ]);

        // modify the settings
        foreach (array('image', 'meta', 'excerpt', 'button', 'categories', 'tags') as $content_item ) {
            if ( in_array($content_item, array('image', 'categories', 'tags')) ) {
                $this->update_control(
                    'post_' . $content_item,
                    [
                        'type' => \Elementor\Controls_Manager::HIDDEN,
                        'default' => ''
                    ]
                );
            }
            else {
                $this->update_control(
                    'post_' . $content_item,
                    [
                        'default' => ''
                    ]
                );
            }
        }

        $this->update_control(
            'post_title_divider',
            [
                'type' => \Elementor\Controls_Manager::HIDDEN,
            ]
        );

        $this->update_control(
            'post_date_label',
            [
                'type' => \Elementor\Controls_Manager::HIDDEN,
                'default' => 'none'
            ]
        );

        $this->update_control(
            'post_meta_data',
            [
                'default' => [ 'author', 'date' ],
            ]
        );
    }

    protected function register_post_content_style_controls() {}

    protected function register_post_date_label_style_controls() {}

    protected function register_image_shape_divider_style_controls() {}

    // disable 'None' navigation option for Archive posts
    public function get_navigation_options_list() {
        $options = parent::get_navigation_options_list();
        if (array_key_exists( 'none', $options) )
            unset($options['none']);

        return $options;
    }

    public function get_post_type_details() {
        return [
            'post_type' => 'page',
            'post_type_name' => esc_html__('Page', 'xstore-core'),
            'post_type_names' => esc_html__('Pages', 'xstore-core'),
            'post_terms' => array(
                'category' => 'category',
                'tag' => 'post_tag'
            ),
        ];
    }

    public static function get_post_meta_data_elements() {
        $meta_data_items = parent::get_post_meta_data_elements();
        if ( array_key_exists('views', $meta_data_items) )
            unset($meta_data_items['views']);
        return $meta_data_items;
    }

}
