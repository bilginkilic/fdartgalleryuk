<?php

namespace FluentFormPro\classes;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

use FluentForm\App\Helpers\Helper;
use FluentForm\App\Modules\Form\FormFieldsParser;
use FluentForm\App\Services\Browser\Browser;
use FluentForm\Framework\Helpers\ArrayHelper as Arr;
use FluentForm\App\Models\Form;

class DraftSubmissionsManager
{
    protected $app = null;

    protected static $tableName = 'fluentform_draft_submissions';

    protected static $cookieName = 'fluentform_step_form_hash';

    public function __construct($app)
    {
        $this->app = $app;
        $this->init();
    }

    public function init()
    {
        add_action('init', [$this, 'maybeLoadSavedProgress'], 99);
        add_action('fluentform/submission_inserted', [$this, 'delete'], 10, 3);
        add_filter('fluentform/form_fields_update', [$this, 'checkPartialSettings'], 10, 2);
        $this->registerAjaxHandlers();

        // after partial submission using Save & Resume button, handle notifications from here
        add_action('fluentform/saved_progress_submission_added', [$this, 'sendAdminEmailNotification'], 10, 4);
        add_action('fluentform/saved_progress_submission_updated', [$this, 'sendAdminEmailNotificationWhenUpdate'], 10, 4);
    }

    public function sendAdminEmailNotification($formData, $response, $insertId, $formId)
    {
        $this->sendPartialEntryEmail($formData, $insertId, $formId, 'create');
    }

    public function sendAdminEmailNotificationWhenUpdate($formData, $activeStep, $updatedId, $formId)
    {
        $this->sendPartialEntryEmail($formData, $updatedId, $formId, 'update');
    }

    private function sendPartialEntryEmail($formData, $entryId, $formId, $action)
    {
        $form = Form::find($formId);
        $actionVerb = $action == 'create' ? 'created' : 'updated';
        $clickedBtnNameAttr = $this->app->request->get('save_progress_btn_name');
        $saveProgressButton = $this->getSaveProgressButtonData($formId, $clickedBtnNameAttr);

        if (Arr::get($saveProgressButton, "settings.send_email_on_entry_{$action}")) {
            $emailAddress = Arr::get($saveProgressButton, "settings.on_{$action}_email_address", get_option('admin_email'));

            if (!is_email($emailAddress)) {
                wp_send_json_error([
                    'Error' => __("Please provide a valid email address to send entry {$action} email",
                        'fluentformpro')
                ], 423);
            }

            $entry = wpFluent()->table(static::$tableName)
                               ->where('id', $entryId)
                               ->first();

            if (!$entry) {
                wp_send_json_error([
                    'Error' => __('No partial entry found!', 'fluentformpro')
                ], 423);
            }

            $entry = (array)$entry;

            $emailSubject = Arr::get(
                $saveProgressButton,
                "settings.on_{$action}_email_subject",
                sprintf(
                    __('A Partial entry %s on form: %s and partial entry ID: %s', 'fluentformpro'),
                    $actionVerb,
                    '{form_name}',
                    '{partial_entry_id}'
                )
            );

            $emailBody = Arr::get(
                $saveProgressButton,
                "settings.on_{$action}_email_body",
                "<p>Hello,</p>
                <p>A partial submission has been {$actionVerb} on {form_name}. See the submission using the link below.</p>
                <p>
                    <a style='color: #ffffff; background-color: #3f9eff; text-decoration: none; font-weight: normal; font-style: normal; padding: 0.5rem 1rem; border-color: #0072ff;' href='{partial_entry_link}'>View partial submission</a>
                </p>
                <p>Thank you</p>"
            );

            $emailFormat = $this->processAdminEmail($emailAddress, $emailSubject, $emailBody, $form, $entryId);
            $emailFormat = apply_filters('fluentform/partial_entry_admin_email_format', $emailFormat, $entry,
                $form);

            do_action('fluentform/before_partial_entry_admin_email', $emailFormat, $entry, $form);

            $notifier = $this->app->make('FluentForm\App\Services\FormBuilder\Notifications\EmailNotification');
            $notifier->notify($emailFormat, $entry, $form);
        }
    }

    public static function boot($app)
    {
        return new static($app);
    }

