<?php

if (!defined('ABSPATH')) {
    exit;
}

class AICA_Cron
{
    private $analyzer;
    const HOOK_ANALYZE = 'aica_scheduled_analysis';
    const HOOK_ANALYZE_RULE = 'aica_scheduled_analysis_rule';
    const HOOK_NEGATIVE_ALERT = 'aica_negative_alert_check';
    const SCHEDULE_KEY = 'aica_every_x_minutes';
    const LOG_OPTION_KEY = 'aica_cron_logs';
    const LOG_LIMIT = 10;
    const LAST_OK_LOG_OPTION_KEY = 'aica_cron_last_ok_signature';

    private static function has_enabled_custom_rules()
    {
        $rules = (array) get_option('aica_custom_cron_rules', []);
        foreach ($rules as $rule) {
            if (!empty($rule['enabled'])) {
                return true;
            }
        }
        return false;
    }

    public static function add_log($event, $status = 'info', $context = [])
    {
        $logs = get_option(self::LOG_OPTION_KEY, []);
        if (!is_array($logs)) {
            $logs = [];
        }

        $logs[] = [
            'time' => current_time('mysql'),
            'event' => sanitize_text_field((string) $event),
            'status' => sanitize_key((string) $status),
            'context' => is_array($context) ? array_map(function ($value) {
                if (is_scalar($value)) {
                    return sanitize_text_field((string) $value);
                }
                return sanitize_text_field(wp_json_encode($value, JSON_UNESCAPED_UNICODE));
            }, $context) : [],
        ];

        if (count($logs) > self::LOG_LIMIT) {
            $logs = array_slice($logs, -1 * self::LOG_LIMIT);
        }

        update_option(self::LOG_OPTION_KEY, $logs, false);
    }

    public static function get_logs($limit = 100)
    {
        $logs = get_option(self::LOG_OPTION_KEY, []);
        if (!is_array($logs)) {
            return [];
        }

        $limit = max(1, (int) $limit);
        return array_reverse(array_slice($logs, -1 * $limit));
    }

    private static function get_interval_minutes()
    {
        $time_value = (string) get_option('aica_analysis_interval_time', '');
        if (preg_match('/^(\d{1,2}):([0-5]\d)$/', $time_value, $matches)) {
            $hours = max(0, min(23, (int) $matches[1]));
            $minutes = (int) $matches[2];
            $total = ($hours * 60) + $minutes;
            return max(1, $total);
        }

        $legacy_minutes = (int) get_option('aica_analysis_interval_minutes', 360);
        return max(1, $legacy_minutes);
    }

    public function __construct($analyzer = null)
    {
        $this->analyzer = $analyzer;
    }

    public function register_hooks()
    {
        add_filter('cron_schedules', [$this, 'add_custom_schedule']);
        add_action(self::HOOK_ANALYZE, [$this, 'run_scheduled_analysis']);
        add_action(self::HOOK_ANALYZE_RULE, [$this, 'run_rule_analysis'], 10, 1);
        add_action(self::HOOK_NEGATIVE_ALERT, [$this, 'check_negative_spike']);
        add_action('transition_comment_status', [$this, 'handle_comment_status_transition'], 10, 3);
        add_action('comment_post', [$this, 'handle_comment_post'], 25, 3);
        add_action('init', [$this, 'ensure_analysis_schedule_alignment']);
        add_action('init', [$this, 'ensure_custom_rules_schedule_alignment']);
        add_action('update_option_aica_analysis_interval_time', [$this, 'reschedule_analysis_event'], 10, 2);
        add_action('update_option_aica_custom_cron_rules', [$this, 'reschedule_custom_rule_events'], 10, 2);
    }

