<?php

namespace FluentFormPro\Integrations\CleverReach;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class API
{
    protected $clientId = null;

    protected $clientSecret = null;

    protected $accessToken = null;

    protected $callBackUrl = null;

    protected $settings = [];

    protected $optionKey = null;

    public function __construct($settings)
    {
        $this->clientId = isset($settings['client_id']) ? $settings['client_id'] : '';
        $this->clientSecret = isset($settings['client_secret']) ? $settings['client_secret'] : '';
        $this->accessToken = isset($settings['access_token']) ? $settings['access_token'] : '';
        $this->callBackUrl = admin_url('?ff_cleverreach_auth');
        $this->settings = $settings;
        if (isset($settings['option_key'])) {
            $this->optionKey = $settings['option_key'];
        }
    }

    public function redirectToAuthServer()
    {
        $url = 'https://rest.cleverreach.com/oauth/authorize.php?client_id=' . $this->clientId . '&grant=basic&response_type=code&redirect_uri=' . $this->callBackUrl;

        wp_redirect($url);
        exit();
    }

    public function checkForClientId()
    {
        $url = 'https://rest.cleverreach.com/oauth/authorize.php?client_id=' . $this->clientId . '&grant=basic&response_type=code&redirect_uri=' . $this->callBackUrl;
        $response = wp_remote_get($url);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $body = \json_decode($body, true);

        if (isset($body['error_description'])) {
            return new \WP_Error('invalid_client', $body['error_description']);
        }
    }

    public function generateAccessToken($code, $settings)
    {
        $response = wp_remote_post('https://rest.cleverreach.com/oauth/token.php', [
            'body' => [
                'client_id'     => $this->clientId,
                'client_secret' => $this->clientSecret,
                'grant_type'    => 'authorization_code',
                'redirect_uri'  => $this->callBackUrl,
                'code'          => $code
            ]
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $body = \json_decode($body, true);

        if (isset($body['error_description'])) {
            return new \WP_Error('invalid_client', $body['error_description']);
        }

        $settings['access_token'] = $body['access_token'];
        $settings['refresh_token'] = $body['refresh_token'];
        $settings['expire_at'] = time() + intval($body['expires_in']);
        return $settings;
    }

    protected function getApiSettings()
    {
        $this->maybeRefreshToken();

        if (is_wp_error($this->settings)) {
            return $this->settings;
        }

        if (!isset($this->settings['status']) || !$this->settings['status']) {
            return new \WP_Error('invalid', __('API key is invalid', 'fluentformpro'));
        }

        if (empty($this->settings['access_token'])) {
            return new \WP_Error('invalid', __('Access token is missing', 'fluentformpro'));
        }

        return [
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'callback'      => $this->callBackUrl,
            'access_token'  => $this->settings['access_token'],
            'refresh_token' => isset($this->settings['refresh_token']) ? $this->settings['refresh_token'] : ''
        ];
    }

    protected function maybeRefreshToken()
    {
        if (empty($this->settings['refresh_token'])) {
            return;
        }

        $response = wp_remote_post('https://rest.cleverreach.com/oauth/token.php', [
            'body' => [
                'client_id'     => $this->clientId,
                'client_secret' => $this->clientSecret,
                'grant_type'    => 'refresh_token',
                'refresh_token' => $this->settings['refresh_token'],
                'redirect_uri'  => $this->callBackUrl
            ]
        ]);

        if (is_wp_error($response)) {
            $this->settings = $response;
            return;
        }

        $body = wp_remote_retrieve_body($response);
        $body = \json_decode($body, true);

        if (isset($body['error_description'])) {
            $this->settings = new \WP_Error('invalid_client', $body['error_description']);
            return;
        }

        if (!isset($body['access_token']) || !isset($body['refresh_token'])) {
            return;
        }

        $this->settings['access_token'] = $body['access_token'];
        $this->settings['refresh_token'] = $body['refresh_token'];
        $this->accessToken = $body['access_token'];

        if ($this->optionKey) {
            $existing = get_option($this->optionKey, []);
            if (!is_array($existing)) {
                $existing = [];
            }
            $existing['access_token'] = $body['access_token'];
            $existing['refresh_token'] = $body['refresh_token'];
            if (isset($body['expires_in'])) {
                $existing['expire_at'] = time() + intval($body['expires_in']);
            }
            update_option($this->optionKey, $existing, 'no');
        }
    }

    public function makeRequest($url, $bodyArgs, $type = 'GET', $headers = false)
    {
        $apiSettings = $this->getApiSettings();
        if (is_wp_error($apiSettings)) {
            return $apiSettings;
        }

        $this->accessToken = $apiSettings['access_token'];

        if (!is_array($headers)) {
            $headers = [];
        }

        $headers['Content-type'] = 'application/x-www-form-urlencoded';
        $headers['Authorization'] = 'Bearer ' . $this->accessToken;

        $args = [
            'headers' => $headers
        ];

        if ($bodyArgs) {
            $args['body'] = $bodyArgs;
        }

        $args['method'] = $type;

        $request = wp_remote_request($url, $args);

        if (is_wp_error($request)) {
            $message = $request->get_error_message();
            return new \WP_Error(423, $message);
        }

        $body = json_decode(wp_remote_retrieve_body($request), true);

        if (!empty($body['error'])) {
            $error = 'Unknown Error';
            if (isset($body['error_description'])) {
                $error = $body['error_description'];
            } elseif (!empty($body['error']['message'])) {
                $error = $body['error']['message'];
            }
            return new \WP_Error(423, $error);
        }

        return $body;
    }

    public function subscribe($subscriber)
    {
        $response = $this->makeRequest(
            'https://rest.cleverreach.com/v3/groups/' . $subscriber['list_id'] . '/receivers',
            $subscriber,
            'POST'
        );

        if (is_wp_error($response)) {
            return $response;
        }

        if (isset($response['errors'])) {
            $errorMessage = is_array($response['errors']) ? json_encode($response['errors']) : $response['errors'];
            return new \WP_Error('error', $errorMessage);
        }

        return $response;
    }
}
