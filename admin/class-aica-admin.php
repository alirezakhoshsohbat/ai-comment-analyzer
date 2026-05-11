<?php

if (!defined('ABSPATH')) {
    exit;
}

class AICA_Admin
{
    private $analyzer;

    public function __construct($analyzer)
    {
        $this->analyzer = $analyzer;
    }

    public function register_hooks()
    {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_post_aica_run_analysis', [$this, 'handle_manual_analysis']);
        add_action('admin_post_aica_quick_flush_cache', [$this, 'handle_quick_flush_cache']);
        add_action('admin_post_aica_quick_run_cron', [$this, 'handle_quick_run_cron']);
        add_action('admin_post_aica_force_run_custom_cron_rule', [$this, 'handle_force_run_custom_cron_rule']);
        add_action('admin_post_aica_save_custom_cron_rule', [$this, 'handle_save_custom_cron_rule']);
        add_action('admin_post_aica_delete_custom_cron_rule', [$this, 'handle_delete_custom_cron_rule']);
        add_action('admin_post_aica_save_custom_css', [$this, 'handle_save_custom_css']);
        add_action('wp_ajax_aica_comment_count', [$this, 'ajax_comment_count']);
        add_action('wp_ajax_aica_test_api_connection', [$this, 'ajax_test_api_connection']);
        add_action('wp_ajax_aica_prepare_manual_analysis', [$this, 'ajax_prepare_manual_analysis']);
        add_action('wp_ajax_aica_process_manual_analysis_item', [$this, 'ajax_process_manual_analysis_item']);
        add_action('add_meta_boxes_comment', [$this, 'register_comment_metabox']);
        add_action('wp_ajax_aica_suggest_reply', [$this, 'ajax_suggest_reply']);
        add_filter('pre_comment_approved', [$this, 'smart_spam_gate'], 10, 2);
        add_action('comment_post', [$this, 'enrich_comment_meta'], 20, 3);
    }

    public function register_menu()
    {
        add_menu_page(
            'تنظیمات تحلیل‌گر نظرات',
            'تحلیل‌گر نظرات AI',
            'manage_options',
            'aica-settings',
            [$this, 'render_settings_page'],
            'dashicons-format-chat',
            58
        );
    }

