<?php
/**
 * Admin Settings, Main Menu, & Comprehensive Documentation Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Sarfee_AI_Admin {

    private Sarfee_AI_Engine $engine;

    public function __construct( Sarfee_AI_Engine $engine ) {
        $this->engine = $engine;
        add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'handle_admin_actions' ] );
        add_action( 'add_meta_boxes', [ $this, 'register_ai_meta_boxes' ] );
    }

    /**
     * Registers top-level main admin menu and submenus
     */
    public function register_admin_menu(): void {
        // Custom SVG Sparkle/AI Icon
        $icon_svg = 'data:image/svg+xml;base64,' . base64_encode(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>'
        );

        // 1. Top Level Menu
        add_menu_page(
            'سئوی هوش مصنوعی و ایندکس آنی (GEO & IndexNow)',
            'سئوی هوش مصنوعی (GEO)',
            'manage_options',
            'sarfee-ai-engine',
            [ $this, 'render_dashboard_page' ],
            $icon_svg,
            32
        );

        // 2. Submenu: Dashboard & Settings
        add_submenu_page(
            'sarfee-ai-engine',
            'پیشخوان و تنظیمات سئوی هوش مصنوعی',
            'پیشخوان و تنظیمات',
            'manage_options',
            'sarfee-ai-engine',
            [ $this, 'render_dashboard_page' ]
        );

        // 3. Submenu: AI Bots Traffic Monitor
        add_submenu_page(
            'sarfee-ai-engine',
            'مانیتورینگ ترافیک ربات‌های هوش مصنوعی',
            'ترافیک زنده بات‌ها',
            'manage_options',
            'sarfee-ai-traffic',
            [ $this, 'render_traffic_page' ]
        );

        // 4. Submenu: Documentation & Guide
        add_submenu_page(
            'sarfee-ai-engine',
            'راهنما و استراتژی بهینه‌سازی هوش مصنوعی (GEO)',
            'راهنمای بهینه‌سازی GEO',
            'manage_options',
            'sarfee-ai-docs',
            [ $this, 'render_docs_page' ]
        );
    }

    public function handle_admin_actions(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Action: Save Settings
        if ( isset( $_POST['sarfee_ai_save_settings'] ) && check_admin_referer( 'sarfee_ai_settings_action', 'sarfee_ai_nonce' ) ) {
            $settings = [
                'enable_llms'               => ! empty( $_POST['enable_llms'] ) ? 1 : 0,
                'enable_ai_txt'             => ! empty( $_POST['enable_ai_txt'] ) ? 1 : 0,
                'enable_ai_robots'          => ! empty( $_POST['enable_ai_robots'] ) ? 1 : 0,
                'enable_schema'             => ! empty( $_POST['enable_schema'] ) ? 1 : 0,
                'enable_indexnow'           => ! empty( $_POST['enable_indexnow'] ) ? 1 : 0,

                // Dynamic Site Title & Tagline for LLMs
                'llms_site_title'           => ! empty( $_POST['llms_site_title'] ) ? sanitize_text_field( trim( $_POST['llms_site_title'] ) ) : '',
                'llms_site_desc'            => ! empty( $_POST['llms_site_desc'] ) ? sanitize_textarea_field( trim( $_POST['llms_site_desc'] ) ) : '',

                // Dynamic Legal & Transparency Pages (YMYL & E-E-A-T)
                'disclaimer_page_id'        => ! empty( $_POST['disclaimer_page_id'] ) ? (int) $_POST['disclaimer_page_id'] : 0,
                'disclaimer_custom_url'     => ! empty( $_POST['disclaimer_custom_url'] ) ? esc_url_raw( trim( $_POST['disclaimer_custom_url'] ) ) : '',
                'disclaimer_label'          => ! empty( $_POST['disclaimer_label'] ) ? sanitize_text_field( trim( $_POST['disclaimer_label'] ) ) : '',

                'report_content_page_id'    => ! empty( $_POST['report_content_page_id'] ) ? (int) $_POST['report_content_page_id'] : 0,
                'report_content_custom_url' => ! empty( $_POST['report_content_custom_url'] ) ? esc_url_raw( trim( $_POST['report_content_custom_url'] ) ) : '',
                'report_content_label'      => ! empty( $_POST['report_content_label'] ) ? sanitize_text_field( trim( $_POST['report_content_label'] ) ) : '',

                'disclaimer_intro_text'     => ! empty( $_POST['disclaimer_intro_text'] ) ? sanitize_textarea_field( trim( $_POST['disclaimer_intro_text'] ) ) : '',
            ];
            update_option( 'sarfee_ai_settings', $settings );
            $this->engine->llms->invalidate_cache();
            add_settings_error( 'sarfee_ai', 'settings_saved', 'تنظیمات و پرونده‌های هوش مصنوعی با موفقیت ذخیره و کش بازسازی شد.', 'updated' );
        }

        // Action: Clear llms.txt Cache
        if ( isset( $_POST['sarfee_ai_clear_cache'] ) && check_admin_referer( 'sarfee_ai_clear_cache_action', 'sarfee_ai_cache_nonce' ) ) {
            $this->engine->llms->invalidate_cache();
            add_settings_error( 'sarfee_ai', 'cache_cleared', 'کش فایل‌های llms.txt و llms-full.txt با موفقیت تخلیه و آماده بازسازی شد.', 'updated' );
        }

        // Action: Test Single IndexNow Ping
        if ( isset( $_POST['sarfee_ai_test_indexnow'] ) && check_admin_referer( 'sarfee_ai_test_indexnow_action', 'sarfee_ai_indexnow_nonce' ) ) {
            $test_url = home_url( '/' );
            $result   = $this->engine->indexnow->ping_urls( [ $test_url ] );
            if ( $result['success'] ) {
                add_settings_error( 'sarfee_ai', 'indexnow_success', 'پینگ تستی IndexNow با موفقیت ارسال شد (کد پاسخ: ' . $result['status'] . ').', 'updated' );
            } else {
                add_settings_error( 'sarfee_ai', 'indexnow_error', 'ارسال پینگ تستی IndexNow: ' . $result['message'], 'error' );
            }
        }

        // Action: Bulk IndexNow Submit (All published exchanges, symbols, posts, pages)
        if ( isset( $_POST['sarfee_ai_bulk_indexnow'] ) && check_admin_referer( 'sarfee_ai_bulk_indexnow_action', 'sarfee_ai_bulk_indexnow_nonce' ) ) {
            $result = $this->engine->indexnow->bulk_submit_all_urls();
            $count  = $result['count'] ?? 0;
            if ( $result['success'] ) {
                add_settings_error( 'sarfee_ai', 'indexnow_bulk_success', sprintf( 'ارسال دسته‌جمعی به IndexNow با موفقیت انجام شد (%d صفحه به موتورهای جستجو مخابره شد).', $count ), 'updated' );
            } else {
                add_settings_error( 'sarfee_ai', 'indexnow_bulk_notice', sprintf( 'ارسال دسته‌جمعی انجام شد (%d صفحه در لیست): %s', $count, $result['message'] ), 'notice-warning' );
            }
        }

        // Action: Clear IndexNow Logs
        if ( isset( $_POST['sarfee_ai_clear_indexnow_logs'] ) && check_admin_referer( 'sarfee_ai_clear_indexnow_action', 'sarfee_ai_clear_indexnow_nonce' ) ) {
            $this->engine->indexnow->clear_logs();
            add_settings_error( 'sarfee_ai', 'indexnow_cleared', 'تاریخچه گزارش‌های IndexNow با موفقیت پاکسازی شد.', 'updated' );
        }

        // Action: Export IndexNow Logs as CSV
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'sarfee_ai_export_indexnow_csv' && check_admin_referer( 'sarfee_ai_export_indexnow_action', 'nonce' ) ) {
            $logs = get_option( 'sarfee_ai_indexnow_log', [] );
            header( 'Content-Type: text/csv; charset=utf-8' );
            header( 'Content-Disposition: attachment; filename="sarfee-indexnow-logs-' . date( 'Y-m-d' ) . '.csv"' );
            $out = fopen( 'php://output', 'w' );
            fprintf( $out, chr(0xEF).chr(0xBB).chr(0xBF) ); // UTF-8 BOM for Excel
            fputcsv( $out, [ 'زمان ارسال', 'کد وضعیت', 'پیام سرور', 'آدرس‌های ارسال‌شده' ] );
            foreach ( $logs as $l ) {
                fputcsv( $out, [
                    $l['time'] ?? '',
                    $l['status'] ?? '',
                    $l['message'] ?? '',
                    implode( ' | ', (array) ( $l['urls'] ?? [] ) ),
                ] );
            }
            fclose( $out );
            exit;
        }

        // Action: Export Bot Traffic Logs as CSV
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'sarfee_ai_export_bot_csv' && check_admin_referer( 'sarfee_ai_export_bot_action', 'nonce' ) ) {
            $stats = get_option( 'sarfee_ai_bot_stats', [] );
            $logs  = $stats['log'] ?? [];
            header( 'Content-Type: text/csv; charset=utf-8' );
            header( 'Content-Disposition: attachment; filename="sarfee-ai-bot-traffic-' . date( 'Y-m-d' ) . '.csv"' );
            $out = fopen( 'php://output', 'w' );
            fprintf( $out, chr(0xEF).chr(0xBB).chr(0xBF) ); // UTF-8 BOM for Excel
            fputcsv( $out, [ 'زمان خزش', 'ربات هوش مصنوعی', 'آدرس صفحه', 'آی‌پی (IP)' ] );
            foreach ( $logs as $l ) {
                fputcsv( $out, [
                    $l['time'] ?? '',
                    $l['bot'] ?? '',
                    $l['url'] ?? '',
                    $l['ip'] ?? '',
                ] );
            }
            fclose( $out );
            exit;
        }

        // Action: Reset AI Bot Stats
        if ( isset( $_POST['sarfee_ai_reset_bot_stats'] ) && check_admin_referer( 'sarfee_ai_reset_bot_action', 'sarfee_ai_reset_bot_nonce' ) ) {
            delete_option( 'sarfee_ai_bot_stats' );
            add_settings_error( 'sarfee_ai', 'bot_stats_reset', 'آمار و تاریخچه خزش ربات‌های هوش مصنوعی با موفقیت پاکسازی شد.', 'updated' );
        }

        // Action: Simulate AI Bot Hit (for local testing)
        if ( isset( $_POST['sarfee_ai_simulate_bot'] ) && check_admin_referer( 'sarfee_ai_simulate_bot_action', 'sarfee_ai_simulate_bot_nonce' ) ) {
            $bot_key = sanitize_text_field( $_POST['simulate_bot_type'] ?? 'gpt' );
            $raw_url = sanitize_text_field( $_POST['simulate_url'] ?? '' );

            if ( empty( trim( $raw_url ) ) ) {
                $target_url = home_url( '/afn/' );
            } elseif ( str_starts_with( $raw_url, 'http://' ) || str_starts_with( $raw_url, 'https://' ) ) {
                $target_url = esc_url_raw( $raw_url );
            } else {
                $target_url = home_url( '/' . ltrim( $raw_url, '/' ) );
            }

            $bot_map  = [
                'gpt'        => 'GPTBot (ChatGPT)',
                'perplexity' => 'PerplexityBot',
                'claude'     => 'ClaudeBot (Anthropic)',
                'gemini'     => 'Google-Extended (Gemini)',
                'apple'      => 'Applebot-Extended',
            ];
            $bot_name = $bot_map[ $bot_key ] ?? 'GPTBot (ChatGPT)';

            $stats = get_option( 'sarfee_ai_bot_stats', [] );
            if ( ! is_array( $stats ) ) {
                $stats = [];
            }
            $today = current_time( 'Y-m-d' );
            if ( ! isset( $stats['counts'][ $bot_name ] ) ) {
                $stats['counts'][ $bot_name ] = [ 'total' => 0, 'today' => 0, 'date' => $today ];
            }
            if ( ( $stats['counts'][ $bot_name ]['date'] ?? '' ) !== $today ) {
                $stats['counts'][ $bot_name ]['today'] = 0;
                $stats['counts'][ $bot_name ]['date']  = $today;
            }
            $stats['counts'][ $bot_name ]['total']++;
            $stats['counts'][ $bot_name ]['today']++;

            if ( ! isset( $stats['log'] ) || ! is_array( $stats['log'] ) ) {
                $stats['log'] = [];
            }
            array_unshift( $stats['log'], [
                'bot'  => $bot_name,
                'url'  => $target_url,
                'time' => current_time( 'mysql' ),
                'ip'   => '127.0.0.1 (شبیه‌سازی دستی)',
            ] );
            $stats['log'] = array_slice( $stats['log'], 0, 150 );
            update_option( 'sarfee_ai_bot_stats', $stats, false );
            add_settings_error( 'sarfee_ai', 'bot_simulated', "یک بازدید تستی از {$bot_name} برای صفحه " . esc_html( $target_url ) . " با موفقیت ثبت شد.", 'updated' );
        }
    }

    /**
     * Common Modern Styles for Admin Pages
     */
    private function render_admin_styles(): void {
        ?>
        <style>
            .sarfee-admin-wrap {
                font-family: inherit;
            }
            /* Modern Tactile Button System */
            .sarfee-admin-wrap .sarfee-btn {
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                gap: 7px !important;
                font-family: inherit !important;
                font-size: 13px !important;
                font-weight: 700 !important;
                line-height: 1.4 !important;
                padding: 8px 18px !important;
                min-height: 38px !important;
                border-radius: 9px !important;
                text-decoration: none !important;
                cursor: pointer !important;
                transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
                box-sizing: border-box !important;
                border: 1px solid transparent !important;
                white-space: nowrap !important;
            }
            .sarfee-admin-wrap .sarfee-btn:active {
                transform: scale(0.98) !important;
            }
            .sarfee-admin-wrap .sarfee-btn-sm {
                padding: 6px 14px !important;
                font-size: 12px !important;
                min-height: 32px !important;
                border-radius: 7px !important;
            }
            .sarfee-admin-wrap .sarfee-btn-lg {
                padding: 10px 26px !important;
                font-size: 14px !important;
                min-height: 44px !important;
                border-radius: 10px !important;
            }
            .sarfee-admin-wrap .sarfee-btn-block {
                width: 100% !important;
            }
            /* Primary Button (Rich Royal Blue) */
            .sarfee-admin-wrap .sarfee-btn-primary {
                background: #2563eb !important;
                color: #ffffff !important;
                border-color: #1d4ed8 !important;
                box-shadow: 0 2px 5px rgba(37, 99, 235, 0.25) !important;
            }
            .sarfee-admin-wrap .sarfee-btn-primary:hover {
                background: #1d4ed8 !important;
                color: #ffffff !important;
                border-color: #1e40af !important;
                box-shadow: 0 4px 12px rgba(37, 99, 235, 0.35) !important;
                transform: translateY(-1px) !important;
            }
            /* Secondary Button (Crisp White with Slate Border) */
            .sarfee-admin-wrap .sarfee-btn-secondary {
                background: #ffffff !important;
                color: #1e293b !important;
                border-color: #cbd5e1 !important;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05) !important;
            }
            .sarfee-admin-wrap .sarfee-btn-secondary:hover {
                background: #f8fafc !important;
                color: #0f172a !important;
                border-color: #94a3b8 !important;
                box-shadow: 0 3px 8px rgba(0, 0, 0, 0.08) !important;
                transform: translateY(-1px) !important;
            }
            /* Success / Action Green Button */
            .sarfee-admin-wrap .sarfee-btn-success {
                background: #059669 !important;
                color: #ffffff !important;
                border-color: #047857 !important;
                box-shadow: 0 2px 5px rgba(5, 150, 105, 0.25) !important;
            }
            .sarfee-admin-wrap .sarfee-btn-success:hover {
                background: #047857 !important;
                color: #ffffff !important;
                border-color: #065f46 !important;
                box-shadow: 0 4px 12px rgba(5, 150, 105, 0.35) !important;
                transform: translateY(-1px) !important;
            }
            /* Danger / Red Button */
            .sarfee-admin-wrap .sarfee-btn-danger {
                background: #ffffff !important;
                color: #dc2626 !important;
                border-color: #fca5a5 !important;
                box-shadow: 0 1px 3px rgba(220, 38, 38, 0.08) !important;
            }
            .sarfee-admin-wrap .sarfee-btn-danger:hover {
                background: #ef4444 !important;
                color: #ffffff !important;
                border-color: #dc2626 !important;
                box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3) !important;
                transform: translateY(-1px) !important;
            }
            /* Banner Buttons */
            .sarfee-admin-wrap .sarfee-btn-banner-primary {
                background: #0284c7 !important;
                color: #ffffff !important;
                border-color: #0284c7 !important;
                box-shadow: 0 2px 8px rgba(2, 132, 199, 0.35) !important;
            }
            .sarfee-admin-wrap .sarfee-btn-banner-primary:hover {
                background: #0369a1 !important;
                border-color: #0369a1 !important;
                color: #ffffff !important;
                transform: translateY(-1px) !important;
                box-shadow: 0 4px 14px rgba(2, 132, 199, 0.45) !important;
            }
            .sarfee-admin-wrap .sarfee-btn-banner-ghost {
                background: rgba(255, 255, 255, 0.15) !important;
                color: #ffffff !important;
                border-color: rgba(255, 255, 255, 0.35) !important;
                backdrop-filter: blur(4px) !important;
            }
            .sarfee-admin-wrap .sarfee-btn-banner-ghost:hover {
                background: rgba(255, 255, 255, 0.25) !important;
                border-color: rgba(255, 255, 255, 0.6) !important;
                color: #ffffff !important;
                transform: translateY(-1px) !important;
            }
            /* General Enhancement for WP Buttons Inside Wrap */
            .sarfee-admin-wrap .button:not(.sarfee-btn) {
                border-radius: 7px !important;
                font-weight: 600 !important;
                min-height: 32px !important;
                line-height: 2 !important;
                transition: all 0.2s ease !important;
            }
            .sarfee-admin-wrap .button:not(.sarfee-btn):hover {
                transform: translateY(-1px) !important;
            }
        </style>
        <?php
    }

    /**
     * Renders Dashboard & Settings Page
     */
    public function render_dashboard_page(): void {
        $settings     = get_option( 'sarfee_ai_settings', [
            'enable_llms'               => 1,
            'enable_ai_txt'             => 1,
            'enable_ai_robots'          => 1,
            'enable_schema'             => 1,
            'enable_indexnow'           => 1,
            'disclaimer_page_id'        => 0,
            'disclaimer_custom_url'     => '',
            'disclaimer_label'          => '',
            'report_content_page_id'    => 0,
            'report_content_custom_url' => '',
            'report_content_label'      => '',
            'disclaimer_intro_text'     => '',
        ] );
        $wp_pages     = get_pages( [
            'post_status' => 'publish,private,draft',
            'sort_column' => 'post_title',
            'sort_order'  => 'ASC',
        ] );
        $indexnow_key = $this->engine->indexnow->ensure_api_key();
        $logs         = get_option( 'sarfee_ai_indexnow_log', [] );

        $bot_stats    = get_option( 'sarfee_ai_bot_stats', [] );
        $bot_counts   = $bot_stats['counts'] ?? [];
        $bot_logs     = $bot_stats['log'] ?? [];

        $llms_url     = home_url( '/llms.txt' );
        $full_url     = home_url( '/llms-full.txt' );
        $ai_url       = home_url( '/ai.txt' );
        $robots_url   = home_url( '/robots.txt' );
        $key_url      = home_url( "/{$indexnow_key}.txt" );
        $traffic_url  = admin_url( 'admin.php?page=sarfee-ai-traffic' );
        $docs_url     = admin_url( 'admin.php?page=sarfee-ai-docs' );

        $active_tab   = sanitize_key( $_GET['tab'] ?? 'settings' );
        if ( ! in_array( $active_tab, [ 'settings', 'indexnow', 'tools' ], true ) ) {
            $active_tab = 'settings';
        }

        $site_name = ! empty( $settings['llms_site_title'] ) ? $settings['llms_site_title'] : ( get_bloginfo( 'name' ) ?: 'صرفی' );
        ?>
        <div class="wrap sarfee-admin-wrap" dir="rtl" style="max-width:none; margin: 16px 20px 24px 2px; box-sizing: border-box;">
            <?php $this->render_admin_styles(); ?>
            
            <!-- Hidden Screen-Reader Heading for WordPress Accessibility & Notice Anchoring -->
            <h1 class="wp-heading-inline screen-reader-text">مرکز سئوی هوش مصنوعی و ایندکس آنی <?php echo esc_html( $site_name ); ?></h1>

            <!-- Dedicated Notice Container (Keeps Notices Completely Out of the Dark Banner) -->
            <div class="sarfee-notices-area" style="margin-bottom:16px;">
                <?php settings_errors( 'sarfee_ai' ); ?>
            </div>

            <!-- Header Banner -->
            <div style="background:linear-gradient(135deg, #1e293b 0%, #0f172a 100%); color:#ffffff; padding:26px 30px; border-radius:16px; margin-bottom:20px; box-shadow:0 4px 20px rgba(0,0,0,0.12); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
                <div>
                    <div style="display:flex; align-items:center; gap:12px; margin-bottom:8px;">
                        <div role="heading" aria-level="2" style="color:#ffffff; margin:0; font-size:24px; font-weight:800; line-height:1.3;">مرکز سئوی هوش مصنوعی و ایندکس آنی (<?php echo esc_html( $site_name ); ?> GEO & AI)</div>
                        <span style="font-size:12px; font-weight:700; background:rgba(56, 189, 248, 0.2); color:#38bdf8; padding:3px 12px; border-radius:20px;">نسخه <?php echo esc_html( SARFEE_AI_VERSION ); ?></span>
                    </div>
                    <p style="color:#94a3b8; font-size:13px; margin:0; line-height:1.7;">مدیریت یکپارچه استانداردهای سئوی هوش مصنوعی (GEO & AEO)، پرونده‌های داده و ارسال بلادرنگ تغییرات.</p>
                </div>
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <a href="<?php echo esc_url( $traffic_url ); ?>" class="sarfee-btn sarfee-btn-banner-primary">
                        <span>📊</span>
                        <span>مانیتورینگ بات‌ها ↗</span>
                    </a>
                    <a href="<?php echo esc_url( $docs_url ); ?>" class="sarfee-btn sarfee-btn-banner-ghost">
                        <span>📖</span>
                        <span>راهنما و مستندات</span>
                    </a>
                </div>
            </div>

            <!-- Dashboard Navigation Tabs -->
            <nav class="nav-tab-wrapper" style="margin-bottom:24px; border-bottom:1px solid #cbd5e1;">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=sarfee-ai-engine&tab=settings' ) ); ?>" class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>" style="font-weight:600; font-size:14px; padding:8px 18px;">
                    <span>⚙️ تنظیمات و وضعیت عمومی</span>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=sarfee-ai-engine&tab=indexnow' ) ); ?>" class="nav-tab <?php echo $active_tab === 'indexnow' ? 'nav-tab-active' : ''; ?>" style="font-weight:600; font-size:14px; padding:8px 18px;">
                    <span>⚡ گزارش‌ها و ایندکس آنی</span>
                    <?php if ( ! empty( $logs ) ) : ?>
                        <span style="background:#e0f2fe; color:#0369a1; font-size:11px; padding:2px 8px; border-radius:10px; margin-right:6px; font-weight:700;"><?php echo count( $logs ); ?></span>
                    <?php endif; ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=sarfee-ai-engine&tab=tools' ) ); ?>" class="nav-tab <?php echo $active_tab === 'tools' ? 'nav-tab-active' : ''; ?>" style="font-weight:600; font-size:14px; padding:8px 18px;">
                    <span>🛠 ابزارهای نگهداری و تست‌ها</span>
                </a>
            </nav>

            <?php if ( $active_tab === 'settings' ) : ?>
                <!-- TAB 1: OVERVIEW & SETTINGS -->
                
                <!-- Row 1: Core System Endpoints Cards (3 Columns) -->
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:18px; margin-bottom:24px;">
                    
                    <!-- Card 1: llms.txt -->
                    <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; padding:22px; box-shadow:0 1px 3px rgba(0,0,0,0.02);">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                            <strong style="color:#0f172a; font-size:15px; display:flex; align-items:center; gap:6px;">
                                <span>📄</span>
                                <span>فایل استاندارد llms.txt</span>
                            </strong>
                            <span style="background:#dcfce7; color:#15803d; font-size:11px; padding:3px 10px; border-radius:12px; font-weight:700;">فعال و کش‌شده</span>
                        </div>
                        <p style="color:#64748b; font-size:13px; margin:0 0 16px; line-height:1.6;">خلاصه فشرده Markdown از صرافی‌ها، نمادها و خدمات جهت خوانش آنی مدل‌های زبانی.</p>
                        <div style="display:flex; gap:10px; flex-wrap:wrap;">
                            <a href="<?php echo esc_url( $llms_url ); ?>" target="_blank" class="sarfee-btn sarfee-btn-secondary sarfee-btn-sm">
                                <span>مشاهده llms.txt</span> <span>↗</span>
                            </a>
                            <a href="<?php echo esc_url( $full_url ); ?>" target="_blank" class="sarfee-btn sarfee-btn-secondary sarfee-btn-sm">
                                <span>نسخه کامل</span> <span>↗</span>
                            </a>
                        </div>
                    </div>

                    <!-- Card 2: ai.txt & robots -->
                    <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; padding:22px; box-shadow:0 1px 3px rgba(0,0,0,0.02);">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                            <strong style="color:#0f172a; font-size:15px; display:flex; align-items:center; gap:6px;">
                                <span>🤖</span>
                                <span>سیاست کراولرها (ai.txt)</span>
                            </strong>
                            <span style="background:#dcfce7; color:#15803d; font-size:11px; padding:3px 10px; border-radius:12px; font-weight:700;">هماهنگ</span>
                        </div>
                        <p style="color:#64748b; font-size:13px; margin:0 0 16px; line-height:1.6;">شرایط الزامی استناد (Attribution) و مجوز رسمی برای کراولرهای معتبر هوش مصنوعی.</p>
                        <div style="display:flex; gap:10px; flex-wrap:wrap;">
                            <a href="<?php echo esc_url( $ai_url ); ?>" target="_blank" class="sarfee-btn sarfee-btn-secondary sarfee-btn-sm">
                                <span>مشاهده ai.txt</span> <span>↗</span>
                            </a>
                            <a href="<?php echo esc_url( $robots_url ); ?>" target="_blank" class="sarfee-btn sarfee-btn-secondary sarfee-btn-sm">
                                <span>فایل robots.txt</span> <span>↗</span>
                            </a>
                        </div>
                    </div>

                    <!-- Card 3: IndexNow -->
                    <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; padding:22px; box-shadow:0 1px 3px rgba(0,0,0,0.02);">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                            <strong style="color:#0f172a; font-size:15px; display:flex; align-items:center; gap:6px;">
                                <span>⚡</span>
                                <span>پروتکل آنی IndexNow</span>
                            </strong>
                            <span style="background:#dcfce7; color:#15803d; font-size:11px; padding:3px 10px; border-radius:12px; font-weight:700;">آماده پینگ</span>
                        </div>
                        <p style="color:#64748b; font-size:13px; margin:0 0 16px; line-height:1.6;">ارسال بلادرنگ تغییرات صرافی‌ها و نمادها به مایکروسافت بینگ، کوپایلت و یاندکس.</p>
                        <div>
                            <a href="<?php echo esc_url( $key_url ); ?>" target="_blank" class="sarfee-btn sarfee-btn-secondary sarfee-btn-sm">
                                <span>بررسی کلید امنیتی</span> <span>↗</span>
                            </a>
                        </div>
                    </div>

                </div>

                <!-- Row 2: 2-Column Balanced Layout -->
                <div style="display:grid; grid-template-columns: 2fr 1fr; gap:24px; align-items:start;">

                    <!-- RIGHT COLUMN: Settings Form & Quick IndexNow Summary -->
                    <div style="display:flex; flex-direction:column; gap:24px;">
                        
                        <!-- Settings Form Card -->
                        <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; padding:26px; box-shadow:0 1px 3px rgba(0,0,0,0.02);">
                            <div style="border-bottom:1px solid #f1f5f9; padding-bottom:14px; margin-bottom:18px;">
                                <h2 style="margin:0; font-size:17px; color:#0f172a;">تنظیمات ماژول‌های فعال هوش مصنوعی</h2>
                                <p style="margin:4px 0 0; font-size:13px; color:#64748b;">فعال یا غیرفعال کردن قابلیت‌های اختصاصی سئوی هوش مصنوعی برای سایت <?php echo esc_html( $site_name ); ?>:</p>
                            </div>
                            
                            <form method="post" action="">
                                <?php wp_nonce_field( 'sarfee_ai_settings_action', 'sarfee_ai_nonce' ); ?>

                                <style>
                                    /* Modern AI Modules Interactive Cards & Switches */
                                    .sarfee-modules-list {
                                        display: flex;
                                        flex-direction: column;
                                        gap: 14px;
                                    }
                                    .sarfee-module-card {
                                        background: #ffffff;
                                        border: 1.5px solid #e2e8f0;
                                        border-radius: 14px;
                                        padding: 18px 20px;
                                        transition: all 0.22s ease-in-out;
                                        position: relative;
                                        display: flex;
                                        align-items: center;
                                        justify-content: space-between;
                                        gap: 16px;
                                        flex-wrap: wrap;
                                    }
                                    .sarfee-module-card:hover {
                                        border-color: #cbd5e1;
                                        box-shadow: 0 4px 16px -2px rgba(15, 23, 42, 0.05);
                                    }
                                    .sarfee-module-card.is-active {
                                        background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
                                        border-color: #93c5fd;
                                        box-shadow: 0 4px 18px -2px rgba(37, 99, 235, 0.08);
                                    }
                                    .sarfee-module-info {
                                        display: flex;
                                        align-items: flex-start;
                                        gap: 14px;
                                        flex: 1;
                                        min-width: 270px;
                                    }
                                    .sarfee-module-icon {
                                        width: 44px;
                                        height: 44px;
                                        border-radius: 12px;
                                        display: flex;
                                        align-items: center;
                                        justify-content: center;
                                        flex-shrink: 0;
                                        box-shadow: 0 2px 6px rgba(0,0,0,0.04);
                                    }
                                    .sarfee-module-text {
                                        flex: 1;
                                    }
                                    .sarfee-module-title {
                                        font-size: 14.5px;
                                        font-weight: 700;
                                        color: #0f172a;
                                        margin: 0 0 4px;
                                        line-height: 1.4;
                                    }
                                    .sarfee-module-desc {
                                        font-size: 12.5px;
                                        color: #64748b;
                                        line-height: 1.7;
                                        margin: 0;
                                    }
                                    .sarfee-module-tags {
                                        display: flex;
                                        gap: 6px;
                                        flex-wrap: wrap;
                                        margin-top: 8px;
                                    }
                                    .sarfee-module-tag {
                                        font-size: 11px;
                                        background: #f1f5f9;
                                        color: #475569;
                                        padding: 2px 8px;
                                        border-radius: 6px;
                                        font-weight: 600;
                                    }
                                    .sarfee-module-card.is-active .sarfee-module-tag {
                                        background: #eff6ff;
                                        color: #1d4ed8;
                                    }
                                    .sarfee-module-control {
                                        display: flex;
                                        align-items: center;
                                        gap: 12px;
                                        flex-shrink: 0;
                                    }
                                    .sarfee-status-badge {
                                        display: inline-flex;
                                        align-items: center;
                                        gap: 6px;
                                        padding: 5px 12px;
                                        border-radius: 20px;
                                        font-size: 11.5px;
                                        font-weight: 700;
                                        transition: all 0.2s ease;
                                    }
                                    .sarfee-status-badge.badge-active {
                                        background: #ecfdf5;
                                        color: #059669;
                                        border: 1px solid #a7f3d0;
                                    }
                                    .sarfee-status-badge.badge-inactive {
                                        background: #f8fafc;
                                        color: #94a3b8;
                                        border: 1px solid #e2e8f0;
                                    }
                                    .sarfee-pulse-dot {
                                        width: 6px;
                                        height: 6px;
                                        background-color: #10b981;
                                        border-radius: 50%;
                                        box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.25);
                                    }

                                    /* Custom iOS-style Switch */
                                    .sarfee-switch {
                                        position: relative;
                                        display: inline-block;
                                        width: 50px;
                                        height: 28px;
                                    }
                                    .sarfee-switch input {
                                        opacity: 0;
                                        width: 0;
                                        height: 0;
                                        position: absolute;
                                    }
                                    .sarfee-slider {
                                        position: absolute;
                                        cursor: pointer;
                                        top: 0;
                                        left: 0;
                                        right: 0;
                                        bottom: 0;
                                        background-color: #cbd5e1;
                                        transition: .25s cubic-bezier(0.4, 0, 0.2, 1);
                                        border-radius: 28px;
                                    }
                                    .sarfee-slider:before {
                                        position: absolute;
                                        content: "";
                                        height: 22px;
                                        width: 22px;
                                        left: 3px;
                                        bottom: 3px;
                                        background-color: white;
                                        transition: .25s cubic-bezier(0.4, 0, 0.2, 1);
                                        border-radius: 50%;
                                        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
                                    }
                                    .sarfee-switch input:checked + .sarfee-slider {
                                        background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
                                    }
                                    .sarfee-switch input:checked + .sarfee-slider:before {
                                        transform: translateX(22px);
                                    }
                                </style>

                                <div class="sarfee-modules-list">
                                    
                                    <!-- Module 1: llms.txt & llms-full.txt -->
                                    <?php $is_llms_active = ! empty( $settings['enable_llms'] ); ?>
                                    <div class="sarfee-module-card <?php echo $is_llms_active ? 'is-active' : ''; ?>">
                                        <div class="sarfee-module-info">
                                            <div class="sarfee-module-icon" style="background:#eff6ff; color:#2563eb;">
                                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                                            </div>
                                            <div class="sarfee-module-text">
                                                <h4 class="sarfee-module-title">پرونده‌های استاندارد هوش مصنوعی (llms.txt و llms-full.txt)</h4>
                                                <p class="sarfee-module-desc">
                                                    تولید خودکار و بلادرنگ دیتای ساختاریافته شامل صرافی‌های معتبر، خدمات حوالجات، نرخ ارزهای فیات، مسکوکات و مقالات طبق استاندارد جهانی <code style="font-size:11.5px; background:#f1f5f9; padding:2px 6px; border-radius:4px;">llmstxt.org</code> به عنوان منبع استناد مستقیم برای ChatGPT، Perplexity و Claude.
                                                </p>
                                                <div class="sarfee-module-tags">
                                                    <span class="sarfee-module-tag">استاندارد جهانی AEO</span>
                                                    <span class="sarfee-module-tag">کشف خودکار در هدر (Head Discovery)</span>
                                                    <span class="sarfee-module-tag">کش هوشمند ۱۲ ساعته</span>
                                                    <span class="sarfee-module-tag">لینک مستقیم صرافی‌ها</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="sarfee-module-control">
                                            <span class="sarfee-status-badge <?php echo $is_llms_active ? 'badge-active' : 'badge-inactive'; ?>">
                                                <?php if ( $is_llms_active ) : ?><span class="sarfee-pulse-dot"></span><?php endif; ?>
                                                <span class="badge-text"><?php echo $is_llms_active ? 'فعال' : 'غیرفعال'; ?></span>
                                            </span>
                                            <label class="sarfee-switch">
                                                <input type="checkbox" name="enable_llms" value="1" <?php checked( $is_llms_active ); ?> onchange="sarfeeToggleModuleCard(this)" />
                                                <span class="sarfee-slider"></span>
                                            </label>
                                        </div>
                                    </div>

                                    <!-- Module 2: ai.txt Policy -->
                                    <?php $is_aitxt_active = ! empty( $settings['enable_ai_txt'] ); ?>
                                    <div class="sarfee-module-card <?php echo $is_aitxt_active ? 'is-active' : ''; ?>">
                                        <div class="sarfee-module-info">
                                            <div class="sarfee-module-icon" style="background:#faf5ff; color:#7c3aed;">
                                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><path d="m9 12 2 2 4-4"></path></svg>
                                            </div>
                                            <div class="sarfee-module-text">
                                                <h4 class="sarfee-module-title">سیاست استفاده، کپی‌رایت و الزام استناد به برند (ai.txt)</h4>
                                                <p class="sarfee-module-desc">
                                                    اعلام رسمی مانیفست استفاده تجاری/آموزشی از داده‌های سایت در آدرس <code style="font-size:11.5px; background:#f1f5f9; padding:2px 6px; border-radius:4px;">/ai.txt</code> و الزام مدل‌های زبانی به درج نام و لینک «<?php echo esc_html( $site_name ); ?>» در کنار فیلتر کردن اسکرپرهای هرزنگار و سنگین (مانند CCBot).
                                                </p>
                                                <div class="sarfee-module-tags">
                                                    <span class="sarfee-module-tag">حفاظت از کپی‌رایت داده‌ها</span>
                                                    <span class="sarfee-module-tag">الزام بک‌لینک استنادی</span>
                                                    <span class="sarfee-module-tag">مسدودسازی اسکرپرها</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="sarfee-module-control">
                                            <span class="sarfee-status-badge <?php echo $is_aitxt_active ? 'badge-active' : 'badge-inactive'; ?>">
                                                <?php if ( $is_aitxt_active ) : ?><span class="sarfee-pulse-dot"></span><?php endif; ?>
                                                <span class="badge-text"><?php echo $is_aitxt_active ? 'فعال' : 'غیرفعال'; ?></span>
                                            </span>
                                            <label class="sarfee-switch">
                                                <input type="checkbox" name="enable_ai_txt" value="1" <?php checked( $is_aitxt_active ); ?> onchange="sarfeeToggleModuleCard(this)" />
                                                <span class="sarfee-slider"></span>
                                            </label>
                                        </div>
                                    </div>

                                    <!-- Module 3: robots.txt Optimization -->
                                    <?php $is_robots_active = ! empty( $settings['enable_ai_robots'] ); ?>
                                    <div class="sarfee-module-card <?php echo $is_robots_active ? 'is-active' : ''; ?>">
                                        <div class="sarfee-module-info">
                                            <div class="sarfee-module-icon" style="background:#ecfeff; color:#0891b2;">
                                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="10" rx="2"></rect><circle cx="12" cy="5" r="2"></circle><path d="M12 7v4"></path><line x1="8" y1="16" x2="8" y2="16"></line><line x1="16" y1="16" x2="16" y2="16"></line></svg>
                                            </div>
                                            <div class="sarfee-module-text">
                                                <h4 class="sarfee-module-title">هماهنگی هوشمند فایل robots.txt با خزنده‌های رسمی AI</h4>
                                                <p class="sarfee-module-desc">
                                                    باز نگه‌داشتن دسترسی خزنده‌های پاسخ‌گو (GPTBot، PerplexityBot، ClaudeBot و Google-Extended) بدون تداخل با رنک‌مث، به همراه سیستم هوشمند حذف موارد تکراری (Deduplication) و درج لینک فایل‌های مرجع هوش مصنوعی در انتهای فایل.
                                                </p>
                                                <div class="sarfee-module-tags">
                                                    <span class="sarfee-module-tag">متاتگ‌های استناد (Citation Meta Tags)</span>
                                                    <span class="sarfee-module-tag">سازگاری ۱۰۰٪ با رنک‌مث</span>
                                                    <span class="sarfee-module-tag">حذف کدهای تکراری</span>
                                                    <span class="sarfee-module-tag">بهینه‌سازی Google AI Overviews</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="sarfee-module-control">
                                            <span class="sarfee-status-badge <?php echo $is_robots_active ? 'badge-active' : 'badge-inactive'; ?>">
                                                <?php if ( $is_robots_active ) : ?><span class="sarfee-pulse-dot"></span><?php endif; ?>
                                                <span class="badge-text"><?php echo $is_robots_active ? 'فعال' : 'غیرفعال'; ?></span>
                                            </span>
                                            <label class="sarfee-switch">
                                                <input type="checkbox" name="enable_ai_robots" value="1" <?php checked( $is_robots_active ); ?> onchange="sarfeeToggleModuleCard(this)" />
                                                <span class="sarfee-slider"></span>
                                            </label>
                                        </div>
                                    </div>

                                    <!-- Module 4: Schema.org FinancialService -->
                                    <?php $is_schema_active = ! empty( $settings['enable_schema'] ); ?>
                                    <div class="sarfee-module-card <?php echo $is_schema_active ? 'is-active' : ''; ?>">
                                        <div class="sarfee-module-info">
                                            <div class="sarfee-module-icon" style="background:#ecfdf5; color:#059669;">
                                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"></path><path d="M3 10h18"></path><path d="M5 6l7-3 7 3"></path><path d="M4 10v11"></path><path d="M20 10v11"></path><path d="M8 14v4"></path><path d="M12 14v4"></path><path d="M16 14v4"></path></svg>
                                            </div>
                                            <div class="sarfee-module-text">
                                                <h4 class="sarfee-module-title">اسکیمای ساختاریافته صرافی‌ها (FinancialService و ExchangeOffice)</h4>
                                                <p class="sarfee-module-desc">
                                                    تولید خودکار اسکیمای غنی JSON-LD برای صفحات صرافی‌ها (مجوز معتبر، شهر، تلفن، آدرس، حوزه خدمات، ارتباط با پایگاه مادر و اصول ویرایشی E-E-A-T) و تزریق مستقیم به گراف رنک‌مث بدون تداخل جهت کسب ریچ ریزالت گوگل.
                                                </p>
                                                <div class="sarfee-module-tags">
                                                    <span class="sarfee-module-tag">تاییدیه Google Rich Results</span>
                                                    <span class="sarfee-module-tag">گراف یکپارچه رنک‌مث</span>
                                                    <span class="sarfee-module-tag">سیگنال اعتماد E-E-A-T</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="sarfee-module-control">
                                            <span class="sarfee-status-badge <?php echo $is_schema_active ? 'badge-active' : 'badge-inactive'; ?>">
                                                <?php if ( $is_schema_active ) : ?><span class="sarfee-pulse-dot"></span><?php endif; ?>
                                                <span class="badge-text"><?php echo $is_schema_active ? 'فعال' : 'غیرفعال'; ?></span>
                                            </span>
                                            <label class="sarfee-switch">
                                                <input type="checkbox" name="enable_schema" value="1" <?php checked( $is_schema_active ); ?> onchange="sarfeeToggleModuleCard(this)" />
                                                <span class="sarfee-slider"></span>
                                            </label>
                                        </div>
                                    </div>

                                    <!-- Module 5: IndexNow Protocol -->
                                    <?php $is_indexnow_active = ! empty( $settings['enable_indexnow'] ); ?>
                                    <div class="sarfee-module-card <?php echo $is_indexnow_active ? 'is-active' : ''; ?>">
                                        <div class="sarfee-module-info">
                                            <div class="sarfee-module-icon" style="background:#fffbeb; color:#d97706;">
                                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>
                                            </div>
                                            <div class="sarfee-module-text">
                                                <h4 class="sarfee-module-title">موتور ایندکس و ارسال بلادرنگ تغییرات (IndexNow Protocol)</h4>
                                                <p class="sarfee-module-desc">
                                                    ارسال خودکار سیگنال فوری به موتورهای جستجوی مایکروسافت بینگ، یاندکس و کوپایلت به محض انتشار یا بروزرسانی هر صرافی، مقاله یا نماد؛ جهت بازخوانی فوری نسخه جدید بدون معطلی در صف‌های خزش سنتی.
                                                </p>
                                                <div class="sarfee-module-tags">
                                                    <span class="sarfee-module-tag">پینگ آنی تغییرات</span>
                                                    <span class="sarfee-module-tag">حذف کش کهنه در جستجو</span>
                                                    <span class="sarfee-module-tag">گزارش لاگ لحظه‌ای</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="sarfee-module-control">
                                            <span class="sarfee-status-badge <?php echo $is_indexnow_active ? 'badge-active' : 'badge-inactive'; ?>">
                                                <?php if ( $is_indexnow_active ) : ?><span class="sarfee-pulse-dot"></span><?php endif; ?>
                                                <span class="badge-text"><?php echo $is_indexnow_active ? 'فعال' : 'غیرفعال'; ?></span>
                                            </span>
                                            <label class="sarfee-switch">
                                                <input type="checkbox" name="enable_indexnow" value="1" <?php checked( $is_indexnow_active ); ?> onchange="sarfeeToggleModuleCard(this)" />
                                                <span class="sarfee-slider"></span>
                                            </label>
                                        </div>
                                    </div>

                                </div>

                                <script>
                                    function sarfeeToggleModuleCard(input) {
                                        var card = input.closest('.sarfee-module-card');
                                        var badge = card.querySelector('.sarfee-status-badge');
                                        var badgeText = badge.querySelector('.badge-text');
                                        var pulseDot = badge.querySelector('.sarfee-pulse-dot');

                                        if (input.checked) {
                                            card.classList.add('is-active');
                                            badge.className = 'sarfee-status-badge badge-active';
                                            badgeText.textContent = 'فعال';
                                            if (!pulseDot) {
                                                var dot = document.createElement('span');
                                                dot.className = 'sarfee-pulse-dot';
                                                badge.insertBefore(dot, badgeText);
                                            }
                                        } else {
                                            card.classList.remove('is-active');
                                            badge.className = 'sarfee-status-badge badge-inactive';
                                            badgeText.textContent = 'غیرفعال';
                                            if (pulseDot) {
                                                pulseDot.remove();
                                            }
                                        }
                                    }
                                </script>

                                <!-- Dynamic Brand Identity Section -->
                                <div style="margin-top:28px; padding-top:22px; border-top:1px solid #e2e8f0;">
                                    <div style="display:flex; align-items:center; gap:10px; margin-bottom:6px;">
                                        <span style="font-size:20px;">🏷️</span>
                                        <h3 style="margin:0; font-size:16px; color:#0f172a; font-weight:700;">هویت برند و معرفی سایت در پرونده‌های هوش مصنوعی (Brand Identity)</h3>
                                    </div>
                                    <p style="color:#64748b; font-size:13px; margin:0 0 20px; line-height:1.6;">
                                        به صورت پیش‌فرض، نام و معرفی سایت مستقیماً از <strong>تنظیمات عمومی وردپرس</strong> (عنوان سایت و معرفی کوتاه) خوانده می‌شود. در صورت تمایل می‌توانید این مقادیر را به صورت اختصاصی برای فایل‌های <code style="font-family:monospace;">llms.txt</code> شخصی‌سازی کنید:
                                    </p>

                                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(310px, 1fr)); gap:20px; margin-bottom:20px;">
                                        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:18px;">
                                            <label style="display:block; font-size:12px; color:#475569; margin-bottom:6px; font-weight:600;">عنوان سایت در فایل‌های هوش مصنوعی:</label>
                                            <input type="text" name="llms_site_title" value="<?php echo esc_attr( $settings['llms_site_title'] ?? '' ); ?>" placeholder="پیش‌فرض: <?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" style="width:100%; height:38px; border-radius:8px; border:1px solid #cbd5e1; padding:0 12px; font-size:13px; background:#ffffff;" />
                                            <span style="display:block; color:#94a3b8; font-size:11px; margin-top:5px;">در صورت خالی بودن، به صورت خودکار از عنوان سایت در وردپرس («<?php echo esc_html( get_bloginfo( 'name' ) ); ?>») استفاده می‌شود.</span>
                                        </div>

                                        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:18px;">
                                            <label style="display:block; font-size:12px; color:#475569; margin-bottom:6px; font-weight:600;">معرفی کوتاه / توضیح خلاصه (Tagline):</label>
                                            <input type="text" name="llms_site_desc" value="<?php echo esc_attr( $settings['llms_site_desc'] ?? '' ); ?>" placeholder="پیش‌فرض: <?php echo esc_attr( get_bloginfo( 'description' ) ); ?>" style="width:100%; height:38px; border-radius:8px; border:1px solid #cbd5e1; padding:0 12px; font-size:13px; background:#ffffff;" />
                                            <span style="display:block; color:#94a3b8; font-size:11px; margin-top:5px;">در صورت خالی بودن، از معرفی کوتاه وردپرس («<?php echo esc_html( get_bloginfo( 'description' ) ); ?>») استفاده می‌شود.</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Dynamic Legal & Transparency Section (YMYL & E-E-A-T) -->
                                <div style="margin-top:28px; padding-top:22px; border-top:1px solid #e2e8f0;">
                                    <div style="display:flex; align-items:center; gap:10px; margin-bottom:6px;">
                                        <span style="font-size:20px;">⚖️</span>
                                        <h3 style="margin:0; font-size:16px; color:#0f172a; font-weight:700;">صفحات شفافیت حقوقی، سلب مسئولیت و نظارت (YMYL & E-E-A-T)</h3>
                                    </div>
                                    <p style="color:#64748b; font-size:13px; margin:0 0 20px; line-height:1.6;">
                                        خزنده‌ها و مدل‌های زبانی (OpenAI ChatGPT, Claude, Google Gemini, Perplexity) برای دایرکتوری‌های مالی اهمیت ویژه‌ای به صفحات سلب ادعای مالی و ثبت شکایات می‌دهند. در این بخش می‌توانید برگه‌های رسمی سایت خود را مشخص نمایید تا به صورت خودکار و استاندارد در پرونده‌های <code style="font-family:monospace;">llms.txt</code> و <code style="font-family:monospace;">llms-full.txt</code> درج گردند:
                                    </p>

                                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(310px, 1fr)); gap:20px; margin-bottom:20px;">
                                        
                                        <!-- Field 1: Disclaimer Page -->
                                        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:18px;">
                                            <div style="display:flex; align-items:center; gap:8px; margin-bottom:12px;">
                                                <span style="font-size:16px;">🛡️</span>
                                                <strong style="color:#0f172a; font-size:14px;">برگه سلب مسئولیت و ضوابط (Disclaimer)</strong>
                                            </div>
                                            
                                            <label style="display:block; font-size:12px; color:#475569; margin-bottom:6px; font-weight:600;">انتخاب برگه از وردپرس:</label>
                                            <select name="disclaimer_page_id" style="width:100%; max-width:100%; height:40px; border-radius:8px; border:1px solid #cbd5e1; padding:0 12px; font-size:13px; margin-bottom:12px; background:#ffffff;">
                                                <option value="0">— انتخاب برگه وردپرس (پیش‌فرض: /disclaimer/) —</option>
                                                <?php if ( ! empty( $wp_pages ) ) : foreach ( $wp_pages as $page_item ) : ?>
                                                    <option value="<?php echo esc_attr( $page_item->ID ); ?>" <?php selected( (int) ( $settings['disclaimer_page_id'] ?? 0 ), $page_item->ID ); ?>>
                                                        <?php echo esc_html( $page_item->post_title ); ?> (<?php echo esc_html( $page_item->post_name ); ?>)
                                                    </option>
                                                <?php endforeach; endif; ?>
                                            </select>

                                            <label style="display:block; font-size:12px; color:#475569; margin-bottom:6px; font-weight:600;">یا آدرس دلخواه (در صورت نبود برگه):</label>
                                            <input type="url" name="disclaimer_custom_url" value="<?php echo esc_attr( $settings['disclaimer_custom_url'] ?? '' ); ?>" placeholder="<?php echo esc_url( home_url( '/disclaimer/' ) ); ?>" style="width:100%; height:38px; border-radius:8px; border:1px solid #cbd5e1; padding:0 12px; font-size:13px; direction:ltr; text-align:left; margin-bottom:12px; background:#ffffff;" />

                                            <label style="display:block; font-size:12px; color:#475569; margin-bottom:6px; font-weight:600;">عنوان نمایشی لینک در پرونده هوش مصنوعی:</label>
                                            <input type="text" name="disclaimer_label" value="<?php echo esc_attr( $settings['disclaimer_label'] ?? '' ); ?>" placeholder="مطالعه متن کامل سلب مسئولیت و ضوابط حقوقی (Disclaimer)" style="width:100%; height:38px; border-radius:8px; border:1px solid #cbd5e1; padding:0 12px; font-size:13px; background:#ffffff;" />

                                            <?php 
                                            $current_disclaimer_url = '';
                                            if ( ! empty( $settings['disclaimer_page_id'] ) ) {
                                                $current_disclaimer_url = get_permalink( (int) $settings['disclaimer_page_id'] );
                                            } elseif ( ! empty( $settings['disclaimer_custom_url'] ) ) {
                                                $current_disclaimer_url = $settings['disclaimer_custom_url'];
                                            } else {
                                                $disc_p = get_page_by_path( 'disclaimer' );
                                                $current_disclaimer_url = $disc_p ? get_permalink( $disc_p->ID ) : home_url( '/disclaimer/' );
                                            }
                                            ?>
                                            <div style="margin-top:10px; font-size:11px; color:#0369a1; background:#f0f9ff; padding:7px 10px; border-radius:6px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:6px;">
                                                <span>لینک فعال:</span>
                                                <a href="<?php echo esc_url( $current_disclaimer_url ); ?>" target="_blank" style="direction:ltr; text-decoration:none; font-family:monospace; font-weight:600; color:#0284c7;">
                                                    <?php echo esc_html( $current_disclaimer_url ); ?> ↗
                                                </a>
                                            </div>
                                        </div>

                                        <!-- Field 2: Report Content / Dispute Page -->
                                        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:18px;">
                                            <div style="display:flex; align-items:center; gap:8px; margin-bottom:12px;">
                                                <span style="font-size:16px;">📢</span>
                                                <strong style="color:#0f172a; font-size:14px;">برگه گزارش تخلف و شکایات (Report Content)</strong>
                                            </div>
                                            
                                            <label style="display:block; font-size:12px; color:#475569; margin-bottom:6px; font-weight:600;">انتخاب برگه از وردپرس:</label>
                                            <select name="report_content_page_id" style="width:100%; max-width:100%; height:40px; border-radius:8px; border:1px solid #cbd5e1; padding:0 12px; font-size:13px; margin-bottom:12px; background:#ffffff;">
                                                <option value="0">— انتخاب برگه وردپرس (پیش‌فرض: /report-content/) —</option>
                                                <?php if ( ! empty( $wp_pages ) ) : foreach ( $wp_pages as $page_item ) : ?>
                                                    <option value="<?php echo esc_attr( $page_item->ID ); ?>" <?php selected( (int) ( $settings['report_content_page_id'] ?? 0 ), $page_item->ID ); ?>>
                                                        <?php echo esc_html( $page_item->post_title ); ?> (<?php echo esc_html( $page_item->post_name ); ?>)
                                                    </option>
                                                <?php endforeach; endif; ?>
                                            </select>

                                            <label style="display:block; font-size:12px; color:#475569; margin-bottom:6px; font-weight:600;">یا آدرس دلخواه (در صورت نبود برگه):</label>
                                            <input type="url" name="report_content_custom_url" value="<?php echo esc_attr( $settings['report_content_custom_url'] ?? '' ); ?>" placeholder="<?php echo esc_url( home_url( '/report-content/' ) ); ?>" style="width:100%; height:38px; border-radius:8px; border:1px solid #cbd5e1; padding:0 12px; font-size:13px; direction:ltr; text-align:left; margin-bottom:12px; background:#ffffff;" />

                                            <label style="display:block; font-size:12px; color:#475569; margin-bottom:6px; font-weight:600;">عنوان نمایشی لینک در پرونده هوش مصنوعی:</label>
                                            <input type="text" name="report_content_label" value="<?php echo esc_attr( $settings['report_content_label'] ?? '' ); ?>" placeholder="گزارش اطلاعات نادرست، درخواست اصلاح یا ثبت شکایت (Report Content)" style="width:100%; height:38px; border-radius:8px; border:1px solid #cbd5e1; padding:0 12px; font-size:13px; background:#ffffff;" />

                                            <?php 
                                            $current_report_url = '';
                                            if ( ! empty( $settings['report_content_page_id'] ) ) {
                                                $current_report_url = get_permalink( (int) $settings['report_content_page_id'] );
                                            } elseif ( ! empty( $settings['report_content_custom_url'] ) ) {
                                                $current_report_url = $settings['report_content_custom_url'];
                                            } else {
                                                $rep_p = get_page_by_path( 'report-content' );
                                                $current_report_url = $rep_p ? get_permalink( $rep_p->ID ) : home_url( '/report-content/' );
                                            }
                                            ?>
                                            <div style="margin-top:10px; font-size:11px; color:#0369a1; background:#f0f9ff; padding:7px 10px; border-radius:6px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:6px;">
                                                <span>لینک فعال:</span>
                                                <a href="<?php echo esc_url( $current_report_url ); ?>" target="_blank" style="direction:ltr; text-decoration:none; font-family:monospace; font-weight:600; color:#0284c7;">
                                                    <?php echo esc_html( $current_report_url ); ?> ↗
                                                </a>
                                            </div>
                                        </div>

                                    </div>

                                    <!-- Field 3: Custom Disclaimer Statement -->
                                    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:18px;">
                                        <label style="display:block; font-size:13px; color:#0f172a; margin-bottom:6px; font-weight:700;">
                                            📝 متن بیانیه سلب مسئولیت و بی‌طرفی در پرونده‌های هوش مصنوعی:
                                        </label>
                                        <span style="display:block; color:#64748b; font-size:12px; margin-bottom:8px;">
                                            این متن در بخش سلب مسئولیت پرونده‌های llms.txt و llms-full.txt مستقیماً به مدل‌های هوش مصنوعی ابلاغ می‌شود:
                                        </span>
                                        <textarea name="disclaimer_intro_text" rows="2" style="width:100%; border-radius:8px; border:1px solid #cbd5e1; padding:10px 12px; font-size:13px; line-height:1.7; background:#ffffff;" placeholder="وب‌سایت <?php echo esc_attr( $site_name ); ?> یک پایگاه اطلاعاتی، تحریریه و دایرکتوری مستقل است. داده‌ها و نرخ‌ها صرفاً جهت اطلاع‌رسانی و مقایسه منتشر می‌شوند و مشاوره مالی، سرمایه‌گذاری یا تضمین معامله محسوب نمی‌شوند:"><?php echo esc_textarea( $settings['disclaimer_intro_text'] ?? '' ); ?></textarea>
                                    </div>

                                </div>

                                <div style="margin-top:24px; padding-top:18px; border-top:1px solid #f1f5f9;">
                                    <button type="submit" name="sarfee_ai_save_settings" class="sarfee-btn sarfee-btn-primary sarfee-btn-lg">
                                        <span>💾</span>
                                        <span>ذخیره تغییرات تنظیمات</span>
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Mini IndexNow Preview Card -->
                        <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; padding:24px; box-shadow:0 1px 3px rgba(0,0,0,0.02);">
                            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #f1f5f9; padding-bottom:12px; margin-bottom:14px; flex-wrap:wrap; gap:10px;">
                                <div>
                                    <h3 style="margin:0; font-size:16px; color:#0f172a;">آخرین پینگ‌های ارسال‌شده به IndexNow</h3>
                                    <span style="font-size:12px; color:#64748b;">نمایش ۴ فعالیت اخیر سامانه ایندکس آنی</span>
                                </div>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=sarfee-ai-engine&tab=indexnow' ) ); ?>" class="sarfee-btn sarfee-btn-secondary sarfee-btn-sm">
                                    <span>مشاهده تمام گزارش‌ها و صفحه‌بندی ←</span>
                                </a>
                            </div>

                            <?php if ( ! empty( $logs ) ) : ?>
                            <div style="overflow-x:auto;">
                                <table class="widefat striped" style="border:none; font-size:12px;">
                                    <thead>
                                        <tr style="background:#f8fafc;">
                                            <th style="padding:8px 10px; width:130px;">زمان</th>
                                            <th style="padding:8px 10px;">آدرس‌ها</th>
                                            <th style="padding:8px 10px; width:70px; text-align:center;">کد</th>
                                            <th style="padding:8px 10px;">وضعیت</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ( array_slice( $logs, 0, 4 ) as $l ) : 
                                            $is_ok = in_array( $l['status'] ?? 0, [ 200, 202 ], true );
                                        ?>
                                        <tr>
                                            <td style="direction:ltr; text-align:right; font-family:monospace; color:#475569; padding:8px 10px;"><?php echo esc_html( $l['time'] ?? '' ); ?></td>
                                            <td style="direction:ltr; text-align:right; padding:8px 10px;">
                                                <?php 
                                                $u_list = (array) ( $l['urls'] ?? [] );
                                                echo '<a href="' . esc_url( $u_list[0] ?? '' ) . '" target="_blank" style="color:#0284c7; text-decoration:none;">' . esc_html( $u_list[0] ?? '' ) . ' ↗</a>';
                                                if ( count( $u_list ) > 1 ) {
                                                    echo ' <span style="color:#64748b; font-size:11px;">(+' . ( count( $u_list ) - 1 ) . ' صفحه)</span>';
                                                }
                                                ?>
                                            </td>
                                            <td style="padding:8px 10px; text-align:center;">
                                                <span style="font-weight:700; font-size:11px; padding:2px 7px; border-radius:8px; background:<?php echo $is_ok ? '#dcfce7' : '#fef3c7'; ?>; color:<?php echo $is_ok ? '#15803d' : '#92400e'; ?>;">
                                                    <?php echo esc_html( $l['status'] ?? '' ); ?>
                                                </span>
                                            </td>
                                            <td style="padding:8px 10px; color:#64748b; font-size:11px;"><?php echo esc_html( wp_trim_words( $l['message'] ?? '', 10, '...' ) ); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else : ?>
                            <div style="text-align:center; padding:20px; color:#64748b; font-size:13px;">هنوز هیچ پینگی ارسال نشده است.</div>
                            <?php endif; ?>
                        </div>

                    </div>

                    <!-- LEFT COLUMN (SIDEBAR): Bot Traffic Widget & Quick Tools -->
                    <div style="display:flex; flex-direction:column; gap:24px;">
                        
                        <!-- Widget 1: AI Bot Traffic Summary Widget -->
                        <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; padding:22px; box-shadow:0 1px 3px rgba(0,0,0,0.02);">
                            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #f1f5f9; padding-bottom:12px; margin-bottom:14px;">
                                <strong style="color:#0f172a; font-size:15px; display:flex; align-items:center; gap:6px;">
                                    <span>🌐</span>
                                    <span>ترافیک زنده بات‌های AI</span>
                                </strong>
                                <a href="<?php echo esc_url( $traffic_url ); ?>" class="sarfee-btn sarfee-btn-secondary sarfee-btn-sm" style="padding:4px 10px; font-size:11px;">لاگ کامل ←</a>
                            </div>

                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-bottom:14px;">
                                <?php
                                $tracked_preview = [
                                    'GPTBot (ChatGPT)'         => [ 'color' => '#10a37f', 'name' => 'ChatGPT' ],
                                    'PerplexityBot'            => [ 'color' => '#20808d', 'name' => 'Perplexity' ],
                                    'ClaudeBot (Anthropic)'    => [ 'color' => '#d97706', 'name' => 'Claude' ],
                                    'Google-Extended (Gemini)' => [ 'color' => '#2563eb', 'name' => 'Gemini' ],
                                ];
                                foreach ( $tracked_preview as $b_key => $b_meta ) :
                                    $tot = $bot_counts[ $b_key ]['total'] ?? 0;
                                ?>
                                <div style="background:#f8fafc; border:1px solid #f1f5f9; border-radius:8px; padding:10px; text-align:center;">
                                    <span style="font-size:11px; color:#64748b; display:block; margin-bottom:2px;"><?php echo esc_html( $b_meta['name'] ); ?></span>
                                    <strong style="font-size:17px; color:<?php echo esc_attr( $b_meta['color'] ); ?>;"><?php echo number_format_i18n( $tot ); ?></strong>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <?php if ( ! empty( $bot_logs ) ) : ?>
                                <div style="font-size:11px; color:#64748b; background:#f8fafc; padding:8px 10px; border-radius:6px; margin-bottom:14px; line-height:1.6;">
                                    آخرین خزش: <strong style="color:#0f172a;"><?php echo esc_html( $bot_logs[0]['bot'] ?? '' ); ?></strong><br>
                                    ساعت: <code><?php echo esc_html( $bot_logs[0]['time'] ?? '' ); ?></code>
                                </div>
                            <?php endif; ?>

                            <a href="<?php echo esc_url( $traffic_url ); ?>" class="sarfee-btn sarfee-btn-secondary sarfee-btn-block">
                                <span>📊</span>
                                <span>ورود به مانیتورینگ زنده بات‌ها ↗</span>
                            </a>
                        </div>

                        <!-- Widget 2: Quick Action Tools -->
                        <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; padding:22px; box-shadow:0 1px 3px rgba(0,0,0,0.02);">
                            <strong style="color:#0f172a; font-size:15px; display:block; border-bottom:1px solid #f1f5f9; padding-bottom:10px; margin-bottom:16px;">
                                <span>🛠</span> ابزارهای مدیریت و نگهداری
                            </strong>

                            <!-- Cache Purge -->
                            <div style="margin-bottom:16px; padding-bottom:14px; border-bottom:1px dashed #f1f5f9;">
                                <span style="font-size:13px; font-weight:600; color:#334155; display:block; margin-bottom:4px;">تخلیه کش llms.txt</span>
                                <p style="font-size:12px; color:#64748b; margin:0 0 10px; line-height:1.5;">فایل‌های مارک‌داون هر ۱۲ ساعت کش می‌شوند:</p>
                                <form method="post" action="">
                                    <?php wp_nonce_field( 'sarfee_ai_clear_cache_action', 'sarfee_ai_cache_nonce' ); ?>
                                    <button type="submit" name="sarfee_ai_clear_cache" class="sarfee-btn sarfee-btn-secondary sarfee-btn-block">
                                        <span>🧹</span>
                                        <span>تخلیه دستی کش</span>
                                    </button>
                                </form>
                            </div>

                            <!-- Bulk Submit IndexNow -->
                            <div style="margin-bottom:16px; padding-bottom:14px; border-bottom:1px dashed #f1f5f9;">
                                <span style="font-size:13px; font-weight:600; color:#334155; display:block; margin-bottom:4px;">ارسال دسته‌جمعی به IndexNow ⚡</span>
                                <p style="font-size:12px; color:#64748b; margin:0 0 10px; line-height:1.5;">مخابره فوری کلیه صرافی‌ها و نمادها به بینگ:</p>
                                <form method="post" action="">
                                    <?php wp_nonce_field( 'sarfee_ai_bulk_indexnow_action', 'sarfee_ai_bulk_indexnow_nonce' ); ?>
                                    <button type="submit" name="sarfee_ai_bulk_indexnow" class="sarfee-btn sarfee-btn-success sarfee-btn-block">
                                        <span>⚡</span>
                                        <span>ارسال کلیه صفحات سایت</span>
                                    </button>
                                </form>
                            </div>

                            <!-- Test Single IndexNow -->
                            <div>
                                <span style="font-size:13px; font-weight:600; color:#334155; display:block; margin-bottom:4px;">تست ارتباط پینگ تکی</span>
                                <form method="post" action="">
                                    <?php wp_nonce_field( 'sarfee_ai_test_indexnow_action', 'sarfee_ai_indexnow_nonce' ); ?>
                                    <button type="submit" name="sarfee_ai_test_indexnow" class="sarfee-btn sarfee-btn-primary sarfee-btn-block">
                                        <span>📡</span>
                                        <span>ارسال پینگ تستی</span>
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Widget 3: Documentation Callout -->
                        <div style="background:linear-gradient(135deg, #f0fdf4 0%, #e0f2fe 100%); border:1px solid #bae6fd; border-radius:14px; padding:20px;">
                            <strong style="color:#0369a1; font-size:14px; display:block; margin-bottom:6px;">راهنما و استراتژی تولید محتوا</strong>
                            <p style="color:#334155; font-size:12px; margin:0 0 14px; line-height:1.7;">توضیحات کامل درباره نحوه بهینه‌سازی مقالات و صرافی‌ها جهت دریافت حداکثر نقل‌قول و رتبه در ChatGPT و Perplexity.</p>
                            <a href="<?php echo esc_url( $docs_url ); ?>" class="sarfee-btn sarfee-btn-secondary sarfee-btn-block" style="color:#0369a1 !important; border-color:#7dd3fc; background:#ffffff;">
                                <span>📖</span>
                                <span>مطالعه مستندات کامل سئو هوش مصنوعی ↗</span>
                            </a>
                        </div>

                    </div>

                </div>

            <?php elseif ( $active_tab === 'indexnow' ) : ?>
                <!-- TAB 2: INDEXNOW FULL DASHBOARD -->
                
                <?php
                $inow_paged     = max( 1, (int) ( $_GET['inow_paged'] ?? 1 ) );
                $inow_per_page  = 10;
                $inow_total     = count( $logs );
                $inow_pages     = max( 1, (int) ceil( $inow_total / $inow_per_page ) );
                if ( $inow_paged > $inow_pages ) {
                    $inow_paged = $inow_pages;
                }
                $paged_inow_logs = array_slice( $logs, ( $inow_paged - 1 ) * $inow_per_page, $inow_per_page );
                $success_count   = count( array_filter( $logs, fn( $item ) => in_array( $item['status'] ?? 0, [ 200, 202 ], true ) ) );
                $export_inow_url = wp_nonce_url( admin_url( 'admin.php?page=sarfee-ai-engine&action=sarfee_ai_export_indexnow_csv' ), 'sarfee_ai_export_indexnow_action', 'nonce' );
                ?>

                <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; padding:24px; margin-bottom:24px; box-shadow:0 1px 3px rgba(0,0,0,0.02);">
                    
                    <!-- Header & Summary Counters -->
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px; border-bottom:1px solid #f1f5f9; padding-bottom:18px; margin-bottom:18px;">
                        <div>
                            <div style="display:flex; align-items:center; gap:10px;">
                                <h2 style="margin:0; font-size:18px; color:#0f172a;">گزارش جامع ارسال به IndexNow</h2>
                                <span style="font-size:12px; background:#eff6ff; color:#2563eb; font-weight:700; padding:3px 10px; border-radius:12px;">کل سیگنال‌ها: <?php echo number_format_i18n( $inow_total ); ?></span>
                                <?php if ( $success_count > 0 ) : ?>
                                    <span style="font-size:12px; background:#dcfce7; color:#15803d; font-weight:700; padding:3px 10px; border-radius:12px;">موفق: <?php echo number_format_i18n( $success_count ); ?></span>
                                <?php endif; ?>
                            </div>
                            <span style="font-size:13px; color:#64748b; display:block; margin-top:4px;">سیگنال‌های ارسال‌شده به موتورهای جستجو (Microsoft Bing, Copilot, Yandex, Seznam)</span>
                        </div>

                        <!-- Action Toolbar -->
                        <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                            <!-- Bulk Submit -->
                            <form method="post" action="" style="display:inline;">
                                <?php wp_nonce_field( 'sarfee_ai_bulk_indexnow_action', 'sarfee_ai_bulk_indexnow_nonce' ); ?>
                                <button type="submit" name="sarfee_ai_bulk_indexnow" class="sarfee-btn sarfee-btn-primary">
                                    <span>⚡</span>
                                    <span>ارسال دسته‌جمعی تمام صفحات</span>
                                </button>
                            </form>

                            <?php if ( ! empty( $logs ) ) : ?>
                                <!-- Export CSV -->
                                <a href="<?php echo esc_url( $export_inow_url ); ?>" class="sarfee-btn sarfee-btn-secondary">
                                    <span>📥</span>
                                    <span>خروجی اکسل (CSV)</span>
                                </a>

                                <!-- Clear Logs -->
                                <form method="post" action="" style="display:inline;" onsubmit="return confirm('آیا از پاکسازی تاریخچه گزارش‌های IndexNow اطمینان دارید؟');">
                                    <?php wp_nonce_field( 'sarfee_ai_clear_indexnow_action', 'sarfee_ai_clear_indexnow_nonce' ); ?>
                                    <button type="submit" name="sarfee_ai_clear_indexnow_logs" class="sarfee-btn sarfee-btn-danger">
                                        <span>🗑</span>
                                        <span>پاکسازی لاگ‌ها</span>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ( $this->engine->indexnow->is_local_host() ) : ?>
                        <div style="background:#fffbeb; border:1px solid #fef3c7; border-radius:10px; padding:12px 16px; margin-bottom:18px; font-size:13px; color:#78350f; line-height:1.7;">
                            <strong>💡 نکته توسعه در محیط لوکال:</strong> سرورهای بینگ و IndexNow آدرس‌های با پیشوند localhost یا پورت‌های لوکال (مانند <code>localhost:10013</code>) را به دلیل عدم دسترسی اینترنت به کامپیوتر شخصی و جلوگیری از اسپم، با خطای ۴۲۹ (TooManyRequests) پاسخ می‌دهند. به محض انتقال این فایل‌ها به هاست اصلی (دامنه رسمی)، وضعیت به ۲۰۰ و ۲۰۲ تبدیل خواهد شد.
                        </div>
                    <?php endif; ?>

                    <?php if ( ! empty( $paged_inow_logs ) ) : ?>
                    <div style="overflow-x:auto;">
                        <table class="widefat striped" style="border:none; font-size:13px;">
                            <thead>
                                <tr style="background:#f8fafc;">
                                    <th style="padding:12px 14px; width:150px;">زمان ارسال</th>
                                    <th style="padding:12px 14px;">آدرس‌های ارسال‌شده</th>
                                    <th style="padding:12px 14px; width:90px; text-align:center;">وضعیت</th>
                                    <th style="padding:12px 14px;">پیام سرور IndexNow</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $paged_inow_logs as $l ) : 
                                    $is_ok = in_array( $l['status'] ?? 0, [ 200, 202 ], true );
                                ?>
                                <tr>
                                    <td style="direction:ltr; text-align:right; font-family:monospace; color:#475569; padding:12px 14px;"><?php echo esc_html( $l['time'] ?? '' ); ?></td>
                                    <td style="direction:ltr; text-align:right; padding:12px 14px;">
                                        <?php 
                                        $url_list = (array) ( $l['urls'] ?? [] );
                                        if ( count( $url_list ) > 3 ) {
                                            $shown = array_slice( $url_list, 0, 3 );
                                            foreach ( $shown as $u ) {
                                                echo '<div style="margin-bottom:3px;"><a href="' . esc_url( $u ) . '" target="_blank" style="color:#0284c7; text-decoration:none;">' . esc_html( $u ) . ' ↗</a></div>';
                                            }
                                            echo '<div style="color:#64748b; font-size:12px; margin-top:4px;">+ و ' . ( count( $url_list ) - 3 ) . ' آدرس دیگر در این ارسال گروهی</div>';
                                        } else {
                                            foreach ( $url_list as $u ) {
                                                echo '<div style="margin-bottom:3px;"><a href="' . esc_url( $u ) . '" target="_blank" style="color:#0284c7; text-decoration:none;">' . esc_html( $u ) . ' ↗</a></div>';
                                            }
                                        }
                                        ?>
                                    </td>
                                    <td style="padding:12px 14px; text-align:center;">
                                        <span style="font-weight:700; font-size:12px; padding:4px 10px; border-radius:12px; background:<?php echo $is_ok ? '#dcfce7' : ( ( $l['status'] ?? 0 ) === 429 ? '#fef3c7' : '#fee2e2' ); ?>; color:<?php echo $is_ok ? '#15803d' : ( ( $l['status'] ?? 0 ) === 429 ? '#92400e' : '#dc2626' ); ?>;">
                                            <?php echo esc_html( $l['status'] ?? '' ); ?>
                                        </span>
                                    </td>
                                    <td style="padding:12px 14px; color:#64748b; font-size:13px; line-height:1.6;"><?php echo esc_html( $l['message'] ?? '' ); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination Bar -->
                    <?php if ( $inow_pages > 1 ) : ?>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:20px; padding-top:16px; border-top:1px solid #f1f5f9; flex-wrap:wrap; gap:12px;">
                        <span style="font-size:13px; color:#64748b;">
                            صفحه <?php echo number_format_i18n( $inow_paged ); ?> از <?php echo number_format_i18n( $inow_pages ); ?> (نمایش <?php echo number_format_i18n( count( $paged_inow_logs ) ); ?> از مجموع <?php echo number_format_i18n( $inow_total ); ?> گزارش)
                        </span>
                        <div style="display:flex; gap:6px; align-items:center;">
                            <?php if ( $inow_paged > 1 ) : ?>
                                <a href="<?php echo esc_url( add_query_arg( [ 'tab' => 'indexnow', 'inow_paged' => $inow_paged - 1 ] ) ); ?>" class="sarfee-btn sarfee-btn-secondary sarfee-btn-sm">‹ قبلی</a>
                            <?php endif; ?>

                            <?php for ( $i = 1; $i <= $inow_pages; $i++ ) : ?>
                                <?php if ( $i === $inow_paged ) : ?>
                                    <span class="sarfee-btn sarfee-btn-primary sarfee-btn-sm" style="font-weight:700;"><?php echo $i; ?></span>
                                <?php elseif ( $i <= 3 || $i >= $inow_pages - 1 || abs( $i - $inow_paged ) <= 1 ) : ?>
                                    <a href="<?php echo esc_url( add_query_arg( [ 'tab' => 'indexnow', 'inow_paged' => $i ] ) ); ?>" class="sarfee-btn sarfee-btn-secondary sarfee-btn-sm"><?php echo $i; ?></a>
                                <?php elseif ( $i === 4 || $i === $inow_pages - 2 ) : ?>
                                    <span style="padding:0 4px; color:#94a3b8;">...</span>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if ( $inow_paged < $inow_pages ) : ?>
                                <a href="<?php echo esc_url( add_query_arg( [ 'tab' => 'indexnow', 'inow_paged' => $inow_paged + 1 ] ) ); ?>" class="sarfee-btn sarfee-btn-secondary sarfee-btn-sm">بعدی ›</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php else : ?>
                    <div style="text-align:center; padding:36px; color:#64748b; font-size:14px; background:#f8fafc; border-radius:10px;">
                        <p style="margin:0 0 8px; font-weight:600; color:#334155;">هنوز هیچ ارسالی به IndexNow ثبت نشده است.</p>
                        <p style="margin:0;">می‌توانید با کلیک روی دکمه «ارسال دسته‌جمعی تمام صفحات» یک ارسال همگانی را آغاز نمایید.</p>
                    </div>
                    <?php endif; ?>

                </div>

            <?php elseif ( $active_tab === 'tools' ) : ?>
                <!-- TAB 3: TOOLS & MAINTENANCE -->
                
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap:20px; margin-bottom:24px;">
                    
                    <!-- Tool 1: Cache Purge -->
                    <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; padding:24px; box-shadow:0 1px 3px rgba(0,0,0,0.02);">
                        <strong style="font-size:16px; color:#0f172a; display:flex; align-items:center; gap:8px; margin-bottom:10px;">
                            <span>🧹</span>
                            <span>تخلیه و بازسازی حافظه موقت (Cache Purge)</span>
                        </strong>
                        <p style="color:#64748b; font-size:13px; line-height:1.7; margin:0 0 16px;">
                            فایل‌های <code>/llms.txt</code> و <code>/llms-full.txt</code> برای حفظ سرعت بالای سرور، به مدت ۱۲ ساعت در Transient وردپرس کش می‌شوند. هر زمان تغییر مهمی در اسامی یا اطلاعات صرافی‌ها دادید، می‌توانید کش را فوراً خالی کنید:
                        </p>
                        <form method="post" action="">
                            <?php wp_nonce_field( 'sarfee_ai_clear_cache_action', 'sarfee_ai_cache_nonce' ); ?>
                            <button type="submit" name="sarfee_ai_clear_cache" class="sarfee-btn sarfee-btn-primary">
                                <span>🧹</span>
                                <span>تخلیه و بازسازی آنی کش</span>
                            </button>
                        </form>
                    </div>

                    <!-- Tool 2: Bulk Submit IndexNow -->
                    <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; padding:24px; box-shadow:0 1px 3px rgba(0,0,0,0.02);">
                        <strong style="font-size:16px; color:#0f172a; display:flex; align-items:center; gap:8px; margin-bottom:10px;">
                            <span>⚡</span>
                            <span>ارسال دسته‌جمعی به IndexNow (Bulk Ping)</span>
                        </strong>
                        <p style="color:#64748b; font-size:13px; line-height:1.7; margin:0 0 16px;">
                            این عملیات تمام صرافی‌ها، نمادهای ارزی، مقالات وبلاگ و صفحات فرود را استخراج کرده و به صورت یک پکیج واحد به سرورهای مایکروسافت بینگ، کوپایلت و یاندکس ارسال می‌کند:
                        </p>
                        <form method="post" action="">
                            <?php wp_nonce_field( 'sarfee_ai_bulk_indexnow_action', 'sarfee_ai_bulk_indexnow_nonce' ); ?>
                            <button type="submit" name="sarfee_ai_bulk_indexnow" class="sarfee-btn sarfee-btn-success">
                                <span>⚡</span>
                                <span>ارسال دسته‌جمعی تمام صفحات سایت</span>
                            </button>
                        </form>
                    </div>

                    <!-- Tool 3: Single Test Ping -->
                    <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; padding:24px; box-shadow:0 1px 3px rgba(0,0,0,0.02);">
                        <strong style="font-size:16px; color:#0f172a; display:flex; align-items:center; gap:8px; margin-bottom:10px;">
                            <span>📡</span>
                            <span>تست پینگ تکی پروتکل IndexNow</span>
                        </strong>
                        <p style="color:#64748b; font-size:13px; line-height:1.7; margin:0 0 16px;">
                            ارسال یک سیگنال تستی از صفحه اصلی سایت برای بررسی سلامت اتصال، کلید امنیتی و دسترسی شبکه به سرورهای IndexNow:
                        </p>
                        <form method="post" action="">
                            <?php wp_nonce_field( 'sarfee_ai_test_indexnow_action', 'sarfee_ai_indexnow_nonce' ); ?>
                            <button type="submit" name="sarfee_ai_test_indexnow" class="sarfee-btn sarfee-btn-primary">
                                <span>📡</span>
                                <span>ارسال پینگ آزمایشی از صفحه اصلی</span>
                            </button>
                        </form>
                    </div>

                    <!-- Tool 4: System Endpoints Verification -->
                    <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; padding:24px; box-shadow:0 1px 3px rgba(0,0,0,0.02);">
                        <strong style="font-size:16px; color:#0f172a; display:flex; align-items:center; gap:8px; margin-bottom:10px;">
                            <span>🔍</span>
                            <span>بررسی سریع فایل‌های سیستمی و سلامت اندپوینت‌ها</span>
                        </strong>
                        <ul style="margin:0; padding:0; list-style:none; display:flex; flex-direction:column; gap:10px; font-size:13px;">
                            <li style="display:flex; justify-content:space-between; align-items:center; padding:8px 0; border-bottom:1px solid #f8fafc;">
                                <span>فایل استاندارد <code>/llms.txt</code>:</span>
                                <a href="<?php echo esc_url( $llms_url ); ?>" target="_blank" class="sarfee-btn sarfee-btn-secondary sarfee-btn-sm">
                                    <span>تست لینک</span> <span>↗</span>
                                </a>
                            </li>
                            <li style="display:flex; justify-content:space-between; align-items:center; padding:8px 0; border-bottom:1px solid #f8fafc;">
                                <span>فایل جامع <code>/llms-full.txt</code>:</span>
                                <a href="<?php echo esc_url( $full_url ); ?>" target="_blank" class="sarfee-btn sarfee-btn-secondary sarfee-btn-sm">
                                    <span>تست لینک</span> <span>↗</span>
                                </a>
                            </li>
                            <li style="display:flex; justify-content:space-between; align-items:center; padding:8px 0; border-bottom:1px solid #f8fafc;">
                                <span>سیاست کراولرها <code>/ai.txt</code>:</span>
                                <a href="<?php echo esc_url( $ai_url ); ?>" target="_blank" class="sarfee-btn sarfee-btn-secondary sarfee-btn-sm">
                                    <span>تست لینک</span> <span>↗</span>
                                </a>
                            </li>
                            <li style="display:flex; justify-content:space-between; align-items:center; padding:8px 0; border-bottom:1px solid #f8fafc;">
                                <span>کلید امنیتی ایندکس‌نو <code>/{key}.txt</code>:</span>
                                <a href="<?php echo esc_url( $key_url ); ?>" target="_blank" class="sarfee-btn sarfee-btn-secondary sarfee-btn-sm">
                                    <span>تست لینک</span> <span>↗</span>
                                </a>
                            </li>
                        </ul>
                    </div>

                </div>

            <?php endif; ?>

        </div>
        <?php
    }

    /**
     * Returns the official brand SVG logo for each AI crawler/bot
     */
    public function get_bot_brand_svg( string $bot_name, int $size = 20 ): string {
        $bot_name = strtolower( $bot_name );

        if ( strpos( $bot_name, 'gpt' ) !== false || strpos( $bot_name, 'openai' ) !== false || strpos( $bot_name, 'chatgpt' ) !== false ) {
            // Official OpenAI / ChatGPT Rosette Logo
            return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" fill="currentColor"><path d="M22.282 9.821a5.985 5.985 0 0 0-.516-4.91 6.046 6.046 0 0 0-6.51-2.9A6.065 6.065 0 0 0 4.981 4.182a5.985 5.985 0 0 0-3.998 2.9 6.046 6.046 0 0 0 .743 7.097 5.98 5.98 0 0 0 .51 4.911 6.051 6.051 0 0 0 6.515 2.9A5.985 5.985 0 0 0 13.26 24a6.056 6.056 0 0 0 5.772-4.206 5.99 5.99 0 0 0 3.997-2.9 6.056 6.056 0 0 0-.747-7.073zM13.26 22.43a4.476 4.476 0 0 1-2.876-1.04l.141-.081 4.779-2.758a.795.795 0 0 0 .392-.681v-6.737l2.02 1.168a.071.071 0 0 1 .038.052v5.583a4.504 4.504 0 0 1-4.494 4.494zM3.6 18.304a4.47 4.47 0 0 1-.535-3.014l.142.085 4.783 2.758a.771.771 0 0 0 .78 0l5.843-3.368v2.332a.08.08 0 0 1-.033.062L9.74 19.95a4.5 4.5 0 0 1-6.14-1.646zM2.34 7.896a4.485 4.485 0 0 1 2.366-1.973V11.6a.766.766 0 0 0 .388.677l5.814 3.354-2.02 1.169a.076.076 0 0 1-.071 0l-4.83-2.787A4.504 4.504 0 0 1 2.34 7.872zm16.597 3.855l-5.833-3.387 2.015-1.164a.076.076 0 0 1 .071 0l4.83 2.791a4.494 4.494 0 0 1-.676 8.104v-5.677a.79.79 0 0 0-.407-.667zm2.01-3.023l-.141-.085-4.774-2.782a.776.776 0 0 0-.785 0L9.41 9.23V6.897a.066.066 0 0 1 .028-.061l4.83-2.787a4.5 4.5 0 0 1 6.68 4.66zM8.307 12.863l-2.02-1.164a.08.08 0 0 1-.038-.057V6.074a4.5 4.5 0 0 1 7.376-3.454l-.142.08L8.704 5.46a.795.795 0 0 0-.393.681zm1.097-2.365l2.602-1.5 2.607 1.5v3l-2.597 1.5-2.607-1.5z"/></svg>';
        }

        if ( strpos( $bot_name, 'perplexity' ) !== false ) {
            // Official Perplexity Geometric Logo
            return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" fill="currentColor"><path d="M22.3977 7.0896h-2.3106V.0676l-7.5094 6.3542V.1577h-1.1554v6.1966L4.4904 0v7.0896H1.6023v10.3976h2.8882V24l6.932-6.3591v6.2005h1.1554v-6.0469l6.9318 6.1807v-6.4879h2.8882V7.0896zm-3.4657-4.531v4.531h-5.355l5.355-4.531zm-13.2862.0676 4.8691 4.4634H5.6458V2.6262zM2.7576 16.332V8.245h7.8476l-6.1149 6.1147v1.9723H2.7576zm2.8882 5.0404v-3.8852h.0001v-2.6488l5.7763-5.7764v7.0111l-5.7764 5.2993zm12.7086.0248-5.7766-5.1509V9.0618l5.7766 5.7766v6.5588zm2.8882-5.0652h-1.733v-1.9723L13.3948 8.245h7.8478v8.087z"/></svg>';
        }

        if ( strpos( $bot_name, 'claude' ) !== false || strpos( $bot_name, 'anthropic' ) !== false ) {
            // Official Anthropic / Claude Logo
            return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" fill="currentColor"><path d="M17.3041 3.541h-3.6718l6.696 16.918H24Zm-10.6082 0L0 20.459h3.7442l1.3693-3.5527h7.0052l1.3693 3.5528h3.7442L10.5363 3.5409Zm-.3712 10.2232 2.2914-5.9456 2.2914 5.9456Z"/></svg>';
        }

        if ( strpos( $bot_name, 'gemini' ) !== false || strpos( $bot_name, 'google' ) !== false ) {
            // Official Google Gemini 4-point Diamond Star
            return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" fill="currentColor"><path d="M11.04 19.32Q12 21.51 12 24q0-2.49.93-4.68.96-2.19 2.58-3.81t3.81-2.55Q21.51 12 24 12q-2.49 0-4.68-.93a12.3 12.3 0 0 1-3.81-2.58 12.3 12.3 0 0 1-2.58-3.81Q12 2.49 12 0q0 2.49-.96 4.68-.93 2.19-2.55 3.81a12.3 12.3 0 0 1-3.81 2.58Q2.49 12 0 12q2.49 0 4.68.96 2.19.93 3.81 2.55t2.55 3.81"/></svg>';
        }

        if ( strpos( $bot_name, 'apple' ) !== false ) {
            // Official Apple Logo
            return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" fill="currentColor"><path d="M12.152 6.896c-.948 0-2.415-1.078-3.96-1.04-2.04.027-3.91 1.183-4.961 3.014-2.117 3.675-.546 9.103 1.519 12.09 1.013 1.454 2.208 3.09 3.792 3.039 1.52-.065 2.09-.987 3.935-.987 1.831 0 2.35.987 3.96.948 1.637-.026 2.676-1.48 3.676-2.948 1.156-1.688 1.636-3.325 1.662-3.415-.039-.013-3.182-1.221-3.22-4.857-.026-3.04 2.48-4.494 2.597-4.559-1.429-2.09-3.623-2.324-4.39-2.376-2-.156-3.675 1.09-4.61 1.09zM15.53 3.83c.843-1.012 1.4-2.427 1.245-3.83-1.207.052-2.662.805-3.532 1.818-.78.896-1.454 2.338-1.273 3.714 1.338.104 2.715-.688 3.559-1.701"/></svg>';
        }

        // Generic AI Bot SVG Fallback
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="10" rx="2"/><circle cx="12" cy="5" r="2"/><path d="M12 7v4"/><line x1="8" y1="16" x2="8" y2="16"/><line x1="16" y1="16" x2="16" y2="16"/></svg>';
    }

    /**
     * Renders Dedicated AI Bots Traffic Monitoring Submenu Page
     */
    public function render_traffic_page(): void {
        $bot_stats  = get_option( 'sarfee_ai_bot_stats', [] );
        $bot_counts = $bot_stats['counts'] ?? [];
        $bot_logs   = $bot_stats['log'] ?? [];

        $tracked_bots = [
            'GPTBot (ChatGPT)'         => [ 'color' => '#10a37f', 'bg' => '#ecfdf5', 'desc' => 'OpenAI / ChatGPT Search' ],
            'PerplexityBot'            => [ 'color' => '#20808d', 'bg' => '#f0fdfa', 'desc' => 'موتور پاسخ‌گوی Perplexity' ],
            'ClaudeBot (Anthropic)'    => [ 'color' => '#d97706', 'bg' => '#fffbeb', 'desc' => 'مدل‌های Claude 3.5 & 3.7' ],
            'Google-Extended (Gemini)' => [ 'color' => '#2563eb', 'bg' => '#eff6ff', 'desc' => 'Google Gemini & AI Overviews' ],
            'Applebot-Extended'        => [ 'color' => '#334155', 'bg' => '#f1f5f9', 'desc' => 'Apple Intelligence' ],
        ];

        // Filters & Search
        $selected_bot     = sanitize_text_field( $_GET['filter_bot'] ?? '' );
        $search_term      = sanitize_text_field( $_GET['traffic_search'] ?? '' );
        $traffic_paged    = max( 1, (int) ( $_GET['traffic_paged'] ?? 1 ) );
        $traffic_per_page = 15;

        $filtered_logs = $bot_logs;
        if ( ! empty( $selected_bot ) ) {
            $filtered_logs = array_values( array_filter( $filtered_logs, fn( $item ) => ( $item['bot'] ?? '' ) === $selected_bot ) );
        }
        if ( ! empty( $search_term ) ) {
            $filtered_logs = array_values( array_filter( $filtered_logs, fn( $item ) => stripos( $item['url'] ?? '', $search_term ) !== false || stripos( $item['ip'] ?? '', $search_term ) !== false ) );
        }

        $traffic_total = count( $filtered_logs );
        $traffic_pages = max( 1, (int) ceil( $traffic_total / $traffic_per_page ) );
        if ( $traffic_paged > $traffic_pages ) {
            $traffic_paged = $traffic_pages;
        }
        $paged_traffic_logs = array_slice( $filtered_logs, ( $traffic_paged - 1 ) * $traffic_per_page, $traffic_per_page );
        $export_bot_url     = wp_nonce_url( admin_url( 'admin.php?page=sarfee-ai-traffic&action=sarfee_ai_export_bot_csv' ), 'sarfee_ai_export_bot_action', 'nonce' );
        $settings           = get_option( 'sarfee_ai_settings', [] );
        $site_name          = ! empty( $settings['llms_site_title'] ) ? $settings['llms_site_title'] : ( get_bloginfo( 'name' ) ?: 'صرفی' );
        ?>
        <div class="wrap sarfee-admin-wrap" dir="rtl" style="max-width:none; margin: 16px 20px 24px 2px; box-sizing: border-box;">
            <?php $this->render_admin_styles(); ?>

            <!-- Hidden Screen-Reader Heading for WordPress Accessibility & Notice Anchoring -->
            <h1 class="wp-heading-inline screen-reader-text">رصد ترافیک و خزش ربات‌های هوش مصنوعی</h1>

            <!-- Dedicated Notice Container -->
            <div class="sarfee-notices-area" style="margin-bottom:16px;">
                <?php settings_errors( 'sarfee_ai' ); ?>
            </div>

            <!-- Header Banner -->
            <div style="background:linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color:#ffffff; padding:26px 30px; border-radius:16px; margin-bottom:24px; box-shadow:0 4px 20px rgba(0,0,0,0.15); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
                <div>
                    <div style="display:inline-block; background:rgba(16, 185, 129, 0.2); color:#34d399; font-size:12px; font-weight:700; padding:4px 12px; border-radius:20px; margin-bottom:10px;">
                        ● مانیتورینگ زنده خزنده‌های هوش مصنوعی (AI Crawlers)
                    </div>
                    <div role="heading" aria-level="2" style="color:#ffffff; margin:0 0 8px; font-size:24px; font-weight:800; line-height:1.3;">رصد ترافیک و خزش ربات‌های هوش مصنوعی</div>
                    <p style="color:#94a3b8; font-size:13px; margin:0; line-height:1.7;">
                        این صفحه به صورت بلادرنگ ورود و مطالعه‌ی صفحات سایت <?php echo esc_html( $site_name ); ?> توسط مدل‌های زبانی مانند ChatGPT، Perplexity، Claude و Gemini را نمایش می‌دهد.
                    </p>
                </div>
                <div>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=sarfee-ai-engine' ) ); ?>" class="sarfee-btn sarfee-btn-banner-ghost">
                        <span>‹ بازگشت به پیشخوان اصلی</span>
                    </a>
                </div>
            </div>

            <!-- Bot Counter Cards Grid -->
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:14px; margin-bottom:24px;">
                <?php
                foreach ( $tracked_bots as $b_name => $meta ) :
                    $tot   = $bot_counts[ $b_name ]['total'] ?? 0;
                    $today = $bot_counts[ $b_name ]['today'] ?? 0;
                ?>
                <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; padding:18px; box-shadow:0 1px 3px rgba(0,0,0,0.02); transition:transform 0.15s ease, box-shadow 0.15s ease;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px;">
                        <span style="font-size:12px; color:#64748b; font-weight:500;"><?php echo esc_html( $meta['desc'] ); ?></span>
                        <div style="width:36px; height:36px; border-radius:10px; background:<?php echo esc_attr( $meta['bg'] ); ?>; display:flex; align-items:center; justify-content:center; color:<?php echo esc_attr( $meta['color'] ); ?>; flex-shrink:0; box-shadow:0 1px 2px rgba(0,0,0,0.03);">
                            <?php echo $this->get_bot_brand_svg( $b_name, 20 ); ?>
                        </div>
                    </div>
                    <strong style="color:#0f172a; font-size:14px; display:block; margin-bottom:12px;"><?php echo esc_html( $b_name ); ?></strong>
                    <div style="display:flex; justify-content:space-between; align-items:baseline; border-top:1px solid #f1f5f9; padding-top:10px;">
                        <div>
                            <span style="font-size:22px; font-weight:800; color:<?php echo esc_attr( $meta['color'] ); ?>;"><?php echo number_format_i18n( $tot ); ?></span>
                            <span style="font-size:12px; color:#64748b; margin-right:2px;">کل خزش‌ها</span>
                        </div>
                        <span style="font-size:12px; background:#eff6ff; color:#1d4ed8; padding:3px 10px; border-radius:12px; font-weight:600;">
                            امروز: <?php echo number_format_i18n( $today ); ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Simulation Box -->
            <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; padding:20px; margin-bottom:24px; box-shadow:0 1px 3px rgba(0,0,0,0.02);">
                <strong style="color:#0f172a; font-size:15px; display:flex; align-items:center; gap:6px; margin-bottom:10px;">
                    <span>🧪</span>
                    <span>شبیه‌سازی بازدید تستی ربات‌ها (محیط آزمایشی)</span>
                </strong>
                <p style="color:#64748b; font-size:12px; margin:0 0 14px; line-height:1.6;">جهت اطمینان از عملکرد ردیابی، می‌توانید یک درخواست آزمایشی برای هر صفحه دلخواه ثبت کنید:</p>

                <form method="post" action="" style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                    <?php wp_nonce_field( 'sarfee_ai_simulate_bot_action', 'sarfee_ai_simulate_bot_nonce' ); ?>
                    <select dir="ltr" name="simulate_bot_type" style="font-size:13px; padding:6px 12px; border-radius:7px; border:1px solid #cbd5e1;">
                        <option value="gpt">GPTBot (ChatGPT)</option>
                        <option value="perplexity">PerplexityBot</option>
                        <option value="claude">ClaudeBot (Anthropic)</option>
                        <option value="gemini">Google-Extended (Gemini)</option>
                        <option value="apple">Applebot-Extended</option>
                    </select>
                    <input dir="ltr" type="text" id="sarfee_sim_url" name="simulate_url" placeholder="آدرس صفحه (مثلاً /afn/ یا /usd/ یا /london/niavaran/)" style="width:320px; font-size:13px; padding:6px 12px; border-radius:7px; border:1px solid #cbd5e1;" />
                    <button type="submit" name="sarfee_ai_simulate_bot" class="sarfee-btn sarfee-btn-primary sarfee-btn-sm">
                        <span>ثبت بازدید تستی</span>
                    </button>
                    
                    <div style="display:flex; align-items:center; gap:6px; margin-right:10px; flex-wrap:wrap;">
                        <span style="font-size:12px; color:#64748b;">نمونه‌های سریع:</span>
                        <button type="button" class="sarfee-btn sarfee-btn-secondary sarfee-btn-sm" onclick="document.getElementById('sarfee_sim_url').value='/afn/';">افغانی</button>
                        <button type="button" class="sarfee-btn sarfee-btn-secondary sarfee-btn-sm" onclick="document.getElementById('sarfee_sim_url').value='/usd/';">دلار</button>
                        <button type="button" class="sarfee-btn sarfee-btn-secondary sarfee-btn-sm" onclick="document.getElementById('sarfee_sim_url').value='/london/niavaran/';">نیاوران لندن</button>
                        <button type="button" class="sarfee-btn sarfee-btn-secondary sarfee-btn-sm" onclick="document.getElementById('sarfee_sim_url').value='/';">صفحه اصلی</button>
                    </div>
                </form>
            </div>

            <!-- Detailed Visits Table with Search & Pagination -->
            <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; padding:24px; margin-bottom:24px; box-shadow:0 1px 3px rgba(0,0,0,0.02);">
                
                <!-- Table Header & Controls -->
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px; border-bottom:1px solid #f1f5f9; padding-bottom:16px; margin-bottom:16px;">
                    <div>
                        <h3 style="margin:0; font-size:16px; color:#1e293b; display:flex; align-items:center; gap:8px;">
                            <span>لاگ خزش‌های ثبت‌شده ربات‌های هوش مصنوعی</span>
                            <span style="font-size:11px; background:#eff6ff; color:#2563eb; font-weight:700; padding:2px 8px; border-radius:12px;"><?php echo number_format_i18n( $traffic_total ); ?> رکورد</span>
                        </h3>
                        <span style="font-size:12px; color:#64748b; display:block; margin-top:3px;">ردیابی آنی و بلادرنگ مطالعه صفحات سایت توسط مدل‌های زبانی</span>
                    </div>

                    <!-- Actions: Export & Reset -->
                    <div style="display:flex; align-items:center; gap:8px;">
                        <?php if ( ! empty( $bot_logs ) ) : ?>
                            <a href="<?php echo esc_url( $export_bot_url ); ?>" class="sarfee-btn sarfee-btn-secondary sarfee-btn-sm">
                                <span>📥</span>
                                <span>خروجی اکسل (CSV)</span>
                            </a>
                            <form method="post" action="" style="display:inline;" onsubmit="return confirm('آیا از پاکسازی کامل آمار و لاگ خزش‌ها اطمینان دارید؟');">
                                <?php wp_nonce_field( 'sarfee_ai_reset_bot_action', 'sarfee_ai_reset_bot_nonce' ); ?>
                                <button type="submit" name="sarfee_ai_reset_bot_stats" class="sarfee-btn sarfee-btn-danger sarfee-btn-sm">
                                    <span>🗑</span>
                                    <span>پاکسازی لاگ‌ها</span>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Filter & Search Toolbar -->
                <div style="background:#f8fafc; border:1px solid #f1f5f9; border-radius:10px; padding:12px 16px; margin-bottom:18px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                    <form method="get" action="" style="display:flex; align-items:center; gap:10px; flex-wrap:wrap; width:100%;">
                        <input type="hidden" name="page" value="sarfee-ai-traffic" />

                        <div style="display:flex; align-items:center; gap:6px;">
                            <span style="font-size:12px; font-weight:600; color:#475569;">فیلتر ربات:</span>
                            <select dir="ltr" name="filter_bot" style="font-size:12px; padding:5px 10px; border-radius:7px; border:1px solid #cbd5e1;">
                                <option value="">همه ربات‌ها</option>
                                <?php foreach ( array_keys( $tracked_bots ) as $b_opt ) : ?>
                                    <option value="<?php echo esc_attr( $b_opt ); ?>" <?php selected( $selected_bot, $b_opt ); ?>><?php echo esc_html( $b_opt ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div style="display:flex; align-items:center; gap:6px;">
                            <span style="font-size:12px; font-weight:600; color:#475569;">جستجو:</span>
                            <input dir="ltr" type="text" name="traffic_search" value="<?php echo esc_attr( $search_term ); ?>" placeholder="آدرس صفحه یا IP..." style="width:200px; font-size:12px; padding:5px 10px; border-radius:7px; border:1px solid #cbd5e1;" />
                        </div>

                        <button type="submit" class="sarfee-btn sarfee-btn-secondary sarfee-btn-sm">اعمال فیلتر</button>

                        <?php if ( ! empty( $selected_bot ) || ! empty( $search_term ) ) : ?>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=sarfee-ai-traffic' ) ); ?>" class="button button-link button-small" style="color:#64748b; text-decoration:none;">حذف فیلترها (نمایش همه)</a>
                        <?php endif; ?>
                    </form>
                </div>

                <?php if ( ! empty( $paged_traffic_logs ) ) : ?>
                <div style="overflow-x:auto;">
                    <table class="widefat striped" style="border:none; font-size:13px;">
                        <thead>
                            <tr style="background:#f8fafc;">
                                <th style="padding:10px 12px; width:140px;">زمان خزش</th>
                                <th style="padding:10px 12px; width:210px;">ربات هوش مصنوعی</th>
                                <th style="padding:10px 12px;">صفحه بازدیدشده</th>
                                <th style="padding:10px 12px; width:180px;">آی‌پی (IP)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            foreach ( $paged_traffic_logs as $entry ) : 
                                $bot_title = $entry['bot'] ?? '';
                                $b_meta    = $tracked_bots[ $bot_title ] ?? [ 'color' => '#0284c7', 'bg' => '#f0f9ff' ];
                            ?>
                            <tr>
                                <td style="direction:ltr; text-align:right; font-family:monospace; color:#475569; padding:12px;">
                                    <?php echo esc_html( $entry['time'] ?? '' ); ?>
                                </td>
                                <td style="padding:12px;">
                                    <span style="display:inline-flex; align-items:center; gap:8px; font-weight:700; color:<?php echo esc_attr( $b_meta['color'] ); ?>;">
                                        <span style="display:inline-flex; align-items:center; justify-content:center; width:22px; height:22px; border-radius:6px; background:<?php echo esc_attr( $b_meta['bg'] ?? '#f1f5f9' ); ?>; color:<?php echo esc_attr( $b_meta['color'] ); ?>; flex-shrink:0;">
                                            <?php echo $this->get_bot_brand_svg( $bot_title, 14 ); ?>
                                        </span>
                                        <span><?php echo esc_html( $bot_title ); ?></span>
                                    </span>
                                </td>
                                <td style="direction:ltr; text-align:right; padding:12px;">
                                    <a href="<?php echo esc_url( $entry['url'] ?? '' ); ?>" target="_blank" style="text-decoration:none; color:#0284c7; font-weight:500;">
                                        <?php echo esc_html( $entry['url'] ?? '' ); ?> ↗
                                    </a>
                                </td>
                                <td style="direction:ltr; text-align:right; font-family:monospace; color:#64748b; padding:12px;">
                                    <span style="background:#f1f5f9; padding:3px 8px; border-radius:6px; font-size:12px;"><?php echo esc_html( $entry['ip'] ?? '' ); ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Bar -->
                <?php if ( $traffic_pages > 1 ) : ?>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:18px; padding-top:14px; border-top:1px solid #f1f5f9; flex-wrap:wrap; gap:10px;">
                    <span style="font-size:12px; color:#64748b;">
                        صفحه <?php echo number_format_i18n( $traffic_paged ); ?> از <?php echo number_format_i18n( $traffic_pages ); ?> (نمایش <?php echo number_format_i18n( count( $paged_traffic_logs ) ); ?> از مجموع <?php echo number_format_i18n( $traffic_total ); ?> خزش)
                    </span>
                    <div style="display:flex; gap:6px; align-items:center;">
                        <?php if ( $traffic_paged > 1 ) : ?>
                            <a href="<?php echo esc_url( add_query_arg( [ 'traffic_paged' => $traffic_paged - 1, 'filter_bot' => $selected_bot, 'traffic_search' => $search_term ] ) ); ?>" class="sarfee-btn sarfee-btn-secondary sarfee-btn-sm">‹ قبلی</a>
                        <?php endif; ?>

                        <?php for ( $i = 1; $i <= $traffic_pages; $i++ ) : ?>
                            <?php if ( $i === $traffic_paged ) : ?>
                                <span class="sarfee-btn sarfee-btn-primary sarfee-btn-sm" style="font-weight:700;"><?php echo $i; ?></span>
                            <?php elseif ( $i <= 3 || $i >= $traffic_pages - 1 || abs( $i - $traffic_paged ) <= 1 ) : ?>
                                <a href="<?php echo esc_url( add_query_arg( [ 'traffic_paged' => $i, 'filter_bot' => $selected_bot, 'traffic_search' => $search_term ] ) ); ?>" class="sarfee-btn sarfee-btn-secondary sarfee-btn-sm"><?php echo $i; ?></a>
                            <?php elseif ( $i === 4 || $i === $traffic_pages - 2 ) : ?>
                                <span style="padding:0 4px; color:#94a3b8;">...</span>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ( $traffic_paged < $traffic_pages ) : ?>
                            <a href="<?php echo esc_url( add_query_arg( [ 'traffic_paged' => $traffic_paged + 1, 'filter_bot' => $selected_bot, 'traffic_search' => $search_term ] ) ); ?>" class="sarfee-btn sarfee-btn-secondary sarfee-btn-sm">بعدی ›</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php else : ?>
                <div style="text-align:center; padding:32px; color:#64748b; background:#f8fafc; border-radius:10px;">
                    <p style="margin:0 0 8px; font-size:15px; font-weight:600; color:#334155;">هیچ گزارشی یافت نشد.</p>
                    <p style="margin:0; font-size:13px;">با معیارهای جستجوی فعلی یا تا این لحظه خزشی ثبت نشده است.</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Technical Explanation Box -->
            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:20px;">
                <h3 style="margin-top:0; font-size:15px; color:#0f172a; display:flex; align-items:center; gap:8px;">
                    <span style="color:#0284c7;">💡</span>
                    <span>توضیح فنی: نحوه تشخیص ربات‌ها و ثبت IP در محیط لوکال و هاست اصلی</span>
                </h3>
                <ul style="color:#475569; font-size:13px; line-height:2; margin:10px 0 0; padding-right:20px;">
                    <li><strong>چرا در محیط لوکال، IP به صورت <code>::1</code> نمایش داده می‌شود؟</strong> عبارت <code>::1</code> معادل آدرس استاندارد لوکال‌هاست در پروتکل IPv6 است. هر زمان که تستی از طریق مرورگر، افزونه یا دستورات خط فرمان در کامپیوتر خودتان به سایت لوکال ارسال شود، سرور لوکال آن را با IP همین سیستم (<code>::1</code> یا <code>127.0.0.1</code>) شناسایی می‌کند.</li>
                    <li><strong>عملکرد در سرور اصلی (هاست):</strong> روی دامنه واقعی (مانند <code>sarafee.uk</code>)، کراولرهای واقعی اوپن‌ای‌آی از رنج‌های IP رسمی شرکت مایکروسافت/اوپن‌ای‌آی و پرپلکسیتی از سرورهای آمازون AWS خزش را انجام می‌دهند و IP واقعی آنها به ثبت خواهد رسید.</li>
                    <li><strong>عملکرد سبک و ایمن:</strong> این سیستم هیچ دیتابیس جداگانه یا جدول سنگینی نمی‌سازد، بلکه فقط تعداد تجمیعی روزانه و ۲۰ لاگ اخیر را نگه می‌دارد تا به هیچ وجه سرعت بارگذاری سایت را تحت تاثیر قرار ندهد.</li>
                </ul>
            </div>
        </div>
        <?php
    }

    /**
     * Renders Comprehensive Documentation Page
     */
    public function render_docs_page(): void {
        $settings  = get_option( 'sarfee_ai_settings', [] );
        $site_name = ! empty( $settings['llms_site_title'] ) ? $settings['llms_site_title'] : ( get_bloginfo( 'name' ) ?: 'صرفی' );
        ?>
        <div class="wrap sarfee-admin-wrap" dir="rtl" style="max-width:none; margin: 16px 20px 24px 2px; box-sizing: border-box;">
            <?php $this->render_admin_styles(); ?>

            <!-- Hidden Screen-Reader Heading for WordPress Accessibility & Notice Anchoring -->
            <h1 class="wp-heading-inline screen-reader-text">راهنمای جامع هوش مصنوعی <?php echo esc_html( $site_name ); ?> (GEO & AEO)</h1>

            <!-- Dedicated Notice Container -->
            <div class="sarfee-notices-area" style="margin-bottom:16px;">
                <?php settings_errors( 'sarfee_ai' ); ?>
            </div>

            <!-- Header Banner -->
            <div style="background:linear-gradient(135deg, #1e293b 0%, #0f172a 100%); color:#ffffff; padding:26px 30px; border-radius:16px; margin-bottom:24px; box-shadow:0 4px 20px rgba(0,0,0,0.15); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
                <div>
                    <div style="display:inline-block; background:rgba(56, 189, 248, 0.2); color:#38bdf8; font-size:12px; font-weight:700; padding:4px 12px; border-radius:20px; margin-bottom:10px;">مستندات فنی و راهنمای راهبردی</div>
                    <div role="heading" aria-level="2" style="color:#ffffff; margin:0 0 8px; font-size:24px; font-weight:800; line-height:1.3;">راهنمای جامع هوش مصنوعی <?php echo esc_html( $site_name ); ?> (GEO & AEO)</div>
                    <p style="color:#94a3b8; font-size:13px; margin:0; line-height:1.7;">چگونه این افزونه سایت <?php echo esc_html( $site_name ); ?> را به مرجع اصلی پاسخ‌ها در Perplexity، ChatGPT، Google Gemini و Claude تبدیل می‌کند.</p>
                </div>
                <div>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=sarfee-ai-engine' ) ); ?>" class="sarfee-btn sarfee-btn-banner-ghost">
                        <span>‹ بازگشت به پیشخوان اصلی</span>
                    </a>
                </div>
            </div>

            <!-- Section 1: What is GEO -->
            <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; padding:24px; margin-bottom:20px;">
                <h2 style="margin-top:0; font-size:18px; color:#0f172a; display:flex; align-items:center; gap:8px;">
                    <span style="background:#eff6ff; color:#2563eb; width:28px; height:28px; display:inline-flex; align-items:center; justify-content:center; border-radius:8px; font-size:14px;">۱</span>
                    <span>تفاوت سئوی سنتی با سئوی هوش مصنوعی (GEO / AEO) چیست؟</span>
                </h2>
                <p style="color:#475569; font-size:14px; line-height:1.9;">
                    در سئوی کلاسیک، هدف شما این بود که در صفحه اول گوگل ۱۰ تا لینک آبی‌رنگ بیاید و کاربر روی سایت شما کلیک کند. اما در عصر هوش مصنوعی، کاربر در <strong>ChatGPT Search</strong>، <strong>Perplexity</strong> یا <strong>Google AI Overviews</strong> مستقیماً سوال می‌پرسد:
                </p>
                <div style="background:#f8fafc; border-right:4px solid #3b82f6; padding:12px 16px; border-radius:6px; font-size:13px; color:#1e293b; margin:12px 0;">
                    <em>«کدام صرافی ایرانی کمترین کارمزد و پشتیبانی تتر را دارد؟ آیا صرافی ایکس مجوز معتبر دارد؟»</em>
                </div>
                <p style="color:#475569; font-size:14px; line-height:1.9;">
                    موتورهای پاسخ‌گو (Answer Engines) کل وب را می‌گردند و به جای نمایش صرفِ لینک، پاسخی جامع تولید کرده و <strong>منابع موثق (Citations)</strong> را لینک می‌دهند. این پلاگین تضمین می‌کند که سایت صرفی ساختار، زبان و اعتبار لازم را داشته باشد تا توسط این موتورها به عنوان منبع موثق استناد شود.
                </p>
            </div>

            <!-- Section 2: Core Components Breakdown -->
            <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; padding:24px; margin-bottom:20px;">
                <h2 style="margin-top:0; font-size:18px; color:#0f172a; display:flex; align-items:center; gap:8px;">
                    <span style="background:#eff6ff; color:#2563eb; width:28px; height:28px; display:inline-flex; align-items:center; justify-content:center; border-radius:8px; font-size:14px;">۲</span>
                    <span>بررسی کارکرد تک‌تک بخش‌های افزونه</span>
                </h2>

                <div style="display:grid; grid-template-columns: 1fr; gap:16px; margin-top:16px;">
                    
                    <!-- Item 1: llms.txt -->
                    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:16px;">
                        <h3 style="margin:0 0 8px; font-size:15px; color:#1e293b;">الف) فایل استاندارد <code>/llms.txt</code> و <code>/llms-full.txt</code></h3>
                        <p style="color:#475569; font-size:13px; line-height:1.8; margin:0 0 8px;">
                            <strong>استاندارد جهانی:</strong> مشابه فایل robots.txt که برای کراولرهاست، فایل llms.txt (مطابق استاندارد llmstxt.org) محتوای سایت را در فرمت تمیز Markdown در اختیار مدل‌های هوش مصنوعی می‌گذارد.
                        </p>
                        <p style="color:#475569; font-size:13px; line-height:1.8; margin:0;">
                            <strong>عملکرد در سایت صرفی:</strong> این فایل به صورت خودکار صرافی‌های مجاز، رتبه‌بندی‌ها، تلفن، شهر و نمادهای ارزی را استخراج می‌کند. هوش مصنوعی بدون تلف کردن زمان روی کدهای سنگین قالب و جاوااسکریپت، به سرعت این خلاصه را می‌خواند. این فایل‌ها به صورت ۱۲ ساعته کش می‌شوند و هرگز باری روی هاست نمی‌گذارند.
                        </p>
                    </div>

                    <!-- Item 2: ai.txt & robots -->
                    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:16px;">
                        <h3 style="margin:0 0 8px; font-size:15px; color:#1e293b;">ب) فایل <code>/ai.txt</code> و بهینه‌سازی <code>robots.txt</code></h3>
                        <p style="color:#475569; font-size:13px; line-height:1.8; margin:0 0 8px;">
                            <strong>مجوز استناد و کپی‌رایت:</strong> این فایل صراحتاً اعلام می‌کند که هوش مصنوعی مجاز است از داده‌های سایت برای پاسخ به کاربران استفاده کند، به شرط اینکه نام و لینک «صرفی» را به عنوان منبع ذکر کند (Attribution: required).
                        </p>
                        <p style="color:#475569; font-size:13px; line-height:1.8; margin:0;">
                            <strong>مدیریت کراولرها:</strong> ربات‌های رسمی مثل GPTBot (اوپن‌ای‌آی)، PerplexityBot (پرپلکسیتی)، ClaudeBot (کلود) و Google-Extended در robots.txt باز نگه داشته شده‌اند تا سایت صرفی از نتایج هوش مصنوعی حذف نشود.
                        </p>
                    </div>

                    <!-- Item 3: Exchange Schema -->
                    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:16px;">
                        <h3 style="margin:0 0 8px; font-size:15px; color:#1e293b;">ج) اسکیمای هوشمند صرافی‌ها (JSON-LD & Knowledge Graph)</h3>
                        <p style="color:#475569; font-size:13px; line-height:1.8; margin:0 0 8px;">
                            برای هر صرافی، یک شناسنامه دیجیتال استاندارد از نوع <code>FinancialService</code> و <code>ExchangeOffice</code> تولید می‌شود که حاوی نام، مجوز، آدرس، تلفن، شهر، ارزهای پشتیبانی‌شده و پیوند با سازمان مادر (سایت صرفی) است.
                        </p>
                        <p style="color:#475569; font-size:13px; line-height:1.8; margin:0;">
                            <strong>هماهنگی با رنک‌مث (Rank Math):</strong> این ماژول مستقیماً با هوک <code>rank_math/json_ld</code> کار می‌کند. یعنی اگر رنک‌مث فعال باشد، این داده‌ها به صورت خودکار به گراف اصلی رنک‌مث ملحق می‌شوند و هیچ تداخل یا کد دوتایی ایجاد نمی‌شود.
                        </p>
                    </div>

                    <!-- Item 4: IndexNow -->
                    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:16px;">
                        <h3 style="margin:0 0 8px; font-size:15px; color:#1e293b;">د) پروتکل ارسال لحظه‌ای IndexNow</h3>
                        <p style="color:#475569; font-size:13px; line-height:1.8; margin:0;">
                            موتورهای هوش مصنوعی مبتنی بر وب (مانند مایکروسافت بینگ، کوپایلت و پلتفرم‌های پرپلکسیتی) از پروتکل IndexNow استفاده می‌کنند. هر زمان که شما یک صرافی را ویرایش کنید، کارمزد آن را تغییر دهید یا مقاله‌ای جدید منتشر کنید، این افزونه بلافاصله و در کسری از ثانیه آدرس صفحه را به سرورهای IndexNow پینگ می‌کند تا نیاز به انتظار چند روزه برای بازبینی مجدد نباشد.
                        </p>
                    </div>

                </div>
            </div>

            <!-- Section 3: Google Search Console & Safety -->
            <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; padding:24px; margin-bottom:20px;">
                <h2 style="margin-top:0; font-size:18px; color:#0f172a; display:flex; align-items:center; gap:8px;">
                    <span style="background:#eff6ff; color:#2563eb; width:28px; height:28px; display:inline-flex; align-items:center; justify-content:center; border-radius:8px; font-size:14px;">۳</span>
                    <span>چرا هیچ خطایی در سرچ کنسول گوگل (Search Console) رخ نمی‌دهد؟</span>
                </h2>
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap:14px; margin-top:14px;">
                    <div style="border-right:3px solid #10b981; padding:10px 14px; background:#f0fdf4; border-radius:6px;">
                        <strong style="color:#065f46; font-size:13px; display:block; margin-bottom:4px;">عدم مسدودسازی Googlebot</strong>
                        <span style="color:#047857; font-size:12px; line-height:1.6;">کراولر اصلی گوگل (Googlebot) هرگز محدود نشده و دسترسی کامل به صفحات دارد.</span>
                    </div>
                    <div style="border-right:3px solid #10b981; padding:10px 14px; background:#f0fdf4; border-radius:6px;">
                        <strong style="color:#065f46; font-size:13px; display:block; margin-bottom:4px;">هدر noindex فایل‌های سیستمی</strong>
                        <span style="color:#047857; font-size:12px; line-height:1.6;">فایل کلید IndexNow هدر noindex دارد تا گوگل آن را ایندکس نکرده و خطای محتوای ضعیف ندهد.</span>
                    </div>
                    <div style="border-right:3px solid #10b981; padding:10px 14px; background:#f0fdf4; border-radius:6px;">
                        <strong style="color:#065f46; font-size:13px; display:block; margin-bottom:4px;">اعتبارسنجی دقیق اسکیما</strong>
                        <span style="color:#047857; font-size:12px; line-height:1.6;">فیلدهای اسکیما تمام قوانین الزامی Google Rich Results را بدون خطا رعایت کرده‌اند.</span>
                    </div>
                    <div style="border-right:3px solid #10b981; padding:10px 14px; background:#f0fdf4; border-radius:6px;">
                        <strong style="color:#065f46; font-size:13px; display:block; margin-bottom:4px;">بدون تداخل با رنک‌مث</strong>
                        <span style="color:#047857; font-size:12px; line-height:1.6;">به جای تولید تگ‌های متناقض، به گراف رسمی رنک‌مث متصل شده و یک خروجی یکدست می‌دهد.</span>
                    </div>
                </div>
            </div>

            <!-- Section 4: Tips & Features -->
            <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; padding:24px;">
                <h2 style="margin-top:0; font-size:18px; color:#0f172a; display:flex; align-items:center; gap:8px;">
                    <span style="background:#eff6ff; color:#2563eb; width:28px; height:28px; display:inline-flex; align-items:center; justify-content:center; border-radius:8px; font-size:14px;">۴</span>
                    <span>ویژگی‌های کلیدی برای تیم تحریریه و سئو</span>
                </h2>
                <ul style="color:#475569; font-size:14px; line-height:2.2; margin:12px 0 0; padding-right:20px;">
                    <li><strong>شورت‌کد شناسنامه صرافی <code>[sarfee_ai_facts]</code>:</strong> موتورهای پاسخ‌گو (مانند Perplexity و Google AI Overviews) عاشق محتوای خلاصه و گلوله‌ای (Bullet Points) هستند.
                        <div style="background:#f8fafc; border-right:3px solid #3b82f6; padding:8px 12px; margin:6px 0; border-radius:4px; font-size:12px; color:#334155;">
                            • <strong>در صفحه اختصاصی هر صرافی:</strong> قرار دادن ساده <code>[sarfee_ai_facts]</code> بدون پارامتر (اطلاعات همان صرافی را خودکار استخراج می‌کند).<br>
                            • <strong>در داخل مقالات و وبلاگ (نوشته‌ها):</strong> کافیست نامک یا شناسه صرافی را مشخص کنید؛ مثال: <code>[sarfee_ai_facts slug="نامک-صرافی"]</code> یا <code>[sarfee_ai_facts id="123"]</code> تا کادر شناسنامه همراه با دکمه هدایت به صفحه رسمی صرافی نمایش داده شود.
                        </div>
                    </li>
                    <li><strong>چیدمان خودکار و داینامیک برگه‌ها (لندینگ‌ها):</strong> تمام برگه‌های خدمات ارزی (مثل خرید ملک در انگلیس، پرداخت شهریه، دریافت پوند، تتر و...) به صورت خودکار خوانده شده و در <code>llms.txt</code> قرار می‌گیرند. شما می‌توانید در بخش ویرایش برگه و کادر «ویژگی‌های برگه»، عدد <strong>«ترتیب» (Order)</strong> را تغییر دهید تا چیدمان آنها در لیست هوش مصنوعی جابجا شود.</li>
                    <li><strong>صفحات درباره ما، تماس و سلب مسئولیت:</strong> این صفحات به عنوان ستون فقرات اعتماد (E-E-A-T) شناسایی شده و در فایل‌های <code>llms.txt</code>، <code>ai.txt</code> و اسکیمای رسمی سایت ادغام شده‌اند.</li>
            </div>
        </div>
        <?php
    }

    /**
     * Registers AI Readiness Meta Box for post editors
     */
    public function register_ai_meta_boxes(): void {
        $screens = [ 'exchange', 'symbol', 'post' ];
        foreach ( $screens as $screen ) {
            add_meta_box(
                'sarfee_ai_readiness_box',
                'آمادگی برای هوش مصنوعی (AI Readiness)',
                [ $this, 'render_ai_meta_box' ],
                $screen,
                'side',
                'high'
            );
        }
    }

    /**
     * Renders AI Readiness Checklist in post editor sidebar
     */
    public function render_ai_meta_box( $post ): void {
        if ( ! $post instanceof WP_Post ) {
            $post = get_post( $post );
        }
        if ( ! $post ) {
            return;
        }

        $post_id   = $post->ID;
        $post_type = $post->post_type;

        // 1. Meta Description Check (for llms.txt)
        $desc = get_post_meta( $post_id, 'rank_math_description', true ) ?: $post->post_excerpt;
        $has_desc = ! empty( trim( (string) $desc ) );

        // 2. Checks based on post type
        $checks = [];
        $checks[] = [
            'label'  => 'توضیحات متا برای llms.txt',
            'passed' => $has_desc,
            'hint'   => $has_desc ? 'ثبت شده در رنک‌مث/چکیده' : 'افزودن توضیح در رنک‌مث برای نقل‌قول هوش مصنوعی',
        ];

        if ( $post_type === 'exchange' ) {
            $verified = function_exists( 'get_field' ) ? get_field( 'verified', $post_id ) : get_post_meta( $post_id, 'verified', true );
            $terms    = wp_get_post_terms( $post_id, 'city' );
            $has_city = ! empty( $terms ) && ! is_wp_error( $terms );
            $phone    = function_exists( 'get_field' ) ? get_field( 'phone', $post_id ) : get_post_meta( $post_id, 'phone', true );
            $address  = function_exists( 'get_field' ) ? get_field( 'address', $post_id ) : get_post_meta( $post_id, 'address', true );

            $checks[] = [
                'label'  => 'تعیین شهر صرافی',
                'passed' => $has_city,
                'hint'   => $has_city ? 'شهر انتخاب شده است' : 'انتخاب تاکسونومی شهر برای اسکیما',
            ];
            $checks[] = [
                'label'  => 'وضعیت تایید و مجوز رسمی',
                'passed' => (bool) $verified,
                'hint'   => $verified ? 'صرافی مجاز علامت‌گذاری شده' : 'بررسی تیک صرافی مجاز',
            ];
            $checks[] = [
                'label'  => 'اطلاعات تماس (آدرس و تلفن)',
                'passed' => (bool) ( $phone || $address ),
                'hint'   => ( $phone && $address ) ? 'کامل برای ثبت در اسکیما' : 'تکمیل تلفن و آدرس صرافی',
            ];
        } elseif ( $post_type === 'symbol' ) {
            $fa_name_val = function_exists( 'get_field' ) ? get_field( 'fa_name', $post_id ) : '';
            if ( empty( $fa_name_val ) ) {
                $fa_name_val = get_post_meta( $post_id, 'fa_name', true );
            }
            $has_fa = ! empty( trim( (string) $fa_name_val ) );
            $checks[] = [
                'label'  => 'نام فارسی نماد (fa_name)',
                'passed' => $has_fa,
                'hint'   => $has_fa ? 'نام فارسی اختصاصی ثبت شده' : 'استفاده از نام پیش‌فرض عنوان (تکمیل فیلد fa_name توصیه می‌شود)',
            ];
        } else {
            $has_thumb = has_post_thumbnail( $post_id );
            $checks[] = [
                'label'  => 'تصویر شاخص برای پیش‌نمایش AI',
                'passed' => $has_thumb,
                'hint'   => $has_thumb ? 'تصویر شاخص موجود است' : 'افزودن تصویر برای کارت‌های استناد',
            ];
        }

        // Calculate score
        $total_checks  = count( $checks );
        $passed_checks = count( array_filter( $checks, fn( $c ) => $c['passed'] ) );
        $percentage    = round( ( $passed_checks / $total_checks ) * 100 );

        $score_color = $percentage >= 80 ? '#16a34a' : ( $percentage >= 50 ? '#d97706' : '#dc2626' );
        ?>
        <div class="sarfee-ai-meta-box" dir="rtl" style="font-size:13px; line-height:1.6;">
            <!-- Score Meter -->
            <div style="margin-bottom:12px; padding-bottom:10px; border-bottom:1px solid #f1f5f9;">
                <div style="display:flex; justify-content:space-between; align-items:baseline; margin-bottom:6px;">
                    <span style="font-weight:700; color:#1e293b;">امتیاز خوانش هوش مصنوعی:</span>
                    <strong style="font-size:16px; color:<?php echo esc_attr( $score_color ); ?>;"><?php echo esc_html( $percentage ); ?>٪</strong>
                </div>
                <div style="width:100%; height:6px; background:#e2e8f0; border-radius:3px; overflow:hidden;">
                    <div style="width:<?php echo esc_attr( $percentage ); ?>%; height:100%; background:<?php echo esc_attr( $score_color ); ?>; transition:width 0.3s ease;"></div>
                </div>
            </div>

            <!-- Items Checklist -->
            <ul style="margin:0; padding:0; list-style:none;">
                <?php foreach ( $checks as $c ) : ?>
                <li style="margin-bottom:8px; display:flex; align-items:flex-start; gap:8px;">
                    <?php if ( $c['passed'] ) : ?>
                        <span style="color:#16a34a; font-size:14px; line-height:1;">✔</span>
                    <?php else : ?>
                        <span style="color:#d97706; font-size:14px; line-height:1;">⚠</span>
                    <?php endif; ?>
                    <div>
                        <strong style="color:#334155; display:block; font-size:12px;"><?php echo esc_html( $c['label'] ); ?></strong>
                        <span style="color:#64748b; font-size:11px;"><?php echo esc_html( $c['hint'] ); ?></span>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>

            <p style="margin:10px 0 0; padding-top:8px; border-top:1px solid #f1f5f9; font-size:11px; color:#94a3b8;">
                این فیلدها به صورت خودکار در <code>llms.txt</code> و اسکیمای ساختاریافته گوگل درج می‌شوند.
            </p>
        </div>
        <?php
    }
}
