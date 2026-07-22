<?php
/**
 * Custom currency card shortcode.
 * Usage: [currency_card] or [currency_card slug="gbp"]
 */
add_shortcode( 'currency_card', 'render_custom_currency_card' );

/**
 * Loads TradingView Lightweight Charts (self-hosted, ~45KB gzipped) once per
 * page and attaches the shared init/runtime script that powers every card.
 */
function ncc_enqueue_lightweight_charts() {
    static $done = false;
    if ( $done ) {
        return;
    }
    $done = true;

    wp_enqueue_script(
        'lightweight-charts',
        get_stylesheet_directory_uri() . '/assets/js/lightweight-charts.standalone.production.js',
        [],
        '5.2.0',
        true
    );

    wp_add_inline_script( 'lightweight-charts', ncc_runtime_js() );
}

function ncc_runtime_js() {
    ob_start();
    ?>
    (function () {
        if ( window.__nccInit ) return;
        window.__nccInit = true;

        var API = 'https://market-pulse.khanes.app/api/v2/currencies/ohlc/';
        var PERIOD_DAYS = { '1d': 2, '1w': 7, '1m': 30, '6m': 182, '1y': 365, '5y': 1825 };

        function fmt( n ) {
            return new Intl.NumberFormat( 'fa-IR' ).format( Math.round( n ) );
        }
        function fmtDate( t ) {
            return new Intl.DateTimeFormat( 'fa-IR-u-ca-persian', { day: 'numeric', month: 'long', year: 'numeric' } )
                .format( new Date( t * 1000 ) );
        }

        function initCard( card ) {
            if ( card.__nccDone ) return;
            card.__nccDone = true;

            var slug      = card.dataset.slug;
            var priceEl   = card.querySelector( '.ncc__price' );
            var pctEl     = card.querySelector( '.ncc__change-pct' );
            var changeEl  = card.querySelector( '.ncc__change' );
            var arrowPath = card.querySelector( '.ncc__change-arrow path' );
            var chartEl   = card.querySelector( '.ncc__chart' );
            var tooltipEl = card.querySelector( '.ncc__tooltip' );
            var loaderEl  = card.querySelector( '.ncc__chart-loader' );
            var btns      = card.querySelectorAll( '.ncc__period' );

            var chart = LightweightCharts.createChart( chartEl, {
                autoSize: true,
                layout: {
                    background: { type: 'solid', color: 'transparent' },
                    textColor: '#888',
                    fontFamily: 'inherit',
                    attributionLogo: false,
                },
                grid: {
                    vertLines: { visible: false },
                    horzLines: { visible: false },
                },
                rightPriceScale: { visible: false },
                leftPriceScale: { visible: false },
                timeScale: { visible: false, borderVisible: false },
                crosshair: {
                    mode: LightweightCharts.CrosshairMode.Normal,
                    vertLine: { width: 1, style: LightweightCharts.LineStyle.Dashed, color: '#d1d5db', labelVisible: false },
                    horzLine: { visible: false, labelVisible: false },
                },
                handleScroll: false,
                handleScale: false,
            } );

            var series = chart.addSeries( LightweightCharts.AreaSeries, {
                lineWidth: 2,
                priceLineVisible: false,
                lastValueVisible: false,
                crosshairMarkerRadius: 5,
                crosshairMarkerBorderColor: '#fff',
                crosshairMarkerBorderWidth: 2,
            } );

            function applyDir( isUp ) {
                changeEl.className = 'ncc__change' + ( isUp ? '' : ' ncc__change--down' );
                arrowPath.setAttribute( 'd', isUp
                    ? 'M4.5 19.5l15-15m0 0H8.25m11.25 0v11.25'
                    : 'M19.5 4.5l-15 15m0 0h11.25m-11.25 0V8.25'
                );
                series.applyOptions( {
                    lineColor:   isUp ? '#10b981' : '#ef4444',
                    topColor:    isUp ? 'rgba(16,185,129,.25)' : 'rgba(239,68,68,.25)',
                    bottomColor: isUp ? 'rgba(16,185,129,0)'   : 'rgba(239,68,68,0)',
                } );
            }

            var fullHistory = [];

            function renderPeriod( period ) {
                var days = PERIOD_DAYS[ period ] || fullHistory.length;
                var rows = fullHistory.slice( -days );
                if ( rows.length < 2 && fullHistory.length >= 2 ) rows = fullHistory;

                if ( ! rows.length ) {
                    priceEl.textContent = '---';
                    pctEl.textContent   = '-';
                    series.setData( [] );
                    return;
                }

                series.setData( rows.map( function ( r ) {
                    return { time: r.timestamp, value: r.close };
                } ) );
                chart.timeScale().fitContent();

                var first = rows[ 0 ].open || rows[ 0 ].close;
                var last  = rows[ rows.length - 1 ].close;
                var pct   = ( ( last - first ) / first ) * 100;

                priceEl.textContent = fmt( last );
                pctEl.textContent   = ( pct >= 0 ? '+' : '' ) + pct.toFixed( 1 ) + '%';
                applyDir( pct >= 0 );
            }

            chart.subscribeCrosshairMove( function ( param ) {
                var priceData = param.time && param.seriesData ? param.seriesData.get( series ) : null;
                if ( ! param.point || ! priceData ) {
                    tooltipEl.hidden = true;
                    return;
                }
                tooltipEl.hidden = false;
                tooltipEl.innerHTML =
                    '<span class="ncc__tooltip-date">' + fmtDate( param.time ) + '</span>' +
                    '<span class="ncc__tooltip-price">' + fmt( priceData.value ) + ' تومان</span>';

                var w    = chartEl.clientWidth;
                var left = Math.min( Math.max( param.point.x, 60 ), w - 60 );
                tooltipEl.style.left = left + 'px';
                tooltipEl.style.top  = Math.max( param.point.y - 12, 0 ) + 'px';
            } );

            btns.forEach( function ( btn ) {
                btn.addEventListener( 'click', function () {
                    btns.forEach( function ( b ) { b.classList.remove( 'ncc__period--active' ); } );
                    btn.classList.add( 'ncc__period--active' );
                    renderPeriod( btn.dataset.period );
                } );
            } );

            loaderEl.hidden = false;
            fetch( API + slug )
                .then( function ( r ) { return r.json(); } )
                .then( function ( res ) {
                    var rows = ( res && res.data && Array.isArray( res.data.history ) ) ? res.data.history.slice() : [];
                    rows.sort( function ( a, b ) { return a.timestamp - b.timestamp; } );
                    fullHistory = rows;
                    loaderEl.hidden = true;
                    var activeBtn = card.querySelector( '.ncc__period--active' );
                    renderPeriod( activeBtn ? activeBtn.dataset.period : '1m' );
                } )
                .catch( function ( err ) {
                    loaderEl.hidden = true;
                    console.error( 'Currency chart fetch error:', err );
                } );
        }

        function scan() {
            document.querySelectorAll( '.ncc[data-slug]' ).forEach( initCard );
        }

        if ( document.readyState === 'loading' ) {
            document.addEventListener( 'DOMContentLoaded', scan );
        } else {
            scan();
        }
    })();
    <?php
    return ob_get_clean();
}

