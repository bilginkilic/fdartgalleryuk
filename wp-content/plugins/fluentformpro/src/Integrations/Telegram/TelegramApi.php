<?php

namespace FluentFormPro\Integrations\Telegram;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class TelegramApi
{
    private $token = '';
    private $chatId = '';
    private $parseMode = 'none';

    private $apiBase = 'https://api.telegram.org/bot';

    public function __construct($token = '', $chatId = '')
    {
        $this->token = $token;
        $this->chatId = $chatId;
    }

    public function setChatId($chatId)
    {
        $this->chatId = $chatId;
        return $this;
    }

    public function setToken($token)
    {
        $this->token = $token;
        return $this;
    }

    public function setParseMode($mode)
    {
        $this->parseMode = $mode;
        return $this;
    }

    public function sendMessage($message, $parseMode = '')
    {
        if (!$message) {
            return new \WP_Error(300, 'Message is required', []);
        }

        if (!$this->token) {
            return new \WP_Error(300, 'Token is required', []);
        }

        if (!$parseMode) {
            $parseMode = $this->parseMode;
        }

        if ($parseMode == 'none') {
            $message = $this->clearText($message);
        }

        return $this->sendRequest('sendMessage', [
            'chat_id'    => $this->chatId,
            'parse_mode' => $parseMode,
            'text'       => $message
        ]);
    }

    public function getMe()
    {
        return $this->sendRequest('getMe', []);
    }

    private function getBaseUrl()
    {
        return $this->apiBase . $this->token . '/';
    }

    private function clearText($html)
    {
        // Convert HTML line breaks to newlines
        $text = str_replace(['<br>'], "\n", $html);
        
        // Remove HTML tags but preserve newlines
        $text = strip_tags($text);
        
        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        
        return $text;
    }

    public function sendRequest($endPoint, $args = [])
    {
        if(!$this->token) {
            return new \WP_Error(300, 'Token is required', []);
        }

        $url = $this->getBaseUrl() . $endPoint;

        $postData = http_build_query($args);

        $ch = curl_init();
        $optArray = array(
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postData,
            CURLOPT_HTTPHEADER     => array('Content-Type: application/x-www-form-urlencoded'),
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        );
        curl_setopt_array($ch, $optArray);
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return new \WP_Error(300, 'CURL Error: ' . $curlError);
        }

        if ($httpCode !== 200) {
            $errorDetails = '';
            if ($result) {
                $decodedResult = json_decode($result, true);
                if ($decodedResult && isset($decodedResult['description'])) {
                    $errorDetails = ' - ' . $decodedResult['description'];
                }
            }
            return new \WP_Error($httpCode, 'HTTP Error: ' . $httpCode . $errorDetails . ' | URL: ' . $url . ' | Data: ' . $postData);
        }

        $result = \json_decode($result, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new \WP_Error(300, 'Invalid JSON response from Telegram API: ' . json_last_error_msg());
        }

        if (isset($result['ok'])) {
            if(!empty($result['ok'])) {
                return $result;
            }
            $errorCode = isset($result['error_code']) ? $result['error_code'] : 400;
            $description = isset($result['description']) ? $result['description'] : 'Unknown error';
            return new \WP_Error($errorCode, $description, $result);
        }

        return new \WP_Error(300, __('Unknown API error from Telegram', 'fluentformpro'), $result);
    }

}