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
            'هوش مصنوعی صرفی (GEO & AEO)',
            'هوش مصنوعی صرفی',
            'manage_options',
            'sarfee-ai-engine',
            [ $this, 'render_dashboard_page' ],
            $icon_svg,
            32
        );

        // 2. Submenu: Dashboard & Settings
        add_submenu_page(
            'sarfee-ai-engine',
            'پیشخوان و تنظیمات هوش مصنوعی صرفی',
            'پیشخوان و تنظیمات',
            'manage_options',
            'sarfee-ai-engine',
            [ $this, 'render_dashboard_page' ]
        );

        // 3. Submenu: Documentation & Guide
        add_submenu_page(
            'sarfee-ai-engine',
            'راهنما و مستندات کامل هوش مصنوعی (GEO)',
            'راهنما و مستندات کامل',
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
                'enable_llms'      => ! empty( $_POST['enable_llms'] ) ? 1 : 0,
                'enable_ai_txt'    => ! empty( $_POST['enable_ai_txt'] ) ? 1 : 0,
                'enable_ai_robots' => ! empty( $_POST['enable_ai_robots'] ) ? 1 : 0,
                'enable_schema'    => ! empty( $_POST['enable_schema'] ) ? 1 : 0,
                'enable_indexnow'  => ! empty( $_POST['enable_indexnow'] ) ? 1 : 0,
            ];
            update_option( 'sarfee_ai_settings', $settings );
            add_settings_error( 'sarfee_ai', 'settings_saved', 'تنظیمات با موفقیت ذخیره شد.', 'updated' );
        }

        // Action: Clear Cache
        if ( isset( $_POST['sarfee_ai_clear_cache'] ) && check_admin_referer( 'sarfee_ai_clear_cache_action', 'sarfee_ai_cache_nonce' ) ) {
            delete_transient( 'sarfee_llms_txt_cache' );
            delete_transient( 'sarfee_llms_full_txt_cache' );
            add_settings_error( 'sarfee_ai', 'cache_cleared', 'کش فایل‌های llms.txt و llms-full.txt با موفقیت تخلیه شد.', 'updated' );
        }

        // Action: Test IndexNow
        if ( isset( $_POST['sarfee_ai_test_indexnow'] ) && check_admin_referer( 'sarfee_ai_test_indexnow_action', 'sarfee_ai_indexnow_nonce' ) ) {
            $test_url = home_url( '/' );
            $result = $this->engine->indexnow->ping_urls( [ $test_url ] );
            if ( $result['success'] ) {
                add_settings_error( 'sarfee_ai', 'indexnow_success', 'پینگ IndexNow با موفقیت ارسال شد (کد پاسخ: ' . $result['status'] . ').', 'updated' );
            } else {
                add_settings_error( 'sarfee_ai', 'indexnow_error', 'ارسال پینگ IndexNow با خطا مواجه شد: ' . $result['message'], 'error' );
            }
        }
    }

    /**
     * Renders Dashboard & Settings Page
     */
    public function render_dashboard_page(): void {
        $settings     = get_option( 'sarfee_ai_settings', [
            'enable_llms'      => 1,
            'enable_ai_txt'    => 1,
            'enable_ai_robots' => 1,
            'enable_schema'    => 1,
            'enable_indexnow'  => 1,
        ] );
        $indexnow_key = $this->engine->indexnow->ensure_api_key();
        $logs         = get_option( 'sarfee_ai_indexnow_log', [] );

        $llms_url     = home_url( '/llms.txt' );
        $full_url     = home_url( '/llms-full.txt' );
        $ai_url       = home_url( '/ai.txt' );
        $key_url      = home_url( "/{$indexnow_key}.txt" );
        $robots_url   = home_url( '/robots.txt' );
        ?>
        <div class="wrap" dir="rtl">
            <h1 style="display:flex; align-items:center; gap:12px; font-weight:800; color:#0f172a; margin-bottom:20px;">
                <span>مرکز کنترل هوش مصنوعی صرفی (Sarfee AI Engine)</span>
                <span style="font-size:12px; font-weight:normal; background:#e0f2fe; color:#0369a1; padding:3px 12px; border-radius:20px;">نسخه <?php echo esc_html( SARFEE_AI_VERSION ); ?></span>
            </h1>

            <?php settings_errors( 'sarfee_ai' ); ?>

            <!-- Readiness Cards Grid -->
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:16px; margin-bottom:24px;">
                
                <!-- Card 1: llms.txt -->
                <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; padding:18px; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                        <strong style="color:#1e293b; font-size:15px;">فایل استاندارد llms.txt</strong>
                        <span style="background:#dcfce7; color:#15803d; font-size:11px; padding:2px 8px; border-radius:12px; font-weight:bold;">فعال و کش‌شده</span>
                    </div>
                    <p style="color:#64748b; font-size:13px; margin:0 0 12px; line-height:1.6;">خلاصه ساختاریافته صرافی‌ها، نمادها و آموزش‌ها برای خوانش آنی هوش مصنوعی.</p>
                    <div style="display:flex; gap:8px;">
                        <a href="<?php echo esc_url( $llms_url ); ?>" target="_blank" class="button button-secondary button-small">مشاهده llms.txt ↗</a>
                        <a href="<?php echo esc_url( $full_url ); ?>" target="_blank" class="button button-secondary button-small">نسخه کامل ↗</a>
                    </div>
                </div>

                <!-- Card 2: ai.txt & robots -->
                <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; padding:18px; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                        <strong style="color:#1e293b; font-size:15px;">سیاست کراولرها (ai.txt)</strong>
                        <span style="background:#dcfce7; color:#15803d; font-size:11px; padding:2px 8px; border-radius:12px; font-weight:bold;">هماهنگ</span>
                    </div>
                    <p style="color:#64748b; font-size:13px; margin:0 0 12px; line-height:1.6;">مجوز استناد و مجاز بودن دسترسی GPTBot, PerplexityBot, ClaudeBot.</p>
                    <div style="display:flex; gap:8px;">
                        <a href="<?php echo esc_url( $ai_url ); ?>" target="_blank" class="button button-secondary button-small">مشاهده ai.txt ↗</a>
                        <a href="<?php echo esc_url( $robots_url ); ?>" target="_blank" class="button button-secondary button-small">فایل robots.txt ↗</a>
                    </div>
                </div>

                <!-- Card 3: IndexNow -->
                <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; padding:18px; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                        <strong style="color:#1e293b; font-size:15px;">پروتکل ایندکس آنی IndexNow</strong>
                        <span style="background:#dcfce7; color:#15803d; font-size:11px; padding:2px 8px; border-radius:12px; font-weight:bold;">آماده پینگ</span>
                    </div>
                    <p style="color:#64748b; font-size:13px; margin:0 0 12px; line-height:1.6;">اطلاع‌رسانی بلادرنگ تغییرات صرافی‌ها به بینگ، کوپایلت و یاندکس.</p>
                    <div>
                        <a href="<?php echo esc_url( $key_url ); ?>" target="_blank" class="button button-secondary button-small">بررسی کلید امنیتی ↗</a>
                    </div>
                </div>

            </div>

            <!-- Two-column Layout: Settings & Actions -->
            <div style="display:grid; grid-template-columns: 2fr 1fr; gap:20px;">

                <!-- Settings Form -->
                <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; padding:20px;">
                    <h2 style="margin-top:0; font-size:17px; border-bottom:1px solid #f1f5f9; padding-bottom:12px;">تنظیمات ماژول‌های افزونه</h2>
                    <form method="post" action="">
                        <?php wp_nonce_field( 'sarfee_ai_settings_action', 'sarfee_ai_nonce' ); ?>

                        <table class="form-table" role="presentation">
                            <tbody>
                                <tr>
                                    <th scope="row">فایل استاندارد llms.txt</th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="enable_llms" value="1" <?php checked( ! empty( $settings['enable_llms'] ) ); ?> />
                                            ارائه خودکار فایل /llms.txt و /llms-full.txt برای هوش مصنوعی
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">سیاست استناد (ai.txt)</th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="enable_ai_txt" value="1" <?php checked( ! empty( $settings['enable_ai_txt'] ) ); ?> />
                                            ارائه خودکار فایل /ai.txt جهت الزام به ذکر منبع
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">بهینه‌سازی robots.txt</th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="enable_ai_robots" value="1" <?php checked( ! empty( $settings['enable_ai_robots'] ) ); ?> />
                                            تزریق خودکار مجوز ربات‌های هوش مصنوعی (GPTBot, PerplexityBot, ClaudeBot)
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">اسکیمای پیشرفته صرافی‌ها</th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="enable_schema" value="1" <?php checked( ! empty( $settings['enable_schema'] ) ); ?> />
                                            تولید JSON-LD FinancialService (هماهنگ و ادغام‌شده با رنک‌مث)
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">ارسال آنی IndexNow</th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="enable_indexnow" value="1" <?php checked( ! empty( $settings['enable_indexnow'] ) ); ?> />
                                            ارسال بلادرنگ آدرس‌ها هنگام انتشار یا ویرایش صرافی، نماد و مقالات
                                        </label>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <p class="submit" style="margin-bottom:0;">
                            <input type="submit" name="sarfee_ai_save_settings" class="button button-primary" value="ذخیره تنظیمات" />
                        </p>
                    </form>
                </div>

                <!-- Tools & Quick Actions -->
                <div style="display:flex; flex-direction:column; gap:16px;">
                    
                    <!-- Clear Cache -->
                    <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; padding:18px;">
                        <h3 style="margin-top:0; font-size:15px; color:#1e293b;">تخلیه کش llms.txt</h3>
                        <p style="font-size:13px; color:#64748b; line-height:1.6;">این فایل‌ها جهت سرعت حداکثری به مدت ۱۲ ساعت کش می‌شوند. با این دکمه کش بلافاصله بازسازی می‌شود.</p>
                        <form method="post" action="">
                            <?php wp_nonce_field( 'sarfee_ai_clear_cache_action', 'sarfee_ai_cache_nonce' ); ?>
                            <input type="submit" name="sarfee_ai_clear_cache" class="button button-secondary" value="پاکسازی و بازسازی کش" />
                        </form>
                    </div>

                    <!-- Test IndexNow -->
                    <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; padding:18px;">
                        <h3 style="margin-top:0; font-size:15px; color:#1e293b;">تست ارسال IndexNow</h3>
                        <p style="font-size:13px; color:#64748b; line-height:1.6;">تست ارتباط زنده با سرورهای پروتکل جهانی IndexNow (ارسال آدرس صفحه اصلی سایت):</p>
                        <form method="post" action="">
                            <?php wp_nonce_field( 'sarfee_ai_test_indexnow_action', 'sarfee_ai_indexnow_nonce' ); ?>
                            <input type="submit" name="sarfee_ai_test_indexnow" class="button button-secondary" value="ارسال پینگ آزمایشی" />
                        </form>
                    </div>

                </div>

            </div>

            <!-- Recent IndexNow Logs -->
            <?php if ( ! empty( $logs ) ) : ?>
            <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; padding:20px; margin-top:20px;">
                <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #f1f5f9; padding-bottom:10px;">
                    <h3 style="margin:0; font-size:15px; color:#1e293b;">آخرین گزارش‌های ارسال به IndexNow</h3>
                    <?php if ( $this->engine->indexnow->is_local_host() ) : ?>
                        <span style="background:#fef3c7; color:#92400e; font-size:12px; padding:3px 10px; border-radius:6px; font-weight:600;">محیط توسعه لوکال (Localhost)</span>
                    <?php endif; ?>
                </div>

                <?php if ( $this->engine->indexnow->is_local_host() ) : ?>
                    <div style="background:#fffbeb; border:1px solid #fef3c7; border-radius:8px; padding:12px 14px; margin:14px 0 10px; font-size:13px; color:#78350f; line-height:1.7;">
                        <strong>💡 درباره خطای ۴۲۹ در لوکال:</strong> سرورهای جهانی IndexNow (مایکروسافت بینگ) آدرس‌های محلی مانند <code>localhost:10013</code> را از اینترنت عمومی نمی‌توانند بررسی کنند و به دلیل حجم بالای درخواست توسعه‌دهندگان از لوکال‌هاست با محدودیت ۴۲۹ (TooManyRequests) پاسخ می‌دهند. این رفتار کاملاً طبیعی است و با انتقال سایت به هاست اصلی (دامنه عمومی مانند <code>sarfee.ir</code>)، کلیه ارسال‌ها با موفقیت ۲۰۰ و ۲۰۲ پذیرفته خواهند شد.
                    </div>
                <?php endif; ?>

                <table class="widefat striped" style="margin-top:10px; border:none;">
                    <thead>
                        <tr>
                            <th>زمان ارسال</th>
                            <th>آدرس (URL)</th>
                            <th>کد وضعیت</th>
                            <th>نتیجه</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $logs as $l ) : ?>
                        <tr>
                            <td style="direction:ltr; text-align:right; font-family:monospace;"><?php echo esc_html( $l['time'] ); ?></td>
                            <td style="direction:ltr; text-align:right;">
                                <?php foreach ( (array) $l['urls'] as $u ) : ?>
                                    <div><a href="<?php echo esc_url( $u ); ?>" target="_blank"><?php echo esc_html( $u ); ?></a></div>
                                <?php endforeach; ?>
                            </td>
                            <td><span style="font-weight:bold; color:<?php echo in_array( $l['status'], [200, 202] ) ? '#16a34a' : '#dc2626'; ?>;"><?php echo esc_html( $l['status'] ); ?></span></td>
                            <td><?php echo esc_html( $l['message'] ); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

        </div>
        <?php
    }

    /**
     * Renders Comprehensive Documentation Page
     */
    public function render_docs_page(): void {
        ?>
        <div class="wrap" dir="rtl" style="max-width:1100px;">
            <div style="background:linear-gradient(135deg, #1e293b 0%, #0f172a 100%); color:#ffffff; padding:28px 32px; border-radius:16px; margin:20px 0 28px; box-shadow:0 4px 20px rgba(0,0,0,0.15);">
                <div style="display:inline-block; background:rgba(56, 189, 248, 0.2); color:#38bdf8; font-size:12px; font-weight:700; padding:4px 12px; border-radius:20px; margin-bottom:12px;">مستندات فنی و راهنمای راهبردی</div>
                <h1 style="color:#ffffff; margin:0 0 10px; font-size:26px; font-weight:800;">راهنمای جامع هوش مصنوعی صرفی (GEO & AEO)</h1>
                <p style="color:#94a3b8; font-size:14px; margin:0; line-height:1.8;">چگونه این افزونه سایت صرفی را به مرجع اصلی پاسخ‌ها در Perplexity، ChatGPT، Google Gemini و Claude تبدیل می‌کند.</p>
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
                    <li><strong>شورت‌کد شناسنامه صرافی <code>[sarfee_ai_facts]</code>:</strong> موتورهای پاسخ‌گو (مانند Perplexity و Google AI Overviews) عاشق محتوای خلاصه و گلوله‌ای (Bullet Points) هستند. اگر در ابتدای نقد و بررسی یک صرافی این شورت‌کد را بگذارید، یک کادر شناسنامه شیک با شهر، وضعیت مجوز، پشتیبانی تتر/رمزارز و تلفن نمایش می‌دهد که هوش مصنوعی آن را به عنوان پاسخ مستقیم نقل‌قول می‌کند. (استفاده از این شورت‌کد کاملاً اختیاری است).</li>
                    <li><strong>چیدمان خودکار و داینامیک برگه‌ها (لندینگ‌ها):</strong> تمام برگه‌های خدمات ارزی (مثل خرید ملک در انگلیس، پرداخت شهریه، دریافت پوند، تتر و...) به صورت خودکار خوانده شده و در <code>llms.txt</code> قرار می‌گیرند. شما می‌توانید در بخش ویرایش برگه و کادر «ویژگی‌های برگه»، عدد <strong>«ترتیب» (Order)</strong> را تغییر دهید تا چیدمان آنها در لیست هوش مصنوعی جابجا شود.</li>
                    <li><strong>صفحات درباره ما، تماس و سلب مسئولیت:</strong> این صفحات به عنوان ستون فقرات اعتماد (E-E-A-T) شناسایی شده و در فایل‌های <code>llms.txt</code>، <code>ai.txt</code> و اسکیمای رسمی سایت ادغام شده‌اند.</li>
                </ul>
            </div>

        </div>
        <?php
    }
}
