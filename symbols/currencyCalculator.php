<?php
/**
 * Dynamic Currency Calculator Shortcode for single symbol page or widgets
 * Usage: [currency_calculator]
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'currency_calculator', 'render_currency_calculator' );

function render_currency_calculator( $atts ) {
    global $post;

    $atts = shortcode_atts( [
        'from' => '',
        'to'   => 'toman',
    ], $atts );

    // If on a single symbol page, use its slug as default 'from'
    if ( empty( $atts['from'] ) && $post && $post->post_type === 'symbol' ) {
        $atts['from'] = $post->post_name;
    }
    if ( empty( $atts['from'] ) ) {
        $atts['from'] = 'usd';
    }

    $symbols_posts = get_posts( [
        'post_type'      => 'symbol',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
    ] );

    $symbols = [];

    // 1. Toman pseudo-symbol
    $symbols['toman'] = [
        'slug'      => 'toman',
        'name'      => 'تومان',
        'code'      => 'تومان',
        'icon'      => 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="%2300b894"><circle cx="12" cy="12" r="10" fill="%23e6f9f5"/><text x="12" y="16" font-family="Arial" font-size="11" font-weight="bold" fill="%2300b894" text-anchor="middle">ت</text></svg>',
        'is_crypto' => false
    ];

    // 2. Loop symbols from DB
    foreach ( $symbols_posts as $sp ) {
        $slug = $sp->post_name;
        $logo = get_the_post_thumbnail_url( $sp->ID, 'thumbnail' );
        
        // Custom check for crypto
        $is_crypto = false;
        if ( function_exists( 'get_field' ) ) {
            $is_crypto = get_field( 'is_crypto', $sp->ID ) ? true : false;
        }
        
        // Generate SVG fallback icon with the first letter of symbol name if no featured image
        if ( ! $logo ) {
            $char = mb_substr( $sp->post_title, 0, 1, 'utf-8' );
            $logo = 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><circle cx="12" cy="12" r="10" fill="%23f1f2f6"/><text x="12" y="16" font-family="Arial" font-size="10" fill="%232f3542" text-anchor="middle">' . $char . '</text></svg>';
        }

        $clean_title = $sp->post_title;
        // Clean long SEO titles to get short name
        $parts = preg_split( '/[|:|–|-]/', $clean_title );
        $clean_title = trim( $parts[0] );
        if ( mb_strpos( $clean_title, 'قیمت ' ) === 0 ) {
            $clean_title = trim( mb_substr( $clean_title, 5 ) );
        }
        if ( mb_substr( $clean_title, -6 ) === ' امروز' ) {
            $clean_title = trim( mb_substr( $clean_title, 0, -6 ) );
        }

        // Get custom ACF field if configured
        $fa_name = function_exists( 'get_field' ) ? get_field( 'fa_name', $sp->ID ) : '';
        $display_name = ! empty( $fa_name ) ? trim( $fa_name ) : $clean_title;

        $symbols[$slug] = [
            'slug'      => $slug,
            'name'      => $display_name,
            'code'      => strtoupper( $slug ),
            'icon'      => $logo,
            'is_crypto' => $is_crypto
        ];
    }

    $uid = 'cc_' . uniqid();
    ob_start();
    ?>
    <div class="cc-widget" id="<?php echo esc_attr( $uid ); ?>" dir="rtl">
        <h2 class="cc-widget__title">ماشین حساب تبدیل ارز</h2>

        <div class="cc-widget__container">
            <!-- Row 1: FROM -->
            <div class="cc-widget__row">
                <div class="cc-widget__select-wrap">
                    <button type="button" class="cc-widget__select-btn" data-role="from-btn">
                        <img src="" alt="" class="cc-widget__icon" data-role="from-icon">
                        <span class="cc-widget__code" data-role="from-code">---</span>
                        <i class="fas fa-chevron-down cc-widget__chevron"></i>
                    </button>
                    <div class="cc-widget__dropdown" data-role="from-dropdown" hidden>
                        <div class="cc-widget__search-wrap">
                            <input type="text" placeholder="جستجو..." class="cc-widget__search" data-role="from-search">
                        </div>
                        <ul class="cc-widget__list">
                            <?php foreach ( $symbols as $sym ) : ?>
                                <li class="cc-widget__item" data-slug="<?php echo esc_attr( $sym['slug'] ); ?>">
                                    <img src="<?php echo esc_attr( $sym['icon'] ); ?>" alt="" class="cc-widget__item-icon">
                                    <span class="cc-widget__item-name"><?php echo esc_html( $sym['name'] ); ?></span>
                                    <span class="cc-widget__item-code"><?php echo esc_html( $sym['code'] ); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <input aria-label="from change price input" type="number" class="cc-widget__input" value="1" step="any" min="0" data-role="from-input">
            </div>

            <!-- Swap Button -->
            <div class="cc-widget__swap-wrap">
                <button type="button" class="cc-widget__swap-btn" data-role="swap-btn" aria-label="swap currencies">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                        <path d="M16 17.01V10h-2v7.01h-3L15 21l4-3.99h-3zM9 3L5 6.99h3V14h2V6.99h3L9 3z"/>
                    </svg>
                </button>
            </div>

            <!-- Row 2: TO -->
            <div class="cc-widget__row">
                <div class="cc-widget__select-wrap">
                    <button type="button" class="cc-widget__select-btn" data-role="to-btn">
                        <img src="" alt="" class="cc-widget__icon" data-role="to-icon">
                        <span class="cc-widget__code" data-role="to-code">---</span>
                        <i class="fas fa-chevron-down cc-widget__chevron"></i>
                    </button>
                    <div class="cc-widget__dropdown" data-role="to-dropdown" hidden>
                        <div class="cc-widget__search-wrap">
                            <input type="text" placeholder="جستجو..." class="cc-widget__search" data-role="to-search">
                        </div>
                        <ul class="cc-widget__list">
                            <?php foreach ( $symbols as $sym ) : ?>
                                <li class="cc-widget__item" data-slug="<?php echo esc_attr( $sym['slug'] ); ?>">
                                    <img src="<?php echo esc_attr( $sym['icon'] ); ?>" alt="" class="cc-widget__item-icon">
                                    <span class="cc-widget__item-name"><?php echo esc_html( $sym['name'] ); ?></span>
                                    <span class="cc-widget__item-code"><?php echo esc_html( $sym['code'] ); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <input aria-label="to change price input" type="number" class="cc-widget__input" value="" step="any" min="0" data-role="to-input">
            </div>

            <!-- Conversion Info Cards -->
            <div class="cc-widget__info-panel" data-role="info-panel" hidden>
                <h4 class="cc-widget__info-title" data-role="summary-heading">هر ۱ ... معادل ... ...</h4>
                
                <div class="cc-widget__summary-boxes">
                    <div class="cc-widget__summary-box">
                        <span class="cc-widget__summary-label">ارزش به تومان:</span>
                        <span class="cc-widget__summary-value" data-role="summary-toman">---</span>
                    </div>
                    <div class="cc-widget__summary-box">
                        <span class="cc-widget__summary-label">نرخ تبدیل:</span>
                        <span class="cc-widget__summary-value" data-role="summary-rate">---</span>
                    </div>
                </div>

                <!-- Live rates comparison table -->
                <table class="cc-widget__table">
                    <tbody>
                        <tr data-row="from-usd">
                            <td class="cc-widget__table-label">قیمت امروز <span data-role="lbl-from">---</span> به دلار:</td>
                            <td class="cc-widget__table-value" data-role="val-from-usd">---</td>
                            <td class="cc-widget__table-change" data-role="chg-from-usd">---</td>
                        </tr>
                        <tr data-row="from-toman">
                            <td class="cc-widget__table-label">قیمت امروز <span data-role="lbl-from">---</span> به تومان:</td>
                            <td class="cc-widget__table-value" data-role="val-from-toman">---</td>
                            <td class="cc-widget__table-change" data-role="chg-from-toman">---</td>
                        </tr>
                        <tr data-row="to-usd">
                            <td class="cc-widget__table-label">قیمت امروز <span data-role="lbl-to">---</span> به دلار:</td>
                            <td class="cc-widget__table-value" data-role="val-to-usd">---</td>
                            <td class="cc-widget__table-change" data-role="chg-to-usd">---</td>
                        </tr>
                        <tr data-row="to-toman">
                            <td class="cc-widget__table-label">قیمت امروز <span data-role="lbl-to">---</span> به تومان:</td>
                            <td class="cc-widget__table-value" data-role="val-to-toman">---</td>
                            <td class="cc-widget__table-change" data-role="chg-to-toman">---</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <!-- Promotion / Action Button -->
            <div class="cc-widget__promo">
                <button type="button" class="cc-widget__promo-btn openPorsline">
                    استعلام ارز از صرافی‌ها
                </button>
            </div>
        </div>
    </div>

    <style>
    .cc-widget {
        background: #f5f8fc;
        border-radius: 24px;
        padding: 30px;
        font-family: inherit;
        box-shadow: 0 10px 30px rgba(0,0,0,0.02);
        box-sizing: border-box;
    }
    .cc-widget__title {
        text-align: center;
        font-size: 20px;
        font-weight: 700;
        margin-bottom: 24px;
        color: #1e293b;
    }
    .cc-widget__container {
        display: flex;
        flex-direction: column;
        gap: 16px;
        position: relative;
    }
    .cc-widget__row {
        display: flex;
        align-items: center;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 8px 16px;
        height: 64px;
        box-sizing: border-box;
        position: relative;
        transition: border-color 0.2s ease;
    }
    .cc-widget__row:focus-within {
        border-color: #0fbcf9;
    }
    .cc-widget__select-wrap {
        position: relative;
        flex-shrink: 0;
        border-left: 1px solid #e2e8f0;
        padding-left: 16px;
        margin-left: 16px;
        display: flex;
        align-items: center;
    }
    .cc-widget__select-btn {
        background: none;
        border: none;
        padding: 0;
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        font-family: inherit;
        font-size: 16px;
        font-weight: 700;
        color: #1e293b;
        width: 110px;
        text-align: right;
    }
    .cc-widget__icon {
        width: 28px;
        height: 28px!important;
        object-fit: contain;
        border-radius: 50%!important;
    }
    .cc-widget__chevron {
        font-size: 11px;
        color: #64748b;
        margin-right: auto;
    }
    .cc-widget__input {
        border: none;
        background: none;
        width: 100%;
        font-size: 20px;
        font-weight: 700;
        color: #1e293b;
        text-align: left;
        padding: 0;
        margin: 0;
        outline: none;
        font-family: monospace;
        direction: ltr;
    }
    .cc-widget__input::-webkit-outer-spin-button,
    .cc-widget__input::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }
    .cc-widget__input[type=number] {
        -moz-appearance: textfield;
    }
    .cc-widget__swap-wrap {
        display: flex;
        justify-content: center;
        position: absolute;
        right: 28px;
        top: 48px;
        z-index: 10;
    }
    .cc-widget__swap-btn {
        background: #00b894;
        color: #ffffff;
        border: 4px solid #f5f8fc;
        width: 42px;
        height: 42px;
        padding:0;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 4px 10px rgba(0,184,148,0.3);
        transition: transform 0.3s ease;
    }
    .cc-widget__swap-btn:hover {
        transform: rotate(180deg);
    }
    .cc-widget__dropdown {
        position: absolute;
        top: 100%;
        right: 0;
        z-index: 100;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.08);
        width: 250px;
        max-height: 280px;
        overflow-y: auto;
        margin-top: 8px;
    }
    .cc-widget__search-wrap {
        padding: 8px 12px;
        border-bottom: 1px solid #f1f5f9;
        position: sticky;
        top: 0;
        background: #ffffff;
    }
    .cc-widget__search {
        width: 100%;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        padding: 6px 10px;
        font-size: 13px;
        font-family: inherit;
        outline: none;
    }
    .cc-widget__list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .cc-widget__item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 12px;
        cursor: pointer;
        transition: background 0.15s ease;
    }
    .cc-widget__item:hover {
        background: #f8fafc;
    }
    .cc-widget__item-icon {
        width: 24px;
        height: 24px;
        object-fit: contain;
        border-radius: 50%;
    }
    .cc-widget__item-name {
        font-size: 13px;
        font-weight: 500;
        color: #334155;
    }
    .cc-widget__item-code {
        font-size: 11px;
        font-weight: 600;
        color: #94a3b8;
        margin-right: auto;
    }
    .cc-widget__promo {
        margin-top: 8px;
        display: flex;
        justify-content: center;
    }
    .cc-widget__promo-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: #00b894;
        color: #ffffff;
        text-decoration: none !important;
        font-size: 14px;
        font-weight: 600;
        padding: 12px 24px;
        border-radius: 12px;
        transition: background 0.2s ease;
        border: none;
        cursor: pointer;
        font-family: inherit;
    }
    .cc-widget__promo-btn:hover {
        background: #009c7d;
    }
    .cc-widget__promo-badge {
        background: rgba(255,255,255,0.25);
        color: #ffffff;
        font-size: 9px;
        font-weight: 700;
        padding: 2px 5px;
        border-radius: 4px;
    }
    .cc-widget__info-panel {
        background: #ffffff;
        border-radius: 16px;
        padding: 20px;
        margin-top: 10px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.01);
    }
    .cc-widget__info-title {
        text-align: center;
        font-size: 18px;
        font-weight: 700;
        color: #1e293b;
        margin-top: 0;
        margin-bottom: 20px;
    }
    .cc-widget__summary-boxes {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        margin-bottom: 20px;
    }
    .cc-widget__summary-box {
        background: #f8fafc;
        border: 1px solid #f1f5f9;
        border-radius: 12px;
        padding: 12px;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 4px;
    }
    .cc-widget__summary-label {
        font-size: 12px;
        color: #64748b;
    }
    .cc-widget__summary-value {
        font-size: 16px;
        font-weight: 700;
        color: #0f172a;
    }
    .cc-widget__table {
        width: 100%;
        border-collapse: collapse;
    }
    .cc-widget__table td {
        padding: 10px 0;
        border: 1px solid #d6d9dcff;
        font-size: 13px;
        color: #334155;
        text-align:center;
    }
    .cc-widget__table tr:last-child td {
        border-bottom: none;
    }
    .cc-widget__table-value {
        font-weight: 700;
        color: #0f172a;
        text-align: left;
        padding-left: 10px !important;
    }
    .cc-widget__table-change {
        font-weight: 600;
        text-align: left;
        width: 70px;
    }
    .cc-widget__change--up {
        color: #10b981;
    }
    .cc-widget__change--down {
        color: #ef4444;
    }
    @media all and (max-width:720px) {
        .cc-widget {
           padding: 20px 12px;
        }
        .cc-widget__table td {
            padding: 6px 4px !important;
            font-size: 11px !important;
            border: none;
            border-bottom: 1px solid #e2e8f0;
        }
        .cc-widget__table tr:last-child td {
            border-bottom: none;
        }
        .cc-widget__table-label {
            padding: 6px 4px !important;
            text-align: right;
        }
        .cc-widget__table-value {
            padding-left: 4px !important;
        }
    }
    </style>

    <script>
    (function() {
        const uid = <?php echo wp_json_encode( $uid ); ?>;
        const symbols = <?php echo wp_json_encode( $symbols ); ?>;
        const defaultFrom = <?php echo wp_json_encode( $atts['from'] ); ?>;
        const defaultTo = <?php echo wp_json_encode( $atts['to'] ); ?>;

        const widget = document.getElementById(uid);
        
        // Element references
        const fromBtn = widget.querySelector('[data-role="from-btn"]');
        const toBtn = widget.querySelector('[data-role="to-btn"]');
        const fromDropdown = widget.querySelector('[data-role="from-dropdown"]');
        const toDropdown = widget.querySelector('[data-role="to-dropdown"]');
        const fromSearch = widget.querySelector('[data-role="from-search"]');
        const toSearch = widget.querySelector('[data-role="to-search"]');
        const fromInput = widget.querySelector('[data-role="from-input"]');
        const toInput = widget.querySelector('[data-role="to-input"]');
        const swapBtn = widget.querySelector('[data-role="swap-btn"]');
        
        const infoPanel = widget.querySelector('[data-role="info-panel"]');
        const summaryHeading = widget.querySelector('[data-role="summary-heading"]');
        const summaryToman = widget.querySelector('[data-role="summary-toman"]');
        const summaryRate = widget.querySelector('[data-role="summary-rate"]');
        
        let state = {
            from: defaultFrom,
            to: defaultTo,
            prices: { toman: 1, usd: 0 },
            changes: { toman: 0, usd: 0 },
            websockets: {}
        };

        // Initialize selectors
        updateButtonUI('from', state.from);
        updateButtonUI('to', state.to);

        // Open ws for default USD (since we need USD price for converting to dollar values)
        subscribeToWS('usd');
        subscribeToWS(state.from);
        subscribeToWS(state.to);

        // Handle dropdown search
        setupSearch(fromSearch, fromDropdown);
        setupSearch(toSearch, toDropdown);

        // Event listeners
        fromBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            toDropdown.hidden = true;
            fromDropdown.hidden = !fromDropdown.hidden;
        });

        toBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            fromDropdown.hidden = true;
            toDropdown.hidden = !toDropdown.hidden;
        });

        document.addEventListener('click', () => {
            fromDropdown.hidden = true;
            toDropdown.hidden = true;
        });

        // Set select items
        fromDropdown.querySelectorAll('.cc-widget__item').forEach(item => {
            item.addEventListener('click', () => {
                const slug = item.dataset.slug;
                changeCurrency('from', slug);
            });
        });

        toDropdown.querySelectorAll('.cc-widget__item').forEach(item => {
            item.addEventListener('click', () => {
                const slug = item.dataset.slug;
                changeCurrency('to', slug);
            });
        });

        // Input changes
        fromInput.addEventListener('input', () => {
            recalculate('from');
        });

        toInput.addEventListener('input', () => {
            recalculate('to');
        });

        // Swap button
        swapBtn.addEventListener('click', () => {
            const oldFrom = state.from;
            state.from = state.to;
            state.to = oldFrom;
            
            updateButtonUI('from', state.from);
            updateButtonUI('to', state.to);

            const oldVal = parseFloat(fromInput.value) || 0;
            const newVal = parseFloat(toInput.value) || 0;
            fromInput.value = newVal || 1;
            
            recalculate('from');
        });

        function changeCurrency(role, slug) {
            if (role === 'from') {
                state.from = slug;
                updateButtonUI('from', slug);
                subscribeToWS(slug);
            } else {
                state.to = slug;
                updateButtonUI('to', slug);
                subscribeToWS(slug);
            }
            recalculate('from');
        }

        function updateButtonUI(role, slug) {
            const sym = symbols[slug];
            if (!sym) return;

            widget.querySelector(`[data-role="${role}-icon"]`).src = sym.icon;
            widget.querySelector(`[data-role="${role}-code"]`).textContent = sym.code;
        }

        function setupSearch(input, dropdown) {
            input.addEventListener('click', (e) => e.stopPropagation());
            input.addEventListener('input', () => {
                const q = input.value.toLowerCase();
                dropdown.querySelectorAll('.cc-widget__item').forEach(item => {
                    const name = item.querySelector('.cc-widget__item-name').textContent.toLowerCase();
                    const code = item.querySelector('.cc-widget__item-code').textContent.toLowerCase();
                    item.style.display = (name.includes(q) || code.includes(q)) ? 'flex' : 'none';
                });
            });
        }

        function subscribeToWS(slug) {
            if (slug === 'toman') return;
            if (state.websockets[slug]) return; // already subscribed

            const wsUrl = `wss://market-pulse-ws.khanes.app/ws/currency/price/${slug.toUpperCase()}`;
            const ws = new WebSocket(wsUrl);

            ws.onmessage = (event) => {
                try {
                    const msg = JSON.parse(event.data);
                    if (msg.type === 'price_update') {
                        state.prices[slug] = parseFloat(msg.price_in_toman) || 0;
                        state.changes[slug] = parseFloat(msg.change_24h) || 0;

                        recalculate('from');
                    }
                } catch (e) {
                    console.error(e);
                }
            };

            ws.onclose = () => {
                delete state.websockets[slug];
                setTimeout(() => subscribeToWS(slug), 3000);
            };

            ws.onerror = () => ws.close();

            state.websockets[slug] = ws;
        }

        function fmtNum(n, decimals = 0) {
            return new Intl.NumberFormat('fa-IR', { maximumFractionDigits: decimals }).format(n);
        }

        function fmtTomanFriendly(toman) {
            if (toman >= 1000000000) {
                return (toman / 1000000000).toFixed(1) + ' میلیارد ت';
            }
            if (toman >= 1000000) {
                return (toman / 1000000).toFixed(1) + ' میلیون ت';
            }
            return fmtNum(toman) + ' ت';
        }

        function recalculate(triggerRole = 'from') {
            const pFrom = state.prices[state.from] || 0;
            const pTo = state.prices[state.to] || 0;

            if (pFrom <= 0 || pTo <= 0) return;

            const rate = pFrom / pTo;

            if (triggerRole === 'from') {
                const val = parseFloat(fromInput.value) || 0;
                toInput.value = (val * rate).toFixed(6).replace(/\.?0+$/, "");
            } else {
                const val = parseFloat(toInput.value) || 0;
                fromInput.value = (val / rate).toFixed(6).replace(/\.?0+$/, "");
            }

            updateInfoPanel(rate);
        }

        function updateInfoPanel(rate) {
            infoPanel.hidden = false;

            const fromSym = symbols[state.from];
            const toSym = symbols[state.to];
            
            let rateStr;
            if (rate > 100) {
                rateStr = fmtNum(rate, 2);
            } else if (rate > 1) {
                rateStr = rate.toFixed(4).replace(/\.?0+$/, "");
            } else if (rate > 0.0001) {
                rateStr = rate.toFixed(6).replace(/\.?0+$/, "");
            } else {
                rateStr = rate.toFixed(10).replace(/\.?0+$/, "");
            }

            summaryHeading.textContent = `هر ۱ ${fromSym.name} معادل ${rateStr} ${toSym.name}`;

            const valueInToman = state.prices[state.from] || 0;
            summaryToman.textContent = fmtTomanFriendly(valueInToman);
            summaryRate.textContent = `${rateStr} ${toSym.code}`;

            // Update details table
            updateTableRow('from', state.from);
            updateTableRow('to', state.to);
        }

        function updateTableRow(role, slug) {
            const sym = symbols[slug];
            widget.querySelectorAll(`[data-role="lbl-${role}"]`).forEach(el => el.textContent = sym.name);

            const rowUsd = widget.querySelector(`tr[data-row="${role}-usd"]`);
            const rowToman = widget.querySelector(`tr[data-row="${role}-toman"]`);

            if (slug === 'toman') {
                rowUsd.hidden = true;
                rowToman.hidden = true;
                return;
            }

            rowUsd.hidden = false;
            rowToman.hidden = false;

            const priceToman = state.prices[slug] || 0;
            const priceUsdRate = state.prices['usd'] || 1;
            const priceUsd = priceToman / priceUsdRate;

            const valUsdEl = widget.querySelector(`[data-role="val-${role}-usd"]`);
            const valTomEl = widget.querySelector(`[data-role="val-${role}-toman"]`);
            const chgUsdEl = widget.querySelector(`[data-role="chg-${role}-usd"]`);
            const chgTomEl = widget.querySelector(`[data-role="chg-${role}-toman"]`);

            let usdStr;
            if (priceUsd > 100) {
                usdStr = fmtNum(priceUsd);
            } else if (priceUsd > 1) {
                usdStr = priceUsd.toFixed(4).replace(/\.?0+$/, "");
            } else if (priceUsd > 0.0001) {
                usdStr = priceUsd.toFixed(6).replace(/\.?0+$/, "");
            } else {
                usdStr = priceUsd.toFixed(10).replace(/\.?0+$/, "");
            }

            valUsdEl.textContent = `$${usdStr}`;
            valTomEl.textContent = `${fmtNum(priceToman)} ت`;

            // Change 24h calculations
            const change24h = state.changes[slug] || 0;
            const changePct = priceToman ? (change24h / priceToman) * 100 : 0;
            
            const pctText = (changePct >= 0 ? '+' : '') + changePct.toFixed(2) + '%';
            
            [chgUsdEl, chgTomEl].forEach(el => {
                el.textContent = pctText;
                el.className = 'cc-widget__table-change ' + (changePct >= 0 ? 'cc-widget__change--up' : 'cc-widget__change--down');
            });
        }

    })();
    </script>
    <?php
    return ob_get_clean();
}
