<?php

namespace FluentFormPro\Reports;

use FluentForm\App\Helpers\Helper;
use FluentForm\App\Models\Form;
use FluentForm\App\Models\Submission;
use FluentForm\App\Modules\Payments\PaymentHelper;
use FluentForm\App\Services\Report\ReportHelper as CoreReportHelper;
use FluentForm\Framework\Helpers\ArrayHelper as Arr;
use FluentForm\Framework\Support\Sanitizer;

/**
 * ReportHelperPro
 */

if (!class_exists('FluentForm\App\Services\Report\ReportHelper')) {
    return;
}
class ReportHelperPro extends CoreReportHelper
{
    /**
     * Get completion rate data
     */
    public static function getCompletionRateData($startDate, $endDate, $formId = 0)
    {
        $completeQuery = Submission::whereBetween('created_at', [$startDate, $endDate]);

        if ($formId) {
            $completeQuery->where('form_id', $formId);
        }

        $completeSubmissions = $completeQuery->count();

        $incompleteSubmissions = 0;
        if (Helper::hasPro()) {
            $incompleteQuery = wpFluent()->table('fluentform_draft_submissions')
                ->whereBetween('created_at', [$startDate, $endDate]);

            if ($formId) {
                $incompleteQuery->where('form_id', $formId);
            }

            $incompleteSubmissions = $incompleteQuery->count();
        }

        // Calculate totals - total_submissions should be complete submissions only
        // Total attempts = complete + incomplete (drafts)
        $totalAttempts = $completeSubmissions + $incompleteSubmissions;
        $completionRate = $totalAttempts > 0 ? round(($completeSubmissions / $totalAttempts) * 100, 1) : 0;

        return [
            'completion_rate' => $completionRate,
            'incomplete_submissions' => $incompleteSubmissions,
            'total_submissions' => $completeSubmissions, // This should be complete submissions only
            'total_attempts' => $totalAttempts // Total form attempts (complete + incomplete)
        ];
    }

    /**
     * Get submission heatmap data aggregated by recurring time periods
     *
     * This method returns cumulative submissions aggregated by 1-hour time slots within recurring periods.
     * (e.g., all Mondays combined, all Tuesdays combined, etc.) .
     *
     * @param string $startDate
     * @param string $endDate
     * @param int|null $formId Optional form ID to filter by
     * @return array Heatmap data with aggregated submissions by recurring time periods
     */
    public static function getSubmissionHeatmap($startDate, $endDate, $formId = 0)
    {

        list($startDate, $endDate) = self::processDateRange($startDate, $endDate);

        // Calculate date difference to determine aggregation type
        $startDateTime = new \DateTime($startDate);
        $endDateTime = new \DateTime($endDate);
        $interval = $startDateTime->diff($endDateTime);
        $daysInterval = $interval->days + 1;

        $aggregationType = 'day_of_week';

        $heatmapData = self::initializeHeatmapData($aggregationType);

        $results = self::getHeatmapSubmissionData($startDate, $endDate, $formId, $aggregationType);

        // Fill in actual submission data
        foreach ($results as $row) {
            $timeSlotIndex = (int)$row->submission_hour; // 1-hour intervals (0-23)
            $count = (int)$row->count;

            if ($aggregationType === 'day_of_week') {
                // Convert MySQL DAYOFWEEK (1=Sunday, 7=Saturday) to our format (0=Sunday, 6=Saturday)
                $dayOfWeek = ((int)$row->day_of_week - 1) % 7;
                $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                $dayKey = $dayNames[$dayOfWeek];

                if (isset($heatmapData[$dayKey])) {
                    $heatmapData[$dayKey][$timeSlotIndex] += $count;
                }
            }
        }

        // Generate time slots for AM/PM display
        $timeSlots = [
            'am' => ["12 AM", "1 AM", "2 AM", "3 AM", "4 AM", "5 AM",
                    "6 AM", "7 AM", "8 AM", "9 AM", "10 AM", "11 AM"],
            'pm' => ["12 PM", "1 PM", "2 PM", "3 PM", "4 PM", "5 PM",
                    "6 PM", "7 PM", "8 PM", "9 PM", "10 PM", "11 PM"]
        ];

        // Generate day labels
        $dayLabels = [
            'Sunday' => 'SUN', 'Monday' => 'MON', 'Tuesday' => 'TUE', 'Wednesday' => 'WED',
            'Thursday' => 'THU', 'Friday' => 'FRI', 'Saturday' => 'SAT'
        ];

        $result = [
            'heatmap_data'     => $heatmapData,
            'time_slots'       => $timeSlots,
            'day_labels'       => $dayLabels,
            'aggregation_type' => $aggregationType,
            'start_date'       => $startDate,
            'end_date'         => $endDate,
            'days_in_range'    => $daysInterval
        ];

        return $result;
    }

    /**
     * Get submissions grouped by country
     */
    public static function getSubmissionsByCountry($startDate, $endDate, $formId = 0)
    {
        if (apply_filters('fluentform/disable_submission_country_detection', false, $formId)) {
            return [
                'country_data' => [],
                'disable' => true
            ];
        }

        list($startDate, $endDate) = self::processDateRange($startDate, $endDate);

        $query = Submission::whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('UPPER(country) as country_code, COUNT(*) as count')
            ->whereNotNull('country')
            ->where('country', '!=', '')
            ->groupBy('country_code')
            ->orderBy('count', 'DESC');

        if ($formId) {
            $query->where('form_id', $formId);
        }

        $results = $query->get();
        $countryNames = getFluentFormCountryList();

        $countryData = [];
        foreach ($results as $result) {
            $countryCode = $result->country_code;
            $countryData[] = [
                'name' => $countryNames[$countryCode] ?? $countryCode,
                'value' => (int)$result->count
            ];
        }

        return [
            'country_data' => $countryData
        ];
    }

