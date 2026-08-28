<?php
namespace FluentFormPro\Components;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

use FluentForm\App\Services\FormBuilder\Components\BaseComponent;
use FluentForm\Framework\Helpers\ArrayHelper;

class FormStep extends BaseComponent
{
    /**
     * Compile and echo step header
     * @param array $data [element data]
     * @param object $form [Form Object]
     * @return void
     */
    public function stepStart($data, $form)
    {
        if (!$data) return;

        if ($data['settings']['progress_indicator'] == 'steps') {
            $stepTitles = apply_filters('fluentform/step_form_navigation_title', $data['settings']['step_titles'], $form);
            $nav = "<ul class='ff-step-titles'><li class='ff_active'><span>" . implode('</span></li><li><span>', $stepTitles) . "</span></li></ul>";
        }elseif ($data['settings']['progress_indicator'] == 'steps_with_nav') {
            $stepTitles = apply_filters('fluentform/step_form_navigation_title', $data['settings']['step_titles'], $form);
            $nav = "<ul class='ff-step-titles ff-step-titles-navs'  aria-live='polite'><li class='ff_active'><span>" . implode('</span></li><li><span>', $stepTitles) . "</span></li></ul>";
        } elseif ($data['settings']['progress_indicator'] == 'progress-bar') {
            $nav = "<div class='ff-el-progress-status' aria-live='polite'></div>
            <div class='ff-el-progress'>
                <div role='progressbar'  class='ff-el-progress-bar'><span></span></div>
            </div>
            <ul style='display: none' class='ff-el-progress-title'>
                <li>" . implode('</li><li>', $data['settings']['step_titles']) . "</li>
            </ul>";
        } else {
            $nav = '';
        }

        $data['attributes']['data-disable_auto_focus'] = ArrayHelper::get($data, 'settings.disable_auto_focus', 'no');
        $data['attributes']['data-enable_auto_slider'] = ArrayHelper::get($data, 'settings.enable_auto_slider', 'no');

        $data['attributes']['data-enable_step_data_persistency'] = ArrayHelper::get($data, 'settings.enable_step_data_persistency', 'no');
        $data['attributes']['data-enable_step_page_resume'] = ArrayHelper::get($data, 'settings.enable_step_page_resume', 'no');
        $data['attributes']['data-animation_type'] = ArrayHelper::get($data, 'settings.step_animation', 'slide');
        $atts = $this->buildAttributes(
            \FluentForm\Framework\Helpers\ArrayHelper::except($data['attributes'], 'name')
        );

        echo "<div class='ff-step-container' {$atts}>";
        if ($nav) {
            echo "<div class='ff-step-header'>{$nav}</div>";
        }

        echo "<span class='ff_step_start'></span><div class='ff-step-body'>";
        $data['attributes']['class'] .= ' fluentform-step';
        $data['attributes']['class'] = trim($data['attributes']['class']) . ' active';
        $atts = $this->buildAttributes(
            \FluentForm\Framework\Helpers\ArrayHelper::except($data['attributes'], 'name')
        );
        echo "<div {$atts}>";
    }

    /**
     * Compile and echo the html element
     * @param array $data [element data]
     * @param stdClass $form [Form Object]
     * @return void
     */
    public function compile($data, $form)
    {
        echo $this->compileButtons($data['settings']);
        $data['attributes']['class'] .= ' fluentform-step';
        $atts = $this->buildAttributes(
            \FluentForm\Framework\Helpers\ArrayHelper::except($data['attributes'], 'name')
        );
        echo "</div><div style='display: none;' {$atts}>";
    }

    /**
     * Compile and echo step footer
     * @param array $data [element data]
     * @param stdClass $form [Form Object]
     * @return void
     */
    public function stepEnd($data, $form)
    {
        $btnPrev = $this->compileButtons($data['settings']);
        ?>
        <div class="ff-step-t-container ff-inner_submit_container ff-column-container ff_columns_total_2">
            <div class="ff-t-cell ff-t-column-1"><?php  echo $btnPrev; ?></div>
            <div class="ff-t-cell ff-t-column-2">
                <?php
                do_action('fluentform/render_item_submit_button', $form->fields['submitButton'], $form);
                ?>
            </div>
        </div>
        </div></div></div>
        <?php
    }

    /**
     * Compile next and prev buttons
     * @param array $data [element data]
     * @return void
     */
    protected function compileButtons($data)
    {
        $btnPrev = $btnNext = '';
        $prev = isset($data['prev_btn']) ? $data['prev_btn'] : null;
        $next = isset($data['next_btn']) ? $data['next_btn'] : null;

        if ($prev) {
            if ($prev['type'] == 'default') {
                $tabIndex = \FluentForm\App\Helpers\Helper::getNextTabIndex();
                $tabIndexHtml = '';
                if($tabIndex) {
                    $tabIndexHtml = "tabindex='".$tabIndex."' ";
                }

                $btnClass = apply_filters('fluentform/step_prev_button_class', 'ff-btn ff-btn-prev ff-btn-secondary', $data);

                $btnPrev = "<button style='float: left;' ".$tabIndexHtml." type='button' data-action='prev' class='".$btnClass."' aria-label='".esc_attr($prev['text'])."'>" . $prev['text'] . "</button>";
            } else {
                $alt = esc_attr(ArrayHelper::get($next,'img_alt'));
                $btnPrev = "<img style='float: left;' data-action='prev' alt='{$alt}' class='prev ff-btn-prev ff_pointer' src={$prev['img_url']}>";
            }
        }

        if ($next) {
            if ($next['type'] == 'default') {
                $tabIndex = \FluentForm\App\Helpers\Helper::getNextTabIndex();
                $tabIndexHtml = '';
                if($tabIndex) {
                    $tabIndexHtml = "tabindex='".$tabIndex."' ";
                }

                $btnClass = apply_filters('fluentform/step_next_button_class', 'ff-float-right ff-btn ff-btn-next ff-btn-secondary', $data);

                $btnNext = "<button style='float: right;' ".$tabIndexHtml." type='button' data-action='next' class='".$btnClass."' aria-label='".esc_attr($next['text'])."'>" . $next['text'] . "</button>";
            } else {
                $alt = esc_attr(ArrayHelper::get($next,'img_alt'));
                $btnNext = "<img style='float: right;' data-action='next' alt='{$alt}' class='next ff-btn-next ff_pointer' src={$next['img_url']}>";
            }
        }

        return "<div class='step-nav ff_step_nav_last'>{$btnPrev}{$btnNext}</div>";
    }
}