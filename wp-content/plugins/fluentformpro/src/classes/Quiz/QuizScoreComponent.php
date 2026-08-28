<?php

namespace FluentFormPro\classes\Quiz;


use FluentForm\App\Services\FormBuilder\BaseFieldManager;
use FluentForm\App\Services\FormBuilder\Components\Text;
use FluentForm\Framework\Helpers\ArrayHelper as Arr;

class QuizScoreComponent extends BaseFieldManager
{
    public function __construct(
        $key = 'quiz_score',
        $title = 'Quiz Score',
        $tags = ['quiz', 'score'],
        $position = 'advanced'
    ) {
        parent::__construct(
            $key,
            $title,
            $tags,
            $position
        );
        add_filter("fluentform/editor_init_element_{$this->key}",function ($element) {
            
            if(!isset($element['options'])){
                $element['options'] = [
                    'Hero'     => 'Hero',
                    'Villain'  => 'Villain',
                    'Mentor'   => 'Mentor',
                    'Sage'     => 'Sage',
                    'Innocent' => 'Innocent',
                    'Rebel'    => 'Rebel',
                    'Explorer' => 'Explorer',
                ];
            }
            if(!isset($element['settings']['select_options'])){
                $element['settings']['select_options'] = '';
            }
    
            return $element;
        });
        add_filter("fluentform/input_data_{$this->key}", [$this, 'addScoreToSubmission'], 10, 4);
    }

    public function getComponent()
    {
        return [
            'index'          => 6,
            'element'        => $this->key,
            'attributes'     => array(
                'type'  => 'hidden',
                'name'  => 'quiz-score',
                'value' => 'empty',
            ),
            'settings'       => array(
                'admin_field_label' => 'Quiz Score',
                'result_type'       => 'total_point',
                'quiz_info'         => '',
                'select_options'    => '',
            ),
            'options'        => [
                'Hero'     => __('Hero', 'fluentformpro'),
                'Mentor'   => __('Mentor', 'fluentformpro'),
                'Sage'     => __('Sage', 'fluentformpro'),
                'Innocent' => __('Innocent', 'fluentformpro'),
            ],
            'editor_options' => array(
                'title'      => __('Quiz Score', 'fluentformpro'),
                'icon_class' => 'el-icon-postcard',
                'template'   => 'inputHidden'
            ),
        ];
    }

    public function getGeneralEditorElements()
    {
        return [
            'admin_field_label',
            'name',
            'result_type',
            'quiz_info',
            'select_options',
        ];
    }


   
    
    public function getEditorCustomizationSettings()
    {
        return [
        
            'result_type' => [
                'template'  => 'select',
                'label'     => __('Select Score Type', 'fluentformpro'),
                'help_text' => __('Select Score Type that you want to show', 'fluentformpro'),
                'options'   => [
                    [
                        'label' => __('Total Point. Example: 70', 'fluentformpro'),
                        'value' => 'total_point'
                    ],
                    [
                        'label' => __('Total Correct Questions. Example: 6', 'fluentformpro'),
                        'value' => 'total_correct'
                    ],
                    [
                        'label' => __('Fraction Point. Example: 6/10', 'fluentformpro'),
                        'value' => 'fraction_point'
                    ],
                    [
                        'label' => __('Grade System. Example: A', 'fluentformpro'),
                        'value' => 'grade'
                    ],
                    [
                        'label' => __('Percentage. Example: 70%', 'fluentformpro'),
                        'value' => 'percent'
                    ],
                    [
                        'label' => __('Personality Quiz', 'fluentformpro'),
                        'value' => 'personality'
                    ]
                ]
            ],
            'select_options' => [
                'template'     => 'selectOptions',
                'label'        => __('Personality Types', 'fluentform'),
                'help_text'    => __('Create visual options for the field and checkmark them for default selection.', 'fluentform'),
                'hide_default_value' => true,
                'show_values'   => true,
                'dependency'   => [
                    'depends_on' => 'settings/result_type',
                    'value'      => 'personality',
                    'operator'   => '==',
                ],
                
            ],
            'quiz_info' => [
                'template' => 'infoBlock',
                'text'     => __('<h6>Personality Quiz Instruction</h6> <span>Personality will be determined based on most answered option. The quiz answers and personality option value must match, so please add your answers value accordingly.</span></br>', 'fluentformpro'),
                'dependency' => [
                    'depends_on' => 'settings/result_type',
                    'value'      => 'personality',
                    'operator'   => '==',
                ],
            ],
        ];
    
    }

    public function render($data, $form)
    {
        return (new Text())->compile($data, $form);
    }
    
    
    /**
     * Process and generate quiz result from result  type for Submission
     *
     * @param $fieldValue
     * @param $field
     * @param $submissionData
     * @param $form
     * @return Quiz Result
     */
    public function addScoreToSubmission($fieldValue, $field, $submissionData, $form)
    {
        $quizController = new QuizController();
        $quizSettings = $quizController->getSettings($form->id);
        
        $quizResults = $quizController->getFormattedResults($quizSettings, $submissionData, $form);
        $score = 0;
        $totalCorrect = 0;
        $totalPoints = 0;
        $advancePoints = 0;
    
        $quizFields = $quizSettings['saved_quiz_fields'];
        
        $scoreType = Arr::get($field, 'raw.settings.result_type');
    
        foreach ($quizResults as $name => $result) {
            if( !in_array($name,array_keys($quizFields))){
                continue;
            }
            if ($result['has_advance_scoring'] == 'yes') {
                $score += $result['advance_points_score'];
                $totalCorrect++;
            
                $totalPoints += $result['advance_points'];
            } else {
                if ($result['correct']) {
                    $score += $result['points'];
                    $totalCorrect++;
                }
                $totalPoints += $result['points'];
            }
        }
    
        $result = apply_filters('fluentform/quiz_no_grade_label', __('Not Graded', 'fluentformpro'));
        switch ($scoreType) {
            case 'total_point':
                $result = $score;
                break;
            case 'total_correct':
                $result = $totalCorrect;
                break;
            case 'fraction_point':
                $result = $totalCorrect . '/' . count($quizResults);
                break;
            case 'percent':
                $result = number_format((($score / $totalPoints) * 100), 2) . '%';
                break;
            case 'grade':
                $grades = $quizSettings['grades'];
                foreach ($grades as $grade) {
                    if (($score >= Arr::get($grade, 'min')) && ($score <= Arr::get($grade, 'max'))) {
                        $result = Arr::get($grade, 'label');
                    }
                }
                break;
    
            case 'personality':
                $userSelections = $quizController->getUserSelectedValues($quizResults);
                $result = $this->determinePersonality($userSelections, $field, $form);
                break;
                
        }
        return apply_filters('fluentform/quiz_score_value', $result, $form->id, $scoreType, $quizResults);
    }
    
    public static function determinePersonality($userSelectedValues, $field, $form)
    {
        $personalityResult = apply_filters('fluentform/quiz_personality_test_fallback_label', __('Did not match any options!'), $form);
        $personalityOptions = Arr::get($field, 'raw.options');
        if(empty($userSelectedValues) || empty($personalityOptions)){
            return $personalityResult;
        }
        
        $mostSelectedOption = array_search(max(array_count_values($userSelectedValues)), array_count_values($userSelectedValues));
        foreach ($personalityOptions as $key => $value) {
            if ($key === $mostSelectedOption) {
                $personalityResult = $key;
                break;
            }
        }
        return $personalityResult;
    }
    
}
