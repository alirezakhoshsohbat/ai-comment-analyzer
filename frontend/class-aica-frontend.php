<?php

if (!defined('ABSPATH')) {
    exit;
}

class AICA_Frontend
{
    public function register_hooks()
    {
        add_filter('the_content', [$this, 'append_analysis_to_content']);
        add_filter('comments_array', [$this, 'filter_comments'], 10, 2);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_shortcode('aica_analysis', [$this, 'render_shortcode_analysis_box']);
    }

    public function enqueue_assets()
    {
        wp_enqueue_style('aica-frontend', AICA_PLUGIN_URL . 'assets/css/frontend.css', [], AICA_VERSION);
        $dynamic_css = $this->build_dynamic_css_vars();
        if ($dynamic_css !== '') {
            wp_add_inline_style('aica-frontend', $dynamic_css);
        }
        $custom_css = trim((string) get_option('aica_custom_css', ''));
        $custom_css_enabled = (bool) get_option('aica_custom_css_enabled', 1);
        if ($custom_css_enabled && $custom_css !== '') {
            wp_add_inline_style('aica-frontend', $custom_css);
        }

        wp_enqueue_script('jquery');
        wp_add_inline_script('jquery', $this->get_live_build_script(), 'after');
        wp_add_inline_script('jquery', $this->get_faq_accordion_script(), 'after');

        $custom_js_enabled = (bool) get_option('aica_custom_js_enabled', 0);
        $custom_js = trim((string) get_option('aica_custom_js', ''));
        if ($custom_js_enabled && $custom_js !== '') {
            wp_add_inline_script('jquery', $custom_js, 'after');
        }
    }

    private function get_live_build_script()
    {
        return "(function(){const runAll=()=>{document.querySelectorAll('[data-aica-toggle]').forEach(btn=>{btn.addEventListener('click',function(){const id=this.getAttribute('data-aica-toggle');const target=document.getElementById('aica-'+id);if(!target)return;target.classList.toggle('is-hidden');if(!target.classList.contains('is-hidden')){target.classList.add('aica-live-init');animateBox(target);if(this.getAttribute('data-aica-disable')==='1'){this.disabled=true;this.classList.add('is-disabled');const disabledText=this.getAttribute('data-aica-disabled-text');if(disabledText){this.textContent=disabledText;}}} });});const boxes=document.querySelectorAll('.aica-box.aica-live-init:not(.aica-toggle-target)');boxes.forEach(animateBox);};const pause=120;const typeNode=(node,done)=>{const text=(node.textContent||'').trim();if(!text){done();return;}node.textContent='';node.classList.add('aica-typing');let i=0;const step=()=>{if(i<=text.length){node.textContent=text.slice(0,i);i++;requestAnimationFrame(step);}else{node.classList.remove('aica-typing');done();}};setTimeout(step,20);};const animateBox=(box)=>{if(!box||box.dataset.aicaAnimated==='1')return;box.dataset.aicaAnimated='1';const head=box.querySelector('.aica-head');if(!head)return;const items=[...box.querySelectorAll('.aica-build-item')].filter(el=>el.textContent.trim()!=='');items.forEach(el=>{el.classList.add('aica-queued');const guessed=(el.tagName==='LI')?'flex':'block';el.dataset.aicaDisplay=(window.getComputedStyle(el).display==='none'?guessed:window.getComputedStyle(el).display);el.style.display='none';});box.classList.add('aica-collapsed');box.style.maxHeight=(Math.ceil(head.getBoundingClientRect().height)+18)+'px';let idx=0;const reveal=(el)=>{el.style.display=el.dataset.aicaDisplay||'block';el.classList.remove('aica-queued');};const next=()=>{if(idx>=items.length){box.classList.remove('aica-collapsed');box.style.maxHeight='';box.classList.remove('aica-live-init');return;}const el=items[idx];reveal(el);box.style.maxHeight=(box.scrollHeight+30)+'px';if(el.classList.contains('aica-tag')||el.tagName==='LI'){setTimeout(()=>{idx++;setTimeout(next,pause);},60);return;}typeNode(el,()=>{idx++;setTimeout(next,pause);});};setTimeout(next,120);};if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',runAll);}else{runAll();}})();";
    }

