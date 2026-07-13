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
        wp_enqueue_style( 'chld_thm_cfg_child', trailingslashit( get_stylesheet_directory_uri() ) . 'style.css', array(  ) );
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
//require get_stylesheet_directory() . '/custom-components/rating-value.php';
// exchanges
require get_stylesheet_directory() . '/exchanges/exchangeDetails.php';
require get_stylesheet_directory() . '/exchanges/exchangeAbout.php';
require get_stylesheet_directory() . '/exchanges/exchangeArchive.php';
require get_stylesheet_directory() . '/exchanges/exchangeUrls.php';
// symbols
require get_stylesheet_directory() . '/symbols/symbolUrls.php';
require get_stylesheet_directory() . '/symbols/currencyCardTable.php';
require get_stylesheet_directory() . '/symbols/currencyLivePrice.php';
require get_stylesheet_directory() . '/symbols/related_post.php';

// --- INCLUDE CUSTOM FUNCTIONALITY --- //
$inc_dir = get_stylesheet_directory() . '/inc/';

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
