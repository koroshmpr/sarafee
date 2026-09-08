<?php
/**
 * IndexNow Protocol Implementation
 * Automatically notifies Microsoft Bing, Copilot, Yandex & other engines of content updates.
 * Features smart local-environment detection for development sites.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Sarfee_AI_IndexNow {

    public function __construct() {
        add_action( 'template_redirect', [ $this, 'handle_key_file_request' ] );
        add_action( 'save_post', [ $this, 'on_save_post' ], 20, 2 );
    }

    /**
     * Checks if current host is a local development environment
     */
    public function is_local_host( ?string $host = null ): bool {
        if ( null === $host ) {
            $host = wp_parse_url( home_url(), PHP_URL_HOST );
        }
        if ( empty( $host ) ) {
            return false;
        }

        $local_domains = [ 'localhost', '127.0.0.1', '::1' ];
        if ( in_array( $host, $local_domains, true ) ) {
            return true;
        }

        return (bool) preg_match( '/\.(local|test|dev|internal)$/i', $host );
    }

    /**
     * Ensures an IndexNow API Key exists
     */
    public function ensure_api_key(): string {
        $key = get_option( 'sarfee_ai_indexnow_key' );
        if ( empty( $key ) || ! is_string( $key ) ) {
            $key = bin2hex( random_bytes( 16 ) ); // 32 hex chars
            update_option( 'sarfee_ai_indexnow_key', $key );
        }
        return $key;
    }

    /**
     * Responds to /{key}.txt verification request
     */
    public function handle_key_file_request(): void {
        if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
            return;
        }

        $key = $this->ensure_api_key();
        $path = trim( parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' );

        if ( $path === $key . '.txt' ) {
            header( 'Content-Type: text/plain; charset=utf-8' );
            header( 'X-Robots-Tag: noindex, nofollow' );
            echo $key;
            exit;
        }
    }

    /**
     * Triggers on post save/publish
     */
    public function on_save_post( int $post_id, WP_Post $post ): void {
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }

        if ( $post->post_status !== 'publish' ) {
            return;
        }

        $allowed_types = [ 'exchange', 'symbol', 'post', 'page' ];
        if ( ! in_array( $post->post_type, $allowed_types, true ) ) {
            return;
        }

        $settings = get_option( 'sarfee_ai_settings', [] );
        if ( isset( $settings['enable_indexnow'] ) && empty( $settings['enable_indexnow'] ) ) {
            return;
        }

        $url = get_permalink( $post_id );
        if ( ! empty( $url ) ) {
            $this->ping_urls( [ $url ] );
        }
    }

    /**
     * Pings IndexNow API with given URLs
     */
    public function ping_urls( array $urls ): array {
        if ( empty( $urls ) ) {
            return [ 'success' => false, 'message' => 'هیچ آدرسی ارسال نشده است.' ];
        }

        $key      = $this->ensure_api_key();
        $host     = wp_parse_url( home_url(), PHP_URL_HOST );
        $key_loc  = home_url( "/{$key}.txt" );
        $is_local = $this->is_local_host( $host );

        $body = [
            'host'        => $host,
            'key'         => $key,
            'keyLocation' => $key_loc,
            'urlList'     => array_values( array_unique( $urls ) ),
        ];

        $response = wp_remote_post( 'https://api.indexnow.org/indexnow', [
            'headers'     => [ 'Content-Type' => 'application/json; charset=utf-8' ],
            'body'        => wp_json_encode( $body ),
            'timeout'     => 10,
            'blocking'    => true,
            'sslverify'   => false,
        ] );

        $status = is_wp_error( $response ) ? 500 : wp_remote_retrieve_response_code( $response );
        $raw_msg = is_wp_error( $response ) ? $response->get_error_message() : wp_remote_retrieve_body( $response );

        // IndexNow returns 200 (OK) or 202 (Accepted)
        $is_success = in_array( $status, [ 200, 202 ], true );

        // Human-friendly message, especially for local environments
        if ( $is_success ) {
            $display_msg = 'موفقیت‌آمیز (تغییرات به موتورهای جستجو مخابره شد)';
        } elseif ( $status === 429 && $is_local ) {
            $display_msg = 'محیط لوکال (TooManyRequests): سرورهای مایکروسافت دامنه‌های localhost را ایندکس نمی‌کنند. روی هاست اصلی با دامنه واقعی کد ۲۰۰ ثبت خواهد شد.';
        } elseif ( $status === 422 ) {
            $display_msg = 'خطای اعتبارسنجی دامنه یا کلید (در لوکال طبیعی است).';
        } else {
            $display_msg = 'کد ' . $status . ': ' . ( $raw_msg ?: 'خطای ناشناخته' );
        }

        // Log the activity (keep last 20)
        $this->log_activity( $urls, $status, $display_msg );

        return [
            'success' => $is_success,
            'status'  => $status,
            'message' => $display_msg,
        ];
    }

    private function log_activity( array $urls, int $status, string $message ): void {
        $logs = get_option( 'sarfee_ai_indexnow_log', [] );
        if ( ! is_array( $logs ) ) {
            $logs = [];
        }

        array_unshift( $logs, [
            'time'    => current_time( 'mysql' ),
            'urls'    => $urls,
            'status'  => $status,
            'message' => $message,
        ] );

        $logs = array_slice( $logs, 0, 20 );
        update_option( 'sarfee_ai_indexnow_log', $logs );
    }
}
