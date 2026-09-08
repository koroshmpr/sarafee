<?php
// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

// BEGIN ENQUEUE PARENT ACTION
// AUTO GENERATED - Do not modify or remove comment markers above or below:

if ( !function_exists( 'chld_thm_cfg_locale_css' ) ):
    function chld_thm_cfg_locale_css( $uri ){
        if ( empty( $uri ) && is_rtl() && file_exists( get_template_directory() . '/rtl.css' ) )
            $uri = get_template_directory_uri() . '/rtl.css';
        return $uri;
    }
endif;
add_filter( 'locale_stylesheet_uri', 'chld_thm_cfg_locale_css' );
         
if ( !function_exists( 'child_theme_configurator_css' ) ):
    function child_theme_configurator_css() {
        $dir_uri  = trailingslashit( get_stylesheet_directory_uri() );
        $dir_path = trailingslashit( get_stylesheet_directory() );

        // 1. Child Theme Main Stylesheet
        $style_ver = file_exists( $dir_path . 'style.css' ) ? filemtime( $dir_path . 'style.css' ) : '1.0.0';
        wp_enqueue_style( 'chld_thm_cfg_child', $dir_uri . 'style.css', array(), $style_ver );

        // 2. Core Variables, Keyframes & Utility Classes
        if ( file_exists( $dir_path . 'assets/css/core-utilities.css' ) ) {
            wp_enqueue_style( 'scf-core-utilities', $dir_uri . 'assets/css/core-utilities.css', array( 'chld_thm_cfg_child' ), filemtime( $dir_path . 'assets/css/core-utilities.css' ) );
        }

        // 3. Header Price Mega Menu (Eliminates FOUC and menu layout shift)
        if ( file_exists( $dir_path . 'assets/css/header-mega-menu.css' ) ) {
            wp_enqueue_style( 'scf-header-mega-menu', $dir_uri . 'assets/css/header-mega-menu.css', array( 'scf-core-utilities' ), filemtime( $dir_path . 'assets/css/header-mega-menu.css' ) );
        }

        // 4. Symbols, Currencies & Gold Components
        if ( file_exists( $dir_path . 'assets/css/symbols.css' ) ) {
            wp_enqueue_style( 'scf-symbols-components', $dir_uri . 'assets/css/symbols.css', array( 'scf-core-utilities' ), filemtime( $dir_path . 'assets/css/symbols.css' ) );
        }

        // 5. Exchanges & Directory Components
        if ( file_exists( $dir_path . 'assets/css/exchanges.css' ) ) {
            wp_enqueue_style( 'scf-exchanges-components', $dir_uri . 'assets/css/exchanges.css', array( 'scf-core-utilities' ), filemtime( $dir_path . 'assets/css/exchanges.css' ) );
        }

        // 6. Comments, Reviews & Notice Box
        if ( file_exists( $dir_path . 'assets/css/comments.css' ) ) {
            wp_enqueue_style( 'scf-comments-components', $dir_uri . 'assets/css/comments.css', array( 'scf-core-utilities' ), filemtime( $dir_path . 'assets/css/comments.css' ) );
        }
    }
endif;
add_action( 'wp_enqueue_scripts', 'child_theme_configurator_css', 10 );

// END ENQUEUE PARENT ACTION

// Dequeue Elementor's MCP kit script when the Angie plugin is not installed.
// Without Angie, the script calls /angie/v1/ REST endpoints that don't exist,
// producing a 404 → sprintf ReferenceError cascade in the editor console.
add_action( 'admin_enqueue_scripts', function () {
    if ( ! defined( 'ANGIE_VERSION' ) && ! class_exists( 'Angie' ) ) {
        wp_dequeue_script( 'elementor-v2-elementor-kit-mcp' );
    }
}, 100 );
require get_stylesheet_directory() . '/custom-components/faq.php';
require get_stylesheet_directory() . '/custom-components/page-content.php';
require get_stylesheet_directory() . '/custom-components/optimize.php';
require get_stylesheet_directory() . '/custom-components/notice-box.php';
//require get_stylesheet_directory() . '/custom-components/rating-value.php';

