<?php
/**
 * Plugin Name: Sarfee GEO & AI Search Optimizer (سئوی هوش مصنوعی و ایندکس آنی)
 * Plugin URI: https://sarafee.uk
 * Description: بهینه‌سازی تخصصی سایت صرفی برای موتورهای جستجوی هوش مصنوعی (ChatGPT, Perplexity, Claude, Gemini) با استانداردهای GEO و AEO، تولید خودکار llms.txt، مانیتورینگ خزنده‌ها و ارسال بلادرنگ با IndexNow.
 * Version: 1.0.0
 * Author: Sarfee Development Team
 * Text Domain: sarfee-ai
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ── Definitions ─────────────────────────────────────────────────────────────
define( 'SARFEE_AI_VERSION', '1.0.0' );
define( 'SARFEE_AI_FILE', __FILE__ );
define( 'SARFEE_AI_PATH', plugin_dir_path( __FILE__ ) );
define( 'SARFEE_AI_URL', plugin_dir_url( __FILE__ ) );

// ── Includes ────────────────────────────────────────────────────────────────
require_once SARFEE_AI_PATH . 'includes/class-llms-generator.php';
require_once SARFEE_AI_PATH . 'includes/class-ai-bots.php';
require_once SARFEE_AI_PATH . 'includes/class-exchange-schema.php';
require_once SARFEE_AI_PATH . 'includes/class-indexnow.php';
require_once SARFEE_AI_PATH . 'includes/class-admin-settings.php';

/**
 * Main Orchestrator Class
 */
final class Sarfee_AI_Engine {

    private static ?Sarfee_AI_Engine $instance = null;

    public Sarfee_AI_LLMs $llms;
    public Sarfee_AI_Bots $bots;
    public Sarfee_AI_Exchange_Schema $schema;
    public Sarfee_AI_IndexNow $indexnow;
    public Sarfee_AI_Admin $admin;

    public static function get_instance(): Sarfee_AI_Engine {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_modules();
        $this->register_hooks();
    }

    private function init_modules(): void {
        $this->llms     = new Sarfee_AI_LLMs();
        $this->bots     = new Sarfee_AI_Bots();
        $this->schema   = new Sarfee_AI_Exchange_Schema();
        $this->indexnow = new Sarfee_AI_IndexNow();
        $this->admin    = new Sarfee_AI_Admin( $this );
    }

    private function register_hooks(): void {
        register_activation_hook( SARFEE_AI_FILE, [ $this, 'activate' ] );
        register_deactivation_hook( SARFEE_AI_FILE, [ $this, 'deactivate' ] );
    }

    public function activate(): void {
        // Initialize default options if not set
        if ( ! get_option( 'sarfee_ai_settings' ) ) {
            update_option( 'sarfee_ai_settings', [
                'enable_llms'        => 1,
                'enable_ai_txt'      => 1,
                'enable_ai_robots'   => 1,
                'enable_schema'      => 1,
                'enable_indexnow'    => 1,
            ] );
        }

        // Generate IndexNow key if missing
        $this->indexnow->ensure_api_key();

        // Flush rewrite rules for custom endpoints
        $this->llms->add_rewrite_rules();
        flush_rewrite_rules();
    }

    public function deactivate(): void {
        // Clear caches
        delete_transient( 'sarfee_llms_txt_cache' );
        delete_transient( 'sarfee_llms_full_txt_cache' );
        flush_rewrite_rules();
    }
}

// Bootstrap
add_action( 'plugins_loaded', function() {
    Sarfee_AI_Engine::get_instance();
} );