    /**
     * Get subscriptions data
     */
    public static function getSubscriptions($startDate, $endDate, $formId = 0)
    {
        $paymentSettings = get_option('__fluentform_payment_module_settings');
        if (!$paymentSettings || !Arr::get($paymentSettings, 'status')) {
            return []; // Return empty if payment module is disabled
        }

        // Process date range
        list($startDate, $endDate) = self::processDateRange($startDate, $endDate);

        // Query subscriptions
        $query = wpFluent()
            ->table('fluentform_subscriptions')
            ->whereBetween('created_at', [$startDate, $endDate]);

        // Apply form id
        if ($formId) {
            $query->where('form_id', $formId);
        }

        // Fetch subscriptions
        $subscriptions = $query->get();

        // Group subscriptions by plan_name for chart display
        $subscriptionsByPlan = [];
        $totalRecurringAmount = 0;

        foreach ($subscriptions as $subscription) {
            $planName = $subscription->plan_name ?: ($subscription->item_name ?: 'Unnamed Plan');
            $recurringAmount = $subscription->recurring_amount / 100; // Convert cents to dollars

            if (!isset($subscriptionsByPlan[$planName])) {
                $subscriptionsByPlan[$planName] = 0;
            }

            $subscriptionsByPlan[$planName] += $recurringAmount;
            $totalRecurringAmount += $recurringAmount;
        }

        // Sort by amount (highest first)
        arsort($subscriptionsByPlan);

        // Calculate growth compared to previous period
        $previousStartDate = (new \DateTime($startDate))->modify('-' . self::getDateDifference($startDate, $endDate) . ' days')->format('Y-m-d H:i:s');
        $previousEndDate = (new \DateTime($startDate))->modify('-1 day')->format('Y-m-d H:i:s');

        $previousQuery = wpFluent()
            ->table('fluentform_subscriptions')
            ->whereBetween('created_at', [$previousStartDate, $previousEndDate])
            ->where(function($q) {
                $q->where('status', 'active')
                    ->orWhere('status', 'trialing');
            });

        if ($formId) {
            $previousQuery->where('form_id', $formId);
        }

        $previousSubscriptions = $previousQuery->get();
        $previousTotalRecurring = 0;

        foreach ($previousSubscriptions as $subscription) {
            $previousTotalRecurring += $subscription->recurring_amount / 100;
        }

        // Calculate growth percentage
        $growthPercentage = 0;
        if ($previousTotalRecurring > 0) {
            $growthPercentage = round((($totalRecurringAmount - $previousTotalRecurring) / $previousTotalRecurring) * 100, 1);
        } elseif ($totalRecurringAmount > 0) {
            $growthPercentage = 100;
        }

        // Format data for chart display
        $chartData = [];
        foreach ($subscriptionsByPlan as $plan => $amount) {
            $chartData[] = [
                'name' => $plan,
                'value' => $amount,
            ];
        }

        // Limit to top 5 plans for readability
        $chartData = array_slice($chartData, 0, 5);

        return [
            'total_recurring' => $totalRecurringAmount,
            'growth_percentage' => $growthPercentage,
            'subscription_count' => count($subscriptions),
            'chart_data' => $chartData,
            'start_date' => $startDate,
            'currency_symbol' => Arr::get(PaymentHelper::getCurrencyConfig($formId), 'currency_sign', '$'),
            'end_date' => $endDate
        ];
    }

