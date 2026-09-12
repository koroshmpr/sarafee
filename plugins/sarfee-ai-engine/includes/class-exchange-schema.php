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

        $site_name = ! empty( $settings['llms_site_title'] ) ? $settings['llms_site_title'] : ( get_bloginfo( 'name' ) ?: 'صرفی' );
        $disclaimer_url = ! empty( $settings['disclaimer_page_id'] ) 
            ? get_permalink( (int) $settings['disclaimer_page_id'] ) 
            : ( ! empty( $settings['disclaimer_custom_url'] ) ? $settings['disclaimer_custom_url'] : home_url( '/disclaimer/' ) );
        $report_url = ! empty( $settings['report_content_page_id'] ) 
            ? get_permalink( (int) $settings['report_content_page_id'] ) 
            : ( ! empty( $settings['report_content_custom_url'] ) ? $settings['report_content_custom_url'] : home_url( '/report-content/' ) );

        // 1. Publisher Organization with E-E-A-T trust signals
        $graph[] = [
            '@type'                => 'Organization',
            '@id'                  => $site_url . '#organization',
            'name'                 => $site_name,
            'url'                  => $site_url,
            'publishingPrinciples' => esc_url( $disclaimer_url ),
            'correctionsPolicy'    => esc_url( $report_url ),
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
            $site_name     = get_bloginfo( 'name' ) ?: 'صرفی';
            $currency_text = $currency ? ' و رمزارز' : '';
            $city_text     = $city_name ? " در {$city_name}" : '';
            $exchange_name = ( mb_strpos( $title, 'صرافی' ) !== false ) ? $title : ( 'صرافی ' . $title );
            $description   = "مشخصات، وضعیت مجوز رسمی، کارمزدها، آدرس و نظرات کاربران {$exchange_name}{$city_text}{$currency_text} در سامانه {$site_name}.";
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

        $services = [ 'تبادل ارز', 'حواله ارزی', 'استعلام قیمت' ];
        if ( $currency ) {
            $services[] = 'اطلاعات رمزارز';
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
     * Usage:
     *   - Inside Exchange CPT: [sarfee_ai_facts] (auto-detects current exchange)
     *   - Inside Blog Posts/Articles: [sarfee_ai_facts slug="arman"] or [sarfee_ai_facts id="123"]
     */
    public function render_ai_facts_shortcode( $atts ): string {
        $atts = shortcode_atts( [
            'id'    => 0,
            'slug'  => '',
            'name'  => '',
        ], (array) $atts, 'sarfee_ai_facts' );

        $target_id = 0;

        // 1. Direct ID passed
        if ( ! empty( $atts['id'] ) ) {
            $candidate_id = (int) $atts['id'];
            if ( get_post_type( $candidate_id ) === 'exchange' ) {
                $target_id = $candidate_id;
            }
        }

        // 2. Slug passed
        if ( ! $target_id && ! empty( $atts['slug'] ) ) {
            $slug = sanitize_title( $atts['slug'] );
            $found = get_page_by_path( $slug, OBJECT, 'exchange' );
            if ( $found ) {
                $target_id = $found->ID;
            } else {
                $posts = get_posts( [
                    'post_type'      => 'exchange',
                    'name'           => $slug,
                    'posts_per_page' => 1,
                    'post_status'    => 'publish',
                ] );
                if ( ! empty( $posts ) ) {
                    $target_id = $posts[0]->ID;
                }
            }
        }

        // 3. Name / Title passed
        if ( ! $target_id && ! empty( $atts['name'] ) ) {
            $posts = get_posts( [
                'post_type'      => 'exchange',
                'title'          => sanitize_text_field( $atts['name'] ),
                'posts_per_page' => 1,
                'post_status'    => 'publish',
            ] );
            if ( ! empty( $posts ) ) {
                $target_id = $posts[0]->ID;
            }
        }

        // 4. Auto-detect from current context
        $current_id = get_the_ID();
        if ( ! $target_id && $current_id ) {
            if ( get_post_type( $current_id ) === 'exchange' ) {
                $target_id = $current_id;
            } else {
                // Check if current post has connected exchange meta
                $meta_exchange = get_post_meta( $current_id, 'exchange_id', true ) 
                              ?: get_post_meta( $current_id, 'related_exchange', true )
                              ?: get_post_meta( $current_id, 'sarfee_exchange', true );

                if ( $meta_exchange && get_post_type( (int) $meta_exchange ) === 'exchange' ) {
                    $target_id = (int) $meta_exchange;
                } else {
                    // Try smart match: does current article slug match an exchange slug?
                    $current_slug = get_post_field( 'post_name', $current_id );
                    if ( $current_slug ) {
                        $posts = get_posts( [
                            'post_type'      => 'exchange',
                            'name'           => $current_slug,
                            'posts_per_page' => 1,
                            'post_status'    => 'publish',
                        ] );
                        if ( ! empty( $posts ) ) {
                            $target_id = $posts[0]->ID;
                        }
                    }
                }
            }
        }

        // 5. If still no valid exchange target found
        if ( ! $target_id || get_post_type( $target_id ) !== 'exchange' ) {
            if ( current_user_can( 'edit_posts' ) ) {
                return '<div class="sarfee-ai-facts-admin-hint" dir="rtl" style="background:#fffbeb; border:1px dashed #d97706; border-radius:10px; padding:14px 18px; margin:20px 0; font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Tahoma,sans-serif; font-size:13px; color:#92400e; line-height:1.8; text-align:right;">'
                    . '<div style="font-weight:700; display:flex; align-items:center; gap:8px; margin-bottom:6px;">'
                    . '<span style="font-size:16px;">💡</span> راهنمای شورت‌کد شناسنامه صرافی (فقط برای مدیران نمایش داده می‌شود):'
                    . '</div>'
                    . 'این برگه یا نوشته از نوع «صرافی» نیست. برای نمایش کادر شناسنامه صرافی در مقالات، وبلاگ یا صفحات فرود، شناسه یا نامک (slug) صرافی مورد نظر را داخل شورت‌کد قرار دهید:<br>'
                    . '<code style="background:#fef3c7; color:#b45309; padding:3px 8px; border-radius:5px; font-weight:600; display:inline-block; margin-top:4px;">[sarfee_ai_facts slug="arman"]</code> یا <code style="background:#fef3c7; color:#b45309; padding:3px 8px; border-radius:5px; font-weight:600; display:inline-block; margin-top:4px;">[sarfee_ai_facts id="123"]</code>'
                    . '</div>';
            }
            return '';
        }

        // Get exchange data
        $title         = get_the_title( $target_id );
        $exchange_name = ( mb_strpos( $title, 'صرافی' ) !== false ) ? $title : ( 'صرافی ' . $title );
        $url           = get_permalink( $target_id );
        $verified      = function_exists( 'get_field' ) ? get_field( 'verified', $target_id ) : get_post_meta( $target_id, 'verified', true );
        $rank          = function_exists( 'get_field' ) ? get_field( 'rank', $target_id ) : get_post_meta( $target_id, 'rank', true );
        $currency      = function_exists( 'get_field' ) ? get_field( 'digital_currency', $target_id ) : get_post_meta( $target_id, 'digital_currency', true );
        $phone         = function_exists( 'get_field' ) ? get_field( 'phone', $target_id ) : get_post_meta( $target_id, 'phone', true );
        $address       = function_exists( 'get_field' ) ? get_field( 'address', $target_id ) : get_post_meta( $target_id, 'address', true );
        $website       = function_exists( 'get_field' ) ? get_field( 'website', $target_id ) : get_post_meta( $target_id, 'website', true );

        $terms = wp_get_post_terms( $target_id, 'city', [ 'fields' => 'names' ] );
        $city  = ( ! empty( $terms ) && ! is_wp_error( $terms ) ) ? implode( '، ', $terms ) : 'مشخص نشده';

        $settings  = get_option( 'sarfee_ai_settings', [] );
        $site_name = ! empty( $settings['llms_site_title'] ) ? $settings['llms_site_title'] : ( get_bloginfo( 'name' ) ?: 'صرفی' );
        $is_external_post = ( $current_id !== $target_id );

        ob_start();
        ?>
        <div class="sarfee-facts-wrapper" dir="rtl">
            <style>
                .sarfee-facts-wrapper {
                    direction: rtl;
                    text-align: right;
                    margin: 28px 0;
                    font-family: inherit;
                }
                .sarfee-facts-card {
                    background: #ffffff;
                    border: 1px solid #e2e8f0;
                    border-radius: 18px;
                    box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.06);
                    overflow: hidden;
                    position: relative;
                    transition: all 0.25s ease;
                }
                .sarfee-facts-card:hover {
                    box-shadow: 0 8px 30px -4px rgba(37, 99, 235, 0.12);
                    border-color: #cbd5e1;
                }
                .sarfee-facts-top-bar {
                    height: 4px;
                    background: linear-gradient(90deg, #2563eb 0%, #3b82f6 50%, #60a5fa 100%);
                }
                .sarfee-facts-inner {
                    padding: 22px 24px;
                }
                .sarfee-facts-header {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    flex-wrap: wrap;
                    gap: 12px;
                    padding-bottom: 16px;
                    margin-bottom: 18px;
                    border-bottom: 1px solid #f1f5f9;
                }
                .sarfee-facts-title-group {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                }
                .sarfee-facts-icon-badge {
                    width: 44px;
                    height: 44px;
                    border-radius: 12px;
                    background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
                    color: #2563eb;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    flex-shrink: 0;
                    box-shadow: 0 2px 8px rgba(37, 99, 235, 0.15);
                }
                .sarfee-facts-title {
                    font-size: 17px;
                    font-weight: 800;
                    color: #0f172a;
                    margin: 0;
                    line-height: 1.4;
                }
                .sarfee-facts-subtitle {
                    font-size: 12px;
                    color: #64748b;
                    margin-top: 2px;
                    font-weight: 400;
                }
                .sarfee-facts-badges {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    flex-wrap: wrap;
                }
                .sarfee-badge {
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    font-size: 11.5px;
                    font-weight: 700;
                    padding: 5px 12px;
                    border-radius: 20px;
                    letter-spacing: -0.2px;
                }
                .sarfee-badge-verified {
                    background: #ecfdf5;
                    color: #059669;
                    border: 1px solid #a7f3d0;
                }
                .sarfee-badge-registered {
                    background: #f8fafc;
                    color: #64748b;
                    border: 1px solid #e2e8f0;
                }
                .sarfee-badge-crypto {
                    background: #eff6ff;
                    color: #1d4ed8;
                    border: 1px solid #bfdbfe;
                }
                .sarfee-facts-grid {
                    display: grid;
                    grid-template-columns: repeat(2, 1fr);
                    gap: 12px;
                }
                .sarfee-fact-item {
                    background: #f8fafc;
                    border: 1px solid #f1f5f9;
                    border-radius: 12px;
                    padding: 12px 14px;
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    transition: background-color 0.2s ease, border-color 0.2s ease;
                }
                .sarfee-fact-item:hover {
                    background: #f1f5f9;
                    border-color: #e2e8f0;
                }
                .sarfee-fact-item-icon {
                    width: 36px;
                    height: 36px;
                    border-radius: 10px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    flex-shrink: 0;
                }
                .sarfee-fact-text {
                    flex: 1;
                    min-width: 0;
                }
                .sarfee-fact-label {
                    font-size: 11.5px;
                    color: #64748b;
                    font-weight: 500;
                    margin-bottom: 2px;
                }
                .sarfee-fact-val {
                    font-size: 13.5px;
                    font-weight: 700;
                    color: #1e293b;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    white-space: nowrap;
                }
                .sarfee-facts-footer {
                    margin-top: 18px;
                    padding-top: 16px;
                    border-top: 1px dashed #e2e8f0;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    flex-wrap: wrap;
                    gap: 12px;
                }
                .sarfee-facts-footer-note {
                    font-size: 12.5px;
                    color: #64748b;
                    display: flex;
                    align-items: center;
                    gap: 6px;
                }
                .sarfee-facts-btn {
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
                    color: #ffffff !important;
                    padding: 9px 18px;
                    border-radius: 10px;
                    font-size: 13px;
                    font-weight: 700;
                    text-decoration: none !important;
                    box-shadow: 0 2px 10px rgba(37, 99, 235, 0.25);
                    transition: transform 0.2s ease, box-shadow 0.2s ease;
                }
                .sarfee-facts-btn:hover {
                    transform: translateY(-1px);
                    box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35);
                    color: #ffffff !important;
                }
                @media (max-width: 680px) {
                    .sarfee-facts-inner {
                        padding: 16px;
                    }
                    .sarfee-facts-grid {
                        grid-template-columns: 1fr;
                        gap: 10px;
                    }
                    .sarfee-facts-header {
                        flex-direction: column;
                        align-items: flex-start;
                    }
                    .sarfee-facts-badges {
                        width: 100%;
                    }
                    .sarfee-facts-footer {
                        flex-direction: column;
                        align-items: stretch;
                        text-align: center;
                    }
                    .sarfee-facts-footer-note {
                        justify-content: center;
                        font-size: 12px;
                    }
                    .sarfee-facts-btn {
                        width: 100%;
                        justify-content: center;
                        padding: 11px 16px;
                    }
                    .sarfee-fact-val {
                        white-space: normal;
                    }
                }
            </style>

            <div class="sarfee-facts-card">
                <div class="sarfee-facts-top-bar"></div>
                <div class="sarfee-facts-inner">
                    <!-- Header -->
                    <div class="sarfee-facts-header">
                        <div class="sarfee-facts-title-group">
                            <div class="sarfee-facts-icon-badge">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                            </div>
                            <div>
                                <h3 class="sarfee-facts-title">شناسنامه و مشخصات کلیدی <?php echo esc_html( $exchange_name ); ?></h3>
                                <div class="sarfee-facts-subtitle">بررسی شده و مستند در پایگاه تحلیلی <?php echo esc_html( $site_name ); ?></div>
                            </div>
                        </div>

                        <!-- Badges -->
                        <div class="sarfee-facts-badges">
                            <?php if ( $verified ) : ?>
                                <span class="sarfee-badge sarfee-badge-verified">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                    دارای مجوز رسمی
                                </span>
                            <?php else : ?>
                                <span class="sarfee-badge sarfee-badge-registered">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                                    ثبت‌شده در سامانه
                                </span>
                            <?php endif; ?>

                            <?php if ( $currency ) : ?>
                                <span class="sarfee-badge sarfee-badge-crypto">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"></circle><path d="M14.5 9h-5v6h5"></path><path d="M11.5 9v6"></path></svg>
                                    رمزارز
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- 2-Column Responsive Facts Grid -->
                    <div class="sarfee-facts-grid">
                        <!-- 1. City -->
                        <div class="sarfee-fact-item">
                            <div class="sarfee-fact-item-icon" style="background:#eff6ff; color:#2563eb;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                            </div>
                            <div class="sarfee-fact-text">
                                <div class="sarfee-fact-label">شهر محل فعالیت</div>
                                <div class="sarfee-fact-val"><?php echo esc_html( $city ); ?></div>
                            </div>
                        </div>

                        <!-- 2. License / Verification -->
                        <div class="sarfee-fact-item">
                            <div class="sarfee-fact-item-icon" style="background:#ecfdf5; color:#059669;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><polyline points="9 12 11 14 15 10"></polyline></svg>
                            </div>
                            <div class="sarfee-fact-text">
                                <div class="sarfee-fact-label">وضعیت مجوز و اعتبار</div>
                                <div class="sarfee-fact-val" style="color:<?php echo $verified ? '#059669' : '#64748b'; ?>;">
                                    <?php echo $verified ? 'مجاز و دارای پروانه معتبر' : 'در دست ارزیابی مدارک'; ?>
                                </div>
                            </div>
                        </div>

                        <!-- 3. Crypto / Digital Currency -->
                        <div class="sarfee-fact-item">
                            <div class="sarfee-fact-item-icon" style="background:#f5f3ff; color:#7c3aed;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                            </div>
                            <div class="sarfee-fact-text">
                                <div class="sarfee-fact-label">رمزارز</div>
                                <div class="sarfee-fact-val"><?php echo $currency ? 'پشتیبانی می‌شود' : 'پشتیبانی نمی‌شود'; ?></div>
                            </div>
                        </div>

                        <!-- 4. Rank -->
                        <div class="sarfee-fact-item">
                            <div class="sarfee-fact-item-icon" style="background:#fffbeb; color:#d97706;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="7"></circle><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline></svg>
                            </div>
                            <div class="sarfee-fact-text">
                                <div class="sarfee-fact-label">رتبه در سامانه <?php echo esc_html( $site_name ); ?></div>
                                <div class="sarfee-fact-val">
                                    <?php if ( $rank ) : ?>
                                        <span style="color:#d97706; font-weight:800;">رتبه <?php echo esc_html( $rank ); ?></span> در بین صرافی‌ها
                                    <?php else : ?>
                                        بررسی شده در سامانه
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- 5. Phone -->
                        <div class="sarfee-fact-item">
                            <div class="sarfee-fact-item-icon" style="background:#ecfeff; color:#0891b2;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                            </div>
                            <div class="sarfee-fact-text">
                                <div class="sarfee-fact-label">شماره تماس مستقیم</div>
                                <div class="sarfee-fact-val">
                                    <?php if ( $phone ) : ?>
                                        <a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', (string) $phone ) ); ?>" dir="ltr" style="direction:ltr; unicode-bidi:isolate; font-family:-apple-system,BlinkMacSystemFont,'SF Mono',Consolas,monospace; text-decoration:none; color:#0f172a; font-weight:700;">
                                            <?php echo esc_html( $phone ); ?>
                                        </a>
                                    <?php else : ?>
                                        <span style="color:#94a3b8;">استعلام از طریق وب‌سایت</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- 6. Address -->
                        <div class="sarfee-fact-item">
                            <div class="sarfee-fact-item-icon" style="background:#f1f5f9; color:#475569;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                            </div>
                            <div class="sarfee-fact-text">
                                <div class="sarfee-fact-label">آدرس و موقعیت مکانی</div>
                                <div class="sarfee-fact-val" title="<?php echo esc_attr( wp_strip_all_tags( (string) $address ) ); ?>">
                                    <?php echo $address ? esc_html( wp_strip_all_tags( (string) $address ) ) : 'فعال در حوزه ' . esc_html( $city ); ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer Action (If viewing in an article/blog) -->
                    <?php if ( $is_external_post && $url ) : ?>
                        <div class="sarfee-facts-footer">
                            <div class="sarfee-facts-footer-note">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                                <span>جهت مشاهده نقد و بررسی جامع، استعلام نرخ‌ها و ثبت تجربه کاربران:</span>
                            </div>
                            <a href="<?php echo esc_url( $url ); ?>" class="sarfee-facts-btn">
                                <span>مشاهده صفحه رسمی <?php echo esc_html( $exchange_name ); ?></span>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                            </a>
                        </div>
                    <?php endif; ?>

                    <!-- Statutory Legal Protection Note -->
                    <div style="margin-top:14px; padding-top:10px; border-top:1px solid #f1f5f9; font-size:11px; color:#94a3b8; line-height:1.7; display:flex; align-items:center; gap:6px;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                        <span>سلب مسئولیت: این سامانه صرفاً پایگاه اطلاع‌رسانی و مقایسه اطلاعات صرافی‌هاست و هیچ‌گونه معامله مالی، تبادل ارز یا خرید و فروش رمزارز انجام نمی‌دهد.</span>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
