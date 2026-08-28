<?php

namespace FluentFormPro\Payments\PaymentMethods\PayPal\API;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

use FluentForm\Framework\Helpers\ArrayHelper;
use FluentFormPro\Payments\PaymentHelper;
use FluentFormPro\Payments\PaymentMethods\PayPal\PayPalProcessor;
use FluentFormPro\Payments\PaymentMethods\PayPal\PayPalSettings;

class IPN
{
    public function init()
    {
        /*
        * paypal specific action hooks
        */
        // normal onetime payment process
        add_action('fluentform/ipn_paypal_action_web_accept', array($this, 'updatePaymentStatusFromIPN'), 10, 3);
        // Process PayPal subscription sign ups
        add_action('fluentform/ipn_paypal_action_subscr_signup', array($this, 'processSubscriptionSignup'), 10, 3);
        // Process PayPal subscription sign ups
        add_action('fluentform/ipn_paypal_action_subscr_payment', array($this, 'processSubscriptionPayment'), 10, 3);
        // Process PayPal subscription cancel
        add_action('fluentform/ipn_paypal_action_subscr_cancel', array($this, 'processSubscriptionPaymentCancel'), 10, 3);
        // Process PayPal subscription end of term notices
        add_action('fluentform/ipn_paypal_action_subscr_eot', array($this, 'processSubscriptionPaymentEot'), 10, 3);
        // Process PayPal payment failed
        add_action('fluentform/ipn_paypal_action_subscr_failed', array($this, 'processSubscriptionFailed'), 10, 3);
    }

