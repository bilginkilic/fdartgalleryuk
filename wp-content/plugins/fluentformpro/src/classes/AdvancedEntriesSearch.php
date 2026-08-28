<?php

namespace FluentFormPro\classes;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

use FluentForm\App\Helpers\Helper;
use FluentForm\Framework\Helpers\ArrayHelper as Arr;
use FluentForm\App\Services\FormBuilder\EditorShortCode;
use FluentForm\App\Modules\Form\FormFieldsParser;

class AdvancedEntriesSearch
{
    protected $supportedColumns = [];
    protected $numericColumns = [];
    protected $entryDetailsTableAlies = [];

    public function init()
    {
        add_filter('fluentform/entries_vars', [$this, 'getAdvancedFilterOptions'], 10, 2);
        add_filter('fluentform/apply_entries_advance_filter', [$this, 'applyAdvancedFilter'], 10, 2);
    }

    public function getAdvancedFilterOptions($data, $form)
    {
        $submissionCodes = EditorShortCode::getSubmissionShortcodes();
        $fields = FormFieldsParser::getInputsByElementTypes($form, $this->supportedFields(), ['label', 'element', 'options', 'attributes', 'settings']);
        $fieldCodes = [];

        foreach ($fields as $name => $field) {
            // Remove repeater subfield
            if ('.*' === substr($name, -strlen('.*'))) {
                continue;
            }

            $arr = [
                'label' => $field['label'] ? $field['label'] : $name,
                'value' => $name,
            ];
            $element = Arr::get($field, 'element');

            if (in_array($element, ['input_number', 'item_quantity_component'])) {
                $arr['type'] = 'numeric';
            } elseif (in_array($element, ['input_radio', 'select', 'input_checkbox', 'multi_payment_component'])) {
                $arr['type'] = 'selections';
                $arr['options'] = Arr::get($field, 'options');
                $arr['is_multiple'] = false;
                if ('input_checkbox' == $element || Arr::isTrue($field, 'attributes.multiple')) {
                    $arr['is_multiple'] = true;
                }
            } elseif ('input_date' == $element) {
                $arr['type'] = 'dates';
                $format = Arr::get($field, 'settings.date_format');
                $arr['format'] = $format;
                $dateType = Arr::get($this->getDatesInfo(), $format . '.type');
                if ('time' == $dateType) {
                    $arr['type'] = 'time';
                }
                $arr['date_type'] = $dateType ?: 'date';
            } else {
                $arr['type'] = 'text';
            }
            $fieldCodes[] = $arr;
        }

        $formShortCodes = [
            'inputs' => [
                'label'    => __('Inputs', 'fluentformpro'),
                'value'     => 'inputs',
                'children' => array_filter($fieldCodes)
            ]
        ];


        $userCodes = $this->formatAdvancedFilters([
            'title'      => __('User', 'fluentformpro'),
            'value'       => 'user',
            'shortcodes' => $this->getUserCodes()
        ]);

        $submissionCodes['value'] = 'entry-attribute';
        $submissionCodes['shortcodes'] = array_filter($submissionCodes['shortcodes'], function ($key) {
            return !in_array($key, ['{submission.created_at}', '{submission.status}']);
        }, ARRAY_FILTER_USE_KEY);
        $submissionCodes = $this->formatAdvancedFilters($submissionCodes);
        $allCodes = array_merge($formShortCodes, $userCodes, $submissionCodes);
        $groups = [];

        foreach ($allCodes as $code) {
            $groups[] = $code;
        }

        $data['advanced_filters'] = array_values($groups);
        $data['advanced_filters_operators'] = $this->getOperators();
        $data['advanced_filters_columns'] = [
            'numeric' => [
                'user.ID', 'entry-attribute.id', 'entry-attribute.serial_number', 'entry-attribute.user_id',
            ]
        ];
        return $data;
    }

    protected function formatAdvancedFilters($shortCodes)
    {
        $codes = [];
        foreach ($shortCodes['shortcodes'] as $code => $label) {
            preg_match('/{+(.*?)}/', $code, $matches);
            if ($matches && false !== strpos($matches[1], 'submission.')) {
                $code = substr($matches[1], strlen('submission.'));
            }

            $codes[] = [
                'label' => $label,
                'value' => $code,
            ];
        }
        return [
            $shortCodes['value'] => [
                'label'    => $shortCodes['title'],
                'value'     => $shortCodes['value'],
                'children' => $codes
            ]
        ];
    }

