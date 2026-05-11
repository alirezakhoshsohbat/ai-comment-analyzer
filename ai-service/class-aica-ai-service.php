<?php

if (!defined('ABSPATH')) {
    exit;
}

class AICA_AI_Service
{
    private $default_api_base = 'https://api.openai.com/v1/chat/completions';
    private $default_analysis_prompt = "
تو تحلیل‌گر حرفه‌ای نظرات کاربران فروشگاه اینترنتی هستی.
برای نظرات زیر تحلیل بساز و خروجی را فقط به صورت JSON معتبر برگردان.

نام محصول:
{{product_name}}

وظایف:
- تحلیل کوتاه، دقیق، غیرتکراری و قابل‌فهم بنویس.
- از اضافه‌گویی، توضیحات تکراری و جمله‌های پرکننده کاملاً جلوگیری کن.
- از عبارت‌های کلی و مبهم پرهیز کن (مثل «تجربه خرید خوب بود»، «قیمت خوب بود»).
- نکات مثبت/منفی را به صورت دقیق و معنادار بنویس (مثل «قیمت نسبت به کیفیت مناسب است»).
- موارد تکراری را ادغام کن و هر نکته را فقط یک‌بار بگو.
- فقط موضوعاتی را بیاور که در نظرات شواهد کافی دارند.
- اگر نظرها متناقض بود، در summary با «برخی کاربران...» و «بعضی دیگر...» جمع‌بندی کن.

کلیدهای لازم:
summary (حداکثر 120 کلمه، شامل نام محصول)
short_summary (یک جمله کوتاه از حال‌وهوای کلی، شامل نام محصول)
sentiment_positive (عدد درصد)
sentiment_neutral (عدد درصد)
sentiment_negative (عدد درصد)
topics (آرایه رشته از نکات مشخص و معنادار؛ نه کلمات کلی)
topic_summaries (آبجکت: هر topic => توضیح کوتاه و دقیق)
faq (آرایه آبجکت با کلیدهای question و answer)
spam_probability (عدد بین 0 تا 1)

قواعد topics:
- بین 3 تا 6 مورد برگردان.
- هر مورد باید عبارت کامل و روشن باشد، نه تک‌کلمه مبهم.
- اگر نکته مثبت است با «مثبت:» شروع شود.
- اگر نکته منفی است با «منفی:» شروع شود.
- مثال درست: «مثبت: قیمت نسبت به کیفیت مناسب است»
- مثال نادرست: «قیمت»، «تجربه خرید»، «کیفیت»
- هر topic حداکثر 12 کلمه باشد.

نظرات:
{{comments}}
";


    private function get_api_base_url()
    {
        $configured = trim((string) get_option('aica_api_base_url', $this->default_api_base));
        $normalized = $this->normalize_api_base_url($configured);

        return !empty($normalized) ? $normalized : $this->default_api_base;
    }

    private function normalize_api_base_url($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return $this->default_api_base;
        }

        // Handle values like "/v1" that trigger "Invalid URL (POST /v1)" in wp_remote_post.
        if ($value[0] === '/') {
            $value = 'https://api.openai.com' . $value;
        } elseif (!preg_match('#^https?://#i', $value)) {
            $value = 'https://' . $value;
        }

        $value = untrailingslashit($value);
        if (preg_match('#/v1$#i', $value)) {
            $value .= '/chat/completions';
        }