    public function registerAjaxHandlers()
    {
        $this->app->addAdminAjaxAction('fluentform_step_form_save_data', [$this, 'saveWithCookie']);
        $this->app->addPublicAjaxAction('fluentform_step_form_save_data', [$this, 'saveWithCookie']);
        $this->app->addAdminAjaxAction('fluentform_step_form_get_data', [$this, 'getEntry']);
        $this->app->addPublicAjaxAction('fluentform_step_form_get_data', [$this, 'getEntry']);
        $this->app->addAdminAjaxAction('fluentform_save_form_progress_with_link', [$this, 'saveWithLink']);
        $this->app->addPublicAjaxAction('fluentform_save_form_progress_with_link', [$this, 'saveWithLink']);
        $this->app->addAdminAjaxAction('fluentform_email_progress_link', [$this, 'emailProgressLink']);
        $this->app->addPublicAjaxAction('fluentform_email_progress_link', [$this, 'emailProgressLink']);
        $this->app->addAdminAjaxAction('fluentform_get_form_state', [$this, 'getEntryFromLink']);
        $this->app->addPublicAjaxAction('fluentform_get_form_state', [$this, 'getEntryFromLink']);
    }

    public static function get($hash, $formId = false)
    {
        if ($formId) {
            return wpFluent()->table(static::$tableName)
                ->where('hash', $hash)
                ->where('form_id', $formId)
                ->first();
        }
        return wpFluent()->table(static::$tableName)->where('hash', $hash)->first();
    }

    public function getEntry()
    {
        $data = null;
        $entry = false;
        $attributes = $this->app->request->all();
        $formId = intval(Arr::get($attributes, 'form_id'));
        if ($hash = $this->getHash()) {
            $entry = $this->get($hash, $formId);
        }

        if (!$entry) {
            $this->getEntryFromLink();
        }

        if (!$entry && $userId = get_current_user_id()) {
            $entry = wpFluent()->table(static::$tableName)
                ->where('user_id', $userId)
                ->where('form_id', $formId)
                ->latest()
                ->first();
        }

        if ($entry) {
            $data['step_completed'] = (int)$entry->step_completed;
            $data['response'] = json_decode($entry->response, true);
            $form = wpFluent()->table('fluentform_forms')->where('id', $formId)->first();
            if ($form) {
                $fields = FormFieldsParser::getInputsByElementTypes($form, ['input_file', 'input_image']);
                foreach ($fields as $name => $field) {
                    if ($urls = Arr::get($data['response'], $name)) {
                        foreach ($urls as $index => $url) {
                            $data['response'][$name][$index] = [
                                "data_src" => $url,
                                "url" => \FluentForm\App\Helpers\Helper::maybeDecryptUrl($url)
                            ];
                        }
                    }
                }
            }
            unset(
                $data['response']['_wp_http_referer'],
                $data['response']['__fluent_form_embded_post_id'],
                $data['response']['_fluentform_' . $entry->form_id . '_fluentformnonce']
            );
        }

        wp_send_json($data, 200);
    }

