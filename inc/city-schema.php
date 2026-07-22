<?php
/**
 * Dynamic JSON-LD Schema for City Taxonomy Archives
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_head', 'sarfee_city_taxonomy_schema' );

function sarfee_city_taxonomy_schema() {
    if ( ! is_tax( 'city' ) ) {
        return;
    }

    $term = get_queried_object();
    if ( ! $term || is_wp_error( $term ) ) {
        return;
    }

    $city_name = esc_html( $term->name );
    $term_link = esc_url( get_term_link( $term ) );
    $site_url  = esc_url( home_url( '/' ) );
    $site_host = rtrim( $site_url, '/' );

    $description = $term->description;
    if ( ! $description ) {
        $description = sprintf( 'مرجع مقایسه، استعلام نرخ زنده پوند و بررسی صرافیهای مجاز و معتبر در محلههای مختلف %s.', $city_name );
    } else {
        $description = wp_strip_all_tags( $description );
    }

    // Query exchanges in this city
    $query_args = [
        'post_type'      => 'exchange',
        'posts_per_page' => 20, // Limit to top 20 for schema
        'no_found_rows'  => true,
        'tax_query'      => [
            [
                'taxonomy' => 'city',
                'field'    => 'term_id',
                'terms'    => $term->term_id,
            ]
        ],
    ];

    $loop = new WP_Query( $query_args );
    $posts = $loop->posts;

    // Sort by rank like exchangeArchive.php does
    $rank_map = [];
    foreach ( $posts as $_p ) {
        $rank_map[ $_p->ID ] = (int) get_field( 'rank', $_p->ID );
    }
    usort( $posts, function ( $a, $b ) use ( $rank_map ) {
        $ra = $rank_map[ $a->ID ];
        $rb = $rank_map[ $b->ID ];
        if ( $ra > 0 && $rb > 0 ) return $ra - $rb;
        if ( $ra > 0 ) return -1;
        if ( $rb > 0 ) return  1;
        return 0;
    } );

    $list_items = [];
    $position = 1;
    foreach ( $posts as $post ) {
        $ex_name = esc_html( $post->post_title );
        $ex_url  = esc_url( get_permalink( $post->ID ) );
        
        // Fetch logo / thumbnail
        $logo_url = get_the_post_thumbnail_url( $post->ID, 'full' );
        if ( ! $logo_url ) {
            $logo_url = $site_host . '/images/logo.png';
        }

        $phone   = get_field( 'phone', $post->ID );
        $address = get_field( 'address', $post->ID );

        $item_details = [
            '@type' => 'FinancialService',
            '@id'   => $ex_url . '#exchange',
            'name'  => $ex_name,
            'url'   => $ex_url,
            'image' => esc_url( $logo_url ),
            'priceRange' => '££',
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress'   => $address ? esc_html( wp_strip_all_tags( $address ) ) : $city_name,
                'addressLocality' => $city_name,
                'addressRegion'   => $city_name,
                'addressCountry'  => 'UK'
            ]
        ];

        if ( $phone ) {
            $item_details['telephone'] = esc_html( $phone );
        }
        
        $list_items[] = [
            '@type' => 'ListItem',
            'position' => $position++,
            'item' => $item_details
        ];
    }

    // Get FAQs from ACF if they exist
    $faqs_data = [];
    $selector = 'term_' . $term->term_id;
    if ( function_exists( 'get_field' ) ) {
        $acf_faqs = get_field( 'faqs', $selector );
        if ( is_array( $acf_faqs ) ) {
            foreach ( $acf_faqs as $f ) {
                if ( ! empty( $f['question'] ) && ! empty( $f['answer'] ) ) {
                    $faqs_data[] = [
                        '@type' => 'Question',
                        'name'  => wp_strip_all_tags( $f['question'] ),
                        'acceptedAnswer' => [
                            '@type' => 'Answer',
                            'text'  => wp_strip_all_tags( $f['answer'] ),
                        ],
                    ];
                }
            }
        }
    }

    // Fallback default FAQs if none exist in ACF
    if ( empty( $faqs_data ) ) {
        $faqs_data = [
            [
                '@type' => 'Question',
                'name'  => sprintf( 'چگونه معتبرترین صرافی ایرانی در %s را پیدا کنم؟', $city_name ),
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => sprintf( 'پلتفرم Sarafee.uk با بررسی مجوزهای FCA و قوانین ضد پولشویی، لیستی از مطمئنترین صرافیهای %s را برای استعلام نرخ زنده و مقایسه در اختیار شما قرار میدهد.', $city_name )
                ]
            ],
            [
                '@type' => 'Question',
                'name'  => sprintf( 'کدام محلههای %s بیشترین صرافی ایرانی را دارند؟', $city_name ),
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => sprintf( 'بیشتر صرافیهای ایرانی معتبر در مناطق مختلف %s متمرکز هستند که لیست کامل آنها در این صفحه قابل مشاهده است.', $city_name )
                ]
            ]
        ];
    }

    // Build the final schema array
    $schema = [
        '@context' => 'https://schema.org',
        '@graph'   => [
            [
                '@type'      => 'CollectionPage',
                '@id'        => $term_link . '#webpage',
                'url'        => $term_link,
                'name'       => sprintf( 'لیست بهترین صرافیهای ایرانی و انگلیسی در %s', $city_name ),
                'description'=> $description,
                'inLanguage' => 'fa-IR',
                'isPartOf'   => [
                    '@id' => $site_host . '/#website'
                ]
            ],
            [
                '@type' => 'Article',
                '@id'   => $term_link . '#article',
                'isPartOf' => [
                    '@id' => $term_link . '#webpage'
                ],
                'headline' => sprintf( 'راهنمای جامع انتقال پول و انتخاب صرافی در %s', $city_name ),
                'author'   => [
                    '@type' => 'Organization',
                    'name'  => 'تیم تحلیل مالی Sarafee',
                    'url'   => $site_url
                ],
                'publisher' => [
                    '@type' => 'Organization',
                    'name'  => 'Sarafee.uk',
                    'logo'  => [
                        '@type' => 'ImageObject',
                        'url'   => $site_host . '/images/logo.png'
                    ]
                ],
                'mainEntityOfPage' => [
                    '@id' => $term_link . '#webpage'
                ]
            ]
        ]
    ];

    // Add ItemList if we have matching exchanges
    if ( ! empty( $list_items ) ) {
        $schema['@graph'][] = [
            '@type' => 'ItemList',
            '@id'   => $term_link . '#itemlist',
            'mainEntityOfPage' => [
                '@id' => $term_link . '#webpage'
            ],
            'name' => sprintf( 'صرافیهای برتر %s', $city_name ),
            'itemListElementType' => 'https://schema.org/FinancialService',
            'itemListElement' => $list_items
        ];
    }

    // Add FAQPage
    if ( ! empty( $faqs_data ) ) {
        $schema['@graph'][] = [
            '@type' => 'FAQPage',
            '@id'   => $term_link . '#faq',
            'mainEntityOfPage' => [
                '@id' => $term_link . '#webpage'
            ],
            'mainEntity' => $faqs_data
        ];
    }

    echo "\n" . '<script type="application/ld+json">' . "\n" . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) . "\n" . '</script>' . "\n";
}