    protected function getUserCodes()
    {
        return [
            'ID'           => "ID",
            'user_login'   => "Username",
            'display_name' => 'Display Name',
            'user_email'   => 'Email'
        ];
    }

    public function applyAdvancedFilter($query, $attributes)
    {
        // Prepare filter groups
        $advanceFilters = Arr::get($attributes, 'advanced_filter');
        $formId = Arr::get($attributes, 'form_id');
        $form = Helper::getForm($formId);
        if (!$form) {
            return $query;
        }
        $this->setSupportedColumns($form);

        $filters = $this->formatAndSanitizeFilters($advanceFilters);
        if (!$filters) {
            return $query;
        }
        return $this->applyFiltersQuery($query, $filters);
    }

    protected function setSupportedColumns($form)
    {
        $data = $this->getAdvancedFilterOptions([], $form);
        $filters = Arr::get($data, 'advanced_filters');
        $columns = [];
        foreach ($filters as $filter) {
            $children = Arr::get($filter, 'children');
            $columns = array_merge($columns, array_column($children, null, 'value'));
        }
        $this->supportedColumns = $columns;
        $this->numericColumns = Arr::get($data, 'advanced_filters_columns.numeric');
    }


    protected function formatAndSanitizeFilters($filters)
    {

        // Use default filters if not has a valid filter
        if (empty($filters)) {
            return false;
        }

        $formattedFilters = [];
        foreach ($filters as $groupsIndex => $groups) {
            if (!is_array($groups)) {
                continue;
            }
            foreach ($groups as $group) {
                if ($group = $this->sanitizeFilterGroup($group)) {
                    $sourceType = Arr::get($group, '0.0');
                    $formattedFilters[$groupsIndex][$sourceType][] = $group;
                }
            }
        }
        return $formattedFilters;
    }


    protected function sanitizeFilterGroup($group)
    {
        if (!$this->isValidGroup($group)) {
            return false;
        }
        $source = Arr::get($group, 'source');
        $operator = Arr::get($group, 'operator');
        $value = Arr::get($group, 'value');
        $value = $this->sanitizeValueBySource($value, $source, $operator);
        if (null === $value || (is_array($value) && empty($value))) {
            return false;
        }
        return $this->sanitizeFilterGroupForEluquentModel($source, $operator, $value);
    }

    protected function sanitizeValueBySource($value, $source, $operator)
    {
        $callback = null;
        $column = join('.', $source);
        if (in_array($column, $this->numericColumns)) {
            $callback = 'absint';
        } elseif ('entry-attribute.is_favourite' == $column) {
            $value = (int)Arr::isTrue(['is_favorites' => $value],'is_favorites');
        }
        return $this->sanitizeValue($value, $callback, $operator);
    }

    protected function sanitizeValue($value, $callback, $operator)
    {
        if (null === $value) {
            return null;
        }
        $value = $this->resolveValueForSanitize($value, $operator);
        if (is_array($value)) {
            if (is_callable($callback)) {
                $value = array_filter(array_map($callback, $value));
            } else {
                $value = array_filter(array_map(function ($v) {
                    return $this->sanitizeWithWPDB($v);
                }, $value));
            }
        } elseif (is_callable($callback)) {
            $value = $callback($value);
        } else {
            $value = $this->sanitizeWithWPDB($value);
        }
        return $value;
    }

    protected function resolveValueForSanitize($value, $operator)
    {
        switch ($operator) {
            case 'IN':
            case 'NOT IN':
                if (is_string($value)) {
                    $value = explode(',', $value);
                }
                $value = array_map('trim', $value);
                break;
            default:
                break;
        }
        return $value;
    }
    protected function sanitizeWithWPDB($value)
    {
        global $wpdb;
        $value = $wpdb->prepare('%s', $value);
        if ($value) {
            // Trim leading and trailing quotes
            $value = trim($value, "'\"");
        }
        return $value;
    }

    protected function sanitizeFilterGroupForEluquentModel($source, $operator, $value)
    {
        global $wpdb;
        switch ($operator) {
            case 'contains':
            case 'doNotContains':
            case 'startsWith':
            case 'endsWith':
                if (is_array($value)) {
                    $value = join('', $value);
                }
                $value = $wpdb->esc_like($value);
                if ('startsWith' === $operator) {
                    $value = "%" . $value;
                } elseif ('endsWith' === $operator) {
                    $value = $value . "%";
                } else {
                    $value = "%" . $value . "%";
                }
                $operator = 'doNotContains' === $operator ? 'NOT LIKE' : 'LIKE';
                break;
            default:
                break;
        }
        return [$source, $operator, $value];
    }


