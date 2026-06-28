<?php

if (!defined('ABSPATH')) exit;

class TDAI_Indexer
{
    public static function index_all()
    {
        if (!TDAI_OpenAI::has_api_key()) {
            return [
                'success' => false,
                'message' => 'Mangler OpenAI API-key.',
                'inserted' => 0,
                'updated' => 0,
                'skipped' => 0,
            ];
        }

        $urls = self::collect_urls();
        $inserted = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($urls as $post) {
            if (self::needs_reindex($post)) {
                self::index_post($post, $inserted);
                $updated++;
            } else {
                $skipped++;
            }
        }

        update_option('tdai_last_indexed_at', current_time('mysql'), false);
        update_option('tdai_last_indexed_count', self::count_indexed_chunks(), false);

        return [
            'success' => true,
            'message' => 'Indeksering fullført.',
            'inserted' => $inserted,
            'updated' => $updated,
            'skipped' => $skipped,
            'total_chunks' => self::count_indexed_chunks(),
        ];
    }

    public static function start_job()
    {
        if (!TDAI_OpenAI::has_api_key()) {
            return [
                'success' => false,
                'message' => 'Mangler OpenAI API-key.',
                'total' => 0,
                'position' => 0,
                'inserted' => 0,
                'updated' => 0,
                'skipped' => 0,
                'total_chunks' => self::count_indexed_chunks(),
            ];
        }

        $urls = self::collect_urls();

        update_option('tdai_index_urls', $urls, false);
        update_option('tdai_index_position', 0, false);
        update_option('tdai_index_inserted', 0, false);
        update_option('tdai_index_updated', 0, false);
        update_option('tdai_index_skipped', 0, false);
        update_option('tdai_index_running', 1, false);

        return [
            'success' => true,
            'message' => 'Inkrementell indeksering startet.',
            'total' => count($urls),
            'position' => 0,
            'inserted' => 0,
            'updated' => 0,
            'skipped' => 0,
            'total_chunks' => self::count_indexed_chunks(),
        ];
    }

    public static function step_job($batch_size = 2)
    {
        $urls = get_option('tdai_index_urls', []);
        $position = intval(get_option('tdai_index_position', 0));
        $inserted = intval(get_option('tdai_index_inserted', 0));
        $updated = intval(get_option('tdai_index_updated', 0));
        $skipped = intval(get_option('tdai_index_skipped', 0));
        $total = is_array($urls) ? count($urls) : 0;

        if (!$urls || $position >= $total) {
            update_option('tdai_index_running', 0, false);
            update_option('tdai_last_indexed_at', current_time('mysql'), false);
            update_option('tdai_last_indexed_count', self::count_indexed_chunks(), false);

            return [
                'success' => true,
                'done' => true,
                'message' => 'Indeksering fullført.',
                'total' => $total,
                'position' => $total,
                'inserted' => $inserted,
                'updated' => $updated,
                'skipped' => $skipped,
                'total_chunks' => self::count_indexed_chunks(),
                'current_url' => '',
            ];
        }

        $batch_size = max(1, intval($batch_size));
        $end = min($position + $batch_size, $total);
        $current_url = '';

        for ($i = $position; $i < $end; $i++) {
            $post = $urls[$i];

            if (!$post || empty($post->ID)) {
                continue;
            }

            $current_url = get_permalink($post->ID);

            if (self::needs_reindex($post)) {
                self::index_post($post, $inserted);
                $updated++;
            } else {
                $skipped++;
            }
        }

        update_option('tdai_index_position', $end, false);
        update_option('tdai_index_inserted', $inserted, false);
        update_option('tdai_index_updated', $updated, false);
        update_option('tdai_index_skipped', $skipped, false);

        $done = $end >= $total;

        if ($done) {
            update_option('tdai_index_running', 0, false);
            update_option('tdai_last_indexed_at', current_time('mysql'), false);
            update_option('tdai_last_indexed_count', self::count_indexed_chunks(), false);
        }

        return [
            'success' => true,
            'done' => $done,
            'message' => $done ? 'Indeksering fullført.' : 'Indekserer...',
            'total' => $total,
            'position' => $end,
            'inserted' => $inserted,
            'updated' => $updated,
            'skipped' => $skipped,
            'total_chunks' => self::count_indexed_chunks(),
            'current_url' => $current_url,
        ];
    }

