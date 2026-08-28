<?php

namespace FluentFormPro\Payments\PaymentMethods\AuthorizeNet\API;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

use FluentFormPro\Payments\PaymentMethods\AuthorizeNet\AuthorizeNetSettings;

class IPN
{
    /**
     * Verify incoming webhook from Authorize.Net
     * This method is called via REST API endpoint, not on every page load
     *
     * @return true|\WP_Error True on success, WP_Error on failure
     */
    public function verifyIPN()
    {
        // Check if the request method is POST
        if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            return new \WP_Error('invalid_method', __('Invalid request method. Only POST is allowed.', 'fluentformpro'));
        }

        // Get the raw POST data
        $post_data = file_get_contents('php://input');

        if (empty($post_data)) {
            return new \WP_Error('empty_post_data', __('Empty POST data received.', 'fluentformpro'));
        }

        // Get all headers in a case-insensitive manner
        $headers = array_change_key_case(getallheaders(), CASE_LOWER);

        // Check for Authorize.Net signature header
        if (!isset($headers['x-anet-signature'])) {
            return new \WP_Error('missing_signature', __('Missing X-ANET-Signature header.', 'fluentformpro'));
        }

        $signatureHeader = $headers['x-anet-signature'];

        // Retrieve the signature from the header
        $reqSignatureKey = $signatureHeader;

        // Remove the 'sha512=' prefix if present
        if (strpos($reqSignatureKey, 'sha512=') === 0) {
            $reqSignatureKey = substr($reqSignatureKey, strlen('sha512='));
        }

        // Get the merchant signature key from settings
        $mrchntSignatureKey = AuthorizeNetSettings::getWebhookSignatureKey();
        if (empty($mrchntSignatureKey)) {
            return new \WP_Error('missing_signature_key', __('Webhook signature key is not configured in settings.', 'fluentformpro'));
        }

        // Generate the same HMAC-SHA512 hash using the webhook notification's body and the merchant's Signature Key
        $generated_hash = hash_hmac('sha512', $post_data, $mrchntSignatureKey);

        // Compare the signatures securely using hash_equals
        if (!hash_equals(strtolower($generated_hash), strtolower($reqSignatureKey))) {
            return new \WP_Error('signature_mismatch', __('Webhook signature verification failed. Invalid signature.', 'fluentformpro'));
        }

        // Decode the JSON payload
        $data = json_decode($post_data);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new \WP_Error('invalid_json', __('Invalid JSON payload: ', 'fluentformpro') . json_last_error_msg());
        }

        // Check if the 'eventType' property exists in the payload
        if (!property_exists($data, 'eventType')) {
            return new \WP_Error('missing_event_type', __('Webhook payload missing eventType property.', 'fluentformpro'));
        }

        // Handle the webhook event
        $this->handleIpn($data);

        // Return success
        return true;
    }

    /**
     * Handle the IPN event by dispatching to appropriate action hook
     *
     * @param object $data Webhook payload data
     * @return void
     */
    protected function handleIpn($data)
    {
        // Check if payload and entityName exist
        if (!isset($data->payload->entityName)) {
            return;
        }

        $entityName = $data->payload->entityName;

        if (has_action('fluentform/handle_authorizenet_' . $entityName . '_ipn')) {
            do_action('fluentform/handle_authorizenet_' . $entityName . '_ipn', $data);
        }
    }
}