    public function getEntryFromLink()
    {
        $this->verify();
        $data = null;
        $requestData = $this->app->request->all();
        $hash = sanitize_text_field(Arr::get($requestData, 'hash', ''));
        $formId = intval(Arr::get($requestData, 'form_id'));
        $entry = $this->get($hash, $formId);

        if (!$entry && $userId = get_current_user_id()) {
            $entry = wpFluent()->table(static::$tableName)
                ->where('user_id', $userId)
                ->where('form_id', $formId)
                ->first();
        }

        if ($entry) {
            // Check if this form has "use link once" enabled
            $form = Form::find($formId);
            $useOnce = false;

            // Parse form fields to find save progress buttons
            $fields = FormFieldsParser::getFields($form, true);
            foreach ($fields as $field) {
                if (Arr::get($field, 'element') == 'save_progress_button') {
                    if (Arr::get($field, 'settings.use_link_once', false)) {
                        $useOnce = true;
                        break;
                    }
                }
            }

            // If use once is enabled and the link has been used, reject it
            if ($useOnce && $entry->used) {
                wp_send_json_error([
                    'message' => __('This resume link has already been used and is no longer valid.', 'fluentformpro')
                ], 403);
                return;
            }

            // If use once is enabled, mark as used
            if ($useOnce) {
                wpFluent()->table(static::$tableName)
                    ->where('id', $entry->id)
                    ->update(['used' => 1]);
            }
            
            $data['step_completed'] = (int)$entry->step_completed;
            $data['response'] = json_decode($entry->response, true);
            $form = wpFluent()->table('fluentform_forms')->where('id', $formId)->first();
            if ($form) {
                $fields = FormFieldsParser::getInputsByElementTypes($form, ['input_file', 'input_image']);
                foreach ($fields as $name => $field) {
                    if ($urls = Arr::get($data['response'], $name)) {
                        foreach ($urls as $index => $url) {
                            $data['response'][$name][$index] = [
                                "data_src" => $url,
                                "url" => \FluentForm\App\Helpers\Helper::maybeDecryptUrl($url)
                            ];
                        }
                    }
                }
            }
            unset(
                $data['response']['_wp_http_referer'],
                $data['response']['__fluent_form_embded_post_id'],
                $data['response']['_fluentform_' . $entry->form_id . '_fluentformnonce']
            );
        }

        wp_send_json($data, 200);
    }

    public function saveWithLink()
    {
        $formData = $this->app->request->get();
        $formId = $this->app->request->get('form_id');
        $clickedBtnNameAttr = $this->app->request->get('save_progress_btn_name');
        $saveProgressButton = $this->getSaveProgressButtonData($formId, $clickedBtnNameAttr);
        if (!$saveProgressButton) {
            wp_send_json_error(
                [
                    'message' => __('No Save & Resume button found.', 'fluentformpro'),
                ]
            );
        }

        $successMessage = Arr::get($saveProgressButton,'settings.save_success_message');
        $this->verify();
        $hash = isset($formData['hash']) ? sanitize_text_field($formData['hash']) : -1;

        if ($hash == -1) {
            $hash = $this->getHash();
        }
        $form = Form::find($formId);
        $useOnce = false;

        $fields = FormFieldsParser::getFields($form, true);
        foreach ($fields as $field) {
            if (Arr::get($field, 'element') == 'save_progress_button') {
                if (Arr::get($field, 'settings.use_link_once', false)) {
                    $useOnce = true;
                    break;
                }
            }
        }

        // Reset the 'used' flag when saving/updating if needed
        $existingEntry = $this->get($hash);
        if ($existingEntry && $useOnce) {
            wpFluent()->table(static::$tableName)
                ->where('hash', $hash)
                ->update(['used' => 0]);
        }
        
        $this->saveState($hash);
        $sourceUrl = $this->getSavedLink($hash);
        wp_send_json_success(
            [
                'saved_url' => $sourceUrl,
                'hash'      => $hash,
                'message'   => $successMessage,
            ]
        );
    }

    public function saveWithCookie()
    {
        $hash = $this->getHash();
        $this->saveState($hash);
        wp_send_json_success();
    }

