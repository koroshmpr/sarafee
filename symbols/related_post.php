<?php
/**
 * Elementor Custom Query: Show blog posts whose category slug matches the current symbol page's slug or category
 * Usage in Elementor Posts / Loop Grid Widget: Set Query ID to 'related_symbols_by_category' or 'related_posts_by_symbol_category'
 * 
 * Automatically hides sections with class '.not-have-related-symbol' if no related posts exist for the symbol.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// 1. Elementor Query Modifier
function handle_related_symbols_by_category_query( $query ) {
    // Get current post ID
    $current_post_id = get_queried_object_id();

    // Ensure we are on a single post/symbol page
    if ( ! is_single() || empty( $current_post_id ) ) {
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

        $query->set( 'post_type', 'post' );
        $query->set( 'tax_query', $tax_query );
        $query->set( 'post__not_in', [ $current_post_id ] );
    }
}

// Hook for Elementor Query IDs
add_action( 'elementor/query/related_symbols_by_category', 'handle_related_symbols_by_category_query' );
add_action( 'elementor/query/related_posts_by_symbol_category', 'handle_related_symbols_by_category_query' );


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