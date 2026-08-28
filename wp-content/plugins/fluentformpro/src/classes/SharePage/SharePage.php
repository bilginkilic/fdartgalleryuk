<?php

namespace FluentFormPro\classes\SharePage;

use FluentForm\App\Helpers\Helper;
use FluentForm\App\Models\FormMeta;
use FluentForm\App\Modules\Acl\Acl;
use FluentForm\Framework\Helpers\ArrayHelper;

class SharePage
{
    public $metaKey = '_landing_page_settings';

    public function boot()
    {
        $enabled = $this->isEnabled();

        add_action('wp', [$this, 'renderLandingForm']);

        add_filter('fluentform/global_addons', function ($addOns) use ($enabled) {
            $addOns['sharePages'] = [
                'title'       => 'Landing Pages',
                'description' => __('Create completely custom "distraction-free" form landing pages to boost conversions', 'fluentformpro'),
                'logo'        => fluentFormMix('img/integrations/landing_pages.png'),
                'enabled'     => ($enabled) ? 'yes' : 'no',
                'config_url'  => '',
                'category'    => ''
            ];
            return $addOns;
        }, 9);

        if (!$enabled) {
            return;
        }

        add_filter('fluentform/form_settings_menu', function ($menu) {
            $menu['landing_pages'] = [
                'title' => __('Landing Page', 'fluentformpro'),
                'slug'  => 'form_settings',
                'hash'  => 'landing_pages',
                'route' => '/landing_pages'
            ];
            return $menu;
        });

        add_action('wp_ajax_ff_get_landing_page_settings', [$this, 'getSettingsAjax']);
        add_action('wp_ajax_ff_store_landing_page_settings', [$this, 'saveSettingsAjax']);

    }

    public function getSettingsAjax()
    {
        $formId = intval(wpFluentForm()->request->get('form_id'));
        Acl::verify('fluentform_forms_manager', $formId);
        $settings = $this->getSettings($formId);

        $shareUrl = '';
        if ($settings['status'] == 'yes') {
            $params = [
                'ff_landing' => $formId
            ];
            $shareUrl = add_query_arg($params, home_url('/'));
        }

        wp_send_json_success([
            'settings'  => $settings,
            'share_url' => $shareUrl
        ]);
    }

    public function saveSettingsAjax()
    {
        $formId = intval(wpFluentForm()->request->get('form_id'));
        Acl::verify('fluentform_forms_manager', $formId);
        $settings = wpFluentForm()->request->get('settings');
        
        $sanitizeMap = [
            'status'         => 'sanitize_text_field',
            'title'          => 'sanitize_text_field',
            'color_schema'   => 'sanitize_text_field',
            'custom_color'   => 'sanitize_text_field',
            'share_url_salt' => 'sanitize_text_field',
        ];
        $settings = fluentform_backend_sanitizer($settings, $sanitizeMap);
        
        $formattedSettings = wp_unslash($settings);
        $formattedSettings['description'] = wp_kses_post(wp_unslash($settings['description']));
        Helper::setFormMeta($formId, $this->metaKey, $formattedSettings);

        $shareUrl = '';
        if ($formattedSettings['status'] == 'yes') {
            $shareUrl = add_query_arg(['ff_landing' => $formId], home_url('/'));
        }

        wp_send_json_success([
            'message'   => __('Settings successfully updated'),
            'share_url' => $shareUrl
        ]);
    }

    public function getSettings($formId)
    {
        $settings = FormMeta::retrieve($this->metaKey, $formId,[]);
    
        $defaults = [
            'status'           => 'no',
            'logo'             => '',
            'title'            => '',
            'description'      => '',
            'color_schema'     => '#4286c4',
            'custom_color'     => '#4286c4',
            'design_style'     => 'modern',
            'featured_image'   => '',
            'background_image' => '',
            'layout'           => 'default',
            'media'            => fluentFormGetRandomPhoto(),
            'brightness'       => 0,
            'alt_text'         => '',
            'media_x_position' => 50,
            'media_y_position' => 50
        ];

        return wp_parse_args($settings, $defaults);
    }

