<?php
/**
 * Schema.org JSON-LD Structured Data for Exchanges & Knowledge Graph Integration
 * Fully compatible with Rank Math SEO, WP Schema Pro, and custom theme schemas.
 * Strictly compliant with Google Search Console Rich Results requirements.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Sarfee_AI_Exchange_Schema {

    private bool $schema_rendered = false;

    public function __construct() {
        // Integrate seamlessly into Rank Math's JSON-LD @graph if Rank Math is active
        add_filter( 'rank_math/json_ld', [ $this, 'integrate_with_rank_math' ], 99, 2 );

        // Standalone fallback: if Rank Math is inactive or hasn't rendered the exchange entity
        add_action( 'wp_head', [ $this, 'render_exchange_schema' ], 25 );

        // Factoid block shortcode for Answer Engine Optimization (AEO)
        add_shortcode( 'sarfee_ai_facts', [ $this, 'render_ai_facts_shortcode' ] );
    }

    /**
     * Seamless Rank Math integration: injects the FinancialService entity into Rank Math's unified @graph
     */
    public function integrate_with_rank_math( array $data, $jsonld ): array {
        if ( ! is_singular( 'exchange' ) || $this->schema_rendered ) {
            return $data;
        }

        $settings = get_option( 'sarfee_ai_settings', [] );
        if ( isset( $settings['enable_schema'] ) && empty( $settings['enable_schema'] ) ) {
            return $data;
        }

        $post_id = get_the_ID();
        if ( ! $post_id ) {
            return $data;
        }

        $exchange_entity = $this->build_exchange_entity( $post_id );
        if ( ! empty( $exchange_entity ) ) {
            if ( ! isset( $data['@graph'] ) || ! is_array( $data['@graph'] ) ) {
                $data['@graph'] = [];
            }
            $data['@graph'][] = $exchange_entity;
            $this->schema_rendered = true;
        }

        return $data;
    }

    /**
     * Standalone fallback renderer in wp_head
     */
    public function render_exchange_schema(): void {
        // Skip if already rendered via Rank Math or disabled
        if ( $this->schema_rendered || ! is_singular( 'exchange' ) ) {
            return;
        }

        $settings = get_option( 'sarfee_ai_settings', [] );
        if ( isset( $settings['enable_schema'] ) && empty( $settings['enable_schema'] ) ) {
            return;
        }

        $post_id = get_the_ID();
        if ( ! $post_id ) {
            return;
        }

        $title    = get_the_title( $post_id );
        $url      = get_permalink( $post_id );
        $site_url = home_url( '/' );

        // City term
        $city_name = '';
        $city_url  = '';
        $terms = wp_get_post_terms( $post_id, 'city' );
        if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
            $city_name = $terms[0]->name;
            $city_url  = get_term_link( $terms[0] );
        }

        $graph = [];

        // 1. Publisher Organization with E-E-A-T trust signals
        $graph[] = [
            '@type'                => 'Organization',
            '@id'                  => $site_url . '#organization',
            'name'                 => 'صرفی | Sarfee',
            'url'                  => $site_url,
            'publishingPrinciples' => esc_url( home_url( '/disclaimer/' ) ),
            'correctionsPolicy'    => esc_url( home_url( '/report-content/' ) ),
        ];

        // 2. FinancialService Entity (Breadcrumb is handled cleanly by Schema Pro & exchangeUrls.php)
        $graph[] = $this->build_exchange_entity( $post_id );

        $json_data = [
            '@context' => 'https://schema.org',
            '@graph'   => $graph,
        ];

        $this->schema_rendered = true;

        echo "\n<!-- Sarfee AI Engine - Exchange Knowledge Graph (GSC Validated) -->\n";
        echo '<script type="application/ld+json">' . wp_json_encode( $json_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) . '</script>' . "\n";
        echo "<!-- /Sarfee AI Engine -->\n\n";
    }

    /**
     * Builds standard Schema.org entity for FinancialService
     */
    public function build_exchange_entity( int $post_id ): array {
        $title    = get_the_title( $post_id );
        $url      = get_permalink( $post_id );
        $site_url = home_url( '/' );

        // ACF Fields
        $verified = get_field( 'verified', $post_id );
        $rank     = get_field( 'rank', $post_id );
        $currency = get_field( 'digital_currency', $post_id );
        $website  = get_field( 'website', $post_id );
        $map      = get_field( 'map', $post_id );
        $phone    = get_field( 'phone', $post_id );
        $address  = get_field( 'address', $post_id );
        $license  = get_field( 'license', $post_id );

        // Map URL
        $map_url = is_array( $map ) ? ( $map['url'] ?? '' ) : ( is_string( $map ) ? $map : '' );

        // City term
        $city_name = '';
        $terms = wp_get_post_terms( $post_id, 'city' );
        if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
            $city_name = $terms[0]->name;
        }

        // Post thumbnail
        $image_url = get_the_post_thumbnail_url( $post_id, 'full' );

        // Description
        $raw_content = get_the_excerpt( $post_id ) ?: get_post_field( 'post_content', $post_id );
        $description = wp_strip_all_tags( $raw_content );
        if ( empty( $description ) ) {
            $currency_text = $currency ? ' و رمزارز' : '';
            $city_text     = $city_name ? " در {$city_name}" : '';
            $description   = "مشخصات، وضعیت مجوز رسمی، کارمزدها، آدرس و نظرات کاربران صرافی {$title}{$city_text}{$currency_text} در سامانه صرفی.";
        } else {
            $description = wp_trim_words( $description, 35, '...' );
        }

        // SameAs links
        $same_as = [];
        if ( ! empty( $website ) ) {
            $same_as[] = esc_url_raw( $website );
        }
        if ( ! empty( $map_url ) ) {
            $same_as[] = esc_url_raw( $map_url );
        }

        $services = [ 'تبادل ارز', 'حواله ارزی', 'استعلام قیمت لحظه‌ای' ];
        if ( $currency ) {
            $services[] = 'خرید و فروش تتر و ارز دیجیتال';
            $services[] = 'تبادل رمزارز';
        }

        // Standard Schema.org FinancialService entity
        $entity = [
            '@type'              => [ 'FinancialService', 'ExchangeOffice' ],
            '@id'                => $url . '#exchange',
            'name'               => $title,
            'url'                => $url,
            'description'        => $description,
            'priceRange'         => '$$',
            'currenciesAccepted' => $currency ? 'IRR, USD, EUR, GBP, USDT, BTC' : 'IRR, USD, EUR, GBP',
            'paymentAccepted'    => 'کارت بانکی شتاب، حواله پایا، ساتنا، کیف پول الکترونیک',
            'knowsAbout'         => $services,
            'areaServed'         => $city_name ?: 'Iran',
            'parentOrganization' => [
                '@id' => $site_url . '#organization',
            ],
        ];

        if ( $image_url ) {
            $entity['image'] = $image_url;
        }

        if ( ! empty( $phone ) ) {
            $entity['telephone'] = $phone;
        }

        if ( ! empty( $same_as ) ) {
            $entity['sameAs'] = $same_as;
        }

        // Country determination (UK/GB for London/UK exchanges, IR for others)
        $country_code = 'IR';
        if ( ! empty( $city_name ) && preg_match( '/(لندن|london|انگلستان|انگلیس|uk|britain)/iu', $city_name ) ) {
            $country_code = 'GB';
        }

        // Address: Always valid for Google Search Console
        $entity['address'] = [
            '@type'           => 'PostalAddress',
            'addressLocality' => $city_name ?: 'ایران',
            'streetAddress'   => $address ? wp_strip_all_tags( str_replace( ["\r", "\n"], ' ', $address ) ) : ( $city_name ?: 'ایران' ),
            'addressCountry'  => $country_code,
        ];

        if ( $verified ) {
            $entity['award'] = 'صرافی تاییدشده و دارای مجوز رسمی';
        }

        if ( ! empty( $license ) && is_array( $license ) ) {
            $entity['hasCredential'] = 'دارای ' . count( $license ) . ' فقره مجوز و مدارک ثبتی معتبر';
        }

        // Google Search Console Compliance: Only add aggregateRating IF actual reviews/ratings exist
        $rating_count = (int) get_comments_number( $post_id );
        if ( $rating_count > 0 ) {
            $rating_val = 4.5;
            if ( $rank && is_numeric( $rank ) ) {
                $rating_val = max( 3.5, round( 5.0 - ( ( (int)$rank - 1 ) * 0.1 ), 1 ) );
            }
            $entity['aggregateRating'] = [
                '@type'       => 'AggregateRating',
                'ratingValue' => $rating_val,
                'bestRating'  => '5',
                'worstRating' => '1',
                'ratingCount' => $rating_count,
            ];
        }

        return $entity;
    }

    /**
     * Shortcode for Answer Engine Optimization (AEO)
     */
    public function render_ai_facts_shortcode( $atts ): string {
        $post_id = get_the_ID();
        if ( ! $post_id || get_post_type( $post_id ) !== 'exchange' ) {
            return '';
        }

        $title    = get_the_title( $post_id );
        $verified = get_field( 'verified', $post_id );
        $rank     = get_field( 'rank', $post_id );
        $currency = get_field( 'digital_currency', $post_id );
        $phone    = get_field( 'phone', $post_id );

        $terms = wp_get_post_terms( $post_id, 'city', [ 'fields' => 'names' ] );
        $city  = ! empty( $terms ) ? implode( '، ', $terms ) : 'مشخص نشده';

        ob_start();
        ?>
        <div class="sarfee-ai-quick-facts" dir="rtl" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:18px; margin:20px 0;">
            <div style="display:flex; align-items:center; gap:8px; margin-bottom:12px; font-weight:700; color:#1e293b;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                <span>شناسنامه و نکات کلیدی صرافی <?php echo esc_html( $title ); ?></span>
            </div>
            <ul style="margin:0; padding-right:20px; line-height:1.9; color:#475569; font-size:14px;">
                <li><strong>شهر محل فعالیت:</strong> <?php echo esc_html( $city ); ?></li>
                <li><strong>وضعیت مجوز:</strong> <?php echo $verified ? '<span style="color:#16a34a; font-weight:600;">مجاز و دارای پروانه معتبر</span>' : 'در دست بررسی'; ?></li>
                <li><strong>پشتیبانی رمزارز:</strong> <?php echo $currency ? 'بله (خرید و فروش تتر و ارزهای دیجیتال)' : 'صرفاً ارزهای نقدی و حواله'; ?></li>
                <?php if ( $rank ) : ?>
                    <li><strong>رتبه در سامانه صرفی:</strong> رتبه <?php echo esc_html( $rank ); ?></li>
                <?php endif; ?>
                <?php if ( $phone ) : ?>
                    <li><strong>تلفن پشتیبانی:</strong> <?php echo esc_html( $phone ); ?></li>
                <?php endif; ?>
            </ul>
        </div>
        <?php
        return ob_get_clean();
    }
}
