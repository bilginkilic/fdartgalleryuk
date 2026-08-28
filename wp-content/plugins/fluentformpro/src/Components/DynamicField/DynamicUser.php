<?php

namespace FluentFormPro\Components\DynamicField;


use FluentForm\Framework\Helpers\ArrayHelper as Arr;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class DynamicUser extends DynamicBase
{

    public function __construct()
    {
        parent::__construct('user', 'users', $this->joinTables());
        add_filter("fluentform/dynamic_field_basic_filters_{$this->source}", [$this, 'resolveBasicFilterQuery'], 10, 2);
    }

    public function resolveBasicFilterQuery($filters, $basicQuery)
    {
        if ($roleName = Arr::get($basicQuery, 'role_name')) {
            $filters = [
                [
                    [
                        'column'    => 'meta_key',
                        'custom'   => false,
                        'operator' => '=',
                        'value'    => $this->wpdb->prefix . 'capabilities'
                    ]
                ]
            ];
            if ('all' != $roleName) {
                $filters[0][] = [
                    'column'    => 'meta_value',
                    'custom'   => false,
                    'operator' => 'contains',
                    'value'    => $roleName
                ];
            }
        }
        return $filters;
    }

    public function selectableColumns()
    {
        return [
            'ID',
            'user_login',
            'display_name',
            'user_nicename',
            'user_email',
            'user_url',
        ];
    }

    public function getSupportedColumns()
    {
        return [
            'user_login'    => __('Username', 'fluentformpro'),
            'ID'            => __('ID', 'fluentformpro'),
            'display_name'  => __('Display Name', 'fluentformpro'),
            'user_nicename' => __('Nicename', 'fluentformpro'),
            'user_email'    => __('Email', 'fluentformpro'),
            'user_url'      => __('User URL', 'fluentformpro'),
            'meta_key'      => __('Meta Key', 'fluentformpro'),
            'meta_value'    => __('Meta Value', 'fluentformpro'),
        ];
    }

    protected function joinTables()
    {
        return [
            [
                'enable'  => false,
                'columns' => ['meta_key', 'meta_value'],
                'join'    => ['usermeta', 'users.ID', '=', 'usermeta.user_id']
            ]
        ];
    }

    /**
     * Retrieve the value options for the editor.
     *
     * @return array The value options array.
     */
    public function getValueOptions()
    {
        $displayName = $nicename = $emails = $userLogins = $IDs = [];
        foreach (get_users(array('number' => $this->getEditorValueOptionsLimit())) as $user) {
            if ($user->ID) {
                $IDs[$user->ID] = $user->ID;
            }
            if ($user->display_name) {
                $displayName[$user->display_name] = $user->display_name;
            }

            if ($user->user_nicename) {
                $nicename[$user->user_nicename] = $user->user_nicename;
            }

            if ($user->user_email) {
                $emails[$user->user_email] = $user->user_email;
            }

            if ($user->user_login) {
                $userLogins[$user->user_login] = $user->user_login;
            }
        }
        $wp_roles = wp_roles();
        $roles = $wp_roles->roles;
        $formattedRoles = [
            'all' => __('All', 'fluentformpro')
        ];
        foreach ( $roles as $role_slug => $role_details ) {
            $formattedRoles[$role_slug] = $role_details['name'];
        }

        return [
            'display_name'  => $displayName,
            'user_nicename' => $nicename,
            'user_email'    => $emails,
            'user_login'    => $userLogins,
            'user_roles'    => $formattedRoles,
            'ID'            => $IDs,
        ];
    }

    public function getDefaultConfig()
    {
        $filters = [
            [
                [
                    'column'    => 'meta_key',
                    'custom'   => false,
                    'operator' => '=',
                    'value'    => $this->wpdb->prefix . 'capabilities'
                ],
                [
                    'column'    => 'meta_value',
                    'custom'   => false,
                    'operator' => 'contains',
                    'value'    => 'subscriber'
                ]
            ]
        ];
        return [
            'filters'        => $filters,
            'sort_by'        => 'ID',
            'order_by'       => 'DESC',
            'query_type'     => 'basic',
            'basic_query'    => [
                'role_name' => 'all',
            ],
            'result_limit'   => $this->getResultLimit(),
            'template_value' => [
                'value'  => '{ID}',
                'custom' => false
            ],
            'template_label' => [
                'value'  => '{display_name} ({ID})',
                'custom' => true
            ]
        ];
    }
}