    public static function getNetRevenueByForms($startDate, $endDate, $perPage = 10, $currentPage = 1)
    {
        global $wpdb;
        $prefix = $wpdb->prefix;
        $query = wpFluent()
            ->table('fluentform_transactions')
            ->join('fluentform_forms', 'fluentform_transactions.form_id', '=', 'fluentform_forms.id')
            ->select(
                'fluentform_forms.id as form_id',
                'fluentform_forms.title as form_title',
                wpFluent()->raw("SUM(CASE WHEN {$prefix}fluentform_transactions.status = 'paid' THEN {$prefix}fluentform_transactions.payment_total ELSE 0 END) as paid_amount"),
                wpFluent()->raw("SUM(CASE WHEN {$prefix}fluentform_transactions.status = 'pending' THEN {$prefix}fluentform_transactions.payment_total ELSE 0 END) as pending_amount"),
                wpFluent()->raw("SUM(CASE WHEN {$prefix}fluentform_transactions.status = 'refunded' THEN {$prefix}fluentform_transactions.payment_total ELSE 0 END) as refunded_amount"),
                wpFluent()->raw("(SUM(CASE WHEN {$prefix}fluentform_transactions.status = 'paid' THEN {$prefix}fluentform_transactions.payment_total ELSE 0 END) - SUM(CASE WHEN {$prefix}fluentform_transactions.status = 'refunded' THEN {$prefix}fluentform_transactions.payment_total ELSE 0 END)) as net_revenue")
            )
            ->whereBetween('fluentform_transactions.created_at', [$startDate, $endDate])
            ->whereIn('fluentform_transactions.status', ['paid', 'pending', 'refunded'])
            ->groupBy('fluentform_forms.id', 'fluentform_forms.title')
            ->orderBy('net_revenue', 'DESC');

        $results = $query->paginate($perPage, ['*'], 'page', $currentPage);
        $total = $results->total();

        // Get totals for all data
        $totalsQuery = wpFluent()
            ->table('fluentform_transactions')
            ->select(
                wpFluent()->raw("SUM(CASE WHEN {$prefix}fluentform_transactions.status = 'paid' THEN {$prefix}fluentform_transactions.payment_total ELSE 0 END) as paid"),
                wpFluent()->raw("SUM(CASE WHEN {$prefix}fluentform_transactions.status = 'pending' THEN {$prefix}fluentform_transactions.payment_total ELSE 0 END) as pending"),
                wpFluent()->raw("SUM(CASE WHEN {$prefix}fluentform_transactions.status = 'refunded' THEN {$prefix}fluentform_transactions.payment_total ELSE 0 END) as refunded"),
                wpFluent()->raw("(SUM(CASE WHEN {$prefix}fluentform_transactions.status = 'paid' THEN {$prefix}fluentform_transactions.payment_total ELSE 0 END) - SUM(CASE WHEN {$prefix}fluentform_transactions.status = 'refunded' THEN {$prefix}fluentform_transactions.payment_total ELSE 0 END)) as net")
            )
            ->whereBetween('fluentform_transactions.created_at', [$startDate, $endDate])
            ->whereIn('fluentform_transactions.status', ['paid', 'pending', 'refunded']);

        $totals = $totalsQuery->first();
        if ($totals) {
            $formattedTotals = [
                'paid'     => round($totals->paid / 100, 2),
                'pending'  => round($totals->pending / 100, 2),
                'refunded' => round($totals->refunded / 100, 2),
                'net'      => round($totals->net / 100, 2)
            ];
        } else {
            $formattedTotals = [
                'paid'     => 0,
                'pending'  => 0,
                'refunded' => 0,
                'net'      => 0
            ];
        }

        $formattedResults = [];
        foreach ($results->items() as $row) {
            $formattedResults[] = [
                'form_id'         => $row->form_id,
                'form_title'      => $row->form_title ?: 'Untitled Form',
                'paid_amount'     => round($row->paid_amount / 100, 2),
                'pending_amount'  => round($row->pending_amount / 100, 2),
                'refunded_amount' => round($row->refunded_amount / 100, 2),
                'net_revenue'     => round($row->net_revenue / 100, 2)
            ];
        }

        return [
            'data'   => $formattedResults,
            'totals' => $formattedTotals,
            'total'  => $total
        ];
    }

    public static function getNetRevenueByPaymentMethod(
        $startDate,
        $endDate,
        $formId = null,
        $perPage = 10,
        $currentPage = 1
    ) {
        global $wpdb;
        $prefix = $wpdb->prefix;
        $query = wpFluent()
            ->table('fluentform_transactions')
            ->select(
                'fluentform_transactions.payment_method',
                wpFluent()->raw("SUM(CASE WHEN {$prefix}fluentform_transactions.status = 'paid' THEN {$prefix}fluentform_transactions.payment_total ELSE 0 END) as paid_amount"),
                wpFluent()->raw("SUM(CASE WHEN {$prefix}fluentform_transactions.status = 'pending' THEN {$prefix}fluentform_transactions.payment_total ELSE 0 END) as pending_amount"),
                wpFluent()->raw("SUM(CASE WHEN {$prefix}fluentform_transactions.status = 'refunded' THEN {$prefix}fluentform_transactions.payment_total ELSE 0 END) as refunded_amount"),
                wpFluent()->raw("(SUM(CASE WHEN {$prefix}fluentform_transactions.status = 'paid' THEN {$prefix}fluentform_transactions.payment_total ELSE 0 END) - SUM(CASE WHEN {$prefix}fluentform_transactions.status = 'refunded' THEN {$prefix}fluentform_transactions.payment_total ELSE 0 END)) as net_revenue"),
                wpFluent()->raw("COUNT(*) as transaction_count")
            )
            ->whereBetween('fluentform_transactions.created_at', [$startDate, $endDate])
            ->whereIn('fluentform_transactions.status', ['paid', 'pending', 'refunded'])
            ->whereNotNull('fluentform_transactions.payment_method');

        if ($formId) {
            $query->where('fluentform_transactions.form_id', $formId);
        }

        $query->groupBy('fluentform_transactions.payment_method')
            ->orderBy('net_revenue', 'DESC');

        $results = $query->paginate($perPage, ['*'], 'page', $currentPage);
        $total = $results->total();

        // Get totals for all data
        $totalsQuery = wpFluent()
            ->table('fluentform_transactions')
            ->select(
                wpFluent()->raw("SUM(CASE WHEN {$prefix}fluentform_transactions.status = 'paid' THEN {$prefix}fluentform_transactions.payment_total ELSE 0 END) as paid"),
                wpFluent()->raw("SUM(CASE WHEN {$prefix}fluentform_transactions.status = 'pending' THEN {$prefix}fluentform_transactions.payment_total ELSE 0 END) as pending"),
                wpFluent()->raw("SUM(CASE WHEN {$prefix}fluentform_transactions.status = 'refunded' THEN {$prefix}fluentform_transactions.payment_total ELSE 0 END) as refunded"),
                wpFluent()->raw("(SUM(CASE WHEN {$prefix}fluentform_transactions.status = 'paid' THEN {$prefix}fluentform_transactions.payment_total ELSE 0 END) - SUM(CASE WHEN {$prefix}fluentform_transactions.status = 'refunded' THEN {$prefix}fluentform_transactions.payment_total ELSE 0 END)) as net")
            )
            ->whereBetween('fluentform_transactions.created_at', [$startDate, $endDate])
            ->whereIn('fluentform_transactions.status', ['paid', 'pending', 'refunded']);

        $totals = $totalsQuery->first();
        if ($totals) {
            $formattedTotals = [
                'paid'     => round($totals->paid / 100, 2),
                'pending'  => round($totals->pending / 100, 2),
                'refunded' => round($totals->refunded / 100, 2),
                'net'      => round($totals->net / 100, 2)
            ];
        } else {
            $formattedTotals = [
                'paid'     => 0,
                'pending'  => 0,
                'refunded' => 0,
                'net'      => 0
            ];
        }

        $formattedResults = [];
        foreach ($results->items() as $row) {
            $paymentMethodName = self::formatPaymentMethodName($row->payment_method);
            $formattedResults[] = [
                'payment_method'      => $row->payment_method,
                'payment_method_name' => $paymentMethodName,
                'paid_amount'         => round($row->paid_amount / 100, 2),
                'pending_amount'      => round($row->pending_amount / 100, 2),
                'refunded_amount'     => round($row->refunded_amount / 100, 2),
                'net_revenue'         => round($row->net_revenue / 100, 2),
                'transaction_count'   => $row->transaction_count
            ];
        }
        return [
            'data'   => $formattedResults,
            'totals' => $formattedTotals,
            'total'  => $total
        ];
    }

