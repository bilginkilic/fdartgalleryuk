<?php

namespace FluentFormPro\Components\ChainedSelect;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

use Exception;
use FluentForm\App\Modules\Form\Form;
use FluentForm\App\Modules\Form\FormFieldsParser;

class ChainedSelectDataSourceManager
{
    protected $app = null;

    public function __construct($app)
    {
        $this->app = $app;
    }

    public function saveDataSource($csvParser)
    {
        try {
            $request = $this->app->request;
            $type = $request->get('type');
            $formId = intval($request->get('form_id'));
            $metaKey = sanitize_file_name($request->get('meta_key'));

            if (empty($formId)) {
                throw new Exception(__('Invalid form ID.', 'fluentformpro'), 400);
            }

            if (empty($metaKey)) {
                throw new Exception(__('Invalid meta key.', 'fluentformpro'), 400);
            }

            // Ensure meta_key always starts with chained_select_
            if (strpos($metaKey, 'chained_select_') !== 0) {
                throw new Exception(__('Invalid meta key format.', 'fluentformpro'), 400);
            }

            $this->ensureUploadDirectory();

            if ($url = $request->get('url')) {
                $this->validateUrl($url);
                
                $parsedUrl = parse_url($url);
                $path = isset($parsedUrl['path']) ? $parsedUrl['path'] : '';
                
                $hasCsvInPath = preg_match('/\.csv(\?|$|#|&|%)/i', $path);
                
                $hasCsvQueryParam = isset($parsedUrl['query']) && (
                    strpos($parsedUrl['query'], 'output=csv') !== false ||
                    strpos($parsedUrl['query'], 'format=csv') !== false ||
                    strpos($parsedUrl['query'], 'exportFormat=csv') !== false
                );
                
                if ($hasCsvInPath || $hasCsvQueryParam) {
                    $response = wp_remote_get($url, [
                        'timeout' => 10,
                        'redirection' => 2,
                        'sslverify' => true,
                        'user-agent' => 'FluentForm/' . FLUENTFORMPRO_VERSION,
                    ]);

                    if (is_wp_error($response)) {
                        throw new Exception(__('Failed to fetch URL.', 'fluentformpro'), 400);
                    }

                    $responseCode = wp_remote_retrieve_response_code($response);
                    if ($responseCode !== 200) {
                        throw new Exception(__('Failed to fetch URL.', 'fluentformpro'), 400);
                    }

                    $data = wp_remote_retrieve_body($response);
                    
                    if (empty($data)) {
                        throw new Exception(__('Empty file content.', 'fluentformpro'), 400);
                    }

                    $path = wp_upload_dir()['basedir'] . FLUENTFORM_UPLOAD_DIR;
                    $target = $path . '/' . $metaKey . '_' . $formId . '.csv';
                    
                    $realPath = realpath($path);
                    $realTarget = realpath(dirname($target));
                    if ($realTarget === false || strpos($realTarget, $realPath) !== 0) {
                        throw new Exception(__('Invalid file path.', 'fluentformpro'), 400);
                    }
                    
                    file_put_contents(
                        $target,
                        $data
                    );
                } else {
                    throw new Exception(__('File URL is not valid. URL must point to a CSV file.', 'fluentformpro'), 400);
                }
            } else {
                if ($request->files('file')) {
                    $file = $request->files('file')['file'];
                    $name = $_FILES['file']['name'];
                    $data = $file->getContents();
                }
            }

            if (!isset($data) || !$data) {
                throw new Exception(__('Oops! Something went wrong.', 'fluentformpro'), 400);
            }

            $csvParser->load_data($data);

            $result = $csvParser->parse($csvParser->find_delimiter());

            if (is_array($result) && count($result)) {
//                 $this->saveFieldoptions($result, $formId, $metaKey);
                $success = true;
                if ($request->files('file')) {
                    $success = $this->saveFile($file, $formId, $metaKey);
                }
                if ($success) {
                    wp_send_json_success([
                        'url'      => $url,
                        'name'     => $type === 'file' ? $name : '',
                        'headers'  => $result[0],
                        'type'     => $type,
                        'meta_key' => $metaKey
                    ]);
                }
            }

            throw new Exception(__('Oops! Something went wrong.', 'fluentformpro'), 400);
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()], $e->getCode());
        }
    }

    /**
     * Validate URL format
     * 
     * @param string $url The URL to validate
     * @throws Exception If URL is invalid
     */
    protected function validateUrl($url)
    {
        if (empty($url)) {
            throw new Exception(__('URL is required.', 'fluentformpro'), 400);
        }

        // Use WordPress URL validation
        if (!function_exists('wp_http_validate_url')) {
            require_once(ABSPATH . WPINC . '/http.php');
        }

        if (!wp_http_validate_url($url)) {
            throw new Exception(__('Invalid URL format.', 'fluentformpro'), 400);
        }

        // Parse URL to validate scheme
        $parsedUrl = parse_url($url);
        
        if (!$parsedUrl || !isset($parsedUrl['scheme'])) {
            throw new Exception(__('Invalid URL format.', 'fluentformpro'), 400);
        }

        // Only allow http and https schemes
        $allowedSchemes = ['http', 'https'];
        if (!in_array(strtolower($parsedUrl['scheme']), $allowedSchemes)) {
            throw new Exception(__('Only HTTP and HTTPS URLs are allowed.', 'fluentformpro'), 400);
        }
    }

    protected function formatOptions($options)
    {
        $headers = array_shift($options);

        $headers = array_map('trim', $headers);

        $formatOptions = [];
        foreach ($options as $key => $option) {
            if (count($headers) == count($option)) {
                $formatOptions[$key] = @array_combine($headers, $option);
            }
        }
        return array_filter($formatOptions);
    }

    protected function ensureUploadDirectory()
    {
        $path = wp_upload_dir()['basedir'] . FLUENTFORM_UPLOAD_DIR;

        if (!is_dir($path)) {
            mkdir($path, 0755);
            file_put_contents(
                wp_upload_dir()['basedir'] . FLUENTFORM_UPLOAD_DIR . '/.htaccess',
                file_get_contents(FLUENTFORMPRO_DIR_PATH . 'src/Stubs/htaccess.stub')
            );
        }

        if(!file_exists($path . '/index.php')) {
            file_put_contents(
                wp_upload_dir()['basedir'] . FLUENTFORM_UPLOAD_DIR . '/index.php',
                file_get_contents(FLUENTFORMPRO_DIR_PATH . 'src/Stubs/index.stub')
            );
        }

    }

    protected function saveFile($file, $formId, $metaKey)
    {
        $fileArray = $file->toArray();

        $path = wp_upload_dir()['basedir'] . FLUENTFORM_UPLOAD_DIR;

        $target = $path . '/' . $metaKey . '_' . $formId . '.csv';
        
        $realPath = realpath($path);
        $realTarget = realpath(dirname($target));
        if ($realTarget === false || strpos($realTarget, $realPath) !== 0) {
            return false;
        }

        if (move_uploaded_file($fileArray['tmp_name'], $target)) {
            return $target;
        }
        
        return false;
    }

    public function deleteDataSource()
    {
        $request = $this->app->request;

        $formId = intval($request->get('form_id'));
        $name = sanitize_text_field($request->get('name'));
        $metaKey = sanitize_file_name($request->get('meta_key'));

        if (empty($formId)) {
            wp_send_json_error(['message' => __('Invalid form ID.', 'fluentformpro')], 400);
            return;
        }

        if (empty($metaKey)) {
            wp_send_json_error(['message' => __('Invalid meta key.', 'fluentformpro')], 400);
            return;
        }

        if (strpos($metaKey, 'chained_select_') !== 0) {
            wp_send_json_error(['message' => __('Invalid meta key format.', 'fluentformpro')], 400);
            return;
        }

        $path = wp_upload_dir()['basedir'] . FLUENTFORM_UPLOAD_DIR;

        $target = $path . '/' . $metaKey . '_' . $formId . '.csv';
        
        $realPath = realpath($path);
        $realTarget = realpath(dirname($target));
        if ($realTarget === false || strpos($realTarget, $realPath) !== 0) {
            wp_send_json_error(['message' => __('Invalid file path.', 'fluentformpro')], 400);
            return;
        }

        $this->maybeDeleteDataSource($formId, $name, $metaKey, $target);

        wp_send_json_success([
            'message' => __('Data source deleted successfully.', 'fluentformpro'),
            'headers' => ['Parent', 'Child', 'Grand Child'],
            'type'    => 'file',
            'url'     => ''
        ]);

        // wpFluent()->table('fluentform_form_meta')
        //     ->where('meta_key', $metaKey)
        //     ->where('form_id', $formId)
        //     ->delete();

        // wp_send_json_success([
        //     'message' => 'Data source deleted successfully.',
        //     'headers' => ['Parent', 'Child', 'Grand Child']
        // ]);
    }

    protected function maybeDeleteDataSource($formId, $name, $metaKey, $target)
    {
        $form = (new Form($this->app))->fetchForm($formId);

        $fields = FormFieldsParser::getFields($form);

        $chainedSelects = array_filter($fields, function ($field) {
            return $field->element == 'chained_select';
        });

        $currentField = array_filter($chainedSelects, function ($field) use ($name) {
            return $field->attributes->name == $name;
        });

        $currentField = reset($currentField);

        if (count($chainedSelects) == 1) {
            file_exists($target) && @unlink($target);
        } else {
            $metaKeys = array_map(function ($field) {
                return $field->settings->data_source->meta_key;
            }, $chainedSelects);

            if (count(array_intersect($metaKeys, [$metaKey])) < 2) {
                file_exists($target) && @unlink($target);
            }
        }

        if ($currentField) {
            return $this->updateCurrentField($currentField, $form, $formId);
        }
    }

    protected function updateCurrentField($currentField, $form, $formId)
    {
        $formFields = json_decode($form->form_fields, true);

        $currentField->settings->data_source = [
            'url'      => '',
            'name'     => '',
            'headers'  => ['Parent', 'Child', 'Grand Child'],
            'type'     => 'file',
            'meta_key' => null
        ];

        foreach ($formFields['fields'] as $key => $field) {
            if (!empty($field['attributes']['name']) && $field['attributes']['name'] == $currentField->attributes->name) {
                $formFields['fields'][$key] = $currentField;
            }
        }

        wpFluent()->table('fluentform_forms')->where('id', $formId)->update([
            'form_fields' => json_encode($formFields)
        ]);
    }

    public function getOptionsForNextField($csvParser)
    {
        $result = [];

        $options = $this->formatOptions($this->getOptions($csvParser));

        if (@$_REQUEST['filter_options'] == 'all') {
            return $this->sendAllOptions($options);
        }

        $params = $this->app->request->get('params');

        foreach ($options as $option) {
            $statuses = [];

            foreach ($params as $param) {
                $statuses[] = $option[$param['key']] == $param['value'];
            }

            if (count(array_filter($statuses)) == count($statuses)) {
                $result[] = $option;
            }
        }

        $response = array_unique(
            array_column($result, str_replace('\\' , '' , $_REQUEST['target_field']))
        );

        wp_send_json_success(array_combine($response, $response));
    }

    protected function sendAllOptions($options)
    {
        $keys = array_keys($_REQUEST['keys']);

        $keysToMatch = [$keys[0] => $_REQUEST['keys'][$keys[0]]];

        $result = array_fill_keys($keys, []);

        foreach ($keys as $index => $key) {
            if ($index == 0) {
                $result[$key] = array_values(array_unique(
                    array_column($options, $key)
                ));
            } else {
                $result[$key] = $this->getNextMatchingOptions(
                    $keysToMatch,
                    $key,
                    $options,
                    $result
                );

                $nextKey = next($keys);
                $keysToMatch = array_merge(
                    $keysToMatch,
                    [$nextKey => $_REQUEST['keys'][$nextKey]]
                );
            }
        }

        wp_send_json($result, 200);
    }

    protected function getNextMatchingOptions($keysToMatch, $targetKey, $options, $result)
    {
        foreach ($options as $option) {
            if ($keysToMatch === array_intersect_key($option, $keysToMatch)) {
                if (!in_array($option[$targetKey], $result[$targetKey])) {
                    $result[$targetKey][] = $option[$targetKey];
                }
            }
        }

        return $result[$targetKey];
    }

    protected function getOptions($csvParser)
    {
        $metaKey = sanitize_file_name($_REQUEST['meta_key']);
        $formId = intval($_REQUEST['form_id']);

        if (empty($formId) || $formId <= 0) {
            return [];
        }

        if (empty($metaKey) || preg_match('/[^a-zA-Z0-9_-]/', $metaKey)) {
            return [];
        }

        if (strpos($metaKey, 'chained_select_') !== 0) {
            return [];
        }

        $path = wp_upload_dir()['basedir'] . FLUENTFORM_UPLOAD_DIR . '/';

        $file = $path . $metaKey . '_' . $formId . '.csv';
        
        $realPath = realpath($path);
        $realFile = realpath($file);
        if ($realFile === false || strpos($realFile, $realPath) !== 0) {
            return [];
        }
        
        if (!file_exists($file)) {
            return [];
        }
        
        $csvParser->load_data($data = file_get_contents($file));
        return $csvParser->parse($csvParser->find_delimiter());
    }
}
