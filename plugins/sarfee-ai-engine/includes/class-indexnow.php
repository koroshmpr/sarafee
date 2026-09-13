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
        add_action( 'init', [ $this, 'handle_key_file_request' ], 1 );
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
     * Ensures an IndexNow API Key exists and creates physical file in root
     */
    public function ensure_api_key(): string {
        $key = get_option( 'sarfee_ai_indexnow_key' );
        if ( empty( $key ) || ! is_string( $key ) ) {
            $key = bin2hex( random_bytes( 16 ) ); // 32 hex chars
            update_option( 'sarfee_ai_indexnow_key', $key );
        }
        $this->ensure_key_file( $key );
        return $key;
    }

    /**
     * Regenerates a fresh IndexNow API Key, cleans old file, and creates new physical file
     */
    public function regenerate_api_key(): string {
        $old_key = get_option( 'sarfee_ai_indexnow_key' );
        if ( ! empty( $old_key ) ) {
            $root_path = defined( 'ABSPATH' ) ? ABSPATH : ( dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/' );
            $old_file  = rtrim( $root_path, '/\\' ) . '/' . sanitize_file_name( $old_key ) . '.txt';
            if ( file_exists( $old_file ) ) {
                @unlink( $old_file );
            }
        }

        $new_key = bin2hex( random_bytes( 16 ) );
        update_option( 'sarfee_ai_indexnow_key', $new_key );
        $this->ensure_key_file( $new_key );
        return $new_key;
    }

    /**
     * Ensures physical /{key}.txt file exists in ABSPATH root for direct web server serving (LiteSpeed/Nginx)
     */
    public function ensure_key_file( ?string $key = null ): bool {
        if ( empty( $key ) ) {
            $key = get_option( 'sarfee_ai_indexnow_key' );
        }
        if ( empty( $key ) || ! is_string( $key ) ) {
            return false;
        }

        $clean_key = sanitize_file_name( trim( $key ) );
        if ( empty( $clean_key ) ) {
            return false;
        }

        $root_path = defined( 'ABSPATH' ) ? ABSPATH : ( dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/' );
        $file_path = rtrim( $root_path, '/\\' ) . '/' . $clean_key . '.txt';

        // Check if file already exists with exact content
        if ( file_exists( $file_path ) ) {
            $existing = @file_get_contents( $file_path );
            if ( false !== $existing && trim( $existing ) === $clean_key ) {
                return true;
            }
        }

        // Try writing the file
        if ( is_writable( dirname( $file_path ) ) || ( file_exists( $file_path ) && is_writable( $file_path ) ) ) {
            $written = @file_put_contents( $file_path, $clean_key, LOCK_EX );
            if ( false !== $written ) {
                @chmod( $file_path, 0644 );
                return true;
            }
        }

        return file_exists( $file_path );
    }

    /**
     * Responds to /{key}.txt verification request with guaranteed HTTP 200 and clean text
     */
    public function handle_key_file_request(): void {
        if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
            return;
        }

        $key = $this->ensure_api_key();
        $path = trim( (string) parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' );

        if ( $path === $key . '.txt' ) {
            // Guarantee HTTP 200 status code (prevent WP 404)
            if ( function_exists( 'status_header' ) ) {
                status_header( 200 );
            }
            if ( function_exists( 'http_response_code' ) ) {
                http_response_code( 200 );
            }

            // Clear any previous output buffers to avoid accidental whitespace/BOM
            while ( ob_get_level() > 0 ) {
                @ob_end_clean();
            }

            header( 'Content-Type: text/plain; charset=utf-8' );
            header( 'X-Robots-Tag: noindex, nofollow' );
            header( 'Cache-Control: public, max-age=86400' );
            header( 'X-Content-Type-Options: nosniff' );

            echo trim( $key );
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

        // Human-friendly message, especially for local environments & 403 verification issues
        if ( $status === 200 ) {
            $display_msg = 'موفقیت‌آمیز (کد ۲۰۰ - آدرس‌ها بلافاصله توسط موتورهای جستجو پردازش شدند)';
        } elseif ( $status === 202 ) {
            $display_msg = 'در صف پردازش (کد ۲۰۲ - کلید در حال اعتبارسنجی توسط ربات مایکروسافت)';
        } elseif ( $status === 403 ) {
            $display_msg = 'عدم احراز هویت کلید (کد ۴۰۳ Forbidden): کلید قبلی به دلیل خطای قبلی در سرور مایکروسافت مسدود شده است. لطفاً روی دکمه «🔄 تولید کلید تازه» کلیک کنید تا یک کلید جدید ساخته شده و اعتبار سنجی موفق شود.';
        } elseif ( $status === 429 && $is_local ) {
            $display_msg = 'محیط لوکال (TooManyRequests): سرورهای مایکروسافت دامنه‌های localhost را ایندکس نمی‌کنند. روی هاست اصلی با دامنه واقعی کد ۲۰۰ ثبت خواهد شد.';
        } elseif ( $status === 422 ) {
            $display_msg = 'خطای اعتبارسنجی دامنه یا کلید (۴۲۲ Unprocessable Entity): آدرس‌های ارسالی با هاست مطابقت ندارند.';
        } else {
            $display_msg = 'کد ' . $status . ': ' . ( $raw_msg ?: 'خطای ناشناخته' );
        }

        // Log the activity (keep last 100)
        $this->log_activity( $urls, $status, $display_msg );

        return [
            'success' => $is_success,
            'status'  => $status,
            'message' => $display_msg,
            'count'   => count( $urls ),
        ];
    }

    /**
     * Submits all published exchanges, symbols, posts, and pages to IndexNow in bulk
     */
    public function bulk_submit_all_urls(): array {
        $urls = [ home_url( '/' ) ];

        $post_types = [ 'exchange', 'symbol', 'post', 'page' ];
        foreach ( $post_types as $pt ) {
            $posts = get_posts( [
                'post_type'      => $pt,
                'post_status'    => 'publish',
                'posts_per_page' => 150, // High limit to cover all key entities
                'fields'         => 'ids',
            ] );

            foreach ( $posts as $pid ) {
                $permalink = get_permalink( $pid );
                if ( ! empty( $permalink ) ) {
                    $urls[] = $permalink;
                }
            }
        }

        $urls = array_values( array_unique( $urls ) );
        return $this->ping_urls( $urls );
    }

    /**
     * Clears IndexNow logs
     */
    public function clear_logs(): void {
        delete_option( 'sarfee_ai_indexnow_log' );
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

        $logs = array_slice( $logs, 0, 100 );
        update_option( 'sarfee_ai_indexnow_log', $logs, false );
    }
}
