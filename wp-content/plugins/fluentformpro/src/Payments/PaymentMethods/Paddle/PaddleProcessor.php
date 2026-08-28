<?php

namespace FluentFormPro\Payments\PaymentMethods\Paddle;

use FluentForm\App\Helpers\Helper;
use FluentForm\App\Modules\Form\FormFieldsParser;
use FluentForm\Framework\Helpers\ArrayHelper;
use FluentFormPro\Payments\PaymentHelper;
use FluentFormPro\Payments\PaymentMethods\BaseProcessor;


class PaddleProcessor extends BaseProcessor
{
    public $method = 'paddle';

    protected $form;

    public function init()
    {
        add_action('fluentform/process_payment_' . $this->method, [$this, 'handlePaymentAction'], 10, 6);
        add_action('fluentform/payment_frameless_' . $this->method, array($this, 'handleSessionRedirectBack'));
        add_filter('fluentform/validate_payment_items_' . $this->method, [$this, 'validateSubmittedItems'], 10, 4);

        add_filter('fluentform/form_payment_settings', [$this, 'modifyFormPaymentSettings'], 10, 2);
        add_filter('fluentform/submission_order_items', [$this, 'resolveSubmissionOrderItems'], 10, 4);

        add_action('wp_ajax_fluentform_paddle_confirm_payment', array($this, 'confirmModalPayment'));
        add_action('wp_ajax_nopriv_fluentform_paddle_confirm_payment', array($this, 'confirmModalPayment'));
    }

    public function handlePaymentAction(
        $submissionId,
        $submissionData,
        $form,
        $methodSettings,
        $hasSubscriptions,
        $totalPayable
    ) {
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
                'description'      => __('Paddle does not support subscriptions right now!', 'fluentformpro')
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

        $formPaymentSettings = PaymentHelper::getFormSettings($form->id, 'admin');

        $this->handlePayment($transaction, $submission, $form, $methodSettings, $formPaymentSettings);
    }