    public function saveState($hash)
    {
        $formData = $this->app->request->get();
        parse_str($formData['data'], $formData['data']);
        $isStepForm = true;

        if ($formData['active_step'] == 'no') {
            $formData['active_step'] = -1;
            $isStepForm = false;
        }

        $hasSaveProgressBtn = false;
        if (isset($formData['save_progress_btn_name'])) {
            $hasSaveProgressBtn = true;
        }

        $response = json_encode($formData['data']);
        $formId = $formData['form_id'];
        $exist = $this->get($hash);

        if (!$exist) {
            $browser = new Browser();
            $ipAddress = $this->app->request->getIp();
            $status = apply_filters_deprecated(
                'fluentform_disable_ip_logging',
                [
                    false,
                    $formId
                ],
                FLUENTFORM_FRAMEWORK_UPGRADE,
                'fluentform/disable_ip_logging',
                'Use fluentform/disable_ip_logging instead of fluentform_disable_ip_logging.'
            );
            if ((defined('FLUENTFROM_DISABLE_IP_LOGGING') && FLUENTFROM_DISABLE_IP_LOGGING) || apply_filters('fluentform/disable_ip_logging', $status, $formId)) {
                $ipAddress = false;
            }

            $response = [
                'form_id'        => $formData['form_id'],
                'hash'           => $hash,
                'response'       => $response,
                'source_url'     => site_url(Arr::get($formData, 'data._wp_http_referer')),
                'user_id'        => get_current_user_id(),
                'browser'        => $browser->getBrowser(),
                'device'         => $browser->getPlatform(),
                'ip'             => $ipAddress,
                'step_completed' => $formData['active_step'],
                'created_at'     => current_time('mysql'),
                'type'           => $isStepForm ? 'step_data' : 'saved_state_data',
                'updated_at'     => current_time('mysql')
            ];
            $insertId = wpFluent()->table(static::$tableName)->insertGetId($response);

            if ($isStepForm) {
                do_action_deprecated(
                    'fluentform_partial_submission_added',
                    [
                        $formData['data'],
                        $response,
                        $insertId,
                        $formId
                    ],
                    FLUENTFORM_FRAMEWORK_UPGRADE,
                    'fluentform/partial_submission_added',
                    'Use fluentform/partial_submission_added instead of fluentform_partial_submission_added.'
                );
                do_action('fluentform/partial_submission_added', $formData['data'], $response, $insertId, $formId);

                do_action_deprecated(
                    'fluentform_partial_submission_step_completed',
                    [
                        1,
                        $formData['data'],
                        $insertId,
                        $formId
                    ],
                    FLUENTFORM_FRAMEWORK_UPGRADE,
                    'fluentform/partial_submission_step_completed',
                    'Use fluentform/partial_submission_step_completed instead of fluentform_partial_submission_step_completed.'
                );
                do_action('fluentform/partial_submission_step_completed', 1, $formData['data'], $insertId, $formId);
            }

            if ($hasSaveProgressBtn) {
                do_action_deprecated(
                    'fluentform_saved_progress_submission_added',
                    [
                        $formData['data'],
                        $response,
                        $insertId,
                        $formId
                    ],
                    FLUENTFORM_FRAMEWORK_UPGRADE,
                    'fluentform/saved_progress_submission_added',
                    'Use fluentform/saved_progress_submission_added instead of fluentform_saved_progress_submission_added.'
                );
                do_action('fluentform/saved_progress_submission_added', $formData['data'], $response, $insertId, $formId);
            }
        }
        else {
            wpFluent()->table(static::$tableName)->where('id', $exist->id)->update([
                'response'       => $response,
                'step_completed' => $formData['active_step'],
                'updated_at'     => current_time('mysql')
            ]);
            if ($isStepForm) {
                do_action_deprecated(
                    'fluentform_partial_submission_step_completed',
                    [
                        $formData['active_step'],
                        $formData['data'],
                        $exist->id,
                        $formId
                    ],
                    FLUENTFORM_FRAMEWORK_UPGRADE,
                    'fluentform/partial_submission_step_completed',
                    'Use fluentform/partial_submission_step_completed instead of fluentform_partial_submission_step_completed.'
                );
                do_action('fluentform/partial_submission_step_completed', $formData['active_step'], $formData['data'], $exist->id, $formId);

                do_action_deprecated(
                    'fluentform_partial_submission_updated',
                    [
                        $formData['data'],
                        $formData['active_step'],
                        $exist->id,
                        $formId
                    ],
                    FLUENTFORM_FRAMEWORK_UPGRADE,
                    'fluentform/partial_submission_updated',
                    'Use fluentform/partial_submission_updated instead of fluentform_partial_submission_updated.'
                );
                do_action('fluentform/partial_submission_updated', $formData['data'], $formData['active_step'], $exist->id, $formId);
            }

            if ($hasSaveProgressBtn) {
                do_action_deprecated(
                    'fluentform_saved_progress_submission_updated',
                    [
                        $formData['data'],
                        $formData['active_step'],
                        $exist->id,
                        $formId
                    ],
                    FLUENTFORM_FRAMEWORK_UPGRADE,
                    'fluentform/saved_progress_submission_updated',
                    'Use fluentform/saved_progress_submission_updated instead of fluentform_saved_progress_submission_updated.'
                );
                do_action('fluentform/saved_progress_submission_updated', $formData['data'], $formData['active_step'], $exist->id, $formId);
            }
        }

        if ($isStepForm) {
            $this->setcookie($this->getCookieName($formId), $hash, $this->getExpiryDate());
        }
    }

