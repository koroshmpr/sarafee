<?php
/**
 * Elementor Custom Query: Show blog posts whose category slug matches the current symbol page's slug or category
 * Usage in Elementor Posts / Loop Grid Widget: Set Query ID to 'related_symbols_by_category' or 'related_posts_by_symbol_category'
 * 
 * Automatically hides sections with class '.not-have-related-symbol' if no related posts exist for the symbol.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Helper to get the current post ID in both standard page loads and Elementor AJAX (Load More) calls
function get_elementor_related_current_post_id() {
    $post_id = get_queried_object_id();

    if ( empty( $post_id ) && wp_doing_ajax() ) {
        if ( ! empty( $_REQUEST['post_id'] ) ) {
            $post_id = intval( $_REQUEST['post_id'] );
        } elseif ( ! empty( $_REQUEST['queried_object_id'] ) ) {
            $post_id = intval( $_REQUEST['queried_object_id'] );
        } elseif ( ! empty( $_REQUEST['editor_post_id'] ) ) {
            $post_id = intval( $_REQUEST['editor_post_id'] );
        }
    }

    return $post_id;
}

// 1. Elementor Query Modifier
function handle_related_symbols_by_category_query( $query ) {
    // Get current post ID (supports standard page render & AJAX Load More)
    $current_post_id = get_elementor_related_current_post_id();

    if ( empty( $current_post_id ) ) {
        return;
    }

    $current_post = get_post( $current_post_id );
    if ( ! $current_post ) {
        return;
    }

    $target_slugs = [];

    // A) Get current symbol / page slug (e.g. 'usd', 'gold', 'eur')
    $symbol_slug = strtolower( trim( $current_post->post_name ) );
    if ( ! empty( $symbol_slug ) ) {
        $target_slugs[] = $symbol_slug;
    }

    // B) Also get category slugs attached to current post (in 'category' or 'symbol-category')
    $taxonomies = [ 'category', 'symbol-category', 'symbol_category' ];
    foreach ( $taxonomies as $tax ) {
        if ( taxonomy_exists( $tax ) ) {
            $terms = wp_get_post_terms( $current_post_id, $tax, [ 'fields' => 'slugs' ] );
            if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
                foreach ( $terms as $term_slug ) {
                    $target_slugs[] = strtolower( trim( $term_slug ) );
                }
            }
        }
    }

    $target_slugs = array_values( array_unique( array_filter( $target_slugs ) ) );

    // Determine target page number for pagination
    $paged = 1;
    foreach ( $_REQUEST as $key => $val ) {
        if ( strpos( $key, 'e-page-' ) === 0 || $key === 'page_number' ) {
            if ( intval( $val ) > 0 ) {
                $paged = intval( $val );
                break;
            }
        }
    }

    if ( $paged === 1 ) {
        if ( get_query_var( 'page' ) > 1 ) {
            $paged = intval( get_query_var( 'page' ) );
        } elseif ( get_query_var( 'paged' ) > 1 ) {
            $paged = intval( get_query_var( 'paged' ) );
        } elseif ( $query->get( 'paged' ) > 1 ) {
            $paged = intval( $query->get( 'paged' ) );
        } elseif ( $query->get( 'page' ) > 1 ) {
            $paged = intval( $query->get( 'page' ) );
        }
    }

    if ( ! empty( $target_slugs ) ) {
        // Query standard blog posts matching these category slugs
        $tax_query = [
            [
                'taxonomy' => 'category',
                'field'    => 'slug',
                'terms'    => $target_slugs,
                'operator' => 'IN',
            ],
        ];

        $existing_not_in = (array) $query->get( 'post__not_in' );
        $existing_not_in[] = $current_post_id;

        $query->set( 'post_type', 'post' );
        $query->set( 'tax_query', $tax_query );
        $query->set( 'post__not_in', array_values( array_unique( array_filter( $existing_not_in ) ) ) );
        $query->set( 'paged', $paged );

        // SQL ORDER BY RAND() re-shuffles all items on every page fetch, causing items from page 1 to randomly repeat on page 2.
        // We override 'rand' to 'date' DESC so pagination works deterministically without duplicate items.
        if ( in_array( strtolower( (string) $query->get( 'orderby' ) ), [ 'rand', 'random' ], true ) ) {
            $query->set( 'orderby', 'date' );
            $query->set( 'order', 'DESC' );
        }
    }
}

// Hook for Elementor Query IDs
add_action( 'elementor/query/related_symbols_by_category', 'handle_related_symbols_by_category_query' );
add_action( 'elementor/query/related_posts_by_symbol_category', 'handle_related_symbols_by_category_query' );

// 2. Prevent 404 Not Found & 301 redirects on single post pages when paginating inner Loop Grid (e.g. /gbp/2/ or /gbp/page/2/)
add_action( 'template_redirect', 'allow_single_post_loop_grid_pagination', 1 );
function allow_single_post_loop_grid_pagination() {
    if ( ( is_single() || is_singular() ) && ( get_query_var( 'page' ) > 1 || get_query_var( 'paged' ) > 1 ) ) {
        global $wp_query;
        $wp_query->is_404 = false;
        status_header( 200 );

        // Prevent WordPress canonical redirect from redirecting /gbp/2/ back to /gbp/
        remove_action( 'template_redirect', 'redirect_canonical' );
    }
}


// 2. Hide section if no related posts exist for the current symbol page
add_action( 'wp_head', 'hide_empty_related_symbols_section' );

function hide_empty_related_symbols_section() {
    if ( ! is_single() ) {
        return;
    }

    $current_post_id = get_queried_object_id();
    $current_post = get_post( $current_post_id );
    if ( ! $current_post ) {
        return;
    }

    $target_slugs = [];

    // Symbol slug
    $symbol_slug = strtolower( trim( $current_post->post_name ) );
    if ( ! empty( $symbol_slug ) ) {
        $target_slugs[] = $symbol_slug;
    }

    // Taxonomy terms
    $taxonomies = [ 'category', 'symbol-category', 'symbol_category' ];
    foreach ( $taxonomies as $tax ) {
        if ( taxonomy_exists( $tax ) ) {
            $terms = wp_get_post_terms( $current_post_id, $tax, [ 'fields' => 'slugs' ] );
            if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
                foreach ( $terms as $term_slug ) {
                    $target_slugs[] = strtolower( trim( $term_slug ) );
                }
            }
        }
    }

    $target_slugs = array_values( array_unique( array_filter( $target_slugs ) ) );
    $has_related = false;

    if ( ! empty( $target_slugs ) ) {
        $matching_posts = get_posts([
            'post_type'      => 'post',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'post__not_in'   => [ $current_post_id ],
            'tax_query'      => [
                [
                    'taxonomy' => 'category',
                    'field'    => 'slug',
                    'terms'    => $target_slugs,
                    'operator' => 'IN',
                ],
            ],
        ]);

        if ( ! empty( $matching_posts ) ) {
            $has_related = true;
        }
    }

    if ( ! $has_related ) {
        echo '<style>.not-have-related-symbol { display: none !important; }</style>' . "\n";
    }
}