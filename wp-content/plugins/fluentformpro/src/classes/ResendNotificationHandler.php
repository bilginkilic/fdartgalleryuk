<?php

namespace FluentFormPro\classes;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}


use FluentForm\App\Helpers\Helper;
use FluentForm\App\Models\FormMeta;
use FluentForm\App\Modules\Form\FormDataParser;
use FluentForm\App\Modules\Form\FormFieldsParser;
use FluentForm\App\Services\FormBuilder\ShortCodeParser;
use FluentForm\App\Services\Integrations\GlobalNotificationManager;
use FluentForm\Framework\Helpers\ArrayHelper;
use FluentFormPro\Components\Post\PostFormHandler;
use FluentForm\App\Models\Form;
use FluentForm\App\Models\Submission;
use FluentForm\App\Hooks\Handlers\GlobalNotificationHandler;

class ResendNotificationHandler
{
    public function init()
    {
        add_action('wp_ajax_ffpro-resent-email-notification', array($this, 'resendEmail'));
        add_action('wp_ajax_ffpro_get_integration_feeds', array($this, 'getFeeds'));
        add_action('wp_ajax_ffpro_post_integration_feed_replay', array($this, 'replayFeed'));
        add_action('fluentform/run_actions_after_update_transaction_as_paid', [$this, 'resendNotificationOnPaidTransaction'], 10, 2);
    }

    public function resendEmail()
    {
        $requestData = wpFluentForm()->request->all();
        $notificationId = intval(ArrayHelper::get($requestData, 'notification_id'));
        $formId = intval(ArrayHelper::get($requestData, 'form_id'));
        $entryId = intval(ArrayHelper::get($requestData, 'entry_id'));

        $this->verify($formId);

        $entryIds = [];
        if (ArrayHelper::get($requestData, 'entry_ids')) {
            $entryIds = array_filter(ArrayHelper::get($requestData, 'entry_ids', []), 'intval');
        }

        $sendToType = sanitize_text_field(ArrayHelper::get($requestData, 'send_to_type'));
        $customRecipient = sanitize_text_field(ArrayHelper::get($requestData, 'send_to_custom_email'));

        $feed = wpFluent()->table('fluentform_form_meta')
                          ->where('id', $notificationId)
                          ->where('meta_key', 'notifications')
                          ->where('form_id', $formId)
                          ->first();

        if (!$feed) {
            wp_send_json_error([
                'message' => __('Sorry, No notification found!')
            ], 423);
        }

        $feed->value = \json_decode($feed->value, true);

        $form = wpFluent()->table('fluentform_forms')
                          ->where('id', $formId)
                          ->first();

        if ($entryId) {
            $this->resendEntryEmail($entryId, $feed, $sendToType, $customRecipient, $form);
        } else if ($entryIds) {
            foreach ($entryIds as $entry_id) {
                $this->resendEntryEmail($entry_id, $feed, $sendToType, $customRecipient, $form);
            }
        }

        wp_send_json_success([
            'message' => __('Notification successfully resent', 'fluentformpro')
        ], 200);
    }

    public function getFeeds()
    {
        $request = wpFluentForm()->request;
        $attributes = $request->all();
        
        // Sanitize request data
        $sanitizeMap = [
            'form_id' => 'intval',
            'entry_id' => 'intval',
        ];
        $attributes = fluentform_backend_sanitizer($attributes, $sanitizeMap);
        
        // Attributes are already sanitized by fluentform_backend_sanitizer above
        $formId = ArrayHelper::get($attributes, 'form_id');
        $entryId = ArrayHelper::get($attributes, 'entry_id');
        $this->verify($formId);

        wp_send_json_success([
            'feeds' => $this->getFormattedFeeds($formId, $entryId)
        ], 200);
    }

    public function replayFeed()
    {
        $request = wpFluentForm()->request;
        $attributes = $request->all();
        
        // Sanitize request data
        $sanitizeMap = [
            'logIds' => function($value) {
                if (is_array($value)) {
                    return array_map('intval', $value);
                }
                return [];
            },
            'multiple_actions' => 'rest_sanitize_boolean',
            'verify_condition' => 'sanitize_text_field',
        ];
        $attributes = fluentform_backend_sanitizer($attributes, $sanitizeMap);
        
        // Attributes are already sanitized by fluentform_backend_sanitizer above
        $requestLogIds = ArrayHelper::get($attributes, 'logIds');
        $this->handleReplayFeed($requestLogIds, $attributes);
    }

