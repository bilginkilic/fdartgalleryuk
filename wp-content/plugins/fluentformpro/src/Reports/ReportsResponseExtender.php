<?php

namespace FluentFormPro\Reports;

use FluentFormPro\Reports\ReportHelperPro as ReportHelper;
use FluentForm\Framework\Helpers\ArrayHelper as Arr;
use FluentForm\Framework\Support\Sanitizer;

/**
 * ReportsResponseExtender
 * Extends Fluent Forms report responses
 */
class ReportsResponseExtender
{
    /**
     * Register filters
     */
    public function init()
    {
        add_filter('fluentform/reports/completion_rate', [$this, 'completionRate'], 10, 2);
        add_filter('fluentform/reports/heatmap_data', [$this, 'heatmapData'], 10, 2);
        add_filter('fluentform/reports/country_heatmap', [$this, 'countryHeatmap'], 10, 2);
        add_filter('fluentform/reports/subscriptions', [$this, 'subscriptions'], 10, 2);
        add_filter('fluentform/reports/submissions_analysis', [$this, 'submissionAnalysis'], 10, 2);
        add_filter('fluentform/reports/revenue_analysis', [$this, 'revenueAnalysis'], 10, 2);
    }

    /**
     *  Completion rate
     *
     * @param array $data   Current response payload
     * @param array $params Original request params
     *
     * @return array
     */
    public function completionRate($data, $params)
    {
        [$start, $end, $formId] = $this->normalizeParams($params);
        $data['completion_rate'] = ReportHelper::getCompletionRateData($start, $end, $formId);
        return $data;
    }

    /**
     *  Heatmap data
     *
     * @param array $data
     * @param array $params
     *
     * @return array
     */
    public function heatmapData($data, $params)
    {
        [$start, $end, $formId] = $this->normalizeParams($params);
        $data['heatmap_data'] = ReportHelper::getSubmissionHeatmap($start, $end, $formId);
        return $data;
    }

    /**
     *  Country heatmap
     *
     * @param array $data
     * @param array $params
     *
     * @return array
     */
    public function countryHeatmap($data, $params)
    {
        [$start, $end, $formId] = $this->normalizeParams($params);
        $data['country_heatmap'] = ReportHelper::getSubmissionsByCountry($start, $end, $formId);
        return $data;
    }

    /**
     *  Subscriptions
     *
     * @param array $data
     * @param array $params
     *
     * @return array
     */
    public function subscriptions($data, $params)
    {
        [$start, $end, $formId] = $this->normalizeParams($params);
        $data['subscriptions'] = ReportHelper::getSubscriptions($start, $end, $formId);
        return $data;
    }



    /**
     * Submission Analysis
     *
     * @param $data
     * @param $params
     *
     * @return array
     * @throws \Exception
     */
    public function submissionAnalysis($data, $params)
    {
        $params = Sanitizer::sanitize($params, [
            'group_by'   => 'sanitizeTextField',
            'start_date' => 'sanitizeTextField',
            'end_date'   => 'sanitizeTextField'
        ]);

        $groupBy = Arr::get($params, 'group_by', 'forms');
        $startDate = Arr::get($params, 'start_date');
        $endDate = Arr::get($params, 'end_date');
        $formId = intval(Arr::get($params, 'form_id'));
        $perPage = intval(Arr::get($params, 'per_page', 10));
        $currentPage = intval(Arr::get($params, 'page', 1));

        if (!$startDate || !$endDate) {
            $endDate = current_time('Y-m-d H:i:s');
            $startDate = date('Y-m-d H:i:s', strtotime('-30 days', strtotime($endDate)));
        }

        try {
            switch ($groupBy) {
                case 'forms':
                    $data = ReportHelper::getSubmissionAnalysisByForms($startDate, $endDate, $perPage, $currentPage);
                    break;
                case 'submission_source':
                    $data = ReportHelper::getSubmissionAnalysisBySource($startDate, $endDate, $formId, $perPage,
                        $currentPage);
                    break;
                case 'email':
                    $data = ReportHelper::getSubmissionAnalysisByEmail($startDate, $endDate, $formId, $perPage,
                        $currentPage);
                    break;
                case 'country':
                    $data = ReportHelper::getSubmissionAnalysisByCountry($startDate, $endDate, $formId, $perPage,
                        $currentPage);
                    break;
                case 'submission_date':
                    $data = ReportHelper::getSubmissionAnalysisByDate($startDate, $endDate, $formId, $perPage,
                        $currentPage);
                    break;
                default:
                    throw new \Exception('Invalid group_by parameter');
            }

            return [
                'data'               => Arr::get($data, 'data', []),
                'total'              => Arr::get($data, 'total', 0),
                'totals'             => Arr::get($data, 'totals', []),
                'is_country_disable' => Arr::isTrue($data, 'is_country_disable'),
                'group_by'           => $groupBy,
                'date_range'         => [
                    'start' => $startDate,
                    'end'   => $endDate
                ]
            ];
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * Revenue Analysis
     * @param array $data
     * * @param array $params
     * @throws \Exception
     * @return array
     */
    public function revenueAnalysis($data, $params)
    {
        $params = Sanitizer::sanitize($params, [
            'start_date' => 'sanitizeTextField',
            'end_date' => 'sanitizeTextField',
            'group_by' => 'sanitizeTextField',
        ]);

        $groupBy = Arr::get($params, 'group_by', 'forms');
        $startDate = Arr::get($params, 'start_date');
        $endDate = Arr::get($params, 'end_date');
        $formId = intval(Arr::get($params, 'form_id'));
        $perPage = intval(Arr::get($params, 'per_page', 10));
        $currentPage = intval(Arr::get($params, 'page', 1));

        if (!$startDate || !$endDate) {
            $endDate = current_time('Y-m-d H:i:s');
            $startDate = date('Y-m-d H:i:s', strtotime('-30 days', strtotime($endDate)));
        }

        try {
            switch ($groupBy) {
                case 'forms':
                    $data = ReportHelper::getNetRevenueByForms($startDate, $endDate, $perPage, $currentPage);
                    break;
                case 'payment_method':
                    $data = ReportHelper::getNetRevenueByPaymentMethod($startDate, $endDate, $formId, $perPage, $currentPage);
                    break;
                case 'payment_type':
                    $data = ReportHelper::getNetRevenueByPaymentType($startDate, $endDate, $formId, $perPage, $currentPage);
                    break;
                default:
                    throw new \Exception('Invalid group_by parameter');
            }

            return [
                'data' => $data['data'],
                'totals' => $data['totals'],
                'total' => $data['total'],
                'group_by' => $groupBy,
                'date_range' => [
                    'start' => $startDate,
                    'end' => $endDate
                ]
            ];
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * Normalize params to core defaults (last 30 days if missing)
     */
    private function normalizeParams($params)
    {
        $params = Sanitizer::sanitize($params, [
            'start_date' => 'sanitizeTextField',
            'end_date'   => 'sanitizeTextField',
        ]);

        $startDate = (string) Arr::get($params, 'start_date', '');
        $endDate   = (string) Arr::get($params, 'end_date', '');
        $formId    = (int) Arr::get($params, 'form_id', 0);

        if (!$startDate || !$endDate) {
            $now = new \DateTime();
            $endDate = $now->format('Y-m-d 23:59:59');
            $thirtyDaysAgo = new \DateTime();
            $thirtyDaysAgo->modify('-30 days');
            $startDate = $thirtyDaysAgo->format('Y-m-d 00:00:00');
        }

        return [$startDate, $endDate, $formId];
    }
}

