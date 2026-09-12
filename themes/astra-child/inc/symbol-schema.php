<?php
/**
 * Dynamic JSON-LD Schema for Single Symbol Pages
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_head', 'sarfee_symbol_single_schema' );

function sarfee_symbol_single_schema() {
    if ( ! is_singular( 'symbol' ) ) {
        return;
    }

    $post = get_queried_object();
    if ( ! $post || is_wp_error( $post ) ) {
        return;
    }

    $slug = $post->post_name;
    $post_title = esc_html( $post->post_title );
    $post_link = esc_url( get_permalink( $post ) );
    $site_url  = esc_url( home_url( '/' ) );
    $site_host = rtrim( $site_url, '/' );

    // Get fa_name with robust layered fallbacks (ACF -> post_meta -> clean title -> raw post_title -> slug)
    $fa_name = '';
    if ( function_exists( 'get_field' ) ) {
        $fa_name = trim( (string) get_field( 'fa_name', $post->ID ) );
    }
    if ( empty( $fa_name ) ) {
        $fa_name = trim( (string) get_post_meta( $post->ID, 'fa_name', true ) );
    }
    if ( empty( $fa_name ) ) {
        $raw_title = (string) $post->post_title;
        $parts     = preg_split( '/[\-|–—|:]/u', $raw_title );
        $clean     = trim( $parts[0] ?? $raw_title );
        if ( mb_strpos( $clean, 'قیمت ' ) === 0 ) {
            $clean = trim( mb_substr( $clean, 5 ) );
        }
        if ( mb_substr( $clean, -6 ) === ' امروز' ) {
            $clean = trim( mb_substr( $clean, 0, -6 ) );
        }
        $fa_name = ! empty( $clean ) ? $clean : ( ! empty( $raw_title ) ? $raw_title : strtoupper( $slug ) );
    }

    if ( empty( $fa_name ) ) {
        $fa_name = strtoupper( $slug ?: 'سیمبل ' . $post->ID );
    }

    // Build clean schema name (using fa_name and ticker code if applicable)
    $ticker     = ( strlen( $slug ) <= 5 && ctype_alpha( $slug ) ) ? strtoupper( $slug ) : '';
    $has_prefix = preg_match( '/(قیمت|تحلیل|نرخ|حباب|حواله)/u', $fa_name );
    $prefix     = $has_prefix ? '' : 'قیمت و تحلیل ';
    $schema_name = ( $ticker && stripos( $fa_name, $ticker ) === false ) ? "{$prefix}{$fa_name} ({$ticker})" : "{$prefix}{$fa_name}";

    // Description: Check Rank Math / SEO custom meta first, fallback to dynamic
    $custom_desc = get_post_meta( $post->ID, 'rank_math_description', true ) ?: $post->post_excerpt;
    if ( empty( $custom_desc ) ) {
        $custom_desc = get_post_meta( $post->ID, '_yoast_wpseo_metadesc', true );
    }
    if ( empty( $custom_desc ) && function_exists( 'get_field' ) ) {
        $custom_desc = get_field( 'description', $post->ID ) ?: get_field( 'meta_description', $post->ID );
    }
    if ( empty( $custom_desc ) ) {
        $custom_desc = get_post_meta( $post->ID, 'description', true ) ?: get_post_meta( $post->ID, 'meta_description', true );
    }
    $description = ! empty( trim( (string) $custom_desc ) ) ? trim( (string) $custom_desc ) : sprintf( 'مشاهده قیمت لحظه ای %s، چارت تغییرات، تحلیل بازار و استفاده از ماشین حساب تبدیل %s به تومان. مقایسه نرخ بهترین صرافیهای بریتانیا در Sarafee.uk.', $fa_name, $fa_name );

    // Get FAQs from ACF if they exist
    $faqs_data = [];
    if ( function_exists( 'get_field' ) ) {
        $acf_faqs = get_field( 'faqs', $post->ID );
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
                'name'  => sprintf( 'قیمت حواله %s چگونه محاسبه میشود؟', $fa_name ),
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => sprintf( 'قیمت حواله بر اساس نرخ روز %s بازار آزاد بعلاوه کارمزد صرافی (اسپرد) تعیین میشود. در پلتفرم Sarafee.uk میتوانید این نرخها را زنده مقایسه کنید.', $fa_name )
                ]
            ],
            [
                '@type' => 'Question',
                'name'  => sprintf( 'آیا ماشین حساب تبدیل ارز %s در سرافی دقیق است؟', $fa_name ),
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => sprintf( 'بله، مبدل ارز ما بر اساس آخرین میانگین قیمت اعلام شده توسط صرافیهای مجاز بریتانیا در همان لحظه بهروزرسانی میشود.', $fa_name )
                ]
            ]
        ];
    }

    // Build the final schema array
    $schema = [
        '@context' => 'https://schema.org',
        '@graph'   => [
            [
                '@type'      => 'WebPage',
                '@id'        => $post_link . '#webpage',
                'url'        => $post_link,
                'name'       => $schema_name,
                'description'=> $description,
                'inLanguage' => 'fa-IR',
                'isPartOf'   => [
                    '@id' => $site_host . '/#website'
                ]
            ],
            [
                '@type' => 'SoftwareApplication',
                '@id'   => $post_link . '#converter',
                'name'  => sprintf( 'ماشین حساب تبدیل %s به تومان', $fa_name ),
                'applicationCategory' => 'FinanceApplication',
                'operatingSystem' => 'All',
                'offers' => [
                    '@type' => 'Offer',
                    'price' => '0',
                    'priceCurrency' => strtoupper( $slug )
                ],
                'description' => sprintf( 'ابزار هوشمند و زنده برای تبدیل سریع %s به تومان ایران بر اساس آخرین نرخ بازار.', $fa_name ),
                'url' => $post_link
            ],
            [
                '@type' => 'Article',
                '@id'   => $post_link . '#article',
                'isPartOf' => [
                    '@id' => $post_link . '#webpage'
                ],
                'headline' => sprintf( 'تحلیل تکنیکال و پیشبینی قیمت %s', $fa_name ),
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
                ]
            ]
        ]
    ];

    // Add FAQPage
    if ( ! empty( $faqs_data ) ) {
        $schema['@graph'][] = [
            '@type' => 'FAQPage',
            '@id'   => $post_link . '#faq',
            'mainEntityOfPage' => [
                '@id' => $post_link . '#webpage'
            ],
            'mainEntity' => $faqs_data
        ];
    }

    echo "\n" . '<script type="application/ld+json">' . "\n" . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) . "\n" . '</script>' . "\n";
}
