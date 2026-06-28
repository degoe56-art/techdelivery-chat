<?php

if (!defined('ABSPATH')) exit;

class TDAI_OpenAI
{
    public static function api_key()
    {
        $env = getenv('OPENAI_API_KEY');

        if (!empty($env)) {
            return trim($env);
        }

        return trim((string) get_option(TDAI_OPENAI_API_KEY_OPTION, ''));
    }

    public static function has_api_key()
    {
        return self::api_key() !== '';
    }

    public static function embedding(string $text)
    {
        $key = self::api_key();

        if (!$key) {
            return false;
        }

        $response = wp_remote_post(
            'https://api.openai.com/v1/embeddings',
            [
                'timeout' => 60,
                'headers' => [
                    'Authorization' => 'Bearer ' . $key,
                    'Content-Type'  => 'application/json',
                ],
                'body' => wp_json_encode([
                    'model' => 'text-embedding-3-small',
                    'input' => $text,
                ]),
            ]
        );

        if (is_wp_error($response)) {
            return false;
        }

        if (wp_remote_retrieve_response_code($response) !== 200) {
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        return $body['data'][0]['embedding'] ?? false;
    }

    public static function chat(array $messages)
    {
        $key = self::api_key();

        if (!$key) {
            return false;
        }

        $response = wp_remote_post(
            'https://api.openai.com/v1/chat/completions',
            [
                'timeout' => 120,
                'headers' => [
                    'Authorization' => 'Bearer ' . $key,
                    'Content-Type'  => 'application/json',
                ],
                'body' => wp_json_encode([
                    'model' => 'gpt-4o-mini',
                    'messages' => $messages,
                    'temperature' => 0.2,
                    'max_tokens' => 500,
                ]),
            ]
        );

        if (is_wp_error($response)) {
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        $raw_body = wp_remote_retrieve_body($response);
        $body = json_decode($raw_body, true);

        if ($code !== 200) {
            return false;
        }

        if (!isset($body['choices'][0]['message']['content'])) {
            return false;
        }

        return trim($body['choices'][0]['message']['content']);
    }
}