    protected function isValidGroup($group)
    {
        if (!$this->isSupportedColumn(Arr::get($group, 'source.1'))) {
            return false;
        }
        if (!$this->isValidOperator(Arr::get($group, 'operator'))) {
            return false;
        }
        return true;
    }

    protected function isValidOperator($operator)
    {
        return Arr::exists($this->getOperators(), $operator);
    }


    protected function isSupportedColumn($column)
    {
        return Arr::exists($this->supportedColumns, $column);
    }


    protected function applyFiltersQuery($query, $filters)
    {
        $entryDetailsJoinCount = 0;
        $hasUserJoin = false;
        // Determine the number of entry details joins required
        foreach ($filters as $groups) {
            if ($inputsConditions = Arr::get($groups, 'inputs')) {
                if ($entryDetailsJoinCount < count($inputsConditions)) {
                    $entryDetailsJoinCount = count($inputsConditions);
                }
            }
            if (Arr::isTrue($groups, 'user')) {
                $hasUserJoin = true;
            }
        }
        // Apply the necessary joins
        for ($i = 0; $i < $entryDetailsJoinCount; $i++) {
            $entryDetailsTableAlies = 'entry_details_' . $i;
            $this->entryDetailsTableAlies[] = $entryDetailsTableAlies;
            $query->leftJoin("fluentform_entry_details as {$entryDetailsTableAlies}", "{$entryDetailsTableAlies}.submission_id", '=', 'fluentform_submissions.id');
        }
        if ($hasUserJoin) {
            $query->leftJoin('users', 'users.ID', '=', 'fluentform_submissions.user_id');
        }
        // Apply filters
        foreach ($filters as $index => $groups) {
            $method = 0 === $index ? 'where' : 'orWhere';
            $query->{$method}(function ($query) use ($groups) {
                if ($inputsConditions = Arr::get($groups, 'inputs')) {
                    $this->applyWhereConditions($query, $inputsConditions, 'entryDetails');
                }
                if ($userConditions = Arr::get($groups, 'user')) {
                    $this->applyWhereConditions($query, $userConditions, 'user');
                }
                if ($entryAttrConditions = Arr::get($groups, 'entry-attribute')) {
                    $this->applyWhereConditions($query, $entryAttrConditions);
                }
                return $query;
            });
        }
        $query->distinct()->select('fluentform_submissions.*');
        return $query;
    }


    protected function applyWhereConditions($query, $conditions, $relationship = null)
    {
        if ($relationship) {
            foreach ($conditions as $index => $condition) {
                list($source, $operator, $value) = $condition;
                $column = $source[1];
                if ($relationship === 'entryDetails') {
                    // Resolve entry details join alies prefix
                    if ($entryDetailsTableAlias = Arr::get($this->entryDetailsTableAlies, $index)) {
                        $this->applyEntryDetailsQuery($query, $column, $operator, $value, $entryDetailsTableAlias);
                    }
                } elseif ($relationship === 'user') {
                    $this->applyCondition($query, 'users.' . $column, $operator, $value);
                }
            }
        } else {
            foreach ($conditions as $condition) {
                list($source, $operator, $value) = $condition;
                $column = 'fluentform_submissions.' . $source[1];
                $this->applyCondition($query, $column, $operator, $value);
            }
        }
    }

    protected function applyEntryDetailsQuery($query, $column, $operator, $value, $entryDetailsTableAlias)
    {
        foreach ($this->buildEntryDetailsWheres($column, $operator, $value) as $where) {
            if (Arr::get($where, 'format')) {
                $this->filterDates($query, $where, $entryDetailsTableAlias);
                continue;
            }
            $_column = Arr::get($where, 'column');
            $_value = Arr::get($where, 'value');
            $_operator = Arr::get($where, 'operator');
            $this->applyCondition($query, "{$entryDetailsTableAlias}.{$_column}", $_operator, $_value);
        }
    }

    protected function buildEntryDetailsWheres($column, $operator, $value)
    {
        $valueWhere = [
            'column' => 'field_value',
            'operator' => $operator,
            'value' => $value
        ];
        if ($field = Arr::get($this->supportedColumns, $column)) {
            if ($format = Arr::get($field, 'format')) {
                $dateInfo = [
                    'format' => $format,
                    'type' => Arr::get($field, 'type'),
                ];
                $valueWhere = array_merge($valueWhere, $dateInfo);
            }
        }
        $wheresGroup = [
            [
                'column' => 'field_name',
                'operator' => '=',
                'value' => $column,
            ],
            $valueWhere
        ];
        // Resolve sub_field_name
        if (preg_match('/\[(.*?)\]/', $column, $matches)) {
            $wheresGroup[] = [
                'column' => 'sub_field_name',
                'operator' => '=',
                'value' => $matches[1],
            ];
            $wheresGroup[0]['value'] = preg_replace('/\[(.*?)\]/', '', $column);
        }
        return $wheresGroup;
    }