    protected function handlePayment($transaction, $submission, $form, $methodSettings, $formPaymentSettings)
    {
        $ipnDomain = site_url('index.php');
        if (defined('FLUENTFORM_PAY_IPN_DOMAIN') && FLUENTFORM_PAY_IPN_DOMAIN) {
            $ipnDomain = FLUENTFORM_PAY_IPN_DOMAIN;
        }

        $successArgs = [
            'fluentform_payment_api_notify' => 1,
            'payment_method'                => $this->method,
            'fluentform_payment'            => $submission->id,
            'transaction_hash'              => $transaction->transaction_hash,
            'type'                          => 'success'
        ];
        $successUrl = add_query_arg($successArgs, $ipnDomain);

        $cancelArgs = array_merge($successArgs, ['type' => 'cancel']);
        $cancelUrl = $submission->source_url;
        if (!wp_http_validate_url($cancelUrl)) {
            $cancelUrl = site_url($cancelUrl);
        }
        $cancelUrl = add_query_arg($cancelArgs, $cancelUrl);

        $currency = strtoupper($transaction->currency);
        $this->supportedCurrency($currency, $submission);

        $orderItems = $this->getOrderItems();

        $paymentArgs = [
            'collection_mode' => 'automatic',
            'checkout'        => [
                'url'                  => $successUrl,
                'customer_success_url' => wp_sanitize_redirect($successUrl),
                'customer_cancel_url'  => wp_sanitize_redirect($cancelUrl)
            ]
        ];

        $items = [];

        if (ArrayHelper::get($formPaymentSettings, 'paddle_transaction_type') == 'non_catalog_price') {
            $products = ArrayHelper::get($formPaymentSettings, 'paddle_non_catalog_price_data');

            foreach ($products as $product) {
                $productId = ArrayHelper::get($product, 'product_id');
                $paymentName = ArrayHelper::get($product, 'payment_item');

                foreach ($orderItems as $singleOrder) {
                    if ($singleOrder->parent_holder == $paymentName) {
                        $quantity = $singleOrder->quantity;
                        $itemName = $singleOrder->item_name;
                        $itemPrice = $singleOrder->item_price;

                        $items[] = [
                            'quantity' => intval($quantity),
                            'price'    => [
                                'description' => $itemName,
                                'unit_price'  => [
                                    'amount'        => $itemPrice,
                                    'currency_code' => $currency
                                ],
                                'product_id'  => $productId
                            ]
                        ];
                    }
                }
            }
        } elseif (ArrayHelper::get($formPaymentSettings, 'paddle_transaction_type') == 'catalog') {
            $prices = ArrayHelper::get($formPaymentSettings, 'paddle_catalog_data');
            foreach ($prices as $price) {
                $priceId = ArrayHelper::get($price, 'price_id');
                $quantityName = ArrayHelper::get($price, 'quantity', 1);
                $quantity = ArrayHelper::get($submission->response, $quantityName);
                $items[] = [
                    'quantity' => intval($quantity),
                    'price_id' => $priceId
                ];
            }
        } else {
            $items = [
                [
                    "quantity" => 1,
                    "price"    => [
                        'description' => $transaction->transaction_hash,
                        "unit_price"  => [
                            "amount"        => $transaction->payment_total,
                            "currency_code" => $currency
                        ],
                        "product"     => [
                            "name"         => $this->getProductNames(),
                            "tax_category" => "standard"
                        ]
                    ]
                ]
            ];
        }

        $paymentArgs = array_merge($paymentArgs, ['items' => $items]);

        $customerId = $this->handleCustomer($submission, $form);
        if ($customerId) {
            $paymentArgs['customer_id'] = $customerId;
        }

        $paymentArgs = apply_filters('fluentform/paddle_payment_args', $paymentArgs, $submission, $transaction, $form);

        $paymentIntent = (new API())->makeApiCall('transactions', $paymentArgs, $form->id, 'POST');

        if (is_wp_error($paymentIntent)) {
            $logData = [
                'parent_source_id' => $submission->form_id,
                'source_type'      => 'submission_item',
                'source_id'        => $submission->id,
                'component'        => 'Payment',
                'status'           => 'error',
                'title'            => __('Paddle Payment Error', 'fluentformpro'),
                'description'      => $paymentIntent->get_error_message()
            ];

            do_action('fluentform/log_data', $logData);

            wp_send_json_success([
                'message'     => $paymentIntent->get_error_message(),
                'append_data' => [
                    '__entry_intermediate_hash' => Helper::getSubmissionMeta($transaction->submission_id,
                        '__entry_intermediate_hash')
                ]
            ], 423);
        }

        if (ArrayHelper::get($paymentIntent, 'data.id')) {
            // Store the payment intent ID for later verification
            $this->setMetaData('paddle_payment_intent_id', ArrayHelper::get($paymentIntent, 'data.id'));

            $redirectUrl = ArrayHelper::get($paymentIntent, 'data.checkout.url');
            $logData = [
                'parent_source_id' => $submission->form_id,
                'source_type'      => 'submission_item',
                'source_id'        => $submission->id,
                'component'        => 'Payment',
                'status'           => 'info',
                'title'            => __('Redirect to Paddle', 'fluentformpro'),
                'description'      => __('User redirect to Paddle for completing the payment', 'fluentformpro')
            ];

            do_action('fluentform/log_data', $logData);

            wp_send_json_success([
                'nextAction'   => 'payment',
                'actionName'   => 'normalRedirect',
                'redirect_url' => $redirectUrl,
                'message'      => __('You are redirecting to complete the purchase. Please wait while you are redirecting....',
                    'fluentformpro'),
                'result'       => [
                    'insert_id' => $submission->id,
                ]
            ], 200);
        }
    }