    public function renderLandingForm()
    {
        $request = wpFluentForm()->request;
        $ff_landing = intval($request->get('ff_landing'));

        if (!$ff_landing || is_admin()) {
            return;
        }

        $hasConfirmation = false;
        $requestData = $request->all();
        
        $sanitizeMap = [
            'entry_confirmation' => 'sanitize_text_field',
            'form' => 'sanitize_text_field',
        ];
        $requestData = fluentform_backend_sanitizer($requestData, $sanitizeMap);
        if (isset($requestData['entry_confirmation'])) {
            do_action_deprecated(
                'fluentformpro_entry_confirmation',
                [
                    $requestData
                ],
                FLUENTFORM_FRAMEWORK_UPGRADE,
                'fluentform/entry_confirmation',
                'Use fluentform/entry_confirmation instead of fluentformpro_entry_confirmation.'
            );
            do_action('fluentform/entry_confirmation', $requestData);
            $hasConfirmation = true;
        }

        $formId = $ff_landing;

        $form = wpFluent()->table('fluentform_forms')->where('id', $formId)->first();

        if (!$form) {
            return;
        }

        $settings = $this->getSettings($formId);


        if (ArrayHelper::get($settings, 'status') != 'yes') {
            return;
        }

        $pageTitle = $form->title;

        if ($settings['title']) {
            $pageTitle = $settings['title'];
        }

        add_action('wp_enqueue_scripts', function () use ($formId) {
            $theme = Helper::getFormMeta($formId, '_ff_selected_style');
            $styles = $theme ? [$theme] : [];

            do_action('fluentform/load_form_assets', $formId, $styles);
            wp_enqueue_style('fluent-form-styles');
            wp_enqueue_style('fluentform-public-default');
            wp_enqueue_script('fluent-form-submission');
        });

        $backgroundColor = ArrayHelper::get($settings, 'color_schema');

        if ($backgroundColor == 'custom') {
            $backgroundColor = ArrayHelper::get($settings, 'custom_color');
        }


        $landingContent = '[fluentform id="' . $formId . '"]';
        if(!$hasConfirmation) {
            $salt = ArrayHelper::get($settings, 'share_url_salt');
            $requestData = wpFluentForm()->request->all();
            if($salt && $salt != ArrayHelper::get($requestData, 'form')) {
                $landingContent = __('Sorry, You do not have access to this form', 'fluentformpro');
                $pageTitle = __('No Access', 'fluentformpro');
                $settings['title'] = '';
                $settings['description'] = '';
            }
        }

        $data = [
            'settings'        => $settings,
            'title'           => $pageTitle,
            'form_id'         => $formId,
            'form'            => $form,
            'bg_color'        => $backgroundColor,
            'landing_content' => $landingContent,
            'has_header'      => $settings['logo'] || $settings['title'] || $settings['description'],
            'isEmbeded' => !!ArrayHelper::get($_GET, 'embedded')
        ];
    
        $data = apply_filters_deprecated(
            'fluentform_landing_vars',
            [
                $data,
                $formId
            ],
            FLUENTFORM_FRAMEWORK_UPGRADE,
            'fluentform/landing_vars',
            'Use fluentform/landing_vars instead of fluentform_landing_vars.'
        );

        $landingVars = apply_filters('fluentform/landing_vars', $data, $formId);

        $this->loadPublicView($landingVars);
    }

    public function loadPublicView($landingVars)
    {
        add_action('wp_enqueue_scripts', function () {
            wp_enqueue_style(
                'fluent-form-landing',
                FLUENTFORMPRO_DIR_URL . 'public/css/form_landing.css',
                [],
                FLUENTFORMPRO_VERSION
            );
        });

        add_filter('pre_get_document_title', function ($title) use ($landingVars) {
            $separator = apply_filters('document_title_separator', '-');
            return $landingVars['title'] . ' ' . $separator . ' ' . get_bloginfo('name', 'display');
        });

        // let's deregister all the style and scripts here
        add_action('wp_print_scripts', function () {
            global $wp_scripts;
            if (!$wp_scripts) {
                return;
            }

            /**
             * Define the list of approved slugs for FluentForm Landing Page assets.
             *
             * This filter allows modification of the list of slugs that are approved for FluentForm assets.
             *
             * @param array $approvedSlugs An array of approved slugs for FluentForm assets.
             */
            $approvedSlugs = apply_filters('fluent_form_landing_asset_listed_slugs', [
                '\/affiliate-wp\/',
                '\/fluent-affiliate\/',
            ]);

            $approvedSlugs[] = 'fluentform';
            $approvedSlugs[] = 'fluentformpro';

            $approvedSlugs = array_unique($approvedSlugs);

            $approvedSlugs = implode('|', $approvedSlugs);

            $contentUrl = content_url();

            $contentUrl = str_replace(['http:', 'https:'], '', $contentUrl);

            foreach ($wp_scripts->queue as $script) {
                if (empty($wp_scripts->registered[$script]) || empty($wp_scripts->registered[$script]->src)) {
                    continue;
                }

                $src = $wp_scripts->registered[$script]->src;
                $isMatched = (strpos($src, $contentUrl) !== false) && !preg_match('/' . $approvedSlugs . '/', $src);
                if (!$isMatched) {
                    continue;
                }

                wp_dequeue_script($wp_scripts->registered[$script]->handle);
            }
        }, 1);

        if(isset($_GET['embedded'])) {
            add_action('wp_print_styles', function () {
                global $wp_styles;
                if($wp_styles) {
                    foreach ($wp_styles->queue as $style) {
                        $src = $wp_styles->registered[$style]->src;
                        if (!strpos($src, 'fluentform') !== false) {
                            wp_dequeue_style($wp_styles->registered[$style]->handle);
                        }
                    }
                }
            }, 1);
            if($landingVars['settings']['design_style'] == 'modern') {
                $landingVars['settings']['design_style'] = 'classic';
                $landingVars['bg_color'] = '#fff';
            }
        }
        
        status_header(200);
        echo $this->loadView('landing_page_view', $landingVars);
        exit(200);
    }

    public function loadView($view, $data = [])
    {
        $file = FLUENTFORMPRO_DIR_PATH . 'src/views/' . $view . '.php';
        extract($data);
        ob_start();
        include($file);
        return ob_get_clean();
    }

    public function isEnabled()
    {
        $globalModules = get_option('fluentform_global_modules_status');

        $sharePages = ArrayHelper::get($globalModules, 'sharePages');

        if (!$sharePages || $sharePages == 'yes') {
            return true;
        }

        return false;
    }

    public function getLandingPageFormIds()
    {
        $formIds = [];

        $forms = wpFluent()
            ->table('fluentform_form_meta')
            ->select('form_id')
            ->where('fluentform_form_meta.meta_key', '=', $this->metaKey)
            ->where('fluentform_form_meta.value', 'like', '%"status":"yes"%')
            ->get();

        if ($forms && count($forms) > 0) {
            foreach ($forms as $form) {
                $formIds[] = $form->form_id;
            }
        }

        return $formIds;
    }
}