    private function handleReplayFeed($requestLogIds, $attributes = [])
    {
        // Attributes are already sanitized before being passed here
        $isMultipleActions = ArrayHelper::get($attributes, 'multiple_actions', false);
        $lastProcessedAction = null;

        add_action(
            'fluentform/integration_action_result',
            function($feed, $status, $message) use ($requestLogIds, $isMultipleActions, &$lastProcessedAction) {
                foreach ($requestLogIds as $logIds) {
                    $feedId = intval(ArrayHelper::get($feed, 'id'));
                    $requestFeedId = intval(ArrayHelper::get($logIds, 'feed_id'));

                    if ($feedId != $requestFeedId) {
                        continue;
                    }

                    $actionId = intval(ArrayHelper::get($logIds, 'action_id'));
                    $feed = FormMeta::where('id', $requestFeedId)->first();

                    // only if single action is processing
                    if ($feed && $feed->value) {
                        $feedValue = json_decode($feed->value, true);
                        $isDisabled = !ArrayHelper::isTrue($feedValue, 'enabled');
                        if ($isDisabled) {
                            if (!$isMultipleActions) {
                                $message = __('Feed is disabled', 'fluentformpro');
                                wp_send_json_error(['message' => $message], 423);
                            }
                        }
                    }

                    // Update scheduled actions
                    wpFluent()->table('ff_scheduled_actions')
                              ->where('id', $actionId)
                              ->update([
                                  'status' => $status,
                                  'note' => $message,
                                  'updated_at' => current_time('mysql')
                              ]);

                    // Store the last processed action
                    $lastProcessedAction = [
                        'status'  => $status,
                        'message' => $message
                    ];
                }
            },
            1,
            3
        );

        // Listen hook for 'ff_log_data' for update 'fluentcrm' response message
        // Fluentcrm integration fire 'ff_log_data' hook after integration success or failed
        if (count($requestLogIds) == 1) {
            add_action('ff_log_data', function ($data) use (&$lastProcessedAction, $requestLogIds) {
                foreach ($requestLogIds as $logIds) {
                    $requestFeedId = intval(ArrayHelper::get($logIds, 'feed_id'));
                    $feed = FormMeta::where('id', $requestFeedId)->first();
                    if ($feed && $feed->value && $feed->meta_key == 'fluentcrm_feeds') {
                        $feedValue = \json_decode($feed->value, true);
                        $isDisabled = !ArrayHelper::isTrue($feedValue, 'enabled');
                        if ($isDisabled) {
                            $message = __('Feed is disabled', 'fluentformpro');
                            wp_send_json_error(['message' => $message], 423);
                        }
                    }
                    $status = ArrayHelper::get($data, 'status');
                    $message = ArrayHelper::get($data, 'description');
                    // Store the last processed action
                    $lastProcessedAction = [
                        'status'  => $status,
                        'message' => $message
                    ];
                }
            });
        }

        foreach ($requestLogIds as $logIds) {
            if (!$isMultipleActions && ArrayHelper::get($logIds, 'integration_enabled') == 'false') {
                wp_send_json_error([
                    'message' => __('Please enable the integration', 'fluentformpro')
                ], 423);
            }

            $entryId = intval(ArrayHelper::get($logIds, 'entry_id'));
            $formId = intval(ArrayHelper::get($logIds, 'form_id'));
            $feedId = intval(ArrayHelper::get($logIds, 'feed_id'));

            // verify_condition is already sanitized by fluentform_backend_sanitizer
            $verifyCondition = ArrayHelper::get($attributes, 'verify_condition') == 'yes';

            $this->verify($formId);

            $form = wpFluent()->table('fluentform_forms')
                              ->where('id', $formId)
                              ->first();

            $feed = wpFluent()->table('fluentform_form_meta')
                              ->where('form_id', $formId)
                              ->where('id', $feedId)
                              ->first();

            if (!$feed) {
                $message = __('Invalid Feed ID', 'fluentformpro');

                if (!$isMultipleActions) {
                    wp_send_json_error([
                        'message' => $message
                    ], 423);
                }
            }

            $entry = $this->getEntry($entryId, $form);
            $formData = json_decode($entry->response, true);
            $parsedValue = json_decode($feed->value, true);

            $originalParsedValue = $parsedValue;

            $processedValues = $parsedValue;
            unset($processedValues['conditionals']);
            $processedValues = ShortCodeParser::parse($processedValues, $entryId, $formData);

            if ($verifyCondition) {
                $isMatched = (new GlobalNotificationManager(wpFluentForm()))->checkCondition($originalParsedValue,
                    $formData, $entryId);

                if (!$isMatched) {
                    $message = __('Conditions did not satisfy for this feed', 'fluentformpro');

                    if (!$isMultipleActions) {
                        wp_send_json_error([
                            'message' => $message
                        ], 423);
                    }
                }
            }

            if ($feed) {
                $item = [
                    'id'              => $feed->id,
                    'meta_key'        => $feed->meta_key,
                    'settings'        => $parsedValue,
                    'processedValues' => $processedValues
                ];

                $action = 'fluentform/integration_notify_' . $item['meta_key'];

                add_filter('fluentform/integration_notify_throw_error', '__return_true');

                if ($item['meta_key'] == 'postFeeds') {
                    (new PostFormHandler())->createPostFromFeed($processedValues, $entryId, $formData, $form);
                } else {
                    do_action($action, $item, $formData, $entry, $form);
                }
            }
        }

        // Prepare the final response
        if ($isMultipleActions) {
            wp_send_json_success([
                'message' => __('Multiple actions have been fired', 'fluentformpro')
            ], 200);
        } else {
            // For single action, we'll use the last processed action's status and message
            if ($lastProcessedAction) {
                $status = $lastProcessedAction['status'];
                $message = $lastProcessedAction['message'];

                if ($status === 'success') {
                    wp_send_json_success(['message' => $message], 200);
                } else {
                    wp_send_json_error(['message' => $message], 423);
                }
            } else {
                // If no action was processed
                wp_send_json_error(['message' => __('No action was processed', 'fluentformpro')], 423);
            }
        }
    }

