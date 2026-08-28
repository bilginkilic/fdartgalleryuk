<?php

namespace ETC\App\Controllers\Elementor\General;


/**
 * FlipBox widget.
 *
 * @since      4.0.6
 * @package    ETC
 * @subpackage ETC/Controllers/Elementor/General
 */
class FlipBox extends \Elementor\Widget_Base
{
    /**
     * Get widget name.
     *
     * @return string Widget name.
     * @since 4.0.6
     * @access public
     *
     */
    public function get_name()
    {
        return 'etheme_flipbox';
    }

    /**
     * Get widget title.
     *
     * @return string Widget title.
     * @since 4.0.6
     * @access public
     *
     */
    public function get_title()
    {
        return __('FlipBox', 'xstore-core');
    }

    /**
     * Get widget icon.
     *
     * @return string Widget icon.
     * @since 4.0.6
     * @access public
     *
     */
    public function get_icon()
    {
        return 'eight_theme-elementor-icon et-elementor-flipbox';
    }

    /**
     * Get widget keywords.
     *
     * @return array Widget keywords.
     * @since 4.0.6
     * @access public
     *
     */
    public function get_keywords()
    {
        return ['flip', '3d', 'rotate', 'banner', 'box', 'icon', 'image', 'effect'];
    }

    /**
     * Get widget categories.
     *
     * @return array Widget categories.
     * @since 4.0.6
     * @access public
     *
     */
    public function get_categories()
    {
        return ['eight_theme_general'];
    }

    /**
     * Get widget dependency.
     *
     * @return array Widget dependency.
     * @since 4.1
     * @access public
     *
     */
    public function get_style_depends()
    {
        return apply_filters('etheme_elementor_widget_style_depends', ['etheme-elementor-flipbox']);
    }

    /**
     * Help link.
     *
     * @return string
     * @since 4.1.5
     *
     */
    public function get_custom_help_url()
    {
        return etheme_documentation_url('122-elementor-live-copy-option', false);
    }

    /**
     * Register widget controls.
     *
     * @since 4.0.6
     * @access protected
     */
    protected function register_controls()
    {
        $sides = array(
            'a' => array(
                'label' => esc_html__('Front Side', 'xstore-core'),
                'default_title' => esc_html__('Front side heading', 'xstore-core'),
                'default_color' => '#f7f7f7',
            ),
            'b' => array(
                'label' => esc_html__('Back Side', 'xstore-core'),
                'default_title' => esc_html__('Back side heading', 'xstore-core'),
                'default_color' => '#1a1a1a',
            ),
        );

        foreach ($sides as $key => $data) {
            $this->register_side_controls($key, $data);
        }

        $this->register_box_controls();

        foreach ($sides as $style_key => $style_data) {
            $this->register_side_style_controls($style_key, $style_data);
        }
    }

