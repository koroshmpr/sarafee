<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Custom URL structure for the 'symbol' post type and symbol-linked blog posts.
 *
 * Symbol single           : (site)/gold/
 * Post in symbol category : (site)/gold/some-article/
 * All other blog posts    : (site)/blog/some-article/
 *
 * A WordPress category whose slug matches a symbol slug is 301-redirected
 * to the symbol single page when visited.
 *
 * KEY DESIGN DECISIONS
 * --------------------
 * - rewrite => false on the symbol CPT prevents WordPress from auto-generating
 *   broad ([^/]+) rules that would swallow /blog/ and other standard URLs.
 * - Per-symbol rules are added at TOP in init/20: one for the single page and
 *   one for posts in that symbol's category. Because patterns are specific
 *   (slug is hardcoded), they never collide with each other or with city rules.
 * - Blog posts use a separate 'blog_post' query var (not the built-in 'name')
 *   so the request filter can enforce post_type=post and return 404 for misses.
 * - post_link filter overrides ALL regular post permalinks; cached helper
 *   keeps the symbol-slug lookup to one DB query per request.
 */

// ── 1. Disable auto-generated rewrite rules for the symbol CPT ───────────
add_filter( 'register_post_type_args', function ( $args, $post_type ) {
    if ( $post_type !== 'symbol' ) {
        return $args;
    }
    $args['rewrite'] = false;
    return $args;
}, 10, 2 );

// ── 2. Register custom query vars ─────────────────────────────────────────
add_filter( 'query_vars', function ( $qv ) {
    $qv[] = 'sym_cat';   // symbol slug for symbol-category post URLs
    $qv[] = 'sym_post';  // post slug  for symbol-category post URLs
    $qv[] = 'blog_post'; // post slug  for /blog/{slug}/ URLs
    return $qv;
} );

// ── 3. Per-symbol rewrite rules (TOP) + blog rule (TOP) ──────────────────
add_action( 'init', function () {
    // One pair of rules per published symbol
    $ids = get_posts( [
        'post_type'      => 'symbol',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ] );
    foreach ( $ids as $id ) {
        $slug = get_post_field( 'post_name', $id );
        if ( ! $slug ) continue;
        $q = preg_quote( $slug, '/' );

        // /gold/some-article/ → post in symbol category
        add_rewrite_rule(
            '^' . $q . '/([^/]+)/?$',
            'index.php?sym_cat=' . $slug . '&sym_post=$matches[1]',
            'top'
        );
        // /gold/ → symbol single (added after two-segment so it ends up first in rule list)
        add_rewrite_rule(
            '^' . $q . '/?$',
            'index.php?post_type=symbol&name=' . $slug,
            'top'
        );
    }

    // /blog/some-article/ → regular post (post_type enforced in request filter)
    add_rewrite_rule(
        '^blog/([^/]+)/?$',
        'index.php?blog_post=$matches[1]',
        'top'
    );
}, 20 );

// ── 4. Request filter: resolve custom vars to WordPress-native vars ────────
add_filter( 'request', function ( $qv ) {

    // ── /gold/some-article/ ───────────────────────────────────────────────
    if ( ! empty( $qv['sym_cat'] ) && ! empty( $qv['sym_post'] ) ) {
        $cat_slug  = $qv['sym_cat'];
        $post_slug = $qv['sym_post'];

        // A published symbol with this slug must exist
        $symbol = get_page_by_path( $cat_slug, OBJECT, 'symbol' );
        if ( ! $symbol || $symbol->post_status !== 'publish' ) {
            $qv['error'] = '404';
            return $qv;
        }

        // A WordPress category with the same slug must exist
        $cat = get_term_by( 'slug', $cat_slug, 'category' );
        if ( ! $cat ) {
            $qv['error'] = '404';
            return $qv;
        }

        // Find the published post in that category
        $posts = get_posts( [
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'name'           => $post_slug,
            'category__in'   => [ $cat->term_id ],
            'posts_per_page' => 1,
            'no_found_rows'  => true,
        ] );
        if ( empty( $posts ) ) {
            // Post was moved to a different category.
            // If it still exists, 301 to its new canonical URL so old links
            // don't silently serve stale content or leave dead bookmarks.
            $moved = get_posts( [
                'post_type'      => 'post',
                'post_status'    => 'publish',
                'name'           => $post_slug,
                'posts_per_page' => 1,
                'no_found_rows'  => true,
            ] );
            if ( ! empty( $moved ) ) {
                $new_url = get_permalink( $moved[0]->ID );
                add_action( 'template_redirect', function () use ( $new_url ) {
                    wp_safe_redirect( $new_url, 301 );
                    exit;
                }, 1 );
            }
            $qv['error'] = '404';
            return $qv;
        }

        unset( $qv['sym_cat'], $qv['sym_post'] );
        $qv['p'] = $posts[0]->ID;
        return $qv;
    }

    // ── /blog/some-article/ ───────────────────────────────────────────────
    if ( ! empty( $qv['blog_post'] ) ) {
        $post_slug = $qv['blog_post'];

        $posts = get_posts( [
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'name'           => $post_slug,
            'posts_per_page' => 1,
            'no_found_rows'  => true,
        ] );
        unset( $qv['blog_post'] );
        if ( empty( $posts ) ) {
            $qv['error'] = '404';
            return $qv;
        }
        // Post may have been moved INTO a symbol category after this URL was
        // indexed. Its canonical URL is now /{symbol}/{slug}/ — redirect.
        $canonical = get_permalink( $posts[0]->ID );
        if ( $canonical !== trailingslashit( home_url( '/blog/' . $post_slug ) ) ) {
            add_action( 'template_redirect', function () use ( $canonical ) {
                wp_safe_redirect( $canonical, 301 );
                exit;
            }, 1 );
            $qv['error'] = '404';
            return $qv;
        }
        $qv['p'] = $posts[0]->ID;
        return $qv;
    }

    return $qv;
}, 10 );