    public function delete($insertId, $formData, $form)
    {
        $this->deleteSavedStateDraft($form, $formData);
        $this->deleteStepFormDraft($form);
    }

    protected function getHash()
    {
        return Arr::get(
            $_COOKIE, $this->getCookieName(), wp_generate_uuid4()
        );
    }

    protected function getCookieName($formId = false)
    {
        $formId = $formId ? $formId : $this->app->request->get('form_id');

        return static::$cookieName . '_' . $formId;
    }

    protected function getExpiryDate($previousTime = false)
    {
        $offset = 7 * 24 * 60 * 60;
        return ($previousTime) ? time() - $offset : time() + $offset;
    }

    protected function setCookie($name, $value, $expiryDate)
    {
        setcookie(
            $name,
            $value,
            $expiryDate,
            COOKIEPATH,
            COOKIE_DOMAIN
        );
    }

    protected function deleteCookie($formId = false)
    {
        $this->setcookie($this->getCookieName($formId), '', $this->getExpiryDate(true));
    }

    public function checkPartialSettings($fields, $formId)
    {
        $fieldsArray = \json_decode($fields, true);
        $isPartialEnabled = 'no';
        if (isset($fieldsArray['stepsWrapper'])) {
            $isPartialEnabled = Arr::get($fieldsArray, 'stepsWrapper.stepStart.settings.enable_step_data_persistency',
                'no');
        }
        self::migrate();
        Helper::setFormMeta($formId, 'step_data_persistency_status', $isPartialEnabled);

        $savedStateButton = array_filter($fieldsArray['fields'], function ($field) {
            return Arr::get($field, 'element') == 'save_progress_button';
        });
        if (!empty($savedStateButton)) {
            Helper::setFormMeta($formId, 'form_save_state_status', 'yes');
        } else {
            Helper::setFormMeta($formId, 'form_save_state_status', 'no');
        }
        return $fields;
    }

