<?php

namespace FluentFormPro\Components\Post;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

use FluentForm\App\Helpers\Helper;
use FluentForm\Framework\Helpers\ArrayHelper as Arr;

class AcfHelper
{
    use Getter;
    static $acfUserFields = [];

    public static function getAcfFields($postType)
    {
        if (!class_exists('\ACF')) {
            return [
                'general' => [],
                'advanced' => []
            ];
        }

        $field_groups = acf_get_field_groups(array(
            'post_type' => $postType
        ));

        return self::classifyFields($field_groups);
    }

    public static function hasAcf()
    {
        return class_exists('\ACF');
    }

    public static function getAcfUserFields()
    {
        if (!self::hasAcf()) {
            return [
                'general' => [],
                'advanced' => []
            ];
        }

        $field_groups = acf_get_field_groups([
            "user_role"     => "all"
        ]);

        return self::classifyFields($field_groups);
    }

    private static function classifyFields($field_groups)
    {
        $generalAcfFields = self::getGeneralFields();
        $advancedAcfFields = self::getAdvancedFields();

        $generalFields = [];
        $advancedFields = [];

        foreach ($field_groups as $field_group) {
            $fields = acf_get_fields($field_group);
            foreach ($fields as $field) {
                if (in_array($field['type'], $generalAcfFields)) {
                    $generalFields[$field['key']] = [
                        'type' => $field['type'],
                        'label' => $field['label'],
                        'name' => $field['name'],
                        'key' => $field['key']
                    ];
                } else if (isset($advancedAcfFields[$field['type']])) {
                    $settings = $advancedAcfFields[$field['type']];
                    $advancedFields[$field['key']] = [
                        'type' => $field['type'],
                        'label' => $field['label'],
                        'name' => $field['name'],
                        'key' => $field['key'],
                        'acceptable_fields' => $settings['acceptable_fields'],
                        'help_message' => $settings['help']
                    ];
                }
            }
        }

        return [
            'general' => $generalFields,
            'advanced' => $advancedFields
        ];
    }

    public static function getGeneralFields()
    {
        $generalFields = [
            'text',
            'textarea',
            'number',
            'range',
            'email',
            'url',
            'password',
            'wysiwyg',
            'date_picker',
            'date_time_picker',
            'time_picker',
            'color_picker'
        ];
        $generalFields = apply_filters_deprecated(
            'fluent_post_acf_accepted_general_fields',
            [
                $generalFields
            ],
            FLUENTFORM_FRAMEWORK_UPGRADE,
            'fluentform/post_acf_accepted_general_fields',
            'Use fluentform/post_acf_accepted_general_fields instead of fluent_post_acf_accepted_general_fields.'
        );
        return apply_filters('fluentform/post_acf_accepted_general_fields', $generalFields);
    }

    public static function getAdvancedFields()
    {
        $advancedFields = [
            'select' => [
                'acceptable_fields' => ['select'],
                'help' => __('Select select field for this mapping', 'fluentformpro')
            ],
            'checkbox' => [
                'acceptable_fields' => ['input_checkbox'],
                'help' => __('Select checkbox field for this mapping', 'fluentformpro')
            ],
            'radio' => [
                'acceptable_fields' => ['input_radio'],
                'help' => __('Select radio field for this mapping', 'fluentformpro')
            ],
            'button_group' => [
                'acceptable_fields' => ['input_radio'],
                'help' => __('Select radio field for this mapping', 'fluentformpro')
            ],
            'true_false' => [
                'acceptable_fields' => ['terms_and_condition', 'gdpr_agreement'],
                'help' => __('Select single checkbox field for this mapping', 'fluentformpro')
            ],
            'file' => [
                'acceptable_fields' => ['input_file'],
                'help' => __('Select File upload field for this mapping', 'fluentformpro')
            ],
            'gallery' => [
                'acceptable_fields' => ['input_image'],
                'help' => __('Select Image upload field for this mapping', 'fluentformpro')
            ],
            'image' => [
                'acceptable_fields' => ['input_image'],
                'help' => __('Select Image upload field for this mapping', 'fluentformpro')
            ],
            'repeater' => [
                'acceptable_fields' => ['repeater_field'],
                'help' => __('Please select repeat field. Your ACF repeat and form field columns need to be equal', 'fluentformpro')
            ]
        ];
        $advancedFields = apply_filters_deprecated(
            'fluent_post_acf_accepted_advanced_fields',
            [
                $advancedFields
            ],
            FLUENTFORM_FRAMEWORK_UPGRADE,
            'fluentform/post_acf_accepted_advanced_fields',
            'Use fluentform/post_acf_accepted_advanced_fields instead of fluent_post_acf_accepted_advanced_fields.'
        );
        return apply_filters('fluentform/post_acf_accepted_advanced_fields', $advancedFields);
    }