    private function resendEntryEmail($entryId, $feed, $sendToType, $customRecipient, $form)
    {
        $parsedValue = $feed->value;
        $entry = wpFluent()->table('fluentform_submissions')
            ->where('id', $entryId)
            ->first();

        $formData = \json_decode($entry->response, true);
        ShortCodeParser::resetData();
        $processedValues = ShortCodeParser::parse($parsedValue, $entry, $formData, $form, false, $feed->meta_key);

        if ($sendToType == 'custom') {
            $processedValues['bcc'] = '';
            $processedValues['sendTo']['email'] = $customRecipient;
            $processedValues['sendTo']['type'] = 'custom';
        }

        $attachments = [];
        if (!empty($processedValues['attachments']) && is_array($processedValues['attachments'])) {
            foreach ($processedValues['attachments'] as $name) {
                $fileUrls = ArrayHelper::get($formData, $name);
                if ($fileUrls && is_array($fileUrls)) {
                    foreach ($fileUrls as $url) {
                        $filePath = str_replace(
                            site_url(''),
                            wp_normalize_path(untrailingslashit(ABSPATH)),
                            $url
                        );
                        if (file_exists($filePath)) {
                            $attachments[] = $filePath;
                        }
                    }
                }
            }
        }

        $uploadDir = wp_upload_dir();
        $mediaAttachments = ArrayHelper::get($processedValues, 'media_attachments');

        if (!empty($mediaAttachments) && is_array($mediaAttachments)) {
            foreach ($mediaAttachments as $file) {
                $fileUrl = ArrayHelper::get($file, 'url');
                if ($fileUrl && strpos($fileUrl, $uploadDir['baseurl']) === 0) {
                    $relativePath = str_replace($uploadDir['baseurl'], '', $fileUrl);
                    $filePath = wp_normalize_path($uploadDir['basedir'] . $relativePath);

                    if (file_exists($filePath)) {
                        $attachments[] = $filePath;
                    }
                }
            }
        }

        $attachments = apply_filters_deprecated(
            'fluentform_email_attachments',
            [
                $attachments,
                $processedValues,
                $formData,
                $entry,
                $form
            ],
            FLUENTFORM_FRAMEWORK_UPGRADE,
            'fluentform/email_attachments',
            'Use fluentform/email_attachments instead of fluentform_email_attachments.'
        );
        // let others to apply attachments
        $attachments = apply_filters('fluentform/email_attachments', $attachments, $processedValues, $formData, $entry, $form);

        $processedValues['attachments'] = $attachments;

        $enabledFeed = [
            'id'              => $feed->id,
            'meta_key'        => $feed->meta_key,
            'settings'        => $parsedValue,
            'processedValues' => $processedValues
        ];

        add_action('wp_mail_failed', function ($error) {
            $reason = $error->get_error_message();
            wp_send_json_error([
                'message' => __("Email Notification failed to sent. Reason: ", 'fluentformpro') . $reason
            ], 423);
        }, 10, 1);

        $notifier = wpFluentForm()->make(
            'FluentForm\App\Services\FormBuilder\Notifications\EmailNotification'
        );
        $notifier->notify($enabledFeed['processedValues'], $formData, $form, $entry->id);
    }

    private function verify($formId = false)
    {
        if (!$formId) {
            $formId = intval(wpFluentForm()->request->get('form_id'));
        }
        \FluentForm\App\Modules\Acl\Acl::verify('fluentform_entries_viewer', $formId);
    }