    public static function schedule_events()
    {
        self::add_log('schedule_events_called', 'info');

        if (self::has_enabled_custom_rules()) {
            wp_clear_scheduled_hook(self::HOOK_ANALYZE);
            return; 
        }

        if (false) { 
            $minutes = self::get_interval_minutes();
            $last_run = (int) get_option('aica_last_analysis_run', 0);
            $first_run = $last_run > 0 ? ($last_run + (MINUTE_IN_SECONDS * $minutes)) : (time() + (MINUTE_IN_SECONDS * $minutes));
            $scheduled = wp_schedule_event($first_run, self::SCHEDULE_KEY, self::HOOK_ANALYZE);
            self::add_log('schedule_event_attempt', $scheduled ? 'success' : 'warning', [
                'hook' => self::HOOK_ANALYZE,
                'schedule' => self::SCHEDULE_KEY,
                'first_run' => $first_run,
            ]);
        }

        if (!wp_next_scheduled(self::HOOK_NEGATIVE_ALERT)) {
            $scheduled_alert = wp_schedule_event(time() + 600, 'hourly', self::HOOK_NEGATIVE_ALERT);
            self::add_log('schedule_event_attempt', $scheduled_alert ? 'success' : 'warning', [
                'hook' => self::HOOK_NEGATIVE_ALERT,
                'schedule' => 'hourly',
            ]);
        }
    }

    public static function clear_events()
    {
        wp_clear_scheduled_hook(self::HOOK_ANALYZE);
        wp_clear_scheduled_hook(self::HOOK_ANALYZE_RULE);
        wp_clear_scheduled_hook(self::HOOK_NEGATIVE_ALERT);
        self::add_log('cron_events_cleared', 'info');
    }

    public static function get_next_analysis_run_timestamp($self_heal = true)
    {
        if (!(bool) get_option('aica_cron_enabled', 1)) {
            return 0;
        }

        if (self::has_enabled_custom_rules()) {
            $cron_array = _get_cron_array();
            if (!is_array($cron_array) || empty($cron_array)) {
                return 0;
            }
            $nearest = 0;
            foreach ($cron_array as $timestamp => $hooks) {
                if (empty($hooks[self::HOOK_ANALYZE_RULE])) {
                    continue;
                }
                $ts = (int) $timestamp;
                if ($ts <= time()) {
                    continue;
                }
                if ($nearest === 0 || $ts < $nearest) {
                    $nearest = $ts;
                }
            }
            return $nearest;
        }

        $interval_seconds = MINUTE_IN_SECONDS * self::get_interval_minutes();
        $now = time();
        $event = wp_get_scheduled_event(self::HOOK_ANALYZE);
        $next_run = $event ? (int) $event->timestamp : 0;

        if ($next_run <= $now) {
            $last_run = (int) get_option('aica_last_analysis_run', 0);
            $candidate = $last_run > 0 ? ($last_run + $interval_seconds) : ($now + $interval_seconds);
            while ($candidate <= $now) {
                $candidate += $interval_seconds;
            }
            $next_run = $candidate;

            if ($self_heal) {
                wp_clear_scheduled_hook(self::HOOK_ANALYZE);
                wp_schedule_event($next_run, self::SCHEDULE_KEY, self::HOOK_ANALYZE);
                self::add_log('schedule_self_healed', 'warning', ['next_run' => $next_run]);
            }
        }

        if ($next_run <= 0 && $self_heal) {
            $next_run = $now + $interval_seconds;
            wp_schedule_event($next_run, self::SCHEDULE_KEY, self::HOOK_ANALYZE);
            self::add_log('schedule_created_from_dashboard_check', 'info', ['next_run' => $next_run]);
        }

        return (int) $next_run;
    }

    public static function get_next_custom_rule_run_info()
    {
        $rules = (array) get_option('aica_custom_cron_rules', []);
        $rules_by_id = [];
        foreach ($rules as $rule) {
            $rule_id = sanitize_key((string) ($rule['id'] ?? ''));
            if ($rule_id === '' || empty($rule['enabled'])) {
                continue;
            }
            $rules_by_id[$rule_id] = $rule;
        }
        if (empty($rules_by_id)) {
            return null;
        }

        $cron_array = _get_cron_array();
        if (!is_array($cron_array) || empty($cron_array)) {
            return null;
        }

        $nearest = null;
        $now = time();
        foreach ($cron_array as $timestamp => $hooks) {
            if (empty($hooks[self::HOOK_ANALYZE_RULE]) || !is_array($hooks[self::HOOK_ANALYZE_RULE])) {
                continue;
            }
            $ts = (int) $timestamp;
            if ($ts <= $now) {
                continue;
            }
            foreach ($hooks[self::HOOK_ANALYZE_RULE] as $entry) {
                $args = $entry['args'] ?? [];
                $rule_id = sanitize_key((string) ($args[0] ?? ''));
                if ($rule_id === '' || !isset($rules_by_id[$rule_id])) {
                    continue;
                }
                if ($nearest === null || $ts < (int) $nearest['timestamp']) {
                    $nearest = [
                        'timestamp' => $ts,
                        'rule_id' => $rule_id,
                        'rule_name' => (string) ($rules_by_id[$rule_id]['name'] ?? $rule_id),
                    ];
                }
            }
        }

        return $nearest;
    }

