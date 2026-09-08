<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Exchange Google Reviews Shortcode Component
 * Shortcode: [exchange_google_reviews]
 * Automatically extracts Google Place ID / CID and Business details directly from the ACF map URL.
 * If map URL is empty or no reviews are found, nothing is rendered (returns empty string).
 */

if ( ! function_exists( 'sarfee_parse_google_map_url' ) ) {
    function sarfee_parse_google_map_url( $url ) {
        if ( empty( $url ) || ! is_string( $url ) ) {
            return [];
        }

        $info = [
            'name'        => '',
            'ftid'        => '',
            'cid_hex'     => '',
            'cid_dec'     => '',
            'place_id'    => '',
            'lat'         => '',
            'lng'         => '',
            'raw_url'     => $url,
        ];

        // 1. Extract place name from /place/NAME/...
        if ( preg_match( '#/maps/place/([^/@?]+)#', $url, $m ) ) {
            $info['name'] = urldecode( str_replace( '+', ' ', $m[1] ) );
        }

        // 2. Extract ftid and Hex CID: !1s(0x...:0x...)
        if ( preg_match( '#!1s(0x[0-9a-fA-F]+):(0x[0-9a-fA-F]+)#', $url, $m ) ) {
            $info['ftid']    = $m[1] . ':' . $m[2];
            $info['cid_hex'] = $m[2];

            // Convert Hex to unsigned 64-bit Decimal string (CID)
            if ( function_exists( 'gmp_strval' ) ) {
                $info['cid_dec'] = gmp_strval( gmp_init( $m[2], 16 ), 10 );
            } elseif ( function_exists( 'bcmul' ) ) {
                // BCMath fallback for 64-bit hex
                $hex = ltrim( strtolower( $m[2] ), '0x' );
                $dec = '0';
                $len = strlen( $hex );
                for ( $i = 0; $i < $len; $i++ ) {
                    $dec = bcadd( bcmul( $dec, '16' ), (string) hexdec( $hex[$i] ) );
                }
                $info['cid_dec'] = $dec;
            } else {
                $info['cid_dec'] = sprintf( '%u', hexdec( $m[2] ) );
            }
        }

        // 3. Extract direct place_id if present
        if ( preg_match( '#place_id[:=]([a-zA-Z0-9_\-]+)#', $url, $m ) ) {
            $info['place_id'] = $m[1];
        }

        // 4. Extract Lat / Lng from !3d... !4d... or /@lat,lng
        if ( preg_match( '#!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)#', $url, $m ) ) {
            $info['lat'] = $m[1];
            $info['lng'] = $m[2];
        } elseif ( preg_match( '#@(-?\d+\.\d+),(-?\d+\.\d+)#', $url, $m ) ) {
            $info['lat'] = $m[1];
            $info['lng'] = $m[2];
        }

        return $info;
    }
}

