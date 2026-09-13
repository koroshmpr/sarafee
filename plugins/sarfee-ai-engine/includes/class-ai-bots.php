<?php
/**
 * AI Bots Management & ai.txt Provider
 * Compliant with emerging AI indexing standards and robots.txt directives
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Sarfee_AI_Bots {

    public function __construct() {
        add_action( 'init', [ $this, 'handle_ai_txt_request' ], 1 );
        add_action( 'template_redirect', [ $this, 'handle_ai_txt_request' ] );
        add_filter( 'robots_txt', [ $this, 'filter_robots_txt' ], 100, 2 );
        add_action( 'send_headers', [ $this, 'inject_ai_response_headers' ] );
        add_action( 'wp_head', [ $this, 'inject_ai_head_tags' ], 2 );
        add_action( 'template_redirect', [ $this, 'track_ai_bot_visit' ], 5 );
    }

    /**
     * Handles /ai.txt endpoint
     */
    public function handle_ai_txt_request(): void {
        if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
            return;
        }

        $path = trim( (string) parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' );
        if ( $path !== 'ai.txt' ) {
            return;
        }

        $settings = get_option( 'sarfee_ai_settings', [] );
        if ( isset( $settings['enable_ai_txt'] ) && empty( $settings['enable_ai_txt'] ) ) {
            return;
        }

        // Guarantee HTTP 200 status code (prevent WP 404)
        if ( function_exists( 'status_header' ) ) {
            status_header( 200 );
        }
        if ( function_exists( 'http_response_code' ) ) {
            http_response_code( 200 );
        }

        while ( ob_get_level() > 0 ) {
            @ob_end_clean();
        }

        header( 'Content-Type: text/plain; charset=utf-8' );
        header( 'X-Robots-Tag: all' );
        header( 'Cache-Control: public, max-age=86400' );
        header( 'X-Content-Type-Options: nosniff' );

        echo $this->build_ai_txt_content();
        exit;
    }

    /**
     * Builds ai.txt body
     */
    public function build_ai_txt_content(): string {
        $settings  = get_option( 'sarfee_ai_settings', [] );
        $site_name = ! empty( $settings['llms_site_title'] ) ? $settings['llms_site_title'] : ( get_bloginfo( 'name' ) ?: 'صرفی' );
        $site_url  = home_url( '/' );
        $llms_url  = home_url( '/llms.txt' );
        $full_url  = home_url( '/llms-full.txt' );

        $disclaimer_url = ! empty( $settings['disclaimer_page_id'] ) 
            ? get_permalink( (int) $settings['disclaimer_page_id'] ) 
            : ( ! empty( $settings['disclaimer_custom_url'] ) ? $settings['disclaimer_custom_url'] : home_url( '/disclaimer/' ) );

        $report_url = ! empty( $settings['report_content_page_id'] ) 
            ? get_permalink( (int) $settings['report_content_page_id'] ) 
            : ( ! empty( $settings['report_content_custom_url'] ) ? $settings['report_content_custom_url'] : home_url( '/report-content/' ) );

        $lines = [
            "# ai.txt - AI Crawler and Usage Policy for {$site_name}",
            "# Site: {$site_url}",
            "",
            "# General Model Usage Rights",
            "User-agent: *",
            "Allow: /",
            "",
            "# AI Permissions",
            "Training: allowed",
            "Inference: allowed",
            "Attribution: required",
            "Attribution-Url: {$site_url}",
            "",
            "# Search Engine & Conversational AI Bots",
            "User-agent: GPTBot",
            "Allow: /",
            "",
            "User-agent: ChatGPT-User",
            "Allow: /",
            "",
            "User-agent: PerplexityBot",
            "Allow: /",
            "",
            "User-agent: ClaudeBot",
            "Allow: /",
            "",
            "User-agent: Google-Extended",
            "Allow: /",
            "",
            "User-agent: Applebot-Extended",
            "Allow: /",
            "",
            "User-agent: Bingbot",
            "Allow: /",
            "",
            "# Disallowed Aggressive Scraping Bots",
            "User-agent: CCBot",
            "Disallow: /",
            "",
            "# AI Context Files",
            "Context: {$llms_url}",
            "Context-Full: {$full_url}",
            "",
            "# Transparency & Editorial Policies",
            "Policy-Disclaimer: " . esc_url( $disclaimer_url ),
            "Policy-Corrections: " . esc_url( $report_url ),
        ];

        return implode( "\n", $lines ) . "\n";
    }

    /**
     * Enriches WordPress robots.txt with AI search engine friendly rules
     */
    public function filter_robots_txt( string $output, bool $public ): string {
        $settings = get_option( 'sarfee_ai_settings', [] );
        if ( isset( $settings['enable_ai_robots'] ) && empty( $settings['enable_ai_robots'] ) ) {
            return $output;
        }

        $llms_url = home_url( '/llms.txt' );
        $ai_url   = home_url( '/ai.txt' );

        // Standard AI crawlers supported by the plugin
        $standard_ai_bots = [
            'GPTBot'            => 'Allow: /',
            'PerplexityBot'     => 'Allow: /',
            'ClaudeBot'         => 'Allow: /',
            'Google-Extended'   => 'Allow: /',
            'Applebot-Extended' => 'Allow: /',
            'Bingbot'           => 'Allow: /',
        ];

        // Deduplicate: Only append bots that the user hasn't already defined in Rank Math or custom robots.txt
        $missing_bots = [];
        foreach ( $standard_ai_bots as $bot_name => $bot_rule ) {
            if ( ! preg_match( '/User-agent:\s*' . preg_quote( $bot_name, '/' ) . '\b/i', $output ) ) {
                $missing_bots[] = "User-agent: {$bot_name}\n{$bot_rule}\n";
            }
        }

        $context_notes = [];
        if ( strpos( $output, 'llms.txt' ) === false ) {
            $context_notes[] = "# LLM Context: {$llms_url}";
        }
        if ( strpos( $output, 'ai.txt' ) === false ) {
            $context_notes[] = "# AI Policy: {$ai_url}";
        }

        // If everything is already covered in Rank Math, nothing more to add
        if ( empty( $missing_bots ) && empty( $context_notes ) ) {
            return $output;
        }

        $addition = "\n# --- Sarfee AI Engine (GEO & AEO Rules) ---\n";
        if ( ! empty( $missing_bots ) ) {
            $addition .= implode( "\n", $missing_bots ) . "\n";
        }
        if ( ! empty( $context_notes ) ) {
            $addition .= "# Context resources for Generative Engines\n" . implode( "\n", $context_notes ) . "\n";
        }
        $addition .= "# --- End Sarfee AI Engine ---\n";

        return trim( $output ) . "\n" . $addition;
    }

    /**
     * Injects headers encouraging maximum snippet length for AI Overviews
     */
    public function inject_ai_response_headers(): void {
        if ( is_admin() ) {
            return;
        }

        // Permit maximum snippet & preview to improve chances of Perplexity and Google SGE citations
        header( 'X-Robots-Tag: max-snippet:-1, max-image-preview:large, max-video-preview:-1', false );
    }

    /**
     * Injects AI discovery links and Answer Engine citation meta tags into <head>
     */
    public function inject_ai_head_tags(): void {
        if ( is_admin() ) {
            return;
        }

        $settings  = get_option( 'sarfee_ai_settings', [] );
        $site_name = ! empty( $settings['llms_site_title'] ) ? $settings['llms_site_title'] : ( get_bloginfo( 'name' ) ?: 'صرفی' );

        echo "\n<!-- Sarfee AI Engine: AI Discovery & Citation Protocols -->\n";

        // 1. LLMs Discovery Links (llmstxt.org specification)
        if ( ! isset( $settings['enable_llms'] ) || ! empty( $settings['enable_llms'] ) ) {
            $llms_url = home_url( '/llms.txt' );
            $full_url = home_url( '/llms-full.txt' );
            echo '<link rel="alternate" type="text/markdown" href="' . esc_url( $llms_url ) . '" title="LLM Context" />' . "\n";
            echo '<link rel="alternate" type="text/markdown" href="' . esc_url( $full_url ) . '" title="Full LLM Context" />' . "\n";
        }

        // 2. AI Rights & Attribution Policy Link
        if ( ! isset( $settings['enable_ai_txt'] ) || ! empty( $settings['enable_ai_txt'] ) ) {
            $ai_url = home_url( '/ai.txt' );
            echo '<link rel="alternate" type="text/plain" href="' . esc_url( $ai_url ) . '" title="AI Usage Policy" />' . "\n";
        }

        // 3. Citation Meta Tags for Answer Engines (Perplexity, Google AI Overviews, ChatGPT Search)
        if ( is_singular() ) {
            $post_id = get_the_ID();
            if ( $post_id ) {
                $title    = get_the_title( $post_id );
                $date_pub = get_the_date( 'Y-m-d', $post_id );
                $date_mod = get_the_modified_date( 'Y-m-d', $post_id ) ?: $date_pub;
                $author   = get_the_author_meta( 'display_name', (int) get_post_field( 'post_author', $post_id ) );

                if ( ! empty( $title ) ) {
                    echo '<meta name="citation_title" content="' . esc_attr( wp_strip_all_tags( $title ) ) . '" />' . "\n";
                }
                echo '<meta name="citation_publisher" content="' . esc_attr( $site_name ) . '" />' . "\n";
                if ( ! empty( $author ) ) {
                    echo '<meta name="citation_author" content="' . esc_attr( $author ) . '" />' . "\n";
                }
                if ( ! empty( $date_pub ) ) {
                    echo '<meta name="citation_publication_date" content="' . esc_attr( $date_pub ) . '" />' . "\n";
                }
                if ( ! empty( $date_mod ) ) {
                    echo '<meta name="citation_lastmod" content="' . esc_attr( $date_mod ) . '" />' . "\n";
                    echo '<meta name="citation_online_date" content="' . esc_attr( $date_mod ) . '" />' . "\n";
                }
                echo '<meta name="citation_fulltext_world_readable" content="" />' . "\n";
            }
        }
        echo "<!-- /Sarfee AI Engine -->\n";
    }

    /**
     * Lightweight tracking of AI crawler visits
     */
    public function track_ai_bot_visit(): void {
        if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
            return;
        }

        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if ( empty( $ua ) ) {
            return;
        }

        $bot_name = '';
        if ( stripos( $ua, 'GPTBot' ) !== false || stripos( $ua, 'ChatGPT-User' ) !== false ) {
            $bot_name = 'GPTBot (ChatGPT)';
        } elseif ( stripos( $ua, 'PerplexityBot' ) !== false ) {
            $bot_name = 'PerplexityBot';
        } elseif ( stripos( $ua, 'ClaudeBot' ) !== false || stripos( $ua, 'Claude-Web' ) !== false ) {
            $bot_name = 'ClaudeBot (Anthropic)';
        } elseif ( stripos( $ua, 'Google-Extended' ) !== false ) {
            $bot_name = 'Google-Extended (Gemini)';
        } elseif ( stripos( $ua, 'Applebot-Extended' ) !== false ) {
            $bot_name = 'Applebot-Extended';
        }

        if ( empty( $bot_name ) ) {
            return;
        }

        // Lightweight aggregated storage
        $stats = get_option( 'sarfee_ai_bot_stats', [] );
        if ( ! is_array( $stats ) ) {
            $stats = [];
        }

        $today = current_time( 'Y-m-d' );

        if ( ! isset( $stats['counts'][ $bot_name ] ) ) {
            $stats['counts'][ $bot_name ] = [ 'total' => 0, 'today' => 0, 'date' => $today ];
        }

        // Reset today counter if date changed
        if ( ( $stats['counts'][ $bot_name ]['date'] ?? '' ) !== $today ) {
            $stats['counts'][ $bot_name ]['today'] = 0;
            $stats['counts'][ $bot_name ]['date']  = $today;
        }

        $stats['counts'][ $bot_name ]['total']++;
        $stats['counts'][ $bot_name ]['today']++;

        // Keep last 15 visits
        if ( ! isset( $stats['log'] ) || ! is_array( $stats['log'] ) ) {
            $stats['log'] = [];
        }

        $req_url = ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http' ) . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

        array_unshift( $stats['log'], [
            'bot'  => $bot_name,
            'url'  => esc_url_raw( $req_url ),
            'time' => current_time( 'mysql' ),
            'ip'   => sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ),
        ] );

        $stats['log'] = array_slice( $stats['log'], 0, 150 );

        update_option( 'sarfee_ai_bot_stats', $stats, false );
    }
}