    public static function getNetRevenueByPaymentType(
        $startDate,
        $endDate,
        $formId = null,
        $perPage = 10,
        $currentPage = 1
    ) {
        global $wpdb;
        $prefix = $wpdb->prefix;
        $query = wpFluent()
            ->table('fluentform_transactions')
            ->select(
                'fluentform_transactions.transaction_type',
                wpFluent()->raw("SUM(CASE WHEN {$prefix}fluentform_transactions.status = 'paid' THEN {$prefix}fluentform_transactions.payment_total ELSE 0 END) as paid_amount"),
                wpFluent()->raw("SUM(CASE WHEN {$prefix}fluentform_transactions.status = 'pending' THEN {$prefix}fluentform_transactions.payment_total ELSE 0 END) as pending_amount"),
                wpFluent()->raw("SUM(CASE WHEN {$prefix}fluentform_transactions.status = 'refunded' THEN {$prefix}fluentform_transactions.payment_total ELSE 0 END) as refunded_amount"),
                wpFluent()->raw("(SUM(CASE WHEN {$prefix}fluentform_transactions.status = 'paid' THEN {$prefix}fluentform_transactions.payment_total ELSE 0 END) - SUM(CASE WHEN {$prefix}fluentform_transactions.status = 'refunded' THEN {$prefix}fluentform_transactions.payment_total ELSE 0 END)) as net_revenue"),
                wpFluent()->raw("COUNT(*) as transaction_count")
            )
            ->whereBetween('fluentform_transactions.created_at', [$startDate, $endDate])
            ->whereIn('fluentform_transactions.status', ['paid', 'pending', 'refunded'])
            ->whereIn('fluentform_transactions.transaction_type', ['onetime', 'subscription']);

        if ($formId) {
            $query->where('fluentform_transactions.form_id', $formId);
        }

        $query->groupBy('fluentform_transactions.transaction_type')
            ->orderBy('net_revenue', 'DESC');

        $results = $query->paginate($perPage, ['*'], 'page', $currentPage);
        $total = $results->total();

        // Get totals for all data
        $totalsQuery = wpFluent()
            ->table('fluentform_transactions')
            ->select(
                wpFluent()->raw("SUM(CASE WHEN {$prefix}fluentform_transactions.status = 'paid' THEN {$prefix}fluentform_transactions.payment_total ELSE 0 END) as paid"),
                wpFluent()->raw("SUM(CASE WHEN {$prefix}fluentform_transactions.status = 'pending' THEN {$prefix}fluentform_transactions.payment_total ELSE 0 END) as pending"),
                wpFluent()->raw("SUM(CASE WHEN {$prefix}fluentform_transactions.status = 'refunded' THEN {$prefix}fluentform_transactions.payment_total ELSE 0 END) as refunded"),
                wpFluent()->raw("(SUM(CASE WHEN {$prefix}fluentform_transactions.status = 'paid' THEN {$prefix}fluentform_transactions.payment_total ELSE 0 END) - SUM(CASE WHEN {$prefix}fluentform_transactions.status = 'refunded' THEN {$prefix}fluentform_transactions.payment_total ELSE 0 END)) as net")
            )
            ->whereBetween('fluentform_transactions.created_at', [$startDate, $endDate])
            ->whereIn('fluentform_transactions.status', ['paid', 'pending', 'refunded']);

        if ($formId) {
            $totalsQuery->where('fluentform_transactions.form_id', $formId);
        }

        $totals = $totalsQuery->first();
        if ($totals) {
            $formattedTotals = [
                'paid'     => round($totals->paid / 100, 2),
                'pending'  => round($totals->pending / 100, 2),
                'refunded' => round($totals->refunded / 100, 2),
                'net'      => round($totals->net / 100, 2)
            ];
        } else {
            $formattedTotals = [
                'paid'     => 0,
                'pending'  => 0,
                'refunded' => 0,
                'net'      => 0
            ];
        }

        $formattedResults = [];
        foreach ($results->items() as $row) {
            $typeName = $row->transaction_type === 'onetime' ? 'One-time Payment' : 'Subscription';
            $formattedResults[] = [
                'payment_type'      => $row->transaction_type,
                'payment_type_name' => $typeName,
                'paid_amount'       => round($row->paid_amount / 100, 2),
                'pending_amount'    => round($row->pending_amount / 100, 2),
                'refunded_amount'   => round($row->refunded_amount / 100, 2),
                'net_revenue'       => round($row->net_revenue / 100, 2),
                'transaction_count' => $row->transaction_count
            ];
        }

        return [
            'data'   => $formattedResults,
            'totals' => $formattedTotals,
            'total'  => $total
        ];
    }

