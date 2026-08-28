<?php

namespace FluentFormPdf\Classes\Report;

use FluentForm\App\Services\Report\ReportService;
use FluentForm\Framework\Helpers\ArrayHelper as Arr;
use FluentForm\App\Models\Form;
use FluentForm\Framework\Support\Sanitizer;
use FluentFormPdf\Classes\Controller\AvailableOptions;
use FluentFormPdf\Classes\Controller\FontManager;
use FluentFormPro\Reports\ReportHelperPro;

class ReportPdfGenerator
{
    /**
     * Generate Report PDF
     *
     * @return void
     */
    public function generatePdf($data)
    {
        // Verify nonce similar to how PDF plugin does it
        if (!wp_verify_nonce(Arr::get($data, 'fluent_forms_admin_nonce'), 'fluent_forms_admin_nonce')) {
            return;
        }

        // Check if PDF plugin is available
        if (
            !defined('FLUENTFORM') &&
            !defined('FLUENTFORM_PDF_VERSION') &&
            !class_exists('\FluentFormPdf\Classes\Controller\GlobalPdfManager')
        ) {
            return;
        }

        // Load mPDF if not already loaded
        if (!class_exists('\Mpdf\Mpdf')) {
            if (defined('FLUENTFORM_PDF_VERSION')) {
                require_once FLUENTFORM_PDF_PATH . 'vendor/autoload.php';
            } else {
                return;
            }
        }

        $data = Sanitizer::sanitize($data, [
            'start_date' => 'sanitizeTextField',
            'end_date' => 'sanitizeTextField',
            'form_id' => 'sanitizeTextField'
        ]);

        $startDate = Arr::get($data, 'start_date');
        $endDate = Arr::get($data, 'end_date');
        $formId = intval(Arr::get($data, 'form_id'));

        // Set default date range if not provided
        if (!$startDate || !$endDate) {
            $now = new \DateTime();
            $endDate = $now->format('Y-m-d 23:59:59');

            $thirtyDaysAgo = new \DateTime();
            $thirtyDaysAgo->modify('-30 days');
            $startDate = $thirtyDaysAgo->format('Y-m-d 00:00:00');
        }

        // Get report service instance
        $reportService = new ReportService();

        // Allow filtering of report components to include
        $defaultComponents = ['form_stats', 'top_performing_forms', 'payment_types', 'api_logs'];
        
        // Add Pro-specific components only if Fluent Forms Pro is active
        if (defined('FLUENTFORMPRO')) {
            $defaultComponents = array_merge($defaultComponents, [
                'completion_rate',
                'heatmap_data', 
                'country_heatmap',
                'subscriptions'
            ]);
        }
        
        $components = apply_filters('fluentform/report_pdf_components', $defaultComponents, $data);

        // Get comprehensive report data using component-specific methods
        $reportData = ['reports' => []];
        $commonParams = [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'form_id' => $formId
        ];

        // Fetch each component individually
        foreach ($components as $component) {
            try {
                switch ($component) {
                    case 'form_stats':
                        $data = $reportService->getFormStats($commonParams);
                        $reportData['reports']['form_stats'] = $data['form_stats'];
                        break;
                    case 'top_performing_forms':
                        $data = $reportService->getTopPerformingForms($commonParams);
                        $reportData['reports']['top_performing_forms'] = $data['top_performing_forms'];
                        break;
                    case 'completion_rate':
                        if (defined('FLUENTFORMPRO') && class_exists('\FluentFormPro\Reports\ReportHelperPro')) {
                            $data = ReportHelperPro::getCompletionRateData($startDate, $endDate, $formId);
                            $reportData['reports']['completion_rate'] = $data;
                        } else {
                            continue 2;
                        }
                        break;
                    case 'payment_types':
                        $data = $reportService->getPaymentTypes($commonParams);
                        $reportData['reports']['payment_types'] = $data['payment_types'];
                        break;
                    case 'api_logs':
                        $data = $reportService->getApiLogs($commonParams);
                        $reportData['reports']['api_logs'] = $data['api_logs'];
                        break;
                    case 'overview_chart':
                        $data = $reportService->getOverviewChart($commonParams);
                        $reportData['reports']['overview_chart'] = $data['overview_chart'];
                        break;
                    case 'revenue_chart':
                        $data = $reportService->getRevenueChart($commonParams);
                        $reportData['reports']['revenue_chart'] = $data['revenue_chart'];
                        break;
                    case 'heatmap_data':
                        if (defined('FLUENTFORMPRO') && class_exists('\FluentFormPro\Reports\ReportHelperPro')) {
                            $data = ReportHelperPro::getSubmissionHeatmap($startDate, $endDate, $formId);
                            $reportData['reports']['heatmap_data'] = $data;
                        } else {
                            continue 2;
                        }
                        break;
                    case 'country_heatmap':
                        if (defined('FLUENTFORMPRO') && class_exists('\FluentFormPro\Reports\ReportHelperPro')) {
                            $data = ReportHelperPro::getSubmissionsByCountry($startDate, $endDate, $formId);
                            $reportData['reports']['country_heatmap'] = $data;
                        } else {
                            continue 2;
                        }
                        break;
                    case 'subscriptions':
                        if (defined('FLUENTFORMPRO') && class_exists('\FluentFormPro\Reports\ReportHelperPro')) {
                            $data = ReportHelperPro::getSubscriptions($startDate, $endDate, $formId);
                            $reportData['reports']['subscriptions'] = $data;
                        } else {
                            continue 2;
                        }
                        break;
                }
            } catch (\Exception $e) {
                // Log error but continue with other components
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging only when WP_DEBUG is enabled
                    error_log("PDF Report component '{$component}' failed: " . $e->getMessage());
                }
            }
        }

