<?php
/**
 * Live currency price shortcode.
 * Usage: [currency_price] or [currency_price slug="gbp"]
 *
 * Streams real-time price from:
 * wss://market-pulse-ws.khanes.app/ws/currency/price/{SLUG}
 *
 * Message shape: { type:"price_update", price_in_toman:235140, change_24h:0.5, ... }
 */
add_shortcode( 'currency_price', 'render_currency_live_price' );

function render_currency_live_price( $atts ) {
    global $post;

    $atts = shortcode_atts( [ 'slug' => '' ], $atts );
    $slug = $atts['slug'] !== '' ? sanitize_key( $atts['slug'] ) : ( $post ? $post->post_name : 'usd' );

    static $instance = 0;
    $instance++;
    $uid = 'clp_' . $instance . '_' . $slug;

    ob_start();
    ?>
    <span class="clp__live">
        <span class="clp__live-dot"></span>
        <span class="clp__live-label">زنده</span>
    </span>
    <div class="clp__price-row">
        <span class="clp__price">···</span>
        <span class="clp__unit">تومان</span>
    </div>
    <span class="clp__change">
        <svg class="clp__change-arrow" width="12" height="12" fill="none"
             stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
            <path class="clp__arrow-path"
                  stroke-linecap="round" stroke-linejoin="round"
                  d="M4.5 19.5l15-15m0 0H8.25m11.25 0v11.25"/>
        </svg>
        <span class="clp__change-pct">···</span>
    </span>
    <?php
    $clp_inner = ob_get_clean();

    ob_start();
    ?>
    <div class="clp-root" id="<?php echo esc_attr( $uid ); ?>" dir="rtl">

        <div class="clp" data-clp-role="inline">
            <?php echo $clp_inner; ?>
        </div>

        <div class="clp-float" data-clp-role="float-wrap">
            <div class="clp clp--floating" data-clp-role="floating">
                <?php echo $clp_inner; ?>
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
        gap: .75rem;
        background: #fff;
        border-radius: .875rem;
        padding: 1rem .875rem;
        border: 1px solid #eef0f2;
        font-family: inherit;
        box-sizing: border-box;
    }

    .clp__live {
        display: inline-flex;
        align-items: center;
        gap: .3125rem;
        flex-shrink: 0;
    }
    .clp__live-label {
        font-size: .6875rem;
        font-weight: 700;
        color: #059669;
    }
    .clp__live-dot {
        width: 6px;
        height: 6px;
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
        0%, 100% { opacity: 1;   transform: scale(1); }
        50%       { opacity: .4; transform: scale(.75); }
    }

    .clp__price-row {
        display: flex;
        align-items: baseline;
        justify-content: center;
        gap: .3rem;
        flex: 1;
        min-width: 0;
    }
    .clp__unit {
        font-size: .6875rem;
        color: #9ca3af;
        font-weight: 500;
    }
    .clp__price {
        font-size: 1.3125rem;
        font-weight: 800;
        color: #0A1128;
        letter-spacing: -.02em;
        line-height: 1;
        white-space: nowrap;
        transition: color .3s;
    }
    .clp__price--flash-up   { color: #10b981; }
    .clp__price--flash-down { color: #ef4444; }

    .clp__change {
        display: inline-flex;
        align-items: center;
        gap: .15rem;
        font-size: .75rem;
        font-weight: 700;
        color: #059669;
        background: rgba(16,185,129,.1);
        border-radius: .5rem;
        padding: .25rem .4375rem;
        flex-shrink: 0;
    }
    .clp__change--down {
        color: #dc2626;
        background: rgba(239,68,68,.1);
    }
    .clp__change-arrow { flex-shrink: 0; }

    /* Floating bar: hidden by default, only used on mobile once the inline
       card scrolls out of view (its parent column doesn't have enough
       height for plain `position: sticky` to have room to stick). */
    .clp-float {
        display: none;
    }

    @media (max-width: 768px) {
        .clp-float {
            display: block;
            position: fixed;
            <?= current_user_can('administrator') ? 'top: 100px;' : 'top: 70px;'; ?>
            left: 1rem;
            right: 1rem;
            z-index: 999;
            pointer-events: none;
        }
        .clp-float .clp--floating {
            transform: translateY(-40%);
            opacity: 0;
            box-shadow: 0 10px 30px rgba(0,0,0,.18);
            transition: transform .35s cubic-bezier(.22,1,.36,1), opacity .3s ease;
        }
        .clp-float.is-visible .clp--floating {
            transform: translateY(0);
            opacity: 1;
            pointer-events: auto;
        }
    }
    </style>
    <?php endif; ?>

    <script>
    (function () {
        var uid    = <?php echo wp_json_encode( $uid ); ?>;
        var slug   = <?php echo wp_json_encode( $slug ); ?>;
        var WS_URL = 'wss://market-pulse-ws.khanes.app/ws/currency/price/' + slug.toUpperCase();

        var root      = document.getElementById( uid );
        var inlineEl  = root.querySelector( '[data-clp-role="inline"]' );
        var floatWrap = root.querySelector( '[data-clp-role="float-wrap"]' );

        var priceEls   = root.querySelectorAll( '.clp__price' );
        var pctEls     = root.querySelectorAll( '.clp__change-pct' );
        var changeEls  = root.querySelectorAll( '.clp__change' );
        var arrowPaths = root.querySelectorAll( '.clp__arrow-path' );
        var dotEls     = root.querySelectorAll( '.clp__live-dot' );

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

        function applyChange( pct ) {
            var isUp = pct >= 0;
            var pctText = ( isUp ? '+' : '' ) + pct.toFixed( 2 ) + '%';
            var arrowD = isUp
                ? 'M4.5 19.5l15-15m0 0H8.25m11.25 0v11.25'
                : 'M19.5 4.5l-15 15m0 0h11.25m-11.25 0V8.25';

            each( changeEls, function ( el ) { el.classList.toggle( 'clp__change--down', ! isUp ); } );
            each( pctEls, function ( el ) { el.textContent = pctText; } );
            each( arrowPaths, function ( el ) { el.setAttribute( 'd', arrowD ); } );
        }

        // Reconnect if no message arrives in 60 s (handles silent server drops)
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

            // change_24h from the API is an absolute toman delta, not a percent.
            if ( typeof msg.change_24h === 'number' && price ) {
                applyChange( ( msg.change_24h / price ) * 100 );
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

        // Mobile only: once the inline card scrolls out of view above the
        // viewport, slide the fixed floating bar in; scrolling back to the
        // top hides it again and reveals the inline card in its place.
        if ( floatWrap && typeof IntersectionObserver !== 'undefined' ) {
            var observer = new IntersectionObserver( function ( entries ) {
                var entry = entries[ 0 ];
                var scrolledPast = ! entry.isIntersecting && entry.boundingClientRect.top < 0;
                floatWrap.classList.toggle( 'is-visible', scrolledPast );
            } );
            observer.observe( inlineEl );
        }

        // Disconnect cleanly if widget is removed from DOM
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