    public static function getSubmissionAnalysisByForms($startDate, $endDate, $perPage = 10, $currentPage = 1)
    {
        global $wpdb;
        $prefix = $wpdb->prefix;
        $query = wpFluent()
            ->table('fluentform_submissions')
            ->join('fluentform_forms', 'fluentform_submissions.form_id', '=', 'fluentform_forms.id')
            ->select(
                'fluentform_forms.id as form_id',
                'fluentform_forms.title as form_title',
                wpFluent()->raw('COUNT(*) as total_submissions'),
                wpFluent()->raw("SUM(CASE WHEN {$prefix}fluentform_submissions.status = 'read' THEN 1 ELSE 0 END) as read_submissions"),
                wpFluent()->raw("SUM(CASE WHEN {$prefix}fluentform_submissions.status = 'unread' THEN 1 ELSE 0 END) as unread_submissions"),
                wpFluent()->raw("SUM(CASE WHEN {$prefix}fluentform_submissions.status = 'spam' THEN 1 ELSE 0 END) as spam_submissions"),
                wpFluent()->raw("ROUND((SUM(CASE WHEN {$prefix}fluentform_submissions.status = 'read' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as conversion_rate")
            )
            ->whereBetween('fluentform_submissions.created_at', [$startDate, $endDate])
            ->groupBy('fluentform_forms.id', 'fluentform_forms.title')
            ->orderBy('total_submissions', 'DESC');

        $results = $query->paginate($perPage, ['*'], 'page', $currentPage);
        $total = $results->total();

        // Get totals for all data
        $totalsQuery = wpFluent()
            ->table('fluentform_submissions')
            ->select(
                wpFluent()->raw('COUNT(*) as `total`'),
                wpFluent()->raw("SUM(CASE WHEN {$prefix}fluentform_submissions.status = 'read' THEN 1 ELSE 0 END) as `read_count`"),
                wpFluent()->raw("SUM(CASE WHEN {$prefix}fluentform_submissions.status = 'unread' THEN 1 ELSE 0 END) as `unread_count`"),
                wpFluent()->raw("SUM(CASE WHEN {$prefix}fluentform_submissions.status = 'spam' THEN 1 ELSE 0 END) as `spam_count`")
            )
            ->whereBetween('fluentform_submissions.created_at', [$startDate, $endDate]);

        $totalsData = $totalsQuery->first();
        if ($totalsData) {
            $totals = [
                'total'    => (int)$totalsData->total,
                'read'     => (int)$totalsData->read_count,
                'unread'   => (int)$totalsData->unread_count,
                'spam'     => (int)$totalsData->spam_count,
                'readRate' => $totalsData->total > 0 ? round(($totalsData->read_count / $totalsData->total) * 100,
                    2) : 0
            ];
        } else {
            $totals = [
                'total'    => 0,
                'read'     => 0,
                'unread'   => 0,
                'spam'     => 0,
                'readRate' => 0
            ];
        }

        $formattedResults = [];
        foreach ($results->items() as $row) {
            $formattedResults[] = [
                'form_id'            => $row->form_id,
                'form_title'         => $row->form_title ?: 'Untitled Form',
                'total_submissions'  => (int)$row->total_submissions,
                'read_submissions'   => (int)$row->read_submissions,
                'unread_submissions' => (int)$row->unread_submissions,
                'spam_submissions'   => (int)$row->spam_submissions,
                'conversion_rate'    => (float)$row->conversion_rate
            ];
        }

        return [
            'data'   => $formattedResults,
            'total'  => $total,
            'totals' => $totals
        ];
    }

