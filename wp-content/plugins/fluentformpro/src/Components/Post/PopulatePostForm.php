<?php

namespace FluentFormPro\Components\Post;

use FluentForm\App\Api\FormProperties;
use FluentForm\App\Helpers\Helper;
use FluentForm\App\Modules\Component\Component;
use FluentForm\App\Services\FormBuilder\Components\Select;
use FluentForm\Framework\Helpers\ArrayHelper;

/**
 * Populate Post Form on Post Selection Change
 */
class PopulatePostForm
{
    /**
     * Boot Class if post feed has post form type set to update
     */
    public function __construct()
    {
        add_action('fluentform/populate_post_form_values', [$this, 'boot'], 10, 3);
        add_action('wp_enqueue_scripts', function () {
            if (wp_script_is('fluentformpro_post_update', 'registered')) {
                return;
            }
            wp_register_script(
                'fluentformpro_post_update',
                FLUENTFORMPRO_DIR_URL . 'public/js/fluentformproPostUpdate.js',
                ['jquery'],
                FLUENTFORMPRO_VERSION,
                true
            );
        });
    }
    
    public function boot($form, $feed, $postType)
    {
        if (!wp_script_is('fluentformpro_post_update', 'registered')) {
            wp_register_script(
                'fluentformpro_post_update',
                FLUENTFORMPRO_DIR_URL . 'public/js/fluentformproPostUpdate.js',
                ['jquery'],
                FLUENTFORMPRO_VERSION,
                true
            );
        }
        wp_enqueue_script('fluentformpro_post_update');
        wp_localize_script('fluentformpro_post_update', 'fluentformpro_post_update_vars', array(
            'post_selector' => 'post-selector-' . time(),
            'nonce'         => wp_create_nonce('fluentformpro_post_update_nonce'),
        ));
    }
    

    /**
     * Push Post Selection field in the form
     *
     * @param $form
     * @param $postType
     *
     * @return void
     */
    public function renderPostSelectionField($data, $form)
    {
        $postType = ArrayHelper::get($data, 'settings.post_type_selection');

        $postPreData = apply_filters_deprecated(
            'fluentform_post_selection_posts_pre_data',
            [
                [],
                $data,
                $form
            ],
            FLUENTFORM_FRAMEWORK_UPGRADE,
            'fluentform/post_selection_posts_pre_data',
            'Use fluentform/post_selection_posts_pre_data instead of fluentform_post_selection_posts_pre_data.'
        );

        $posts = apply_filters('fluentform/post_selection_posts_pre_data', $postPreData, $data, $form);

        if (!$posts) {
            $queryParams = [
                'post_type'      => $postType,
                'posts_per_page' => -1
            ];

            $extraParams = ArrayHelper::get($data, 'settings.post_extra_query_params');
            $extraParams = apply_filters_deprecated(
                'fluentform_post_selection_posts_query_args',
                [
                    $extraParams,
                    $data,
                    $form
                ],
                FLUENTFORM_FRAMEWORK_UPGRADE,
                'fluentform/post_selection_posts_query_args',
                'Use fluentform/post_selection_posts_query_args instead of fluentform_post_selection_posts_query_args.'
            );
            $extraParams = apply_filters('fluentform/post_selection_posts_query_args', $extraParams, $data, $form);
            if ($extraParams) {
                if (strpos($extraParams, '{') !== false) {
                    $extraParams = (new Component(wpFluentForm()))->replaceEditorSmartCodes($extraParams, $form);
                }

                parse_str($extraParams, $get_array);
                $queryParams = wp_parse_args($get_array, $queryParams);
            }

            $posts = query_posts($queryParams);
            wp_reset_query();
        }

        $formattedOptions = [];

        $postSelectBy = apply_filters_deprecated(
            'fluentform_post_selection_value_by',
            [
                'ID',
                $form
            ],
            FLUENTFORM_FRAMEWORK_UPGRADE,
            'fluentform/post_selection_value_by',
            'Use fluentform/post_selection_value_by instead of fluentform_post_selection_value_by.'
        );
        $postValueBy = apply_filters('fluentform/post_selection_value_by', $postSelectBy, $form);
        $postSelectLabelBy = apply_filters_deprecated(
            'fluentform_post_selection_label_by',
            [
                'post_title',
                $form
            ],
            FLUENTFORM_FRAMEWORK_UPGRADE,
            'fluentform/post_selection_label_by',
            'Use fluentform/post_selection_label_by instead of fluentform_post_selection_label_by.'
        );
        $labelBy = apply_filters('fluentform/post_selection_label_by', $postSelectLabelBy, $form);

        foreach ($posts as $post) {
            $formattedOptions[] = [
                'label'      => $post->{$labelBy},
                'value'      => $post->{$postValueBy},
                'calc_value' => ''
            ];
        }

        $data['settings']['advanced_options'] = $formattedOptions;

        (new Select())->compile($data, $form);
    }

