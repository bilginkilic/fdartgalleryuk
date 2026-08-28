<?php

namespace FluentFormPro\Payments\Classes;

use FluentForm\Framework\Helpers\ArrayHelper;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class CouponController
{
    public function validateCoupon()
    {
        $code = sanitize_text_field(ArrayHelper::get($_REQUEST, 'coupon'));
        $formId = intval(ArrayHelper::get($_REQUEST, 'form_id'));
        $totalAmount = intval(ArrayHelper::get($_REQUEST, 'total_amount'));

        $couponModel = new CouponModel();
        $coupon = $couponModel->getCouponByCode($code);

        if (!$coupon) {
            wp_send_json([
                'message' => __(apply_filters('fluentform/coupon_general_failure_message', 'The provided coupon is not valid', $formId), 'fluentformpro')
            ], 423);
        }

        $failedMessageArray = ArrayHelper::get($coupon->settings, 'failed_message');

        if ($coupon->status != 'active' || $coupon->code !== $code) {
            wp_send_json([
                'message' => __(ArrayHelper::get($failedMessageArray, 'inactive'), 'fluentformpro')
            ], 423);
        }

        if ($couponModel->isDateExpire($coupon)) {
            wp_send_json([
                'message' => __(ArrayHelper::get($failedMessageArray, 'date_expire'), 'fluentformpro')
            ], 423);
        }

        if ($formIds = ArrayHelper::get($coupon->settings, 'allowed_form_ids')) {
            if (!in_array($formId, $formIds)) {
                wp_send_json([
                    'message' => __(ArrayHelper::get($failedMessageArray, 'allowed_form'), 'fluentformpro')
                ], 423);
            }
        }

        $couponLimit = ArrayHelper::get($coupon->settings, 'coupon_limit', 0);

        if ($couponLimit) {
            $userId = get_current_user_id();

            if ($userId) {
                if (!$couponModel->hasLimit($coupon->code, $couponLimit, $userId)) {
                    wp_send_json([
                        'message' => __(ArrayHelper::get($failedMessageArray, 'limit'), 'fluentformpro')
                    ], 423);
                }
            } else {
                wp_send_json([
                    'message' => __(ArrayHelper::get($failedMessageArray, 'limit'), 'fluentformpro')
                ], 423);
            }
        }

        if ($coupon->min_amount && $coupon->min_amount > $totalAmount) {
            wp_send_json([
                'message' => __(ArrayHelper::get($failedMessageArray, 'min_amount'), 'fluentformpro')
            ], 423);
        }

        $otherCouponCodes = wp_unslash(ArrayHelper::get($_REQUEST, 'other_coupons', ''));

        if ($otherCouponCodes) {
            $otherCouponCodes = \json_decode($otherCouponCodes, true);
            if ($otherCouponCodes) {
                $codes = $couponModel->getCouponsByCodes($otherCouponCodes);
                foreach ($codes as $couponItem) {
                    if (($couponItem->stackable != 'yes' || $coupon->stackable != 'yes') && $coupon->code != $couponItem->code) {
                        wp_send_json([
                            'message' => __(ArrayHelper::get($failedMessageArray, 'stackable'), 'fluentformpro')
                        ], 423);
                    }
                }
            }
        }

        $successMessage = ArrayHelper::get($coupon->settings, 'success_message');

        $formattedCoupon = [
            'code'               => $coupon->code,
            'title'              => $coupon->title,
            'amount'             => $coupon->amount,
            'coupon_type'        => $coupon->coupon_type,
            'message'            => $successMessage
        ];

        if ($coupon->min_amount) {
            $formattedCoupon['min_amount'] = $coupon->min_amount;
            $formattedCoupon['min_amount_message'] = __(ArrayHelper::get($failedMessageArray, 'min_amount'), 'fluentformpro');
        }

        wp_send_json([
            'coupon' => $formattedCoupon
        ], 200);
    }
}
