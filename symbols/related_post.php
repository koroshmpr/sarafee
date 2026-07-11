<?php
/**
 * Elementor Custom Query: Show posts with the same category as the current single post
 */
add_action( 'elementor/query/related_symbols_by_category', function( $query ) {

    // 1. Get the current post ID
    $current_post_id = get_queried_object_id();

    // 2. Make sure we are on a single post page
    if ( ! is_single() || empty( $current_post_id ) ) {
        return;
    }

    // 3. Define the taxonomy.
    // If you are using the default WordPress categories, leave it as 'category'.
    // If your custom post type uses a custom taxonomy (e.g., 'symbol_category'), change it here.
    $taxonomy = 'category';

    // 4. Get the terms (categories) attached to the current post
    $terms = wp_get_post_terms( $current_post_id, $taxonomy, array( 'fields' => 'slugs' ) );

    // 5. If the post has categories, modify the Elementor query
    if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
        $tax_query = array(
            array(
                'taxonomy' => $taxonomy,
                'field'    => 'slug',
                'terms'    => $terms,
            ),
        );

        $query->set( 'tax_query', $tax_query );

        // Optional but recommended: Exclude the current post so it doesn't show up in its own related list
        $query->set( 'post__not_in', array( $current_post_id ) );
    }
} );