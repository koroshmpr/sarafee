<?php
/**
 * Symbols Header Mega Menu Component
 * Usage: [symbols_header_menu]
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'symbols_header_menu', 'render_symbols_header_mega_menu' );

function render_symbols_header_mega_menu( $atts ) {
    $atts = shortcode_atts( [
        'title' => 'قیمت طلا و ارزها',
    ], $atts );

    // 1. Query WP symbol posts for custom names and permalinks
    $wp_symbols = [];
    $symbols_posts = get_posts( [
        'post_type'      => 'symbol',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
    ] );

    foreach ( $symbols_posts as $sp ) {
        $slug = strtolower( $sp->post_name );
        $clean_title = $sp->post_title;
        $parts = preg_split( '/[|:|–|-]/', $clean_title );
        $clean_title = trim( $parts[0] );
        if ( mb_strpos( $clean_title, 'قیمت ' ) === 0 ) {
            $clean_title = trim( mb_substr( $clean_title, 5 ) );
        }
        if ( mb_substr( $clean_title, -6 ) === ' امروز' ) {
            $clean_title = trim( mb_substr( $clean_title, 0, -6 ) );
        }

        $fa_name = function_exists( 'get_field' ) ? get_field( 'fa_name', $sp->ID ) : '';
        $display_name = ! empty( $fa_name ) ? trim( $fa_name ) : $clean_title;

        $wp_symbols[$slug] = [
            'name' => $display_name,
            'url'  => get_permalink( $sp->ID ),
        ];
    }

    // 2. Pre-fetch API models (cached for 5 minutes)
    $transient_key = 'sat_api_models_v2';
    $api_models = get_transient( $transient_key );

    if ( false === $api_models || ! is_array( $api_models ) ) {
        $response = wp_remote_get( 'https://market-pulse.khanes.app/api/v2/currencies?per_page=500', [ 'timeout' => 5 ] );
        if ( ! is_wp_error( $response ) ) {
            $body = wp_remote_retrieve_body( $response );
            $json = json_decode( $body, true );
            if ( ! empty( $json['data']['models'] ) && is_array( $json['data']['models'] ) ) {
                $api_models = $json['data']['models'];
                set_transient( $transient_key, $api_models, 300 );
            }
        }
    }

    $gold_items = [];
    $currency_items = [];

    if ( ! empty( $api_models ) && is_array( $api_models ) ) {
        foreach ( $api_models as $model ) {
            if ( empty( $model['symbol'] ) ) continue;
            $slug = strtolower( $model['symbol'] );
            $asset_type = ( isset( $model['asset_type'] ) && $model['asset_type'] === 'gold' ) ? 'gold' : 'currency';

            $wp_info = isset( $wp_symbols[$slug] ) ? $wp_symbols[$slug] : [];
            $name    = ! empty( $wp_info['name'] ) ? $wp_info['name'] : ( ! empty( $model['name_fa'] ) ? $model['name_fa'] : ( ! empty( $model['name'] ) ? $model['name'] : $slug ) );
            $url     = ! empty( $wp_info['url'] ) ? $wp_info['url'] : home_url( '/' . $slug );
            $icon    = ! empty( $model['iconUrl'] ) ? $model['iconUrl'] : '';

            $item = [
                'slug'       => $slug,
                'name'       => $name,
                'url'        => $url,
                'icon'       => $icon,
                'asset_type' => $asset_type,
            ];

            if ( $asset_type === 'gold' ) {
                $gold_items[] = $item;
            } else {
                $currency_items[] = $item;
            }
        }
    }

    $uid = 'hmm_' . uniqid();

    // Helper for rendering icons
    $get_icon_markup = function( $icon_url, $asset_type, $name ) {
        if ( ! empty( $icon_url ) ) {
            $no_radius = ( $asset_type === 'currency' ) ? 'smm-icon--no-radius' : '';
            return '<img src="' . esc_url( $icon_url ) . '" alt="' . esc_attr( $name ) . '" class="smm-icon ' . $no_radius . '" width="20" height="14" loading="lazy" />';
        }
        if ( $asset_type === 'gold' ) {
            $gid = 'smm_gold_grad_' . uniqid();
            return '<svg width="20" height="20" class="smm-icon smm-icon--gold" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="12" cy="12" r="9" fill="url(#' . $gid . ')" stroke="#D97706" stroke-width="1.5"/>
                <path d="M12 7V17M9 9.5C9 8.5 10.2 7.5 12 7.5C13.8 7.5 15 8.5 15 9.5C15 11 13 11.5 12 12C11 12.5 9 13 9 14.5C9 15.5 10.2 16.5 12 16.5C13.8 16.5 15 15.5 15 14.5" stroke="#92400E" stroke-width="1.5" stroke-linecap="round"/>
                <defs>
                    <linearGradient id="' . $gid . '" x1="3" y1="3" x2="21" y2="21" gradientUnits="userSpaceOnUse">
                        <stop stop-color="#FDE047"/>
                        <stop offset="0.5" stop-color="#EAB308"/>
                        <stop offset="1" stop-color="#CA8A04"/>
                    </linearGradient>
                </defs>
            </svg>';
        }
        return '<svg class="smm-icon smm-icon--no-radius" viewBox="0 0 20 14" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect width="20" height="14" fill="#CBD5E1"/>
            <circle cx="10" cy="7" r="3" stroke="#475569" stroke-width="1.5"/>
        </svg>';
    };

    ob_start();
    ?>
    <div class="smm-wrapper" id="<?php echo esc_attr( $uid ); ?>" dir="rtl">
        <!-- Header Trigger Button -->
        <button type="button" class="smm-trigger-btn" aria-expanded="false" aria-controls="<?php echo esc_attr( $uid ); ?>-menu">
            <span class="smm-trigger-text"><?php echo esc_html( $atts['title'] ); ?></span>
            <svg class="smm-trigger-arrow" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="6 9 12 15 18 9"></polyline>
            </svg>
        </button>

        <!-- Mega Menu Panel (Compact Dropdown on Desktop, Accordion on Mobile) -->
        <div class="smm-panel" id="<?php echo esc_attr( $uid ); ?>-menu">
            <div class="smm-container">
                <!-- Top Header: View All Link -->
                <div class="smm-all-link-wrap">
                    <a href="<?php echo esc_url( home_url( '/symbol' ) ); ?>" class="smm-all-link">
                        <span>مشاهده همه نمادها</span>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="15 18 9 12 15 6"></polyline>
                        </svg>
                    </a>
                </div>

                <!-- Gold & Coins Section -->
                <div class="smm-section smm-section--gold">
                    <button type="button" class="smm-accordion-hdr" aria-expanded="true">
                        <span class="smm-section-title">
                            <span class="smm-badge-dot smm-badge-dot--gold"></span>
                            طلا، سکه و طلای آب‌شده
                        </span>
                        <svg class="smm-acc-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </button>
                    <div class="smm-grid">
                        <?php foreach ( $gold_items as $gi ) : ?>
                            <a href="<?php echo esc_url( $gi['url'] ); ?>" class="smm-item">
                                <span class="smm-item-icon-wrap">
                                    <?php echo $get_icon_markup( $gi['icon'], 'gold', $gi['name'] ); ?>
                                </span>
                                <span class="smm-item-name"><?php echo esc_html( $gi['name'] ); ?></span>
                                <svg class="smm-item-arrow" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="15 18 9 12 15 6"></polyline>
                                </svg>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Currencies Section -->
                <div class="smm-section smm-section--currency">
                    <button type="button" class="smm-accordion-hdr" aria-expanded="true">
                        <span class="smm-section-title">
                            <span class="smm-badge-dot smm-badge-dot--blue"></span>
                            دلار و ارزهای آزاد
                        </span>
                        <svg class="smm-acc-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </button>
                    <div class="smm-grid">
                        <?php foreach ( $currency_items as $ci ) : ?>
                            <a href="<?php echo esc_url( $ci['url'] ); ?>" class="smm-item">
                                <span class="smm-item-icon-wrap">
                                    <?php echo $get_icon_markup( $ci['icon'], 'currency', $ci['name'] ); ?>
                                </span>
                                <span class="smm-item-name"><?php echo esc_html( $ci['name'] ); ?></span>
                                <svg class="smm-item-arrow" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="15 18 9 12 15 6"></polyline>
                                </svg>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <script>
    (function() {
        if (window.__smmInit) return;
        window.__smmInit = true;

        document.addEventListener('click', function(e) {
            // 1. Trigger button click
            var btn = e.target.closest('.smm-trigger-btn');
            if (btn) {
                e.preventDefault();
                e.stopPropagation();
                var wrap = btn.closest('.smm-wrapper');
                if (wrap) {
                    var isOpen = wrap.classList.toggle('is-open');
                    btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                }
                return;
            }

            // 2. Accordion sub-header click
            var accHdr = e.target.closest('.smm-accordion-hdr');
            if (accHdr) {
                var section = accHdr.closest('.smm-section');
                if (section) {
                    var isCollapsed = section.classList.toggle('is-collapsed');
                    accHdr.setAttribute('aria-expanded', isCollapsed ? 'false' : 'true');
                }
                return;
            }

            // 3. Click outside -> close open wrappers
            document.querySelectorAll('.smm-wrapper.is-open').forEach(function(wrap) {
                if (!wrap.contains(e.target)) {
                    wrap.classList.remove('is-open');
                    var tBtn = wrap.querySelector('.smm-trigger-btn');
                    if (tBtn) tBtn.setAttribute('aria-expanded', 'false');
                }
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}
