<?php
/**
 * Plugin Name: تحلیل‌گر هوشمند نظرات
 * Description: خلاصه‌سازی و تحلیل هوشمند نظرات کاربران با هوش مصنوعی.
 * Version: 1.0.0
 * Author: تیم توسعه
 * Text Domain: ai-comment-analyzer
 */

if (!defined('ABSPATH')) {
    exit;
}

define('AICA_VERSION', '1.0.0');
define('AICA_PLUGIN_FILE', __FILE__);
define('AICA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AICA_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once AICA_PLUGIN_DIR . 'includes/class-aica-loader.php';

register_activation_hook(__FILE__, ['AICA_Loader', 'activate']);
register_deactivation_hook(__FILE__, ['AICA_Loader', 'deactivate']);

AICA_Loader::instance()->boot();