    public function add_custom_schedule($schedules)
    {
        $minutes = self::get_interval_minutes();

        $schedules[self::SCHEDULE_KEY] = [
            'interval' => MINUTE_IN_SECONDS * $minutes,
            'display' => sprintf('هر %d دقیقه', $minutes),
        ];

        return $schedules;
    }

    public function ensure_analysis_schedule_alignment()
    {
        if (self::has_enabled_custom_rules()) {
            wp_clear_scheduled_hook(self::HOOK_ANALYZE);
            return;
        }

        if (!(bool) get_option('aica_cron_enabled', 1)) {
            wp_clear_scheduled_hook(self::HOOK_ANALYZE);
            self::add_log('ensure_schedule_skipped', 'info', ['reason' => 'cron_disabled']);
            return;
        }

        $minutes = self::get_interval_minutes();
        $last_run = (int) get_option('aica_last_analysis_run', 0);
        $target_timestamp = $last_run > 0 ? ($last_run + (MINUTE_IN_SECONDS * $minutes)) : (time() + (MINUTE_IN_SECONDS * $minutes));
        $event = wp_get_scheduled_event(self::HOOK_ANALYZE);
        if (!$event) {
            $scheduled = wp_schedule_event($target_timestamp, self::SCHEDULE_KEY, self::HOOK_ANALYZE);
            self::add_log('ensure_schedule_created', $scheduled ? 'success' : 'warning', [
                'target_timestamp' => $target_timestamp,
                'interval_minutes' => $minutes,
            ]);
            return;
        }

        $expected_interval = MINUTE_IN_SECONDS * $minutes;
        $interval_mismatch = isset($event->interval) ? ((int) $event->interval !== (int) $expected_interval) : true;
        if ($event->schedule !== self::SCHEDULE_KEY || $interval_mismatch) {
            wp_clear_scheduled_hook(self::HOOK_ANALYZE);
            $scheduled = wp_schedule_event($target_timestamp, self::SCHEDULE_KEY, self::HOOK_ANALYZE);
            self::add_log('ensure_schedule_resynced', $scheduled ? 'success' : 'warning', [
                'target_timestamp' => $target_timestamp,
                'old_schedule' => (string) $event->schedule,
                'new_schedule' => self::SCHEDULE_KEY,
                'interval_minutes' => $minutes,
            ]);
        } else {
            if ((int) $event->timestamp < time()) {
                wp_schedule_single_event(time() + 5, self::HOOK_ANALYZE);
                self::add_log('schedule_overdue_catchup_scheduled', 'warning', [
                    'overdue_timestamp' => (int) $event->timestamp,
                    'catchup_at' => time() + 5,
                ]);
            }
            $signature = ((int) $event->timestamp) . '|' . (isset($event->interval) ? (int) $event->interval : 0);
            $last_signature = (string) get_option(self::LAST_OK_LOG_OPTION_KEY, '');
            if ($signature !== $last_signature) {
                self::add_log('ensure_schedule_ok', 'info', [
                    'next_run' => (int) $event->timestamp,
                    'interval' => isset($event->interval) ? (int) $event->interval : 0,
                ]);
                update_option(self::LAST_OK_LOG_OPTION_KEY, $signature, false);
            }
        }
    }

    public function reschedule_analysis_event($old_value, $new_value)
    {
        if ((string) $old_value === (string) $new_value) {
            return;
        }

        wp_clear_scheduled_hook(self::HOOK_ANALYZE);
        $minutes = self::get_interval_minutes();
        $last_run = (int) get_option('aica_last_analysis_run', 0);
        $next_run = $last_run > 0 ? ($last_run + (MINUTE_IN_SECONDS * $minutes)) : (time() + (MINUTE_IN_SECONDS * $minutes));
        wp_schedule_event($next_run, self::SCHEDULE_KEY, self::HOOK_ANALYZE);
    }

    public function ensure_custom_rules_schedule_alignment()
    {
        wp_clear_scheduled_hook(self::HOOK_ANALYZE_RULE);
    }