if ( ! function_exists( 'sarfee_get_exchange_google_reviews' ) ) {
    function sarfee_get_exchange_google_reviews( $post_id, $map_url ) {
        if ( empty( $map_url ) ) {
            return false;
        }

        $transient_key = 'sarfee_gr_' . absint( $post_id );
        $cached_data   = get_transient( $transient_key );

        if ( false !== $cached_data ) {
            return $cached_data; // Can be an array with reviews or false if previously checked and empty
        }

        $map_info = sarfee_parse_google_map_url( $map_url );
        $api_key  = defined( 'GOOGLE_PLACES_API_KEY' ) ? GOOGLE_PLACES_API_KEY : get_option( 'sarfee_google_places_api_key', '' );

        // 1. If API Key is configured, fetch live reviews directly from Google Places API
        if ( $api_key ) {
            $query_text = ! empty( $map_info['name'] ) ? $map_info['name'] : get_the_title( $post_id );

            // 1.1 Try Google Places API (New) - Modern v1 endpoint
            $v1_url = 'https://places.googleapis.com/v1/places:searchText';
            $v1_payload = [
                'textQuery'    => $query_text,
                'languageCode' => 'fa',
            ];
            if ( ! empty( $map_info['lat'] ) && ! empty( $map_info['lng'] ) ) {
                $v1_payload['locationBias'] = [
                    'circle' => [
                        'center' => [
                            'latitude'  => (float) $map_info['lat'],
                            'longitude' => (float) $map_info['lng'],
                        ],
                        'radius' => 500.0,
                    ],
                ];
            }

            $v1_res = wp_remote_post( $v1_url, [
                'headers' => [
                    'Content-Type'     => 'application/json',
                    'X-Goog-Api-Key'   => $api_key,
                    'X-Goog-FieldMask' => 'places.id,places.displayName,places.rating,places.userRatingCount,places.reviews',
                ],
                'body'    => wp_json_encode( $v1_payload ),
                'timeout' => 12,
            ] );

            if ( ! is_wp_error( $v1_res ) && 200 === wp_remote_retrieve_response_code( $v1_res ) ) {
                $v1_body = json_decode( wp_remote_retrieve_body( $v1_res ), true );
                if ( ! empty( $v1_body['places'][0] ) ) {
                    $place = $v1_body['places'][0];
                    $reviews_list = [];

                    // If reviews were returned directly
                    if ( ! empty( $place['reviews'] ) && is_array( $place['reviews'] ) ) {
                        foreach ( $place['reviews'] as $rev ) {
                            $reviews_list[] = [
                                'author_name'               => $rev['authorAttribution']['displayName'] ?? 'کاربر گوگل',
                                'profile_photo_url'         => $rev['authorAttribution']['photoUri'] ?? '',
                                'rating'                    => (float) ( $rev['rating'] ?? 5 ),
                                'relative_time_description' => $rev['relativePublishTimeDescription'] ?? 'مدتی پیش',
                                'text'                      => $rev['text']['text'] ?? ( $rev['originalText']['text'] ?? '' ),
                            ];
                        }
                    } elseif ( ! empty( $place['id'] ) ) {
                        // Query Place Details v1 for reviews
                        $det_v1_url = "https://places.googleapis.com/v1/places/{$place['id']}?languageCode=fa";
                        $det_v1_res = wp_remote_get( $det_v1_url, [
                            'headers' => [
                                'X-Goog-Api-Key'   => $api_key,
                                'X-Goog-FieldMask' => 'id,displayName,rating,userRatingCount,reviews',
                            ],
                            'timeout' => 10,
                        ] );

                        if ( ! is_wp_error( $det_v1_res ) && 200 === wp_remote_retrieve_response_code( $det_v1_res ) ) {
                            $det_v1_body = json_decode( wp_remote_retrieve_body( $det_v1_res ), true );
                            if ( ! empty( $det_v1_body['reviews'] ) && is_array( $det_v1_body['reviews'] ) ) {
                                foreach ( $det_v1_body['reviews'] as $rev ) {
                                    $reviews_list[] = [
                                        'author_name'               => $rev['authorAttribution']['displayName'] ?? 'کاربر گوگل',
                                        'profile_photo_url'         => $rev['authorAttribution']['photoUri'] ?? '',
                                        'rating'                    => (float) ( $rev['rating'] ?? 5 ),
                                        'relative_time_description' => $rev['relativePublishTimeDescription'] ?? 'مدتی پیش',
                                        'text'                      => $rev['text']['text'] ?? ( $rev['originalText']['text'] ?? '' ),
                                    ];
                                }
                            }
                        }
                    }

                    if ( ! empty( $reviews_list ) || ! empty( $place['rating'] ) ) {
                        $result_data = [
                            'rating'             => $place['rating'] ?? 5.0,
                            'user_ratings_total' => $place['userRatingCount'] ?? count( $reviews_list ),
                            'reviews'            => $reviews_list,
                        ];
                        set_transient( $transient_key, $result_data, DAY_IN_SECONDS * 3 );
                        return $result_data;
                    }
                }
            }

            // 1.2 Fallback: Google Places API (Legacy) - Details by CID or Place ID
            if ( ! empty( $map_info['cid_dec'] ) ) {
                $cid_endpoint = "https://maps.googleapis.com/maps/api/place/details/json?cid={$map_info['cid_dec']}&fields=place_id,name,rating,user_ratings_total,reviews&language=fa&key={$api_key}";
                $res = wp_remote_get( $cid_endpoint, [ 'timeout' => 10 ] );
                if ( ! is_wp_error( $res ) && 200 === wp_remote_retrieve_response_code( $res ) ) {
                    $body = json_decode( wp_remote_retrieve_body( $res ), true );
                    if ( ! empty( $body['result']['reviews'] ) ) {
                        $result_data = [
                            'rating'             => $body['result']['rating'] ?? 5.0,
                            'user_ratings_total' => $body['result']['user_ratings_total'] ?? count( $body['result']['reviews'] ),
                            'reviews'            => $body['result']['reviews'],
                        ];
                        set_transient( $transient_key, $result_data, DAY_IN_SECONDS * 3 );
                        return $result_data;
                    }
                }
            }
        }

        // 2. Check if custom ACF manual reviews exist
        $manual_reviews = get_field( 'google_reviews', $post_id );
        if ( ! empty( $manual_reviews ) && is_array( $manual_reviews ) ) {
            $formatted_reviews = [];
            foreach ( $manual_reviews as $r ) {
                $formatted_reviews[] = [
                    'author_name'               => $r['author_name'] ?? $r['name'] ?? 'کاربر گوگل',
                    'profile_photo_url'         => $r['profile_photo_url'] ?? $r['avatar'] ?? '',
                    'rating'                    => (float) ( $r['rating'] ?? 5 ),
                    'relative_time_description' => $r['relative_time'] ?? $r['time'] ?? 'مدتی پیش',
                    'text'                      => $r['text'] ?? $r['comment'] ?? '',
                ];
            }
            $result_data = [
                'rating'             => (float) ( get_field( 'rating', $post_id ) ?: 4.8 ),
                'user_ratings_total' => (int) ( get_field( 'review_count', $post_id ) ?: count( $formatted_reviews ) ),
                'reviews'            => $formatted_reviews,
            ];
            set_transient( $transient_key, $result_data, DAY_IN_SECONDS );
            return $result_data;
        }

        // If no reviews found, cache false for 1 hour to prevent constant re-querying
        set_transient( $transient_key, false, HOUR_IN_SECONDS );
        return false;
    }
}