    /**
     * Get JSON Post Data
     * @return void
     */
    public function getPostDetails()
    {
        \FluentForm\App\Modules\Acl\Acl::verifyNonce('fluentformpro_post_update_nonce');
        $postId = intval($_REQUEST['post_id']);
        $formId = intval($_REQUEST['form_id']);
        if (!$postId) {
            wp_send_json([
                'message' => __('Please select a Post', 'fluentformpro')
            ], 423);
        }
        $post = get_post($postId, 'ARRAY_A');
        $selectedData = ArrayHelper::only($post,
            array('post_content', 'post_excerpt', 'post_category', 'tags_input', 'post_title', 'post_type'));
        $selectedData['thumbnail'] = get_the_post_thumbnail_url($postId);
    
        $taxonomiesData = [];
        $taxonomies = get_object_taxonomies($post['post_type']);
        foreach ($taxonomies as $taxonomy) {
            $taxonomiesData[$taxonomy] = $this->formattedTerms($postId, $taxonomy);
        }
        $postMetas = $this->getCustomPostMetaFieldValue($formId, $postId);
        wp_send_json_success([
            'post'     => $selectedData,
            'taxonomy' => $taxonomiesData,
            'custom_meta' => $postMetas['custom_meta'],
            'acf_metas' => $postMetas['acf_metas'],
            'advanced_acf_metas' => $postMetas['advanced_acf_metas'],
            'mb_general_metas' => $postMetas['mb_general_metas'],
            'mb_advanced_metas' => $postMetas['mb_advanced_metas'],
            'jetengine_metas' => $postMetas['jetengine_metas'],
            'advanced_jetengine_metas' => $postMetas['advanced_jetengine_metas'],
        ]);
    }
    
    private function formattedTerms($postId, $taxonomy)
    {
        $terms = get_the_terms($postId, $taxonomy);
        $formattedTaxonomies = [];
        if (empty($terms)) {
            return $formattedTaxonomies;
        }
        foreach ($terms as $term) {
            $formattedTaxonomies[] = [
                'value' => $term->term_id,
                'label' => $term->name
            ];
        }
        return $formattedTaxonomies;
    }

	private function getFormFeeds($formId)
	{
		return wpFluent()->table('fluentform_form_meta')
		                 ->where('form_id', $formId)
		                 ->where('meta_key', 'postFeeds')
		                 ->get();
	}

	private function getCustomPostMetaFieldValue($formId, $postId) {
        $meta_fields = [
            "custom_meta" => [],
            "acf_metas" => [],
            "advanced_acf_metas" => [],
            "mb_general_metas" => [],
            "mb_advanced_metas" => [],
            "jetengine_metas" => [],
            "advanced_jetengine_metas" => [],
        ];
		if (!$formId) {
			return $meta_fields;
		}

		$feeds = $this->getFormFeeds($formId);
		if (!$feeds) {
			return $meta_fields;
		}

		foreach ($feeds as $feed) {
			$feed->value = json_decode($feed->value, true);
			if (ArrayHelper::get($feed->value, 'post_form_type') !== 'update') {
				continue;
			}

			if ($metaFields = ArrayHelper::get($feed->value, 'meta_fields_mapping', [])) {
				$form = Helper::getForm($formId);
				if(!$form) {
					continue;
				}
				$formFields = (new FormProperties($form))->inputs(['raw']);

				foreach ($metaFields as $metaField) {
	                $value = ArrayHelper::get($metaField, 'meta_value', '');
                    if ($name = $this->getFormFieldName($value)) {
						$type = "text";
						if (isset($formFields[$name])) {
							$type = ArrayHelper::get($formFields[$name], 'raw.attributes.type', 'text');
						}
                        $value = get_post_custom_values($metaField['meta_key'], $postId);
						if ($value) {
							$value = $value[0];
						} else {
							$value = '';
						}
						if($type === 'file' && strpos($value, 'uploads/fluentform') === false) {
                            $AttachmentIds = explode(',' , $value);
                            $value = [];
                            foreach ($AttachmentIds as $id) {
                                if ($attachment = wp_prepare_attachment_for_js($id)) {
                                    $value[] = $attachment;
                                }
                            }
						}
						if ($type === 'checkbox') {
							$value = maybe_unserialize($value);
						}
                        $meta_fields['custom_meta'][] = [
                            "name" => $name,
                            "type" => $type,
                            "value" => $value
                        ];
                    }
                };
			}

            if (class_exists('\ACF')) {
                if ($acfFields = ArrayHelper::get($feed->value, 'acf_mappings', [])) {
                    foreach ($acfFields as $field) {
                        $value = ArrayHelper::get($field, 'field_value', '');
                        if ($name = $this->getFormFieldName($value)) {
                            $acfField = acf_get_field($field['field_key']);
                            $value = acf_get_value($postId, $acfField);
                            $meta_fields['acf_metas'][] = [
                                "name" => $name,
                                "type" => $acfField['type'],
                                "value" => $value ?:''
                            ];
                        }
                    };
                }

                if ($advancedAcfFields = ArrayHelper::get($feed->value, 'advanced_acf_mappings', [])) {
                    foreach ($advancedAcfFields as $field) {
                        $acfField = acf_get_field($field['field_key']);
                        $value = acf_get_value($postId, $acfField);
                        if ('gallery' == $acfField['type'] && 'array' != ArrayHelper::get($acfField, 'return_format')) {
                            $acfField['return_format'] = 'array';
                        }
                        $value = acf_format_value($value, $postId, $acfField);
                        $meta_fields['advanced_acf_metas'][] = [
                            "name" => $field['field_value'],
                            "type" => $acfField['type'],
                            "value" => $value
                        ];
                    }
                }
            }

            if (JetEngineHelper::hasJetEngine()) {
                JetEngineHelper::maybePopulateMetaFields($meta_fields, $feed, $postId, $formId);
            }
            if (MetaboxHelper::hasMetabox()) {
                MetaboxHelper::maybePopulatePostUpdateMetaFields($meta_fields, $feed, $postId, $formId);
            }
        }
		return $meta_fields;
	}

    private function getFormFieldName($value = '')
    {
        preg_match('/{+(.*?)}/', $value, $matches);
        if ($matches && strpos($matches[1], 'inputs.') !== false) {
            return substr($matches[1], strlen('inputs.'));
        }
        return '';
    }

}
