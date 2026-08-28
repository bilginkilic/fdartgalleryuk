<?php
namespace FluentFormPro\Components;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

use FluentForm\App\Services\FormBuilder\Components\BaseComponent;

class ActionHook extends BaseComponent
{
	/**
	 * Compile and echo the html element
	 * @param  array $data [element data]
	 * @param  stdClass $form [Form Object]
	 * @return void
	 */
	public function compile($data, $form)
	{
        $elementName = $data['element'];
        
        $data = apply_filters_deprecated(
            'fluentform_rendering_field_data_' . $elementName,
            [
                $data,
                $form
            ],
            FLUENTFORM_FRAMEWORK_UPGRADE,
            'fluentform/rendering_field_data_' . $elementName,
            'Use fluentform/rendering_field_data_' . $elementName . ' instead of fluentform_rendering_field_data_' . $elementName
        );

        $data = apply_filters('fluentform/rendering_field_data_ ' . $elementName, $data, $form);

        $hasConditions = $this->hasConditions($data) ? 'has-conditions ' : '';
		
		$data['attributes']['class'] = trim(
			$this->getDefaultContainerClass()
			.' '. @$data['attributes']['class']
			.' '. $hasConditions
		);

		$atts = $this->buildAttributes(
			\FluentForm\Framework\Helpers\ArrayHelper::except($data['attributes'], 'name')
		);

		ob_start();
		echo "<div {$atts}>";
		do_action($data['settings']['hook_name'], $form);
		echo"</div>";
		$html = ob_get_clean();
        
        $html = apply_filters_deprecated(
            'fluentform_rendering_field_html_' . $elementName,
            [
                $html,
                $data,
                $form
            ],
            FLUENTFORM_FRAMEWORK_UPGRADE,
            'fluentform/rendering_field_html_' . $elementName,
            'Use fluentform/rendering_field_html_' . $elementName . ' instead of fluentform_rendering_field_html_'.$elementName
        );

        echo apply_filters('fluentform/rendering_field_html_' . $elementName, $html, $data, $form);
	}
}