function exchange_google_reviews_shortcode( $atts ) {
    $atts = shortcode_atts( [
        'id'    => 0,
        'theme' => 'dark', // 'dark' or 'light'
        'limit' => 6,
    ], $atts, 'exchange_google_reviews' );

    $post_id = ! empty( $atts['id'] ) ? absint( $atts['id'] ) : get_the_ID();
    if ( ! $post_id ) return '';

    // 1. Strictly retrieve the ACF map field link
    $map_field = get_field( 'map', $post_id );
    $map_url   = is_array( $map_field ) ? ( $map_field['url'] ?? '' ) : ( is_string( $map_field ) ? $map_field : '' );

    $empty_fallback = '<style>.reviews-section, .exchange-reviews-section, .google-reviews-section, .egr-section, .egr { display: none !important; }</style>' .
        '<script>(function(){var s=document.currentScript;if(s){var sec=s.closest(".reviews-section, .exchange-reviews-section, .google-reviews-section, .egr-section, .elementor-section, .e-con, .e-container, .elementor-widget");if(sec && !sec.textContent.trim()) sec.style.display="none";}})();</script>';

    // If map link is empty, output fallback
    if ( empty( $map_url ) ) {
        return $empty_fallback;
    }

    // 2. Fetch data
    $data = sarfee_get_exchange_google_reviews( $post_id, $map_url );
    if ( empty( $data ) || empty( $data['reviews'] ) ) {
        return $empty_fallback;
    }

    $reviews          = $data['reviews'];
    $theme_class      = ( 'light' === $atts['theme'] ) ? 'egr--light' : 'egr--dark';
    $rating_formatted = number_format( (float) ( $data['rating'] ?? 5.0 ), 1 );
    $total_reviews    = (int) ( $data['user_ratings_total'] ?? count( $reviews ) );

    ob_start();
    ?>
    <section class="egr <?php echo esc_attr( $theme_class ); ?>" aria-label="نظرات کاربران گوگل">
        <!-- Header Row -->
        <div class="egr__header">
            <div class="egr__header-right">
                <span class="egr__google-icon" aria-hidden="true">
                    <svg width="22" height="22" viewBox="0 0 24 24">
                        <path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.82-2.4 3.68v3.05h3.88c2.27-2.09 3.665-5.17 3.665-9.17z"/>
                        <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.1-6.72-4.93H1.25v3.15C3.26 21.36 7.33 24 12 24z"/>
                        <path fill="#FBBC05" d="M5.28 14.27c-.25-.72-.38-1.49-.38-2.27s.13-1.55.38-2.27V6.58H1.25C.45 8.18 0 10.03 0 12s.45 3.82 1.25 5.42l4.03-3.15z"/>
                        <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.33 0 3.26 2.64 1.25 6.58l4.03 3.15c.95-2.83 3.6-4.98 6.72-4.98z"/>
                    </svg>
                </span>
                <h3 class="egr__title">نظرات گوگل</h3>
            </div>
            <div class="egr__header-left">
                <span class="egr__score"><?php echo esc_html( $rating_formatted ); ?></span>
                <span class="egr__star-gold">★</span>
                <span class="egr__sep">•</span>
                <span class="egr__count"><?php echo esc_html( $total_reviews ); ?> نظر گوگل</span>
            </div>
        </div>

        <!-- Reviews Cards Scroll / Grid -->
        <?php if ( ! empty( $reviews ) ) : ?>
            <div class="egr__cards-scroll">
                <div class="egr__cards">
                    <?php foreach ( array_slice( $reviews, 0, (int) $atts['limit'] ) as $review ) : 
                        $author_name = $review['author_name'] ?? 'کاربر';
                        $avatar_url  = $review['profile_photo_url'] ?? '';
                        $first_char  = mb_substr( $author_name, 0, 1, 'UTF-8' );
                        $stars_count = round( (float) ( $review['rating'] ?? 5 ) );
                        $time_ago    = $review['relative_time_description'] ?? '';
                        $text        = $review['text'] ?? '';
                    ?>
                        <article class="egr__card">
                            <header class="egr__card-header">
                                <div class="egr__user-info">
                                    <span class="egr__user-name"><?php echo esc_html( $author_name ); ?></span>
                                    <div class="egr__avatar">
                                        <?php if ( $avatar_url ) : ?>
                                            <img src="<?php echo esc_url( $avatar_url ); ?>" alt="<?php echo esc_attr( $author_name ); ?>" loading="lazy">
                                        <?php else : ?>
                                            <span class="egr__avatar-char"><?php echo esc_html( $first_char ); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if ( $time_ago ) : ?>
                                    <span class="egr__time"><?php echo esc_html( $time_ago ); ?></span>
                                <?php endif; ?>
                            </header>

                            <!-- Rating Stars -->
                            <div class="egr__stars" aria-label="<?php echo esc_attr( $stars_count . ' از 5 ستاره' ); ?>">
                                <?php for ( $s = 1; $s <= 5; $s++ ) : ?>
                                    <span class="egr__star <?php echo $s <= $stars_count ? 'egr__star--active' : ''; ?>">★</span>
                                <?php endfor; ?>
                            </div>

                            <!-- Review Text -->
                            <?php if ( $text ) : ?>
                                <p class="egr__text"><?php echo esc_html( $text ); ?></p>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </section>
    <?php
    return ob_get_clean();
}
add_shortcode( 'exchange_google_reviews', 'exchange_google_reviews_shortcode' );