    protected function register_side_controls($key, $data)
    {
        $this->start_controls_section("section_side_$key", [
            'label' => $data['label']
        ]);

        $this->start_controls_tabs("side_{$key}_tabs");

        // Content Tab
        $this->start_controls_tab("side_{$key}_tab", ['label' => __('Content', 'xstore-core')]);

        $this->add_control("graphic_element_$key", [
            'label' => __('Graphic Element', 'xstore-core'),
            'type' => \Elementor\Controls_Manager::CHOOSE,
            'options' => [
                'none' => ['title' => __('None', 'xstore-core'), 'icon' => 'eicon-ban'],
                'image' => ['title' => __('Image', 'xstore-core'), 'icon' => 'eicon-image'],
                'icon' => ['title' => __('Icon', 'xstore-core'), 'icon' => 'eicon-star'],
            ],
            'default' => 'icon',
        ]);

        $this->add_control("image_$key", [
            'label' => __('Choose Image', 'xstore-core'),
            'type' => \Elementor\Controls_Manager::MEDIA,
            'default' => ['url' => \Elementor\Utils::get_placeholder_image_src()],
            'dynamic' => ['active' => true],
            'condition' => ["graphic_element_$key" => 'image'],
        ]);

        $this->add_group_control(
            \Elementor\Group_Control_Image_Size::get_type(),
            [
                'name' => "image_$key", // Actually its `image_size`
                'default' => 'thumbnail',
                'condition' => ["graphic_element_$key" => 'image'],
            ]
        );

        $this->add_control("selected_icon_$key", [
            'label' => __('Icon', 'xstore-core'),
            'type' => \Elementor\Controls_Manager::ICONS,
            'fa4compatibility' => "icon_$key",
            'default' => ['value' => 'fas fa-star', 'library' => 'fa-solid'],
            'condition' => ["graphic_element_$key" => 'icon'],
        ]);

        $this->add_control("title_$key", [
            'label' => __('Content', 'xstore-core'),
            'type' => \Elementor\Controls_Manager::TEXT,
            'default' => $data['default_title'],
            'placeholder' => __('Enter your title', 'xstore-core'),
            'dynamic' => ['active' => true],
            'label_block' => true,
            'separator' => 'before',
        ]);

        $this->add_control("description_$key", [
            'label' => __('Description', 'xstore-core'),
            'type' => \Elementor\Controls_Manager::TEXTAREA,
            'default' => __('Lorem ipsum dolor sit amet consectetur adipiscing elit dolor', 'xstore-core'),
            'placeholder' => __('Enter your description', 'xstore-core'),
            'separator' => 'none',
            'dynamic' => ['active' => true],
            'rows' => 10,
            'show_label' => false,
        ]);

        $this->add_control("button_{$key}_text", [
            'label' => __('Button Text', 'xstore-core'),
            'default' => __('Button', 'xstore-core'),
            'type' => \Elementor\Controls_Manager::TEXT,
        ]);

        $this->add_control("link_$key", [
            'label' => __('Link', 'xstore-core'),
            'type' => \Elementor\Controls_Manager::URL,
            'placeholder' => __('https://your-link.com', 'xstore-core'),
            'default' => ['url' => '#'],
            'dynamic' => ['active' => true],
        ]);

        $this->end_controls_tab();

        // Style Tab
        $this->start_controls_tab("side_{$key}_style_tab", ['label' => __('Style', 'xstore-core')]);

        $this->add_group_control(
            \Elementor\Group_Control_Background::get_type(),
            [
                'name' => "side_{$key}_background",
                'types' => ['classic', 'gradient'],
                'selector' => "{{WRAPPER}} .etheme-flipbox-side_$key",
                'fields_options' => [
                    'background' => ['default' => 'classic'],
                    'color' => ['default' => $data['default_color']],
                ],
            ]
        );

        $this->add_control("side_{$key}_overlay", [
            'label' => __('Overlay', 'xstore-core'),
            'type' => \Elementor\Controls_Manager::COLOR,
            'default' => '',
            'selectors' => [
                "{{WRAPPER}} .etheme-flipbox-side_$key:before" => 'content: "";background-color: {{VALUE}};',
            ],
            'condition' => ["side_{$key}_background_image[id]!" => ''],
        ]);

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->end_controls_section();
    }

