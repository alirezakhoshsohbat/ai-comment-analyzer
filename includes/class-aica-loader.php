<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once AICA_PLUGIN_DIR . 'database/class-aica-database.php';
require_once AICA_PLUGIN_DIR . 'ai-service/class-aica-ai-service.php';
require_once AICA_PLUGIN_DIR . 'ai-service/class-aica-analyzer.php';
require_once AICA_PLUGIN_DIR . 'cron/class-aica-cron.php';
require_once AICA_PLUGIN_DIR . 'frontend/class-aica-frontend.php';
require_once AICA_PLUGIN_DIR . 'admin/class-aica-admin.php';
require_once AICA_PLUGIN_DIR . 'api/class-aica-rest.php';

class AICA_Loader
{
    private static $instance = null;

    private $database;
    private $analyzer;
    private $cron;
    private $frontend;
    private $admin;
    private $rest;

    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function activate()
    {
        AICA_Database::create_tables();
        AICA_Cron::schedule_events();
    }

    public static function deactivate()
    {
        AICA_Cron::clear_events();
    }

    public function boot()
    {
        $this->database = new AICA_Database();
        $ai_service = new AICA_AI_Service();
        $this->analyzer = new AICA_Analyzer($ai_service);
        $this->cron = new AICA_Cron($this->analyzer);
        $this->frontend = new AICA_Frontend();
        $this->admin = new AICA_Admin($this->analyzer);
        $this->rest = new AICA_REST($this->analyzer);

        add_action('plugins_loaded', [$this, 'init']);
    }

    public function init()
    {
        $this->cron->register_hooks();
        $this->frontend->register_hooks();
        $this->admin->register_hooks();
        $this->rest->register_hooks();

        load_plugin_textdomain('ai-comment-analyzer', false, dirname(plugin_basename(AICA_PLUGIN_FILE)) . '/languages');
    }
}
