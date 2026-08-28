<?php

namespace FluentFormPro\Components;

class CheckableOtherOption
{
    /**
     * Other option for checkbox and radio fields
     */
    public static function boot()
    {
        // Add settings placement for form builder
        add_filter('fluentform/editor_element_settings_placement', function ($placements) {
            // Add settings for checkbox
            if (isset($placements['input_checkbox']['general'])) {
                $placements['input_checkbox']['general'][] = 'enable_other_option';
                $placements['input_checkbox']['general'][] = 'other_option_label';
                $placements['input_checkbox']['general'][] = 'other_option_placeholder';
            }
            // Add settings for radio
            if (isset($placements['input_radio']['general'])) {
                $placements['input_radio']['general'][] = 'enable_other_option';
                $placements['input_radio']['general'][] = 'other_option_label';
                $placements['input_radio']['general'][] = 'other_option_placeholder';
            }
            return $placements;
        });

        // Add default settings for existing forms upgrade - checkbox
        add_filter('fluentform/editor_init_element_input_checkbox', function ($element) {
            if (!isset($element['settings']['enable_other_option'])) {
                $element['settings']['enable_other_option'] = 'no';
            }
            if (!isset($element['settings']['other_option_label'])) {
                $element['settings']['other_option_label'] = __('Other', 'fluentformpro');
            }
            if (!isset($element['settings']['other_option_placeholder'])) {
                $element['settings']['other_option_placeholder'] = __('Please specify...', 'fluentformpro');
            }
            return $element;
        });

        // Add default settings for existing forms upgrade - radio
        add_filter('fluentform/editor_init_element_input_radio', function ($element) {
            if (!isset($element['settings']['enable_other_option'])) {
                $element['settings']['enable_other_option'] = 'no';
            }
            if (!isset($element['settings']['other_option_label'])) {
                $element['settings']['other_option_label'] = __('Other', 'fluentformpro');
            }
            if (!isset($element['settings']['other_option_placeholder'])) {
                $element['settings']['other_option_placeholder'] = __('Please specify...', 'fluentformpro');
            }
            return $element;
        });
    }
}