    public static function prepareGeneralFieldsData($fields, $postType, $isUpdate = false)
    {
        $acfFields = self::getFieldsConfig($postType);

        if (!$acfFields) {
            return [];
        }

        $metaValues = [];

        foreach ($fields as $field) {
            $fieldValue = Arr::get($field, 'field_value');
            $fieldKey = Arr::get($field, 'field_key');

            if (!$fieldKey || !isset($acfFields[$fieldKey])) {
                continue;
            }

            if (!$isUpdate && !$fieldValue) {
                continue;
            }

            $fieldConfig = $acfFields[$fieldKey];
            if (in_array($fieldConfig['type'], ['date_picker', 'date_time_picker', 'time_picker'])) {
                $format = Arr::get($field,'format');
                if ($format && strpos($format, 'K') !== false) {
                    $format = str_replace('K', 'A', $format);
                }
                if ($format && $date = \DateTime::createFromFormat($format, $fieldValue)) {
                    if ($fieldConfig['type'] == 'date_time_picker') {
                        $fieldValue = $date->format('Y-m-d H:i:s');
                    } elseif ($fieldConfig['type']=== 'time_picker') {
                        $fieldValue = $date->format('H:i:s');
                    } else {
                        $fieldValue = $date->format('Ymd');
                    }
                }
            }
            $mataName = $fieldConfig['name'];
            $metaValues[$mataName] = $fieldValue;
        }

        return $metaValues;

    }

    public static function prepareAdvancedFieldsData($fields, $formData, $postType, $isUpdate = false)
    {
        $metaValues = [];
        foreach ($fields as $field) {
            $fieldValue = Arr::get($formData, $field['field_value']);
            if (!$isUpdate && !$fieldValue) {
                continue;
            }
            $fieldKey = $field['field_key'];
            $fieldConfig = acf_get_field($fieldKey);
            if (!$fieldConfig) {
                continue;
            }

            $fieldData = [];

            $type = $fieldConfig['type'];
            if ($type == 'image' || $type == 'file') {
                static::maybeDeleteAttachmentIds($field['field_value'], $formData);
                $fieldData = self::extractImageValue($fieldValue, $fieldConfig);
            } else if ($type == 'gallery') {
                $existingAttachmentIds = static::maybeDeleteAndGetExistingAttachmentIds($field['field_value'], $formData);
                if ($newAttachmentIds = self::extractImagesValue($fieldValue, $fieldConfig)) {
                    $fieldData[$fieldConfig['name']] = array_merge($existingAttachmentIds, $newAttachmentIds);
                }
            } else if ($type == 'repeater') {
                $fieldData = self::extractRepeaterValue($fieldValue, $fieldConfig);
            } else if ($type == 'true_false') {
                $mataName = $fieldConfig['name'];
                if ($fieldValue != 'on') {
                    continue;
                }
                $fieldData = [
                    $mataName => 1,
                    '_' . $mataName => $fieldConfig['key']
                ];
            } else if ($type == 'select' || $type == 'checkbox' || $type == 'radio' || $type == 'button_group') {
                $fieldData = self::extractCheckableValue($fieldValue, $fieldConfig);
            } else {
                continue;
            }
            if ($fieldData) {
                $metaValues = array_merge($metaValues, $fieldData);
            }
        }
        return $metaValues;
    }

    protected static function getFieldsConfig($postType)
    {
        if (!class_exists('ACF')) {
            return [];
        }

        if ($postType) {
            $field_groups = acf_get_field_groups([
                'post_type' => $postType
            ]);
        } else {
            $field_groups = acf_get_field_groups([
                "user_role"     => "all"
            ]);
        }

        $formattedFields = [];

        $acceptedFields = self::getGeneralFields();

        $acceptedFields = apply_filters_deprecated(
            'fluent_post_acf_accepted_fileds',
            [
                $acceptedFields
            ],
            FLUENTFORM_FRAMEWORK_UPGRADE,
            'fluentform/post_acf_accepted_fields',
            'Use fluentform/post_acf_accepted_fields instead of fluent_post_acf_accepted_fileds.'
        );
        $acceptedFields = apply_filters('fluentform/post_acf_accepted_fields', $acceptedFields);

        foreach ($field_groups as $field_group) {
            $fields = acf_get_fields($field_group);
            foreach ($fields as $field) {
                if (in_array($field['type'], $acceptedFields)) {
                    $formattedFields[$field['key']] = [
                        'type' => $field['type'],
                        'label' => $field['label'],
                        'name' => $field['name'],
                        'key' => $field['key']
                    ];
                }
            }
        }

        return $formattedFields;
    }

    /*
     * Extract Field values
     */

    private static function extractImageValue($fieldValue, $fieldConfig)
    {
        if (!is_array($fieldValue)) {
            return [];
        }
        $firstItem = $fieldValue[0];
        if (!$firstItem) {
            return [];
        }
        $attachmentId = (new PostFormHandler())->getAttachmentToImageUrl($firstItem);
        $mataName = $fieldConfig['name'];
        return [
            $mataName => $attachmentId
        ];
    }