    private static function index_post($post, &$inserted)
    {
        global $wpdb;

        if (!$post || empty($post->ID)) {
            return;
        }

        $url = get_permalink($post->ID);
        $title = $post->post_title;
        $text = wp_strip_all_tags($post->post_content);

        if (mb_strlen($text) < 20) {
            return;
        }

        $content_hash = hash('sha256', $text);
        $table = self::table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->delete(
            $table,
            ['url' => $url],
            ['%s']
        );

        wp_cache_delete('tdai_indexed_chunks_count', 'tdai');

        $chunks = self::chunk_text($text, 90, 30);

        foreach ($chunks as $chunk) {
            $embedding = TDAI_OpenAI::embedding($chunk);

            if (!$embedding) {
                continue;
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $wpdb->insert(
                $table,
                [
                    'post_id'      => intval($post->ID),
                    'title'        => sanitize_text_field($title),
                    'url'          => esc_url_raw($url),
                    'content'      => wp_kses_post($chunk),
                    'content_hash' => sanitize_text_field($content_hash),
                    'embedding'    => wp_json_encode($embedding),
                ],
                ['%d', '%s', '%s', '%s', '%s', '%s']
            );

            wp_cache_delete('tdai_indexed_chunks_count', 'tdai');

            $inserted++;
        }
    }

    private static function needs_reindex($post)
    {
        global $wpdb;

        if (!$post || empty($post->ID)) {
            return false;
        }

        $table = self::table_name();
        $url = get_permalink($post->ID);
        $text = wp_strip_all_tags($post->post_content);

        if (mb_strlen($text) < 20) {
            return false;
        }

        $hash = hash('sha256', $text);

        // LINJE CA. 232 - I needs_reindex()

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$stored_hash = $wpdb->get_var(
    $wpdb->prepare(
        'SELECT content_hash FROM ' . esc_sql($table) . ' WHERE url = %s LIMIT 1',
        $url
    )
);

        return $stored_hash !== $hash;
    }

    private static function count_indexed_chunks()
    {
        global $wpdb;

        $table = self::table_name();
        $cache_key = 'tdai_indexed_chunks_count';

        $cached_count = wp_cache_get($cache_key, 'tdai');

        if (false !== $cached_count) {
            return intval($cached_count);
        }

        // LINJE CA. 258 - I count_indexed_chunks()

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
$count = intval($wpdb->get_var('SELECT COUNT(*) FROM ' . $table));

        wp_cache_set($cache_key, $count, 'tdai', 300);

        return $count;
    }

    private static function table_name()
    {
        global $wpdb;

        return $wpdb->prefix . TDAI_EMBED_TABLE;
    }

    public static function collect_urls()
    {
        return get_posts([
            'post_type'      => ['post', 'page'],
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'modified',
            'order'          => 'DESC',
        ]);
    }

    private static function chunk_text($text, $target_words = 90, $overlap_words = 30)
    {
        $target_words = max(1, intval($target_words));
        $overlap_words = max(0, intval($overlap_words));

        if ($overlap_words >= $target_words) {
            $overlap_words = 0;
        }

        $words = preg_split('/\s+/', trim($text));
        $chunks = [];

        if (!is_array($words)) {
            return $chunks;
        }

        $i = 0;
        $count = count($words);

        while ($i < $count) {
            $chunk_words = array_slice($words, $i, $target_words);
            $chunk = trim(implode(' ', $chunk_words));

            if ($chunk !== '') {
                $chunks[] = $chunk;
            }

            $i += ($target_words - $overlap_words);

            if ($i >= $count) {
                break;
            }
        }

        return $chunks;
    }
}