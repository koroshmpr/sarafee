<?php
/**
 * Live currency price shortcode.
 * Usage: [currency_price] or [currency_price slug="gbp"]
 *
 * Streams real-time price from:
 * wss://market-pulse-ws.khanes.app/ws/currency/price/{SLUG}
 *
 * Message shape: { type:"price_update", price_in_toman:235140, change_24h:0.5, change_toman:1500, ... }
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'currency_price', 'render_currency_live_price' );

function render_currency_live_price( $atts ) {
    global $post;

    $atts = shortcode_atts( [ 'slug' => '' ], $atts );
    $slug = $atts['slug'] !== '' ? sanitize_key( $atts['slug'] ) : ( $post ? $post->post_name : 'usd' );

    static $instance = 0;
    $instance++;
    $uid = 'clp_' . $instance . '_' . $slug;

    // ── 1. Fetch WP symbol posts & API models transient ─────────────────────
    static $symbols_cache = null;
    if ( null === $symbols_cache ) {
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

        $wp_symbols = [];
        $symbols_posts = get_posts( [
            'post_type'      => 'symbol',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
        ] );

        foreach ( $symbols_posts as $sp ) {
            $s_slug = strtolower( $sp->post_name );
            $clean_title = $sp->post_title;
            $parts = preg_split( '/[|:|–|-]/', $clean_title );
            $clean_title = trim( $parts[0] );
            if ( mb_strpos( $clean_title, 'قیمت ' ) === 0 ) {
                $clean_title = trim( mb_substr( $clean_title, 5 ) );
            }
            if ( mb_substr( $clean_title, -6 ) === ' امروز' ) {
                $clean_title = trim( mb_substr( $clean_title, 0, -6 ) );
            }
            $fa = function_exists( 'get_field' ) ? get_field( 'fa_name', $sp->ID ) : '';
            $display_name = ! empty( $fa ) ? trim( $fa ) : $clean_title;

            $wp_symbols[$s_slug] = [
                'name' => $display_name,
                'url'  => get_permalink( $sp->ID ),
            ];
        }

        $gold_slugs = ['geram18', 'sekee', 'sekeb', 'nim', 'rob', 'abshodeh', 'gerami', 'sekee_bubbler', 'sekeb_blubber', 'nim_blubber', 'rob_blubber', 'bub_18ayar', 'gerami_blubber', 'ons', 'ons_silver', 'tala', 'silver', 'mesghal'];

        $all_items = [];
        if ( ! empty( $api_models ) && is_array( $api_models ) ) {
            foreach ( $api_models as $model ) {
                if ( empty( $model['symbol'] ) ) continue;
                $s_slug = strtolower( $model['symbol'] );
                $asset_type = ( isset( $model['asset_type'] ) && $model['asset_type'] === 'gold' ) ? 'gold' : ( in_array( $s_slug, $gold_slugs, true ) ? 'gold' : 'currency' );

                $wp_info = isset( $wp_symbols[$s_slug] ) ? $wp_symbols[$s_slug] : [];
                $name    = ! empty( $wp_info['name'] ) ? $wp_info['name'] : ( ! empty( $model['name_fa'] ) ? $model['name_fa'] : ( ! empty( $model['name'] ) ? $model['name'] : strtoupper( $s_slug ) ) );
                $url     = ! empty( $wp_info['url'] ) ? $wp_info['url'] : home_url( '/' . $s_slug );
                $icon    = ! empty( $model['iconUrl'] ) ? $model['iconUrl'] : '';

                $price        = isset( $model['price_in_toman'] ) ? floatval( $model['price_in_toman'] ) : 0;
                $change       = isset( $model['change_24h'] ) ? floatval( $model['change_24h'] ) : null;
                $change_toman = isset( $model['change_toman'] ) ? floatval( $model['change_toman'] ) : null;

                if ( null === $change_toman && null !== $change && $price > 0 ) {
                    $change_toman = round( $price * ( $change / 100 ) );
                }

                $all_items[$s_slug] = [
                    'slug'         => $s_slug,
                    'ticker'       => strtoupper( $model['symbol'] ),
                    'name'         => $name,
                    'url'          => $url,
                    'icon'         => $icon,
                    'asset_type'   => $asset_type,
                    'price'        => $price,
                    'change'       => $change,
                    'change_toman' => $change_toman,
                ];
            }
        }

        foreach ( $wp_symbols as $s_slug => $wp_info ) {
            if ( ! isset( $all_items[$s_slug] ) ) {
                $asset_type = in_array( $s_slug, $gold_slugs, true ) ? 'gold' : 'currency';
                $all_items[$s_slug] = [
                    'slug'         => $s_slug,
                    'ticker'       => strtoupper( $s_slug ),
                    'name'         => $wp_info['name'],
                    'url'          => $wp_info['url'],
                    'icon'         => '',
                    'asset_type'   => $asset_type,
                    'price'        => 0,
                    'change'       => null,
                    'change_toman' => null,
                ];
            }
        }

        $symbols_cache = $all_items;
    }

    // ── 2. Current symbol details & category sorting ────────────────────────
    $gold_slugs_list = ['geram18', 'sekee', 'sekeb', 'nim', 'rob', 'abshodeh', 'gerami', 'sekee_bubbler', 'sekeb_blubber', 'nim_blubber', 'rob_blubber', 'bub_18ayar', 'gerami_blubber', 'ons', 'ons_silver', 'tala', 'silver', 'mesghal'];
    $current_item = isset( $symbols_cache[$slug] ) ? $symbols_cache[$slug] : null;
    $current_asset_type = $current_item ? $current_item['asset_type'] : ( in_array( $slug, $gold_slugs_list, true ) ? 'gold' : 'currency' );
    $current_ticker = $current_item ? $current_item['ticker'] : strtoupper( $slug );
    $current_icon = $current_item ? $current_item['icon'] : '';

    $fa_name = '';
    if ( $current_item && ! empty( $current_item['name'] ) ) {
        $fa_name = $current_item['name'];
    } elseif ( $post && $post->post_type === 'symbol' ) {
        $fa_name = function_exists( 'get_field' ) ? get_field( 'fa_name', $post->ID ) : '';
        if ( empty( $fa_name ) ) {
            $clean_title = $post->post_title;
            $parts = preg_split( '/[|:|–|-]/', $clean_title );
            $clean_title = trim( $parts[0] );
            if ( mb_strpos( $clean_title, 'قیمت ' ) === 0 ) {
                $clean_title = trim( mb_substr( $clean_title, 5 ) );
            }
            if ( mb_substr( $clean_title, -6 ) === ' امروز' ) {
                $clean_title = trim( mb_substr( $clean_title, 0, -6 ) );
            }
            $fa_name = $clean_title;
        }
    }
    if ( empty( $fa_name ) ) {
        $fa_name = strtoupper( $slug );
    }

    $gold_list = [];
    $currency_list = [];

    foreach ( $symbols_cache as $item ) {
        if ( $item['asset_type'] === 'gold' ) {
            $gold_list[] = $item;
        } else {
            $currency_list[] = $item;
        }
    }

    // Order categories: current symbol category first, then other
    if ( $current_asset_type === 'gold' ) {
        $ordered_categories = [
            [ 'id' => 'gold', 'title' => 'طلا و سکه', 'items' => $gold_list ],
            [ 'id' => 'currency', 'title' => 'ارزها', 'items' => $currency_list ],
        ];
    } else {
        $ordered_categories = [
            [ 'id' => 'currency', 'title' => 'ارزها', 'items' => $currency_list ],
            [ 'id' => 'gold', 'title' => 'طلا و سکه', 'items' => $gold_list ],
        ];
    }

    $render_symbol_icon = function( $icon_url, $asset_type, $name ) {
        if ( ! empty( $icon_url ) ) {
            $no_radius = ( $asset_type === 'currency' ) ? 'clp-icon--no-radius' : '';
            return '<img src="' . esc_url( $icon_url ) . '" alt="' . esc_attr( $name ) . '" class="clp-icon ' . $no_radius . '" width="20" height="20" loading="lazy" />';
        }
        if ( $asset_type === 'gold' ) {
            $gid = 'clp_g_' . uniqid();
            return '<svg class="clp-icon clp-icon--gold" width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
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
        return '<svg class="clp-icon clp-icon--no-radius" width="20" height="14" viewBox="0 0 20 14" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect width="20" height="14" rx="2" fill="#059669"/>
            <circle cx="10" cy="7" r="3" stroke="#ffffff" stroke-width="1.5"/>
        </svg>';
    };

    // Pre-fill initial values if available
    $init_price = ( $current_item && ! empty( $current_item['price'] ) ) ? number_format( round( $current_item['price'] ) ) : '···';
    $init_pct   = ( $current_item && null !== $current_item['change'] ) ? ( ( $current_item['change'] >= 0 ? '+' : '' ) . number_format( $current_item['change'], 2 ) . '%' ) : '···';
    $init_is_down = ( $current_item && null !== $current_item['change'] && $current_item['change'] < 0 );

    $init_toman_str = '···';
    if ( $current_item && null !== $current_item['change_toman'] ) {
        $t_val = round( $current_item['change_toman'] );
        $init_toman_str = ( $t_val > 0 ? '+ ' : '' ) . number_format( $t_val );
    }

    ob_start();
    ?>
    <div class="clp-root" id="<?php echo esc_attr( $uid ); ?>" dir="rtl">
        <div class="clp">
            <!-- Right side: Interactive Title Selector Button -->
            <div class="clp__title-container">
                <h1 class="clp__title-h1">
                    <button type="button" class="clp__title-btn" aria-expanded="false" aria-haspopup="true" data-clp-role="trigger">
                        <span class="clp__title-icon-wrap"><?php echo $render_symbol_icon( $current_icon, $current_asset_type, $fa_name ); ?></span>
                        <div class="clp__title-content">
                            <span class="clp__title-main"><?php echo esc_html( $fa_name ); ?></span>
                        
                        </div>
                        <svg class="clp__title-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </button>
                </h1>

                <div class="clp__dropdown-menu" data-clp-role="dropdown" role="menu" aria-hidden="true">
                    <div class="clp__search-wrap">
                        <svg class="clp__search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                        <input type="text" class="clp__search-input" placeholder="جستجوی نماد..." autocomplete="off">
                    </div>

                    <div class="clp__dropdown-list">
                        <?php foreach ( $ordered_categories as $cat ) : ?>
                            <?php if ( ! empty( $cat['items'] ) ) : ?>
                                <div class="clp__cat-group" data-cat="<?php echo esc_attr( $cat['id'] ); ?>">
                                    <div class="clp__cat-title"><?php echo esc_html( $cat['title'] ); ?></div>
                                    <?php foreach ( $cat['items'] as $item ) : ?>
                                        <?php $is_active = ( $item['slug'] === $slug ); ?>
                                        <a href="<?php echo esc_url( $item['url'] ); ?>" 
                                           class="clp__item <?php echo $is_active ? 'is-active' : ''; ?>"
                                           data-search="<?php echo esc_attr( mb_strtolower( $item['name'] . ' ' . $item['slug'] . ' ' . $item['ticker'] ) ); ?>">
                                            <div class="clp__item-main">
                                                <span class="clp__item-icon"><?php echo $render_symbol_icon( $item['icon'], $item['asset_type'], $item['name'] ); ?></span>
                                                <span class="clp__item-name"><?php echo esc_html( $item['name'] ); ?></span>
                                            </div>
                                            <span class="clp__item-ticker">(<?php echo esc_html( $item['ticker'] ); ?>)</span>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <div class="clp__no-results" style="display: none;">نمادی یافت نشد</div>
                    </div>
                </div>
            </div>

            <!-- Left side: Live Price & 24h Change Box -->
            <div class="clp__price-box">
                <div class="clp__price-top">
                    <div class="clp__change-badge-wrap">
                        <span class="clp__change-period">(روزانه)</span>
                        <span class="clp__change <?php echo $init_is_down ? 'clp__change--down' : ''; ?>">
                            <span class="clp__change-pct"><?php echo esc_html( $init_pct ); ?></span>
                            <svg class="clp__change-arrow" width="10" height="10" viewBox="0 0 24 24" fill="currentColor">
                                <path class="clp__arrow-path" d="M12 4l-8 8h16l-8-8z"/>
                            </svg>
                        </span>
                    </div>
                    <span class="clp__price"><?php echo esc_html( $init_price ); ?></span>
                    <span class="clp__live-dot"></span>
                </div>
                <div class="clp__price-bottom">
                    <span class="clp__change-toman"><?php echo esc_html( $init_toman_str ); ?></span>
                    <span class="clp__unit">تومان</span>
                </div>
            </div>
        </div>
    </div>

    <?php
    static $css_printed = false;
    if ( ! $css_printed ) :
        $css_printed = true;
    ?>
    <style>
    .clp {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        background: #fff;
        border-radius: 0.875rem;
        padding: 0.875rem 1.25rem;
        border: 1px solid #eef0f2;
        font-family: inherit;
        box-sizing: border-box;
        min-width: 340px;
        position: relative;
    }

    /* Right Side: Title button & dropdown */
    .clp__title-container {
        position: relative;
        display: inline-block;
    }

    .clp__title-h1 {
        margin: 0;
        padding: 0;
        font-size: inherit;
        font-weight: inherit;
        line-height: inherit;
    }

    .clp__title-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 0.625rem;
        padding: 0.7rem;
        cursor: pointer;
        font-family: inherit;
        color: #0a1128;
        transition: all 0.2s ease;
        outline: none;
        user-select: none;
        text-align: right;
    }

    .clp__title-btn:hover,
    .clp__title-btn:focus-visible {
        background: #f1f5f9;
        border-color: #cbd5e1;
    }

    .clp__title-container.is-open .clp__title-btn {
        background: #ffffff;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
    }

    .clp__title-icon-wrap {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 22px;
        height: 22px;
        flex-shrink: 0;
    }

    .clp-icon {
        object-fit: contain;
        border-radius: 50%;
    }
    .clp-icon--no-radius {
        border-radius: 2px;
    }

    .clp__title-content {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        line-height: 1.2;
    }

    .clp__title-main {
        font-size: 0.85rem;
        font-weight: 700;
        color: #0a1128;
        white-space: nowrap;
    }

    .clp__title-sub {
        font-size: 0.6875rem;
        color: #64748b;
        font-weight: 500;
        white-space: nowrap;
        direction: ltr;
    }

    .clp__title-chevron {
        color: #64748b;
        flex-shrink: 0;
        transition: transform 0.2s ease;
        margin-right: 0.125rem;
    }

    .clp__title-container.is-open .clp__title-chevron {
        transform: rotate(180deg);
        color: #3b82f6;
    }

    /* Dropdown Menu */
    .clp__dropdown-menu {
        position: absolute;
        top: calc(100% + 6px);
        right: 0;
        width: 270px;
        max-width: 90vw;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 0.875rem;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
        z-index: 9999;
        display: none;
        flex-direction: column;
        padding: 0.5rem;
        box-sizing: border-box;
    }

    .clp__title-container.is-open .clp__dropdown-menu {
        display: flex;
    }

    .clp__search-wrap {
        position: relative;
        margin-bottom: 0.5rem;
    }

    .clp__search-input {
        width: 100% !important;
        padding: 0.45rem 2rem 0.45rem 0.625rem !important;
        border-radius: 0.5rem !important;
        border: 1px solid #e2e8f0 !important;
        font-size: 0.78125rem !important;
        font-family: inherit !important;
        outline: none !important;
        box-sizing: border-box !important;
        background: #f8fafc !important;
        color: #0f172a !important;
        transition: border-color 0.2s, background-color 0.2s !important;
        height: auto !important;
        line-height: 1.4 !important;
    }

    .clp__search-input:focus {
        background: #ffffff !important;
        border-color: #3b82f6 !important;
    }

    .clp__search-icon {
        position: absolute;
        right: 0.625rem;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        pointer-events: none;
    }

    .clp__dropdown-list {
        max-height: 260px;
        overflow-y: auto;
        scrollbar-width: thin;
        scrollbar-color: #cbd5e1 transparent;
        padding-left: 2px;
    }

    .clp__dropdown-list::-webkit-scrollbar {
        width: 5px;
    }
    .clp__dropdown-list::-webkit-scrollbar-thumb {
        background-color: #cbd5e1;
        border-radius: 4px;
    }

    .clp__cat-title {
        font-size: 0.6875rem;
        font-weight: 700;
        color: #94a3b8;
        padding: 0.375rem 0.5rem 0.25rem 0.5rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom: 1px dashed #f1f5f9;
        margin-bottom: 0.25rem;
    }

    .clp__item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.45rem 0.5rem;
        border-radius: 0.5rem;
        text-decoration: none !important;
        color: #334155 !important;
        transition: background-color 0.15s ease;
        font-size: 0.8125rem;
        gap: 0.5rem;
    }

    .clp__item:hover {
        background-color: #f1f5f9;
        color: #0f172a !important;
    }

    .clp__item.is-active {
        background-color: #eff6ff;
        color: #2563eb !important;
        font-weight: 700;
    }

    .clp__item-main {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        min-width: 0;
    }

    .clp__item-icon {
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .clp__item-name {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .clp__item-ticker {
        font-size: 0.72rem;
        color: #94a3b8;
        direction: ltr;
        font-weight: 500;
        flex-shrink: 0;
    }

    .clp__item.is-active .clp__item-ticker {
        color: #3b82f6;
    }

    .clp__no-results {
        padding: 1rem 0.5rem;
        text-align: center;
        font-size: 0.8125rem;
        color: #94a3b8;
    }

    /* Left Side: Price & Change Box */
    .clp__price-box {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 0.2rem;
    }

    .clp__price-top {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        line-height: 1;
    }

    .clp__live-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #10b981;
        flex-shrink: 0;
        animation: clp-pulse 2s infinite;
    }
    .clp__live-dot--off {
        background: #d1d5db;
        animation: none;
    }

    @keyframes clp-pulse {
        0%, 100% { opacity: 1; transform: scale(1); }
        50%       { opacity: .4; transform: scale(.75); }
    }

    .clp__price {
        font-size: 1.75rem;
        font-weight: 900;
        color: #0a1128;
        letter-spacing: -0.02em;
        line-height: 1;
        white-space: nowrap;
        transition: color 0.3s;
    }

    .clp__price--flash-up   { color: #10b981; }
    .clp__price--flash-down { color: #ef4444; }

    .clp__change-badge-wrap {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 0.15rem;
    }

    .clp__change-period {
        font-size: 0.7rem;
        color: #94a3b8;
        font-weight: 500;
        line-height: 1;
    }

    .clp__change {
        display: inline-flex;
        align-items: center;
        gap: 0.15rem;
        font-size: 0.8125rem;
        font-weight: 700;
        color: #10b981;
        line-height: 1;
        direction: ltr;
    }

    .clp__change--down {
        color: #ef4444;
    }

    .clp__change-arrow {
        flex-shrink: 0;
        transition: transform 0.2s ease;
    }
    .clp__change--down .clp__change-arrow {
        transform: rotate(180deg);
    }

    .clp__price-bottom {
        display: flex;
        align-items: center;
        gap: 0.25rem;
        font-size: 0.8125rem;
        color: #64748b;
        font-weight: 500;
        padding-left: 1rem;
    }

    .clp__change-toman {
        font-weight: 600;
    }

    .clp__unit {
        font-size: 0.8125rem;
        color: #64748b;
    }

    @media (max-width: 768px) {
        .clp {
            min-width: 0;
            width: 100%;
            gap: 0.5rem;
            padding: 0.75rem 0.625rem;
        }
        .clp__title-btn {
            gap: 0.35rem;
        }
        .clp__title-main {
            font-size: 0.78125rem;
        }
        .clp__title-sub {
            font-size: 0.625rem;
        }
        .clp__dropdown-menu {
            width: 260px;
            max-width: 85vw;
        }
        .clp__price {
            font-size: 1.35rem;
        }
    }
    </style>
    <?php endif; ?>

    <script>
    (function () {
        var uid    = <?php echo wp_json_encode( $uid ); ?>;
        var slug   = <?php echo wp_json_encode( $slug ); ?>;
        var WS_URL = 'wss://market-pulse-ws.khanes.app/ws/currency/price/' + slug.toUpperCase();

        var root     = document.getElementById( uid );
        var priceEls = root.querySelectorAll( '.clp__price' );
        var pctEls   = root.querySelectorAll( '.clp__change-pct' );
        var changeEls = root.querySelectorAll( '.clp__change' );
        var tomanEls = root.querySelectorAll( '.clp__change-toman' );
        var dotEls   = root.querySelectorAll( '.clp__live-dot' );

        var ws         = null;
        var retryDelay = 1000;
        var maxDelay   = 30000;
        var flashTimer = null;
        var staleTimer = null;

        function fmt( n ) {
            return new Intl.NumberFormat( 'fa-IR' ).format( Math.round( n ) );
        }

        function each( list, fn ) {
            for ( var i = 0; i < list.length; i++ ) fn( list[ i ] );
        }

        function setConnected( on ) {
            each( dotEls, function ( el ) { el.classList.toggle( 'clp__live-dot--off', ! on ); } );
        }

        function flashPrice( isUp ) {
            var cls = isUp ? 'clp__price--flash-up' : 'clp__price--flash-down';
            each( priceEls, function ( el ) { el.classList.add( cls ); } );
            clearTimeout( flashTimer );
            flashTimer = setTimeout( function () {
                each( priceEls, function ( el ) { el.classList.remove( cls ); } );
            }, 500 );
        }

        function applyChange( pct, changeToman, price ) {
            var isUp = pct >= 0;
            var pctText = ( isUp ? '+' : '' ) + pct.toFixed( 2 ) + '%';

            var tomanVal = changeToman;
            if ( typeof tomanVal !== 'number' && typeof pct === 'number' && price ) {
                tomanVal = Math.round( price * ( pct / 100 ) );
            }

            each( changeEls, function ( el ) {
                el.classList.toggle( 'clp__change--down', ! isUp );
            } );
            each( pctEls, function ( el ) {
                el.textContent = pctText;
            } );

            if ( typeof tomanVal === 'number' ) {
                var formattedToman = ( tomanVal > 0 ? '+ ' : '' ) + fmt( tomanVal );
                each( tomanEls, function ( el ) {
                    el.textContent = formattedToman;
                } );
            }
        }

        function resetStaleTimer() {
            clearTimeout( staleTimer );
            staleTimer = setTimeout( function () {
                if ( ws ) { ws.close(); }
            }, 60000 );
        }

        function onMessage( event ) {
            resetStaleTimer();

            var msg;
            try { msg = JSON.parse( event.data ); } catch ( e ) { return; }

            if ( msg.type !== 'price_update' ) return;

            var price     = parseFloat( msg.price_in_toman ) || 0;
            var prevText  = priceEls[ 0 ] ? priceEls[ 0 ].dataset.raw || '0' : '0';
            var prevPrice = parseFloat( prevText ) || 0;

            each( priceEls, function ( el ) {
                el.dataset.raw = price;
                el.textContent = fmt( price );
            } );
            flashPrice( price >= prevPrice );

            var changePct   = typeof msg.change_24h === 'number' ? msg.change_24h : null;
            var changeToman = typeof msg.change_toman === 'number' ? msg.change_toman : null;

            if ( changePct !== null ) {
                applyChange( changePct, changeToman, price );
            }
        }

        function connect() {
            ws = new WebSocket( WS_URL );

            ws.onopen = function () {
                setConnected( true );
                retryDelay = 1000;
                resetStaleTimer();
            };

            ws.onmessage = onMessage;

            ws.onclose = function () {
                setConnected( false );
                clearTimeout( staleTimer );
                ws = null;
                setTimeout( connect, retryDelay );
                retryDelay = Math.min( retryDelay * 2, maxDelay );
            };

            ws.onerror = function () { ws.close(); };
        }

        connect();

        // ── Dropdown Toggle & Search Logic ──────────────────────────────────
        var titleContainer = root.querySelector( '.clp__title-container' );
        if ( titleContainer ) {
            var trigger   = titleContainer.querySelector( '[data-clp-role="trigger"]' );
            var dropdown  = titleContainer.querySelector( '[data-clp-role="dropdown"]' );
            var searchInp = titleContainer.querySelector( '.clp__search-input' );
            var catGroups = titleContainer.querySelectorAll( '.clp__cat-group' );
            var noResults = titleContainer.querySelector( '.clp__no-results' );

            if ( trigger && dropdown ) {
                function toggleDropdown( show ) {
                    var isOpen = typeof show === 'boolean' ? show : ! titleContainer.classList.contains( 'is-open' );

                    each( document.querySelectorAll( '.clp__title-container.is-open' ), function ( other ) {
                        if ( other !== titleContainer ) {
                            other.classList.remove( 'is-open' );
                            var trg = other.querySelector( '[data-clp-role="trigger"]' );
                            var drp = other.querySelector( '[data-clp-role="dropdown"]' );
                            if ( trg ) trg.setAttribute( 'aria-expanded', 'false' );
                            if ( drp ) drp.setAttribute( 'aria-hidden', 'true' );
                        }
                    } );

                    titleContainer.classList.toggle( 'is-open', isOpen );
                    trigger.setAttribute( 'aria-expanded', isOpen ? 'true' : 'false' );
                    dropdown.setAttribute( 'aria-hidden', isOpen ? 'false' : 'true' );

                    if ( isOpen && searchInp ) {
                        searchInp.value = '';
                        filterList( '' );
                        setTimeout( function () { searchInp.focus(); }, 50 );
                    }
                }

                function filterList( query ) {
                    var q = query.trim().toLowerCase();
                    var hasAny = false;

                    each( catGroups, function ( group ) {
                        var items = group.querySelectorAll( '.clp__item' );
                        var visibleCount = 0;

                        each( items, function ( item ) {
                            var searchStr = item.getAttribute( 'data-search' ) || '';
                            if ( ! q || searchStr.indexOf( q ) !== -1 ) {
                                item.style.display = 'flex';
                                visibleCount++;
                                hasAny = true;
                            } else {
                                item.style.display = 'none';
                            }
                        } );

                        group.style.display = visibleCount > 0 ? 'block' : 'none';
                    } );

                    if ( noResults ) {
                        noResults.style.display = hasAny ? 'none' : 'block';
                    }
                }

                trigger.addEventListener( 'click', function ( e ) {
                    e.stopPropagation();
                    toggleDropdown();
                } );

                if ( searchInp ) {
                    searchInp.addEventListener( 'click', function ( e ) { e.stopPropagation(); } );
                    searchInp.addEventListener( 'input', function () {
                        filterList( this.value );
                    } );
                }

                dropdown.addEventListener( 'click', function ( e ) {
                    e.stopPropagation();
                } );
            }
        }

        document.addEventListener( 'click', function () {
            each( document.querySelectorAll( '.clp__title-container.is-open' ), function ( c ) {
                c.classList.remove( 'is-open' );
                var trg = c.querySelector( '[data-clp-role="trigger"]' );
                var drp = c.querySelector( '[data-clp-role="dropdown"]' );
                if ( trg ) trg.setAttribute( 'aria-expanded', 'false' );
                if ( drp ) drp.setAttribute( 'aria-hidden', 'true' );
            } );
        } );

        document.addEventListener( 'keydown', function ( e ) {
            if ( e.key === 'Escape' ) {
                each( document.querySelectorAll( '.clp__title-container.is-open' ), function ( c ) {
                    c.classList.remove( 'is-open' );
                    var trg = c.querySelector( '[data-clp-role="trigger"]' );
                    var drp = c.querySelector( '[data-clp-role="dropdown"]' );
                    if ( trg ) trg.setAttribute( 'aria-expanded', 'false' );
                    if ( drp ) drp.setAttribute( 'aria-hidden', 'true' );
                } );
            }
        } );

        if ( typeof MutationObserver !== 'undefined' ) {
            var obs = new MutationObserver( function () {
                if ( ! document.getElementById( uid ) ) {
                    clearTimeout( staleTimer );
                    if ( ws ) ws.close();
                    obs.disconnect();
                }
            } );
            obs.observe( document.body, { childList: true, subtree: true } );
        }

    } )();
    </script>
    <?php
    return ob_get_clean();
}