    private static function extractImagesValue($fieldValue, $fieldConfig)
    {
        if (!is_array($fieldValue)) {
            return [];
        }
        $imageArrays = [];
        foreach ($fieldValue as $item) {
            $attachmentId = (new PostFormHandler())->getAttachmentToImageUrl($item);
            if ($attachmentId) {
                $imageArrays[] = $attachmentId;
            }
        }
        return $imageArrays;
    }

    private static function extractRepeaterValue($values, $fieldConfig)
    {
        if (!is_array($values) || !$values) {
            return [];
        }

        $subFields = Arr::get($fieldConfig, 'sub_fields', []);
        $mataName = $fieldConfig['name'];

        $itemValues = [];
        foreach ($values as $value) {
            $item = [];
            foreach ($subFields as $subfieldIndex => $subField) {
                $item[$subField['name']] = $value[$subfieldIndex];
            }
            $itemValues[] = $item;
        }

        if(!$itemValues) {
            return [];
        }

        return [$mataName => $itemValues];
    }

    private static function extractCheckableValue($value, $fieldConfig)
    {
        $mataName = $fieldConfig['name'];

        return [
            $mataName => $value
        ];
    }

    public static function maybeUpdateWithAcf($metaKey, $metaValue, $postId = false)
    {
        if (!$postId) {
            $postId = "user_" . get_current_user_id();
        }

        if (class_exists('\ACF')) {
            if (!static::$acfUserFields) {
                $acfUserMetaFields = static::getAcfUserFields();
                $acfUserMetaFields = array_merge($acfUserMetaFields['general'], $acfUserMetaFields['advanced']);
                $formatAcfUserMetaFields = [];
                foreach ($acfUserMetaFields as $field) {
                    $formatAcfUserMetaFields[$field['name']] = $field;
                }
                static::$acfUserFields = $formatAcfUserMetaFields;
            }

            if (isset(static::$acfUserFields[$metaKey])) {
                $type = Arr::get(static::$acfUserFields[$metaKey], 'type');
                if ('image' == $type || 'file' == $type) {
                    if (!$metaValue) {
                        return true;
                    }
                    if (is_array($metaValue) && count($metaValue) > 0) {
                        $metaValue = $metaValue[0];
                    }
                    $metaValue = (new PostFormHandler())->getAttachmentToImageUrl($metaValue);
                }
                update_field($metaKey, $metaValue, $postId);
                return true;
            }
        }
        return false;
    }

    public static function getUserMappingValue($acfMapping)
    {
        $metas = [];
        if ($userId = get_current_user_id()) {
            $acfFields = array_merge(Arr::get($acfMapping, 'general', []), Arr::get($acfMapping, 'advanced', []));
            foreach ($acfFields as $field) {
                $name = Arr::get($field, 'field_value');
                if ($name && preg_match('/{+(.*?)}/', $name)) {
                    $name = Helper::getInputNameFromShortCode($name);
                }
                if (!$name) {
                    continue;
                }
                $acfField = acf_get_field($field['field_key']);
                $value = acf_get_value('user_' . $userId, $acfField);
                if ('gallery' == $acfField['type'] && 'array' != Arr::get($acfField, 'return_format')) {
                    $acfField['return_format'] = 'array';
                }
                $value = acf_format_value($value, 'user_' . get_current_user_id(), $acfField);
                $metas[] = [
                    "name" => $name,
                    "type" => $acfField['type'],
                    "value" => $value
                ];
            }
        }
        return $metas;
    }

    public static function maybeUpdateUserMetas($userId, $formData, $form,$feed)
    {
        if (self::hasAcf() && function_exists('update_field') && $acfFields = Arr::get($feed, 'processedValues.acf_mappings')) {
            $isUserUpdate = 'user_update' == Arr::get($feed, 'settings.list_id');
            $acfMetas = [];
            if ($generalAcfFields = Arr::get($acfFields, 'general')) {
                $rawAcfGeneralFields = Arr::get($feed, 'settings.acf_mappings.general');
                $generalAcfFields = self::maybeResolveProcessedFeedDateFormat(self::resolveDateFieldFormat($rawAcfGeneralFields, $form), $generalAcfFields);
                $acfMetas = self::prepareGeneralFieldsData($generalAcfFields, null, $isUserUpdate);
            }
            if ($advancedAcfFields = Arr::get($acfFields, 'advanced')) {
                $advancedAcfMetas = self::prepareAdvancedFieldsData($advancedAcfFields, $formData, null, $isUserUpdate);
                if ($advancedAcfMetas) {
                    $acfMetas = array_merge($acfMetas, $advancedAcfMetas);
                }
            }
            foreach ($acfMetas as $fieldKey => $value) {
                update_field($fieldKey, $value, 'user_' . $userId);
            }
        }
    }
}