    public static function getSubmissionAnalysisBySource(
        $startDate,
        $endDate,
        $formId = null,
        $perPage = 10,
        $currentPage = 1
    ) {
        global $wpdb;
        $prefix = $wpdb->prefix;
        // Build the query using wpFluent with full table names
        $query = wpFluent()
            ->table('fluentform_submissions')
            ->select(
                'fluentform_submissions.source_url',
                wpFluent()->raw('COUNT(*) as total_submissions'),
                wpFluent()->raw("SUM(CASE WHEN {$prefix}fluentform_submissions.status = 'read' THEN 1 ELSE 0 END) as read_submissions"),
                wpFluent()->raw("SUM(CASE WHEN {$prefix}fluentform_submissions.status = 'unread' THEN 1 ELSE 0 END) as unread_submissions"),
                wpFluent()->raw("SUM(CASE WHEN {$prefix}fluentform_submissions.status = 'spam' THEN 1 ELSE 0 END) as spam_submissions"),
                wpFluent()->raw("ROUND((SUM(CASE WHEN {$prefix}fluentform_submissions.status = 'read' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as conversion_rate")
            )
            ->whereBetween('fluentform_submissions.created_at', [$startDate, $endDate]);

        if ($formId) {
            $query->where('fluentform_submissions.form_id', $formId);
        }
        // Group by source URL and order by total submissions descending
        $query->groupBy('fluentform_submissions.source_url')
            ->orderBy('total_submissions', 'DESC');

        $results = $query->paginate($perPage, ['*'], 'page', $currentPage);
        $total = $results->total();

        // Get totals for all data
        $totalsQuery = wpFluent()
            ->table('fluentform_submissions')
            ->select(
                wpFluent()->raw('COUNT(*) as `total`'),
                wpFluent()->raw("SUM(CASE WHEN {$prefix}fluentform_submissions.status = 'read' THEN 1 ELSE 0 END) as `read_count`"),
                wpFluent()->raw("SUM(CASE WHEN {$prefix}fluentform_submissions.status = 'unread' THEN 1 ELSE 0 END) as `unread_count`"),
                wpFluent()->raw("SUM(CASE WHEN {$prefix}fluentform_submissions.status = 'spam' THEN 1 ELSE 0 END) as `spam_count`")
            )
            ->whereBetween('fluentform_submissions.created_at', [$startDate, $endDate]);

        if ($formId) {
            $totalsQuery->where('fluentform_submissions.form_id', $formId);
        }

        $totalsData = $totalsQuery->first();
        if ($totalsData) {
            $totals = [
                'total'    => (int)$totalsData->total,
                'read'     => (int)$totalsData->read_count,
                'unread'   => (int)$totalsData->unread_count,
                'spam'     => (int)$totalsData->spam_count,
                'readRate' => $totalsData->total > 0 ? round(($totalsData->read_count / $totalsData->total) * 100,
                    2) : 0
            ];
        } else {
            $totals = [
                'total'    => 0,
                'read'     => 0,
                'unread'   => 0,
                'spam'     => 0,
                'readRate' => 0
            ];
        }

        $formattedResults = [];
        foreach ($results->items() as $row) {
            $formattedResults[] = [
                'source_url'         => $row->source_url ?: 'Direct Access',
                'total_submissions'  => (int)$row->total_submissions,
                'read_submissions'   => (int)$row->read_submissions,
                'unread_submissions' => (int)$row->unread_submissions,
                'spam_submissions'   => (int)$row->spam_submissions,
                'conversion_rate'    => (float)$row->conversion_rate
            ];
        }

        return [
            'data'   => $formattedResults,
            'total'  => $total,
            'totals' => $totals
        ];
    }

    public static function getSubmissionAnalysisByEmail(
        $startDate,
        $endDate,
        $formId = null,
        $perPage = 10,
        $currentPage = 1
    ) {
        global $wpdb;
        $prefix = $wpdb->prefix;
        $formCondition = $formId ? "AND {$prefix}fluentform_submissions.form_id = ?" : "";
        $bindings = [$startDate, $endDate];
        if ($formId) {
            $bindings[] = $formId;
        }

        // First, get the total count for pagination
        $countQuery = "SELECT COUNT(*) as total FROM (
            SELECT
                COALESCE(
                    NULLIF(JSON_UNQUOTE(JSON_EXTRACT({$prefix}fluentform_submissions.response, '$.email')), ''),
                    NULLIF(JSON_UNQUOTE(JSON_EXTRACT({$prefix}fluentform_submissions.response, '$.email_1')), ''),
                    NULLIF(JSON_UNQUOTE(JSON_EXTRACT({$prefix}fluentform_submissions.response, '$.user_email')), ''),
                    'No Email'
                ) as email
            FROM {$prefix}fluentform_submissions
            WHERE {$prefix}fluentform_submissions.created_at BETWEEN ? AND ?
            {$formCondition}
            GROUP BY COALESCE(
                NULLIF(JSON_UNQUOTE(JSON_EXTRACT({$prefix}fluentform_submissions.response, '$.email')), ''),
                NULLIF(JSON_UNQUOTE(JSON_EXTRACT({$prefix}fluentform_submissions.response, '$.email_1')), ''),
                NULLIF(JSON_UNQUOTE(JSON_EXTRACT({$prefix}fluentform_submissions.response, '$.user_email')), ''),
                'No Email'
            )
        ) as email_count";

        $totalResult = wpFluent()->selectOne($countQuery, $bindings);
        $total = $totalResult ? (int)$totalResult->total : 0;

