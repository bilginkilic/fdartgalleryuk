<?php

namespace FluentFormPro\Components\Post;


use FluentForm\App\Api\FormProperties;
use FluentForm\App\Helpers\Helper;
use FluentForm\Framework\Helpers\ArrayHelper as Arr;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

trait Getter
{
    private static function deleteAttachmentIds($attachmentIds)
    {
        $deletedIds = [];
        foreach ($attachmentIds as $id) {
            if (!$id) continue;
            $id = (int)$id;
            if (wp_delete_attachment($id)){
                $deletedIds[] = $id;
            }
        }
        return $deletedIds;
    }

    private static function maybeDeleteAndGetExistingAttachmentIds($fieldName, $formData)
    {
        $deleteAttachmentIds = static::maybeDeleteAttachmentIds($fieldName, $formData);
        $existingAttachmentIds = Arr::get($formData, 'existing-attachment-key-'.$fieldName, []);
        if ($existingAttachmentIds) {
            $existingAttachmentIds = array_map('intval', $existingAttachmentIds);
            if ($deleteAttachmentIds) {
                $existingAttachmentIds = array_values(array_diff($existingAttachmentIds, $deleteAttachmentIds));
            }
        }
        return $existingAttachmentIds;
    }

    private static function maybeDeleteAttachmentIds($fieldName, $formData)
    {
        $deleteAttachmentIds = Arr::get($formData, 'remove-attachment-key-'.$fieldName, []);
        if ($deleteAttachmentIds) {
            $deleteAttachmentIds = static::deleteAttachmentIds($deleteAttachmentIds);
        }
        return $deleteAttachmentIds;
    }

    private static function resolveCustomMetaFileTypeField($customMetas, $form, $formData)
    {
        if (!$customMetas || !$form || !$formData) return $customMetas;

        $formFields = (new FormProperties($form))->inputs(['attributes']);
        foreach ($customMetas as $index => $field) {
            $name = Helper::getInputNameFromShortCode(Arr::get($field, 'meta_value', ''));
            if (!$name) continue;
            $formField = Arr::get($formFields, $name);
            if ('file' == Arr::get($formField, 'attributes.type')) {
                static::maybeDeleteAttachmentIds($name, $formData);
                if (!Arr::get($formData, $name)) {
                    Arr::forget($customMetas, $index);
                }
            }
        }
        return $customMetas;
    }

    public static function resolveDateFieldFormat($fields, $form)
    {
        if (!$fields || !$form) return $fields;
        $formFields = (new FormProperties($form))->inputs();
        foreach ($fields as $index => $field) {
            $name = Helper::getInputNameFromShortCode(Arr::get($field, 'field_value', ''));
            if (!$name) continue;
            $formField = Arr::get($formFields, $name);
            if (
                Arr::get($formField, 'element') === 'input_date' &&
                $format = Arr::get($formField, 'raw.settings.date_format')
            ) {
                $fields[$index]['format'] = $format;
            }
        }
        return $fields;
    }

    protected static function maybeResolveProcessedFeedDateFormat($rawFields, $processedFields)
    {
        foreach ($processedFields as $index => &$field) {
            $dateFormat = Arr::get($rawFields, $index . '.format');
            if ($dateFormat) {
                $field['format'] = $dateFormat;
            }
        }
        return $processedFields;
    }

    private static function getPostType($formId)
    {
        $postSettings = wpFluent()->table('fluentform_form_meta')
            ->where('form_id', $formId)
            ->where('meta_key', 'post_settings')
            ->first()->value;

        $postSettings = json_decode($postSettings);

        return $postSettings->post_type;
    }

}