    private function get_faq_accordion_script()
    {
        return "(function(){const runFaq=()=>{const updatePanel=(trigger,panel)=>{const expanded=trigger.getAttribute('aria-expanded')==='true';trigger.setAttribute('aria-expanded',expanded?'false':'true');panel.setAttribute('aria-hidden',expanded?'true':'false');if(expanded){panel.classList.remove('is-open');panel.style.maxHeight='0';}else{panel.classList.add('is-open');panel.style.maxHeight=panel.scrollHeight+'px';}};document.querySelectorAll('[data-aica-toggle-faq]').forEach(btn=>{const panel=document.getElementById(btn.getAttribute('data-aica-toggle-faq'));if(!panel)return;btn.addEventListener('click',()=>updatePanel(btn,panel));});};if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',runFaq);}else{runFaq();}})();";
    }

    private function build_dynamic_css_vars()
    {
        $max_width = max(320, (int) get_option('aica_box_max_width', 980));
        $title_size = max(14, (int) get_option('aica_box_title_size', 20));
        $font_size = max(12, (int) get_option('aica_box_font_size', 14));
        $line_height = max(1.2, (float) get_option('aica_box_line_height', 1.8));
        $border_width = max(0, (int) get_option('aica_box_border_width', 1));
        $border_color = get_option('aica_box_border_color', '#dcdcdc');
        $box_shadow = (bool) get_option('aica_box_shadow', 1) ? '0 12px 32px rgba(15,23,42,.12)' : 'none';
        $button_bg = get_option('aica_button_bg_color', '#2563eb');
        $button_text = get_option('aica_button_text_color', '#ffffff');
        $button_radius = max(8, (int) get_option('aica_button_radius', 14));

        // FAQ accordion styling options
        $faq_bg = get_option('aica_faq_bg_color', '');
        $faq_border_color = get_option('aica_faq_border_color', '');
        $faq_border_width = max(0, (int) get_option('aica_faq_border_width', 0));
        $faq_border_radius = max(0, (int) get_option('aica_faq_border_radius', 18));
        $faq_padding = max(0, (int) get_option('aica_faq_padding', 0));

        // Topics panel styling options
        $topics_bg = get_option('aica_topics_panel_bg_color', '');
        $topics_border_color = get_option('aica_topics_panel_border_color', '');
        $topics_border_width = max(0, (int) get_option('aica_topics_panel_border_width', 0));
        $topics_border_radius = max(0, (int) get_option('aica_topics_panel_border_radius', 18));
        $topics_padding = max(0, (int) get_option('aica_topics_panel_padding', 0));

        // Topic pill styling options
        $topic_pill_bg = get_option('aica_topic_pill_bg_color', '#fff');
        $topic_pill_text = get_option('aica_topic_pill_text_color', '#0f172a');
        $topic_pill_border_color = get_option('aica_topic_pill_border_color', 'rgba(79,70,229,.2)');
        $topic_pill_border_width = max(0, (int) get_option('aica_topic_pill_border_width', 1));
        $topic_pill_border_radius = max(0, (int) get_option('aica_topic_pill_border_radius', 12));

        // Topic card styling options
        $topic_card_bg = get_option('aica_topic_card_bg_color', '#fff');
        $topic_card_text = get_option('aica_topic_card_text_color', '#0f172a');
        $topic_card_border_color = get_option('aica_topic_card_border_color', 'rgba(79,70,229,.2)');
        $topic_card_border_width = max(0, (int) get_option('aica_topic_card_border_width', 1));
        $topic_card_border_radius = max(0, (int) get_option('aica_topic_card_border_radius', 12));
        $topic_card_padding = max(0, (int) get_option('aica_topic_card_padding', 12));

        return '.aica-box{max-width:' . (int) $max_width . 'px;font-size:' . (int) $font_size . 'px;line-height:' . (float) $line_height . ';border-width:' . (int) $border_width . 'px;border-color:' . esc_attr($border_color) . ';box-shadow:' . $box_shadow . ';}' .
            '.aica-box h3{font-size:' . (int) $title_size . 'px;}' .
            '.aica-toggle-btn{background:' . esc_attr($button_bg) . ';color:' . esc_attr($button_text) . ';border-radius:' . (int) $button_radius . 'px;}' .
            '--aica-topic-pill-bg:' . esc_attr($topic_pill_bg) . ';' .
            '--aica-topic-pill-text:' . esc_attr($topic_pill_text) . ';' .
            '--aica-topic-pill-border-color:' . esc_attr($topic_pill_border_color) . ';' .
            '--aica-topic-pill-border-width:' . $topic_pill_border_width . 'px;' .
            '--aica-topic-pill-border-radius:' . $topic_pill_border_radius . 'px;' .
            '--aica-faq-bg:' . esc_attr($faq_bg) . ';' .
            '--aica-faq-border-color:' . esc_attr($faq_border_color) . ';' .
            '--aica-faq-border-width:' . $faq_border_width . 'px;' .
            '--aica-faq-border-radius:' . $faq_border_radius . 'px;' .
            '--aica-topics-panel-padding:' . $topics_padding . 'px;' .
            '--aica-topic-card-bg:' . esc_attr($topic_card_bg) . ';' .
            '--aica-topic-card-text:' . esc_attr($topic_card_text) . ';' .
            '--aica-topic-card-border-color:' . esc_attr($topic_card_border_color) . ';' .
            '--aica-topic-card-border-width:' . $topic_card_border_width . 'px;' .
            '--aica-topic-card-border-radius:' . $topic_card_border_radius . 'px;' .
            '--aica-topic-card-padding:' . $topic_card_padding . 'px;' .
            '--aica-topics-panel-bg:' . esc_attr($topics_bg) . ';' .
            '--aica-topics-panel-border-color:' . esc_attr($topics_border_color) . ';' .
            '--aica-topics-panel-border-width:' . $topics_border_width . 'px;' .
            '--aica-topics-panel-border-radius:' . $topics_border_radius . 'px;' .
            '--aica-topics-panel-padding:' . $topics_padding . 'px;';
    }