        // Now get the actual data with pagination
        $offset = ($currentPage - 1) * $perPage;
        $dataQuery = "SELECT
            COALESCE(
                NULLIF(JSON_UNQUOTE(JSON_EXTRACT({$prefix}fluentform_submissions.response, '$.email')), ''),
                NULLIF(JSON_UNQUOTE(JSON_EXTRACT({$prefix}fluentform_submissions.response, '$.email_1')), ''),
                NULLIF(JSON_UNQUOTE(JSON_EXTRACT({$prefix}fluentform_submissions.response, '$.user_email')), ''),
                'No Email'
            ) as email,
            COUNT(*) as total_submissions,
            SUM(CASE WHEN {$prefix}fluentform_submissions.status = 'read' THEN 1 ELSE 0 END) as read_submissions,
            SUM(CASE WHEN {$prefix}fluentform_submissions.status = 'unread' THEN 1 ELSE 0 END) as unread_submissions,
            SUM(CASE WHEN {$prefix}fluentform_submissions.status = 'spam' THEN 1 ELSE 0 END) as spam_submissions,
            ROUND((SUM(CASE WHEN {$prefix}fluentform_submissions.status = 'read' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as conversion_rate
        FROM {$prefix}fluentform_submissions
        WHERE {$prefix}fluentform_submissions.created_at BETWEEN ? AND ?
        {$formCondition}
        GROUP BY COALESCE(
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT({$prefix}fluentform_submissions.response, '$.email')), ''),
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT({$prefix}fluentform_submissions.response, '$.email_1')), ''),
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT({$prefix}fluentform_submissions.response, '$.user_email')), ''),
            'No Email'
        )
        ORDER BY total_submissions DESC
        LIMIT {$perPage} OFFSET {$offset}";

        $results = wpFluent()->select($dataQuery, $bindings);

        // Get totals for all data
        $totalsQuery = wpFluent()
            ->table('fluentform_submissions')
            ->select(
                wpFluent()->raw('COUNT(*) as `total`'),
                wpFluent()->raw("SUM(CASE WHEN {$prefix}fluentform_submissions.status = 'read' THEN 1 ELSE 0 END) as `read_count`"),
                wpFluent()->raw("SUM(CASE WHEN {$prefix}fluentform_submissions.status = 'unread' THEN 1 ELSE 0 END) as `unread_count`"),
                wpFluent()->raw("SUM(CASE WHEN {$prefix}fluentform_submissions.status = 'spam' THEN 1 ELSE 0 END) as `spam_count`")
            )
            ->whereBetween('fluentform_submissions.created_at', [$startDate, $endDate]);

        if ($formId) {
            $totalsQuery->where('fluentform_submissions.form_id', $formId);
        }

        $totalsData = $totalsQuery->first();
        if ($totalsData) {
            $totals = [
                'total'    => (int)$totalsData->total,
                'read'     => (int)$totalsData->read_count,
                'unread'   => (int)$totalsData->unread_count,
                'spam'     => (int)$totalsData->spam_count,
                'readRate' => $totalsData->total > 0 ? round(($totalsData->read_count / $totalsData->total) * 100,
                    2) : 0
            ];
        } else {
            $totals = [
                'total'    => 0,
                'read'     => 0,
                'unread'   => 0,
                'spam'     => 0,
                'readRate' => 0
            ];
        }

        $formattedResults = [];
        foreach ($results as $row) {
            $formattedResults[] = [
                'email'              => $row->email,
                'total_submissions'  => (int)$row->total_submissions,
                'read_submissions'   => (int)$row->read_submissions,
                'unread_submissions' => (int)$row->unread_submissions,
                'spam_submissions'   => (int)$row->spam_submissions,
                'conversion_rate'    => (float)$row->conversion_rate
            ];
        }

        return [
            'data'   => $formattedResults,
            'total'  => $total,
            'totals' => $totals
        ];
    }

    public static function getSubmissionAnalysisByCountry(
        $startDate,
        $endDate,
        $formId = null,
        $perPage = 10,
        $currentPage = 1
    ) {
        if (apply_filters('fluentform/disable_submission_country_detection', false, $formId)) {
            return [
                'is_country_disable' => __('Country detection is disabled. Please enable it to see the submission analysis by country.', 'fluentform')
            ];
        }

        global $wpdb;
        $prefix = $wpdb->prefix;
        $query = wpFluent()
            ->table('fluentform_submissions')
            ->select(
                'fluentform_submissions.country',
                wpFluent()->raw('COUNT(*) as total_submissions'),
                wpFluent()->raw("SUM(CASE WHEN {$prefix}fluentform_submissions.status = 'read' THEN 1 ELSE 0 END) as read_submissions"),
                wpFluent()->raw("SUM(CASE WHEN {$prefix}fluentform_submissions.status = 'unread' THEN 1 ELSE 0 END) as unread_submissions"),
                wpFluent()->raw("SUM(CASE WHEN {$prefix}fluentform_submissions.status = 'spam' THEN 1 ELSE 0 END) as spam_submissions"),
                wpFluent()->raw("ROUND((SUM(CASE WHEN {$prefix}fluentform_submissions.status = 'read' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as conversion_rate")
            )
            ->whereBetween('fluentform_submissions.created_at', [$startDate, $endDate]);

        if ($formId) {
            $query->where('fluentform_submissions.form_id', $formId);
        }

        // Group by country and order by total submissions descending
        $query->groupBy('fluentform_submissions.country')
            ->orderBy('total_submissions', 'DESC');

        $results = $query->paginate($perPage, ['*'], 'page', $currentPage);

        $total = $results->total();

        // Get totals for all data
        $totalsQuery = wpFluent()
            ->table('fluentform_submissions')
            ->select(
                wpFluent()->raw('COUNT(*) as `total`'),
                wpFluent()->raw("SUM(CASE WHEN {$prefix}fluentform_submissions.status = 'read' THEN 1 ELSE 0 END) as `read_count`"),
                wpFluent()->raw("SUM(CASE WHEN {$prefix}fluentform_submissions.status = 'unread' THEN 1 ELSE 0 END) as `unread_count`"),
                wpFluent()->raw("SUM(CASE WHEN {$prefix}fluentform_submissions.status = 'spam' THEN 1 ELSE 0 END) as `spam_count`")
            )
            ->whereBetween('fluentform_submissions.created_at', [$startDate, $endDate]);

        if ($formId) {
            $totalsQuery->where('fluentform_submissions.form_id', $formId);
        }

        $totalsData = $totalsQuery->first();
        if ($totalsData) {
            $totals = [
                'total'    => (int)$totalsData->total,
                'read'     => (int)$totalsData->read_count,
                'unread'   => (int)$totalsData->unread_count,
                'spam'     => (int)$totalsData->spam_count,
                'readRate' => $totalsData->total > 0 ? round(($totalsData->read_count / $totalsData->total) * 100,
                    2) : 0
            ];
        } else {
            $totals = [
                'total'    => 0,
                'read'     => 0,
                'unread'   => 0,
                'spam'     => 0,
                'readRate' => 0,
            ];
        }

        $formattedResults = [];
        $countryNames = getFluentFormCountryList();
        foreach ($results->items() as $row) {
            $countryCode = null;
            if ($row->country && strlen($row->country) === 2 && ctype_alpha($row->country)) {
                $upper = strtoupper($row->country);
                if (isset($countryNames[$upper])) {
                    // Stored as ISO2 code
                    $countryCode = strtolower($upper);
                }
            }
            $formattedResults[] = [
                'country'            => $row->country ? Arr::get($countryNames, strtoupper($row->country), $row->country) : 'Unknown',
                'country_code'       => $countryCode, // ISO2 lower-case (e.g., 'us', 'gb') for flags
                'total_submissions'  => (int)$row->total_submissions,
                'read_submissions'   => (int)$row->read_submissions,
                'unread_submissions' => (int)$row->unread_submissions,
                'spam_submissions'   => (int)$row->spam_submissions,
                'conversion_rate'    => (float)$row->conversion_rate
            ];
        }

        return [
            'data'   => $formattedResults,
            'total'  => $total,
            'totals' => $totals
        ];
    }

    public static function getSubmissionAnalysisByDate(
        $startDate,
        $endDate,
        $formId = null,
        $perPage = 10,
        $currentPage = 1
    ) {
        global $wpdb;
        $prefix = $wpdb->prefix;
        $formCondition = $formId ? "AND {$prefix}fluentform_submissions.form_id = ?" : "";
        $bindings = [$startDate, $endDate];
        if ($formId) {
            $bindings[] = $formId;
        }

        // First, get the total count for pagination
        $countQuery = "SELECT COUNT(*) as total FROM (
            SELECT DATE({$prefix}fluentform_submissions.created_at) as submission_date
            FROM {$prefix}fluentform_submissions
            WHERE {$prefix}fluentform_submissions.created_at BETWEEN ? AND ?
            {$formCondition}
            GROUP BY DATE({$prefix}fluentform_submissions.created_at)
        ) as date_count";

        $totalResult = wpFluent()->selectOne($countQuery, $bindings);
        $total = $totalResult ? (int)$totalResult->total : 0;

        $offset = ($currentPage - 1) * $perPage;
        $dataQuery = "SELECT
            DATE({$prefix}fluentform_submissions.created_at) as submission_date,
            COUNT(*) as total_submissions,
            SUM(CASE WHEN {$prefix}fluentform_submissions.status = 'read' THEN 1 ELSE 0 END) as read_submissions,
            SUM(CASE WHEN {$prefix}fluentform_submissions.status = 'unread' THEN 1 ELSE 0 END) as unread_submissions,
            SUM(CASE WHEN {$prefix}fluentform_submissions.status = 'spam' THEN 1 ELSE 0 END) as spam_submissions,
            ROUND((SUM(CASE WHEN {$prefix}fluentform_submissions.status = 'read' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as conversion_rate
        FROM {$prefix}fluentform_submissions
        WHERE {$prefix}fluentform_submissions.created_at BETWEEN ? AND ?
        {$formCondition}
        GROUP BY DATE({$prefix}fluentform_submissions.created_at)
        ORDER BY submission_date DESC
        LIMIT {$perPage} OFFSET {$offset}";

        $results = wpFluent()->select($dataQuery, $bindings);

        // Get totals for all data
        $totalsQuery = wpFluent()
            ->table('fluentform_submissions')
            ->select(
                wpFluent()->raw('COUNT(*) as `total`'),
                wpFluent()->raw("SUM(CASE WHEN {$prefix}fluentform_submissions.status = 'read' THEN 1 ELSE 0 END) as `read_count`"),
                wpFluent()->raw("SUM(CASE WHEN {$prefix}fluentform_submissions.status = 'unread' THEN 1 ELSE 0 END) as `unread_count`"),
                wpFluent()->raw("SUM(CASE WHEN {$prefix}fluentform_submissions.status = 'spam' THEN 1 ELSE 0 END) as `spam_count`")
            )
            ->whereBetween('fluentform_submissions.created_at', [$startDate, $endDate]);

        if ($formId) {
            $totalsQuery->where('fluentform_submissions.form_id', $formId);
        }

        $totalsData = $totalsQuery->first();
        if ($totalsData) {
            $totals = [
                'total'    => (int)$totalsData->total,
                'read'     => (int)$totalsData->read_count,
                'unread'   => (int)$totalsData->unread_count,
                'spam'     => (int)$totalsData->spam_count,
                'readRate' => $totalsData->total > 0 ? round(($totalsData->read_count / $totalsData->total) * 100,
                    2) : 0
            ];
        } else {
            $totals = [
                'total'    => 0,
                'read'     => 0,
                'unread'   => 0,
                'spam'     => 0,
                'readRate' => 0,
            ];
        }

        $formattedResults = [];
        foreach ($results as $row) {
            $formattedResults[] = [
                'submission_date'    => $row->submission_date,
                'total_submissions'  => (int)$row->total_submissions,
                'read_submissions'   => (int)$row->read_submissions,
                'unread_submissions' => (int)$row->unread_submissions,
                'spam_submissions'   => (int)$row->spam_submissions,
                'conversion_rate'    => (float)$row->conversion_rate
            ];
        }

        return [
            'data'   => $formattedResults,
            'total'  => $total,
            'totals' => $totals
        ];
    }


}