    public function reschedule_custom_rule_events($old_value, $new_value)
    {
        $this->ensure_custom_rules_schedule_alignment();
    }

    public function run_rule_analysis($rule_id) 
    {
        $rule_id = sanitize_key((string) $rule_id);
        if (!$this->analyzer || $rule_id === '') {
            return;
        }

        $rules = (array) get_option('aica_custom_cron_rules', []); 
        $target_rule = null;
        foreach ($rules as $rule) {
            if (sanitize_key((string) ($rule['id'] ?? '')) === $rule_id) {
                $target_rule = $rule;
                break;
            }
        }
        if (!$target_rule || empty($target_rule['enabled'])) { 
            self::add_log('custom_rule_skipped', 'info', ['rule_id' => $rule_id, 'reason' => 'rule_disabled_or_missing']);
            return;
        }

        $target_post_ids = self::resolve_rule_target_post_ids($target_rule);
        $post_type = sanitize_key((string) ($target_rule['manual_post_type'] ?? ($target_rule['post_type'] ?? 'post')));
        if (empty($target_post_ids)) {
            self::add_log('custom_rule_skipped', 'info', ['rule_id' => $rule_id, 'reason' => 'no_target_posts']);
            return;
        }

        $success = 0; 
        $ignored = 0; 
        $failed = 0;
        $min_comments_to_analyze = max(1, (int) ($target_rule['min_comments_to_analyze'] ?? 1));
        $new_comments_threshold = max(1, (int) ($target_rule['new_comments_threshold'] ?? 10));
        foreach ($target_post_ids as $target_post_id) {
            $approved_comments_count = (int) get_comments([
                'post_id' => (int) $target_post_id, 
                'status' => 'approve',
                'type__not_in' => ['pingback', 'trackback'],
                'count' => true,
            ]);

            if ($approved_comments_count < $min_comments_to_analyze) {
                $ignored++;
                continue;
            }

            $last_analyzed_comment_id = (int) get_post_meta((int) $target_post_id, '_aica_last_analyzed_comment_id', true);
            $new_comments_count = $this->count_new_approved_comments((int) $target_post_id, $last_analyzed_comment_id);
            if ($new_comments_count < $new_comments_threshold) {
                $ignored++;
                continue;
            }

            $result = $this->analyzer->analyze_post_comments((int) $target_post_id, false, [
                'incremental_threshold' => $new_comments_threshold,
            ]);
            if (is_wp_error($result)) {
                if ($result->get_error_code() === 'aica_below_min_comments') {
                    $ignored++;
                } else {
                    $failed++;
                }
            } else {
                $success++;
            }
        }

        self::add_log('custom_rule_finished', 'success', [
            'rule_id' => sanitize_key($rule_id),
            'rule_name' => sanitize_text_field((string) ($target_rule['name'] ?? '')),
            'post_type' => $post_type,
            'success' => $success,
            'failed' => $failed,
            'ignored' => $ignored,
            'min_comments_to_analyze' => $min_comments_to_analyze,
            'new_comments_threshold' => $new_comments_threshold,
        ]);
    }

    public function handle_comment_status_transition($new_status, $old_status, $comment)
    {
        if (!$this->analyzer || !$comment || !isset($comment->comment_post_ID)) {
            return;
        }
        $normalized_new_status = sanitize_key((string) $new_status);
        $normalized_old_status = sanitize_key((string) $old_status);
        $is_approved_now = in_array($normalized_new_status, ['approve', 'approved', '1'], true);
        $was_approved_before = in_array($normalized_old_status, ['approve', 'approved', '1'], true);

        if (!$is_approved_now || $was_approved_before) {
            return;
        }

        $post_id = (int) $comment->comment_post_ID;
        $rules = (array) get_option('aica_custom_cron_rules', []);
        if (empty($rules)) {
            return;
        }

        $matched = 0;
        foreach ($rules as $rule) {
            if (empty($rule['enabled'])) {
                continue;
            }
            $target_post_ids = self::resolve_rule_target_post_ids($rule);
            if (!in_array($post_id, array_map('absint', $target_post_ids), true)) {
                continue;
            }
            $rule_id = sanitize_key((string) ($rule['id'] ?? ''));
            if ($rule_id !== '') {
                $matched++;
                $this->run_rule_analysis($rule_id);
            }
        }
    }

