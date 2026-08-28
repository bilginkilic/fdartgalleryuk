<?php

namespace FluentFormPro\Components\DynamicField;

use FluentForm\Framework\Helpers\ArrayHelper as Arr;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class DynamicCsv
{
    protected $source = 'dynamic_csv';
    public function __construct()
    {
        add_filter('fluentform/dynamic_field_sources', [$this, 'addDynamicCsvOnSource']);
        add_filter('fluentform/dynamic_field_filter_get_result' . $this->source, [$this, 'getResult'], 10, 2);
    }

    public function addDynamicCsvOnSource($sources) {
        $sources[$this->source] = __('Dynamic CSV', 'fluentformpro');
        return $sources;
    }

    public function getResult($_, $config)
    {
        $csvUrl = Arr::get($config, 'csv_url', '');
        $csvUrl = sanitize_url($csvUrl);
        if (!$csvUrl) {
            throw new \Exception('invalid error');
        }
        if (!class_exists('CSVParser')) {
            require_once(FLUENTFORMPRO_DIR_PATH . 'libs/CSVParser/CSVParser.php');
        }
        $csvParser = new \CSVParser;
        $content = @file_get_contents($csvUrl);
        if (!$content) {
            throw new \Exception(__('Invalid url', 'fluentformpro'));
        }

        $csvParser->load_data($content);
        $csvDelimiter = Arr::get($config, 'csv_delimiter');
        if ('comma' == $csvDelimiter) {
            $csvDelimiter = ",";
        } elseif ('semicolon' == $csvDelimiter) {
            $csvDelimiter = ";";
        } else {
            $csvDelimiter = $csvParser->find_delimiter();
        }
        $result = $csvParser->parse($csvDelimiter);

        if(!$result) {
            throw new \Exception(__('Empty data', 'fluentformpro'));
        }
        $headers = array_shift($result);
        $limit = (int)Arr::get($config, 'result_limit');
        if ($limit && $limit < count($result)) {
            $result = array_slice($result, 0, $limit);
        }

        $value = sanitize_text_field(Arr::get($config, 'template_value.value', "{{$headers[0]}}"));
        $label = sanitize_text_field(Arr::get($config, 'template_label.value', "{{$headers[0]}}"));
        if (!$value) {
            return [];
        }
        $validOptions = [];
        $uniqueResult = 'yes' === Arr::get($config, 'unique_result');
        $uniqueValueSet = [];
        foreach ($result as $key  => $row) {
            if (count($headers) !== count($row)) {
                continue;
            }
            $result[$key] = array_combine($headers, $row);

            // Replace placeholders in value
            $valueResult = $this->replacePlaceholders($value, $result[$key]);
            if (!is_string($value)) {
                continue;
            }
            $valueResult = esc_attr($valueResult);
            if (!$valueResult) {
                continue;
            }
            // Replace placeholders in label
            $labelResult = $this->replacePlaceholders($label, $result[$key]);

            // Use value result as label if label result is not a string or empty
            if (!is_string($labelResult)) {
                $labelResult = $valueResult;
            }
            $labelResult = esc_html($labelResult);
            if (!$labelResult) {
                $labelResult = $valueResult;
            }
            // Check for duplicate values and skip if found
            if ($uniqueResult && in_array($valueResult, $uniqueValueSet)) {
                continue;
            }
            $validOptions[] = ['id' => $key, 'label' => $labelResult, 'value' => $valueResult];
            $uniqueValueSet[] = $valueResult;
        }

        return [
            'result_counts'  => [
                'total' => count($result),
                'valid' => count($validOptions),
            ],
            'valid_options'  => $validOptions,
            'all_options'    => $result,
        ];
    }

    protected function replacePlaceholders($string, $row)
    {
        return preg_replace_callback('/\{([^\}]+)\}/', function ($matches) use ($row) {
            return isset($row[$matches[1]]) ? trim($row[$matches[1]]) : '';
        }, $string);
    }
}