//gravity form
require get_stylesheet_directory() . '/gform/city-query.php';
require get_stylesheet_directory() . '/gform/gravity-forms-style.php';
// exchanges
require get_stylesheet_directory() . '/exchanges/exchangeDetails.php';
require get_stylesheet_directory() . '/exchanges/exchangeLicense.php';
require get_stylesheet_directory() . '/exchanges/exchangeAbout.php';
require get_stylesheet_directory() . '/exchanges/exchangeArchive.php';
require get_stylesheet_directory() . '/exchanges/symbolExchangesList.php';
require get_stylesheet_directory() . '/exchanges/exchangeUrls.php';
//require get_stylesheet_directory() . '/exchanges/exchangeGoogleReviews.php';
// symbols
require get_stylesheet_directory() . '/symbols/ai_currency_analysis.php';
require get_stylesheet_directory() . '/symbols/ai_currency_forecast.php';
require get_stylesheet_directory() . '/symbols/rest-api.php';
require get_stylesheet_directory() . '/symbols/symbolUrls.php';
require get_stylesheet_directory() . '/symbols/currencyCardTable.php';
require get_stylesheet_directory() . '/symbols/currencyLivePrice.php';
require get_stylesheet_directory() . '/symbols/currencyCalculator.php';
require get_stylesheet_directory() . '/symbols/related_post.php';
require get_stylesheet_directory() . '/symbols/symbolArchiveTable.php';
require get_stylesheet_directory() . '/symbols/headerMegaMenu.php';

// --- INCLUDE CUSTOM FUNCTIONALITY --- //
$inc_dir = get_stylesheet_directory() . '/inc/';
require_once $inc_dir . 'import-symbols.php';


require_once get_stylesheet_directory() . '/symbols/symbolSearch.php';

// Custom TinyMCE buttons
if (file_exists($inc_dir . 'custom-tiny-mce-button.php')) {
    require_once $inc_dir . 'custom-tiny-mce-button.php';
}

// REST API Endpoints (n8n integration)
if (file_exists($inc_dir . 'rest-api.php')) {
    require_once $inc_dir . 'rest-api.php';
}

// Custom Shortcodes
if (file_exists($inc_dir . 'shortcodes.php')) {
    require_once $inc_dir . 'shortcodes.php';
}

// City Taxonomy Schema
if (file_exists($inc_dir . 'city-schema.php')) {
    require_once $inc_dir . 'city-schema.php';
}

// Symbol Single Schema
if (file_exists($inc_dir . 'symbol-schema.php')) {
    require_once $inc_dir . 'symbol-schema.php';
}

/**
 * افزودن دکمه «گزارش این نظر» کنار دکمه پاسخ در هر دیدگاه
 * (فقط برای پست‌تایپ‌های غیر از post مانند صرافی‌ها، نمادها و...)
 * جهت ارسال خودکار شناسه نظر و آدرس به فرم گرویتی در /report-content/
 */
add_filter( 'comment_reply_link', function( $link, $args, $comment, $post ) {
    if ( is_admin() ) {
        return $link;
    }

    $post_obj = is_numeric( $post ) ? get_post( $post ) : ( $post ?: get_post() );
    if ( ! $post_obj || get_post_type( $post_obj ) === 'post' ) {
        return $link;
    }

    if ( empty( $comment ) ) {
        $comment = get_comment();
    }
    if ( ! $comment ) {
        return $link;
    }

    $comment_id   = $comment->comment_ID;
    $comment_link = get_comment_link( $comment );
    $post_title   = get_the_title( $post_obj );

    $report_url = add_query_arg( [
        'comment_id' => $comment_id,
        'page_url'   => rawurlencode( $comment_link ),
        'page_title' => rawurlencode( $post_title ),
    ], home_url( '/report-content/' ) );

    $report_btn = sprintf(
        '<a href="%s" class="scf-report-comment-link" target="_blank" rel="nofollow" title="گزارش این نظر">'
        . '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"></path><line x1="4" y1="22" x2="4" y2="15"></line></svg>'
        . 'گزارش این نظر</a>',
        esc_url( $report_url )
    );

    return $link . ' ' . $report_btn;
}, 10, 4 );