// ── 5. Correct URL for category terms that map to a symbol ───────────────
//    Elementor breadcrumb and Schema Pro both call get_term_link() for the
//    category crumb. Without this filter they output /category/{slug}/.
//    With it they output /{symbol-slug}/ automatically.
add_filter( 'term_link', function ( $url, $term, $taxonomy ) {
    if ( $taxonomy !== 'category' ) {
        return $url;
    }
    if ( ! in_array( $term->slug, _sarfee_get_symbol_slugs(), true ) ) {
        return $url;
    }
    return trailingslashit( home_url( '/' . $term->slug ) );
}, 10, 3 );

// ── 7. Correct permalink for symbol single pages ──────────────────────────
add_filter( 'post_type_link', function ( $url, $post ) {
    if ( $post->post_type !== 'symbol' ) {
        return $url;
    }
    return trailingslashit( home_url( '/' . $post->post_name ) );
}, 10, 2 );

// ── 6. Correct permalink for regular posts ────────────────────────────────
//    Posts in a symbol-matched category → /{symbol-slug}/{post-slug}/
//    All other posts                    → /blog/{post-slug}/
//    Non-published posts keep WordPress's default /?p={id} URL so the
//    native draft/preview flow is not broken.
add_filter( 'post_link', function ( $url, $post ) {
    if ( 'publish' !== $post->post_status ) {
        return $url;
    }
    $cats = get_the_category( $post->ID );
    if ( $cats ) {
        $symbol_slugs = _sarfee_get_symbol_slugs();
        foreach ( $cats as $cat ) {
            if ( in_array( $cat->slug, $symbol_slugs, true ) ) {
                return trailingslashit( home_url( '/' . $cat->slug . '/' . $post->post_name ) );
            }
        }
    }
    return trailingslashit( home_url( '/blog/' . $post->post_name ) );
}, 10, 2 );

// ── 7. Category archive whose slug matches a symbol → 301 to symbol ───────
add_action( 'template_redirect', function () {
    if ( ! is_category() ) {
        return;
    }
    $cat    = get_queried_object();
    $symbol = get_page_by_path( $cat->slug, OBJECT, 'symbol' );
    if ( ! $symbol || $symbol->post_status !== 'publish' ) {
        return;
    }
    wp_safe_redirect( get_permalink( $symbol ), 301 );
    exit;
} );

// ── 8. BreadcrumbList schema fix for posts in symbol categories ──────────
//    term_link (above) already corrects URLs inside Schema Pro's generation
//    pipeline. This ob_start safety net catches any hardcoded /category/
//    URLs that Schema Pro may have already resolved before our filter ran,
//    and strips duplicate BreadcrumbList blocks the same way as exchange posts.
add_action( 'template_redirect', function () {
    if ( ! is_singular( 'post' ) ) {
        return;
    }

    $cats = get_the_category( get_the_ID() );
    if ( ! $cats ) {
        return;
    }

    $symbol_slugs = _sarfee_get_symbol_slugs();
    $symbol_cat   = null;
    foreach ( $cats as $cat ) {
        if ( in_array( $cat->slug, $symbol_slugs, true ) ) {
            $symbol_cat = $cat;
            break;
        }
    }
    if ( ! $symbol_cat ) {
        return;
    }

    $symbol_url  = trailingslashit( home_url( '/' . $symbol_cat->slug ) );
    $post_url    = get_permalink();
    $post_title  = get_the_title();

    $correct_schema = '<script type="application/ld+json">'
        . wp_json_encode( [
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => [
                [
                    '@type'    => 'ListItem',
                    'position' => 1,
                    'item'     => [ '@id' => home_url( '/' ), 'name' => 'صفحه اصلی' ],
                ],
                [
                    '@type'    => 'ListItem',
                    'position' => 2,
                    'item'     => [ '@id' => $symbol_url, 'name' => $symbol_cat->name ],
                ],
                [
                    '@type'    => 'ListItem',
                    'position' => 3,
                    'item'     => [ '@id' => $post_url, 'name' => $post_title ],
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
                    return $match[0];
                }
                if ( ! $replaced ) {
                    $replaced = true;
                    return $correct_schema;
                }
                return '';
            },
            $html
        );
    } );
} );

// ── 10. Rebuild rewrite rules when symbols are saved or deleted ───────────
add_action( 'save_post_symbol', '_sarfee_flush_symbol_rules' );
add_action( 'delete_post', function ( $post_id ) {
    if ( get_post_type( $post_id ) === 'symbol' ) {
        _sarfee_flush_symbol_rules();
    }
} );
add_action( 'trashed_post', function ( $post_id ) {
    if ( get_post_type( $post_id ) === 'symbol' ) {
        _sarfee_flush_symbol_rules();
    }
} );
function _sarfee_flush_symbol_rules() {
    flush_rewrite_rules( false );
}

// ── 11. One-time flush to clear any stale rules from DB ──────────────────
add_action( 'init', function () {
    if ( get_option( 'sarfee_symbol_rewrite_v' ) !== '1' ) {
        flush_rewrite_rules( false );
        update_option( 'sarfee_symbol_rewrite_v', '1' );
    }
}, 999 );

// ── Helper: cached list of published symbol slugs ─────────────────────────
function _sarfee_get_symbol_slugs() {
    static $slugs = null;
    if ( $slugs !== null ) {
        return $slugs;
    }
    $ids   = get_posts( [
        'post_type'      => 'symbol',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ] );
    $slugs = array_map( function ( $id ) {
        return get_post_field( 'post_name', $id );
    }, $ids );
    return $slugs;
}