    public function register_settings()
    {
        register_setting('aica_settings_group', 'aica_api_key', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('aica_settings_group', 'aica_model', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('aica_settings_group', 'aica_api_base_url', ['sanitize_callback' => [$this, 'sanitize_api_base_url']]);
        register_setting('aica_settings_group', 'aica_analysis_interval_time', ['sanitize_callback' => [$this, 'sanitize_analysis_interval_time']]);
        register_setting('aica_settings_group', 'aica_cache_hours', ['sanitize_callback' => 'absint']);
        register_setting('aica_settings_group', 'aica_max_comments_per_post', ['sanitize_callback' => [$this, 'sanitize_positive_int']]);
        register_setting('aica_settings_group', 'aica_min_comments_to_analyze', ['sanitize_callback' => [$this, 'sanitize_min_comments_to_analyze']]);
        register_setting('aica_settings_group', 'aica_chunk_size', ['sanitize_callback' => [$this, 'sanitize_chunk_size']]);
        register_setting('aica_settings_group', 'aica_incremental_new_comments_threshold', ['sanitize_callback' => [$this, 'sanitize_incremental_threshold']]);
        register_setting('aica_settings_group', 'aica_analysis_tone', ['sanitize_callback' => [$this, 'sanitize_analysis_tone']]);
        register_setting('aica_settings_group', 'aica_analysis_detail_level', ['sanitize_callback' => [$this, 'sanitize_analysis_detail_level']]);
        register_setting('aica_settings_group', 'aica_cron_enabled', ['sanitize_callback' => 'absint']);
        register_setting('aica_settings_group', 'aica_cron_post_types', ['sanitize_callback' => [$this, 'sanitize_post_types']]);
        register_setting('aica_settings_group', 'aica_cron_max_posts', ['sanitize_callback' => [$this, 'sanitize_positive_int']]);
        register_setting('aica_settings_group', 'aica_negative_threshold', ['sanitize_callback' => [$this, 'sanitize_positive_int']]);
        register_setting('aica_settings_group', 'aica_alert_email', ['sanitize_callback' => 'sanitize_email']);
        register_setting('aica_settings_group', 'aica_frontend_enabled', ['sanitize_callback' => 'absint']);
        register_setting('aica_settings_group', 'aica_frontend_position', ['sanitize_callback' => [$this, 'sanitize_frontend_position']]);
        register_setting('aica_settings_group', 'aica_show_topics', ['sanitize_callback' => 'absint']);
        register_setting('aica_settings_group', 'aica_show_topic_summaries', ['sanitize_callback' => 'absint']);
        register_setting('aica_settings_group', 'aica_show_faq', ['sanitize_callback' => 'absint']);
        register_setting('aica_settings_group', 'aica_show_sentiment_filter', ['sanitize_callback' => 'absint']);

        // Topic pill styling options
        register_setting('aica_settings_group', 'aica_topic_pill_bg_color', ['sanitize_callback' => [$this, 'sanitize_hex_color_fallback']]);
        register_setting('aica_settings_group', 'aica_topic_pill_text_color', ['sanitize_callback' => [$this, 'sanitize_hex_color_fallback']]);
        register_setting('aica_settings_group', 'aica_topic_pill_border_color', ['sanitize_callback' => [$this, 'sanitize_hex_color_fallback']]);
        register_setting('aica_settings_group', 'aica_topic_pill_border_width', ['sanitize_callback' => [$this, 'sanitize_border_width']]);
        register_setting('aica_settings_group', 'aica_topic_pill_border_radius', ['sanitize_callback' => [$this, 'sanitize_box_radius']]);

        // FAQ accordion styling options
        register_setting('aica_settings_group', 'aica_faq_bg_color', ['sanitize_callback' => [$this, 'sanitize_hex_color_fallback']]);
        register_setting('aica_settings_group', 'aica_faq_border_color', ['sanitize_callback' => [$this, 'sanitize_hex_color_fallback']]);
        register_setting('aica_settings_group', 'aica_faq_border_width', ['sanitize_callback' => [$this, 'sanitize_border_width']]);
        register_setting('aica_settings_group', 'aica_faq_border_radius', ['sanitize_callback' => [$this, 'sanitize_box_radius']]);
        register_setting('aica_settings_group', 'aica_faq_padding', ['sanitize_callback' => [$this, 'sanitize_box_padding']]);

        // Topics panel styling options
        register_setting('aica_settings_group', 'aica_topics_panel_bg_color', ['sanitize_callback' => [$this, 'sanitize_hex_color_fallback']]);
        register_setting('aica_settings_group', 'aica_topics_panel_border_color', ['sanitize_callback' => [$this, 'sanitize_hex_color_fallback']]);
        register_setting('aica_settings_group', 'aica_topics_panel_border_width', ['sanitize_callback' => [$this, 'sanitize_border_width']]);
        register_setting('aica_settings_group', 'aica_topics_panel_border_radius', ['sanitize_callback' => [$this, 'sanitize_box_radius']]);
        register_setting('aica_settings_group', 'aica_topics_panel_padding', ['sanitize_callback' => [$this, 'sanitize_box_padding']]);

        // Topic card styling options
        register_setting('aica_settings_group', 'aica_topic_card_bg_color', ['sanitize_callback' => [$this, 'sanitize_hex_color_fallback']]);
        register_setting('aica_settings_group', 'aica_topic_card_text_color', ['sanitize_callback' => [$this, 'sanitize_hex_color_fallback']]);
        register_setting('aica_settings_group', 'aica_topic_card_bg_color', ['sanitize_callback' => [$this, 'sanitize_hex_color_fallback']]);
        register_setting('aica_settings_group', 'aica_topic_card_text_color', ['sanitize_callback' => [$this, 'sanitize_hex_color_fallback']]);
        register_setting('aica_settings_group', 'aica_topic_card_border_color', ['sanitize_callback' => [$this, 'sanitize_hex_color_fallback']]);
        register_setting('aica_settings_group', 'aica_topic_card_border_width', ['sanitize_callback' => [$this, 'sanitize_border_width']]);
        register_setting('aica_settings_group', 'aica_topic_card_border_radius', ['sanitize_callback' => [$this, 'sanitize_box_radius']]);
        register_setting('aica_settings_group', 'aica_topic_card_padding', ['sanitize_callback' => [$this, 'sanitize_box_padding']]);
        register_setting('aica_settings_group', 'aica_box_title', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('aica_settings_group', 'aica_box_subtitle', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('aica_settings_group', 'aica_theme', ['sanitize_callback' => [$this, 'sanitize_theme']]);
        register_setting('aica_settings_group', 'aica_enable_toggle_button', ['sanitize_callback' => 'absint']);
        register_setting('aica_settings_group', 'aica_button_text', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('aica_settings_group', 'aica_button_style', ['sanitize_callback' => [$this, 'sanitize_button_style']]);
        register_setting('aica_settings_group', 'aica_button_align', ['sanitize_callback' => [$this, 'sanitize_button_align']]);
        register_setting('aica_settings_group', 'aica_button_icon', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('aica_settings_group', 'aica_button_bg_color', ['sanitize_callback' => [$this, 'sanitize_hex_color_fallback']]);
        register_setting('aica_settings_group', 'aica_button_text_color', ['sanitize_callback' => [$this, 'sanitize_hex_color_fallback']]);
        register_setting('aica_settings_group', 'aica_button_radius', ['sanitize_callback' => [$this, 'sanitize_button_radius']]);
        register_setting('aica_settings_group', 'aica_button_disable_after_click', ['sanitize_callback' => 'absint']);
        register_setting('aica_settings_group', 'aica_button_disabled_text', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('aica_settings_group', 'aica_accent_color', ['sanitize_callback' => [$this, 'sanitize_hex_color_fallback']]);
        register_setting('aica_settings_group', 'aica_glass_effect', ['sanitize_callback' => 'absint']);
        register_setting('aica_settings_group', 'aica_show_header_icon', ['sanitize_callback' => 'absint']);
        register_setting('aica_settings_group', 'aica_show_summary', ['sanitize_callback' => 'absint']);
        register_setting('aica_settings_group', 'aica_show_short_summary', ['sanitize_callback' => 'absint']);
        register_setting('aica_settings_group', 'aica_show_positive_points', ['sanitize_callback' => 'absint']);
        register_setting('aica_settings_group', 'aica_show_negative_points', ['sanitize_callback' => 'absint']);
        register_setting('aica_settings_group', 'aica_points_limit', ['sanitize_callback' => [$this, 'sanitize_points_limit']]);
        register_setting('aica_settings_group', 'aica_point_style', ['sanitize_callback' => [$this, 'sanitize_point_style']]);
        register_setting('aica_settings_group', 'aica_point_icon_positive', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('aica_settings_group', 'aica_point_icon_negative', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('aica_settings_group', 'aica_point_bg_positive', ['sanitize_callback' => [$this, 'sanitize_hex_color_fallback']]);
        register_setting('aica_settings_group', 'aica_point_bg_negative', ['sanitize_callback' => [$this, 'sanitize_hex_color_fallback']]);
        register_setting('aica_settings_group', 'aica_point_text_color', ['sanitize_callback' => [$this, 'sanitize_hex_color_fallback']]);
        register_setting('aica_settings_group', 'aica_box_max_width', ['sanitize_callback' => [$this, 'sanitize_box_max_width']]);
        register_setting('aica_settings_group', 'aica_box_title_size', ['sanitize_callback' => [$this, 'sanitize_title_size']]);
        register_setting('aica_settings_group', 'aica_box_font_size', ['sanitize_callback' => [$this, 'sanitize_font_size']]);
        register_setting('aica_settings_group', 'aica_box_line_height', ['sanitize_callback' => [$this, 'sanitize_line_height']]);
        register_setting('aica_settings_group', 'aica_box_shadow', ['sanitize_callback' => 'absint']);
        register_setting('aica_settings_group', 'aica_box_border_width', ['sanitize_callback' => [$this, 'sanitize_border_width']]);
        register_setting('aica_settings_group', 'aica_box_border_color', ['sanitize_callback' => [$this, 'sanitize_hex_color_fallback']]);
        register_setting('aica_settings_group', 'aica_box_border_radius', ['sanitize_callback' => [$this, 'sanitize_box_radius']]);
        register_setting('aica_settings_group', 'aica_box_padding', ['sanitize_callback' => [$this, 'sanitize_box_padding']]);
        register_setting('aica_settings_group', 'aica_custom_css_enabled', ['sanitize_callback' => 'absint']);
        register_setting('aica_settings_group', 'aica_custom_css', ['sanitize_callback' => [$this, 'sanitize_custom_css']]);
        register_setting('aica_settings_group', 'aica_custom_js_enabled', ['sanitize_callback' => 'absint']);
        register_setting('aica_settings_group', 'aica_custom_js', ['sanitize_callback' => [$this, 'sanitize_custom_js']]);
        register_setting('aica_settings_group', 'aica_enable_summary', ['sanitize_callback' => 'absint']);
        register_setting('aica_settings_group', 'aica_enable_sentiment', ['sanitize_callback' => 'absint']);
        register_setting('aica_settings_group', 'aica_enable_topics', ['sanitize_callback' => 'absint']);
        register_setting('aica_settings_group', 'aica_enable_spam_detection', ['sanitize_callback' => 'absint']);
    }

    public function sanitize_positive_int($value)
    {
        return max(1, absint($value));
    }

    public function sanitize_chunk_size($value)
    {
        return max(10, min(200, absint($value)));
    }

    public function sanitize_incremental_threshold($value)
    {
        return max(1, min(200, absint($value)));
    }

    public function sanitize_analysis_interval_time($value)
    {
        $value = trim((string) $value);
        if (!preg_match('/^(\d{1,2}):([0-5]\d)$/', $value, $matches)) {
            return '06:00';
        }

        $hours = max(0, min(23, (int) $matches[1]));
        $minutes = (int) $matches[2];
        if ($hours === 0 && $minutes === 0) {
            return '00:01';
        }

        return sprintf('%02d:%02d', $hours, $minutes);
    }

    public function sanitize_min_comments_to_analyze($value)
    {
        return max(1, absint($value));
    }

    public function sanitize_post_types($value)
    {
        if (is_array($value)) {
            $types = $value;
        } else {
            $types = explode(',', (string) $value);
        }

        $types = array_filter(array_map('sanitize_key', $types));
        return array_values(array_unique($types));
    }

    public function sanitize_frontend_position($value)
    {
        $allowed = ['before', 'after', 'shortcode'];
        return in_array($value, $allowed, true) ? $value : 'after';
    }

    public function sanitize_theme($value)
    {
        $allowed = ['modern-dark', 'modern-light', 'minimal', 'neon', 'aurora', 'midnight-pro', 'sunset-pro', 'frost-pro'];
        return in_array($value, $allowed, true) ? $value : 'modern-dark';
    }
    public function sanitize_button_style($value)
    {
        $allowed = ['solid', 'glass', 'outline'];
        return in_array($value, $allowed, true) ? $value : 'solid';
    }
    public function sanitize_button_radius($value)
    {
        return max(8, min(40, absint($value)));
    }
    public function sanitize_button_align($value)
    {
        $allowed = ['right', 'left', 'center'];
        return in_array($value, $allowed, true) ? $value : 'right';
    }

    public function sanitize_hex_color_fallback($value)
    {
        $color = sanitize_hex_color($value);
        return $color ?: '#4f46e5';
    }

    public function sanitize_points_limit($value)
    {
        return max(2, min(20, absint($value)));
    }

    public function sanitize_point_style($value)
    {
        $allowed = ['card', 'button'];
        return in_array($value, $allowed, true) ? $value : 'card';
    }

    public function sanitize_box_radius($value)
    {
        return max(0, min(40, absint($value)));
    }

    public function sanitize_box_padding($value)
    {
        return max(8, min(48, absint($value)));
    }

    public function sanitize_box_max_width($value)
    {
        return max(320, min(1400, absint($value)));
    }

    public function sanitize_title_size($value)
    {
        return max(14, min(36, absint($value)));
    }

    public function sanitize_font_size($value)
    {
        return max(12, min(22, absint($value)));
    }

    public function sanitize_line_height($value)
    {
        $value = (float) $value;
        return max(1.2, min(2.2, $value));
    }

    public function sanitize_border_width($value)
    {
        return max(0, min(4, absint($value)));
    }

    public function sanitize_custom_css($value)
    {
        $value = (string) $value;
        $value = str_replace(['<?', '?>'], '', $value);
        return trim($value);
    }

    public function sanitize_custom_js($value)
    {
        $value = (string) $value;
        $value = str_replace(['<script', '</script>', '<?', '?>'], '', $value);
        return trim($value);
    }

    public function sanitize_analysis_tone($value)
    {
        $allowed = ['neutral', 'formal', 'friendly', 'professional', 'minimal', 'critical', 'persuasive', 'technical', 'confident'];
        return in_array($value, $allowed, true) ? $value : 'neutral';
    }

    public function sanitize_analysis_detail_level($value)
    {
        $allowed = ['short', 'normal', 'detailed'];
        return in_array($value, $allowed, true) ? $value : 'normal';
    }

    public function enqueue_admin_assets($hook)
    {
        $allowed_hooks = ['settings_page_aica-settings', 'toplevel_page_aica-settings'];
        if (!in_array($hook, $allowed_hooks, true)) {
            return;
        }

        if (function_exists('wp_enqueue_code_editor')) {
            $css_settings = wp_enqueue_code_editor(['type' => 'text/css']);
            $js_settings = wp_enqueue_code_editor(['type' => 'text/javascript']);
            if (!empty($css_settings) || !empty($js_settings)) {
                wp_enqueue_script('wp-theme-plugin-editor');
                $inline = 'jQuery(function(){';
                $inline .= 'if(window.wp&&wp.codeEditor&&document.getElementById("aica-custom-css-editor")){wp.codeEditor.initialize("aica-custom-css-editor",' . wp_json_encode($css_settings) . ');}';
                $inline .= 'if(window.wp&&wp.codeEditor&&document.getElementById("aica-custom-js-editor")){wp.codeEditor.initialize("aica-custom-js-editor",' . wp_json_encode($js_settings) . ');}';
                $inline .= '});';
                wp_add_inline_script(
                    'wp-theme-plugin-editor',
                    $inline
                );
            }
        }
    }


    public function sanitize_api_base_url($value)
    {
        $value = trim((string) $value);

        if (empty($value)) {
            return 'https://api.openai.com/v1/chat/completions';
        }

        if ($value[0] === '/') {
            $value = 'https://api.openai.com' . $value;
        } elseif (!preg_match('#^https?://#i', $value)) {
            $value = 'https://' . $value;
        }

        $value = untrailingslashit($value);
        if (preg_match('#/v1$#i', $value)) {
            $value .= '/chat/completions';
        }

        $value = esc_url_raw($value);

        return !empty($value) ? $value : 'https://api.openai.com/v1/chat/completions';
    }
    public function render_settings_page()
    {
        $analysis_status = sanitize_text_field($_GET['analysis'] ?? '');
        $analysis_post_id = absint($_GET['post_id'] ?? 0);
        $analysis_comments = absint($_GET['comments'] ?? 0);
        $analysis_message = sanitize_text_field(urldecode((string) ($_GET['message'] ?? '')));
        $analysis_data = $analysis_post_id ? AICA_Database::get_analysis($analysis_post_id) : null;
        $post_types = get_post_types(['public' => true], 'objects');
        $selected_cron_post_types = (array) get_option('aica_cron_post_types', ['post', 'product']);
        $stats = $this->get_dashboard_stats();
        $health = $this->get_system_health();
        $analysis_filters = $this->get_analysis_filters_from_request();
        $recent_analyses_data = $this->get_recent_analyses_paginated($analysis_filters);
        $recent_analyses = $recent_analyses_data['items'];
        $recent_pagination = $recent_analyses_data['pagination'];
        $active_tab = sanitize_key($_GET['tab'] ?? 'overview');
        $allowed_tabs = ['overview', 'analysis', 'progress', 'custom-cron', 'cron-report', 'display', 'advanced'];
        if (!in_array($active_tab, $allowed_tabs, true)) {
            $active_tab = 'overview';
        }
        ?>
        <div class="wrap aica-admin-wrap">
            <div class="aica-hero">
                <div>
                    <h1>داشبورد تحلیل‌گر هوشمند نظرات</h1>
                    <p>مرکز کنترل تحلیل، نمایش و اتوماسیون نظرات کاربران</p>
                </div>
                <div class="aica-hero-badges">
                    <span>نسخه UI Pro</span>
                    <span><?php echo esc_html('آخرین اجرا: ' . $stats['last_run']); ?></span>
                </div>
            </div>
            <div class="aica-layout">
                <aside class="aica-sidebar">
                    <h3>منوی مدیریت</h3>
                    <nav class="aica-tabs">
                        <a class="aica-tab <?php echo $active_tab === 'overview' ? 'is-active' : ''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=aica-settings&tab=overview')); ?>">نمای کلی</a>
                        <a class="aica-tab <?php echo $active_tab === 'analysis' ? 'is-active' : ''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=aica-settings&tab=analysis')); ?>">تحلیل و API</a>
                        <a class="aica-tab <?php echo $active_tab === 'progress' ? 'is-active' : ''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=aica-settings&tab=progress')); ?>">پیشرفت تحلیل</a>
                        <a class="aica-tab <?php echo $active_tab === 'custom-cron' ? 'is-active' : ''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=aica-settings&tab=custom-cron')); ?>">کران اختصاصی</a>
                        <a class="aica-tab <?php echo $active_tab === 'cron-report' ? 'is-active' : ''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=aica-settings&tab=cron-report')); ?>">گزارش اجرای کران</a>
                        <a class="aica-tab <?php echo $active_tab === 'display' ? 'is-active' : ''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=aica-settings&tab=display')); ?>">نمایش و دیزاین</a>
                        <a class="aica-tab <?php echo $active_tab === 'advanced' ? 'is-active' : ''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=aica-settings&tab=advanced')); ?>">پیشرفته</a>
                    </nav>
                    <div class="aica-sidebar-meta">
                        <div><span>API</span><strong><?php echo $health['api_key_ok'] ? 'متصل' : 'تنظیم نشده'; ?></strong></div>
                        <div><span>Cron</span><strong><?php echo $health['cron_ok'] ? 'فعال' : 'غیرفعال'; ?></strong></div>
                    </div>
                </aside>
                <main class="aica-main">
            <?php if ($analysis_status === 'done') : ?>
                <div class="notice notice-success is-dismissible">
                    <p>
                        تحلیل پست با موفقیت انجام شد.
                        <?php if ($analysis_comments > 0) : ?>
                            <?php echo esc_html('تعداد نظرات تحلیل‌شده: ' . $analysis_comments); ?>
                        <?php endif; ?>
                        <?php if (!empty($analysis_message)) : ?>
                            <?php echo ' - ' . esc_html($analysis_message); ?>
                        <?php endif; ?>
                    </p>
                </div>
            <?php elseif ($analysis_status === 'error') : ?>
                <div class="notice notice-error is-dismissible">
                    <p><?php echo esc_html($analysis_message ?: 'تحلیل انجام نشد. لطفا دوباره تلاش کنید.'); ?></p>
                </div>
            <?php endif; ?>
            <?php if (!empty($_GET['settings-updated'])) : ?>
                <div class="notice notice-success is-dismissible">
                    <p>تنظیمات با موفقیت ذخیره شد.</p>
                </div>
            <?php endif; ?>
            <div class="aica-stats-grid aica-tab-pane <?php echo $active_tab === 'overview' ? 'is-active' : ''; ?>" data-tab="overview">
                <div class="aica-stat-card"><span>پست‌های تحلیل‌شده</span><strong><?php echo esc_html($stats['analyzed_posts']); ?></strong></div>
                <div class="aica-stat-card"><span>نظرات تاییدشده</span><strong><?php echo esc_html($stats['approved_comments']); ?></strong></div>
                <div class="aica-stat-card"><span>آخرین تحلیل خودکار</span><strong><?php echo esc_html($stats['last_run']); ?></strong></div>
                <div class="aica-stat-card"><span>اجرای بعدی کران</span><strong><?php echo esc_html($stats['next_run']); ?></strong></div>
                <div class="aica-stat-card"><span>پست‌های نیازمند تحلیل</span><strong><?php echo esc_html($stats['stale_posts']); ?></strong></div>
                <div class="aica-stat-card"><span>میانگین کامنت/پست تحلیل‌شده</span><strong><?php echo esc_html($stats['avg_comments_per_analyzed_post']); ?></strong></div>
            </div>

            <div class="aica-admin-grid aica-tab-pane <?php echo $active_tab === 'overview' ? 'is-active' : ''; ?>" data-tab="overview">
                <div class="aica-card">
                    <h2>تحلیل دستی</h2>
                    <p class="aica-card-sub">تحلیل سریع یک پست با شناسه دلخواه</p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="aica-manual-analysis-form" class="aica-manual-card">
                        <?php wp_nonce_field('aica_manual_analysis_nonce', 'aica_nonce'); ?>
                        <input type="hidden" name="action" value="aica_run_analysis">
                        <div class="aica-mf-grid">
                        <p class="aica-field" id="aica-mode-wrap">
                            <label>روش انتخاب (پیش‌فرض: هیچ‌کدام)</label><br>
                            <select name="manual_filter_mode" id="aica-manual-filter-mode">
                                <option value="none">هیچ‌کدام (تحلیل یک شناسه پست)</option>
                                <option value="post_ids">چند شناسه پست/محصول</option>
                                <option value="post_type_latest">آخرین موارد یک نوع پست</option>
                            </select>
                            <span class="description">فقط یک روش انتخاب را فعال کنید؛ همزمان از چند فیلتر استفاده نکنید.</span>
                        </p>
                        <p class="aica-field" id="aica-post-type-wrap" style="display:none;">
                            <label>نوع پست (برای حالت «آخرین موارد»)</label><br>
                            <select name="manual_post_type" id="aica-manual-post-type">
                                <option value="">همه</option>
                                <?php foreach ($post_types as $post_type) : ?>
                                    <option value="<?php echo esc_attr($post_type->name); ?>"><?php echo esc_html($post_type->labels->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                        <p class="aica-field" id="aica-post-ids-wrap" style="display:none;">
                            <label>شناسه‌ها (چندتایی)</label><br>
                            <textarea name="manual_post_ids" id="aica-manual-post-ids" rows="3" placeholder="مثال: 12,45,78"></textarea>
                            <span class="description">شناسه‌ها را با کاما جدا کنید.</span>
                        </p>
                        <p class="aica-field" id="aica-post-type-limit-wrap" style="display:none;">
                            <label>تعداد آخرین موارد</label><br>
                            <input type="number" name="manual_limit" id="aica-manual-limit" min="1" max="200" value="10">
                        </p>
                        <p class="aica-field" id="aica-single-post-id-wrap">
                            <label>شناسه پست</label><br>
                            <input type="number" name="post_id" id="aica-post-id" placeholder="شناسه پست" required>
                        </p>
                        </div>
                        <div class="aica-mf-actions">
                        <?php submit_button('شروع تحلیل', 'primary', 'submit', false, ['id' => 'aica-run-analysis-btn']); ?>
                        </div>
                        <p id="aica-analysis-status" class="description"></p>
                    </form>
                </div>

                <div class="aica-card">
                    <h2>سلامت سیستم</h2>
                    <p class="aica-card-sub">بررسی وضعیت اجزای حیاتی افزونه</p>
                    <ul class="aica-health-list">
                        <li><span>API Key</span><strong class="<?php echo $health['api_key_ok'] ? 'ok' : 'bad'; ?>"><?php echo $health['api_key_ok'] ? 'متصل' : 'تنظیم نشده'; ?></strong></li>
                        <li><span>کران خودکار</span><strong class="<?php echo $health['cron_ok'] ? 'ok' : 'bad'; ?>"><?php echo $health['cron_ok'] ? 'فعال' : 'غیرفعال'; ?></strong></li>
                        <li><span>نمایش فرانت</span><strong class="<?php echo $health['frontend_ok'] ? 'ok' : 'bad'; ?>"><?php echo $health['frontend_ok'] ? 'فعال' : 'غیرفعال'; ?></strong></li>
                        <li><span>هشدار ایمیل</span><strong class="<?php echo $health['email_ok'] ? 'ok' : 'bad'; ?>"><?php echo $health['email_ok'] ? 'معتبر' : 'نامعتبر'; ?></strong></li>
                    </ul>
                </div>

            </div>

            <div class="aica-card aica-tab-pane <?php echo $active_tab === 'overview' ? 'is-active' : ''; ?>" data-tab="overview">
                <h2>آخرین تحلیل‌ها</h2>
                <p class="aica-card-sub">لیست قابل فیلتر و صفحه‌بندی تحلیل‌های ذخیره‌شده</p>
                <form method="get" class="aica-analysis-filters">
                    <input type="hidden" name="page" value="aica-settings">
                    <input type="hidden" name="tab" value="overview">
                    <input type="number" name="analysis_post_id" placeholder="شناسه پست" value="<?php echo esc_attr($analysis_filters['post_id'] ?: ''); ?>">
                    <input type="text" name="analysis_q" placeholder="جستجو در خلاصه" value="<?php echo esc_attr($analysis_filters['q']); ?>">
                    <input type="date" name="analysis_date_from" value="<?php echo esc_attr($analysis_filters['date_from']); ?>">
                    <input type="date" name="analysis_date_to" value="<?php echo esc_attr($analysis_filters['date_to']); ?>">
                    <button type="submit" class="button">اعمال فیلتر</button>
                    <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=aica-settings&tab=overview')); ?>">پاکسازی</a>
                </form>
                <?php if (!empty($recent_analyses)) : ?>
                    <div class="aica-analysis-list">
                        <?php foreach ($recent_analyses as $row) : ?>
                            <details class="aica-analysis-item">
                                <summary>
                                    <span>#<?php echo esc_html($row['post_id']); ?></span>
                                    <span><?php echo esc_html($row['short_summary']); ?></span>
                                    <span><?php echo esc_html($row['updated_at']); ?></span>
                                </summary>
                                <div class="aica-analysis-detail">
                                    <p><strong>خلاصه کامل:</strong> <?php echo esc_html($row['summary']); ?></p>
                                    <p><strong>احساسات:</strong> <?php echo esc_html('مثبت ' . $row['sentiment_positive'] . '% | خنثی ' . $row['sentiment_neutral'] . '% | منفی ' . $row['sentiment_negative'] . '%'); ?></p>
                                    <p><strong>موضوعات:</strong>
                                        <?php if (!empty($row['topics'])) : ?>
                                            <?php foreach ($row['topics'] as $topic) : ?>
                                                <span class="aica-pill"><?php echo esc_html($topic); ?></span>
                                            <?php endforeach; ?>
                                        <?php else : ?>
                                            <span>ندارد</span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </details>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($recent_pagination['total_pages'] > 1) : ?>
                        <div class="aica-pagination">
                            <?php for ($i = 1; $i <= $recent_pagination['total_pages']; $i++) : ?>
                                <?php
                                $link = add_query_arg([
                                    'page' => 'aica-settings',
                                    'tab' => 'overview',
                                    'analysis_page' => $i,
                                    'analysis_post_id' => $analysis_filters['post_id'] ?: null,
                                    'analysis_q' => $analysis_filters['q'] ?: null,
                                    'analysis_date_from' => $analysis_filters['date_from'] ?: null,
                                    'analysis_date_to' => $analysis_filters['date_to'] ?: null,
                                ], admin_url('admin.php'));
                                ?>
                                <a class="aica-page-btn <?php echo $i === $recent_pagination['current_page'] ? 'is-active' : ''; ?>" href="<?php echo esc_url($link); ?>"><?php echo esc_html($i); ?></a>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                <?php else : ?>
                    <p>هنوز تحلیلی ثبت نشده است.</p>
                <?php endif; ?>
            </div>

            <div class="aica-card aica-tab-pane aica-tab-pane-card <?php echo $active_tab === 'progress' ? 'is-active' : ''; ?>" data-tab="progress">
                <h2>پیشرفت تحلیل دستی</h2>
                <p class="aica-card-sub">نمایش زنده وضعیت هر پست در فرآیند تحلیل</p>
                <div id="aica-progress-empty" class="description">برای شروع، از تب «نمای کلی» روی «شروع تحلیل» بزنید.</div>
                <div id="aica-progress-wrap" style="display:none;">
                    <div style="margin:10px 0;">
                        <div style="height:14px;background:#e2e8f0;border-radius:999px;overflow:hidden;">
                            <div id="aica-progress-bar" style="width:0%;height:100%;background:#2563eb;transition:width .2s ease;"></div>
                        </div>
                        <p id="aica-progress-summary" class="description" style="margin-top:8px;"></p>
                    </div>
                    <div class="aica-progress-table-wrap">
                        <table class="widefat striped aica-progress-table">
                            <thead>
                                <tr>
                                    <th>پست</th>
                                    <th>عنوان</th>
                                    <th>تعداد کامنت</th>
                                    <th>وضعیت</th>
                                    <th>پیام</th>
                                </tr>
                            </thead>
                            <tbody id="aica-progress-rows"></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <form method="post" action="options.php" class="aica-settings-form aica-tab-<?php echo esc_attr($active_tab); ?>">
                <?php settings_fields('aica_settings_group'); ?>
                <div class="aica-settings-grid">
                    <div class="aica-card" data-tab-card="analysis">
                        <h2>تنظیمات API</h2>
                        <p><label>کلید API<br><input type="password" name="aica_api_key" value="<?php echo esc_attr(get_option('aica_api_key')); ?>" class="regular-text" /></label></p>
                        <p><label>مدل<br><input type="text" name="aica_model" value="<?php echo esc_attr(get_option('aica_model', 'gpt-4o-mini')); ?>" class="regular-text" /></label></p>
                        <p><label>Base URL<br><input type="url" name="aica_api_base_url" value="<?php echo esc_attr(get_option('aica_api_base_url', 'https://api.openai.com/v1/chat/completions')); ?>" class="regular-text" /></label></p>
                        <p>
                            <button type="button" class="button" id="aica-test-api-btn">تست اتصال API</button>
                            <span id="aica-test-api-result" class="description" style="display:block;margin-top:8px;"></span>
                        </p>
                    </div>

                    <div class="aica-card" data-tab-card="analysis">
                        <h2>موتور تحلیل</h2>
                        <p class="aica-card-sub">انتخاب حالت اجرا: فقط دستی یا زمان‌بندی‌شده</p>
                        <p><label><input type="checkbox" name="aica_cron_enabled" value="1" <?php checked(1, get_option('aica_cron_enabled', 1)); ?> /> فعال بودن تحلیل زمان‌بندی‌شده (Cron)</label></p>
                        <p><label>فاصله تحلیل (HH:MM)<br><input type="time" name="aica_analysis_interval_time" value="<?php echo esc_attr(get_option('aica_analysis_interval_time', '06:00')); ?>" step="60" /></label></p>
                        <p><label>مدت کش (ساعت)<br><input type="number" name="aica_cache_hours" value="<?php echo esc_attr(get_option('aica_cache_hours', 6)); ?>" min="1" /></label></p>
                        <p><label>حداقل کامنت برای شروع تحلیل<br><input type="number" name="aica_min_comments_to_analyze" value="<?php echo esc_attr(get_option('aica_min_comments_to_analyze', 1)); ?>" min="1" /></label></p>
                        <p><label>حداکثر کامنت در هر تحلیل<br><input type="number" name="aica_max_comments_per_post" value="<?php echo esc_attr(get_option('aica_max_comments_per_post', 1000)); ?>" min="1" /></label></p>
                        <p><label>اندازه هر دسته API<br><input type="number" name="aica_chunk_size" value="<?php echo esc_attr(get_option('aica_chunk_size', 50)); ?>" min="10" max="200" /></label></p>
                        <p><label>آستانه تحلیل افزایشی (تعداد کامنت جدید)<br><input type="number" name="aica_incremental_new_comments_threshold" value="<?php echo esc_attr(get_option('aica_incremental_new_comments_threshold', 10)); ?>" min="1" max="200" /></label></p>
                        <p><label>لحن تحلیل<br>
                            <select name="aica_analysis_tone">
                                <option value="neutral" <?php selected(get_option('aica_analysis_tone', 'neutral'), 'neutral'); ?>>خنثی</option>
                                <option value="formal" <?php selected(get_option('aica_analysis_tone', 'neutral'), 'formal'); ?>>رسمی</option>
                                <option value="friendly" <?php selected(get_option('aica_analysis_tone', 'neutral'), 'friendly'); ?>>دوستانه</option>
                                <option value="professional" <?php selected(get_option('aica_analysis_tone', 'neutral'), 'professional'); ?>>حرفه‌ای</option>
                                <option value="minimal" <?php selected(get_option('aica_analysis_tone', 'neutral'), 'minimal'); ?>>مینیمال</option>
                                <option value="critical" <?php selected(get_option('aica_analysis_tone', 'neutral'), 'critical'); ?>>نقادانه</option>
                                <option value="persuasive" <?php selected(get_option('aica_analysis_tone', 'neutral'), 'persuasive'); ?>>ترغیب‌کننده</option>
                                <option value="technical" <?php selected(get_option('aica_analysis_tone', 'neutral'), 'technical'); ?>>فنی</option>
                                <option value="confident" <?php selected(get_option('aica_analysis_tone', 'neutral'), 'confident'); ?>>قاطع</option>
                            </select>
                        </label></p>
                        <p><label>سطح جزئیات تحلیل<br>
                            <select name="aica_analysis_detail_level">
                                <option value="short" <?php selected(get_option('aica_analysis_detail_level', 'normal'), 'short'); ?>>کوتاه</option>
                                <option value="normal" <?php selected(get_option('aica_analysis_detail_level', 'normal'), 'normal'); ?>>نرمال</option>
                                <option value="detailed" <?php selected(get_option('aica_analysis_detail_level', 'normal'), 'detailed'); ?>>جزئیات بیشتر</option>
                            </select>
                        </label></p>
                        <p>
                        <p><label><input type="checkbox" name="aica_show_topics" value="1" <?php checked(1, get_option('aica_show_topics', 1)); ?> /> نمایش موضوعات</label><br>
                             <label><input type="checkbox" name="aica_show_topic_summaries" value="1" <?php checked(1, get_option('aica_show_topic_summaries', 1)); ?> /> نمایش خلاصه موضوعات</label><br>
                             <label><input type="checkbox" name="aica_use_custom_topic_pill_style" value="1" <?php checked(1, get_option('aica_use_custom_topic_pill_style', 0)); ?> /> استفاده از استایل سفارشی موضوعات</label>
                            <label><input type="checkbox" name="aica_enable_topics" value="1" <?php checked(1, get_option('aica_enable_topics', 1)); ?> /> موضوعات پرتکرار</label><br>
                            <label><input type="checkbox" name="aica_enable_spam_detection" value="1" <?php checked(1, get_option('aica_enable_spam_detection', 1)); ?> /> تشخیص اسپم</label>
                        </p>
                    </div>

                    <div class="aica-card aica-card-full" data-tab-card="cron-report">
                        <h2>لاگ کران‌جاب</h2>
                        <p class="aica-card-sub">وضعیت اجرا/عدم اجرا، علت خطا، و خروجی آپدیت‌ها</p>
                        <?php $cron_logs = $this->get_human_cron_logs(10); ?>
                        <div class="aica-progress-table-wrap aica-cron-log-table-wrap">
                            <table class="widefat striped aica-progress-table aica-cron-log-table">
                                <thead>
                                    <tr>
                                        <th>زمان</th>
                                        <th>وضعیت</th>
                                        <th>رویداد</th>
                                        <th>جزئیات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($cron_logs)) : ?>
                                        <?php foreach ($cron_logs as $log) : ?>
                                            <tr>
                                                <td><?php echo esc_html((string) ($log['time'] ?? '-')); ?></td>
                                                <td><?php echo esc_html((string) ($log['status'] ?? '-')); ?></td>
                                                <td><?php echo esc_html((string) ($log['message'] ?? '-')); ?></td>
                                                <td><?php echo esc_html((string) ($log['details'] ?? '-')); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else : ?>
                                        <tr><td colspan="4">داده‌ای برای نمایش وجود ندارد.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="aica-card" data-tab-card="display">
                        <h2>نمایش در سایت</h2>
                        <p><label><input type="checkbox" name="aica_frontend_enabled" value="1" <?php checked(1, get_option('aica_frontend_enabled', 1)); ?> /> نمایش باکس تحلیل</label></p>
                        <p><label>موقعیت نمایش<br>
                            <select name="aica_frontend_position">
                                <option value="after" <?php selected(get_option('aica_frontend_position', 'after'), 'after'); ?>>بعد از محتوا</option>
                                <option value="before" <?php selected(get_option('aica_frontend_position', 'after'), 'before'); ?>>قبل از محتوا</option>
                                <option value="shortcode" <?php selected(get_option('aica_frontend_position', 'after'), 'shortcode'); ?>>شورت‌کد</option>
                            </select>
                        </label></p>
                        <p>
                            <label><input type="checkbox" name="aica_show_summary" value="1" <?php checked(1, get_option('aica_show_summary', 1)); ?> /> نمایش خلاصه اصلی</label><br>
                            <label><input type="checkbox" name="aica_show_short_summary" value="1" <?php checked(1, get_option('aica_show_short_summary', 1)); ?> /> نمایش نظر کلی کوتاه</label><br>
                            <label><input type="checkbox" name="aica_show_positive_points" value="1" <?php checked(1, get_option('aica_show_positive_points', 1)); ?> /> نمایش نکات مثبت</label><br>
                            <label><input type="checkbox" name="aica_show_negative_points" value="1" <?php checked(1, get_option('aica_show_negative_points', 1)); ?> /> نمایش نکات منفی</label><br>
                            <label><input type="checkbox" name="aica_show_topics" value="1" <?php checked(1, get_option('aica_show_topics', 1)); ?> /> نمایش موضوعات</label><br>
                            <label><input type="checkbox" name="aica_show_topic_summaries" value="1" <?php checked(1, get_option('aica_show_topic_summaries', 1)); ?> /> نمایش خلاصه موضوعات</label><br>
                            <label><input type="checkbox" name="aica_show_faq" value="1" <?php checked(1, get_option('aica_show_faq', 1)); ?> /> نمایش FAQ</label><br>
                            <label><input type="checkbox" name="aica_show_sentiment_filter" value="1" <?php checked(1, get_option('aica_show_sentiment_filter', 1)); ?> /> نمایش فیلتر احساسات</label>
                        </p>
                        <p><label>حداکثر تعداد نکات مثبت/منفی<br><input type="number" name="aica_points_limit" value="<?php echo esc_attr(get_option('aica_points_limit', 6)); ?>" min="2" max="20" /></label></p>
                    </div>

                    <div class="aica-card" data-tab-card="display">
                        <h2>طراحی باکس AI</h2>
                        <p><label>عنوان باکس<br><input type="text" name="aica_box_title" value="<?php echo esc_attr(get_option('aica_box_title', 'تحلیل هوشمند نظرات کاربران')); ?>" class="regular-text" /></label></p>
                         <p><label>زیرعنوان باکس<br><input type="text" name="aica_box_subtitle" value="<?php echo esc_attr(get_option('aica_box_subtitle', 'خلاصه‌ای سریع از حال‌وهوای دیدگاه‌ها')); ?>" class="regular-text" /></label></p>
                        <p><label>تم نمایشی<br>
                            <select name="aica_theme">
                                <option value="modern-dark" <?php selected(get_option('aica_theme', 'modern-dark'), 'modern-dark'); ?>>مدرن تیره</option>
                                <option value="modern-light" <?php selected(get_option('aica_theme', 'modern-dark'), 'modern-light'); ?>>مدرن روشن</option>
                                <option value="minimal" <?php selected(get_option('aica_theme', 'modern-dark'), 'minimal'); ?>>مینیمال</option>
                                <option value="neon" <?php selected(get_option('aica_theme', 'modern-dark'), 'neon'); ?>>نئون</option>
                                <option value="aurora" <?php selected(get_option('aica_theme', 'modern-dark'), 'aurora'); ?>>آرورا</option>
                                <option value="midnight-pro" <?php selected(get_option('aica_theme', 'modern-dark'), 'midnight-pro'); ?>>Midnight Pro</option>
                                <option value="sunset-pro" <?php selected(get_option('aica_theme', 'modern-dark'), 'sunset-pro'); ?>>Sunset Pro</option>
                                <option value="frost-pro" <?php selected(get_option('aica_theme', 'modern-dark'), 'frost-pro'); ?>>Frost Pro</option>
                            </select>
                        </label></p>
                        <p><label>رنگ اصلی (Accent)<br><input type="text" name="aica_accent_color" value="<?php echo esc_attr(get_option('aica_accent_color', '#4f46e5')); ?>" class="regular-text" /></label></p>
                        <p><label>حداکثر عرض باکس (px)<br><input type="number" name="aica_box_max_width" value="<?php echo esc_attr(get_option('aica_box_max_width', 980)); ?>" min="320" max="1400" /></label></p>
                        <p><label>اندازه عنوان (px)<br><input type="number" name="aica_box_title_size" value="<?php echo esc_attr(get_option('aica_box_title_size', 20)); ?>" min="14" max="36" /></label></p>
                        <p><label>اندازه فونت متن (px)<br><input type="number" name="aica_box_font_size" value="<?php echo esc_attr(get_option('aica_box_font_size', 14)); ?>" min="12" max="22" /></label></p>
                        <p><label>Line-height متن<br><input type="number" step="0.1" name="aica_box_line_height" value="<?php echo esc_attr(get_option('aica_box_line_height', 1.8)); ?>" min="1.2" max="2.2" /></label></p>
                        <p><label>ضخامت کادر (px)<br><input type="number" name="aica_box_border_width" value="<?php echo esc_attr(get_option('aica_box_border_width', 1)); ?>" min="0" max="4" /></label></p>
                        <p><label>رنگ کادر<br><input type="text" name="aica_box_border_color" value="<?php echo esc_attr(get_option('aica_box_border_color', '#dcdcdc')); ?>" class="regular-text" /></label></p>
                        <p><label>گردی گوشه‌ها<br><input type="number" name="aica_box_border_radius" value="<?php echo esc_attr(get_option('aica_box_border_radius', 16)); ?>" min="0" max="40" /></label></p>
                        <p><label>فاصله داخلی باکس<br><input type="number" name="aica_box_padding" value="<?php echo esc_attr(get_option('aica_box_padding', 18)); ?>" min="8" max="48" /></label></p>
                        <p>
                            <label><input type="checkbox" name="aica_glass_effect" value="1" <?php checked(1, get_option('aica_glass_effect', 1)); ?> /> افکت شیشه‌ای</label><br>
                            <label><input type="checkbox" name="aica_show_header_icon" value="1" <?php checked(1, get_option('aica_show_header_icon', 1)); ?> /> نمایش آیکون هدر</label><br>
                            <label><input type="checkbox" name="aica_box_shadow" value="1" <?php checked(1, get_option('aica_box_shadow', 1)); ?> /> نمایش سایه باکس</label>
                        </p>
                        <hr>
                        <h3>استایل نکات مثبت/منفی</h3>
                        <p><label>حالت نمایش نکات<br>
                            <select name="aica_point_style">
                                <option value="card" <?php selected(get_option('aica_point_style', 'card'), 'card'); ?>>باکسی</option>
                                <option value="button" <?php selected(get_option('aica_point_style', 'card'), 'button'); ?>>دکمه‌ای</option>
                            </select>
                        </label></p>
                        <p><label>آیکون نکات مثبت<br><input type="text" name="aica_point_icon_positive" value="<?php echo esc_attr(get_option('aica_point_icon_positive', '🟢')); ?>" class="regular-text" /></label></p>
                        <p><label>آیکون نکات منفی<br><input type="text" name="aica_point_icon_negative" value="<?php echo esc_attr(get_option('aica_point_icon_negative', '🔴')); ?>" class="regular-text" /></label></p>
                        <p><label>رنگ زمینه مثبت<br><input type="text" name="aica_point_bg_positive" value="<?php echo esc_attr(get_option('aica_point_bg_positive', '#dcfce7')); ?>" class="regular-text" /></label></p>
                        <p><label>رنگ زمینه منفی<br><input type="text" name="aica_point_bg_negative" value="<?php echo esc_attr(get_option('aica_point_bg_negative', '#fee2e2')); ?>" class="regular-text" /></label></p>
                        <p><label>رنگ متن نکات<br><input type="text" name="aica_point_text_color" value="<?php echo esc_attr(get_option('aica_point_text_color', '#0f172a')); ?>" class="regular-text" /></label></p>
                    </div>

                    <div class="aica-card" data-tab-card="display">
                        <h2>طراحی دکمه نمایش باکس</h2>
                        <p><label><input type="checkbox" name="aica_enable_toggle_button" value="1" <?php checked(1, get_option('aica_enable_toggle_button', 0)); ?> /> فعال‌سازی دکمه «خلاصه نظرات با AI»</label></p>
                        <p><label>متن دکمه<br><input type="text" name="aica_button_text" value="<?php echo esc_attr(get_option('aica_button_text', 'خلاصه نظرات با AI')); ?>" class="regular-text" /></label></p>
                        <p><label>آیکون دکمه AI<br><input type="text" name="aica_button_icon" value="<?php echo esc_attr(get_option('aica_button_icon', '🤖')); ?>" class="regular-text" /></label></p>
                        <p><label>استایل دکمه<br>
                            <select name="aica_button_style">
                                <option value="solid" <?php selected(get_option('aica_button_style', 'solid'), 'solid'); ?>>سالید</option>
                                <option value="glass" <?php selected(get_option('aica_button_style', 'solid'), 'glass'); ?>>گلس</option>
                                <option value="outline" <?php selected(get_option('aica_button_style', 'solid'), 'outline'); ?>>اوتلاین</option>
                            </select>
                        </label></p>
                        <p><label>جایگاه دکمه<br>
                            <select name="aica_button_align">
                                <option value="right" <?php selected(get_option('aica_button_align', 'right'), 'right'); ?>>راست</option>
                                <option value="left" <?php selected(get_option('aica_button_align', 'right'), 'left'); ?>>چپ</option>
                                <option value="center" <?php selected(get_option('aica_button_align', 'right'), 'center'); ?>>وسط</option>
                            </select>
                        </label></p>
                        <p><label>رنگ پس‌زمینه دکمه<br><input type="text" name="aica_button_bg_color" value="<?php echo esc_attr(get_option('aica_button_bg_color', '#2563eb')); ?>" class="regular-text" /></label></p>
                        <p><label>رنگ متن دکمه<br><input type="text" name="aica_button_text_color" value="<?php echo esc_attr(get_option('aica_button_text_color', '#ffffff')); ?>" class="regular-text" /></label></p>
                        <p><label>گردی دکمه<br><input type="number" name="aica_button_radius" value="<?php echo esc_attr(get_option('aica_button_radius', 14)); ?>" min="8" max="40" /></label></p>
                        <p><label><input type="checkbox" name="aica_button_disable_after_click" value="1" <?php checked(1, get_option('aica_button_disable_after_click', 1)); ?> /> دیزیبل شدن دکمه بعد از کلیک</label></p>
                         <p><label>متن پس از دیزیبل شدن<br><input type="text" name="aica_button_disabled_text" value="<?php echo esc_attr(get_option('aica_button_disabled_text', 'خلاصه بارگذاری شد')); ?>" class="regular-text" /></label></p>
                     </div>

                     <div class="aica-card" data-tab-card="display">
                         <h3>استایل موضوعات پرتکرار</h3>
                         <p><label>رنگ زمینه پِل‌ها<br><input type="text" name="aica_topic_pill_bg_color" value="<?php echo esc_attr(get_option('aica_topic_pill_bg_color', '#fff')); ?>" class="regular-text" /></label></p>
                         <p><label>رنگ متن پِل‌ها<br><input type="text" name="aica_topic_pill_text_color" value="<?php echo esc_attr(get_option('aica_topic_pill_text_color', '#0f172a')); ?>" class="regular-text" /></label></p>
                         <p><label>رنگ حاشیه پِل‌ها<br><input type="text" name="aica_topic_pill_border_color" value="<?php echo esc_attr(get_option('aica_topic_pill_border_color', 'rgba(79,70,229,.2)')); ?>" class="regular-text" /></label></p>
                         <p><label>ضخامت حاشیه پِل‌ها (px)<br><input type="number" name="aica_topic_pill_border_width" value="<?php echo esc_attr(get_option('aica_topic_pill_border_width', 1)); ?>" min="0" max="4" /></label></p>
                         <p><label>گردی گوشهٔ پِل‌ها (px)<br><input type="number" name="aica_topic_pill_border_radius" value="<?php echo esc_attr(get_option('aica_topic_pill_border_radius', 12)); ?>" min="0" max="40" /></label></p>
                        <hr>
                        <h4>استایل کارت‌های موضوعات پرتکرار</h4>
                        <p><label>رنگ زمینهٔ کارت‌ها<br><input type="text" name="aica_topic_card_bg_color" value="<?php echo esc_attr(get_option('aica_topic_card_bg_color', '#fff')); ?>" class="regular-text" /></label></p>
                        <p><label>رنگ متن کارت‌ها<br><input type="text" name="aica_topic_card_text_color" value="<?php echo esc_attr(get_option('aica_topic_card_text_color', '#0f172a')); ?>" class="regular-text" /></label></p>
                        <p><label>رنگ حاشیهٔ کارت‌ها<br><input type="text" name="aica_topic_card_border_color" value="<?php echo esc_attr(get_option('aica_topic_card_border_color', 'rgba(79,70,229,.2)')); ?>" class="regular-text" /></label></p>
                        <p><label>ضخامت حاشیهٔ کارت‌ها (px)<br><input type="number" name="aica_topic_card_border_width" value="<?php echo esc_attr(get_option('aica_topic_card_border_width', 1)); ?>" min="0" max="4" /></label></p>
                        <p><label>گردی گوشهٔ کارت‌ها (px)<br><input type="number" name="aica_topic_card_border_radius" value="<?php echo esc_attr(get_option('aica_topic_card_border_radius', 12)); ?>" min="0" max="40" /></label></p>
                        <p><label>فاصله درونی کارت‌ها (px)<br><input type="number" name="aica_topic_card_padding" value="<?php echo esc_attr(get_option('aica_topic_card_padding', 12)); ?>" min="0" max="40" /></label></p>
                        <h3>استایل پنل موضوعات</h3>
                         <p><label>رنگ زمینه پنل<br><input type="text" name="aica_topics_panel_bg_color" value="<?php echo esc_attr(get_option('aica_topics_panel_bg_color', '')); ?>" class="regular-text" /></label></p>
                         <p><label>رنگ حاشیه پنل<br><input type="text" name="aica_topics_panel_border_color" value="<?php echo esc_attr(get_option('aica_topics_panel_border_color', '')); ?>" class="regular-text" /></label></p>
                         <p><label>ضخامت حاشیه پنل (px)<br><input type="number" name="aica_topics_panel_border_width" value="<?php echo esc_attr(get_option('aica_topics_panel_border_width', 0)); ?>" min="0" max="4" /></label></p>
                         <p><label>گردی گوشهٔ پنل (px)<br><input type="number" name="aica_topics_panel_border_radius" value="<?php echo esc_attr(get_option('aica_topics_panel_border_radius', 18)); ?>" min="0" max="40" /></label></p>
                         <p><label>فاصله داخلی پنل (px)<br><input type="number" name="aica_topics_panel_padding" value="<?php echo esc_attr(get_option('aica_topics_panel_padding', 0)); ?>" min="0" max="48" /></label></p>
                         <hr>
                         <h3>استایل آکاردئون FAQ</h3>
                         <p><label>رنگ زمینه FAQ<br><input type="text" name="aica_faq_bg_color" value="<?php echo esc_attr(get_option('aica_faq_bg_color', '')); ?>" class="regular-text" /></label></p>
                         <p><label>رنگ حاشیه FAQ<br><input type="text" name="aica_faq_border_color" value="<?php echo esc_attr(get_option('aica_faq_border_color', '')); ?>" class="regular-text" /></label></p>
                         <p><label>ضخامت حاشیه FAQ (px)<br><input type="number" name="aica_faq_border_width" value="<?php echo esc_attr(get_option('aica_faq_border_width', 0)); ?>" min="0" max="4" /></label></p>
                         <p><label>گردی گوشهٔ FAQ (px)<br><input type="number" name="aica_faq_border_radius" value="<?php echo esc_attr(get_option('aica_faq_border_radius', 18)); ?>" min="0" max="40" /></label></p>
                         <p><label>فاصله داخلی FAQ (px)<br><input type="number" name="aica_faq_padding" value="<?php echo esc_attr(get_option('aica_faq_padding', 0)); ?>" min="0" max="48" /></label></p>
                     </div>

                    <div class="aica-card" data-tab-card="advanced">
                        <h2>CSS Lab (مستقل)</h2>
                        <p><label><input type="checkbox" name="aica_custom_css_enabled" value="1" <?php checked(1, get_option('aica_custom_css_enabled', 1)); ?> /> فعال بودن CSS سفارشی</label></p>
                        <p class="description">این بخش مستقل از تنظیمات ظاهری است و می‌تواند کل استایل باکس را Override کند.</p>
                        <textarea id="aica-custom-css-editor" name="aica_custom_css" rows="14" class="large-text code" placeholder=".aica-box { ... }"><?php echo esc_textarea(get_option('aica_custom_css', '')); ?></textarea>
                        <p class="description">Scope پیشنهادی: فقط selectorهایی با پیشوند <code>.aica-</code> بنویسید.</p>
                    </div>

                    <div class="aica-card" data-tab-card="advanced">
                        <h2>JS Lab (مستقل)</h2>
                        <p><label><input type="checkbox" name="aica_custom_js_enabled" value="1" <?php checked(1, get_option('aica_custom_js_enabled', 0)); ?> /> فعال بودن JS سفارشی</label></p>
                        <p class="description">این کد در فرانت اجرا می‌شود. بدون تگ <code>script</code> وارد کنید.</p>
                        <textarea id="aica-custom-js-editor" name="aica_custom_js" rows="10" class="large-text code" placeholder="console.log('AICA JS loaded');"><?php echo esc_textarea(get_option('aica_custom_js', '')); ?></textarea>
                    </div>
                </div>
                <div class="aica-savebar">
                    <span>تغییرات هر بخش را در پایان ذخیره کنید.</span>
                    <?php submit_button('ذخیره همه تنظیمات', 'primary', 'submit', false); ?>
                </div>
            </form>

            <div class="aica-card aica-card-full aica-tab-pane aica-tab-pane-card <?php echo $active_tab === 'custom-cron' ? 'is-active' : ''; ?>" data-tab="custom-cron" style="margin-top:16px;">
                <h2>مدیریت کران‌های اختصاصی</h2>
                <p class="aica-card-sub">برای هر نوع محتوا قانون آپدیت مبتنی بر تعداد کامنت جدید تعریف کنید.</p>
                <div class="aica-quick-actions" style="margin-bottom:12px;">
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('aica_quick_run_cron', 'aica_quick_nonce'); ?>
                        <input type="hidden" name="action" value="aica_quick_run_cron">
                        <button type="submit" class="button button-primary">اجرای کلی آپدیت (همه قوانین فعال)</button>
                    </form>
                </div>
                <?php $custom_rules = (array) get_option('aica_custom_cron_rules', []); ?>
                <?php if (!empty($custom_rules)) : ?>
                    <div class="aica-progress-table-wrap" style="margin-bottom:12px;">
                        <table class="widefat striped aica-progress-table">
                            <thead>
                                <tr>
                                    <th>نام قانون</th>
                                    <th>حالت انتخاب</th>
                                    <th>آستانه کامنت جدید</th>
                                    <th>حداقل کامنت</th>
                                    <th>شرح هدف</th>
                                    <th>وضعیت</th>
                                    <th>عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($custom_rules as $rule) : ?>
                                    <?php $rule_id = sanitize_key((string) ($rule['id'] ?? '')); ?>
                                    <tr>
                                        <td><?php echo esc_html((string) ($rule['name'] ?? 'بدون نام')); ?></td>
                                        <td><?php echo esc_html((string) ($rule['selection_mode'] ?? 'post_type_latest')); ?></td>
                                        <td><?php echo esc_html((int) ($rule['new_comments_threshold'] ?? 10)); ?></td>
                                        <td><?php echo esc_html((int) ($rule['min_comments_to_analyze'] ?? 1)); ?></td>
                                        <td><?php echo esc_html((string) ($rule['rule_target_summary'] ?? '-')); ?></td>
                                        <td><?php echo !empty($rule['enabled']) ? 'فعال' : 'غیرفعال'; ?></td>
                                        <td>
                                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                                                <?php wp_nonce_field('aica_delete_custom_cron_rule', 'aica_custom_cron_nonce'); ?>
                                                <input type="hidden" name="action" value="aica_delete_custom_cron_rule">
                                                <input type="hidden" name="rule_id" value="<?php echo esc_attr($rule_id); ?>">
                                                <button type="submit" class="button-link-delete">حذف</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="aica-manual-card">
                    <?php wp_nonce_field('aica_save_custom_cron_rule', 'aica_custom_cron_nonce'); ?>
                    <input type="hidden" name="action" value="aica_save_custom_cron_rule">
                    <div class="aica-mf-grid">
                        <p class="aica-field"><label>نام قانون<br><input type="text" name="rule_name" required></label></p>
                        <p class="aica-field"><label>روش انتخاب</label><br>
                            <select name="selection_mode" id="aica-custom-rule-selection-mode">
                                <option value="none">یک شناسه پست</option>
                                <option value="post_ids">چند شناسه پست</option>
                                <option value="post_type_latest" selected>آخرین موارد یک نوع پست</option>
                            </select>
                        </p>
                        <p class="aica-field" id="aica-custom-rule-post-type-wrap"><label>نوع پست (اختیاری)<br>
                            <select name="rule_post_type" id="aica-custom-rule-post-type">
                                <option value="">همه</option>
                                <?php foreach ($post_types as $post_type) : ?>
                                    <option value="<?php echo esc_attr($post_type->name); ?>"><?php echo esc_html($post_type->labels->singular_name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label></p>
                        <p class="aica-field" id="aica-custom-rule-post-id-wrap"><label>شناسه پست (برای حالت تک‌پست)<br><input type="number" name="rule_post_id" id="aica-custom-rule-post-id" min="1" value=""></label></p>
                        <p class="aica-field" id="aica-custom-rule-post-ids-wrap"><label>شناسه‌ها (برای حالت چند شناسه)<br><input type="text" name="rule_post_ids" id="aica-custom-rule-post-ids" value="" placeholder="مثال: 12,45,78"></label></p>
                        <p class="aica-field"><label>آستانه کامنت جدید تاییدشده<br><input type="number" name="rule_new_comments_threshold" min="1" value="10" required></label></p>
                        <p class="aica-field" id="aica-custom-rule-limit-wrap"><label>تعداد آخرین موارد (برای حالت آخرین‌ها)<br><input type="number" name="rule_max_posts" id="aica-custom-rule-limit" min="1" value="20" required></label></p>
                        <p class="aica-field"><label>حداقل کامنت تاییدشده برای تحلیل<br><input type="number" name="rule_min_comments_to_analyze" min="1" value="1" required></label></p>
                        <p class="aica-field"><label><input type="checkbox" name="rule_enabled" value="1" checked> فعال باشد</label></p>
                    </div>
                    <div class="aica-mf-actions"><button type="submit" class="button button-primary">افزودن کران اختصاصی</button></div>
                </form>
            </div>

            <?php if ($analysis_data && $analysis_status === 'done') : ?>
                <div class="aica-card" style="margin-top:16px;">
                    <?php $sentiment_topics = $this->get_sentiment_topic_headlines($analysis_post_id, 8); ?>
                    <h2>نتیجه آخرین تحلیل پست <?php echo esc_html($analysis_post_id); ?></h2>
                    <p><strong>نظر کلی:</strong> <?php echo esc_html($analysis_data['short_summary'] ?? ''); ?></p>
                    <p><?php echo esc_html($analysis_data['summary'] ?? ''); ?></p>
                    <div>
                        <strong>نکات مثبت</strong>
                        <?php if (!empty($sentiment_topics['positive'])) : ?>
                            <ul class="aica-admin-points">
                                <?php foreach ($sentiment_topics['positive'] as $topic) : ?>
                                    <li class="aica-admin-point-pos"><?php echo esc_html($topic); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else : ?>
                            <span>داده کافی موجود نیست.</span>
                        <?php endif; ?>
                    </div>
                    <div style="margin-top:10px;">
                        <strong>نکات منفی</strong>
                        <?php if (!empty($sentiment_topics['negative'])) : ?>
                            <ul class="aica-admin-points">
                                <?php foreach ($sentiment_topics['negative'] as $topic) : ?>
                                    <li class="aica-admin-point-neg"><?php echo esc_html($topic); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else : ?>
                            <span>داده کافی موجود نیست.</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
                </main>
            </div>
        </div>
        <style>
            :root{
                --aica-bg:#f4f7fb;
                --aica-surface:#ffffff;
                --aica-border:#e5eaf1;
                --aica-border-soft:#edf2f8;
                --aica-text:#0f172a;
                --aica-text-muted:#64748b;
                --aica-primary:#2563eb;
                --aica-primary-strong:#1d4ed8;
                --aica-shadow:0 10px 30px rgba(15,23,42,.06);
                --aica-shadow-soft:0 4px 16px rgba(15,23,42,.05);
                --aica-radius:14px;
                --aica-radius-lg:18px;
            }
            .aica-admin-wrap{max-width:1320px;direction:rtl;text-align:right;background:radial-gradient(1200px 350px at 100% -20%, #dbeafe 0%, rgba(219,234,254,0) 60%),var(--aica-bg);padding:10px 14px 18px;border-radius:18px;font-family:Inter,-apple-system,BlinkMacSystemFont,\"Segoe UI\",Roboto,\"Helvetica Neue\",Arial,sans-serif}
            .aica-admin-wrap *{box-sizing:border-box}
            .aica-admin-wrap input,.aica-admin-wrap select,.aica-admin-wrap textarea{direction:rtl;text-align:right;border:1px solid var(--aica-border);border-radius:10px;padding:8px 10px;transition:border-color .15s ease,box-shadow .15s ease;background:#fff}
            .aica-admin-wrap input:focus,.aica-admin-wrap select:focus,.aica-admin-wrap textarea:focus{outline:none;border-color:#93c5fd;box-shadow:0 0 0 3px rgba(37,99,235,.12)}
            .aica-admin-wrap input,.aica-admin-wrap select{min-height:40px}
            .aica-admin-wrap textarea{line-height:1.8}
            .aica-admin-wrap input[type="checkbox"],.aica-admin-wrap input[type="radio"]{
                min-height:auto;
                width:16px;
                height:16px;
                padding:0;
                margin:0 0 0 8px;
                border-radius:4px;
                border:1px solid #94a3b8;
                background:#fff;
                vertical-align:middle;
                accent-color:var(--aica-primary);
                box-shadow:none;
                cursor:pointer;
            }
            .aica-admin-wrap input[type="checkbox"]:focus,.aica-admin-wrap input[type="radio"]:focus{
                box-shadow:0 0 0 3px rgba(37,99,235,.18);
                border-color:var(--aica-primary);
            }
            .aica-admin-wrap label > input[type="checkbox"],.aica-admin-wrap label > input[type="radio"]{
                transform:translateY(1px);
            }
            .aica-admin-wrap .notice,.aica-admin-wrap .notice p{color:#111 !important}
            .aica-admin-wrap .button{border-radius:10px;padding-inline:14px;font-weight:600}
            .aica-admin-wrap .button-primary{background:linear-gradient(135deg,var(--aica-primary),var(--aica-primary-strong));border-color:var(--aica-primary-strong);box-shadow:0 6px 18px rgba(37,99,235,.24)}
            .aica-admin-wrap .button-primary:hover{filter:brightness(1.04)}

            .aica-hero{display:flex;justify-content:space-between;align-items:center;gap:16px;background:radial-gradient(130% 120% at 100% 0,#1e3a8a 0,#0f172a 60%);color:#fff;padding:24px 26px;border-radius:var(--aica-radius-lg);margin:4px 0 16px;box-shadow:0 16px 36px rgba(15,23,42,.26);position:relative;overflow:hidden}
            .aica-hero:after{content:'';position:absolute;inset:0;background:linear-gradient(120deg,rgba(255,255,255,.10),rgba(255,255,255,0) 35%);pointer-events:none}
            .aica-hero h1{margin:0 0 6px;color:#fff;font-size:26px;font-weight:800;letter-spacing:-.2px}
            .aica-hero p{margin:0;opacity:.9}
            .aica-hero-badges{display:flex;flex-wrap:wrap;gap:8px}
            .aica-hero-badges span{background:rgba(255,255,255,.14);backdrop-filter:blur(4px);padding:7px 12px;border-radius:999px;border:1px solid rgba(255,255,255,.18)}

            .aica-layout{display:block}
            .aica-sidebar{background:var(--aica-surface);border:1px solid var(--aica-border-soft);border-radius:var(--aica-radius);padding:14px;box-shadow:var(--aica-shadow-soft);margin-bottom:14px;position:sticky;top:12px;z-index:4}
            .aica-sidebar h3{margin:0 0 10px;color:var(--aica-text);font-weight:700}
            .aica-tabs{display:flex;gap:8px;flex-wrap:wrap}
            .aica-tab{padding:9px 13px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:999px;text-decoration:none;color:#0f172a;font-weight:600;transition:all .18s ease}
            .aica-tab:hover{background:#e9eff7}
            .aica-tab.is-active{background:linear-gradient(135deg,var(--aica-primary),var(--aica-primary-strong));border-color:var(--aica-primary-strong);color:#fff;box-shadow:0 8px 18px rgba(37,99,235,.24)}
            .aica-sidebar-meta{margin-top:12px;display:flex;gap:18px;flex-wrap:wrap}
            .aica-sidebar-meta div{display:flex;gap:7px;align-items:center}
            .aica-sidebar-meta span{color:var(--aica-text-muted)}
            .aica-main{min-width:0}

            .aica-tab-pane{display:none}
            .aica-tab-pane.is-active{display:grid}
            .aica-tab-pane-card.is-active{display:block}
            .aica-tab-pane[data-tab="overview"]:not(.is-active){display:none !important}

            .aica-stats-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin:0 0 16px}
            .aica-stat-card{background:linear-gradient(165deg,#0f172a,#1e293b);color:#fff;border-radius:var(--aica-radius);padding:15px 14px;min-height:98px;display:flex;flex-direction:column;justify-content:space-between;gap:8px;box-shadow:var(--aica-shadow-soft)}
            .aica-stat-card span{display:block;opacity:.78;font-size:12px;line-height:1.5;margin:0}
            .aica-stat-card strong{font-size:24px;line-height:1.12;display:block;margin:0;font-weight:800}

            .aica-admin-grid:not(.aica-tab-pane),.aica-settings-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
            .aica-card{background:var(--aica-surface);border:1px solid var(--aica-border-soft);border-radius:var(--aica-radius);padding:16px;box-shadow:var(--aica-shadow);position:relative;transition:transform .16s ease,box-shadow .16s ease,border-color .16s ease}
            .aica-card:hover{transform:translateY(-2px);box-shadow:0 14px 30px rgba(15,23,42,.10);border-color:#d7e3f3}
            .aica-card h2{margin:0 0 6px;color:var(--aica-text);font-size:18px;line-height:1.35}
            .aica-card-sub{margin:0 0 12px;color:var(--aica-text-muted)}
            .aica-card-full{grid-column:1 / -1}

            .aica-settings-form{margin-top:14px}
            .aica-savebar{position:sticky;top:14px;z-index:5;display:flex;justify-content:space-between;align-items:center;gap:10px;background:#fff;border:1px solid var(--aica-border);padding:11px 12px;border-radius:12px;margin-top:22px;margin-bottom:12px;box-shadow:var(--aica-shadow-soft);backdrop-filter:blur(2px)}
            .aica-settings-form [data-tab-card]{display:none}
            .aica-settings-form.aica-tab-analysis [data-tab-card~="analysis"],
            .aica-settings-form.aica-tab-display [data-tab-card~="display"],
            .aica-settings-form.aica-tab-custom-cron [data-tab-card~="custom-cron"],
            .aica-settings-form.aica-tab-cron-report [data-tab-card~="cron-report"],
            .aica-settings-form.aica-tab-advanced [data-tab-card~="advanced"]{display:block}

            .aica-health-list{margin:0;padding:0;list-style:none}
            .aica-health-list li{display:flex;justify-content:space-between;padding:9px 0;border-bottom:1px dashed var(--aica-border)}
            .aica-health-list li:last-child{border-bottom:0}
            .aica-health-list .ok{color:#0f766e;font-weight:700}
            .aica-health-list .bad{color:#b91c1c;font-weight:700}

            .aica-quick-actions{display:flex;flex-wrap:wrap;gap:10px}
            .aica-analysis-filters{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:8px;margin-bottom:12px}
            .aica-analysis-list{display:grid;gap:10px}
            .aica-analysis-item{border:1px solid var(--aica-border);border-radius:12px;padding:9px 11px;background:#fbfdff}
            .aica-analysis-item summary{cursor:pointer;display:grid;grid-template-columns:80px 1fr 140px;gap:10px;align-items:center;font-weight:700;color:#0f172a}
            .aica-analysis-detail{margin-top:10px;padding-top:10px;border-top:1px dashed #d5deea}

            .aica-progress-table-wrap{width:100%;border:1px solid var(--aica-border);border-radius:12px;overflow:hidden;background:#fff;box-shadow:inset 0 1px 0 rgba(255,255,255,.7)}
            .aica-progress-table{width:100%;table-layout:fixed;border-collapse:collapse}
            .aica-progress-table th,.aica-progress-table td{vertical-align:top;line-height:1.75;text-align:right;padding:10px 12px}
            .aica-progress-table thead th{background:#f8fbff;color:#334155;border-bottom:1px solid #e2e8f0;font-weight:800}
            .aica-progress-table tbody tr:nth-child(even){background:#fcfdff}
            .aica-progress-table tbody tr:hover{background:#f6faff}
            .aica-progress-table th{white-space:nowrap}
            .aica-progress-table th:nth-child(1),.aica-progress-table td:nth-child(1){width:90px}
            .aica-progress-table th:nth-child(2),.aica-progress-table td:nth-child(2){width:34%}
            .aica-progress-table th:nth-child(3),.aica-progress-table td:nth-child(3){width:120px}
            .aica-progress-table th:nth-child(4),.aica-progress-table td:nth-child(4){width:130px}
            .aica-progress-table th:nth-child(5),.aica-progress-table td:nth-child(5){width:auto}
            .aica-progress-table td:nth-child(1),.aica-progress-table td:nth-child(3),.aica-progress-table td:nth-child(4){white-space:nowrap}
            .aica-progress-table td:nth-child(2),.aica-progress-table td:nth-child(5){word-break:break-word}
            .aica-cron-log-table{table-layout:auto}
            .aica-cron-log-table th:nth-child(1),.aica-cron-log-table td:nth-child(1){width:170px;white-space:nowrap}
            .aica-cron-log-table th:nth-child(2),.aica-cron-log-table td:nth-child(2){width:110px;white-space:nowrap}
            .aica-cron-log-table th:nth-child(3),.aica-cron-log-table td:nth-child(3){min-width:320px}
            .aica-cron-log-table th:nth-child(4),.aica-cron-log-table td:nth-child(4){min-width:360px}
            .aica-cron-log-table td:nth-child(3),.aica-cron-log-table td:nth-child(4){white-space:normal;word-break:break-word}
            .aica-cron-log-table-wrap{overflow:auto}

            .aica-pill{display:inline-block;padding:3px 9px;border-radius:999px;background:#eef2ff;color:#1e40af;margin:3px;font-size:12px;font-weight:600}
            .aica-pagination{display:flex;gap:6px;flex-wrap:wrap;margin-top:10px}
            .aica-page-btn{display:inline-block;padding:5px 10px;border-radius:9px;background:#eef2ff;text-decoration:none;color:#334155;border:1px solid #e2e8f0}
            .aica-page-btn.is-active{background:linear-gradient(135deg,var(--aica-primary),var(--aica-primary-strong));color:#fff;border-color:var(--aica-primary-strong)}

            .aica-admin-points{margin:8px 0 14px;padding:0;list-style:none}
            .aica-admin-points li{position:relative;padding-right:18px;margin:6px 0}
            .aica-admin-points li:before{content:'';width:10px;height:10px;border-radius:50%;position:absolute;right:0;top:6px}
            .aica-admin-point-pos:before{background:#16a34a}
            .aica-admin-point-neg:before{background:#dc2626}

            #aica-manual-analysis-form p{margin:0}
            #aica-manual-analysis-form label{font-weight:700;color:#0f172a}
            #aica-manual-analysis-form input,#aica-manual-analysis-form select,#aica-manual-analysis-form textarea{width:100%;max-width:100%}
            .aica-manual-card{background:linear-gradient(180deg,#fbfdff,#f6faff);border:1px solid #dbeafe;border-radius:12px;padding:13px;box-shadow:inset 0 1px 0 rgba(255,255,255,.8)}
            .aica-mf-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
            .aica-field{background:#fff;border:1px solid var(--aica-border);border-radius:11px;padding:10px;display:flex;flex-direction:column;gap:6px;transition:border-color .14s ease,box-shadow .14s ease}
            .aica-field:focus-within{border-color:#93c5fd;box-shadow:0 0 0 3px rgba(37,99,235,.10)}
            .aica-field .description{margin:0;color:var(--aica-text-muted)}
            .aica-mf-actions{display:flex;justify-content:flex-end;margin-top:10px}
            .aica-admin-grid + .aica-card.aica-tab-pane{margin-top:8px}

            @media (max-width:1100px){
                .aica-stats-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
                .aica-admin-grid,.aica-settings-grid{grid-template-columns:1fr}
                .aica-analysis-filters{grid-template-columns:repeat(2,minmax(0,1fr))}
                .aica-mf-grid{grid-template-columns:1fr}
            }
            @media (max-width:700px){
                .aica-hero{flex-direction:column;align-items:flex-start}
                .aica-stats-grid{grid-template-columns:1fr}
                .aica-analysis-filters{grid-template-columns:1fr}
                .aica-analysis-item summary{grid-template-columns:1fr}
            }
        </style>
        <script>
            (function () {
                const postIdInput = document.getElementById('aica-post-id');
                const postTypeInput = document.getElementById('aica-manual-post-type');
                const modeInput = document.getElementById('aica-manual-filter-mode');
                const postIdsWrap = document.getElementById('aica-post-ids-wrap');
                const postTypeLimitWrap = document.getElementById('aica-post-type-limit-wrap');
                const postTypeWrap = document.getElementById('aica-post-type-wrap');
                const singlePostWrap = document.getElementById('aica-single-post-id-wrap');
                const postIdsInput = document.getElementById('aica-manual-post-ids');
                const postLimitInput = document.getElementById('aica-manual-limit');
                const form = document.getElementById('aica-manual-analysis-form');
                const submitBtn = document.getElementById('aica-run-analysis-btn');
                const status = document.getElementById('aica-analysis-status');
                const progressEmpty = document.getElementById('aica-progress-empty');
                const progressWrap = document.getElementById('aica-progress-wrap');
                const progressBar = document.getElementById('aica-progress-bar');
                const progressSummary = document.getElementById('aica-progress-summary');
                const progressRows = document.getElementById('aica-progress-rows');
                const testApiBtn = document.getElementById('aica-test-api-btn');
                const testApiResult = document.getElementById('aica-test-api-result');
                const tabLinks = Array.from(document.querySelectorAll('.aica-tab'));
                const tabPanes = Array.from(document.querySelectorAll('.aica-tab-pane'));
                if (!postIdInput || !form || !submitBtn || !status) {
                    return;
                }

                const applyModeUi = () => {
                    const mode = modeInput ? modeInput.value : 'none';
                    const isNone = mode === 'none';
                    const isIds = mode === 'post_ids';
                    const isLatest = mode === 'post_type_latest';

                    if (singlePostWrap) singlePostWrap.style.display = isNone ? '' : 'none';
                    if (postIdsWrap) postIdsWrap.style.display = isIds ? '' : 'none';
                    if (postTypeWrap) postTypeWrap.style.display = isLatest ? '' : 'none';
                    if (postTypeLimitWrap) postTypeLimitWrap.style.display = isLatest ? '' : 'none';

                    postIdInput.required = isNone;
                    if (postIdsInput) postIdsInput.required = isIds;
                    if (postLimitInput) postLimitInput.required = isLatest;

                    if (!isNone) {
                        status.textContent = '';
                    }
                };
                if (modeInput) {
                    modeInput.addEventListener('change', applyModeUi);
                    applyModeUi();
                }

                const customRuleModeInput = document.getElementById('aica-custom-rule-selection-mode');
                const customRulePostTypeWrap = document.getElementById('aica-custom-rule-post-type-wrap');
                const customRulePostIdWrap = document.getElementById('aica-custom-rule-post-id-wrap');
                const customRulePostIdsWrap = document.getElementById('aica-custom-rule-post-ids-wrap');
                const customRuleLimitWrap = document.getElementById('aica-custom-rule-limit-wrap');
                const customRulePostIdInput = document.getElementById('aica-custom-rule-post-id');
                const customRulePostIdsInput = document.getElementById('aica-custom-rule-post-ids');
                const customRuleLimitInput = document.getElementById('aica-custom-rule-limit');

                const applyCustomRuleModeUi = () => {
                    if (!customRuleModeInput) {
                        return;
                    }
                    const mode = customRuleModeInput.value;
                    const isSingle = mode === 'none';
                    const isIds = mode === 'post_ids';
                    const isLatest = mode === 'post_type_latest';

                    if (customRulePostTypeWrap) customRulePostTypeWrap.style.display = isLatest ? '' : 'none';
                    if (customRulePostIdWrap) customRulePostIdWrap.style.display = isSingle ? '' : 'none';
                    if (customRulePostIdsWrap) customRulePostIdsWrap.style.display = isIds ? '' : 'none';
                    if (customRuleLimitWrap) customRuleLimitWrap.style.display = isLatest ? '' : 'none';

                    if (customRulePostIdInput) customRulePostIdInput.required = isSingle;
                    if (customRulePostIdsInput) customRulePostIdsInput.required = isIds;
                    if (customRuleLimitInput) customRuleLimitInput.required = isLatest;
                };
                if (customRuleModeInput) {
                    customRuleModeInput.addEventListener('change', applyCustomRuleModeUi);
                    applyCustomRuleModeUi();
                }

                if (testApiBtn && testApiResult) {
                    testApiBtn.addEventListener('click', async function () {
                        testApiBtn.disabled = true;
                        testApiResult.textContent = 'در حال تست اتصال...';
                        try {
                            const response = await fetch(ajaxurl, {
                                method: 'POST',
                                headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                                body: new URLSearchParams({
                                    action: 'aica_test_api_connection',
                                    nonce: '<?php echo esc_js(wp_create_nonce('aica_test_api_connection_nonce')); ?>'
                                })
                            });
                            const data = await response.json();
                            if (data.success) {
                                testApiResult.textContent = data.data && data.data.message ? data.data.message : 'اتصال موفق بود.';
                            } else {
                                testApiResult.textContent = data.data && data.data.message ? data.data.message : 'اتصال ناموفق بود.';
                            }
                        } catch (error) {
                            testApiResult.textContent = 'خطا در تست اتصال.';
                        }
                        testApiBtn.disabled = false;
                    });
                }

                const fetchCount = () => {
                    const postId = parseInt(postIdInput.value, 10);
                    if (!postId) {
                        status.textContent = '';
                        return;
                    }

                    status.textContent = 'در حال بررسی تعداد نظرات...';
                    fetch(ajaxurl, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                        body: new URLSearchParams({
                            action: 'aica_comment_count',
                            post_id: postId
                        })
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (!data.success) {
                            status.textContent = data.data && data.data.message ? data.data.message : 'خطا در دریافت اطلاعات پست.';
                            return;
                        }

                        const count = parseInt(data.data.count || 0, 10);
                        if (count > 0) {
                            status.textContent = `این پست ${count} نظر تاییدشده دارد.`;
                        } else {
                            status.textContent = 'این پست نظر تاییدشده‌ای برای تحلیل ندارد.';
                        }
                    })
                    .catch(() => {
                        status.textContent = 'خطا در دریافت تعداد نظرات.';
                    });
                };

                postIdInput.addEventListener('change', fetchCount);
                postIdInput.addEventListener('blur', fetchCount);

                const escapeHtml = (value) => String(value || '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');

                const renderProgress = (state) => {
                    if (!progressWrap || !progressBar || !progressSummary || !progressRows) {
                        return;
                    }
                    const total = state.items.length;
                    const done = state.items.filter(i => i.status !== 'pending').length;
                    const success = state.items.filter(i => i.status === 'success').length;
                    const failed = state.items.filter(i => i.status === 'failed').length;
                    const ignored = state.items.filter(i => i.status === 'ignored').length;
                    const percent = total > 0 ? Math.round((done / total) * 100) : 0;
                    progressBar.style.width = `${percent}%`;
                    progressSummary.textContent = `پیشرفت: ${done}/${total} (${percent}%) | موفق: ${success} | ناموفق: ${failed} | نادیده‌گرفته: ${ignored}`;
                    progressRows.innerHTML = state.items.map((item) => {
                        const postId = escapeHtml(item.post_id);
                        const title = escapeHtml(item.post_title || '-');
                        const comments = escapeHtml(item.comments_count);
                        const statusLabel = escapeHtml(item.status_label || '-');
                        const message = escapeHtml(item.message || '-');
                        return `<tr><td>#${postId}</td><td>${title}</td><td>${comments}</td><td>${statusLabel}</td><td>${message}</td></tr>`;
                    }).join('');
                };

                const processQueue = async (state) => {
                    for (let i = 0; i < state.items.length; i++) {
                        const item = state.items[i];
                        if (item.status !== 'pending') continue;
                        const body = new URLSearchParams({
                            action: 'aica_process_manual_analysis_item',
                            nonce: state.nonce,
                            post_id: item.post_id
                        });
                        let data;
                        try {
                            const response = await fetch(ajaxurl, {
                                method: 'POST',
                                headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                                body
                            });
                            data = await response.json();
                        } catch (error) {
                            data = null;
                        }
                        if (data && data.success && data.data) {
                            state.items[i] = data.data.item;
                        } else {
                            state.items[i].status = 'failed';
                            state.items[i].status_label = 'ناموفق';
                            state.items[i].message = (data && data.data && data.data.message) ? data.data.message : 'خطای نامشخص';
                        }
                        renderProgress(state);
                    }
                    submitBtn.disabled = false;
                    status.textContent = 'تحلیل دستی کامل شد.';
                };

                const activateTab = (tabName) => {
                    if (!tabName) return;
                    tabLinks.forEach((link) => {
                        const isTarget = (link.getAttribute('href') || '').includes(`tab=${tabName}`);
                        link.classList.toggle('is-active', isTarget);
                    });
                    tabPanes.forEach((pane) => {
                        const isTarget = pane.getAttribute('data-tab') === tabName;
                        pane.classList.toggle('is-active', isTarget);
                    });
                };

                form.addEventListener('submit', async function (event) {
                    event.preventDefault();
                    submitBtn.disabled = true;
                    status.textContent = 'در حال آماده‌سازی لیست تحلیل...';
                    const formData = new FormData(form);
                    formData.append('action', 'aica_prepare_manual_analysis');
                    formData.append('nonce', '<?php echo esc_js(wp_create_nonce('aica_manual_analysis_ajax')); ?>');
                    let payload;
                    try {
                        const res = await fetch(ajaxurl, { method: 'POST', body: formData });
                        payload = await res.json();
                    } catch (error) {
                        submitBtn.disabled = false;
                        status.textContent = 'خطا در ارتباط با سرور.';
                        return;
                    }
                    if (!payload.success || !payload.data || !payload.data.items || !payload.data.items.length) {
                        submitBtn.disabled = false;
                        status.textContent = payload.data && payload.data.message ? payload.data.message : 'آیتمی برای تحلیل پیدا نشد.';
                        return;
                    }
                    status.textContent = 'تحلیل شروع شد...';
                    if (progressEmpty) progressEmpty.style.display = 'none';
                    if (progressWrap) progressWrap.style.display = '';
                    activateTab('progress');
                    if (window.history && window.history.replaceState) {
                        const url = new URL(window.location.href);
                        url.searchParams.set('tab', 'progress');
                        window.history.replaceState({}, '', url.toString());
                    }
                    const state = { nonce: payload.data.nonce, items: payload.data.items };
                    renderProgress(state);
                    await processQueue(state);
                });
            })();
        </script>
        <?php
    }

    private function get_dashboard_stats()
    {
        global $wpdb;
        $table_name = AICA_Database::table_name();
        $analyzed_posts = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        $approved_comments = (int) get_comments([
            'status' => 'approve',
            'type__not_in' => ['pingback', 'trackback'],
            'count' => true,
        ]);
        $last_run_ts = (int) get_option('aica_last_analysis_run', 0);
        $next_run_ts = AICA_Cron::get_next_analysis_run_timestamp(true);
        $next_rule_info = AICA_Cron::get_next_custom_rule_run_info();
        $stale_posts = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             WHERE p.post_status='publish' AND p.post_type IN ('post','product')
             AND NOT EXISTS (SELECT 1 FROM {$table_name} a WHERE a.post_id=p.ID)"
        );
        $avg_comments = $analyzed_posts > 0 ? round($approved_comments / $analyzed_posts, 1) : 0;

        return [
            'analyzed_posts' => $analyzed_posts,
            'approved_comments' => $approved_comments,
            'last_run' => $last_run_ts ? wp_date('Y-m-d H:i', $last_run_ts) : 'هنوز اجرا نشده',
            'next_run' => $next_run_ts > time() ? wp_date('Y-m-d H:i', $next_run_ts) : 'زمان‌بندی نشده',
            'next_rule_label' => $next_rule_info ? ((string) $next_rule_info['rule_name'] . ' - ' . wp_date('Y-m-d H:i', (int) $next_rule_info['timestamp'])) : 'زمان‌بندی نشده',
            'stale_posts' => $stale_posts,
            'avg_comments_per_analyzed_post' => $avg_comments,
        ];
    }

    private function get_system_health()
    {
        return [
            'api_key_ok' => !empty(get_option('aica_api_key', '')),
            'cron_ok' => (bool) get_option('aica_cron_enabled', 1),
            'frontend_ok' => (bool) get_option('aica_frontend_enabled', 1),
            'email_ok' => is_email(get_option('aica_alert_email', get_option('admin_email'))),
        ];
    }

    private function get_human_cron_logs($limit = 10)
    {
        $raw_logs = AICA_Cron::get_logs(max(10, $limit * 4));
        $items = [];
        foreach ($raw_logs as $log) {
            $row = $this->map_cron_log_to_human($log);
            if (!$row) {
                continue;
            }
            $items[] = $row;
            if (count($items) >= $limit) {
                break;
            }
        }
        return $items;
    }

    private function map_cron_log_to_human($log)
    {
        $event = (string) ($log['event'] ?? '');
        $context = is_array($log['context'] ?? null) ? $log['context'] : [];
        $time = (string) ($log['time'] ?? '');

        if ($event === 'scheduled_analysis_started') {
            return [
                'time' => $time,
                'status' => 'شروع شد',
                'message' => 'کران تحلیل اجرا شد.',
                'details' => 'تعداد پست هدف: ' . (int) ($context['posts_count'] ?? 0),
            ];
        }
        if ($event === 'scheduled_analysis_finished') {
            return [
                'time' => $time,
                'status' => 'موفق',
                'message' => 'کران تحلیل کامل شد.',
                'details' => sprintf(
                    'آپدیت شد: %d | آپدیت نشد: %d | نادیده‌گرفته: %d',
                    (int) ($context['success'] ?? 0),
                    (int) ($context['failed'] ?? 0),
                    (int) ($context['ignored'] ?? 0)
                ),
            ];
        }
        if ($event === 'scheduled_analysis_skipped') {
            $reason = (string) ($context['reason'] ?? '');
            $reason_map = [
                'analyzer_missing' => 'ماژول تحلیل در دسترس نبود.',
                'cron_disabled' => 'کران غیرفعال است.',
                'interval_not_reached' => 'هنوز زمان اجرای بعدی نرسیده بود.',
                'no_enabled_rules' => 'هیچ کران اختصاصی فعالی تعریف نشده است.',
            ];
            return [
                'time' => $time,
                'status' => 'اجرا نشد',
                'message' => 'کران تحلیل اجرا نشد.',
                'details' => $reason_map[$reason] ?? 'علت مشخص نشد.',
            ];
        }
        if ($event === 'post_analysis_result') {
            $post_id = (int) ($context['post_id'] ?? 0);
            $status = (string) ($context['status'] ?? '');
            if ($status === 'success') {
                return [
                    'time' => $time,
                    'status' => 'آپدیت شد',
                    'message' => "پست #{$post_id} آپدیت شد.",
                    'details' => '',
                ];
            }
            return [
                'time' => $time,
                'status' => 'آپدیت نشد',
                'message' => "پست #{$post_id} آپدیت نشد.",
                'details' => (string) ($context['message'] ?? ''),
            ];
        }
        if ($event === 'manual_cron_trigger_requested') {
            return [
                'time' => $time,
                'status' => 'دستی',
                'message' => 'اجرای دستی کران از پنل انجام شد.',
                'details' => '',
            ];
        }
        if ($event === 'negative_alert_sent') {
            return [
                'time' => $time,
                'status' => 'هشدار',
                'message' => 'ایمیل هشدار منفی ارسال شد.',
                'details' => '',
            ];
        }
        if ($event === 'custom_rule_finished') {
            $rule_id = (string) ($context['rule_id'] ?? '');
            $rule_name = trim((string) ($context['rule_name'] ?? ''));
            $label = $rule_name !== '' ? $rule_name : $rule_id;
            $post_type = (string) ($context['post_type'] ?? 'post');
            return [
                'time' => $time,
                'status' => 'موفق',
                'message' => "کران اختصاصی {$label} اجرا شد.",
                'details' => sprintf(
                    'نوع محتوا: %s | آپدیت شد: %d | آپدیت نشد: %d | نادیده‌گرفته: %d',
                    $post_type,
                    (int) ($context['success'] ?? 0),
                    (int) ($context['failed'] ?? 0),
                    (int) ($context['ignored'] ?? 0)
                ),
            ];
        }
        if ($event === 'custom_rule_skipped') {
            return [
                'time' => $time,
                'status' => 'اجرا نشد',
                'message' => 'کران اختصاصی اجرا نشد.',
                'details' => (string) ($context['reason'] ?? 'علت مشخص نشد.'),
            ];
        }
        return null;
    }

    private function get_recent_analyses($limit = 8)
    {
        global $wpdb;
        $table_name = AICA_Database::table_name();
        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT post_id, short_summary, summary, sentiment_positive, sentiment_neutral, sentiment_negative, topics, updated_at FROM {$table_name} ORDER BY updated_at DESC LIMIT %d", max(1, (int) $limit)),
            ARRAY_A
        );

        if (!is_array($rows)) {
            return [];
        }

        foreach ($rows as &$row) {
            $row['topics'] = json_decode($row['topics'], true) ?: [];
        }
        unset($row);

        return $rows;
    }

    private function get_analysis_filters_from_request()
    {
        return [
            'post_id' => absint($_GET['analysis_post_id'] ?? 0),
            'q' => sanitize_text_field($_GET['analysis_q'] ?? ''),
            'date_from' => sanitize_text_field($_GET['analysis_date_from'] ?? ''),
            'date_to' => sanitize_text_field($_GET['analysis_date_to'] ?? ''),
            'page' => max(1, absint($_GET['analysis_page'] ?? 1)),
            'per_page' => 8,
        ];
    }

    private function get_recent_analyses_paginated($filters)
    {
        global $wpdb;
        $table_name = AICA_Database::table_name();
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['post_id'])) {
            $where[] = 'post_id = %d';
            $params[] = (int) $filters['post_id'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(short_summary LIKE %s OR summary LIKE %s)';
            $like = '%' . $wpdb->esc_like($filters['q']) . '%';
            $params[] = $like;
            $params[] = $like;
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'DATE(updated_at) >= %s';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'DATE(updated_at) <= %s';
            $params[] = $filters['date_to'];
        }

        $where_sql = implode(' AND ', $where);
        $count_sql = "SELECT COUNT(*) FROM {$table_name} WHERE {$where_sql}";
        $total = (int) $wpdb->get_var($params ? $wpdb->prepare($count_sql, $params) : $count_sql);

        $per_page = max(1, (int) ($filters['per_page'] ?? 8));
        $current_page = max(1, (int) ($filters['page'] ?? 1));
        $total_pages = max(1, (int) ceil($total / $per_page));
        if ($current_page > $total_pages) {
            $current_page = $total_pages;
        }
        $offset = ($current_page - 1) * $per_page;

        $data_sql = "SELECT post_id, short_summary, summary, sentiment_positive, sentiment_neutral, sentiment_negative, topics, updated_at FROM {$table_name} WHERE {$where_sql} ORDER BY updated_at DESC LIMIT %d OFFSET %d";
        $data_params = $params;
        $data_params[] = $per_page;
        $data_params[] = $offset;
        $rows = $wpdb->get_results($wpdb->prepare($data_sql, $data_params), ARRAY_A);
        $items = is_array($rows) ? $rows : [];
        foreach ($items as &$row) {
            $row['topics'] = json_decode($row['topics'], true) ?: [];
        }
        unset($row);

        return [
            'items' => $items,
            'pagination' => [
                'total' => $total,
                'per_page' => $per_page,
                'current_page' => $current_page,
                'total_pages' => $total_pages,
            ],
        ];
    }

    private function get_sentiment_topic_headlines($post_id, $limit = 8)
    {
        $comments = get_comments([
            'post_id' => (int) $post_id,
            'status' => 'approve',
            'type__not_in' => ['pingback', 'trackback'],
            'number' => 1000,
        ]);

        $buckets = ['positive' => [], 'negative' => []];
        foreach ($comments as $comment) {
            $sentiment = get_comment_meta($comment->comment_ID, 'aica_sentiment', true);
            $topic = trim((string) get_comment_meta($comment->comment_ID, 'aica_topic', true));
            if (($sentiment !== 'positive' && $sentiment !== 'negative') || $topic === '') {
                continue;
            }
            if (!isset($buckets[$sentiment][$topic])) {
                $buckets[$sentiment][$topic] = 0;
            }
            $buckets[$sentiment][$topic]++;
        }

        foreach (['positive', 'negative'] as $key) {
            arsort($buckets[$key]);
            $buckets[$key] = array_slice(array_keys($buckets[$key]), 0, max(1, (int) $limit));
        }

        if (empty($buckets['positive']) && empty($buckets['negative'])) {
            $buckets = $this->fallback_sentiment_topics_from_analysis((int) $post_id, (int) $limit);
        }

        return $buckets;
    }

    private function fallback_sentiment_topics_from_analysis($post_id, $limit = 8)
    {
        $analysis = AICA_Database::get_analysis((int) $post_id);
        if (!$analysis) {
            return ['positive' => [], 'negative' => []];
        }

        $topic_summaries = is_array($analysis['topic_summaries']) ? $analysis['topic_summaries'] : [];
        $topics = is_array($analysis['topics']) ? $analysis['topics'] : [];
        $positive = [];
        $negative = [];
        $negative_markers = ['بد', 'ضعیف', 'مشکل', 'تاخیر', 'دیر', 'گرون', 'گران', 'نارضایتی', 'کند', 'خراب', 'منفی'];
        $positive_markers = ['خوب', 'عالی', 'سریع', 'مناسب', 'راضی', 'رضایت', 'باکیفیت', 'عالیه', 'مثبت', 'پیشنهاد'];

        foreach ($topics as $topic) {
            $topic_text = trim((string) $topic);
            if ($topic_text === '') {
                continue;
            }
            if (mb_strpos($topic_text, 'مثبت:') === 0) {
                $positive[] = trim(mb_substr($topic_text, mb_strlen('مثبت:')));
                continue;
            }
            if (mb_strpos($topic_text, 'منفی:') === 0) {
                $negative[] = trim(mb_substr($topic_text, mb_strlen('منفی:')));
                continue;
            }
        }

        foreach ($topic_summaries as $topic => $summary) {
            $topic_text = trim((string) $topic);
            if (mb_strpos($topic_text, 'مثبت:') === 0) {
                $positive[] = trim(mb_substr($topic_text, mb_strlen('مثبت:')));
                continue;
            }
            if (mb_strpos($topic_text, 'منفی:') === 0) {
                $negative[] = trim(mb_substr($topic_text, mb_strlen('منفی:')));
                continue;
            }
            $text = mb_strtolower((string) $summary);
            $is_negative = false;
            foreach ($negative_markers as $marker) {
                if (mb_strpos($text, $marker) !== false) {
                    $negative[] = (string) $topic;
                    $is_negative = true;
                    break;
                }
            }
            if ($is_negative) {
                continue;
            }
            foreach ($positive_markers as $marker) {
                if (mb_strpos($text, $marker) !== false) {
                    $positive[] = (string) $topic;
                    break;
                }
            }
        }

        foreach ($topics as $topic) {
            $topic_text = trim((string) $topic);
            if ($topic_text === '' || mb_strpos($topic_text, 'مثبت:') === 0 || mb_strpos($topic_text, 'منفی:') === 0) {
                continue;
            }
            if (count($positive) < max(1, (int) $limit) && !in_array($topic_text, $positive, true) && !in_array($topic_text, $negative, true)) {
                $positive[] = $topic_text;
            }
        }

        return [
            'positive' => array_slice(array_values(array_unique(array_filter($positive))), 0, max(1, (int) $limit)),
            'negative' => array_slice(array_values(array_unique(array_filter($negative))), 0, max(1, (int) $limit)),
        ];
    }

    public function handle_manual_analysis()
    {
        if (!current_user_can('manage_options')) {
            wp_die('دسترسی غیرمجاز');
        }

        check_admin_referer('aica_manual_analysis_nonce', 'aica_nonce');

        $post_id = absint($_POST['post_id'] ?? 0);
        $mode = sanitize_key($_POST['manual_filter_mode'] ?? 'none');
        $manual_post_type = sanitize_key($_POST['manual_post_type'] ?? '');
        $processed_posts = [];
        $failed_posts = [];
        $total_comments = 0;

        if ($mode === 'post_ids') {
            $raw_ids = sanitize_text_field($_POST['manual_post_ids'] ?? '');
            $ids = array_filter(array_map('absint', preg_split('/[\s,]+/', $raw_ids)));
            if (empty($ids)) {
                wp_safe_redirect(admin_url('admin.php?page=aica-settings&analysis=error&message=' . rawurlencode('شناسه‌های معتبری وارد نشده است.')));
                exit;
            }
            $target_post_ids = array_values(array_unique($ids));
        } elseif ($mode === 'post_type_latest') {
            $limit = max(1, min(200, absint($_POST['manual_limit'] ?? 10)));
            $post_types_query = $manual_post_type ? [$manual_post_type] : ['post', 'product'];
            $posts = get_posts([
                'post_type' => $post_types_query,
                'post_status' => 'publish',
                'numberposts' => $limit,
                'orderby' => 'date',
                'order' => 'DESC',
                'fields' => 'ids',
            ]);
            if (empty($posts)) {
                wp_safe_redirect(admin_url('admin.php?page=aica-settings&analysis=error&message=' . rawurlencode('موردی برای تحلیل پیدا نشد.')));
                exit;
            }
            $target_post_ids = array_map('absint', $posts);
        } else {
            if (!$post_id) {
                wp_safe_redirect(admin_url('admin.php?page=aica-settings&analysis=error&message=' . rawurlencode('شناسه پست وارد نشده است.')));
                exit;
            }
            $post = get_post($post_id);
            if (!$post || ($manual_post_type && $post->post_type !== $manual_post_type)) {
                wp_safe_redirect(admin_url('admin.php?page=aica-settings&analysis=error&message=' . rawurlencode('پست انتخابی معتبر نیست یا با نوع پست فیلتر شده همخوانی ندارد.')));
                exit;
            }
            $target_post_ids = [$post_id];
        }

        foreach ($target_post_ids as $target_post_id) {
            $comments_count = $this->get_approved_comments_count($target_post_id);
            $result = $this->analyzer->analyze_post_comments($target_post_id, true);
            if (is_wp_error($result)) {
                $failed_posts[] = $target_post_id;
                continue;
            }
            $processed_posts[] = $target_post_id;
            $total_comments += $comments_count;
        }

        if (empty($processed_posts)) {
            wp_safe_redirect(admin_url('admin.php?page=aica-settings&analysis=error&message=' . rawurlencode('هیچ تحلیلی با موفقیت انجام نشد.')));
            exit;
        }

        $message = sprintf('پست‌های موفق: %d | ناموفق: %d', count($processed_posts), count($failed_posts));
        $last_post_id = (int) end($processed_posts);
        wp_safe_redirect(admin_url("options-general.php?page=aica-settings&analysis=done&post_id={$last_post_id}&comments={$total_comments}&message=" . rawurlencode($message)));
        exit;
    }

    public function handle_quick_flush_cache()
    {
        if (!current_user_can('manage_options')) {
            wp_die('دسترسی غیرمجاز');
        }

        check_admin_referer('aica_quick_flush_cache', 'aica_quick_nonce');
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_aica_analysis_%' OR option_name LIKE '_transient_timeout_aica_analysis_%'");
        wp_safe_redirect(admin_url('admin.php?page=aica-settings&tab=overview'));
        exit;
    }

    public function handle_quick_run_cron()
    {
        if (!current_user_can('manage_options')) {
            wp_die('دسترسی غیرمجاز');
        }

        check_admin_referer('aica_quick_run_cron', 'aica_quick_nonce');
        AICA_Cron::add_log('manual_cron_trigger_requested', 'info', ['source' => 'admin_quick_action']);
        if ($this->analyzer) {
            $cron = new AICA_Cron($this->analyzer);
            $cron->run_scheduled_analysis();
            $rules = (array) get_option('aica_custom_cron_rules', []);
            foreach ($rules as $rule) {
                if (empty($rule['enabled'])) {
                    continue;
                }
                $rule_id = sanitize_key((string) ($rule['id'] ?? ''));
                if ($rule_id === '') {
                    continue;
                }
                $cron->run_rule_analysis($rule_id);
            }
        } else {
            AICA_Cron::add_log('manual_cron_trigger_failed', 'warning', ['reason' => 'analyzer_missing']);
        }
        wp_safe_redirect(admin_url('admin.php?page=aica-settings&tab=custom-cron'));
        exit;
    }

    public function handle_save_custom_cron_rule()
    {
        if (!current_user_can('manage_options')) {
            wp_die('دسترسی غیرمجاز');
        }
        check_admin_referer('aica_save_custom_cron_rule', 'aica_custom_cron_nonce');

        $name = sanitize_text_field($_POST['rule_name'] ?? '');
        $selection_mode = sanitize_key($_POST['selection_mode'] ?? 'post_type_latest');
        $post_type = sanitize_key($_POST['rule_post_type'] ?? '');
        $post_id = absint($_POST['rule_post_id'] ?? 0);
        $post_ids = sanitize_text_field($_POST['rule_post_ids'] ?? '');
        $new_comments_threshold = max(1, absint($_POST['rule_new_comments_threshold'] ?? 10));
        $max_posts = max(1, absint($_POST['rule_max_posts'] ?? 20));
        $min_comments_to_analyze = max(1, absint($_POST['rule_min_comments_to_analyze'] ?? 1));
        $enabled = isset($_POST['rule_enabled']) ? 1 : 0;

        if ($name === '') {
            wp_safe_redirect(admin_url('admin.php?page=aica-settings&tab=custom-cron'));
            exit;
        }

        $target_summary = 'آخرین موارد';
        if ($selection_mode === 'none') {
            $target_summary = $post_id ? ('پست #' . $post_id) : 'پست تکی (بدون شناسه)';
        } elseif ($selection_mode === 'post_ids') {
            $target_summary = $post_ids !== '' ? ('شناسه‌ها: ' . $post_ids) : 'چند شناسه (خالی)';
        } elseif ($selection_mode === 'post_type_latest') {
            $target_summary = ($post_type ? $post_type : 'همه') . ' | آخرین ' . $max_posts . ' مورد';
        }

        $rules = (array) get_option('aica_custom_cron_rules', []);
        $rules[] = [
            'id' => 'rule_' . wp_generate_password(10, false, false),
            'name' => $name,
            'selection_mode' => $selection_mode,
            'manual_post_type' => $post_type,
            'post_id' => $post_id,
            'post_ids' => $post_ids,
            'manual_limit' => $max_posts,
            'post_type' => $post_type,
            'new_comments_threshold' => $new_comments_threshold,
            'max_posts' => $max_posts,
            'min_comments_to_analyze' => $min_comments_to_analyze,
            'rule_target_summary' => $target_summary,
            'enabled' => $enabled,
        ];
        update_option('aica_custom_cron_rules', array_values($rules), false);
        AICA_Cron::add_log('custom_rule_saved', 'success', ['name' => $name, 'post_type' => $post_type, 'new_comments_threshold' => $new_comments_threshold]);
        wp_safe_redirect(admin_url('admin.php?page=aica-settings&tab=custom-cron'));
        exit;
    }

    public function handle_delete_custom_cron_rule()
    {
        if (!current_user_can('manage_options')) {
            wp_die('دسترسی غیرمجاز');
        }
        check_admin_referer('aica_delete_custom_cron_rule', 'aica_custom_cron_nonce');

        $rule_id = sanitize_key($_POST['rule_id'] ?? '');
        $rules = (array) get_option('aica_custom_cron_rules', []);
        $rules = array_values(array_filter($rules, function ($rule) use ($rule_id) {
            return sanitize_key((string) ($rule['id'] ?? '')) !== $rule_id;
        }));
        update_option('aica_custom_cron_rules', $rules, false);
        AICA_Cron::add_log('custom_rule_deleted', 'info', ['rule_id' => $rule_id]);
        wp_safe_redirect(admin_url('admin.php?page=aica-settings&tab=custom-cron'));
        exit;
    }

    private function get_approved_comments_count($post_id)
    {
        return (int) get_comments([
            'post_id' => (int) $post_id,
            'status' => 'approve',
            'type__not_in' => ['pingback', 'trackback'],
            'count' => true,
        ]);
    }

    public function ajax_comment_count()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'دسترسی غیرمجاز']);
        }

        $post_id = absint($_POST['post_id'] ?? 0);
        if (!$post_id) {
            wp_send_json_error(['message' => 'شناسه پست معتبر نیست.']);
        }

        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error(['message' => 'پستی با این شناسه پیدا نشد.']);
        }

        wp_send_json_success(['count' => $this->get_approved_comments_count($post_id)]);
    }

    public function ajax_test_api_connection()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'دسترسی غیرمجاز']);
        }

        check_ajax_referer('aica_test_api_connection_nonce', 'nonce');
        $api_key = trim((string) get_option('aica_api_key', ''));
        if ($api_key === '') {
            wp_send_json_error(['message' => 'کلید API تنظیم نشده است.']);
        }

        $api_base_url = $this->sanitize_api_base_url((string) get_option('aica_api_base_url', 'https://api.openai.com/v1/chat/completions'));
        $model = sanitize_text_field((string) get_option('aica_model', 'gpt-4o-mini'));

        $body = [
            'model' => $model,
            'max_tokens' => 5,
            'messages' => [
                ['role' => 'user', 'content' => 'سلام'],
            ],
        ];

        $response = wp_remote_post($api_base_url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'body' => wp_json_encode($body, JSON_UNESCAPED_UNICODE),
            'timeout' => 25,
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => 'خطای ارتباط: ' . $response->get_error_message()]);
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $raw = json_decode((string) wp_remote_retrieve_body($response), true);
        if ($status_code >= 200 && $status_code < 300) {
            wp_send_json_success(['message' => 'اتصال موفق بود. (HTTP ' . $status_code . ')']);
        }

        $error_message = $raw['error']['message'] ?? ('اتصال ناموفق بود. (HTTP ' . $status_code . ')');
        wp_send_json_error(['message' => sanitize_text_field($error_message)]);
    }

    public function ajax_prepare_manual_analysis()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'دسترسی غیرمجاز']);
        }

        check_ajax_referer('aica_manual_analysis_ajax', 'nonce');

        $post_id = absint($_POST['post_id'] ?? 0);
        $mode = sanitize_key($_POST['manual_filter_mode'] ?? 'none');
        $manual_post_type = sanitize_key($_POST['manual_post_type'] ?? '');

        if ($mode === 'post_ids') {
            $raw_ids = sanitize_text_field($_POST['manual_post_ids'] ?? '');
            $ids = array_filter(array_map('absint', preg_split('/[\s,]+/', $raw_ids)));
            $target_post_ids = array_values(array_unique($ids));
        } elseif ($mode === 'post_type_latest') {
            $limit = max(1, min(200, absint($_POST['manual_limit'] ?? 10)));
            $post_types_query = $manual_post_type ? [$manual_post_type] : ['post', 'product'];
            $posts = get_posts([
                'post_type' => $post_types_query,
                'post_status' => 'publish',
                'numberposts' => $limit,
                'orderby' => 'date',
                'order' => 'DESC',
                'fields' => 'ids',
            ]);
            $target_post_ids = array_map('absint', (array) $posts);
        } else {
            $target_post_ids = $post_id ? [$post_id] : [];
        }

        if (empty($target_post_ids)) {
            wp_send_json_error(['message' => 'موردی برای تحلیل پیدا نشد.']);
        }

        $items = [];
        foreach ($target_post_ids as $target_post_id) {
            $post = get_post($target_post_id);
            if (!$post) {
                continue;
            }

            $items[] = [
                'post_id' => (int) $target_post_id,
                'post_title' => (string) get_the_title($target_post_id),
                'comments_count' => $this->get_approved_comments_count($target_post_id),
                'status' => 'pending',
                'status_label' => 'در صف',
                'message' => '',
            ];
        }

        if (empty($items)) {
            wp_send_json_error(['message' => 'هیچ پست معتبری برای تحلیل وجود ندارد.']);
        }

        wp_send_json_success([
            'nonce' => wp_create_nonce('aica_manual_analysis_ajax'),
            'items' => $items,
        ]);
    }

    public function ajax_process_manual_analysis_item()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'دسترسی غیرمجاز']);
        }

        check_ajax_referer('aica_manual_analysis_ajax', 'nonce');
        $post_id = absint($_POST['post_id'] ?? 0);
        if (!$post_id) {
            wp_send_json_error(['message' => 'شناسه پست نامعتبر است.']);
        }

        $comments_count = $this->get_approved_comments_count($post_id);
        $result = $this->analyzer->analyze_post_comments($post_id, true);
        $item = [
            'post_id' => $post_id,
            'post_title' => (string) get_the_title($post_id),
            'comments_count' => $comments_count,
            'status' => 'success',
            'status_label' => 'موفق',
            'message' => 'تحلیل انجام شد.',
        ];

        if (is_wp_error($result)) {
            $error_code = $result->get_error_code();
            if ($error_code === 'aica_below_min_comments') {
                $item['status'] = 'ignored';
                $item['status_label'] = 'نادیده‌گرفته';
            } else {
                $item['status'] = 'failed';
                $item['status_label'] = 'ناموفق';
            }
            $item['message'] = $result->get_error_message();
        }

        wp_send_json_success(['item' => $item]);
    }

    public function register_comment_metabox()
    {
        add_meta_box(
            'aica_comment_reply_box',
            'پاسخ پیشنهادی هوش مصنوعی',
            [$this, 'render_comment_metabox'],
            'comment',
            'normal',
            'default'
        );
    }

    public function render_comment_metabox($comment)
    {
        $nonce = wp_create_nonce('aica_suggest_reply_nonce');
        ?>
        <p>برای دریافت پاسخ پیشنهادی، دکمه زیر را بزنید:</p>
        <button type="button" class="button" id="aica-suggest-reply-btn" data-comment-id="<?php echo esc_attr($comment->comment_ID); ?>" data-nonce="<?php echo esc_attr($nonce); ?>">تولید پاسخ پیشنهادی</button>
        <textarea id="aica-suggested-reply" style="width:100%;margin-top:10px;" rows="5" readonly></textarea>
        <script>
            document.getElementById('aica-suggest-reply-btn').addEventListener('click', function () {
                const commentId = this.dataset.commentId;
                const nonce = this.dataset.nonce;
                const result = document.getElementById('aica-suggested-reply');
                result.value = 'در حال تولید...';

                fetch(ajaxurl, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                    body: new URLSearchParams({
                        action: 'aica_suggest_reply',
                        comment_id: commentId,
                        nonce: nonce
                    })
                })
                .then(r => r.json())
                .then(data => { result.value = data.success ? data.data.reply : data.data.message; });
            });
        </script>
        <?php
    }

    public function ajax_suggest_reply()
    {
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'دسترسی غیرمجاز']);
        }

        check_ajax_referer('aica_suggest_reply_nonce', 'nonce');

        $comment_id = absint($_POST['comment_id'] ?? 0);
        $comment = get_comment($comment_id);

        if (!$comment) {
            wp_send_json_error(['message' => 'کامنت پیدا نشد']);
        }

        $reply = $this->analyzer->generate_suggested_reply($comment->comment_content);

        if (is_wp_error($reply)) {
            wp_send_json_error(['message' => $reply->get_error_message()]);
        }

        wp_send_json_success(['reply' => $reply]);
    }

    public function smart_spam_gate($approved, $commentdata)
    {
        $enabled = (bool) get_option('aica_enable_spam_detection', 1);
        if (!$enabled || empty($commentdata['comment_content'])) {
            return $approved;
        }

        $is_spam = $this->analyzer->should_flag_spam($commentdata['comment_content']);

        if ($is_spam) {
            return 0;
        }

        return $approved;
    }

    public function enrich_comment_meta($comment_id, $comment_approved, $commentdata)
    {
        if ((int) $comment_approved === 0 || empty($commentdata['comment_content'])) {
            return;
        }

        $model = get_option('aica_model', 'gpt-4o-mini');
        $result = (new AICA_AI_Service())->analyze_comments([$commentdata['comment_content']], $model);

        if (is_wp_error($result)) {
            return;
        }

        $sentiment = 'neutral';
        $positive = (float) ($result['sentiment_positive'] ?? 0);
        $negative = (float) ($result['sentiment_negative'] ?? 0);
        if ($positive > $negative && $positive > 40) {
            $sentiment = 'positive';
        } elseif ($negative > $positive && $negative > 40) {
            $sentiment = 'negative';
        }

        update_comment_meta($comment_id, 'aica_sentiment', $sentiment);
        if (!empty($result['topics'][0])) {
            update_comment_meta($comment_id, 'aica_topic', sanitize_text_field($result['topics'][0]));
        }
    }
}
