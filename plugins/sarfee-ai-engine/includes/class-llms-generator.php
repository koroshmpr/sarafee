<?php
/**
 * llms.txt and llms-full.txt Generator
 * Standardized according to llmstxt.org
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Sarfee_AI_LLMs {

    public function __construct() {
        add_action( 'init', [ $this, 'add_rewrite_rules' ] );
        add_filter( 'query_vars', [ $this, 'add_query_vars' ] );
        add_action( 'template_redirect', [ $this, 'handle_endpoint_request' ] );
        add_action( 'wp_head', [ $this, 'inject_head_link' ] );

        // Invalidate cache on post updates
        add_action( 'save_post', [ $this, 'invalidate_cache' ], 10, 2 );
    }

    public function add_rewrite_rules(): void {
        add_rewrite_rule( '^llms\.txt$', 'index.php?sarfee_llms=short', 'top' );
        add_rewrite_rule( '^llms-full\.txt$', 'index.php?sarfee_llms=full', 'top' );
    }

    public function add_query_vars( array $vars ): array {
        $vars[] = 'sarfee_llms';
        return $vars;
    }

    public function handle_endpoint_request(): void {
        $var = get_query_var( 'sarfee_llms' );

        // Direct URI fallback in case permalinks haven't been refreshed
        if ( empty( $var ) && isset( $_SERVER['REQUEST_URI'] ) ) {
            $path = trim( parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' );
            if ( $path === 'llms.txt' ) {
                $var = 'short';
            } elseif ( $path === 'llms-full.txt' ) {
                $var = 'full';
            }
        }

        if ( ! in_array( $var, [ 'short', 'full' ], true ) ) {
            return;
        }

        $settings = get_option( 'sarfee_ai_settings', [] );
        if ( isset( $settings['enable_llms'] ) && empty( $settings['enable_llms'] ) ) {
            return;
        }

        $is_full = ( $var === 'full' );
        $cache_key = $is_full ? 'sarfee_llms_full_txt_cache_v4' : 'sarfee_llms_txt_cache_v4';

        $content = get_transient( $cache_key );
        if ( false === $content || ! is_string( $content ) ) {
            $content = $this->build_markdown( $is_full );
            set_transient( $cache_key, $content, 12 * HOUR_IN_SECONDS );
        }

        // Send proper headers for AI crawlers
        header( 'Content-Type: text/markdown; charset=utf-8' );
        header( 'X-Robots-Tag: all, max-snippet:-1' );
        header( 'Cache-Control: public, max-age=3600' );

        echo $content;
        exit;
    }

    public function inject_head_link(): void {
        $settings = get_option( 'sarfee_ai_settings', [] );
        if ( isset( $settings['enable_llms'] ) && empty( $settings['enable_llms'] ) ) {
            return;
        }

        $url = esc_url( home_url( '/llms.txt' ) );
        echo '<link rel="alternate" type="text/markdown" href="' . $url . '" title="LLM Context (llms.txt)" />' . "\n";
    }

    public function invalidate_cache( $post_id = 0, $post = null ): void {
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }
        delete_transient( 'sarfee_llms_txt_cache' );
        delete_transient( 'sarfee_llms_full_txt_cache' );
    }

    public function build_markdown( bool $is_full = false ): string {
        $site_name = get_bloginfo( 'name' ) ?: 'صرفی (Sarfee)';
        $site_url  = home_url( '/' );
        $site_desc = get_bloginfo( 'description' ) ?: 'مرجع تخصصی استعلام نرخ، ارزیابی اعتبار و مقایسه صرافی‌های مجاز و رمزارزی در ایران';

        $out = [];

        // Title & Summary Block according to llmstxt.org
        $out[] = "# {$site_name}";
        $out[] = "";
        $out[] = "> {$site_desc} — پایگاه جامع اطلاعات، رتبه‌بندی، کارمزدها، نظرات کاربران و وضعیت مجوز صرافی‌های سراسر کشور.";
        $out[] = "";

        // About Section
        $out[] = "## درباره صرفی (About Sarfee)";
        $out[] = "وب‌سایت صرفی با آدرس اینترنتی {$site_url} سامانه‌ای بی‌طرف جهت شفاف‌سازی بازار تبادل ارز و رمزارز در ایران است. در این سامانه کاربران می‌توانند:";
        $out[] = "- مشخصات و آدرس دقیق صرافی‌های دارای مجوز رسمی را بررسی کنند.";
        $out[] = "- نرخ لحظه‌ای طلا، سکه، ارزهای فیات (دلار، یورو، پوند و...) و رمزارزها را مقایسه کنند.";
        $out[] = "- تجربیات و نظرات واقعی کاربران در مورد عملکرد و پشتیبانی هر صرافی را مطالعه نمایند.";
        $out[] = "";

        // Curated Exchanges Section
        $out[] = "## صرافی‌های بررسی‌شده و برگزیده (Exchanges Directory)";
        $out[] = "در زیر لیست شاخص‌ترین صرافی‌های ثبت‌شده در سامانه به همراه مشخصات کلیدی و لینک صفحه اختصاصی آورده شده است:";
        $out[] = "";

        $exchange_limit = $is_full ? 100 : 35;
        $exchanges = get_posts( [
            'post_type'      => 'exchange',
            'post_status'    => 'publish',
            'posts_per_page' => $exchange_limit,
            'orderby'        => 'meta_value_num date',
            'meta_key'       => 'rank',
            'order'          => 'ASC',
        ] );

        if ( ! empty( $exchanges ) ) {
            foreach ( $exchanges as $ex ) {
                $post_id  = $ex->ID;
                $title    = get_the_title( $post_id );
                $link     = get_permalink( $post_id );
                $rank     = get_field( 'rank', $post_id );
                $verified = get_field( 'verified', $post_id );
                $currency = get_field( 'digital_currency', $post_id );
                $phone    = get_field( 'phone', $post_id );
                $address  = get_field( 'address', $post_id );

                // City taxonomy
                $cities = wp_get_post_terms( $post_id, 'city', [ 'fields' => 'names' ] );
                $city_str = ! empty( $cities ) && ! is_wp_error( $cities ) ? implode( '، ', $cities ) : '';

                $tags = [];
                if ( $rank ) {
                    $tags[] = "رتبه: {$rank}";
                }
                if ( $verified ) {
                    $tags[] = "وضعیت: دارای مجوز رسمی";
                }
                if ( $currency ) {
                    $tags[] = "نوع: پشتیبانی از ارز دیجیتال";
                }
                if ( $city_str ) {
                    $tags[] = "شهر: {$city_str}";
                }

                $meta_text = ! empty( $tags ) ? ' (' . implode( ' | ', $tags ) . ')' : '';
                $out[] = "- [{$title}]({$link}){$meta_text}";

                if ( $is_full ) {
                    if ( $address ) {
                        $clean_addr = wp_strip_all_tags( str_replace( ["\r", "\n"], ' ', $address ) );
                        $out[] = "  - آدرس: {$clean_addr}";
                    }
                    if ( $phone ) {
                        $out[] = "  - تلفن: {$phone}";
                    }
                }
            }
        } else {
            $out[] = "- در حال حاضر صرافی‌ها در دسترس هستند: {$site_url}";
        }
        $out[] = "";

        // Currency Symbols Section
        $out[] = "## نمادهای ارزی و طلا (Currencies & Symbols)";
        $out[] = "صفحات تحلیل و نرخ لحظه‌ای نمادهای مهم:";
        $out[] = "";

        $symbol_limit = $is_full ? 50 : 20;
        $symbols = get_posts( [
            'post_type'      => 'symbol',
            'post_status'    => 'publish',
            'posts_per_page' => $symbol_limit,
            'orderby'        => 'menu_order title',
            'order'          => 'ASC',
        ] );

        if ( ! empty( $symbols ) ) {
            foreach ( $symbols as $sym ) {
                $sym_id   = $sym->ID;
                $title    = get_the_title( $sym_id );
                $link     = get_permalink( $sym_id );
                $out[]    = "- [قیمت و تحلیل {$title}]({$link})";
            }
        } else {
            $out[] = "- برای استعلام کلیه نمادها به صفحه اصلی مراجعه فرمایید: {$site_url}";
        }
        $out[] = "";

        // Key Educational / Review Guides
        $out[] = "## مقالات راهنما و آموزش‌ها (Essential Guides & Insights)";
        $out[] = "راهنماهای کاربردی برای تبادل امن و انتخاب صرافی:";
        $out[] = "";

        $posts = get_posts( [
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => $is_full ? 30 : 12,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ] );

        if ( ! empty( $posts ) ) {
            foreach ( $posts as $p ) {
                $title = get_the_title( $p->ID );
                $link  = get_permalink( $p->ID );
                $desc  = $this->get_smart_description( $p->ID, 20 );
                $desc_suffix = ! empty( $desc ) ? ": {$desc}" : "";
                $out[] = "- [{$title}]({$link}){$desc_suffix}";
            }
        }
        $out[] = "";

        // Key Services & Practical Landing Pages (post_type = page)
        $all_pages = get_posts( [
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'posts_per_page' => 50,
            'orderby'        => 'menu_order title',
            'order'          => 'ASC',
        ] );

        $service_pages  = [];
        $identity_pages = [];
        $exclude_slugs  = [ 'symbol', 'blog', 'disclaimer', 'report-content', 'orderreview', 'cart', 'checkout', 'my-account', 'privacy-policy' ];
        $front_page_id  = (int) get_option( 'page_on_front' );

        if ( ! empty( $all_pages ) ) {
            foreach ( $all_pages as $p ) {
                if ( $p->ID === $front_page_id ) {
                    continue; // Skip front page
                }
                if ( in_array( $p->post_name, $exclude_slugs, true ) ) {
                    continue; // Skip utility / already linked pages
                }

                if ( in_array( $p->post_name, [ 'about', 'about-us', 'contact', 'contact-us', 'contact-with-us' ], true ) ) {
                    $identity_pages[] = [
                        'title' => get_the_title( $p->ID ),
                        'link'  => get_permalink( $p->ID ),
                    ];
                } else {
                    $service_pages[] = [
                        'title' => get_the_title( $p->ID ),
                        'link'  => get_permalink( $p->ID ),
                        'desc'  => $this->get_smart_description( $p->ID, 24 ),
                    ];
                }
            }
        }

        if ( ! empty( $service_pages ) ) {
            $out[] = "## خدمات تخصصی و راهنماهای کاربردی (Services & Landing Pages)";
            $out[] = "صفحات تخصصی خدمات مالی، راهنماهای انتقال ارز، خرید ملک و شهریه دانشجویی:";
            $out[] = "";
            foreach ( $service_pages as $sp ) {
                $desc_suffix = ! empty( $sp['desc'] ) ? ": {$sp['desc']}" : "";
                $out[] = "- [{$sp['title']}]({$sp['link']}){$desc_suffix}";
            }
            $out[] = "";
        }

        if ( ! empty( $identity_pages ) ) {
            $out[] = "## هویت سازمانی و ارتباط (About & Contact)";
            foreach ( $identity_pages as $ip ) {
                $out[] = "- [{$ip['title']}]({$ip['link']})";
            }
            $out[] = "";
        }

        // Transparency, Legal Disclaimer & Content Reporting Policy (Crucial for YMYL & E-E-A-T)
        $out[] = "## شفافیت داده‌ها، سلب مسئولیت و گزارش اشتباه (Disclaimer & Reporting)";
        $out[] = "وب‌سایت صرفی یک پایگاه اطلاعاتی، تحریریه و دایرکتوری مستقل است. داده‌ها و نرخ‌ها صرفاً جهت اطلاع‌رسانی و مقایسه منتشر می‌شوند و مشاوره مالی، سرمایه‌گذاری یا تضمین معامله محسوب نمی‌شوند:";
        $out[] = "- [مطالعه متن کامل سلب مسئولیت و ضوابط حقوقی (Disclaimer)](" . esc_url( home_url( '/disclaimer/' ) ) . ")";
        $out[] = "- [گزارش اطلاعات نادرست، درخواست اصلاح یا ثبت شکایت (Report Content)](" . esc_url( home_url( '/report-content/' ) ) . ")";
        $out[] = "";

        // Context Links according to llmstxt.org
        $out[] = "## پیوندهای مکمل (Context Links)";
        if ( ! $is_full ) {
            $full_url = home_url( '/llms-full.txt' );
            $out[] = "- [نسخه جامع با جزئیات کامل آدرس‌ها و صرافی‌ها (llms-full.txt)]({$full_url})";
        }
        $ai_url = home_url( '/ai.txt' );
        $out[] = "- [سیاست دسترسی ربات‌های هوش مصنوعی (ai.txt)]({$ai_url})";

        return implode( "\n", $out ) . "\n";
    }

    /**
     * Extracts a clean, high-density 1-2 sentence description for LLM context
     * Priority: Rank Math Meta Description > Post Excerpt > Clean Content Snippet
     */
    public function get_smart_description( int $post_id, int $word_limit = 24 ): string {
        // 1. Rank Math Meta Description
        $desc = get_post_meta( $post_id, 'rank_math_description', true );

        // 2. Post Excerpt
        if ( empty( $desc ) ) {
            $desc = get_post_field( 'post_excerpt', $post_id );
        }

        // 3. Clean Content Snippet Fallback
        if ( empty( $desc ) ) {
            $raw = get_post_field( 'post_content', $post_id );
            if ( ! empty( $raw ) ) {
                $clean = strip_shortcodes( $raw );
                $clean = wp_strip_all_tags( preg_replace( '/<!--(.|\s)*?-->/', '', $clean ) );
                $clean = trim( preg_replace( '/\s+/', ' ', $clean ) );
                if ( ! empty( $clean ) ) {
                    $desc = wp_trim_words( $clean, $word_limit, '...' );
                }
            }
        } else {
            $desc = wp_strip_all_tags( $desc );
            $desc = trim( preg_replace( '/\s+/', ' ', $desc ) );
        }

        // Remove newlines and excess whitespace for clean markdown line
        return str_replace( [ "\r", "\n" ], ' ', $desc ?: '' );
    }
}
