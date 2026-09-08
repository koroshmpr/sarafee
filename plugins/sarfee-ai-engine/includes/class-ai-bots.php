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
        add_action( 'template_redirect', [ $this, 'handle_ai_txt_request' ] );
        add_filter( 'robots_txt', [ $this, 'filter_robots_txt' ], 100, 2 );
        add_action( 'send_headers', [ $this, 'inject_ai_response_headers' ] );
    }

    /**
     * Handles /ai.txt endpoint
     */
    public function handle_ai_txt_request(): void {
        if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
            return;
        }

        $path = trim( parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' );
        if ( $path !== 'ai.txt' ) {
            return;
        }

        $settings = get_option( 'sarfee_ai_settings', [] );
        if ( isset( $settings['enable_ai_txt'] ) && empty( $settings['enable_ai_txt'] ) ) {
            return;
        }

        header( 'Content-Type: text/plain; charset=utf-8' );
        header( 'X-Robots-Tag: all' );
        header( 'Cache-Control: public, max-age=86400' );

        echo $this->build_ai_txt_content();
        exit;
    }

    /**
     * Builds ai.txt body
     */
    public function build_ai_txt_content(): string {
        $site_url = home_url( '/' );
        $llms_url = home_url( '/llms.txt' );
        $full_url = home_url( '/llms-full.txt' );

        $lines = [
            "# ai.txt - AI Crawler and Usage Policy for Sarfee",
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
            "Policy-Disclaimer: " . esc_url( home_url( '/disclaimer/' ) ),
            "Policy-Corrections: " . esc_url( home_url( '/report-content/' ) ),
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

        $ai_directives = [
            "",
            "# --- Sarfee AI Engine (GEO & AEO Rules) ---",
            "User-agent: GPTBot",
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
            "User-agent: Bingbot",
            "Allow: /",
            "",
            "# Context resources for Generative Engines",
            "# LLM Context: {$llms_url}",
            "# AI Policy: {$ai_url}",
            "# --- End Sarfee AI Engine ---",
            "",
        ];

        return trim( $output ) . "\n" . implode( "\n", $ai_directives );
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
}
