<?php

if (!defined('ABSPATH')) {
    exit;
}

class AICA_Database
{
    public static function table_name()
    {
        global $wpdb;
        return $wpdb->prefix . 'ai_comment_analysis';
    }

    public static function create_tables()
    {
        global $wpdb;

        $table_name = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT UNSIGNED NOT NULL,
            summary LONGTEXT NULL,
            short_summary VARCHAR(255) NULL,
            sentiment_positive DECIMAL(5,2) DEFAULT 0,
            sentiment_neutral DECIMAL(5,2) DEFAULT 0,
            sentiment_negative DECIMAL(5,2) DEFAULT 0,
            topics LONGTEXT NULL,
            topic_summaries LONGTEXT NULL,
            faq LONGTEXT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY post_id (post_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public static function save_analysis($post_id, $data)
    {
        global $wpdb;

        $table_name = self::table_name();
        $existing = self::get_analysis($post_id);

        $insert_data = [
            'post_id' => (int) $post_id,
            'summary' => wp_kses_post($data['summary'] ?? ''),
            'short_summary' => sanitize_text_field($data['short_summary'] ?? ''),
            'sentiment_positive' => (float) ($data['sentiment_positive'] ?? 0),
            'sentiment_neutral' => (float) ($data['sentiment_neutral'] ?? 0),
            'sentiment_negative' => (float) ($data['sentiment_negative'] ?? 0),
            'topics' => wp_json_encode($data['topics'] ?? [], JSON_UNESCAPED_UNICODE),
            'topic_summaries' => wp_json_encode($data['topic_summaries'] ?? [], JSON_UNESCAPED_UNICODE),
            'faq' => wp_json_encode($data['faq'] ?? [], JSON_UNESCAPED_UNICODE),
            'updated_at' => current_time('mysql'),
        ];

        if ($existing) {
            return $wpdb->update($table_name, $insert_data, ['post_id' => (int) $post_id]);
        }

        return $wpdb->insert($table_name, $insert_data);
    }

    public static function get_analysis($post_id)
    {
        global $wpdb;
        $table_name = self::table_name();

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table_name} WHERE post_id = %d", (int) $post_id),
            ARRAY_A
        );

        if (!$row) {
            return null;
        }

        $row['topics'] = json_decode($row['topics'], true) ?: [];
        $row['topic_summaries'] = json_decode($row['topic_summaries'], true) ?: [];
        $row['faq'] = json_decode($row['faq'], true) ?: [];

        return $row;
    }
}