        $reportData = apply_filters('fluentform/report_pdf_data', $reportData, $data);

        $submissionsData = $reportService->submissions([
            'start_date' => $startDate,
            'end_date' => $endDate,
            'form_id' => $formId,
            'group_by' => 'forms'
        ]);

        $submissionsData = apply_filters('fluentform/report_pdf_submissions_data', $submissionsData, $data);

        $htmlContent = $this->generateReportHtml($reportData, $submissionsData, $startDate, $endDate, $formId);

        $htmlContent = apply_filters('fluentform/report_pdf_html_content', $htmlContent, $reportData, $submissionsData, $data);

        // Create PDF using Fluent Forms PDF
        $this->createReportPdf($htmlContent, $startDate, $endDate);
    }

    /**
     * Generate HTML content for the comprehensive report
     *
     * @param array $reportData
     * @param array $submissionsData
     * @param string $startDate
     * @param string $endDate
     * @param int $formId
     * @return string
     */
    private function generateReportHtml($reportData, $submissionsData, $startDate, $endDate, $formId)
    {
        $reports = $reportData['reports'];
        $formStats = Arr::get($reports, 'form_stats', []);

        // Format dates for display
        // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date -- not performing any date operations
        $startDateFormatted = date('M j, Y', strtotime($startDate));
        // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date -- not performing any date operations
        $endDateFormatted = date('M j, Y', strtotime($endDate));

        $html = '<div style="font-family: Arial, sans-serif; color: #333;">';

        // Header with logo
        $html .= '<div style="text-align: center; margin-bottom: 30px; border-bottom: 2px solid #007cba; padding-bottom: 20px;">';

        // Add FluentForm logo
        $logoUrl = fluentformMix('img/fluentform-logo.svg');
        if ($logoUrl) {
            $html .= '<div style="margin-bottom: 20px;">';
            $html .= '<img src="' . esc_url($logoUrl) . '" alt="FluentForm Logo" style="height: 40px; width: auto;" />';
            $html .= '</div>';
        }

        $html .= '<h1 style="color: #007cba; margin: 0; font-size: 28px;">Fluent Forms Report</h1>';
        $html .= '<p style="color: #888; margin: 10px 0 0 0; font-size: 14px;">' . $startDateFormatted . ' - ' . $endDateFormatted . '</p>';
        if ($formId) {
            $form = Form::find($formId);
            if ($form) {
                $html .= '<p style="color: #666; margin: 5px 0 0 0; font-size: 14px;">Form: #' . $formId . ' - ' . esc_html($form->title) . '</p>';
            }
        } else {
            $html .= '<p style="color: #666; margin: 5px 0 0 0; font-size: 14px;">All Forms</p>';
        }
        $html .= '</div>';

        // Allow filtering of header HTML
        $headerHtml = apply_filters('fluentform/report_pdf_header_html', $html, $formId, $startDate, $endDate);
        $html = $headerHtml;

        // Stats Cards
        if (!empty($formStats)) {
            $statsHtml = $this->generateStatsSection($formStats);
            // Allow filtering of stats section HTML
            $statsHtml = apply_filters('fluentform/report_pdf_stats_html', $statsHtml, $formStats);
            $html .= $statsHtml;
        }

        // Additional data tables
        $tablesHtml = $this->generateDataTablesSection($reports, $submissionsData);
        // Allow filtering of data tables HTML
        $tablesHtml = apply_filters('fluentform/report_pdf_tables_html', $tablesHtml, $reports, $submissionsData);
        $html .= $tablesHtml;

        $html .= '</div>';

        return $html;
    }

    /**
     * Generate stats section HTML
     *
     * @param array $formStats
     * @return string
     */
    private function generateStatsSection($formStats)
    {
        $html = '<div style="margin-bottom: 30px;">';
        $html .= '<h3 style="color: #007cba; margin-bottom: 20px; font-size: 20px;">Key Statistics</h3>';

        // Use table format for compact display
        $html .= '<table style="width: 100%; border-collapse: collapse;">';
        $html .= '<thead><tr style="background: #f5f5f5;">';
        $html .= '<th style="padding: 12px; text-align: left; border-bottom: 1px solid #ddd; font-weight: bold;">Metric</th>';
        $html .= '<th style="padding: 12px; text-align: right; border-bottom: 1px solid #ddd; font-weight: bold;">Value</th>';
        $html .= '<th style="padding: 12px; text-align: center; border-bottom: 1px solid #ddd; font-weight: bold;">Change</th>';
        $html .= '</tr></thead><tbody>';

        // Show all available stats
        $statsToShow = [
            'total_submissions',
            'read_submissions',
            'unread_submissions',
            'spam_submissions',
            'active_forms',
            'total_payments',
            'pending_payments',
            'total_refunds',
            'total_revenue'
        ];

        foreach ($statsToShow as $statKey) {
            if (isset($formStats[$statKey])) {
                $stat = $formStats[$statKey];
                $html .= $this->generateStatRow($statKey, $stat);
            }
        }

        $html .= '</tbody></table></div>';
        return $html;
    }

    /**
     * Generate individual stat row HTML
     *
     * @param string $statKey
     * @param array $stat
     * @return string
     */
    private function generateStatRow($statKey, $stat)
    {
        $value = Arr::get($stat, 'value', '0');
        $currencySymbol = Arr::get($stat, 'currency_symbol', '');
        $change = Arr::get($stat, 'change', '');
        $changeType = Arr::get($stat, 'change_type', 'neutral');

        // Format the display value
        $displayValue = $currencySymbol . $value;

        // Get readable label
        $label = $this->getStatLabel($statKey);

        // Format change with color (special handling for spam submissions)
        $changeColor = '#666';
        $changeIcon = '';
        if ($changeType === 'up') {
            if ($statKey === 'spam_submissions') {
                $changeColor = '#ef4444';
                $changeIcon = '↗ ';
            } else {
                $changeColor = '#10b981';
                $changeIcon = '↗ ';
            }
        } elseif ($changeType === 'down') {
            if ($statKey === 'spam_submissions') {
                $changeColor = '#10b981';
                $changeIcon = '↘ ';
            } else {
                $changeColor = '#ef4444';
                $changeIcon = '↘ ';
            }
        }

        $html = '<tr>';
        $html .= '<td style="padding: 10px; border-bottom: 1px solid #eee;">' . esc_html($label) . '</td>';
        $html .= '<td style="padding: 10px; border-bottom: 1px solid #eee; text-align: right; font-weight: bold; color: #007cba;">' . esc_html($displayValue) . '</td>';

        if ($change) {
            $html .= '<td style="padding: 10px; border-bottom: 1px solid #eee; text-align: center; color: ' . $changeColor . '; font-size: 12px;">' . $changeIcon . esc_html($change) . '</td>';
        } else {
            $html .= '<td style="padding: 10px; border-bottom: 1px solid #eee; text-align: center; color: #666; font-size: 12px;">-</td>';
        }

        $html .= '</tr>';

        return $html;
    }

    /**
     * Generate data tables section HTML
     *
     * @param array $reports
     * @param array $submissionsData
     * @return string
     */
    private function generateDataTablesSection($reports, $submissionsData = [])
    {
        $html = '<div style="margin-bottom: 30px;">';
        $html .= '<h3 style="color: #007cba; margin-bottom: 20px; font-size: 20px;">Detailed Analytics</h3>';

        // Add top performing forms if available
        if ($topPerformingForms = Arr::get($reports, 'top_performing_forms', [])) {
            $topFormsHtml = $this->generateTopFormsSection($topPerformingForms);
            $html .= apply_filters('fluentform/report_pdf_top_forms_html', $topFormsHtml, $topPerformingForms);
        }

        // Add completion rate data if available
        if ($completionRate = Arr::get($reports, 'completion_rate', [])) {
            $completionHtml = $this->generateCompletionRateSection($completionRate);
            $html .= apply_filters('fluentform/report_pdf_completion_rate_html', $completionHtml, $completionRate);
        }

        // Add payment types data if available
        if ($paymentTypes = Arr::get($reports, 'payment_types', [])) {
            $paymentHtml = $this->generatePaymentTypesSection($paymentTypes);
            $html .= apply_filters('fluentform/report_pdf_payment_types_html', $paymentHtml, $paymentTypes);
        }

        // Add submissions analysis data if available
        if ($submissionsData = Arr::get($reports, 'submissions_data', [])) {
            $submissionsHtml = $this->generateSubmissionsAnalysisSection($submissionsData);
            $html .= apply_filters('fluentform/report_pdf_submissions_analysis_html', $submissionsHtml, $submissionsData);
        }

        // Add API logs data if available
        if ($apiLogs = Arr::get($reports, 'api_logs', [])) {
            $apiLogsHtml = $this->generateApiLogsSection($apiLogs);
            $html .= apply_filters('fluentform/report_pdf_api_logs_html', $apiLogsHtml, $apiLogs);
        }

        // Allow adding custom sections
        $customSections = apply_filters('fluentform/report_pdf_custom_sections', '', $reports, $submissionsData);
        $html .= $customSections;

        $html .= '</div>';
        return $html;
    }

    /**
     * Get readable label for stat key
     *
     * @param string $statKey
     * @return string
     */
    private function getStatLabel($statKey)
    {
        $labels = [
            'total_submissions' => 'Total Submissions',
            'spam_submissions' => 'Spam Submissions',
            'unread_submissions' => 'Unread Submissions',
            'read_submissions' => 'Read Submissions',
            'active_forms' => 'Active Forms',
            'total_payments' => 'Total Paid',
            'pending_payments' => 'Total Pending',
            'total_refunds' => 'Total Refunded',
            'total_revenue' => 'Total Revenue'
        ];

        return Arr::get($labels, $statKey, ucwords(str_replace('_', ' ', $statKey)));
    }

    /**
     * Generate top performing forms section
     *
     * @param array $topForms
     * @return string
     */
    private function generateTopFormsSection($topForms)
    {
        if (empty($topForms)) {
            return '';
        }

        $html = '<div style="margin-bottom: 20px;">';
        $html .= '<h4 style="color: #007cba; margin-bottom: 15px; font-size: 16px;">Top Performing Forms</h4>';
        $html .= '<table style="width: 100%; border-collapse: collapse;">';
        $html .= '<thead><tr style="background: #f5f5f5;">';
        $html .= '<th style="padding: 10px; text-align: left; border-bottom: 1px solid #ddd;">Rank</th>';
        $html .= '<th style="padding: 10px; text-align: left; border-bottom: 1px solid #ddd;">Form</th>';
        $html .= '<th style="padding: 10px; text-align: left; border-bottom: 1px solid #ddd;">Entries</th>';
        $html .= '<th style="padding: 10px; text-align: left; border-bottom: 1px solid #ddd;">Status</th>';
        $html .= '</tr></thead><tbody>';

        $rank = 1;
        // Sort by value in descending order
        usort($topForms, function ($a, $b) {
            return $b['value'] - $a['value'];
        });

        foreach (array_slice($topForms, 0, 10) as $form) {
            $entries = Arr::get($form, 'value', 0);

            $html .= '<tr>';
            $html .= '<td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;">' . $rank . '</td>';
            $html .= '<td style="padding: 8px; border-bottom: 1px solid #eee;">#' . esc_html($form['id']) . ' - ' . esc_html($form['title']) . '</td>';
            $html .= '<td style="padding: 8px; border-bottom: 1px solid #eee;">' . esc_html($entries) . '</td>';
            $html .= '<td style="padding: 8px; border-bottom: 1px solid #eee;">Active</td>';
            $html .= '</tr>';
            $rank++;
        }

        $html .= '</tbody></table></div>';
        return $html;
    }

    /**
     * Generate completion rate section
     *
     * @param array $completionData
     * @return string
     */
    private function generateCompletionRateSection($completionData)
    {
        if (empty($completionData)) {
            return '';
        }

        $html = '<div style="margin-bottom: 20px;">';
        $html .= '<h4 style="color: #007cba; margin-bottom: 15px; font-size: 16px;">Form Completion Statistics</h4>';

        // Handle the actual completion rate data structure
        if (isset($completionData['completion_rate'])) {
            $html .= '<table style="width: 100%; border-collapse: collapse;">';
            $html .= '<thead><tr style="background: #f5f5f5;">';
            $html .= '<th style="padding: 10px; text-align: left; border-bottom: 1px solid #ddd;">Metric</th>';
            $html .= '<th style="padding: 10px; text-align: left; border-bottom: 1px solid #ddd;">Value</th>';
            $html .= '</tr></thead><tbody>';

            $html .= '<tr>';
            $html .= '<td style="padding: 8px; border-bottom: 1px solid #eee;">Completion Rate</td>';
            $html .= '<td style="padding: 8px; border-bottom: 1px solid #eee;">' . esc_html($completionData['completion_rate']) . '%</td>';
            $html .= '</tr>';

            if (isset($completionData['total_submissions'])) {
                $html .= '<tr>';
                $html .= '<td style="padding: 8px; border-bottom: 1px solid #eee;">Total Submissions</td>';
                $html .= '<td style="padding: 8px; border-bottom: 1px solid #eee;">' . esc_html($completionData['total_submissions']) . '</td>';
                $html .= '</tr>';
            }

            if (isset($completionData['total_attempts'])) {
                $html .= '<tr>';
                $html .= '<td style="padding: 8px; border-bottom: 1px solid #eee;">Total Attempts</td>';
                $html .= '<td style="padding: 8px; border-bottom: 1px solid #eee;">' . esc_html($completionData['total_attempts']) . '</td>';
                $html .= '</tr>';
            }

            if (isset($completionData['incomplete_submissions'])) {
                $html .= '<tr>';
                $html .= '<td style="padding: 8px; border-bottom: 1px solid #eee;">Incomplete Submissions</td>';
                $html .= '<td style="padding: 8px; border-bottom: 1px solid #eee;">' . esc_html($completionData['incomplete_submissions']) . '</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody></table>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Generate payment types section
     *
     * @param array $paymentTypes
     * @return string
     */
    private function generatePaymentTypesSection($paymentTypes)
    {
        if (empty($paymentTypes)) {
            return '';
        }

        $html = '<div style="margin-bottom: 20px;">';
        $html .= '<h4 style="color: #007cba; margin-bottom: 15px; font-size: 16px;">Payment Analysis</h4>';

        // One-time payments
        if (isset($paymentTypes['onetime']) && !empty($paymentTypes['onetime']['payment_statuses'])) {
            $onetime = $paymentTypes['onetime'];
            $currencySymbol = Arr::get($onetime, 'currency_symbol', '$');

            $html .= '<h5 style="color: #666; margin-bottom: 10px; font-size: 14px;">One-time Payments</h5>';
            $html .= '<table style="width: 100%; border-collapse: collapse; margin-bottom: 15px;">';
            $html .= '<thead><tr style="background: #f5f5f5;">';
            $html .= '<th style="padding: 10px; text-align: left; border-bottom: 1px solid #ddd;">Status</th>';
            $html .= '<th style="padding: 10px; text-align: left; border-bottom: 1px solid #ddd;">Count</th>';
            $html .= '<th style="padding: 10px; text-align: left; border-bottom: 1px solid #ddd;">Amount</th>';
            $html .= '<th style="padding: 10px; text-align: left; border-bottom: 1px solid #ddd;">Percentage</th>';
            $html .= '</tr></thead><tbody>';

            foreach ($onetime['payment_statuses'] as $status => $data) {
                $amount = number_format($data['amount'], 2);
                $html .= '<tr>';
                $html .= '<td style="padding: 8px; border-bottom: 1px solid #eee;">' . esc_html(ucfirst($status)) . '</td>';
                $html .= '<td style="padding: 8px; border-bottom: 1px solid #eee;">' . esc_html($data['count']) . '</td>';
                $html .= '<td style="padding: 8px; border-bottom: 1px solid #eee;">' . $currencySymbol . esc_html($amount) . '</td>';
                $html .= '<td style="padding: 8px; border-bottom: 1px solid #eee;">' . esc_html(round($data['percentage'], 1)) . '%</td>';
                $html .= '</tr>';
            }

            // Add total row
            $totalAmount = number_format($onetime['total_amount'], 2);
            $weeklyAverage = number_format($onetime['weekly_average'], 2);
            $html .= '<tr style="background: #f9f9f9; font-weight: bold;">';
            $html .= '<td style="padding: 8px; border-bottom: 1px solid #eee;">Total</td>';
            $html .= '<td style="padding: 8px; border-bottom: 1px solid #eee;">-</td>';
            $html .= '<td style="padding: 8px; border-bottom: 1px solid #eee;">' . $currencySymbol . esc_html($totalAmount) . '</td>';
            $html .= '<td style="padding: 8px; border-bottom: 1px solid #eee;">100%</td>';
            $html .= '</tr>';

            $html .= '</tbody></table>';

            $html .= '<p style="margin: 0 0 15px 0; font-size: 12px; color: #666;">Weekly Average: ' . $currencySymbol . $weeklyAverage . '</p>';
        }

        // Recurring payments (subscriptions)
        if (isset($paymentTypes['subscription'])) {
            $subscription = $paymentTypes['subscription'];
            $currencySymbol = Arr::get($subscription, 'currency_symbol', '$');

            $html .= '<h5 style="color: #666; margin-bottom: 10px; font-size: 14px;">Recurring Payments (Subscriptions)</h5>';

            if (!empty($subscription['payment_statuses'])) {
                $html .= '<table style="width: 100%; border-collapse: collapse; margin-bottom: 15px;">';
                $html .= '<thead><tr style="background: #f5f5f5;">';
                $html .= '<th style="padding: 10px; text-align: left; border-bottom: 1px solid #ddd;">Status</th>';
                $html .= '<th style="padding: 10px; text-align: left; border-bottom: 1px solid #ddd;">Count</th>';
                $html .= '<th style="padding: 10px; text-align: left; border-bottom: 1px solid #ddd;">Amount</th>';
                $html .= '<th style="padding: 10px; text-align: left; border-bottom: 1px solid #ddd;">Percentage</th>';
                $html .= '</tr></thead><tbody>';

                foreach ($subscription['payment_statuses'] as $status => $data) {
                    $amount = number_format($data['amount'], 2);
                    $html .= '<tr>';
                    $html .= '<td style="padding: 8px; border-bottom: 1px solid #eee;">' . esc_html(ucfirst($status)) . '</td>';
                    $html .= '<td style="padding: 8px; border-bottom: 1px solid #eee;">' . esc_html($data['count']) . '</td>';
                    $html .= '<td style="padding: 8px; border-bottom: 1px solid #eee;">' . $currencySymbol . esc_html($amount) . '</td>';
                    $html .= '<td style="padding: 8px; border-bottom: 1px solid #eee;">' . esc_html(round($data['percentage'], 1)) . '%</td>';
                    $html .= '</tr>';
                }

                // Add total row for subscriptions
                $totalAmount = number_format($subscription['total_amount'], 2);
                $weeklyAverage = number_format($subscription['weekly_average'], 2);
                $html .= '<tr style="background: #f9f9f9; font-weight: bold;">';
                $html .= '<td style="padding: 8px; border-bottom: 1px solid #eee;">Total</td>';
                $html .= '<td style="padding: 8px; border-bottom: 1px solid #eee;">-</td>';
                $html .= '<td style="padding: 8px; border-bottom: 1px solid #eee;">' . $currencySymbol . esc_html($totalAmount) . '</td>';
                $html .= '<td style="padding: 8px; border-bottom: 1px solid #eee;">100%</td>';
                $html .= '</tr>';

                $html .= '</tbody></table>';

                $html .= '<p style="margin: 0; font-size: 12px; color: #666;">Weekly Average: ' . $currencySymbol . $weeklyAverage . '</p>';
            } else {
                $html .= '<p style="margin: 0 0 15px 0; font-size: 14px; color: #666; font-style: italic;">No recurring payments found for this period.</p>';
            }
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Generate submissions analysis section
     *
     * @param array $submissionsData
     * @return string
     */
    private function generateSubmissionsAnalysisSection($submissionsData)
    {
        if (empty($submissionsData['data'])) {
            return '';
        }

        $html = '<div style="margin-bottom: 20px;">';
        $html .= '<h4 style="color: #007cba; margin-bottom: 15px; font-size: 16px;">Form Submissions Analysis</h4>';
        $html .= '<table style="width: 100%; border-collapse: collapse;">';
        $html .= '<thead><tr style="background: #f5f5f5;">';
        $html .= '<th style="padding: 10px; text-align: left; border-bottom: 1px solid #ddd;">Form</th>';
        $html .= '<th style="padding: 10px; text-align: left; border-bottom: 1px solid #ddd;">Total</th>';
        $html .= '<th style="padding: 10px; text-align: left; border-bottom: 1px solid #ddd;">Read</th>';
        $html .= '<th style="padding: 10px; text-align: left; border-bottom: 1px solid #ddd;">Unread</th>';
        $html .= '<th style="padding: 10px; text-align: left; border-bottom: 1px solid #ddd;">Conversion Rate</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($submissionsData['data'] as $form) {
            $html .= '<tr>';
            $html .= '<td style="padding: 8px; border-bottom: 1px solid #eee;">#' . esc_html($form['form_id']) . ' - ' . esc_html($form['form_title']) . '</td>';
            $html .= '<td style="padding: 8px; border-bottom: 1px solid #eee;">' . esc_html($form['total_submissions']) . '</td>';
            $html .= '<td style="padding: 8px; border-bottom: 1px solid #eee;">' . esc_html($form['read_submissions']) . '</td>';
            $html .= '<td style="padding: 8px; border-bottom: 1px solid #eee;">' . esc_html($form['unread_submissions']) . '</td>';
            $html .= '<td style="padding: 8px; border-bottom: 1px solid #eee;">' . esc_html($form['conversion_rate']) . '%</td>';
            $html .= '</tr>';
        }

        // Add totals row if available
        if (isset($submissionsData['totals'])) {
            $totals = $submissionsData['totals'];
            $html .= '<tr style="background: #f9f9f9; font-weight: bold;">';
            $html .= '<td style="padding: 8px; border-bottom: 1px solid #eee;">Total</td>';
            $html .= '<td style="padding: 8px; border-bottom: 1px solid #eee;">' . esc_html($totals['total']) . '</td>';
            $html .= '<td style="padding: 8px; border-bottom: 1px solid #eee;">' . esc_html($totals['read']) . '</td>';
            $html .= '<td style="padding: 8px; border-bottom: 1px solid #eee;">' . esc_html($totals['unread']) . '</td>';
            $html .= '<td style="padding: 8px; border-bottom: 1px solid #eee;">' . esc_html($totals['readRate']) . '%</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table></div>';
        return $html;
    }

    /**
     * Generate API logs section
     *
     * @param array $apiLogs
     * @return string
     */
    private function generateApiLogsSection($apiLogs)
    {
        if (empty($apiLogs['totals'])) {
            return '';
        }

        $totals = $apiLogs['totals'];
        $totalRequests = $totals['success'] + $totals['pending'] + $totals['failed'];

        // Only show if there's meaningful data
        if ($totalRequests == 0) {
            return '';
        }

        $html = '<div style="margin-bottom: 20px;">';
        $html .= '<h4 style="color: #007cba; margin-bottom: 15px; font-size: 16px;">API Integration Logs</h4>';
        $html .= '<table style="width: 100%; border-collapse: collapse;">';
        $html .= '<thead><tr style="background: #f5f5f5;">';
        $html .= '<th style="padding: 10px; text-align: left; border-bottom: 1px solid #ddd;">Status</th>';
        $html .= '<th style="padding: 10px; text-align: left; border-bottom: 1px solid #ddd;">Count</th>';
        $html .= '<th style="padding: 10px; text-align: left; border-bottom: 1px solid #ddd;">Percentage</th>';
        $html .= '</tr></thead><tbody>';

        // Success
        $successPercentage = $totalRequests > 0 ? round(($totals['success'] / $totalRequests) * 100, 1) : 0;
        $html .= '<tr>';
        $html .= '<td style="padding: 8px; border-bottom: 1px solid #eee; color: #28a745;">✓ Success</td>';
        $html .= '<td style="padding: 8px; border-bottom: 1px solid #eee;">' . esc_html($totals['success']) . '</td>';
        $html .= '<td style="padding: 8px; border-bottom: 1px solid #eee;">' . esc_html($successPercentage) . '%</td>';
        $html .= '</tr>';

        // Pending/Processing
        $pendingPercentage = $totalRequests > 0 ? round(($totals['pending'] / $totalRequests) * 100, 1) : 0;
        $html .= '<tr>';
        $html .= '<td style="padding: 8px; border-bottom: 1px solid #eee; color: #ffc107;">⏳ Processing</td>';
        $html .= '<td style="padding: 8px; border-bottom: 1px solid #eee;">' . esc_html($totals['pending']) . '</td>';
        $html .= '<td style="padding: 8px; border-bottom: 1px solid #eee;">' . esc_html($pendingPercentage) . '%</td>';
        $html .= '</tr>';

        // Failed
        $failedPercentage = $totalRequests > 0 ? round(($totals['failed'] / $totalRequests) * 100, 1) : 0;
        $html .= '<tr>';
        $html .= '<td style="padding: 8px; border-bottom: 1px solid #eee; color: #dc3545;">✗ Failed</td>';
        $html .= '<td style="padding: 8px; border-bottom: 1px solid #eee;">' . esc_html($totals['failed']) . '</td>';
        $html .= '<td style="padding: 8px; border-bottom: 1px solid #eee;">' . esc_html($failedPercentage) . '%</td>';
        $html .= '</tr>';

        // Total row
        $html .= '<tr style="background: #f9f9f9; font-weight: bold;">';
        $html .= '<td style="padding: 8px; border-bottom: 1px solid #eee;">Total</td>';
        $html .= '<td style="padding: 8px; border-bottom: 1px solid #eee;">' . esc_html($totalRequests) . '</td>';
        $html .= '<td style="padding: 8px; border-bottom: 1px solid #eee;">100%</td>';
        $html .= '</tr>';

        $html .= '</tbody></table>';

        $html .= '</div>';
        return $html;
    }

    /**
     * Create PDF using existing infrastructure
     *
     * @param string $htmlContent
     * @param string $startDate
     * @param string $endDate
     * @return void
     */
    private function createReportPdf($htmlContent, $startDate, $endDate)
    {
        $globalSettings = apply_filters('fluentform/report_pdf_settings', [
            'paper_size' => 'A4',
            'orientation' => 'P',
            'font' => 'default',
            'font_size' => '14',
            'font_color' => '#323232',
            'accent_color' => '#989797',
            'heading_color' => '#000000',
            'language_direction' => 'ltr'
        ]);

        $this->ensureEssentialFontsExist();

        $dirStructure = AvailableOptions::getDirStructure();

        $defaults = [
            'fontDir' => [
                $dirStructure['fontDir']
            ],
            'tempDir' => $dirStructure['tempDir'],
            'curlCaCertificate' => ABSPATH . WPINC . '/certificates/ca-bundle.crt',
            'curlFollowLocation' => true,
            'allow_output_buffering' => true,
            'autoLangToFont' => true,
            'autoScriptToLang' => true,
            'useSubstitutions' => true,
            'ignore_invalid_utf8' => true,
            'setAutoTopMargin' => 'stretch',
            'setAutoBottomMargin' => 'stretch',
            'enableImports' => true,
            'use_kwt' => true,
            'keepColumns' => true,
            'biDirectional' => true,
            'showWatermarkText' => true,
            'showWatermarkImage' => true,
            'mode' => 'utf-8',
            'format' => Arr::get($globalSettings, 'paper_size', 'A4'),
            'orientation' => Arr::get($globalSettings, 'orientation', 'P'),
            'default_font' => 'FreeSans',
        ];

        // Allow filtering of mPDF configuration
        $config = apply_filters('fluentform/report_pdf_mpdf_config', $defaults, $globalSettings);

        $config = apply_filters('fluentform/mpdf_config', $config);

        // Initialize mPDF with proper font configuration (autoload already loaded in generatePdf)
        $mpdf = new \Mpdf\Mpdf($config);

        // Set document properties
        // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date -- not performing any date operations
        $fileName = apply_filters('fluentform/report_pdf_filename', 'fluent-forms-report-' . date('Y-m-d'), $startDate, $endDate);
        $title = apply_filters('fluentform/report_pdf_title', 'Fluent Forms Comprehensive Report');
        $author = apply_filters('fluentform/report_pdf_author', 'Fluent Forms');

        $mpdf->SetTitle($title);
        $mpdf->SetAuthor($author);

        // Add CSS for better styling
        $css = $this->getReportPdfCss($globalSettings);
        $mpdf->WriteHTML($css, \Mpdf\HTMLParserMode::HEADER_CSS);

        // Write HTML content
        $mpdf->WriteHTML($htmlContent, \Mpdf\HTMLParserMode::HTML_BODY);

        // Allow filtering of output mode (I = inline, D = download, F = file, S = string)
        $outputMode = apply_filters('fluentform/report_pdf_output_mode', 'I');

        // Output PDF
        $mpdf->Output($fileName . '.pdf', $outputMode);
    }

    /**
     * Get CSS for PDF styling
     *
     * @param array $globalSettings
     * @return string
     */
    private function getReportPdfCss($globalSettings)
    {
        $fontSize = Arr::get($globalSettings, 'font_size', '14');
        $fontColor = Arr::get($globalSettings, 'font_color', '#323232');
        $headingColor = Arr::get($globalSettings, 'heading_color', '#000000');
        $accentColor = Arr::get($globalSettings, 'accent_color', '#989797');

        return apply_filters('fluentform/report_pdf_css', "
            body {
                font-family: Arial, sans-serif;
                font-size: {$fontSize}px;
                color: {$fontColor};
                line-height: 1.6;
                margin: 0;
                padding: 20px;
            }
            h1, h2, h3 {
                color: {$headingColor};
                margin-top: 0;
            }
            .stat-card {
                background: #f9f9f9;
                padding: 15px;
                margin-bottom: 10px;
                border-left: 4px solid {$accentColor};
                border-radius: 4px;
            }
            .stat-value {
                font-size: 20px;
                font-weight: bold;
                color: {$headingColor};
            }
            .stat-label {
                color: {$accentColor};
                font-size: 12px;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
            }
            th, td {
                padding: 8px;
                text-align: left;
                border-bottom: 1px solid {$accentColor};
            }
            th {
                background-color: #f5f5f5;
                font-weight: bold;
            }
        ");
    }

    /**
     * Ensure essential fonts exist for PDF generation
     *
     * @return void
     */
    private function ensureEssentialFontsExist()
    {
        $fontManager = new FontManager();
        $downloadableFiles = $fontManager->getDownloadableFonts();

        // If no fonts need downloading, we're good
        if (empty($downloadableFiles)) {
            return;
        }

        // Essential fonts for basic PDF generation
        $essentialFonts = [
            'FreeSans.ttf',
            'FreeSansBold.ttf',
            'FreeSerif.ttf',
            'FreeSerifBold.ttf',
            'DejaVuSans.ttf',
            'DejaVuSans-Bold.ttf'
        ];

        // Download essential fonts if they're missing
        foreach ($downloadableFiles as $downloadableFont) {
            if (in_array($downloadableFont['name'], $essentialFonts)) {
                $result = $fontManager->download($downloadableFont['name']);
                if (is_wp_error($result) && defined('WP_DEBUG') && WP_DEBUG) {
                    // Log error but continue - font substitution should handle this
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging only when WP_DEBUG is enabled
                    error_log('FluentForms PDF: Failed to download font ' . $downloadableFont['name'] . ': ' . $result->get_error_message());
                }
            }
        }
    }
}
