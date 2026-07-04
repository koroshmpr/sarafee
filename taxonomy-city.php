<?php
/**
 * Template: City taxonomy archive
 *
 * Loads the Elementor header/footer (via Elementor Pro Theme Builder hooks)
 * and renders the exchange archive via the [exchange_archive] shortcode.
 *
 * Edit the shortcode attributes below to set your featured cities and rate page.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Force full-width layout (no sidebar) for this template.
add_filter( 'astra_page_layout', function () { return 'no-sidebar'; } );
add_filter( 'astra_the_title_enabled', '__return_false' ); // hide Astra's default page title

get_header();
?>

<div id="primary" <?php astra_primary_class(); ?>>

    <?php
    echo do_shortcode(
        '[exchange_archive
            featured_cities="london,manchester,birmingham,leeds"
            rate_page="/best-rate/"
        ]'
    );
    ?>

</div><!-- #primary -->

<?php
get_footer();
