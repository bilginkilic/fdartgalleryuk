<?php

namespace FluentFormPro\Payments\PaymentMethods\Square;

use FluentForm\App\Helpers\Helper;
use FluentForm\Framework\Helpers\ArrayHelper;
use FluentFormPro\Payments\PaymentHelper;
use FluentFormPro\Payments\PaymentMethods\BaseProcessor;


class SquareProcessor extends BaseProcessor
{
    
    public $method = 'square';
    
    protected $form;
    
    public function init()
    {
        add_action('fluentform/process_payment_square_hosted', array($this, 'handlePaymentAction'), 10, 6);
        add_action('fluentform/payment_frameless_' . $this->method, array($this, 'handleSessionRedirectBack'));
        add_filter('fluentform/validate_payment_items_' . $this->method, [$this, 'validateSubmittedItems'], 10, 4);
    }

    public function handlePaymentAction($submissionId, $submissionData, $form, $methodSettings, $hasSubscriptions, $totalPayable)
    {
        $this->setSubmissionId($submissionId);
        $this->form = $form;
        $submission = $this->getSubmission();

        if ($hasSubscriptions) {
            do_action('fluentform/log_data', [
                'parent_source_id' => $submission->form_id,
                'source_type'      => 'submission_item',
                'source_id'        => $submission->id,
                'component'        => 'Payment',
                'status'           => 'info',
                'title'            => __('Skip Subscription Item', 'fluentformpro'),
                'description'      => __('Square does not support subscriptions right now!', 'fluentformpro')
            ]);
        }
        
        $uniqueHash = md5($submission->id . '-' . $form->id . '-' . time() . '-' . mt_rand(100, 999));
        
        $transactionId = $this->insertTransaction([
            'transaction_type' => 'onetime',
            'transaction_hash' => $uniqueHash,
            'payment_total'    => $this->getAmountTotal(),
            'status'           => 'pending',
            'currency'         => PaymentHelper::getFormCurrency($form->id),
            'payment_mode'     => $this->getPaymentMode()
        ]);
        
        $transaction = $this->getTransaction($transactionId);
        
        $this->handleRedirect($transaction, $submission, $form, $methodSettings);
    }
    
    protected function getPaymentMode()
    {
        $isLive = SquareSettings::isLive();
        if ($isLive) {
            return 'live';
        }
        return 'test';
    }
    
    protected function handleRedirect($transaction, $submission, $form, $methodSettings)
    {
        $keys = SquareSettings::getApiKeys();
        
        $ipnDomain = site_url('index.php');
        if(defined('FLUENTFORM_PAY_IPN_DOMAIN') && FLUENTFORM_PAY_IPN_DOMAIN) {
            $ipnDomain = FLUENTFORM_PAY_IPN_DOMAIN;
        }
        
        $listenerUrl = add_query_arg(array(
            'fluentform_payment_api_notify' => 1,
            'payment_method'                => $this->method,
            'fluentform_payment'            => $submission->id,
            'transaction_hash'              => $transaction->transaction_hash,
        ), $ipnDomain);
    
        $paymentArgs = [
            "idempotency_key" => $transaction->transaction_hash,
            "order"           => [
                "order" => [
                    "location_id" => ArrayHelper::get($keys, "location_id"),
                    "line_items"  => [
                        [
                            "quantity"         => '1',
                            "item_type"        => "ITEM",
                            "metadata"         => [
                                'form_id'       => 'Form Id ' . strval($form->id),
                                'submission_id' => 'Submission Id ' . strval($submission->id)
                            ],
                            "name"             => $this->getProductNames(),
                            "base_price_money" => [
                                "amount"   => PaymentHelper::isZeroDecimal($transaction->currency) ? intval($transaction->payment_total / 100) : intval($transaction->payment_total),
                                "currency" => $transaction->currency
                            ]
                        ]
                    ],
                ]
            ],
            'pre_populated_data' => [
                'buyer_email'   => PaymentHelper::getCustomerEmail($submission, $form),
                'buyer_phone_number' => PaymentHelper::getCustomerPhoneNumber($submission, $form),
            ],
            "redirect_url"    => $listenerUrl
        ];
    
        $paymentArgs = apply_filters('fluentform/square_payment_args', $paymentArgs, $submission, $transaction, $form);
        $paymentIntent = (new API())->makeApiCall('checkouts', $paymentArgs, $form->id, 'POST');
        if (is_wp_error($paymentIntent)) {
            do_action('fluentform/log_data', [
                'parent_source_id' => $submission->form_id,
                'source_type'      => 'submission_item',
                'source_id'        => $submission->id,
                'component'        => 'Payment',
                'status'           => 'error',
                'title'            => __('Square Payment Redirect Error', 'fluentformpro'),
                'description'      => $paymentIntent->get_error_message()
            ]);
            $this->changeSubmissionPaymentStatus('failed');
            $this->changeTransactionStatus($transaction->id, 'failed');
            wp_send_json_error([
                'message' => $paymentIntent->get_error_message()
            ], 423);
        }
    
        Helper::setSubmissionMeta($submission->id, '_square_payment_id', ArrayHelper::get($paymentIntent,'checkout.id'));
    
        do_action('fluentform/log_data', [
            'parent_source_id' => $submission->form_id,
            'source_type'      => 'submission_item',
            'source_id'        => $submission->id,
            'component'        => 'Payment',
            'status'           => 'info',
            'title'            => __('Redirect to Square', 'fluentformpro'),
            'description'      => __('User redirect to Square for completing the payment', 'fluentformpro')
        ]);
        $checkoutPageUrl = ArrayHelper::get($paymentIntent,'checkout.checkout_page_url');
        if (strpos($checkoutPageUrl, 'https:') === false) {
            if (SquareSettings::isLive()) {
                $checkoutHostingUrl = 'https://connect.squareup.com';
            } else {
                $checkoutHostingUrl = 'https://connect.squareupsandbox.com';
            }
            $checkoutPageUrl = $checkoutHostingUrl . $checkoutPageUrl;
        }
        wp_send_json_success([
            'nextAction'   => 'payment',
            'actionName'   => 'normalRedirect',
            'redirect_url' => $checkoutPageUrl,
            'message'      => __('You are redirecting to square to complete the purchase. Please wait while you are redirecting....', 'fluentformpro'),
            'result'       => [
                'insert_id' => $submission->id
            ]
        ], 200);
    }
    