    public function handleSessionRedirectBack($data)
    {
        $type = sanitize_text_field(ArrayHelper::get($data, 'type'));
        $submissionId = intval(ArrayHelper::get($data, 'fluentform_payment'));
        $transactionHash = sanitize_text_field(ArrayHelper::get($data, 'transaction_hash'));

        $this->setSubmissionId($submissionId);
        $submission = $this->getSubmission();
        if (!$submission) {
            return;
        }

        $transaction = $this->getTransaction($transactionHash, 'transaction_hash');

        if (!$transaction) {
            $returnData = [
                'insert_id' => $submissionId,
                'title'     => __('Invalid Transaction', 'fluentformpro'),
                'result'    => false,
                'error'     => __('No valid transaction found for this payment.', 'fluentformpro')
            ];
            $this->showPaymentView($returnData);
            return;
        }

        if ($type == 'success') {
            if ($transaction->status === 'paid' || $this->getMetaData('is_form_action_fired') == 'yes') {
                $returnData = $this->getReturnData();
                $returnData['type'] = 'success';
            } else {
                $message = '<div class="ff_paddle_payment_container"><p></p></div>';

                $returnData = [
                    'insert_id' => $submissionId,
                    'title'     => __('Processing Paddle Payment', 'fluentformpro'),
                    'result'    => false,
                    'error'     => $message,
                    'type'      => 'success',
                    'is_new'    => true,
                    'txn_id'    => $transactionHash
                ];

                $this->addCheckoutJs($submissionId, $transactionHash);
            }
        } else {
            $returnData = [
                'insert_id' => $submissionId,
                'title'     => __('Payment Cancelled', 'fluentformpro'),
                'result'    => false,
                'error'     => __('Looks like you have cancelled the payment. Please try again!', 'fluentformpro'),
                'type'      => 'failed',
                'is_new'    => false
            ];
        }

        $this->showPaymentView($returnData);
    }

    protected function getPaymentMode()
    {
        $isLive = PaddleSettings::isLive();
        if ($isLive) {
            return 'production';
        }
        return 'sandbox';
    }