    /**
     * Migrate the table.
     *
     * @return void
     */
    public static function migrate()
    {
        global $wpdb;
        $charsetCollate = $wpdb->get_charset_collate();
        $table = $wpdb->prefix . static::$tableName;
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            $sql = "CREATE TABLE $table (
            `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            `form_id` INT UNSIGNED NULL,
            `hash` VARCHAR(255) NOT NULL,
            `type` VARCHAR(255) DEFAULT 'step_data',
            `step_completed` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `response` LONGTEXT NULL,
            `source_url` VARCHAR(255) NULL,
            `browser` VARCHAR(45) NULL,
            `device` VARCHAR(45) NULL,
            `ip` VARCHAR(45) NULL,
            `used` TINYINT(1) DEFAULT 0,
            `created_at` TIMESTAMP NULL,
            `updated_at` TIMESTAMP NULL,
             PRIMARY KEY (`id`) ) $charsetCollate;";
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        } else {
            // Check if 'used' column exists
            $row = $wpdb->get_results("SHOW COLUMNS FROM {$table} LIKE 'used'");
            if (empty($row)) {
                $wpdb->query("ALTER TABLE {$table} ADD COLUMN `used` TINYINT(1) DEFAULT 0 AFTER `ip`");
            }
        }
    }

    /**
     * @param $hash
     */
    private function getSavedLink($hash)
    {
        $postData = $this->app->request->get();
        $sourceUrl = sanitize_url($postData['source_url']);
        $slug = 'fluent_state';
        if (strpos($sourceUrl, '?') !== false) {
            $sourceUrl .= '&';
        } else {
            $sourceUrl .= '?';
        }
        $pattern = "/(?<={$slug}=).*$/";

        preg_match($pattern, $sourceUrl, $match);
        if (!empty($match)) {
            return str_replace($match[0], base64_encode($hash), $sourceUrl);
        }

        return $sourceUrl . "{$slug}=" . base64_encode($hash);
    }

    public function maybeLoadSavedProgress()
    {
        $key = isset($_GET['fluent_state']) ? sanitize_text_field($_GET['fluent_state']) : false;

        if (!$key) {
            return;
        }

        $key = base64_decode($key);
        $draftForm = \FluentFormPro\classes\DraftSubmissionsManager::get($key);
        if (!$draftForm) {
            return;
        }

        $form = Form::find($draftForm->form_id);
        $fields = FormFieldsParser::getFields($form, true);
        foreach ($fields as $field) {
            if (Arr::get($field, 'element') == 'save_progress_button') {
                if (
                    Arr::get($field, 'settings.save_resume_for_logged_in_user')
                    && !is_user_logged_in()
                ) {
                    return;
                }
            }
        }
    
        add_action('wp_enqueue_scripts', function () use ($key) {
            $vars = apply_filters('fluentform/save_progress_vars', [
                'source_url'          => home_url($_SERVER['REQUEST_URI']),
                'key'                 => $key,
                'nonce'               => wp_create_nonce(),
                'copy_button'         => sprintf("<img src='%s' >", fluentFormMix('img/copy.svg')),
                'copy_success_button' => sprintf("<img src='%s' >", fluentFormMix('img/check.svg')),
                'email_button'        => sprintf("<img src='%s' >", fluentFormMix('img/email.svg')),
            ]);
            wp_localize_script('form-save-progress', 'form_state_save_vars', $vars);

            add_filter('fluentform/global_form_vars', function($data) use ($key) {
                $data['hash'] = $key;
                return $data;
            }, 9, 1);
        });
    }
    
    public function emailProgressLink()
    {
        $this->verify();
        $requestData = $this->app->request->get();
        $hash = $requestData['hash'];
        $formId = intval($requestData['form_id']);
        $toEmail = trim($requestData['to_email']);
        if (!is_email($toEmail)) {
            wp_send_json_error([
                'Error' => __('Please provide a valid email address','fluentformpro')
            ], 423);
        }
        $link = $requestData['link'];
        
        $form = wpFluent()->table('fluentform_forms')->find($formId);
        $settings = FormFieldsParser::getElement($form, ['save_progress_button'], ['raw']);
        if (empty($settings) || !is_array($settings)) {
            wp_send_json_error([
                'Error' => __('Element Not Found.Please check again!', 'fluentformpro')
            ], 423);
        }
        $settings = array_pop($settings);
        
        $entry = $this->get($hash, $formId);
        if (!$entry && $userId = get_current_user_id()) {
            $entry = wpFluent()->table(static::$tableName)
                ->where('user_id', $userId)
                ->where('form_id', $formId)
                ->first();
        }
        $submittedData = null;
        if ($entry) {
            $submittedData['step_completed'] = (int)$entry->step_completed;
            $submittedData['response'] = json_decode($entry->response, true);
            unset(
                $submittedData['response']['_wp_http_referer'],
                $submittedData['response']['__fluent_form_embded_post_id'],
                $submittedData['response']['_fluentform_' . $entry->form_id . '_fluentformnonce']
            );
        }
        
        
        $emailFormat = $this->processEmail($settings, $form, $link, $toEmail);
        $emailFormat =  apply_filters_deprecated(
            'fluentform_email_form_resume_link_config',
            [
                $emailFormat,
                $submittedData,
                $requestData,
                $form
            ],
            FLUENTFORM_FRAMEWORK_UPGRADE,
            'fluentform/email_form_resume_link_config',
            'Use fluentform/email_form_resume_link_config instead of fluentform_email_form_resume_link_config.'
        );
        $emailFormat = apply_filters('fluentform/email_form_resume_link_config', $emailFormat, $submittedData, $requestData, $form);
        do_action_deprecated(
            'fluentform_email_form_resume_link_before_sent',
            [
                $emailFormat,
                $submittedData,
                $requestData,
                $form
            ],
            FLUENTFORM_FRAMEWORK_UPGRADE,
            'fluentform/email_form_resume_link_before_sent',
            'Use fluentform/email_form_resume_link_before_sent instead of fluentform_email_form_resume_link_before_sent.'
        );
        do_action('fluentform/email_form_resume_link_before_sent', $emailFormat, $submittedData, $requestData, $form);
        $notifier = $this->app->make(
            'FluentForm\App\Services\FormBuilder\Notifications\EmailNotification'
        );
        $notify = $notifier->notify($emailFormat, $submittedData, $form);
        
        if ($notify) {
            $message = sprintf(__('Email Successfully Sent to %s', 'fluentformpro'),$toEmail);
            $message = apply_filters_deprecated(
                'fluentform_email_resume_link_response',
                [
                    $message
                ],
                FLUENTFORM_FRAMEWORK_UPGRADE,
                'fluentform/email_resume_link_response',
                'Use fluentform/email_resume_link_response instead of fluentform_email_resume_link_response.'
            );
            wp_send_json_success([
                'response' => apply_filters('fluentform/email_resume_link_response', $message)
            ]);
        }
        
        wp_send_json_error([
            'Error' => __('Error Occurred while sending email', 'fluentformpro')
        ], 423);
    }

    private function deleteSavedStateDraft($form, $formData)
    {
        if (!isset($formData['__fluent_state_hash'])) {
            return;
        }
        $hash = sanitize_text_field($formData['__fluent_state_hash']);
        ob_start();
        $draft = $this->get($hash, $form->id);
        if ($draft) {
            wpFluent()->table(static::$tableName)
                ->where('hash', $hash)
                ->delete();
            ob_get_clean();
            do_action_deprecated(
                'fluentform_saved_progress_submission_deleted',
                [
                    $draft,
                    $form->id
                ],
                FLUENTFORM_FRAMEWORK_UPGRADE,
                'fluentform/saved_progress_submission_deleted',
                'Use fluentform/saved_progress_submission_deleted instead of fluentform_saved_progress_submission_deleted.'
            );
            do_action('fluentform/saved_progress_submission_deleted', $draft, $form->id);
        }
    }

    private function deleteStepFormDraft($form)
    {
        if (
            Helper::getFormMeta($form->id, 'step_data_persistency_status') != 'yes' &&
            !Helper::getFormMeta($form->id, 'conv_form_per_step_save', false)
        ) {
            return;
        }

        if ($hash = Arr::get($_COOKIE, $this->getCookieName($form->id))) {
            $draft = $this->get($hash, $form->id);
            if ($draft) {
                ob_start();
                wpFluent()->table(static::$tableName)
                    ->where('id', $draft->id)
                    ->delete();
                wpFluent()->table('fluentform_logs')
                    ->where('parent_source_id', $form->id)
                    ->where('source_id', $draft->id)
                    ->where('source_type', 'draft_submission_meta')
                    ->delete();

                $this->deleteCookie($form->id);
                $errors = ob_get_clean();
                do_action_deprecated(
                    'fluentform_partial_submission_deleted',
                    [
                        $draft,
                        $form->id
                    ],
                    FLUENTFORM_FRAMEWORK_UPGRADE,
                    'fluentform/partial_submission_step_completed',
                    'Use fluentform/partial_submission_step_completed instead of fluentform_partial_submission_deleted.'
                );
                do_action('fluentform/partial_submission_deleted', $draft, $form->id);
            }
        }

        if ($userId = get_current_user_id()) {
            wpFluent()->table(static::$tableName)
                ->where('user_id', $userId)
                ->where('form_id', $form->id)
                ->delete();
        }
    }

    private function processAdminEmail($emailAddress, $emailSubject, $emailBody, $form, $entryId)
    {
        if (false !== strpos($emailSubject, '{form_name}')) {
            $emailSubject = str_replace('{form_name}', $form->title, $emailSubject);
        }

        if (false !== strpos($emailSubject, '{partial_entry_id}')) {
            $emailSubject = str_replace('{partial_entry_id}', $entryId, $emailSubject);
        }

        if (false !== strpos($emailBody, '{form_name}')) {
            $emailBody = str_replace('{form_name}', $form->title, $emailBody);
        }

        if (false !== strpos($emailBody, '{partial_entry_link}')) {
            $link = admin_url("admin.php?page=fluent_forms&form_id={$form->id}&route=msformentries#/entries/{$entryId}");
            $emailBody = str_replace('{partial_entry_link}', $link, $emailBody);
            $emailBody = apply_filters('fluentform/partial_entry_admin_email_body', $emailBody, $form, $link);
        }

        return [
            'fromEmail'   => get_option('admin_email'),
            'fromName'    => get_bloginfo(),
            'asPlainText' => 'no',
            'message'     => $emailBody,
            'replyTo'     => get_option('admin_email'),
            'subject'     => $emailSubject,
            'sendTo'      => [
                'email' => $emailAddress,
            ]
        ];
    }
    
    private function processEmail($settings, $form, $link, $toEmail)
    {
        $emailSubject = Arr::get($settings, 'raw.settings.email_subject');
        $emailBody = Arr::get($settings, 'raw.settings.email_body', '<p>Hi there,</p><p>Please Continue To Your Submission Process of the Form by clicking on the link below.</p><p>{email_resume_link}</p><p>Thanks</p>');
        if (false !== strpos($emailSubject, '{form_name}')) {
            $emailSubject = str_replace('{form_name}', $form->title, $emailSubject);
        }
        
        if (false !== strpos($emailBody, '{email_resume_link}')) {
            $emailBody = str_replace('{email_resume_link}', $link, $emailBody);
            $emailBody = apply_filters_deprecated(
                'fluentform_email_resume_link_body',
                [
                    $emailBody,
                    $form,
                    $link
                ],
                FLUENTFORM_FRAMEWORK_UPGRADE,
                'fluentform/email_resume_link_body',
                'Use fluentform/email_resume_link_body instead of fluentform_email_resume_link_body.'
            );
            $emailBody = apply_filters('fluentform/email_resume_link_body', $emailBody, $form, $link);
        }
        if (false !== strpos($emailBody, '{form_name}')) {
            $emailBody = str_replace('{form_name}', $form->title, $emailBody);
        }
        return [
            'fromEmail'   => get_option('admin_email'),
            'fromName'    => get_bloginfo(),
            'asPlainText' => 'no',
            'message'     => $emailBody,
            'replyTo'     => get_option('admin_email'),
            'subject'     => $emailSubject,
            'sendTo'      => [
                'email' => $toEmail,
            ]
        ];
    }
    
    private function getSaveProgressButtonData($formId, $clickedBtnNameAttr)
    {
        $form = wpFluent()->table('fluentform_forms')->find($formId);
        $formFields = FormFieldsParser::getFields($form, true);

        // Function to search for the 'save_progress_button' inside fields or columns
        $findSaveProgressButton = function($fields) use ($clickedBtnNameAttr) {
            foreach ($fields as $field) {
                // Check if it's a 'save_progress_button' directly in the field
                if (
                    isset($field['element']) &&
                    $field['element'] === 'save_progress_button' &&
                    isset($field['attributes']['name']) &&
                    $field['attributes']['name'] === $clickedBtnNameAttr
                ) {
                    return $field;
                }

                // If it's a container, search inside the columns
                if (
                    isset($field['element']) &&
                    $field['element'] === 'container' &&
                    isset($field['columns'])
                ) {
                    foreach ($field['columns'] as $column) {
                        if (isset($column['fields']) && is_array($column['fields'])) {
                            foreach ($column['fields'] as $columnField) {
                                if (
                                    isset($columnField['element']) &&
                                    $columnField['element'] === 'save_progress_button' &&
                                    isset($columnField['attributes']['name']) &&
                                    $columnField['attributes']['name'] === $clickedBtnNameAttr
                                ) {
                                    return $columnField;
                                }
                            }
                        }
                    }
                }
            }

            return null;
        };

        // Find the save progress button in form fields
        $saveProgressButton = $findSaveProgressButton($formFields);

        // If no button is found, return false
        if (!$saveProgressButton) {
            return false;
        }

        return $saveProgressButton;
    }
    
    private function verify()
    {
        $nonce = $this->app->request->get('nonce');
        if (!wp_verify_nonce($nonce)) {
            $nonceMessage =  __('Nonce verification failed, please try again.', 'fluentform');
            $nonceMessage = apply_filters_deprecated(
                'fluentform_nonce_error',
                [
                    $nonceMessage
                ],
                FLUENTFORM_FRAMEWORK_UPGRADE,
                'fluentform/nonce_error',
                'Use fluentform/nonce_error instead of fluentform_nonce_error.'
            );
            $message = apply_filters('fluentform/nonce_error', $nonceMessage);
            
            wp_send_json_error([
                'message' => $message,
            ], 422);
        }
    }
}