        $sanitized = esc_url_raw($value);
        return $sanitized ?: $this->default_api_base;
    }
    public function analyze_comments($comments, $model, $context = [])
    {
        $api_key = get_option('aica_api_key', '');

        if (empty($api_key)) {
            return new WP_Error('aica_missing_api_key', 'کلید API تنظیم نشده است.');
        }

        $prompt = $this->build_prompt($comments, $context);

        $body = [
            'model' => $model,
            'temperature' => 0.2,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'شما تحلیل‌گر حرفه‌ای نظرات کاربران هستید. فقط JSON معتبر برگردانید.',
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
        ];

        $response = wp_remote_post($this->get_api_base_url(), [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'body' => wp_json_encode($body, JSON_UNESCAPED_UNICODE),
            'timeout' => 60,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status_code >= 400) {
            $message = $body['error']['message'] ?? 'خطا در ارتباط با سرویس هوش مصنوعی';
            return new WP_Error('aica_api_error', sanitize_text_field($message));
        }

        $content = $body['choices'][0]['message']['content'] ?? '{}';
        $decoded = json_decode($content, true);

        if (!is_array($decoded)) {
            return new WP_Error('aica_invalid_json', 'پاسخ JSON معتبر نبود.');
        }

        return $decoded;
    }

    private function build_prompt($comments, $context = [])
    {
        $comment_text = implode("\n---\n", $comments);
        $product_name = trim((string) ($context['product_name'] ?? ''));
        if ($product_name === '') {
            $product_name = 'نامشخص';
        }
        $tone = sanitize_key(get_option('aica_analysis_tone', 'neutral'));
        $detail = sanitize_key(get_option('aica_analysis_detail_level', 'normal'));

        $tone_map = [
            'neutral' => 'خنثی و بی‌طرف',
            'formal' => 'رسمی',
            'friendly' => 'دوستانه',
            'professional' => 'حرفه‌ای',
            'minimal' => 'خیلی کوتاه و مینیمال',
            'critical' => 'نقادانه و دقیق',
            'persuasive' => 'ترغیب‌کننده و فروش‌محور',
            'technical' => 'فنی و جزئی‌نگر',
            'confident' => 'قاطع و مستقیم',
        ];
        $detail_map = [
            'short' => 'کوتاه و موجز',
            'normal' => 'متعادل',
            'detailed' => 'با جزئیات بیشتر',
        ];
        $detail_rules = [
            'short' => [
                'summary' => 'summary را در 45 تا 70 کلمه بنویس.',
                'short_summary' => 'short_summary حداکثر 12 کلمه باشد.',
                'topics' => 'برای topics فقط 3 مورد برگردان.',
            ],
            'normal' => [
                'summary' => 'summary را در 70 تا 100 کلمه بنویس.',
                'short_summary' => 'short_summary حداکثر 16 کلمه باشد.',
                'topics' => 'برای topics بین 3 تا 5 مورد برگردان.',
            ],
            'detailed' => [
                'summary' => 'summary را در 100 تا 120 کلمه بنویس.',
                'short_summary' => 'short_summary حداکثر 20 کلمه باشد.',
                'topics' => 'برای topics بین 4 تا 6 مورد برگردان.',
            ],
        ];
        $tone_rules = [
            'neutral' => 'زبان کاملاً بی‌طرف و بدون اغراق باشد.',
            'formal' => 'زبان رسمی، دقیق و بدون محاوره باشد.',
            'friendly' => 'زبان روان، صمیمی و محترمانه باشد.',
            'professional' => 'زبان تخصصی، شفاف و نتیجه‌محور باشد.',
            'minimal' => 'زبان بسیار کوتاه، مستقیم و بدون توضیح اضافی باشد.',
            'critical' => 'زبان تحلیلی و نقادانه باشد و ضعف‌ها را شفاف‌تر بیان کند.',
            'persuasive' => 'زبان ترغیب‌کننده و متمرکز بر ارزش خرید باشد، بدون ادعای غیرواقعی.',
            'technical' => 'زبان فنی‌تر باشد و روی کیفیت ساخت، عملکرد و جزئیات کاربردی تاکید کند.',
            'confident' => 'زبان قاطع، جمع‌بندی‌محور و نتیجه‌گیری روشن داشته باشد.',
        ];

        $tone_text = $tone_map[$tone] ?? $tone_map['neutral'];
        $detail_text = $detail_map[$detail] ?? $detail_map['normal'];
        $detail_rule = $detail_rules[$detail] ?? $detail_rules['normal'];
        $tone_rule = $tone_rules[$tone] ?? $tone_rules['neutral'];
        $template = $this->default_analysis_prompt;
        $template .= "\nلحن خروجی: {$tone_text}\n";
        $template .= "سطح جزئیات summary و short_summary: {$detail_text}\n";
        $template .= "الزام لحن: {$tone_rule}\n";
        $template .= "الزام جزئیات: {$detail_rule['summary']}\n";
        $template .= "الزام short_summary: {$detail_rule['short_summary']}\n";
        $template .= "الزام topics: {$detail_rule['topics']}\n";
        $template .= "الزام اختصار: بدون مقدمه‌چینی، بدون تکرار، بدون توضیح اضافی.\n";
        $template = str_replace('{{product_name}}', $product_name, $template);

        return str_replace('{{comments}}', $comment_text, $template);
    }

    public function suggest_reply($comment_text, $model)
    {
        $api_key = get_option('aica_api_key', '');

        if (empty($api_key)) {
            return new WP_Error('aica_missing_api_key', 'کلید API تنظیم نشده است.');
        }

        $body = [
            'model' => $model,
            'temperature' => 0.4,
            'messages' => [
                ['role' => 'system', 'content' => 'شما دستیار پشتیبانی فارسی هستید. پاسخ مودبانه و کوتاه بده.'],
                ['role' => 'user', 'content' => "برای این نظر کاربر یک پاسخ پیشنهادی بنویس:\n{$comment_text}"],
            ],
        ];

        $response = wp_remote_post($this->get_api_base_url(), [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'body' => wp_json_encode($body, JSON_UNESCAPED_UNICODE),
            'timeout' => 60,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $content = json_decode(wp_remote_retrieve_body($response), true);
        return sanitize_textarea_field($content['choices'][0]['message']['content'] ?? 'پاسخی تولید نشد.');
    }

    public function consolidate_analysis_results($partial_results, $model, $context = [])
    {
        $api_key = get_option('aica_api_key', '');
        if (empty($api_key)) {
            return new WP_Error('aica_missing_api_key', 'کلید API تنظیم نشده است.');
        }

        $product_name = trim((string) ($context['product_name'] ?? 'نامشخص'));
        $payload = wp_json_encode($partial_results, JSON_UNESCAPED_UNICODE);
        $tone = sanitize_key(get_option('aica_analysis_tone', 'neutral'));
        $detail = sanitize_key(get_option('aica_analysis_detail_level', 'normal'));

        $prompt = "شما باید چند خروجی تحلیلی تکه‌تکه را به یک خروجی نهایی واحد و بدون تکرار تبدیل کنی.\n";
        $prompt .= "فقط JSON معتبر با همان کلیدها برگردان و هیچ متن اضافه ننویس.\n";
        $prompt .= "نام محصول: {$product_name}\n";
        $prompt .= "لحن: {$tone}\n";
        $prompt .= "سطح جزئیات: {$detail}\n";
        $prompt .= "قواعد سخت:\n";
        $prompt .= "- summary فقط یک پاراگراف و بدون تکرار باشد.\n";
        $prompt .= "- short_summary فقط یک جمله باشد.\n";
        $prompt .= "- از اضافه‌گویی جلوگیری کن.\n";
        $prompt .= "- نکات مشابه را ادغام کن.\n";
        $prompt .= "- topics را یکتا و معنادار نگه دار.\n";
        $prompt .= "- در صورت امکان topics را با پیشوند «مثبت:» یا «منفی:» برگردان.\n";
        $prompt .= "داده‌های ورودی (JSON آرایه):\n{$payload}";

        $body = [
            'model' => $model,
            'temperature' => 0.1,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'شما تحلیل‌گر حرفه‌ای نظرات کاربران هستید. فقط JSON معتبر برگردانید.',
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
        ];

        $response = wp_remote_post($this->get_api_base_url(), [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'body' => wp_json_encode($body, JSON_UNESCAPED_UNICODE),
            'timeout' => 60,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $decoded_body = json_decode(wp_remote_retrieve_body($response), true);
        if ($status_code >= 400) {
            $message = $decoded_body['error']['message'] ?? 'خطا در ادغام نهایی تحلیل';
            return new WP_Error('aica_api_error', sanitize_text_field($message));
        }

        $content = $decoded_body['choices'][0]['message']['content'] ?? '{}';
        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            return new WP_Error('aica_invalid_json', 'پاسخ JSON معتبر نبود.');
        }

        return $decoded;
    }
}
