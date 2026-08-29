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