    private function getFormattedFeeds($formId, $entryId)
    {
        $activeFeeds = apply_filters_deprecated(
            'fluentform_global_notification_active_types',
            [
                [],
                $formId
            ],
            FLUENTFORM_FRAMEWORK_UPGRADE,
            'fluentform/global_notification_active_types',
            'Use fluentform/global_notification_active_types instead of fluentform_global_notification_active_types.'
        );
        // Let's find the feeds that are available for this form
        $feedKeys = apply_filters('fluentform/global_notification_active_types', $activeFeeds, $formId);

        if (!$feedKeys) {
            return [];
        }

        unset($feedKeys['user_registration_feeds']);
        unset($feedKeys['notifications']);

        $feedMetaKeys = array_keys($feedKeys);
        $feedMetaKeys[] = 'postFeeds';

        $feeds = wpFluent()->table('fluentform_form_meta')
            ->where('form_id', $formId)
            ->whereIn('meta_key', $feedMetaKeys)
            ->orderBy('id', 'ASC')
            ->get();

        if (!$feeds) {
            return [];
        }

        $formattedFeeds = [];
        foreach ($feeds as $feed) {
            $parsedValue = json_decode($feed->value, true);
            if (!$parsedValue) {
                continue;
            }

            $conditionSettings = ArrayHelper::get($parsedValue, 'conditionals');
            if (
                !$conditionSettings ||
                !ArrayHelper::isTrue($conditionSettings, 'status')
            ) {
                $hasCondition = false;
            } else {
                $hasCondition = true;
            }

            $feedName = ArrayHelper::get($parsedValue, 'name');
            if (!$feedName) {
                $feedName = ArrayHelper::get($parsedValue, 'feed_name');
            }

            $status = ArrayHelper::isTrue($parsedValue, 'enabled');
            if (!isset($parsedValue['enabled'])) {
                $status = ArrayHelper::isTrue($parsedValue, 'feed_status');
            }

            $integrationKey = ArrayHelper::get($feedKeys, $feed->meta_key);
            if ($integrationKey == 'slack' || $integrationKey == 'webhook' || $integrationKey == 'zapier') {
                $feedLink =  admin_url("admin.php?page=fluent_forms&form_id={$formId}&route=settings&sub_route=form_settings#/{$integrationKey}");
            } else {
                $feedLink =  admin_url("admin.php?page=fluent_forms&form_id={$formId}&route=settings&sub_route=form_settings#/all-integrations/{$feed->id}/{$integrationKey}");
            }

            $feedData = [
                'id'            => $feed->id,
                'has_condition' => $hasCondition,
                'name'          => $feedName,
                'enabled'       => $status,
                'provider'      => $feed->meta_key,
                'feed'          => $parsedValue,
                'feed_link'     => $feedLink,
            ];

            $scheduledActions = wpFluent()->table('ff_scheduled_actions')
                                          ->where('origin_id', $entryId)
                                          ->where('feed_id', $feed->id)
                                          ->get();

            if ($scheduledActions) {
                foreach ($scheduledActions as $action) {
                    $feedData['action_id'] = $action->id;
                    $feedData['action_status'] = $action->status;
                }
            }

            $feedData = apply_filters_deprecated(
                'fluentform_global_notification_feed_' . $feed->meta_key,
                [
                    $feedData,
                    $formId
                ],
                FLUENTFORM_FRAMEWORK_UPGRADE,
                'fluentform/global_notification_feed_' . $feed->meta_key,
                'Use fluentform/global_notification_feed_' . $feed->meta_key . ' instead of fluentform_global_notification_feed_' . $feed->meta_key
            );

            $formattedFeeds[] = apply_filters('fluentform/global_notification_feed_' . $feed->meta_key, $feedData, $formId);
        }

        return $formattedFeeds;
    }

    private function getEntry($id, $form)
    {
        $submission = wpFluent()->table('fluentform_submissions')->find($id);
        $formInputs = FormFieldsParser::getEntryInputs($form, ['admin_label', 'raw']);
        return FormDataParser::parseFormEntry($submission, $form, $formInputs);
    }

    public function resendNotificationOnPaidTransaction($newStatus, $transaction)
    {
        if ($newStatus !== 'paid') {
            return;
        }

        $formId = $transaction->form_id;
        $form = Form::find($formId);
        $submissionId = $transaction->submission_id;
        $submission = Submission::find($submissionId);
        $formData = \json_decode($submission->response, true);
        $hasIntegrationSentAlready = Helper::getSubmissionMeta($submissionId, '_ff_integration_sent') == 'yes';

        if ($form && $formData && !$hasIntegrationSentAlready) {
            add_filter('fluentform/user_registration_bypass_login', '__return_true');
            $notificationManager = new GlobalNotificationHandler(wpFluentForm());
            $notificationManager->globalNotify($transaction->submission_id, $formData, $form);
            Helper::SetSubmissionMeta($submissionId, '_ff_integration_sent', 'yes');
        }

        remove_filter('fluentform/user_registration_bypass_login', '__return_true');
    }
}
