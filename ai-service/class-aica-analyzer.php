<?php

if (!defined('ABSPATH')) {
    exit;
}

class AICA_Analyzer
{
    private $ai_service;

    public function __construct($ai_service)
    {
        $this->ai_service = $ai_service;
    }

    public function analyze_post_comments($post_id, $force = false, $runtime_options = [])
    {
        $post_id = (int) $post_id;
        $min_comments_to_analyze = max(1, (int) get_option('aica_min_comments_to_analyze', 1));
        $runtime_options = is_array($runtime_options) ? $runtime_options : [];
        $runtime_incremental_threshold = isset($runtime_options['incremental_threshold']) ? (int) $runtime_options['incremental_threshold'] : 0;
        $incremental_threshold = $runtime_incremental_threshold > 0
            ? max(1, $runtime_incremental_threshold)
            : max(1, (int) get_option('aica_incremental_new_comments_threshold', 10));
        $approved_comments_count = (int) get_comments([
            'post_id' => $post_id,
            'status' => 'approve',
            'type__not_in' => ['pingback', 'trackback'],
            'count' => true,
        ]);

        if ($approved_comments_count < $min_comments_to_analyze) {
            return new WP_Error(
                'aica_below_min_comments',
                sprintf('تعداد نظرات کمتر از حداقل تعیین‌شده (%d) است.', $min_comments_to_analyze)
            );
        }

        $cache_hours = (int) get_option('aica_cache_hours', 6);
        $cache_variant = [
            'model' => (string) get_option('aica_model', 'gpt-4o-mini'),
            'tone' => (string) get_option('aica_analysis_tone', 'neutral'),
            'detail' => (string) get_option('aica_analysis_detail_level', 'normal'),
        ];
        $cache_key = 'aica_analysis_' . $post_id . '_' . md5(wp_json_encode($cache_variant));
        $existing_analysis = AICA_Database::get_analysis($post_id);
        $product_name = trim(wp_strip_all_tags(get_the_title($post_id)));
        if ($product_name === '') {
            $product_name = 'نامشخص';
        }
        $model = get_option('aica_model', 'gpt-4o-mini');
        $max_comments = max(1, (int) get_option('aica_max_comments_per_post', 1000));
        $last_analyzed_comment_id = (int) get_post_meta($post_id, '_aica_last_analyzed_comment_id', true);

        if (!$force) {
            $new_comments_count = $this->count_new_approved_comments($post_id, $last_analyzed_comment_id);

            if ($existing_analysis && $new_comments_count <= 0) {
                set_transient($cache_key, $existing_analysis, HOUR_IN_SECONDS * max(1, $cache_hours));
                return $existing_analysis;
            }

            if ($existing_analysis && $new_comments_count < $incremental_threshold) {
                return $existing_analysis;
            }

            $cached = get_transient($cache_key);
            if ($cached && (!$existing_analysis || $new_comments_count < $incremental_threshold)) {
                return $cached;
            }
        }

        $comments_query_args = [
            'post_id' => $post_id,
            'status' => 'approve',
            'type__not_in' => ['pingback', 'trackback'],
            'orderby' => 'comment_ID',
            'order' => 'ASC',
            'number' => $max_comments,
        ];
        $comments = get_comments($comments_query_args);
        if (!$force && $existing_analysis && $last_analyzed_comment_id > 0) {
            $comments = array_values(array_filter($comments, function ($comment) use ($last_analyzed_comment_id) {
                return isset($comment->comment_ID) && (int) $comment->comment_ID > $last_analyzed_comment_id;
            }));
        }

        if (empty($comments)) {
            if ($existing_analysis) {
                return $existing_analysis;
            }
            return new WP_Error('aica_no_comments', 'نظری برای تحلیل وجود ندارد.');
        }

        $comment_texts = array_map(function ($comment) {
            return wp_strip_all_tags($comment->comment_content);
        }, $comments);

        // خلاصه‌سازی سلسله‌ای برای مدیریت هزینه و محدودیت توکن
        $chunk_size = max(10, (int) get_option('aica_chunk_size', 50));
        $chunks = array_chunk($comment_texts, $chunk_size);
        $partial_results = [];
        foreach ($chunks as $chunk) {
            $result = $this->ai_service->analyze_comments($chunk, $model, [
                'product_name' => $product_name,
            ]);
            if (is_wp_error($result)) {
                return $result;
            }
            $partial_results[] = $result;
        }

        $incremental_result = $this->merge_results($partial_results, [
            'model' => $model,
            'product_name' => $product_name,
        ]);

        $final_result = $incremental_result;
        if (!$force && $existing_analysis) {
            $merged = $this->ai_service->consolidate_analysis_results([$existing_analysis, $incremental_result], $model, [
                'product_name' => $product_name,
            ]);
            if (!is_wp_error($merged) && is_array($merged)) {
                $final_result = $merged;
            }
        }
        $final_result = $this->apply_feature_toggles($final_result);

        $latest_comment = end($comments);
        if ($latest_comment && isset($latest_comment->comment_ID)) {
            update_post_meta($post_id, '_aica_last_analyzed_comment_id', (int) $latest_comment->comment_ID);
        }

        AICA_Database::save_analysis($post_id, $final_result);
        set_transient($cache_key, $final_result, HOUR_IN_SECONDS * max(1, $cache_hours));

        return $final_result;
    }