    function applyCondition($query, $column, $operator, $value)
    {
        if (is_array($value)) {
            if ('IN' === $operator) {
                $query->whereIn($column, $value);
            } elseif ('NOT IN' === $operator) {
                $query->whereNotIn($column, $value);
            } else {
                foreach ($value as $v) {
                    $query->where($column, $operator, $v);
                }
            }
        } else {
            if (in_array($operator, ['=', '!=', '<', '>', '<=', '>=']) && is_numeric($value)) {
                global $wpdb;
                $column = $wpdb->prefix . $column;
                $query->where(function ($query) use ($column) {
                    $query->whereRaw("{$column} REGEXP ?", ['^-?[0-9]+(\.[0-9]+)?$']);
                })->where(function ($query) use ($column, $value, $operator) {
                    $query->whereRaw("CAST({$column} AS DOUBLE) {$operator} ?", [$value]);
                });
            } else {
                $query->where($column, $operator, $value);
            }
        }
    }

    protected function filterDates($query, $where, $entryDetailsTableAlias)
    {
        global $wpdb;
        $entryDetailsTableAlias = $wpdb->prefix . $entryDetailsTableAlias;
        $dateFormats = $this->getDatesInfo();
        $format = Arr::get($where, 'format');
        if (!Arr::exists($dateFormats, $format)) {
            return $query;
        }
        $column = Arr::get($where, 'column');
        $column = "{$entryDetailsTableAlias}.{$column}";
        $operator = Arr::get($where, 'operator');
        $value = Arr::get($where, 'value');
        $regex = Arr::get($dateFormats, $format . '.regex');
        $mysqlDateFormat = Arr::get($dateFormats, $format . '.mysql_format');
        $dateInputFormat = 'time' === Arr::get($where, 'type') ? '%H:%i:%s' : '%Y-%m-%d %H:%i:%s';
        $query->where(function ($query) use ($column, $mysqlDateFormat, $regex, $dateInputFormat, $operator, $value) {
            // Use REGEXP to filter valid date/time strings
            $query->whereRaw("{$column} REGEXP ?", [$regex]);
            if (is_array($value) && count($value) === 2) {
                // Use BETWEEN or NOT BETWEEN for date ranges
                if ($operator === 'BETWEEN' || $operator === 'NOT BETWEEN') {
                    $query->whereRaw(
                        "STR_TO_DATE({$column}, '{$mysqlDateFormat}') {$operator} STR_TO_DATE(?, '{$dateInputFormat}') AND STR_TO_DATE(?, '{$dateInputFormat}')",
                        [$value[0], $value[1]]
                    );
                }
            } elseif (is_string($value)) {
                // Use regular comparison for single date value
                $query->whereRaw(
                    "STR_TO_DATE({$column}, '{$mysqlDateFormat}') {$operator} STR_TO_DATE(?, '{$dateInputFormat}')",
                    [$value]
                );
            }
        });

        return $query;
    }


    public function getOperators()
    {
        return [
            '='             => __('Equal', 'fluentformpro'),
            '!='            => __('Not Equal', 'fluentformpro'),
            'IN'            => __('Equal In', 'fluentformpro'),
            'NOT IN'        => __('Not Equal In', 'fluentformpro'),
            '>'             => __('Greater Than', 'fluentformpro'),
            '<'             => __('Less Than', 'fluentformpro'),
            '>='            => __('Greater Than or Equal', 'fluentformpro'),
            '<='            => __('Less Than or Equal', 'fluentformpro'),
            'contains'      => __('Contains', 'fluentformpro'),
            'doNotContains' => __('Does Not Contain', 'fluentformpro'),
            'startsWith'    => __('Starts With', 'fluentformpro'),
            'endsWith'      => __('Ends With', 'fluentformpro'),
            'BETWEEN'       => __('Between', 'fluentformpro'),
            'NOT BETWEEN'   => __('Not Between', 'fluentformpro'),
        ];
    }

