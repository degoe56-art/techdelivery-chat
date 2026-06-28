<?php

if (!defined('ABSPATH')) exit;

class TDAI_REST
{
    public function __construct()
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes()
    {
        register_rest_route('tdai-chat/v1', '/ask', [
            'methods'             => 'POST',
            'callback'            => [$this, 'ask'],
            'permission_callback' => '__return_true',
            'args' => [
                'q' => [
                    'required' => true,
                    'type'     => 'string',
                ],
            ],
        ]);

        register_rest_route('tdai-chat/v1', '/index/start', [
            'methods'             => 'POST',
            'callback'            => [$this, 'index_start'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);

        register_rest_route('tdai-chat/v1', '/index/step', [
            'methods'             => 'POST',
            'callback'            => [$this, 'index_step'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);
    }

    public function index_start(WP_REST_Request $request)
    {
        return new WP_REST_Response(TDAI_Indexer::start_job(), 200);
    }

    public function index_step(WP_REST_Request $request)
    {
        return new WP_REST_Response(TDAI_Indexer::step_job(2), 200);
    }

    public function ask(WP_REST_Request $request)
    {
        $question = trim(sanitize_text_field((string) $request->get_param('q')));

        if ($question === '') {
            return new WP_REST_Response(['error' => 'Empty query'], 400);
        }

        if (!TDAI_OpenAI::has_api_key()) {
            return new WP_REST_Response([
                'answer' => 'Chatboten mangler OpenAI API-key.'
            ], 200);
        }

        $question_embedding = TDAI_OpenAI::embedding($question);

        if (!$question_embedding) {
            return new WP_REST_Response([
                'answer' => 'Klarte ikke lage embedding for spørsmålet.'
            ], 200);
        }

        $docs = $this->top_docs($question_embedding, 5, $question);

        if (empty($docs)) {
            return new WP_REST_Response([
                'answer' => 'Jeg finner ikke svaret på nettstedet.'
            ], 200);
        }

        $context_parts = [];

        foreach ($docs as $doc) {
            $context_parts[] =
                "TITTEL: {$doc['title']}\n" .
                "URL: {$doc['url']}\n" .
                "INNHOLD:\n{$doc['content']}";
        }

        $context = implode("\n\n-----\n\n", $context_parts);

        $site_name = get_bloginfo('name');

        $messages = [
            [
    'role' => 'system',
    'content' => 'Du er en hjelpsom chatbot for nettstedet ' . $site_name . '. Svar alltid på norsk. Svar kort og presist basert på konteksten. Hvis svaret ikke finnes i konteksten, si at du ikke finner svaret på nettstedet. Ikke oppgi URL-er. Ikke skriv Kilder. Pluginen legger automatisk til kildene etter svaret.',
],
            [
                'role' => 'user',
                'content' =>
                    "Spørsmål:\n{$question}\n\n" .
                    "Kontekst:\n{$context}",
            ],
        ];

        $answer = TDAI_OpenAI::chat($messages);

        if (!$answer) {
            return new WP_REST_Response([
                'answer' => 'Klarte ikke hente svar fra AI-modellen.'
            ], 200);
        }

        return new WP_REST_Response([
            'answer'  => $answer,
            'sources' => $docs,
        ], 200);
    }

    private function top_docs($query_embedding, $limit = 10, $query = '')
{
    global $wpdb;

    $table = $wpdb->prefix . TDAI_EMBED_TABLE;

    $cache_key = 'tdai_rest_index_rows';
    $rows = wp_cache_get($cache_key, 'tdai');

    if (false === $rows) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results(
            'SELECT id, title, url, content, embedding FROM ' . esc_sql($table),
            ARRAY_A
        );

        wp_cache_set($cache_key, $rows, 'tdai', 300);
    }

    if (!$rows) {
        return [];
    }

    $query_lc = mb_strtolower($query);
    $keywords = preg_split(
        '/\s+/',
        preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $query_lc)
    );

    $scored = [];

    foreach ($rows as $row) {
        $embedding = json_decode($row['embedding'], true);

        if (!is_array($embedding)) {
            continue;
        }

        $score = $this->cosine_similarity($query_embedding, $embedding);

        $title_lc = mb_strtolower($row['title']);
        $url_lc = mb_strtolower($row['url']);
        $content_lc = mb_strtolower($row['content']);

        foreach ($keywords as $keyword) {
            if (mb_strlen($keyword) < 3) {
                continue;
            }

            if (mb_strpos($title_lc, $keyword) !== false) {
                $score += 0.35;
            }

            if (mb_strpos($url_lc, $keyword) !== false) {
                $score += 0.35;
            }

            if (mb_strpos($content_lc, $keyword) !== false) {
                $score += 0.12;
            }
        }

        $row['score'] = $score;
        unset($row['embedding']);

        $scored[] = $row;
    }

    usort($scored, function ($a, $b) {
        return $b['score'] <=> $a['score'];
    });

    return array_slice($scored, 0, $limit);
}

    private function cosine_similarity($a, $b)
    {
        $dot = 0.0;
        $norm_a = 0.0;
        $norm_b = 0.0;

        $length = min(count($a), count($b));

        for ($i = 0; $i < $length; $i++) {
            $dot += $a[$i] * $b[$i];
            $norm_a += $a[$i] * $a[$i];
            $norm_b += $b[$i] * $b[$i];
        }

        if ($norm_a == 0 || $norm_b == 0) {
            return 0.0;
        }

        return $dot / (sqrt($norm_a) * sqrt($norm_b));
    }
}