    private function count_new_approved_comments($post_id, $last_analyzed_comment_id)
    {
        global $wpdb;
        $post_id = (int) $post_id;
        $last_analyzed_comment_id = (int) $last_analyzed_comment_id;

        if ($last_analyzed_comment_id <= 0) {
            return (int) get_comments([
                'post_id' => $post_id,
                'status' => 'approve',
                'type__not_in' => ['pingback', 'trackback'],
                'count' => true,
            ]);
        }

        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->comments}
             WHERE comment_post_ID = %d
               AND comment_approved = '1'
               AND comment_type NOT IN ('pingback', 'trackback')
               AND comment_ID > %d",
            $post_id,
            $last_analyzed_comment_id
        );

        return (int) $wpdb->get_var($sql);
    }

    private function apply_feature_toggles($result)
    {
        if (!(bool) get_option('aica_enable_summary', 1)) {
            $result['summary'] = '';
            $result['short_summary'] = '';
        }

        if (!(bool) get_option('aica_enable_sentiment', 1)) {
            $result['sentiment_positive'] = 0;
            $result['sentiment_neutral'] = 0;
            $result['sentiment_negative'] = 0;
        }

        if (!(bool) get_option('aica_enable_topics', 1)) {
            $result['topics'] = [];
            $result['topic_summaries'] = [];
            $result['faq'] = [];
        }

        return $result;
    }

    private function merge_results($results, $context = [])
    {
        if (count($results) === 1) {
            return $results[0];
        }

        $model = sanitize_text_field((string) ($context['model'] ?? ''));
        if ($model === '') {
            $model = get_option('aica_model', 'gpt-4o-mini');
        }
        $product_name = sanitize_text_field((string) ($context['product_name'] ?? 'نامشخص'));
        $consolidated = $this->ai_service->consolidate_analysis_results($results, $model, [
            'product_name' => $product_name,
        ]);
        if (!is_wp_error($consolidated) && is_array($consolidated)) {
            return $consolidated;
        }

        $summary_parts = [];
        $short_summary_parts = [];
        $topics = [];
        $topic_summaries = [];
        $faq = [];
        $pos = 0;
        $neu = 0;
        $neg = 0;

        foreach ($results as $result) {
            $summary_parts[] = $result['summary'] ?? '';
            $short_summary_parts[] = $result['short_summary'] ?? '';
            $pos += (float) ($result['sentiment_positive'] ?? 0);
            $neu += (float) ($result['sentiment_neutral'] ?? 0);
            $neg += (float) ($result['sentiment_negative'] ?? 0);
            $topics = array_merge($topics, $result['topics'] ?? []);
            $topic_summaries = array_merge($topic_summaries, $result['topic_summaries'] ?? []);
            $faq = array_merge($faq, $result['faq'] ?? []);
        }

        $count = max(1, count($results));

        return [
            'summary' => implode("\n", array_filter($summary_parts)),
            'short_summary' => sanitize_text_field($short_summary_parts[0] ?? 'نظر کلی کاربران متعادل است.'),
            'sentiment_positive' => round($pos / $count, 2),
            'sentiment_neutral' => round($neu / $count, 2),
            'sentiment_negative' => round($neg / $count, 2),
            'topics' => array_values(array_unique(array_filter($topics))),
            'topic_summaries' => $topic_summaries,
            'faq' => array_slice($faq, 0, 8),
        ];
    }

    public function should_flag_spam($comment_content)
    {
        $model = get_option('aica_model', 'gpt-4o-mini');
        $result = $this->ai_service->analyze_comments([$comment_content], $model);

        if (is_wp_error($result)) {
            return false;
        }

        $probability = (float) ($result['spam_probability'] ?? 0);
        return $probability >= 0.75;
    }

    public function generate_suggested_reply($comment_text)
    {
        $model = get_option('aica_model', 'gpt-4o-mini');
        return $this->ai_service->suggest_reply($comment_text, $model);
    }
}