    protected function getProductNames()
    {
        $orderItems = $this->getOrderItems();
        $itemsHtml = '';
        foreach ($orderItems as $item) {
            $itemsHtml != "" && $itemsHtml .= ", ";
            $itemsHtml .= $item->item_name;
        }

        return $itemsHtml;
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
            $errors[] = __('Paddle Error: Paddle does not support subscriptions right now!', 'fluentformpro');
        }
        return $errors;
    }

    protected function supportedCurrency($currency, $submission)
    {
        $supportedCurrencies = [
            'USD',
            'EUR',
            'GBP',
            'JPY',
            'AUD',
            'CAD',
            'CHF',
            'HKD',
            'SGD',
            'SEK',
            'ARS',
            'BRL',
            'CNY',
            'COP',
            'CZK',
            'DKK',
            'HUF',
            'ILS',
            'INR',
            'KRW',
            'MXN',
            'NOK',
            'NZD',
            'PLN',
            'RUB',
            'THB',
            'TRY',
            'TWD',
            'UAH',
            'ZAR'
        ];

        if (!in_array($currency, $supportedCurrencies)) {
            wp_send_json([
                'errors'      => $currency . __('is not supported by Paddle payment method', 'fluentformpro'),
                'append_data' => [
                    '__entry_intermediate_hash' => Helper::getSubmissionMeta($submission->id,
                        '__entry_intermediate_hash')
                ]
            ], 423);
        }
    }

    public function confirmModalPayment()
    {
        $transactionHash = sanitize_text_field(ArrayHelper::get($_REQUEST, 'transaction_hash'));
        $transaction = $this->getTransaction($transactionHash, 'transaction_hash');

        if (!$transaction || $transaction->status != 'pending') {
            wp_send_json([
                'errors' => __('Payment Error: Invalid Request', 'fluentformpro'),
            ], 423);
        }

        $vendorPayment = ArrayHelper::get($_REQUEST, 'paddle_payment');
        $checkoutId = sanitize_text_field(ArrayHelper::get($vendorPayment, 'id'));

        if ($checkoutId) {
            $this->setSubmissionId($transaction->submission_id);

            // Get form settings for redirect URL
            $form = $this->getForm();
            $formSettings = PaymentHelper::getFormSettings($form->id);

            // Determine redirect URL 
            $redirectTo = ArrayHelper::get($formSettings, 'confirmation.redirectTo');
            if ($redirectTo && $redirectTo == 'customUrl') {
                $redirectUrl = ArrayHelper::get($formSettings, 'confirmation.customUrl');
            } else if ($redirectTo) {
                $redirectUrl = get_permalink($redirectTo);
            } else {
                $redirectUrl = wp_get_referer() ?: site_url();
            }

            $logData = [
                'parent_source_id' => $transaction->form_id,
                'source_type'      => 'submission_item',
                'source_id'        => $transaction->submission_id,
                'component'        => 'Payment',
                'status'           => 'success',
                'title'            => __('Payment Success', 'fluentformpro'),
                'description'      => __('Paddle payment has been marked as paid', 'fluentformpro')
            ];

            do_action('fluentform/log_data', $logData);

            $this->setSubmissionId($transaction->submission_id);
            $submission = $this->getSubmission();
            $returnData = $this->handlePaid($submission, $transaction, $vendorPayment);
            $returnData['payment'] = $vendorPayment;
            $returnData['success_message'] = __('Your payment has successfully been processed!', 'fluentformpro');
            $returnData['redirect_url'] = $redirectUrl;
            $returnData['title'] = __('Payment Successful', 'fluentformpro');;

            wp_send_json_success($returnData, 200);
        }

        wp_send_json([
            'errors'      => __('Payment could not be verified. Please contact site admin', 'fluentformpro'),
            'append_data' => [
                '__entry_intermediate_hash' => Helper::getSubmissionMeta($transaction->submission_id,
                    '__entry_intermediate_hash')
            ]
        ], 423);
    }

    public function addCheckoutJs($submissionId, $transactionHash)
    {
        wp_enqueue_script('paddle', 'https://cdn.paddle.com/paddle/v2/paddle.js', [], FLUENTFORMPRO_VERSION);
        wp_enqueue_script('ff_paddle_handler', FLUENTFORMPRO_DIR_URL . 'public/js/paddle_handler.js', ['jquery'],
            FLUENTFORMPRO_VERSION);

        $paymentMode = $this->getPaymentMode();
        $submission = $this->getSubmission();
        $form = $this->getForm();

        // Get form settings for redirect URLs
        $formSettings = PaymentHelper::getFormSettings($form->id);

        // Determine redirect URL on success
        $redirectTo = ArrayHelper::get($formSettings, 'confirmation.redirectTo');
        if ($redirectTo && $redirectTo == 'customUrl') {
            $redirectUrl = ArrayHelper::get($formSettings, 'confirmation.customUrl');
        } else if ($redirectTo) {
            $redirectUrl = get_permalink($redirectTo);
        } else {
            $redirectUrl = wp_get_referer() ?: site_url();
        }

        // Get cancel URL
        $cancelUrl = $submission->source_url;
        if (!wp_http_validate_url($cancelUrl)) {
            $cancelUrl = site_url($cancelUrl);
        }

        $allowedPaymentMethods = [
            'alipay', 'apple_pay', 'bancontact', 'card', 'google_pay', 'ideal', 'paypal'
        ];

        $checkoutVars = [
            'ajax_url'                => admin_url('admin-ajax.php'),
            'submission_id'           => $submissionId,
            'transaction_hash'        => $transactionHash,
            'onFailedMessage'         => __("Sorry! We couldn't mark your payment as paid. Please try again later!", 'fluentformpro'),
            'payment_mode'            => $paymentMode,
            'title_message'           => __('Paddle Payment Successful', 'fluentformpro'),
            'theme'                   => 'light',
            'locale'                  => 'en',
            'client_token'            => PaddleSettings::getClientToken(),
            'frame_initial_height'    => '450',
            'frame_style'             => 'width: 100%; min-width: 312px; background-color: transparent; border: none;',
            'allowed_payment_methods' => $allowedPaymentMethods,
            'success_redirect'        => $redirectUrl,
            'failure_redirect'        => $cancelUrl,
            'redirect_delay'          => 1000
        ];

        wp_localize_script('ff_paddle_handler', 'ff_paddle_vars',
            apply_filters('fluentform/paddle_checkout_vars', $checkoutVars));
    }

    public function resolveSubmissionOrderItems($orderItems, $submissionData, $form, $method)
    {
        // Remove extra order items that's not mapping by setting for catalog, non_catalog_price type payment
        if ($this->method === $method) {
            $formPaymentSettings = PaymentHelper::getFormSettings($form->id, 'admin');
            if (ArrayHelper::get($formPaymentSettings, 'paddle_transaction_type') == 'non_catalog_price') {
                $products = ArrayHelper::get($formPaymentSettings, 'paddle_non_catalog_price_data');
                $paymentNames = [];
                foreach ($products as $product) {
                    $paymentNames[] = ArrayHelper::get($product, 'payment_item');
                }
                $orderItems = array_filter($orderItems, function ($item) use ($paymentNames) {
                    return in_array(ArrayHelper::get($item, 'parent_holder'), $paymentNames);
                });
            } elseif (ArrayHelper::get($formPaymentSettings, 'paddle_transaction_type') == 'catalog') {
                $prices = ArrayHelper::get($formPaymentSettings, 'paddle_catalog_data');
                $quantityItems = [];
                $formQuantityField = FormFieldsParser::getElement($form, ['item_quantity_component'], ['settings', 'attributes']);
                if ($formQuantityField) {
                    foreach ($formQuantityField as $quantityField) {
                        if ($targetProductName = ArrayHelper::get($quantityField, 'settings.target_product')) {
                            $quantityItems[ArrayHelper::get($quantityField, 'attributes.name')] = $targetProductName;
                        }
                    }
                }
                $qtyFieldNames = [];
                foreach ($prices as $price) {
                    $mappingQtyName = ArrayHelper::get($price, 'quantity');
                    if ($name = ArrayHelper::get($quantityItems, $mappingQtyName)) {
                        $qtyFieldNames[] = $name;
                    }
                }
                $orderItems = array_filter($orderItems, function ($item) use ($qtyFieldNames) {
                    return in_array(ArrayHelper::get($item, 'parent_holder'), $qtyFieldNames);
                });
            }
        }
        return $orderItems;
    }

    public function modifyFormPaymentSettings($paymentSettings, $formId)
    {
        $availablePaymentMethods = PaymentHelper::getFormPaymentMethods($formId);

        if (
            !ArrayHelper::exists($availablePaymentMethods, 'paddle') &&
            ArrayHelper::get($availablePaymentMethods, 'paddle.enabled') == 'no'
        ) {
            return $paymentSettings;
        }

        $products = $this->getProducts($formId);
        $prices = $this->getPrices($formId);

        $customSettings = [
            'paddle_transaction_type'       => 'non_catalog',
            'paddle_create_customer'        => 'no',
            'paddle_catalog_data'           => [
                [
                    'price_id' => '',
                    'quantity' => ''
                ]
            ],
            'paddle_non_catalog_price_data' => [
                [
                    'product_id'   => '',
                    'payment_item' => ''
                ]
            ]
        ];

        $paymentSettings['settings'] = wp_parse_args($paymentSettings['settings'], $customSettings);
        $paymentSettings['settings']['paddle_products'] = $products;
        $paymentSettings['settings']['paddle_prices'] = $prices;

        return $paymentSettings;
    }

    protected function handlePaid($submission, $transaction, $vendorTransaction)
    {
        $this->setSubmissionId($submission->id);

        // Check if actions are fired
        if ($this->getMetaData('is_form_action_fired') == 'yes') {
            return $this->completePaymentSubmission(false);
        }

        $status = 'paid';

        // Let's make the payment as paid
        $updateData = [
            'payment_note'  => maybe_serialize($vendorTransaction),
            'charge_id'     => sanitize_text_field(ArrayHelper::get($vendorTransaction, 'transaction_id')),
            'payer_email'   => sanitize_email(ArrayHelper::get($vendorTransaction, 'customer.email')),
            'card_brand'    => ArrayHelper::get($vendorTransaction, 'payment.method_details.card.type', null),
            'card_last_4'  => ArrayHelper::get($vendorTransaction, 'payment.method_details.card.last4', null)
        ];

        $this->updateTransaction($transaction->id, $updateData);
        $this->changeSubmissionPaymentStatus($status);
        $this->changeTransactionStatus($transaction->id, $status);
        $this->recalculatePaidTotal();
        $returnData = $this->getReturnData();
        $returnData['type'] = 'success';
        $this->setMetaData('is_form_action_fired', 'yes');
        return $returnData;
    }

    protected function handleCustomer($submission, $form)
    {
        $customerName = PaymentHelper::getCustomerName($submission, $this->form);
        $customerEmail = PaymentHelper::getCustomerEmail($submission, $this->form);
        $customerAddress = PaymentHelper::getCustomerAddress($submission);

        // First create a customer
        $customerData = [
            'email' => $customerEmail,
            'name'  => $customerName
        ];

        $customerResponse = (new API())->makeApiCall('customers', $customerData, $form->id, 'POST');
        $customerId = null;

        if (is_wp_error($customerResponse)) {
            $errorData = $customerResponse->get_error_data();
            $errorCode = $customerResponse->get_error_code();
            $errorMessage = $customerResponse->get_error_message();

            // Check if the error is "customer_already_exists"
            if (
                $errorData &&
                isset($errorData['error']) &&
                isset($errorData['error']['code']) &&
                $errorData['error']['code'] === 'customer_already_exists'
            ) {
                // Customer already exists, find by email
                $customerId = $this->findCustomerByEmail($customerEmail, $form, $submission);

                if ($customerId) {
                    do_action('fluentform/log_data', [
                        'parent_source_id' => $form->id,
                        'source_type'      => 'submission_item',
                        'source_id'        => $submission->id,
                        'component'        => 'Payment',
                        'status'           => 'info',
                        'title'            => __('Existing Customer Found', 'fluentformpro'),
                        'description'      => __("Using existing customer with ID: {$customerId}", 'fluentformpro')
                    ]);
                } else {
                    do_action('fluentform/log_data', [
                        'parent_source_id' => $form->id,
                        'source_type'      => 'submission_item',
                        'source_id'        => $submission->id,
                        'component'        => 'Payment',
                        'status'           => 'failed',
                        'title'            => __('Customer Lookup Failed', 'fluentformpro'),
                        'description'      => __("Customer exists but couldn't be found by email", 'fluentformpro')
                    ]);
                }
            } else {
                // Other error occurred
                do_action('fluentform/log_data', [
                    'parent_source_id' => $form->id,
                    'source_type'      => 'submission_item',
                    'source_id'        => $submission->id,
                    'component'        => 'Payment',
                    'status'           => 'failed',
                    'title'            => __('Skip Customer Creation', 'fluentformpro'),
                    'description'      => __("Error Code: {$errorCode}. Reason: {$errorMessage}", 'fluentformpro')
                ]);
            }
        } else {
            if (isset($customerResponse['data']['id'])) {
                $customerId = $customerResponse['data']['id'];

                do_action('fluentform/log_data', [
                    'parent_source_id' => $form->id,
                    'source_type'      => 'submission_item',
                    'source_id'        => $submission->id,
                    'component'        => 'Payment',
                    'status'           => 'info',
                    'title'            => __('Customer Created', 'fluentformpro'),
                    'description'      => __("Customer created with ID: {$customerId}", 'fluentformpro')
                ]);
            }
        }

        if ($customerAddress && $customerId && !empty($customerAddress['country'])) {
            $this->handleCustomerAddress($customerId, $customerAddress, $form, $submission);
        }

        return $customerId;
    }

    protected function findCustomerByEmail($email, $form, $submission)
    {
        // Use the exact email parameter
        $endpoint = 'customers?email=' . urlencode($email);
        $response = (new API())->makeApiCall($endpoint, [], $form->id);

        if (is_wp_error($response)) {
            do_action('fluentform/log_data', [
                'parent_source_id' => $form->id,
                'source_type'      => 'submission_item',
                'source_id'        => $submission->id,
                'component'        => 'Payment',
                'status'           => 'error',
                'title'            => __('Customer Search Failed', 'fluentformpro'),
                'description'      => $response->get_error_message()
            ]);
            return null;
        }

        if (!isset($response['data']) || empty($response['data'])) {
            return null;
        }

        return ArrayHelper::get($response, 'data.0.id', '');
    }
    
    protected function handleCustomerAddress($customerId, $customerAddress, $form, $submission)
    {
        $address = [];

        // Paddle requires country code
        if ($country = ArrayHelper::get($customerAddress, 'country')) {
            $address['country_code'] = $country;
        }
        
        if ($firstLine = ArrayHelper::get($customerAddress, 'address_line_1')) {
            $address['first_line'] = $firstLine;
        }

        if ($secondLine = ArrayHelper::get($customerAddress, 'address_line_2')) {
            $address['second_line'] = $secondLine;
        }

        if ($city = ArrayHelper::get($customerAddress, 'city')) {
            $address['city'] = $city;
        }

        if ($state = ArrayHelper::get($customerAddress, 'state')) {
            $address['region'] = $state;
        }

        if ($zip = ArrayHelper::get($customerAddress, 'zip')) {
            $address['postal_code'] = $zip;
        }
        
        $endpoint = 'customers/' . $customerId . '/addresses';
        $customerAddressResponse = (new API())->makeApiCall($endpoint, $address, $form->id, 'POST');

        if (!is_wp_error($customerAddressResponse) && isset($customerAddressResponse['data']['id'])) {
            do_action('fluentform/log_data', [
                'parent_source_id' => $form->id,
                'source_type'      => 'submission_item',
                'source_id'        => $submission->id,
                'component'        => 'Payment',
                'status'           => 'info',
                'title'            => __('Customer Address Created', 'fluentformpro'),
                'description'      => __("Customer address created for customer ID: {$customerId}", 'fluentformpro')
            ]);
            
            return;
        }

        do_action('fluentform/log_data', [
            'parent_source_id' => $form->id,
            'source_type'      => 'submission_item',
            'source_id'        => $submission->id,
            'component'        => 'Payment',
            'status'           => 'failed',
            'title'            => __('Skip Customer Address Creation', 'fluentformpro'),
            'description'      => __("Could not be able to create address for customer ID: {$customerId}", 'fluentformpro')
        ]);
    }

    private function getProducts($formId)
    {
        $products = (new API())->makeApiCall('products', [], $formId);

        if (is_wp_error($products)) {
            return [];
        }

        $formattedProducts = [];

        foreach ($products['data'] as $product) {
            $id = $product['id'];
            $name = $product['name'];
            $formattedProducts[$id] = $name;
        }

        return $formattedProducts;
    }

    private function getPrices($formId)
    {
        $prices = (new API())->makeApiCall('prices', [], $formId);

        if (is_wp_error($prices)) {
            return [];
        }

        $formattedPrices = [];

        foreach ($prices['data'] as $price) {
            if (ArrayHelper::get($price, 'billing_cycle')) {
                continue;
            }
            $id = $price['id'];
            $description = $price['description'];
            $formattedPrices[$id] = $description;
        }

        return $formattedPrices;
    }
}