    public function handle_comment_post($comment_id, $comment_approved, $commentdata)
    {
        if (!$this->analyzer) {
            return;
        }

        $approved_value = is_scalar($comment_approved) ? sanitize_key((string) $comment_approved) : '';
        $is_approved = in_array($approved_value, ['1', 'approve', 'approved'], true);
        if (!$is_approved) {
            return;
        }

        $comment = get_comment((int) $comment_id);
        if (!$comment) {
            return;
        }

        $this->handle_comment_status_transition('approve', 'hold', $comment);
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

    public static function resolve_rule_target_post_ids($rule)
    {
        $mode = sanitize_key((string) ($rule['selection_mode'] ?? 'post_type_latest'));
        $manual_post_type = sanitize_key((string) ($rule['manual_post_type'] ?? ''));
        $post_id = absint($rule['post_id'] ?? 0);
        $post_ids_raw = sanitize_text_field((string) ($rule['post_ids'] ?? ''));
        $limit = max(1, min(200, absint($rule['manual_limit'] ?? ($rule['max_posts'] ?? 20))));

        if ($mode === 'none') {
            if (!$post_id) {
                return [];
            }
            $post = get_post($post_id);
            if (!$post) {
                return [];
            }
            return [$post_id];
        }

        if ($mode === 'post_ids') {
            $ids = array_filter(array_map('absint', preg_split('/[\s,]+/', $post_ids_raw)));
            return array_values(array_unique($ids));
        }

        $post_types_query = $manual_post_type ? [$manual_post_type] : ['post', 'product'];
        $posts = get_posts([
            'post_type' => $post_types_query,
            'post_status' => 'publish',
            'numberposts' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
            'fields' => 'ids',
        ]);
        return array_map('absint', (array) $posts);
    }

    public function run_scheduled_analysis()
    {
        self::add_log('scheduled_analysis_hook_triggered', 'info', ['now' => time()]);

        if (!$this->analyzer) {
            self::add_log('scheduled_analysis_skipped', 'warning', ['reason' => 'analyzer_missing']);
            return;
        }

        if (!(bool) get_option('aica_cron_enabled', 1)) {
            self::add_log('scheduled_analysis_skipped', 'info', ['reason' => 'cron_disabled']);
            return;
        }

        $rules = (array) get_option('aica_custom_cron_rules', []);
        $enabled_rules = array_values(array_filter($rules, function ($rule) {
            return !empty($rule['enabled']) && !empty($rule['id']);
        }));

        if (empty($enabled_rules)) {
            self::add_log('scheduled_analysis_skipped', 'info', ['reason' => 'no_enabled_rules']);
            return;
        }

        self::add_log('scheduled_analysis_started', 'info', ['rules_count' => count($enabled_rules)]);
        foreach ($enabled_rules as $rule) {
            $this->run_rule_analysis(sanitize_key((string) $rule['id']));
        }
        update_option('aica_last_analysis_run', time());
        self::add_log('scheduled_analysis_finished', 'success', ['rules_count' => count($enabled_rules)]);
    }

    public function check_negative_spike()
    {
        $threshold = (int) get_option('aica_negative_threshold', 5);
        $recent_comments = get_comments([
            'status' => 'approve',
            'date_query' => [
                [
                    'after' => '24 hours ago',
                ],
            ],
            'number' => 200,
        ]);

        $negative_count = 0;
        foreach ($recent_comments as $comment) {
            $meta = get_comment_meta($comment->comment_ID, 'aica_sentiment', true);
            if ($meta === 'negative') {
                $negative_count++;
            }
        }

        if ($negative_count >= $threshold) {
            $admin_email = sanitize_email(get_option('aica_alert_email', get_option('admin_email')));
            if (empty($admin_email)) {
                return;
            }
            wp_mail(
                $admin_email,
                'هشدار افزایش نظرات منفی',
                sprintf('در 24 ساعت گذشته %d نظر منفی ثبت شده است.', $negative_count)
            );
            self::add_log('negative_alert_sent', 'info', ['negative_count' => $negative_count, 'email' => $admin_email]);
        } else {
            self::add_log('negative_alert_checked', 'info', ['negative_count' => $negative_count, 'threshold' => $threshold]);
        }
    }
}