    protected function register_box_controls()
    {
        $this->start_controls_section(
            'section_box_settings',
            [
                'label' => __('Settings', 'xstore-core'),
            ]
        );

        $this->add_responsive_control(
            'box_height',
            [
                'label' => __('Height', 'xstore-core'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'range' => [
                    'px' => [
                        'min' => 50,
                        'max' => 500,
                    ],
                    'vh' => [
                        'min' => 3,
                        'max' => 100,
                    ],
                    '%' => [
                        'min' => 1,
                        'max' => 100,
                    ],
                ],
                'size_units' => ['px', 'vh', '%'],
                'selectors' => [
                    '{{WRAPPER}} .etheme-flipbox-wrapper' => 'height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'box_border_radius',
            [
                'label' => __('Border Radius', 'xstore-core'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', '%'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 200,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 5
                ],
                'selectors' => [
                    '{{WRAPPER}} .etheme-flipbox-wrapper' => 'border-radius: {{SIZE}}{{UNIT}}',
                ],
            ]
        );

        $flip_effects = [
            'flip' => 'Flip',
            'flip-2' => 'Flip Bounced',
            'slide' => 'Slide',
            'slide-2' => 'Slide 2',
            'overlay' => 'Overlay',
            'zoom-in' => 'Zoom In',
            'zoom-in-2' => 'Zoom In 2',
            'zoom-out' => 'Zoom out',
            'zoom-out-2' => 'Zoom out 2',
            'fade' => 'Fade',
            'random' => 'Random'
        ];

        $this->add_control(
            'flip_effect',
            [
                'label' => __('Flip Effect', 'xstore-core'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'flip',
                'options' => $flip_effects,
            ]
        );

        $flip_directions = [
            'left' => __('Left', 'xstore-core'),
            'right' => __('Right', 'xstore-core'),
            'up' => __('Up', 'xstore-core'),
            'down' => __('Down', 'xstore-core'),
            'random' => __('Random', 'xstore-core')
        ];

        $this->add_control(
            'flip_direction',
            [
                'label' => __('Flip Direction', 'xstore-core'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'right',
                'options' => $flip_directions,
                'condition' => [
                    'flip_effect' => [
                        'flip',
                        'flip-2',
                        'slide',
                        'slide-2',
                        'overlay',
                        'random',
                    ],
                ],
            ]
        );

        $this->add_control(
            'flip_3d',
            [
                'label' => __('3D Depth', 'xstore-core'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
                'condition' => [
                    'flip_effect' => ['flip', 'flip-2', 'random'],
                ],
            ]
        );

        $this->add_control(
            'transition_duration',
            [
                'label' => __('Transition Duration', 'xstore-core'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'range' => [
                    'px' => [
                        'min' => 0.1,
                        'max' => 2,
                        'step' => 0.1
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .etheme-flipbox-wrapper' => '--transition-duration: {{SIZE}}s;',
                ],
            ]
        );

        $this->add_control(
            'transition_timing_function',
            [
                'label' => __('Transition Timing Function', 'xstore-core'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => '',
                'options' => [
                    'linear' => 'linear',
                    'ease' => 'ease',
                    'ease-in' => 'ease-in',
                    'ease-out' => 'ease-out',
                    'ease-in-out' => 'ease-in-out',
                    '' => esc_html__('Default', 'xstore-core'),
                ],
                'selectors' => [
                    '{{WRAPPER}} .etheme-flipbox-wrapper' => '--transition-timing-fn: {{VALUE}};',
                ],
                'condition' => [
                    'flip_effect!' => 'flip-2'
                ]
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'box_border',
                'label' => esc_html__('Border', 'xstore-core'),
                'separator' => 'before',
                'selector' => '{{WRAPPER}} .etheme-flipbox-wrapper',
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'box_shadow',
                'selector' => '{{WRAPPER}} .etheme-flipbox-wrapper',
            ]
        );

        $this->end_controls_section();
    }

    protected function register_side_style_controls($key, $data)
    {
        $this->start_controls_section(
            "section_style_side_{$key}",
            [
                'label' => $data['label'],
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            "side_{$key}_alignment",
            [
                'label' => __('Horizontal Align', 'xstore-core'),
                'type' => \Elementor\Controls_Manager::CHOOSE,
                'options' => [
                    'left' => [
                        'title' => __('Left', 'xstore-core'),
                        'icon' => 'eicon-h-align-left',
                    ],
                    'center' => [
                        'title' => __('Center', 'xstore-core'),
                        'icon' => 'eicon-h-align-center',
                    ],
                    'right' => [
                        'title' => __('Right', 'xstore-core'),
                        'icon' => 'eicon-h-align-right',
                    ],
                ],
                'selectors_dictionary' => [
                    'left' => 'start',
                    'right' => 'end',
                ],
                'selectors' => [
                    "{{WRAPPER}} .etheme-flipbox-side_{$key}" => 'text-align: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            "side_{$key}_v_align",
            [
                'label' => __('Vertical Align', 'xstore-core'),
                'type' => \Elementor\Controls_Manager::CHOOSE,
                'options' => [
                    'top' => [
                        'title' => __('Top', 'xstore-core'),
                        'icon' => 'eicon-v-align-top',
                    ],
                    'middle' => [
                        'title' => __('Middle', 'xstore-core'),
                        'icon' => 'eicon-v-align-middle',
                    ],
                    'bottom' => [
                        'title' => __('Bottom', 'xstore-core'),
                        'icon' => 'eicon-v-align-bottom',
                    ],
                ],
                'default' => 'center',
                'selectors_dictionary' => [
                    'top' => 'flex-start',
                    'middle' => 'center',
                    'bottom' => 'flex-end'
                ],
                'selectors' => [
                    "{{WRAPPER}} .etheme-flipbox-side_{$key}" => 'align-items: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            "side_{$key}_padding",
            [
                'label' => __('Padding', 'xstore-core'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    "{{WRAPPER}} .etheme-flipbox-side_{$key}" => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            "heading_image_{$key}_style",
            [
                'type' => \Elementor\Controls_Manager::HEADING,
                'label' => __('Image', 'xstore-core'),
                'condition' => [
                    "graphic_element_{$key}" => 'image',
                ],
                'separator' => 'before',
            ]
        );

        $this->add_control(
            "image_{$key}_spacing",
            [
                'label' => __('Spacing', 'xstore-core'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 100,
                    ],
                ],
                'selectors' => [
                    "{{WRAPPER}} .etheme-flipbox-image-{$key}" => 'margin-bottom: {{SIZE}}{{UNIT}};',
                ],
                'condition' => [
                    "graphic_element_{$key}" => 'image',
                ],
            ]
        );

        $this->add_control(
            "image_{$key}_width",
            [
                'label' => __('Width', 'xstore-core') . ' (%)',
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['%'],
                'default' => [
                    'unit' => '%',
                ],
                'range' => [
                    '%' => [
                        'min' => 5,
                        'max' => 100,
                    ],
                ],
                'selectors' => [
                    "{{WRAPPER}} .etheme-flipbox-image-{$key} img" => 'width: {{SIZE}}{{UNIT}}',
                ],
                'condition' => [
                    "graphic_element_{$key}" => 'image',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => "image_{$key}_border",
                'selector' => "{{WRAPPER}} .etheme-flipbox-image-{$key} img",
                'condition' => [
                    "graphic_element_{$key}" => 'image',
                ],
                'separator' => 'before',
            ]
        );

        $this->add_control(
            "image_{$key}_border_radius",
            [
                'label' => __('Border Radius', 'xstore-core'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    "{{WRAPPER}} .etheme-flipbox-image-{$key} img" => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
                'condition' => [
                    "graphic_element_{$key}" => 'image',
                ],
            ]
        );

        $this->add_control(
            "heading_icon_{$key}_style",
            [
                'type' => \Elementor\Controls_Manager::HEADING,
                'label' => __('Icon', 'xstore-core'),
                'condition' => [
                    "graphic_element_{$key}" => 'icon',
                ],
                'separator' => 'before',
            ]
        );

        $this->add_control(
            "icon_{$key}_spacing",
            [
                'label' => __('Spacing', 'xstore-core'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 100,
                    ],
                ],
                'selectors' => [
                    "{{WRAPPER}} .etheme-flipbox-icon-{$key}" => 'margin-bottom: {{SIZE}}{{UNIT}};',
                ],
                'condition' => [
                    "graphic_element_{$key}" => 'icon',
                ],
            ]
        );

        $this->add_control(
            "icon_{$key}_color",
            [
                'label' => __('Color', 'xstore-core'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => ($key == 'b' ? '#fff' : ''),
                'condition' => [
                    "graphic_element_{$key}" => 'icon',
                ],
                'selectors' => [
                    "{{WRAPPER}} .etheme-flipbox-icon-{$key}" => 'color: {{VALUE}};',
                    "{{WRAPPER}} .etheme-flipbox-icon-{$key} svg" => 'fill: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            "icon_{$key}_bg_color",
            [
                'label' => __('Background Color', 'xstore-core'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '',
                'condition' => [
                    "graphic_element_{$key}" => 'icon',
                ],
                'selectors' => [
                    "{{WRAPPER}} .etheme-flipbox-icon-{$key}" => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            "icon_{$key}_size",
            [
                'label' => __('Icon Size', 'xstore-core'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 100,
                    ],
                ],
                'selectors' => [
                    "{{WRAPPER}} .etheme-flipbox-icon-{$key}" => 'font-size: {{SIZE}}{{UNIT}};',
                    "{{WRAPPER}} .etheme-flipbox-icon-{$key} svg" => 'width: {{SIZE}}{{UNIT}};',
                ],
                'condition' => [
                    "graphic_element_{$key}" => 'icon',
                ],
            ]
        );

        $this->add_control(
            "icon_{$key}_padding",
            [
                'label' => __('Icon Padding', 'xstore-core'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'selectors' => [
                    "{{WRAPPER}} .etheme-flipbox-icon-{$key}" => 'padding: {{SIZE}}{{UNIT}};',
                ],
                'size_units' => ['px', 'em'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 50,
                    ],
                    'em' => [
                        'min' => 0,
                        'max' => 5,
                    ],
                ],
                'condition' => [
                    "graphic_element_{$key}" => 'icon',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => "icon_{$key}_border",
                'selector' => "{{WRAPPER}} .etheme-flipbox-icon-{$key}",
                'condition' => [
                    "graphic_element_{$key}" => 'icon',
                ],
                'separator' => 'before',
            ]
        );

        $this->add_control(
            "icon_{$key}_border_radius",
            [
                'label' => __('Border Radius', 'xstore-core'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    "{{WRAPPER}} .etheme-flipbox-icon-{$key}" => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
                'condition' => [
                    "graphic_element_{$key}" => 'icon',
                ],
            ]
        );

        $this->add_control(
            "heading_title_{$key}_style",
            [
                'type' => \Elementor\Controls_Manager::HEADING,
                'label' => __('Title', 'xstore-core'),
                'separator' => 'before',
                'condition' => [
                    "title_{$key}!" => '',
                ],
            ]
        );

        $this->add_control(
            "title_{$key}_spacing",
            [
                'label' => __('Spacing', 'xstore-core'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 100,
                    ],
                ],
                'selectors' => [
                    "{{WRAPPER}} .etheme-flipbox-title-{$key}" => 'margin-bottom: {{SIZE}}{{UNIT}};',
                ],
                'condition' => [
                    "title_{$key}!" => '',
                ],
            ]
        );

        $this->add_control(
            "title_{$key}_color",
            [
                'label' => __('Text Color', 'xstore-core'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => ($key == 'b' ? '#fff' : ''),
                'selectors' => [
                    "{{WRAPPER}} .etheme-flipbox-title-{$key}" => 'color: {{VALUE}}',
                ],
                'condition' => [
                    "title_{$key}!" => '',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => "title_{$key}_typography",
                'selector' => "{{WRAPPER}} .etheme-flipbox-title-{$key}",
                'condition' => [
                    "title_{$key}!" => '',
                ],
            ]
        );

        $this->add_control(
            "heading_description_{$key}_style",
            [
                'type' => \Elementor\Controls_Manager::HEADING,
                'label' => __('Description', 'xstore-core'),
                'separator' => 'before',
                'condition' => [
                    "description_{$key}!" => '',
                ],
            ]
        );

        $this->add_control(
            "description_{$key}_spacing",
            [
                'label' => __('Spacing', 'xstore-core'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 100,
                    ],
                ],
                'selectors' => [
                    "{{WRAPPER}} .etheme-flipbox-description-{$key}" => 'margin-bottom: {{SIZE}}{{UNIT}};',
                ],
                'condition' => [
                    "description_{$key}!" => '',
                ],
            ]
        );

        $this->add_control(
            "description_{$key}_color",
            [
                'label' => __('Text Color', 'xstore-core'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => ($key == 'b' ? '#f7f7f7' : ''),
                'selectors' => [
                    "{{WRAPPER}} .etheme-flipbox-description-{$key}" => 'color: {{VALUE}}',
                ],
                'condition' => [
                    "description_{$key}!" => '',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => "description_{$key}_typography",
                'selector' => "{{WRAPPER}} .etheme-flipbox-description-{$key}",
                'condition' => [
                    "description_{$key}!" => '',
                ],
            ]
        );

        $this->add_control(
            "heading_button_{$key}_style",
            [
                'type' => \Elementor\Controls_Manager::HEADING,
                'label' => __('Button', 'xstore-core'),
                'separator' => 'before',
                'condition' => [
                    "button_{$key}_text!" => '',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(), [
                'name' => "button_{$key}_border",
                'selector' => "{{WRAPPER}} .etheme-flipbox-button-{$key}",
                'condition' => [
                    "button_{$key}_text!" => '',
                ],
            ]
        );

        $this->add_control(
            "button_{$key}_border_radius",
            [
                'label' => __('Border Radius', 'xstore-core'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    "{{WRAPPER}} .etheme-flipbox-button-{$key}" => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
                'condition' => [
                    "button_{$key}_text!" => '',
                ],
            ]
        );

        $this->add_control(
            "button_{$key}_padding",
            [
                'label' => __('Padding', 'xstore-core'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    "{{WRAPPER}} .etheme-flipbox-button-{$key}" => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
                'condition' => [
                    "button_{$key}_text!" => '',
                ],
            ]
        );

        $this->start_controls_tabs("button_{$key}_style", [
            'condition' => [
                "button_{$key}_text!" => '',
            ],
        ]);

        $this->start_controls_tab(
            "button_{$key}_normal",
            [
                'label' => __('Normal', 'xstore-core'),
            ]
        );

        $this->add_control(
            "button_{$key}_text_color",
            [
                'label' => __('Text Color', 'xstore-core'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    "{{WRAPPER}} .etheme-flipbox-button-{$key}" => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            "button_{$key}_background_color",
            [
                'label' => __('Background Color', 'xstore-core'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    "{{WRAPPER}} .etheme-flipbox-button-{$key}" => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->start_controls_tab(
            "button_{$key}_hover",
            [
                'label' => __('Hover', 'xstore-core'),
            ]
        );

        $this->add_control(
            "button_{$key}_hover_color",
            [
                'label' => __('Text Color', 'xstore-core'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    "{{WRAPPER}} .etheme-flipbox-button-{$key}:hover" => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            "button_{$key}_background_hover_color",
            [
                'label' => __('Background Color', 'xstore-core'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    "{{WRAPPER}} .etheme-flipbox-button-{$key}:hover" => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            "button_{$key}_hover_border_color",
            [
                'label' => __('Border Color', 'xstore-core'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    "{{WRAPPER}} .etheme-flipbox-button-{$key}:hover" => 'border-color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => "button_{$key}_typography",
                'selector' => "{{WRAPPER}} .etheme-flipbox-button-{$key}",
                'condition' => [
                    "button_{$key}_text!" => '',
                ],
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Render widget output on the frontend.
     *
     * Written in PHP and used to generate the final HTML.
     *
     * @since 4.0.6
     * @access protected
     */
    protected function render()
    {

        $settings = $this->get_settings_for_display();

        $this->add_render_attribute('wrapper', 'class', 'etheme-flipbox-wrapper');

        $flip_effects = ['flip', 'flip-2', 'slide', 'slide-2', 'overlay', 'zoom-in', 'zoom-in-2', 'zoom-out', 'zoom-out-2', 'fade'];

        $flip_effect = $settings['flip_effect'];

        if ($flip_effect == 'random') {
            $flip_effect = $flip_effects[rand(0, count($flip_effects) - 1)];
        }

        switch ($flip_effect) {
            case 'flip-2':
                $flip_effect_class = 'flip etheme-flip-box-effect-flip-bounced';
                break;
            case 'slide-2':
                $flip_effect_class = 'slide etheme-flip-box-effect-slide-delayed';
                break;
            default:
                $flip_effect_class = $flip_effect;
        }

        $this->add_render_attribute('wrapper', 'class', 'etheme-flip-box-effect-' . $flip_effect_class);

        if (in_array($flip_effect, array('flip', 'flip-2')) && $settings['flip_3d']) {
            $this->add_render_attribute('wrapper', 'class', 'etheme-flip-box-3d');
        }

        if (in_array($flip_effect, array('flip', 'flip-2', 'slide', 'slide-2', 'overlay', 'random'))) {
            $flip_directions = ['left', 'right', 'up', 'down'];
            $flip_direction = $settings['flip_direction'];
            if ($flip_direction == 'random') {
                $flip_direction = $flip_directions[rand(0, count($flip_directions) - 1)];
            }
            $this->add_render_attribute('wrapper', 'class', 'etheme-flip-box-direction-' . $flip_direction);
        }

        // side a
        $this->add_render_attribute('side_a', 'class', 'etheme-flipbox-side_a');

        $this->add_render_attribute('icon_wrapper_a', 'class', ['elementor-icon', 'etheme-flipbox-icon', 'etheme-flipbox-icon-a']);
        $this->add_render_attribute('image_wrapper_a', 'class', ['etheme-flipbox-image', 'etheme-flipbox-image-a']);

        $this->add_render_attribute('title_a', 'class', ['etheme-flipbox-title', 'etheme-flipbox-title-a']);
        $this->add_render_attribute('description_a', 'class', ['etheme-flipbox-description', 'etheme-flipbox-description-a']);
        // button
        $this->add_render_attribute('button_a_text', 'class', [
            'elementor-button',
            'etheme-flipbox-button',
            'etheme-flipbox-button-a',
        ]);

        if (!empty($settings['link_a']['url'])) {
            $this->add_link_attributes('button_a_text', $settings['link_a']);
        }

        // side b
        $this->add_render_attribute('side_b', 'class', 'etheme-flipbox-side_b');

        $this->add_render_attribute('icon_wrapper_b', 'class', ['elementor-icon', 'etheme-flipbox-icon', 'etheme-flipbox-icon-b']);
        $this->add_render_attribute('image_wrapper_b', 'class', ['etheme-flipbox-image', 'etheme-flipbox-image-b']);

        $this->add_render_attribute('title_b', 'class', ['etheme-flipbox-title', 'etheme-flipbox-title-b']);
        $this->add_render_attribute('description_b', 'class', ['etheme-flipbox-description', 'etheme-flipbox-description-b']);
        // button
        $this->add_render_attribute('button_b_text', 'class', [
            'elementor-button',
            'etheme-flipbox-button',
            'etheme-flipbox-button-b',
        ]);

        if (!empty($settings['link_b']['url'])) {
            $this->add_link_attributes('button_b_text', $settings['link_b']);
        }

        $migration_allowed = \Elementor\Icons_Manager::is_migration_allowed();

        ?>

        <div <?php $this->print_render_attribute_string('wrapper'); ?>>
            <div <?php $this->print_render_attribute_string('side_a'); ?>>
                <div class="etheme-flipbox-inner">
                    <?php switch ($settings['graphic_element_a']) {
                        case 'icon':
                            if (!empty($settings['icon_a']) || !empty($settings['selected_icon_a'])) : ?>
                                <div <?php echo $this->get_render_attribute_string('icon_wrapper_a'); ?>>
                                    <?php if ((empty($settings['icon_a']) && $migration_allowed) || isset($settings['__fa4_migrated']['selected_icon_a'])) :
                                        \Elementor\Icons_Manager::render_icon($settings['selected_icon_a']);
                                    else : ?>
                                        <i <?php echo $this->get_render_attribute_string('icon_a'); ?>></i>
                                    <?php endif; ?>
                                </div>
                            <?php
                            endif;
                            break;
                        case 'image': ?>
                            <div <?php echo $this->get_render_attribute_string('image_wrapper_a'); ?>>
                                <div class="etheme-flipbox-image">
                                    <?php echo \Elementor\Group_Control_Image_Size::get_attachment_image_html($settings, 'image_a'); ?>
                                </div>
                            </div>
                            <?php
                            break;
                        default;
                    }

                    if (!empty($settings['title_a'])) {
                        ?>
                        <h3 <?php echo $this->get_render_attribute_string('title_a'); ?>>
                            <?php echo $settings['title_a']; ?>
                        </h3>
                        <?php
                    }

                    if (!empty($settings['description_a'])) {
                        ?>
                        <div <?php $this->print_render_attribute_string('description_a'); ?>>
                            <?php echo $settings['description_a']; ?>
                        </div>
                        <?php
                    }

                    if (!empty($settings['button_a_text'])) : ?>

                        <a <?php echo $this->get_render_attribute_string('button_a_text'); ?>><?php echo $settings['button_a_text']; ?></a>

                    <?php endif; ?>
                </div>
            </div>
            <div <?php $this->print_render_attribute_string('side_b'); ?>>
                <div class="etheme-flipbox-inner">
                    <?php switch ($settings['graphic_element_b']) {
                        case 'icon':
                            if (!empty($settings['icon_b']) || !empty($settings['selected_icon_b'])) : ?>
                                <div <?php echo $this->get_render_attribute_string('icon_wrapper_b'); ?>>
                                    <?php if ((empty($settings['icon_b']) && $migration_allowed) || isset($settings['__fa4_migrated']['selected_icon_b'])) :
                                        \Elementor\Icons_Manager::render_icon($settings['selected_icon_b']);
                                    else : ?>
                                        <i <?php echo $this->get_render_attribute_string('icon_b'); ?>></i>
                                    <?php endif; ?>
                                </div>
                            <?php
                            endif;
                            break;
                        case 'image': ?>
                            <div <?php echo $this->get_render_attribute_string('image_wrapper_b'); ?>>
                                <div class="etheme-flipbox-image">
                                    <?php echo \Elementor\Group_Control_Image_Size::get_attachment_image_html($settings, 'image_b'); ?>
                                </div>
                            </div>
                            <?php
                            break;
                        default;
                    }

                    if (!empty($settings['title_b'])) {
                        ?>
                        <h3 <?php echo $this->get_render_attribute_string('title_b'); ?>>
                            <?php echo $settings['title_b']; ?>
                        </h3>
                        <?php
                    }

                    if (!empty($settings['description_b'])) {
                        ?>
                        <div <?php $this->print_render_attribute_string('description_b'); ?>>
                            <?php echo $settings['description_b']; ?>
                        </div>
                        <?php
                    }

                    if (!empty($settings['button_b_text'])) : ?>

                        <a <?php echo $this->get_render_attribute_string('button_b_text'); ?>><?php echo $settings['button_b_text']; ?></a>

                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
}