    protected function getDatesInfo()
    {
        return [
            'd/m/Y' => [
                'regex' => '^\d{2}/\d{2}/\d{4}$',
                'mysql_format' => '%d/%m/%Y',
                'type' => 'date'
            ],
            'm/d/Y' => [
                'regex' => '^\d{2}/\d{2}/\d{4}$',
                'mysql_format' => '%m/%d/%Y',
                'type' => 'date'
            ],
            'd.m.Y' => [
                'regex' => '^\d{2}\.\d{2}\.\d{4}$',
                'mysql_format' => '%d.%m.%Y',
                'type' => 'date'
            ],
            'm.d.Y' => [
                'regex' => '^\d{2}\.\d{2}\.\d{4}$',
                'mysql_format' => '%m.%d.%Y',
                'type' => 'date'
            ],
            'n/j/Y' => [
                'regex' => '^\d{1,2}/\d{1,2}/\d{4}$',
                'mysql_format' => '%c/%e/%Y',
                'type' => 'date'
            ],
            'm/d/y' => [
                'regex' => '^\d{2}/\d{2}/\d{2}$',
                'mysql_format' => '%m/%d/%y',
                'type' => 'date'
            ],
            'd/m/y' => [
                'regex' => '^\d{2}/\d{2}/\d{2}$',
                'mysql_format' => '%d/%m/%y',
                'type' => 'date'
            ],
            'M/d/Y' => [
                'regex' => '^[A-Za-z]{3}/\d{2}/\d{4}$',
                'mysql_format' => '%b/%d/%Y',
                'type' => 'date'
            ],
            'y/m/d' => [
                'regex' => '^\d{2}/\d{2}/\d{2}$',
                'mysql_format' => '%y/%m/%d',
                'type' => 'date'
            ],
            'Y-m-d' => [
                'regex' => '^\d{4}-\d{2}-\d{2}$',
                'mysql_format' => '%Y-%m-%d',
                'type' => 'date'
            ],
            'd-M-y' => [
                'regex' => '^\d{2}-[A-Za-z]{3}-\d{2}$',
                'mysql_format' => '%d-%b-%y',
                'type' => 'date'
            ],
            'm/d/Y h:i K' => [
                'regex' => '^\d{2}/\d{2}/\d{4} \d{1,2}:\d{2} [APap][Mm]$',
                'mysql_format' => '%m/%d/%Y %l:%i %p',
                'type' => 'datetime'
            ],
            'm/d/Y H:i' => [
                'regex' => '^\d{2}/\d{2}/\d{4} \d{2}:\d{2}$',
                'mysql_format' => '%m/%d/%Y %H:%i',
                'type' => 'datetime'
            ],
            'd/m/Y h:i K' => [
                'regex' => '^\d{2}/\d{2}/\d{4} \d{1,2}:\d{2} [APap][Mm]$',
                'mysql_format' => '%d/%m/%Y %l:%i %p',
                'type' => 'datetime'
            ],
            'd/m/Y H:i' => [
                'regex' => '^\d{2}/\d{2}/\d{4} \d{2}:\d{2}$',
                'mysql_format' => '%d/%m/%Y %H:%i',
                'type' => 'datetime'
            ],
            'd.m.Y h:i K' => [
                'regex' => '^\d{2}\.\d{2}\.\d{4} \d{1,2}:\d{2} [APap][Mm]$',
                'mysql_format' => '%d.%m.%Y %l:%i %p',
                'type' => 'datetime'
            ],
            'd.m.Y H:i' => [
                'regex' => '^\d{2}\.\d{2}\.\d{4} \d{2}:\d{2}$',
                'mysql_format' => '%d.%m.%Y %H:%i',
                'type' => 'datetime'
            ],
            'h:i K' => [
                'regex' => '^\d{1,2}:\d{2} [APap][Mm]$',
                'mysql_format' => '%l:%i %p',
                'type' => 'time'
            ],
            'H:i' => [
                'regex' => '^\d{2}:\d{2}$',
                'mysql_format' => '%H:%i',
                'type' => 'time'
            ],
        ];
    }



    protected function supportedFields()
    {
        return [
            "input_name",
            "input_email",
            "input_text",
            "input_mask",
            "textarea",
            "address",
            "input_number",
            "select",
            "input_radio",
            "input_checkbox",
            "multi_select",
            "input_url",
            "input_date",
            "select_country",
            "custom_html",
            "ratings",
            "input_hidden",
            "terms_and_condition",
            "gdpr_agreement",
            'custom_payment_component',
            'multi_payment_component',
            'payment_method',
            'item_quantity_component',
            'rangeslider',
            'payment_coupon',
        ];
    }
}