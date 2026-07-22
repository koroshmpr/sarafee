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

    // Get fa_name or clean title fallback
    $fa_name = '';
    if ( function_exists( 'get_field' ) ) {
        $fa_name = get_field( 'fa_name', $post->ID );
    }
    if ( empty( $fa_name ) ) {
        $raw_title = $post->post_title;
        $parts = preg_split( '/[|:|–|-]/', $raw_title );
        $fa_name = trim( $parts[0] );
        if ( mb_strpos( $fa_name, 'قیمت ' ) === 0 ) {
            $fa_name = trim( mb_substr( $fa_name, 5 ) );
        }
        if ( mb_substr( $fa_name, -6 ) === ' امروز' ) {
            $fa_name = trim( mb_substr( $fa_name, 0, -6 ) );
        }
    }

    // Dynamic description builder
    $description = sprintf( 'مشاهده قیمت لحظه ای %s، چارت تغییرات، تحلیل بازار و استفاده از ماشین حساب تبدیل %s به تومان. مقایسه نرخ بهترین صرافیهای بریتانیا در Sarafee.uk.', $fa_name, $fa_name );

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
                'name'       => $post_title,
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