    public function append_analysis_to_content($content)
    {
        if (!is_singular() || !in_the_loop() || !is_main_query()) {
            return $content;
        }

        if (!(bool) get_option('aica_frontend_enabled', 1)) {
            return $content;
        }

        $position = get_option('aica_frontend_position', 'after');
        if ($position === 'shortcode') {
            return $content;
        }

        $analysis_html = $this->get_analysis_html(get_the_ID());
        if ($analysis_html === '') {
            return $content;
        }

        return $position === 'before' ? ($analysis_html . $content) : ($content . $analysis_html);
    }

    public function render_shortcode_analysis_box($atts = [])
    {
        if (!(bool) get_option('aica_frontend_enabled', 1)) {
            return '';
        }

        if (get_option('aica_frontend_position', 'after') !== 'shortcode') {
            return '';
        }

        $post_id = absint($atts['post_id'] ?? get_the_ID());
        if (!$post_id) {
            $post_id = get_queried_object_id();
        }

        if (!$post_id) {
            return '';
        }

        return $this->get_analysis_html($post_id);
    }

    private function get_analysis_html($post_id)
    {
        $analysis = AICA_Database::get_analysis((int) $post_id);

        if (!$analysis) {
            return '';
        }

        $topics = $analysis['topics'];
        $topic_summaries = is_array($analysis['topic_summaries']) ? $analysis['topic_summaries'] : [];
        $faq_items = is_array($analysis['faq']) ? $analysis['faq'] : [];
        $points_limit = max(2, (int) get_option('aica_points_limit', 6));
        $sentiment_topics = $this->get_sentiment_topic_headlines((int) $post_id, $points_limit);
        $theme = get_option('aica_theme', 'modern-dark');
        $accent = get_option('aica_accent_color', '#4f46e5');
        $radius = max(0, (int) get_option('aica_box_border_radius', 16));
        $padding = max(8, (int) get_option('aica_box_padding', 18));
        $glass = (bool) get_option('aica_glass_effect', 1);
        $show_icon = (bool) get_option('aica_show_header_icon', 1);
        $title = get_option('aica_box_title', 'تحلیل هوشمند نظرات کاربران');
        $subtitle = get_option('aica_box_subtitle', 'خلاصه‌ای سریع از حال‌وهوای دیدگاه‌ها');
        $show_short_summary = (bool) get_option('aica_show_short_summary', 1);
        $show_summary = (bool) get_option('aica_show_summary', 1);
        $show_positive_points = (bool) get_option('aica_show_positive_points', 1);
        $show_negative_points = (bool) get_option('aica_show_negative_points', 1);
        $toggle_enabled = (bool) get_option('aica_enable_toggle_button', 0);
        $toggle_text = get_option('aica_button_text', 'خلاصه نظرات با AI');
        $toggle_icon = get_option('aica_button_icon', '🤖');
        $button_style = get_option('aica_button_style', 'solid');
        $button_align = get_option('aica_button_align', 'right');
        $disable_after_click = (bool) get_option('aica_button_disable_after_click', 1);
        $disabled_text = get_option('aica_button_disabled_text', 'خلاصه بارگذاری شد');
        $point_style = get_option('aica_point_style', 'card');
        $point_icon_positive = get_option('aica_point_icon_positive', '🟢');
        $point_icon_negative = get_option('aica_point_icon_negative', '🔴');
        $point_bg_positive = get_option('aica_point_bg_positive', '#dcfce7');
        $point_bg_negative = get_option('aica_point_bg_negative', '#fee2e2');
        $point_text_color = get_option('aica_point_text_color', '#0f172a');
        $pill_bg = get_option('aica_topic_pill_bg_color', '#fff');
        $pill_text = get_option('aica_topic_pill_text_color', '#0f172a');
        $pill_border_color = get_option('aica_topic_pill_border_color', 'rgba(79,70,229,.2)');
        $pill_border_width = max(0, (int) get_option('aica_topic_pill_border_width', 1));
        $pill_border_radius = max(0, (int) get_option('aica_topic_pill_border_radius', 12));
        $topic_card_bg = get_option('aica_topic_card_bg_color', '#fff');
        $topic_card_text = get_option('aica_topic_card_text_color', '#0f172a');
        $topic_card_border_color = get_option('aica_topic_card_border_color', 'rgba(79,70,229,.2)');
        $topic_card_border_width = max(0, (int) get_option('aica_topic_card_border_width', 1));
        $topic_card_border_radius = max(0, (int) get_option('aica_topic_card_border_radius', 12));
        $topic_card_padding = max(0, (int) get_option('aica_topic_card_padding', 12));
        $box_classes = 'aica-box aica-live-init aica-theme-' . sanitize_html_class($theme) . ($glass ? ' aica-glass' : '');
        $box_style = sprintf(
            '--aica-accent:%s;--aica-radius:%dpx;--aica-padding:%dpx;--aica-point-bg-pos:%s;--aica-point-bg-neg:%s;--aica-point-text:%s;--aica-topic-pill-bg:%s;--aica-topic-pill-text:%s;--aica-topic-pill-border-color:%s;--aica-topic-pill-border-width:%dpx;--aica-topic-pill-border-radius:%dpx;--aica-topic-card-bg:%s;--aica-topic-card-text:%s;--aica-topic-card-border-color:%s;--aica-topic-card-border-width:%dpx;--aica-topic-card-border-radius:%dpx;--aica-topic-card-padding:%dpx;',
            esc_attr($accent),
            (int) $radius,
            (int) $padding,
            esc_attr($point_bg_positive),
            esc_attr($point_bg_negative),
            esc_attr($point_text_color),
            esc_attr($pill_bg),
            esc_attr($pill_text),
            esc_attr($pill_border_color),
            (int) $pill_border_width,
            (int) $pill_border_radius,
            esc_attr($topic_card_bg),
            esc_attr($topic_card_text),
            esc_attr($topic_card_border_color),
            (int) $topic_card_border_width,
            (int) $topic_card_border_radius,
            (int) $topic_card_padding
        );
        ob_start();
        ?>
        <?php if ($toggle_enabled) : ?>
            <div class="aica-toggle-wrap aica-align-<?php echo esc_attr(sanitize_html_class($button_align)); ?>">
            <button type="button" class="aica-toggle-btn aica-btn-<?php echo esc_attr(sanitize_html_class($button_style)); ?>" data-aica-toggle="box-<?php echo esc_attr((int) $post_id); ?>" data-aica-disable="<?php echo esc_attr($disable_after_click ? '1' : '0'); ?>" data-aica-disabled-text="<?php echo esc_attr($disabled_text); ?>">
                <span class="aica-btn-icon"><?php echo esc_html($toggle_icon); ?></span>
                <span><?php echo esc_html($toggle_text); ?></span>
            </button>
            </div>
        <?php endif; ?>
        <div id="aica-box-<?php echo esc_attr((int) $post_id); ?>" class="<?php echo esc_attr($box_classes . ' aica-point-style-' . sanitize_html_class($point_style) . ($toggle_enabled ? ' aica-toggle-target is-hidden' : '')); ?>" style="<?php echo esc_attr($box_style); ?>">
            <div class="aica-head">
                <?php if ($show_icon) : ?><span class="aica-head-icon">✦</span><?php endif; ?>
                <div>
                    <h3><?php echo esc_html($title); ?></h3>
                    <p class="aica-subtitle"><?php echo esc_html($subtitle); ?></p>
                </div>
            </div>
            <?php if ($show_short_summary) : ?>
                <p class="aica-build-item"><strong>نظر کلی:</strong> <?php echo esc_html($analysis['short_summary']); ?></p>
            <?php endif; ?>
            <?php if ($show_summary) : ?>
                <p class="aica-build-item"><?php echo esc_html($analysis['summary']); ?></p>
            <?php endif; ?>
            <div class="aica-sentiments">
                <?php if ($show_positive_points) : ?>
                    <strong class="aica-build-item">نکات مثبت</strong>
                    <?php if (!empty($sentiment_topics['positive'])) : ?>
                        <ul class="aica-point-list">
                            <?php foreach ($sentiment_topics['positive'] as $topic) : ?>
                                <li class="aica-point-pos aica-build-item"><span class="aica-point-icon"><?php echo esc_html($point_icon_positive); ?></span><span class="aica-point-text"><?php echo esc_html($topic); ?></span></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else : ?>
                        <span class="aica-build-item">داده کافی موجود نیست.</span>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if ($show_negative_points) : ?>
                    <strong class="aica-build-item">نکات منفی</strong>
                    <?php if (!empty($sentiment_topics['negative'])) : ?>
                        <ul class="aica-point-list">
                            <?php foreach ($sentiment_topics['negative'] as $topic) : ?>
                                <li class="aica-point-neg aica-build-item"><span class="aica-point-icon"><?php echo esc_html($point_icon_negative); ?></span><span class="aica-point-text"><?php echo esc_html($topic); ?></span></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else : ?>
                        <span class="aica-build-item">داده کافی موجود نیست.</span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php if ((bool) get_option('aica_show_topics', 1)) : ?>
                <div class="aica-topics-panel aica-build-item">
                    <div class="aica-topics-head aica-build-item">
                        <div>
                            <strong>موضوعات پرتکرار</strong>
                            <p class="aica-topics-subtitle">پر استفاده‌ترین دغدغه‌هایی که مشتریانت درباره آن‌ها نظر داده‌اند</p>
                        </div>
                        <span class="aica-topics-spark" aria-hidden="true"></span>
                    </div>
                    <div class="aica-topics-grid">
                        <?php if (!empty($topics)) : ?>
                            <?php foreach ($topics as $topic) : ?>
                                <div class="aica-topic-card aica-build-item">
                                    <span><?php echo esc_html($topic); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <div class="aica-topic-card aica-placeholder">فعلاً داده‌ای در دسترس نیست.</div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            <?php if ((bool) get_option('aica_show_topic_summaries', 1) && !empty($topic_summaries)) : ?>
                <div class="aica-topic-summaries aica-build-item">
                    <h4 class="aica-build-item">خلاصه هر موضوع</h4>
                    <?php foreach ($topic_summaries as $topic => $topic_summary) : ?>
                        <p class="aica-build-item"><strong><?php echo esc_html($topic); ?>:</strong> <?php echo esc_html($topic_summary); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ((bool) get_option('aica_show_faq', 1) && !empty($faq_items)) : ?>
                <div class="aica-faq-accordion aica-build-item" aria-live="polite">
                    <h4 class="aica-build-item">پرسش‌های پرتکرار کاربران</h4>
                    <div class="aica-accordion">
                        <?php foreach ($faq_items as $index => $item) : ?>
                            <?php $faq_id = 'aica-faq-' . esc_attr((int) $post_id) . '-' . esc_attr((int) $index); ?>
                            <button type="button" class="aica-accordion-trigger" aria-expanded="false" data-aica-toggle-faq="<?php echo esc_attr($faq_id); ?>">
                                <span><?php echo esc_html($item['question'] ?? ''); ?></span>
                                <span class="aica-accordion-icon" aria-hidden="true">+</span>
                            </button>
                            <div id="<?php echo esc_attr($faq_id); ?>" class="aica-accordion-panel" aria-hidden="true">
                                <p><?php echo esc_html($item['answer'] ?? ''); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            <?php if ((bool) get_option('aica_show_sentiment_filter', 1)) : ?>
                <div class="aica-filter">
                    <a href="<?php echo esc_url(add_query_arg('aica_sentiment', 'positive')); ?>">فقط مثبت</a> |
                    <a href="<?php echo esc_url(add_query_arg('aica_sentiment', 'negative')); ?>">فقط منفی</a> |
                    <a href="<?php echo esc_url(remove_query_arg('aica_sentiment')); ?>">همه</a>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private function get_sentiment_topic_headlines($post_id, $limit = 6)
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

    private function fallback_sentiment_topics_from_analysis($post_id, $limit = 6)
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

    public function filter_comments($comments, $post_id)
    {
        $sentiment_filter = sanitize_text_field($_GET['aica_sentiment'] ?? '');
        $topic_filter = sanitize_text_field($_GET['aica_topic'] ?? '');

        if (!$sentiment_filter && !$topic_filter) {
            return $comments;
        }

        return array_filter($comments, function ($comment) use ($sentiment_filter, $topic_filter) {
            $sentiment = get_comment_meta($comment->comment_ID, 'aica_sentiment', true);
            $topic = get_comment_meta($comment->comment_ID, 'aica_topic', true);

            $sentiment_ok = $sentiment_filter ? ($sentiment === $sentiment_filter) : true;
            $topic_ok = $topic_filter ? (stripos((string) $topic, $topic_filter) !== false) : true;

            return $sentiment_ok && $topic_ok;
        });
    }
}
