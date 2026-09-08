<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Custom URL structure for the exchange post type + city taxonomy.
 *
 * City archive : (site)/london/
 * Single post  : (site)/london/sarafi-arman/
 *
 * KEY DESIGN DECISION
 * -------------------
 * We set rewrite => false on the exchange post type because WordPress
 * auto-generates broad ([^/]+) patterns when the slug contains a rewrite
 * tag like %city%. Those broad rules intercept /blog/, /blog/post-name/,
 * and any other standard WordPress URL.
 *
 * Instead we add:
 *   • One rule per city slug at TOP     → /london/
 *   • One broad two-segment rule at BOTTOM → /london/sarafi-arman/
 *
 * The BOTTOM rule fires only after WordPress's own rules have had first pick,
 * so /blog/post-name/ is caught by the standard blog rule and never touches ours.
 */

// ── 1. Fallback taxonomy registration ────────────────────────────────────────
//    Keeps 'city' alive when ACF is disabled (e.g. Elementor safe mode).
add_action( 'init', function () {
    if ( ! taxonomy_exists( 'city' ) ) {
        register_taxonomy( 'city', [ 'exchange' ], [
            'label'              => 'شهر',
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_rest'       => true,
            'rewrite'            => [ 'slug' => 'city', 'with_front' => false ],
        ] );
    }
}, 20 );

// ── 2. Register %city% rewrite tag ────────────────────────────────────────────
add_action( 'init', function () {
    add_rewrite_tag( '%city%', '([^/]+)', 'city=' );
}, 1 );

// ── 3. Disable auto-generated rewrite rules for the exchange post type ─────────
//    WordPress expands %city% to ([^/]+) when building the permastruct, which
//    creates ^([^/]+)/?$ (archive) and ^([^/]+)/([^/]+)/?$ (single) — both
//    too broad. We replace them with our own controlled rules below.
add_filter( 'register_post_type_args', function ( $args, $post_type ) {
    if ( $post_type !== 'exchange' ) {
        return $args;
    }
    $args['rewrite'] = false;
    return $args;
}, 10, 2 );

// ── 4. Per-city archive rules – specific slugs only (TOP priority) ────────────
//    /london/ → city taxonomy archive
//    Only actual city terms get rules; /blog/, /shop/, /about/, etc. are safe.
add_action( 'init', function () {
    $slugs = get_terms( [
        'taxonomy'   => 'city',
        'hide_empty' => false,
        'fields'     => 'slugs',
        'number'     => 500,
    ] );
    if ( is_wp_error( $slugs ) || empty( $slugs ) ) {
        return;
    }
    foreach ( $slugs as $slug ) {
        add_rewrite_rule(
            '^' . preg_quote( $slug, '/' ) . '/?$',
            'index.php?city=' . $slug,
            'top'
        );
    }
}, 20 );

// ── 5. Two-segment exchange single post rule (BOTTOM priority) ─────────────────
//    /london/sarafi-arman/ → exchange post lookup
//
//    BOTTOM means it lives AFTER all standard WordPress rules in the list.
//    So /blog/post-name/ is already matched by WordPress's own blog rule
//    (^blog/([^/]+)/?$) and never reaches this rule.
add_action( 'init', function () {
    add_rewrite_rule(
        '^([^/]+)/([^/]+)/?$',
        'index.php?city=$matches[1]&exchange=$matches[2]',
        'bottom'
    );
}, 20 );

// ── 6. Request filter – validate and clean up exchange post lookups ────────────
//    When our bottom rule fires (city + exchange vars set), confirm the city
//    is a real term. If not, it means a URL slipped through that standard
//    WordPress rules should have caught — return 404 as a safety net.
add_filter( 'request', function ( $qv ) {
    if ( empty( $qv['exchange'] ) || empty( $qv['city'] ) ) {
        return $qv;
    }
    if ( get_term_by( 'slug', $qv['city'], 'city' ) ) {
        // Valid exchange post request — drop city so WP resolves the post cleanly.
        unset( $qv['city'] );
        return $qv;
    }
    $qv['error'] = '404';
    return $qv;
}, 10 );

// ── 7. Breadcrumb schema: replace ALL BreadcrumbList schemas for exchange posts ─
//    Schema Pro outputs multiple BreadcrumbLists and we can't reliably hook into
//    its internals. Instead we buffer the entire page output on template_redirect,
//    then swap every BreadcrumbList JSON-LD block: first occurrence becomes our
//    correct city→exchange schema, all duplicates are removed.
add_action( 'template_redirect', function () {
    if ( ! is_singular( 'exchange' ) ) {
        return;
    }

    // Pre-compute while the post context is guaranteed to be set.
    $terms = get_the_terms( get_the_ID(), 'city' );
    if ( ! $terms || is_wp_error( $terms ) ) {
        return;
    }
    $city_url = get_term_link( $terms[0], 'city' );
    if ( is_wp_error( $city_url ) ) {
        return;
    }

    $correct_schema = '<script type="application/ld+json">'
        . wp_json_encode( [
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => [
                [
                    '@type'    => 'ListItem',
                    'position' => 1,
                    'item'     => [ '@id' => $city_url, 'name' => $terms[0]->name ],
                ],
                [
                    '@type'    => 'ListItem',
                    'position' => 2,
                    'item'     => [ '@id' => get_permalink(), 'name' => get_the_title() ],
                ],
            ],
        ], JSON_UNESCAPED_UNICODE )
        . '</script>';

    ob_start( function ( $html ) use ( $correct_schema ) {
        $replaced = false;
        return preg_replace_callback(
            '#<script[^>]*type=[\'"]application/ld\+json[\'"][^>]*>.*?</script>#si',
            function ( $match ) use ( $correct_schema, &$replaced ) {
                if ( strpos( $match[0], '"BreadcrumbList"' ) === false ) {
                    return $match[0]; // not a breadcrumb — leave it alone
                }
                if ( ! $replaced ) {
                    $replaced = true;
                    return $correct_schema; // replace first occurrence
                }
                return ''; // strip all duplicates
            },
            $html
        );
    } );
} );

// ── 8. Rebuild rules automatically when city terms change ─────────────────────
foreach ( [ 'created_city', 'edited_city', 'deleted_city' ] as $_hook ) {
    add_action( $_hook, function () {
        flush_rewrite_rules( false );
    } );
}
unset( $_hook );

// ── 8. Correct permalink for exchange single posts ────────────────────────────
//    Constructs the URL directly (no %city% placeholder) since rewrite is false.
add_filter( 'post_type_link', function ( $url, $post ) {
    if ( $post->post_type !== 'exchange' ) {
        return $url;
    }
    $terms = get_the_terms( $post->ID, 'city' );
    if ( ! $terms || is_wp_error( $terms ) ) {
        return $url;
    }
    return trailingslashit( home_url( '/' . $terms[0]->slug . '/' . $post->post_name ) );
}, 10, 2 );

// ── 9. Correct URL for city taxonomy terms ────────────────────────────────────
add_filter( 'term_link', function ( $url, $term, $taxonomy ) {
    if ( $taxonomy !== 'city' ) {
        return $url;
    }
    return trailingslashit( home_url( '/' . $term->slug ) );
}, 10, 3 );

// ── 10. One-time flush – version bump clears old broad rules from DB ──────────
add_action( 'init', function () {
    if ( get_option( 'sarfee_exchange_rewrite_v' ) !== '4' ) {
        flush_rewrite_rules( false );
        update_option( 'sarfee_exchange_rewrite_v', '4' );
    }
}, 999 );