function render_custom_currency_card( $atts ) {
    global $post;

    $atts = shortcode_atts( [ 'slug' => '' ], $atts );
    $slug = $atts['slug'] !== '' ? sanitize_key( $atts['slug'] ) : ( $post ? $post->post_name : 'usd' );

    $uid = 'ncc_' . uniqid() . '_' . $slug;

    ncc_enqueue_lightweight_charts();

    // Resolve custom name from symbol post if exists
    $symbol_posts = get_posts([
        'post_type'      => 'symbol',
        'name'           => $slug,
        'posts_per_page' => 1,
        'post_status'    => 'publish'
    ]);
    $symbol_post = ! empty( $symbol_posts ) ? $symbol_posts[0] : null;
    
    $fa_name = '';
    if ( $symbol_post ) {
        if ( function_exists( 'get_field' ) ) {
            $fa_name = get_field( 'fa_name', $symbol_post->ID );
        }
        if ( empty( $fa_name ) ) {
            $raw_title = $symbol_post->post_title;
            $parts = preg_split( '/[|:|–|-]/', $raw_title );
            $fa_name = trim( $parts[0] );
            if ( mb_strpos( $fa_name, 'قیمت ' ) === 0 ) {
                $fa_name = trim( mb_substr( $fa_name, 5 ) );
            }
            if ( mb_substr( $fa_name, -6 ) === ' امروز' ) {
                $fa_name = trim( mb_substr( $fa_name, 0, -6 ) );
            }
        }
    }

    $names = [
        'gbp' => 'پوند',
        'usd' => 'دلار',
        'eur' => 'یورو',
        'aed' => 'درهم',
        'try' => 'لیر',
        'cad' => 'دلار کانادا',
        'aud' => 'دلار استرالیا',
        'chf' => 'فرانک سوئیس',
        'jpy' => 'ین ژاپن',
        'cny' => 'یوان چین',
        'sar' => 'ریال عربستان',
        'sek' => 'کرون سوئد',
    ];
    $currency_name = ! empty( $fa_name ) ? $fa_name : ( $names[ $slug ] ?? strtoupper( $slug ) );

    $periods = [
        // '1d' => '۱ روز',
        '1w' => '۱ هفته',
        '1m' => '۱ ماه',
        '6m' => '۶ ماه',
        '1y' => '۱ سال',
        '5y' => '۵ سال',
    ];

    $symbol_link = '';
    if ( $symbol_post ) {
        if ( ! ( is_singular( 'symbol' ) && get_the_ID() === $symbol_post->ID ) ) {
            $symbol_link = get_permalink( $symbol_post->ID );
        }
    }

    ob_start();
    ?>
    <div class="ncc" id="<?php echo esc_attr( $uid ); ?>" data-slug="<?php echo esc_attr( strtoupper( $slug ) ); ?>" dir="rtl">
        <div class="ncc_data">
            <div class="ncc__header">
                <span class="ncc__icon" aria-hidden="true">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                    </svg>
                </span>
                <h2 class="ncc__title">
                    <?php if ( $symbol_link ) : ?>
                    <a href="<?php echo esc_url( $symbol_link ); ?>">
                    <?php endif; ?>
                    نمودار قیمت <?php echo esc_html( $currency_name ); ?>
                    <?php if ( $symbol_link ) : ?>
                    </a>
                    <?php endif; ?>
                </h2>
            </div>

            <div class="ncc__price-change">
                <div class="ncc__price-row">
                    <span class="ncc__price">···</span>
                    <span class="ncc__unit">تومان</span>
                </div>

                <div class="ncc__change-row">
                    <span class="ncc__change">
                        <svg class="ncc__change-arrow" width="20" height="20" fill="none"
                            stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M4.5 19.5l15-15m0 0H8.25m11.25 0v11.25"/>
                        </svg>
                        <span class="ncc__change-pct">···</span>
                    </span>
                </div>
            </div>
        </div>

        <div class="ncc__chart-wrap">
            <div class="ncc__chart"></div>
            <div class="ncc__tooltip" hidden></div>
            <div class="ncc__chart-loader">···</div>
        </div>

        <div class="ncc__periods">
            <?php foreach ( $periods as $key => $label ) : ?>
            <button class="ncc__period<?php echo $key === '1m' ? ' ncc__period--active' : ''; ?>"
                    data-period="<?php echo esc_attr( $key ); ?>">
                <?php echo esc_html( $label ); ?>
            </button>
            <?php endforeach; ?>
        </div>

    </div>

    <?php
    static $css_printed = false;
    if ( ! $css_printed ) :
        $css_printed = true;
    ?>
    <style>
    .ncc {
        background: #fff;
        border-radius: 2rem;
        padding: 1.5rem 1.5rem 1.25rem;
        border: 1px solid #f0f0f0;
        box-shadow: 0 2px 16px rgba(0,0,0,.05);
        margin: 0 auto;
        font-family: inherit;
        box-sizing: border-box;
    }
    .ncc__chart table , .ncc__chart td {
        border:unset;
    }
    .ncc_data {
        display: flex;
        justify-content: space-between;
        align-items: start;
    }
    .ncc__header {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: .75rem;
        margin-bottom: 1.75rem;
    }
    .ncc__title {
        font-size: 1.0625rem;
        font-weight: 700;
        color: #111827;
        margin: 0;
        padding: 0;
        line-height: 1.3;
    }
    .ncc__title a {
        color: inherit;
        text-decoration: none;
        transition: opacity 0.2s;
    }
    .ncc__title a:hover {
        opacity: 0.8;
    }
    .ncc__icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 2rem;
        height: 2rem;
        border-radius: .75rem;
        background: #f9f9f9;
        border: 1px solid #ececec;
        color: #555;
        flex-shrink: 0;
    }
    .ncc__price-row {
        display: flex;
        align-items: flex-end;
        justify-content: flex-end;
        gap: .5rem;
        margin-bottom: .25rem;
    }
    .ncc__unit {
        font-size: 1.0625rem;
        color: #888;
        font-weight: 500;
        padding-bottom: .5rem;
    }
    .ncc__price {
        font-size: 3.5rem;
        font-weight: 900;
        color: #0A1128;
        letter-spacing: -.02em;
        line-height: 1;
    }
    .ncc__change-row {
        display: flex;
        justify-content: flex-end;
        margin-bottom: 1.75rem;
    }
    .ncc__change {
        display: flex;
        align-items: center;
        gap: .2rem;
        font-size: 1.0625rem;
        font-weight: 700;
        color: #10b981;
    }
    .ncc__change--down { color: #ef4444; }
    .ncc__change-arrow { flex-shrink: 0; }
    .ncc__chart-wrap {
        position: relative;
        width: 100%;
        height: 6.5rem;
        margin-bottom: 1.75rem;
    }
    .ncc__chart { width: 100%; height: 100%; cursor: crosshair; }
    .ncc__tooltip {
        position: absolute;
        top: 0;
        left: 0;
        transform: translate(-50%, -100%);
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: .1rem;
        padding: .4rem .7rem;
        background: #0A1128;
        color: #fff;
        border-radius: .625rem;
        font-size: .75rem;
        line-height: 1.3;
        white-space: nowrap;
        pointer-events: none;
        z-index: 3;
        box-shadow: 0 4px 14px rgba(10,17,40,.25);
    }
    .ncc__tooltip[hidden] { display: none; }
    .ncc__tooltip-date  { color: #9ca3af; font-weight: 500; }
    .ncc__tooltip-price { font-weight: 700; }
    .ncc__chart-loader {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #ccc;
        font-size: 1.5rem;
        letter-spacing: .15em;
        pointer-events: none;
        z-index: 1;
    }
    .ncc__chart-loader[hidden] { display: none; }
    .ncc__periods {
        display: flex;
        gap: .625rem;
        justify-content: space-between;
    }
    .ncc__period {
        flex: 1;
        padding: .625rem 0;
        font-size: .8125rem;
        font-weight: 700;
        border-radius: 1rem;
        border: 1px solid #ececec;
        background: #fff;
        color: #555;
        cursor: pointer;
        transition: background .18s, color .18s, border-color .18s, box-shadow .18s;
        font-family: inherit;
        line-height: 1;
    }
    .ncc__period:hover:not(.ncc__period--active) { background: #f5f5f5; color:#555 }
    .ncc__period--active {
        background: #0A1128;
        color: #fff;
        border-color: #0A1128;
        box-shadow: 0 2px 10px rgba(10,17,40,.22);
    }
    .ncc__period:disabled { opacity: .45; cursor: default; }
       @media (max-width: 960px) {
            .ncc_data {
                flex-direction: column;
                align-items: center;
            }
            .ncc__change-row {
                justify-content: center;
            }
        }
    </style>
    <?php endif; ?>

    <?php
    return ob_get_clean();
}