    public function getProductNames()
    {
        $orderItems = $this->getOrderItems();
        $itemsHtml = '';
        foreach ($orderItems as $item) {
            $itemsHtml != "" && $itemsHtml .= ", ";
            $itemsHtml .=  $item->item_name ;
        }
        
        return $itemsHtml;
    }
    
    public function handleSessionRedirectBack($data)
    {
        $submissionId = intval($data['fluentform_payment']);
        $this->setSubmissionId($submissionId);
        $submission = $this->getSubmission();
        $paymentId = ArrayHelper::get($data, 'transactionId');
        $transaction = $this->getLastTransaction($submissionId);

        $payment = (new API())->makeApiCall('orders/'.$paymentId, [], $submission->form_id);
        if (is_wp_error($payment)) {
            do_action('fluentform/log_data', [
                'parent_source_id' => $submission->form_id,
                'source_type'      => 'submission_item',
                'source_id'        => $submission->id,
                'component'        => 'Payment',
                'status'           => 'error',
                'title'            => __('Square Payment Error', 'fluentformpro'),
                'description'      => $payment->get_error_message()
            ]);
            $returnData = $this->handleFailed($submission, $transaction);
            $returnData['type'] = 'failed';
        } else {
            $returnData = $this->handlePaid($submission, $transaction, $payment);
            $status = ArrayHelper::get($payment, 'order.state', '');
            $returnData['type'] = $status == 'COMPLETED' ? 'success' : 'failed';
        }
        if (!isset($returnData['is_new'])) {
            $returnData['is_new'] = false;
        }
        $redirectUrl = ArrayHelper::get($returnData, 'result.redirectUrl');
    
        if ($redirectUrl) {
            wp_redirect($redirectUrl);
        }
        $this->showPaymentView($returnData);
    }

    private function handleFailed($submission, $transaction)
    {
        $this->setSubmissionId($submission->id);
        $this->changeSubmissionPaymentStatus('failed');
        $this->changeTransactionStatus($transaction->id, 'failed');
        if ($this->getMetaData('is_form_action_fired') == 'yes') {
            return $this->completePaymentSubmission(false);
        }
        $this->setMetaData('is_form_action_fired', 'yes');
        return $this->getReturnData();
    }

    
    private function handlePaid($submission, $transaction, $payment)
    {
        $this->setSubmissionId($submission->id);
        if ($this->getMetaData('is_form_action_fired') == 'yes') {
            return $this->completePaymentSubmission(false);
        }
    
        $status = 'paid';
    
        // Let's make the payment as paid
        $updateData = [
            'payment_note' => maybe_serialize($payment),
            'charge_id'    => sanitize_text_field(ArrayHelper::get($payment, 'order.id')),
        ];
    
        if ($last4 = ArrayHelper::get($payment, 'order.tenders.card_details.card.last_4')) {
            $updateData['card_last_4'] = $last4;
        }
        $this->updateTransaction($transaction->id, $updateData);
        $this->changeSubmissionPaymentStatus($status);
        $this->changeTransactionStatus($transaction->id, $status);
        $this->recalculatePaidTotal();
        $returnData = $this->getReturnData();
    
        $this->setMetaData('is_form_action_fired', 'yes');
        
        // Handle zero-decimal currencies like JPY for amount comparison
        $expectedAmount = $transaction->payment_total;
        $receivedAmount = intval($payment['order']['total_money']['amount']);
        
        if (PaymentHelper::isZeroDecimal($transaction->currency)) {
            $expectedAmount = intval($transaction->payment_total / 100);
        }
        
        if ($expectedAmount != $receivedAmount || strtoupper($transaction->currency) != strtoupper($payment['order']['total_money']['currency'])) {
            do_action('fluentform/log_data', [
                'parent_source_id' => $submission->form_id,
                'source_type'      => 'submission_item',
                'source_id'        => $submission->id,
                'component'        => 'Payment',
                'status'           => 'error',
                'title'            => __('Transaction Amount Mismatch - Square', 'fluentformpro'),
                'description'      => __('Transaction Amount should be ' . PaymentHelper::formatMoney($expectedAmount,
                        $transaction->currency) . ' but received ' . PaymentHelper::formatMoney($receivedAmount,
                        strtoupper($payment['order']['total_money']['currency'])), 'fluentformpro')
            ]);
        }
    
        return $returnData;
    }

    public function validateSubmittedItems($errors, $paymentItems, $subscriptionItems, $form)
    {
        $singleItemTotal = 0;
        foreach ($paymentItems as $paymentItem) {
            if ($paymentItem['line_total']) {
                $singleItemTotal += $paymentItem['line_total'];
            }
        }
        if (count($subscriptionItems) && !$singleItemTotal) {
            $errorMessage = __('Square Error: Square does not support subscriptions right now!', 'fluentformpro');
            $errors[] = apply_filters('fluentform/payment_error_message', $errorMessage, null, $form);
        }
        return $errors;
    }
}