    public function verifyIPN()
    {
        status_header(200);
        // Check the request method is POST
        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] != 'POST') {
            return false;
        }

        $submissionId = intval(ArrayHelper::get($_GET, 'submission_id'));

        // Set initial post data to empty string
        $post_data = '';

        // Fallback just in case post_max_size is lower than needed
        if (ini_get('allow_url_fopen')) {
            $post_data = file_get_contents('php://input');
        } else {
            // If allow_url_fopen is not enabled, then make sure that post_max_size is large enough
            ini_set('post_max_size', '12M');
        }
        // Start the encoded data collection with notification command
        $encoded_data = 'cmd=_notify-validate';

        // Get current arg separator
        $arg_separator = ini_get('arg_separator.output');

        // Verify there is a post_data
        if ($post_data || strlen($post_data) > 0) {
            // Append the data
            $encoded_data .= $arg_separator . $post_data;
        } else {
            // Check if POST is empty
            if (empty($_POST)) {
                PaymentHelper::log([
                    'status'      => 'error',
                    'title'       => __('Invalid PayPal IPN Notification Received. No Posted Data', 'fluentformpro'),
                    'description' => json_encode($_GET)
                ]);
                // Nothing to do
                return false;
            } else {
                // Loop through each POST
                foreach ($_POST as $key => $value) {
                    // Encode the value and append the data
                    $encoded_data .= $arg_separator . "$key=" . urlencode($value);
                }
            }
        }
   
        try {
            $encoded_data = $this->utf8_encode_custom(urldecode($encoded_data));
            // Convert collected post data to an array
            parse_str($encoded_data, $encoded_data_array);
        } catch (\Exception $e) {
            //
        }
        
        foreach ($encoded_data_array as $key => $value) {
            if (false !== strpos($key, 'amp;')) {
                $new_key = str_replace('&amp;', '&', $key);
                $new_key = str_replace('amp;', '&', $new_key);
                unset($encoded_data_array[$key]);
                $encoded_data_array[$new_key] = $value;
            }
        }
      

        // Check if $post_data_array has been populated
        if (!is_array($encoded_data_array) && !empty($encoded_data_array)) {
            PaymentHelper::log([
                'status'      => 'error',
                'title'       => __('Invalid PayPal IPN Notification Received', 'fluentformpro'),
                'description' => json_encode($_POST)
            ]);
            return false;
        }

        $encoded_data_array = apply_filters_deprecated(
            'fluentform_process_paypal_ipn_data',
            [
                $encoded_data_array
            ],
            FLUENTFORM_FRAMEWORK_UPGRADE,
            'fluentform/process_paypal_ipn_data',
            'Use fluentform/process_paypal_ipn_data instead of fluentform_process_paypal_ipn_data.'
        );

        $encoded_data_array = apply_filters('fluentform/process_paypal_ipn_data', $encoded_data_array);
        $defaults = array(
            'txn_type'       => '',
            'payment_status' => '',
            'custom'         => ''
        );

        $encoded_data_array = wp_parse_args($encoded_data_array, $defaults);
        $customJson = ArrayHelper::get($encoded_data_array, 'custom');
        $customArray = json_decode($customJson, true);
        $paymentSettings = PayPalSettings::getSettings();

        if (!$submissionId) {
            if (!$customArray || empty($customArray['fs_id'])) {
                $submissionId = false;
            } else {
                $submissionId = intval($customArray['fs_id']);
            }
        }

        $submission = false;
        if ($submissionId) {
            $submission = wpFluent()->table('fluentform_submissions')->find($submissionId);
        }

        if (!$submission) {
            PaymentHelper::log([
                'status'      => 'error',
                'title'       => __('Invalid PayPal IPN Notification Received. No Submission Found', 'fluentformpro'),
                'description' => json_encode($encoded_data_array)
            ]);
            return false;
        }

        $ipnVerified = false;
        if ($paymentSettings['disable_ipn_verification'] != 'yes') {

            $validate_ipn = wp_unslash($_POST); // WPCS: CSRF ok, input var ok.
            $validate_ipn['cmd'] = '_notify-validate';

            // Send back post vars to paypal.
            $params = array(
                'body'        => $validate_ipn,
                'timeout'     => 60,
                'httpversion' => '1.1',
                'compress'    => false,
                'decompress'  => false,
                'user-agent'  => 'FluentForm/' . FLUENTFORMPRO_VERSION,
            );

            // Post back to get a response.
            $response = wp_safe_remote_post((!PayPalSettings::isLive()) ? 'https://www.sandbox.paypal.com/cgi-bin/webscr' : 'https://www.paypal.com/cgi-bin/webscr', $params);
            if (!is_wp_error($response) && $response['response']['code'] >= 200 && $response['response']['code'] < 300 && strstr($response['body'], 'VERIFIED')) {
                PaymentHelper::log([
                    'status' => 'success',
                    'title'  => __('Received valid response from PayPal IPN', 'fluentformpro'),
                    'description' => json_encode($encoded_data_array)
                ], $submission);
                $ipnVerified = true;
            } else {
                PaymentHelper::log([
                    'status'      => 'error',
                    'title'       => __('PayPal IPN verification Failed', 'fluentformpro'),
                    'description' => json_encode($encoded_data_array)
                ], $submission);
                return false;
            }
        }

        if (has_action('fluentform/ipn_paypal_action_' . $encoded_data_array['txn_type'])) {
            do_action_deprecated(
                'fluentform_ipn_paypal_action_' . $encoded_data_array['txn_type'],
                [
                    $encoded_data_array,
                    $submissionId,
                    $submission,
                    $ipnVerified
                ],
                FLUENTFORM_FRAMEWORK_UPGRADE,
                'fluentform/ipn_paypal_action_' . $encoded_data_array['txn_type'],
                'Use fluentform/ipn_paypal_action_' . $encoded_data_array['txn_type'] . ' instead of fluentform_ipn_paypal_action_' . $encoded_data_array['txn_type']
            );
            // Allow PayPal IPN types to be processed separately
            do_action('fluentform/ipn_paypal_action_' . $encoded_data_array['txn_type'], $encoded_data_array, $submissionId, $submission, $ipnVerified);
        } else {
            do_action_deprecated(
                'fluentform_ipn_paypal_action_web_accept',
                [
                    $encoded_data_array,
                    $submissionId,
                    $submission,
                    $ipnVerified
                ],
                FLUENTFORM_FRAMEWORK_UPGRADE,
                'fluentform/ipn_paypal_action_web_accept',
                'Use fluentform/ipn_paypal_action_web_accept instead of fluentform_ipn_paypal_action_web_accept.'
            );
            // Fallback to web accept just in case the txn_type isn't present
            do_action('fluentform/ipn_paypal_action_web_accept', $encoded_data_array, $submissionId, $submission, $ipnVerified);
        }
        
        exit;
    }

    public function updatePaymentStatusFromIPN($data, $submissionId, $submission)
    {
        (new PayPalProcessor())->handleWebAcceptPayment($data, $submissionId);
    }

    public function processSubscriptionSignup($data, $submissionId, $submission)
    {
        $subscription = $this->findSubscriptionBySubmissionId($submissionId);

        if (!$subscription) {
            return;
        }

        $this->updateSubmission($submissionId, [
            'payment_status' => 'paid',
        ]);

        $submission = $this->findSubmission($submissionId);

        $subscriptionStatus = 'active';
        if ($subscription->trial_days && $subscription->status == 'pending') {
            $subscriptionStatus = 'trialling';
        }

        $this->updateSubscription($subscription->id, [
            'vendor_response'        => maybe_serialize($data),
            'vendor_customer_id'     => $data['payer_id'],
            'vendor_subscription_id' => $data['subscr_id']
        ]);

        $subscription = fluentFormApi('submissions')->getSubscription($subscription->id);

        $paypalProcessor = (new PayPalProcessor());
        $paypalProcessor->setSubmissionId($submission->id);
        $paypalProcessor->updateSubscriptionStatus($subscription, $subscriptionStatus);

        if ($subscription->status == 'pending') {
            $logData = [
                'parent_source_id' => $submission->form_id,
                'source_type'      => 'submission_item',
                'source_id'        => $submission->id,
                'component'        => 'Subscription',
                'status'           => 'success',
                'title'            => __('PayPal Subscription Complete', 'fluentformpro'),
                'description'      => __('PayPal recurring payment subscription successfully initiated', 'fluentformpro')
            ];

            do_action('fluentform/log_data', $logData);
        }

        // PayPal sometimes send subscr_payment after few second and use see empty data
        // So We have to entry the payment here
        if (isset($data['from_subscr_signup'])) {
            // Transaction will be added via processSubscriptionPayment
            return true;
        }

        // Let's check if the transaction actually added or not in the meantime
        $transaction = wpFluent()->table('fluentform_transactions')
            ->where('submission_id', $submission->id)
            ->first();

        if ($transaction) {
            return true; // transaction has been added already
        }

        // We have to make a dummy transaction for now with the available data
        $amount = ArrayHelper::get($data, 'amount3');
        if (ArrayHelper::get($data, 'amount1')) {
            // this for with signup fee
            $amount = ArrayHelper::get($data, 'amount1');
        }

        $amount = intval($amount * 100);

        $paymentData = [
            'form_id'          => $submission->form_id,
            'submission_id'    => $submission->id,
            'subscription_id'  => $subscription->id,
            'payer_email'      => ArrayHelper::get($data, 'payer_email'),
            'payer_name'       => trim(ArrayHelper::get($data, 'first_name') . ' ' . ArrayHelper::get($data, 'last_name')),
            'transaction_type' => 'subscription',
            'payment_method'   => 'paypal',
            'charge_id'        => 'temp_' . $data['subscr_id'], // We don't know the txn id yet so we are adding the subscr_id
            'payment_total'    => $amount,
            'status'           => 'paid',
            'currency'         => $submission->currency,
            'payment_mode'     => (PayPalSettings::isLive($submission->form_id)) ? 'live' : 'test',
            'payment_note'     => maybe_serialize($data)
        ];

        if ($submission->user_id) {
            $paymentData['user_id'] = $submission->user_id;
        }

        $paypalProcessor->maybeInsertSubscriptionCharge($paymentData);
    }

    public function processSubscriptionPayment($vendorData, $submissionId, $submission)
    {
        $subscription = $this->findSubscriptionBySubmissionId($submissionId);

        if (!$submission || !$subscription) {
            return false; // maybe the submission has been deleted
        }

        if ($subscription->status == 'pending') {
            $vendorData['from_subscr_signup'] = 'yes';
            // somehow the subscr_signup hook could not be fired yet. So let's make it active manually
            $this->processSubscriptionSignup($vendorData, $submissionId, $submission);
            $subscription = $this->findSubscriptionBySubmissionId($submissionId);
        }

        $paymentStatus = strtolower($vendorData['payment_status']);
        if ($paymentStatus == 'completed') {
            $paymentStatus = 'paid';
        }
        // Fallback for payment_total
        // For non USD currency 'payment_gross' get empty(''), and total payment is stored in 'mc_gross'
        if (!is_numeric(ArrayHelper::get($vendorData, 'payment_gross'))) {
            $vendorData['payment_gross'] = ArrayHelper::get($vendorData, 'mc_gross');
        }
        if (!is_numeric(ArrayHelper::get($vendorData, 'payment_gross'))) {
            return false; // invalid payment total
        }
        $paymentData = [
            'form_id'          => $submission->form_id,
            'submission_id'    => $submission->id,
            'subscription_id'  => $subscription->id,
            'payer_email'      => ArrayHelper::get($vendorData, 'payer_email'),
            'payer_name'       => trim(ArrayHelper::get($vendorData, 'first_name') . ' ' . ArrayHelper::get($vendorData, 'last_name')),
            'transaction_type' => 'subscription',
            'payment_method'   => 'paypal',
            'charge_id'        => $vendorData['txn_id'],
            'payment_total'    => intval($vendorData['payment_gross'] * 100),
            'status'           => $paymentStatus,
            'currency'         => $submission->currency,
            'payment_mode'     => (PayPalSettings::isLive($submission->form_id)) ? 'live' : 'test',
            'payment_note'     => maybe_serialize($vendorData)
        ];

        if ($submission->user_id) {
            $paymentData['user_id'] = $submission->user_id;
        }

        // find the pending transaction
        $pendingTransaction = wpFluent()->table('fluentform_transactions')
            ->whereNull('charge_id')
            ->where('submission_id', $submissionId)
            ->first();

        $payPalProcessor = new PayPalProcessor();
        $payPalProcessor->setSubmissionId($submissionId);

        if ($pendingTransaction) {
            $payPalProcessor->updateTransaction($pendingTransaction->id, $paymentData);
            $transactionId = $pendingTransaction->id;
        } else {
            $transactionId = $payPalProcessor->maybeInsertSubscriptionCharge($paymentData);
        }

        $logData = [
            'parent_source_id' => $submission->form_id,
            'source_type'      => 'submission_item',
            'source_id'        => $submission->id,
            'component'        => 'Subscription',
            'status'           => 'success',
            'title'            => __('PayPal Subscription Payment', 'fluentformpro'),
            'description'      => __('Congratulations! New Payment has been received from your subscription', 'fluentformpro')
        ];

        do_action('fluentform/log_data', $logData);

        do_action_deprecated(
            'fluentform_form_submission_activity_start',
            [
                $submission->form_id
            ],
            FLUENTFORM_FRAMEWORK_UPGRADE,
            'fluentform/form_submission_activity_start',
            'Use fluentform/form_submission_activity_start instead of fluentform_form_submission_activity_start.'
        );

        do_action('fluentform/form_submission_activity_start', $submission->form_id);

        $updatedSubscription = $this->findSubscription($subscription->id);

        $isNewPayment = $subscription->bill_count !== $updatedSubscription->bill_count;

        $payPalProcessor->recalculatePaidTotal();

        if($pendingTransaction) {
            $returnData = $payPalProcessor->completePaymentSubmission(false);
        }

        if ($isNewPayment) {
            do_action_deprecated(
                'fluentform_subscription_payment_received',
                [
                    $submission,
                    $updatedSubscription,
                    $submission->form_id,
                    $subscription
                ],
                FLUENTFORM_FRAMEWORK_UPGRADE,
                'fluentform/subscription_payment_received',
                'Use fluentform/subscription_payment_received instead of fluentform_subscription_payment_received.'
            );
            do_action('fluentform/subscription_payment_received', $submission, $updatedSubscription, $submission->form_id, $subscription);

            do_action_deprecated(
                'fluentform_subscription_payment_received_paypal',
                [
                    $submission,
                    $updatedSubscription,
                    $submission->form_id,
                    $subscription
                ],
                FLUENTFORM_FRAMEWORK_UPGRADE,
                'fluentform/subscription_payment_received_paypal',
                'Use fluentform/subscription_payment_received_paypal instead of fluentform_subscription_payment_received_paypal.'
            );
            do_action('fluentform/subscription_payment_received_paypal', $submission, $updatedSubscription, $submission->form_id, $subscription);
        }

        if ($updatedSubscription->bill_count === 1) {
            PaymentHelper::maybeFireSubmissionActionHok($submission);
            $transaction = $this->findTransaction($transactionId);

            do_action_deprecated(
                'fluentform_form_payment_success',
                [
                    $submission,
                    $transaction,
                    $submission->form_id,
                    false
                ],
                FLUENTFORM_FRAMEWORK_UPGRADE,
                'fluentform/form_payment_success',
                'Use fluentform/form_payment_success instead of fluentform_form_payment_success.'
            );

            do_action('fluentform/form_payment_success', $submission, $transaction, $submission->form_id, false);
        }
    }

    public function processSubscriptionPaymentCancel($vendorData, $submissionId, $submission)
    {
        $subscription = $this->findSubscriptionBySubmissionId($submissionId);

        if ($subscription) {
            return;
        }

        $paypalProcess = new PayPalProcessor();
        $paypalProcess->setSubmissionId($submissionId);
        $paypalProcess->updateSubscriptionStatus($subscription, 'cancelled');
    }

    public function processSubscriptionPaymentEot($vendorData, $submissionId, $submission)
    {
        $subscription = $this->findSubscriptionBySubmissionId($submissionId);

        if (!$subscription) {
            return;
        }

        $paypalProcess = new PayPalProcessor();
        $paypalProcess->setSubmissionId($submissionId);
        $paypalProcess->updateSubscriptionStatus($subscription, 'completed');
    }

    public function processSubscriptionFailed($vendorData, $submissionId, $submission)
    {
        $subscription = $this->findSubscriptionBySubmissionId($submissionId);

        if (!$subscription) {
            return;
        }

        $paypalProcess = new PayPalProcessor();
        $paypalProcess->setSubmissionId($submissionId);
        $paypalProcess->updateSubscriptionStatus($subscription, 'cancelled');
    }

    private function findSubscriptionBySubmissionId($submissionId)
    {
        return wpFluent()->table('fluentform_subscriptions')
            ->where('submission_id', $submissionId)
            ->first();
    }

    private function findSubscription($subscriptionId)
    {
        return wpFluent()->table('fluentform_subscriptions')
            ->where('id', $subscriptionId)
            ->first();
    }

    private function findSubmission($id)
    {
        return wpFluent()->table('fluentform_submissions')
            ->where('id', $id)
            ->first();
    }

    private function updateSubscription($id, $data)
    {
        $data['updated_at'] = current_time('mysql');

        wpFluent()->table('fluentform_subscriptions')
            ->where('id', $id)
            ->update($data);
    }

    private function updateSubmission($id, $data)
    {
        $data['updated_at'] = current_time('mysql');

        wpFluent()->table('fluentform_submissions')
            ->where('id', $id)
            ->update($data);
    }

    public function findTransaction($id, $transactionType = 'subscription')
    {
        return wpFluent()->table('fluentform_transactions')
            ->where('id', $id)
            ->where('transaction_type', $transactionType)
            ->first();
    }
    
    /**
     * With the function it is now possible to replace
     * all utf8_encode calls with the new utf8_encode_custom function to avoid the deprecation notice
     * @return false|string
     */
    public function utf8_encode_custom($s)
    {
        $s .= $s;
        $len = \strlen($s);
        for ($i = $len >> 1, $j = 0; $i < $len; ++$i, ++$j) {
            switch (true) {
                case $s[$i] < "\x80": $s[$j] = $s[$i]; break;
                case $s[$i] < "\xC0": $s[$j] = "\xC2"; $s[++$j] = $s[$i]; break;
                default: $s[$j] = "\xC3"; $s[++$j] = \chr(\ord($s[$i]) - 64); break;
            }
        }
        return substr($s, 0, $j);
    }
}
