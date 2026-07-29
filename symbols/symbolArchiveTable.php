<?php
/**
 * Symbols Archive Table Shortcode
 * Usage:
 *   - [symbols_archive_table] (Shows both Gold and Currency tables side-by-side)
 *   - [symbols_archive_table type="gold"] (Shows Gold table only)
 *   - [symbols_archive_table type="currency"] (Shows Currency table only)
 *   - [symbols_archive_table show_title="false"] (Hides table titles)
 *   - Automatically detects category archive pages (hides default title on category pages so you can add titles in WP/Elementor)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'symbols_archive_table', 'render_symbols_archive_table' );

function render_symbols_archive_table( $atts ) {
    $atts = shortcode_atts( [
        'type'       => '', // 'gold', 'currency', or empty
        'category'   => '', // alias for type
        'title'      => '', // optional custom title override
        'show_title' => '', // 'true', 'false', '1', '0'
    ], $atts );

    // 1. Define asset type groups
    $groups = [
        'gold' => [
            'title'   => 'نرخ لحظه‌ای طلا، سکه و طلای آبشده',
            'header'  => 'طلا و سکه',
            'symbols' => []
        ],
        'currency' => [
            'title'   => 'قیمت لحظه‌ای دلار و ارزهای آزاد (آپدیت امروز)',
            'header'  => 'ارز',
            'symbols' => []
        ],
    ];

    // 2. Determine filter type & category page detection
    $is_category_page = false;
    $filter_type = ! empty( $atts['type'] ) ? strtolower( trim( $atts['type'] ) ) : strtolower( trim( $atts['category'] ) );

    if ( empty( $filter_type ) ) {
        if ( is_tax( 'symbol-category' ) ) {
            $term = get_queried_object();
            if ( $term && ! empty( $term->slug ) ) {
                $filter_type = ( $term->slug === 'gold' ) ? 'gold' : 'currency';
                $is_category_page = true;
            }
        } elseif ( is_category() ) {
            $cat = get_queried_object();
            if ( $cat && ! empty( $cat->slug ) ) {
                if ( $cat->slug === 'gold' ) {
                    $filter_type = 'gold';
                    $is_category_page = true;
                } elseif ( in_array( $cat->slug, [ 'currencies', 'currency' ], true ) ) {
                    $filter_type = 'currency';
                    $is_category_page = true;
                }
            }
        }
    }

    if ( $filter_type === 'currencies' ) {
        $filter_type = 'currency';
    }

    // Determine whether to display the h3 title
    $show_title = true;
    if ( strtolower( (string)$atts['show_title'] ) === 'false' || (string)$atts['show_title'] === '0' ) {
        $show_title = false;
    } elseif ( $is_category_page && empty( $atts['show_title'] ) ) {
        $show_title = false;
    }

    // 3. Query WP symbol posts for custom names and permalinks
    $wp_symbols = [];
    $args = [
        'post_type'      => 'symbol',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
    ];
    $query = new WP_Query($args);
    
    if ($query->have_posts()) {
        foreach ($query->posts as $p) {
            $slug = strtolower($p->post_name);
            $fa_name = get_post_meta($p->ID, 'fa_name', true);
            if (empty($fa_name)) {
                $fa_name = $p->post_title;
            }
            $wp_symbols[$slug] = [
                'slug' => $p->post_name,
                'name' => $fa_name,
                'url'  => get_permalink($p->ID)
            ];
        }
    }

    // 4. Pre-fetch models from API (cached for 5 minutes)
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

    if ( ! empty( $api_models ) && is_array( $api_models ) ) {
        foreach ( $api_models as $model ) {
            if ( empty( $model['symbol'] ) ) continue;
            $slug = strtolower( $model['symbol'] );
            $asset_type = ( isset( $model['asset_type'] ) && $model['asset_type'] === 'gold' ) ? 'gold' : 'currency';

            $fa_name = isset( $wp_symbols[$slug] ) ? $wp_symbols[$slug]['name'] : ( ! empty( $model['name_fa'] ) ? $model['name_fa'] : $model['name'] );
            $url     = isset( $wp_symbols[$slug] ) ? $wp_symbols[$slug]['url'] : home_url( '/' . $slug );
            $icon    = ! empty( $model['iconUrl'] ) ? $model['iconUrl'] : '';
            $price        = isset( $model['price_in_toman'] ) ? floatval( $model['price_in_toman'] ) : 0;
            $change       = isset( $model['change_24h'] ) ? floatval( $model['change_24h'] ) : null;
            $change_toman = isset( $model['change_toman'] ) ? floatval( $model['change_toman'] ) : null;

            if ( null === $change_toman && null !== $change && $price > 0 ) {
                $change_toman = round( $price * ( $change / 100 ) );
            }

            $groups[$asset_type]['symbols'][] = [
                'slug'         => $slug,
                'name'         => $fa_name,
                'url'          => $url,
                'icon'         => $icon,
                'price'        => $price,
                'change'       => $change,
                'change_toman' => $change_toman
            ];
        }
    } else {
        // Fallback: group WP symbols by known gold slugs vs currency
        $gold_slugs = ['geram18', 'sekee', 'sekeb', 'nim', 'rob', 'abshodeh', 'gerami', 'sekee_bubbler', 'sekeb_blubber', 'nim_blubber', 'rob_blubber', 'bub_18ayar', 'gerami_blubber', 'ons'];
        foreach ( $wp_symbols as $slug => $item ) {
            $asset_type = in_array( $slug, $gold_slugs, true ) ? 'gold' : 'currency';
            $groups[$asset_type]['symbols'][] = [
                'slug'         => $item['slug'],
                'name'         => $item['name'],
                'url'          => $item['url'],
                'icon'         => '',
                'price'        => 0,
                'change'       => null,
                'change_toman' => null
            ];
        }
    }

    // Filter $groups if $filter_type is active
    if ( ! empty( $filter_type ) && isset( $groups[$filter_type] ) ) {
        $selected_group = $groups[$filter_type];
        if ( ! empty( $atts['title'] ) ) {
            $selected_group['title'] = esc_html( $atts['title'] );
        }
        $groups = [ $filter_type => $selected_group ];
    }

    $uid = 'sat_' . uniqid();

    $render_icon = function( $icon_url, $asset_type, $name ) {
        if ( ! empty( $icon_url ) ) {
            $no_radius = ( $asset_type === 'currency' ) ? 'sat-icon--no-radius' : '';
            return '<img src="' . esc_url( $icon_url ) . '" alt="' . esc_attr( $name ) . '" class="sat-icon ' . $no_radius . '" width="20" height="14" loading="lazy" />';
        }
        if ( $asset_type === 'gold' ) {
            $gid = 'gold_grad_' . uniqid();
            return '<svg class="sat-icon sat-icon--gold" width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
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
        return '<svg class="sat-icon sat-icon--no-radius" width="20" height="14" viewBox="0 0 20 14" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect width="20" height="14" fill="#CBD5E1"/>
            <circle cx="10" cy="7" r="3" stroke="#475569" stroke-width="1.5"/>
        </svg>';
    };

    ob_start();
    ?>
    <style>
        .sat-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem 1rem;
            direction: rtl;
        }
        @media (min-width: 992px) {
            .sat-grid { grid-template-columns: <?php echo count($groups) === 1 ? '1fr' : 'repeat(2, 1fr)'; ?>; }
        }

        .sat-category-box {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.03);
            border: 1px solid #f1f5f9;
            padding: 1.25rem;
            display: flex;
            flex-direction: column;
        }
        .sat-category-title {
            font-size: 1.15rem;
            font-weight: 800;
            color: #0f172a;
            margin-top: 0.25rem;
            margin-bottom: 1.25rem;
            text-align: center;
        }
        .sat-category-title::before {
            display: none !important;
        }

        .sat-table-wrap {
            overflow-x: auto;
            flex: 1;
        }
        .sat-table {
            width: 100%;
            border-collapse: collapse;
            text-align: right;
            font-size: 0.85rem;
            margin: 0;
        }
        .sat-table th {
            font-size: 0.75rem;
            color: #64748b;
            padding: 0.6rem 0.75rem;
            font-weight: 700;
            background: #f8fafc;
            border: 1px solid #f1f5f9 !important;
            white-space: nowrap;
        }
        .sat-table th:first-child {
            text-align: right;
        }
        .sat-table th:nth-child(2),
        .sat-table th:nth-child(3) {
            text-align: center;
        }

        .sat-table td {
            padding: 0.4rem;
            border: 1px solid #f1f5f9 !important;
            color: #334155;
            vertical-align: middle;
        }
    
        .sat-table tbody tr {
            transition: background 0.3s ease;
        }
        .sat-table tbody tr:hover {
            background: #f8fafc;
        }
        
        @keyframes sat-pulse-up {
            0%   { background-color: rgba(16, 185, 129, 0.25); }
            100% { background-color: transparent; }
        }
        @keyframes sat-pulse-down {
            0%   { background-color: rgba(239, 68, 68, 0.25); }
            100% { background-color: transparent; }
        }
        .sat-pulse-up {
            animation: sat-pulse-up 1s ease-out;
        }
        .sat-pulse-down {
            animation: sat-pulse-down 1s ease-out;
        }

        .sat-item-link {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none !important;
            color: #0f172a;
            font-weight: 600;
            transition: color 0.2s;
        }
        .sat-item-link:hover {
            color: #3b82f6;
        }
        .sat-icon {
            width: 20px;
            height: 14px;
            object-fit: cover;
            border-radius: 3px;
            flex-shrink: 0;
            display: inline-block;
            vertical-align: middle;
            border: 1px solid rgb(128 128 128 / 0.11) !important;
        }
        .sat-icon--gold {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: none !important;
        }
        .sat-icon--no-radius {
            border-radius: 0 !important;
        }

        .sat-price {
            font-weight: 700;
            font-family: monospace, inherit;
            font-size: 0.92rem;
            color: #0f172a;
            text-align: center;
        }
        .sat-change-cell {
            text-align: center;
            white-space: nowrap;
        }
        .sat-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.25rem;
            padding: 0.3rem 0.6rem;
            border-radius: 6px;
            font-size: 0.78rem;
            font-weight: 700;
            direction: ltr;
            white-space: nowrap;
        }
        .sat-badge--up {
            background: #dcfce7;
            color: #15803d;
        }
        .sat-badge--down {
            background: #fee2e2;
            color: #b91c1c;
        }
        .sat-badge--neutral {
            background: #f1f5f9;
            color: #64748b;
        }

        /* Mobile Responsive Scaling */
        @media (max-width: 768px) {
            .sat-category-box {
                padding: 0.75rem 0.35rem !important;
                border-radius: 12px !important;
            }
            .sat-category-title {
                font-size: 0.98rem !important;
                margin-bottom: 0.75rem !important;
            }
            .sat-table th {
                font-size: 0.68rem !important;
                padding: 0.4rem 0.25rem !important;
            }
            .sat-table td {
                padding: 0.35rem 0.2rem !important;
            }
            .sat-item-link {
                gap: 0.25rem !important;
                font-size: 0.73rem !important;
                white-space: nowrap !important;
            }
            .sat-item-link span {
                white-space: nowrap !important;
            }
            .sat-price {
                font-size: 0.75rem !important;
                padding-left: 0.15rem !important;
                padding-right: 0.15rem !important;
                white-space: nowrap !important;
            }
            .sat-badge {
                font-size: 0.65rem !important;
                padding: 0.2rem 0.3rem !important;
                gap: 0.15rem !important;
                border-radius: 4px !important;
                white-space: nowrap !important;
            }
            .sat-icon {
                width: 16px !important;
                height: 11px !important;
            }
            .sat-icon--gold {
                width: 16px !important;
                height: 16px !important;
            }
        }

        /* Pagination Styles */
        .sat-pagination {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.3rem;
            margin-top: 1rem;
            padding-top: 0.75rem;
            border-top: 1px solid #f1f5f9;
        }
        .sat-page-btn {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            color: #475569;
            min-width: 28px;
            height: 28px;
            padding: 0 6px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .sat-page-btn:hover {
            border-color: #cbd5e1;
            background: #f8fafc;
            color: #0f172a;
        }
        .sat-page-btn.is-active {
            background: #3b82f6;
            border-color: #3b82f6;
            color: #ffffff;
        }
        .sat-category-box[data-type="gold"] .sat-page-btn.is-active {
            background: #d97706;
            border-color: #d97706;
        }
        .sat-page-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }
    </style>

    <div class="sat-grid" id="<?php echo esc_attr($uid); ?>">
        <?php foreach ( $groups as $type => $group ) : ?>
            <div class="sat-category-box" data-type="<?php echo esc_attr($type); ?>">
                <?php if ( $show_title && ! empty( $group['title'] ) ) : ?>
                    <h3 class="sat-category-title"><?php echo esc_html($group['title']); ?></h3>
                <?php endif; ?>
                
                <div class="sat-table-wrap">
                    <table class="sat-table" data-type="<?php echo esc_attr($type); ?>">
                        <thead>
                            <tr>
                                <th><?php echo esc_html($group['header']); ?></th>
                                <th>قیمت (تومان)</th>
                                <th>تغییر (۲۴ ساعته)</th>
                            </tr>
                        </thead>
                        <tbody data-role="tbody">
                            <?php 
                            $initial_items = array_slice($group['symbols'], 0, 16);
                            foreach ($initial_items as $sym) : 
                                $price_str = $sym['price'] > 0 ? number_format($sym['price'], 0, '', '٫') : '---';
                                
                                $chg_class = 'sat-badge--neutral';
                                $chg_badge_text = '▲ (۰,۰۰٪)';
                                
                                if (null !== $sym['change']) {
                                    $abs_pct = number_format(abs($sym['change']), 2, '٫', '');
                                    $toman_str = '';
                                    if (null !== $sym['change_toman'] && $sym['change_toman'] != 0) {
                                        $toman_str = ' ' . number_format(abs($sym['change_toman']), 0, '', '٫');
                                    }
                                    if ($sym['change'] > 0) {
                                        $chg_class = 'sat-badge--up';
                                        $chg_badge_text = '▲ (' . $abs_pct . '٪)' . $toman_str;
                                    } elseif ($sym['change'] < 0) {
                                        $chg_class = 'sat-badge--down';
                                        $chg_badge_text = '▼ (' . $abs_pct . '٪)' . $toman_str;
                                    }
                                }
                            ?>
                                <tr class="sat-row" data-slug="<?php echo esc_attr($sym['slug']); ?>">
                                    <td>
                                        <a href="<?php echo esc_url($sym['url']); ?>" class="sat-item-link">
                                            <?php echo $render_icon($sym['icon'], $type, $sym['name']); ?>
                                            <span><?php echo esc_html($sym['name']); ?></span>
                                        </a>
                                    </td>
                                    <td class="sat-price" data-role="price"><?php echo esc_html($price_str); ?></td>
                                    <td class="sat-change-cell">
                                        <div class="sat-badge <?php echo esc_attr($chg_class); ?>" data-role="badge">
                                            <?php echo esc_html($chg_badge_text); ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="sat-pagination" data-role="pagination" data-type="<?php echo esc_attr($type); ?>"></div>
            </div>
        <?php endforeach; ?>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var wrapper = document.getElementById('<?php echo esc_js($uid); ?>');
        if (!wrapper) return;

        var allGroups = <?php echo wp_json_encode($groups); ?>;
        var wpSymbolsMap = <?php echo wp_json_encode($wp_symbols); ?>;
        var homeUrl = '<?php echo esc_js(home_url('/')); ?>';
        var itemsPerPage = 16;
        var currentPage = { gold: 1, currency: 1 };
        var websockets = {};

        function fmtNumFa(n, decimals) {
            if (n === null || n === undefined || isNaN(n)) return '---';
            return new Intl.NumberFormat('fa-IR', { 
                maximumFractionDigits: decimals !== undefined ? decimals : 0,
                minimumFractionDigits: decimals !== undefined ? decimals : 0 
            }).format(n);
        }

        function getIconHtml(iconUrl, assetType, name) {
            if (iconUrl) {
                var noRadius = (assetType === 'currency') ? ' sat-icon--no-radius' : '';
                return '<img src="' + iconUrl + '" alt="' + name + '" class="sat-icon' + noRadius + '" width="20" height="14" loading="lazy" />';
            }
            if (assetType === 'gold') {
                return '<svg class="sat-icon sat-icon--gold" width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">' +
                    '<circle cx="12" cy="12" r="9" fill="url(#sat-gold-grad-js)" stroke="#D97706" stroke-width="1.5"/>' +
                    '<path d="M12 7V17M9 9.5C9 8.5 10.2 7.5 12 7.5C13.8 7.5 15 8.5 15 9.5C15 11 13 11.5 12 12C11 12.5 9 13 9 14.5C9 15.5 10.2 16.5 12 16.5C13.8 16.5 15 15.5 15 14.5" stroke="#92400E" stroke-width="1.5" stroke-linecap="round"/>' +
                    '<defs>' +
                        '<linearGradient id="sat-gold-grad-js" x1="3" y1="3" x2="21" y2="21" gradientUnits="userSpaceOnUse">' +
                            '<stop stop-color="#FDE047"/>' +
                            '<stop offset="0.5" stop-color="#EAB308"/>' +
                            '<stop offset="1" stop-color="#CA8A04"/>' +
                        '</linearGradient>' +
                    '</defs>' +
                '</svg>';
            }
            return '<svg class="sat-icon sat-icon--no-radius" width="20" height="14" viewBox="0 0 20 14" fill="none" xmlns="http://www.w3.org/2000/svg">' +
                '<rect width="20" height="14" fill="#CBD5E1"/>' +
                '<circle cx="10" cy="7" r="3" stroke="#475569" stroke-width="1.5"/>' +
            '</svg>';
        }

        function buildBadgeText(change, changeToman) {
            if (change === null || change === undefined) return '▲ (۰,۰۰٪)';
            var absPct = fmtNumFa(Math.abs(change), 2);
            var tomanStr = '';
            if (changeToman !== null && changeToman !== undefined && changeToman !== 0) {
                tomanStr = ' ' + fmtNumFa(Math.abs(changeToman), 0);
            }
            if (change > 0) return '▲ (' + absPct + '٪)' + tomanStr;
            if (change < 0) return '▼ (' + absPct + '٪)' + tomanStr;
            return '▲ (۰,۰۰٪)';
        }

        function renderTablePage(type, page) {
            var group = allGroups[type];
            if (!group || !group.symbols) return;

            currentPage[type] = page;
            var start = (page - 1) * itemsPerPage;
            var end = start + itemsPerPage;
            var pageItems = group.symbols.slice(start, end);

            var tbody = wrapper.querySelector('.sat-table[data-type="' + type + '"] tbody');
            if (!tbody) return;

            var html = '';
            pageItems.forEach(function(sym) {
                var priceStr = sym.price > 0 ? fmtNumFa(sym.price, 0) : '---';
                var chgClass = 'sat-badge--neutral';

                if (sym.change !== null && sym.change !== undefined) {
                    if (sym.change > 0) chgClass = 'sat-badge--up';
                    else if (sym.change < 0) chgClass = 'sat-badge--down';
                }

                var badgeText = buildBadgeText(sym.change, sym.change_toman);
                var iconHtml = getIconHtml(sym.icon, type, sym.name);

                html += '<tr class="sat-row" data-slug="' + sym.slug + '">';
                html += '  <td>';
                html += '    <a href="' + sym.url + '" class="sat-item-link">';
                html +=        iconHtml;
                html += '      <span>' + sym.name + '</span>';
                html += '    </a>';
                html += '  </td>';
                html += '  <td class="sat-price" data-role="price">' + priceStr + '</td>';
                html += '  <td class="sat-change-cell">';
                html += '    <div class="sat-badge ' + chgClass + '" data-role="badge">' + badgeText + '</div>';
                html += '  </td>';
                html += '</tr>';

                // Connect WebSocket for visible page items
                subscribeWS(sym.slug);
            });

            tbody.innerHTML = html;
            renderPaginationControls(type);
        }

        function renderPaginationControls(type) {
            var group = allGroups[type];
            if (!group || !group.symbols) return;

            var totalPages = Math.ceil(group.symbols.length / itemsPerPage);
            var pagContainer = wrapper.querySelector('.sat-pagination[data-type="' + type + '"]');
            if (!pagContainer) return;

            if (totalPages <= 1) {
                pagContainer.innerHTML = '';
                return;
            }

            var curr = currentPage[type];
            var html = '';

            // Prev Button
            html += '<button class="sat-page-btn" data-action="prev" ' + (curr === 1 ? 'disabled' : '') + '>❮</button>';

            for (var i = 1; i <= totalPages; i++) {
                var activeClass = (i === curr) ? ' is-active' : '';
                html += '<button class="sat-page-btn' + activeClass + '" data-page="' + i + '">' + fmtNumFa(i) + '</button>';
            }

            // Next Button
            html += '<button class="sat-page-btn" data-action="next" ' + (curr === totalPages ? 'disabled' : '') + '>❯</button>';

            pagContainer.innerHTML = html;

            pagContainer.querySelectorAll('.sat-page-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    if (this.disabled) return;
                    var targetPage = curr;

                    if (this.dataset.action === 'prev') {
                        targetPage = Math.max(1, curr - 1);
                    } else if (this.dataset.action === 'next') {
                        targetPage = Math.min(totalPages, curr + 1);
                    } else if (this.dataset.page) {
                        targetPage = parseInt(this.dataset.page, 10);
                    }

                    if (targetPage !== curr) {
                        renderTablePage(type, targetPage);
                    }
                });
            });
        }

        // Live REST API Fetch to refresh items list and prices
        fetch('https://market-pulse.khanes.app/api/v2/currencies?per_page=500')
            .then(function(res) { return res.json(); })
            .then(function(res) {
                if (res && res.data && Array.isArray(res.data.models)) {
                    var newGroups = {
                        gold: { title: allGroups.gold ? allGroups.gold.title : '', header: allGroups.gold ? allGroups.gold.header : '', symbols: [] },
                        currency: { title: allGroups.currency ? allGroups.currency.title : '', header: allGroups.currency ? allGroups.currency.header : '', symbols: [] }
                    };

                    res.data.models.forEach(function(model) {
                        if (!model.symbol) return;
                        var slug = model.symbol.toLowerCase();
                        var assetType = (model.asset_type === 'gold') ? 'gold' : 'currency';

                        var wpInfo = wpSymbolsMap[slug] || {};
                        var name = wpInfo.name || model.name_fa || model.name || slug;
                        var url = wpInfo.url || (homeUrl + slug);
                        var price = parseFloat(model.price_in_toman) || 0;
                        var change = model.change_24h !== undefined && model.change_24h !== null ? parseFloat(model.change_24h) : null;
                        var changeToman = model.change_toman !== undefined && model.change_toman !== null ? parseFloat(model.change_toman) : null;

                        if (changeToman === null && change !== null && price > 0) {
                            changeToman = Math.round(price * (change / 100));
                        }

                        if (newGroups[assetType]) {
                            newGroups[assetType].symbols.push({
                                slug: slug,
                                name: name,
                                url: url,
                                icon: model.iconUrl || '',
                                price: price,
                                change: change,
                                change_toman: changeToman
                            });
                        }
                    });

                    // Retain old groups if API returned empty
                    Object.keys(allGroups).forEach(function(type) {
                        if (newGroups[type] && newGroups[type].symbols.length > 0) {
                            allGroups[type].symbols = newGroups[type].symbols;
                        }
                    });

                    // Re-render visible pages
                    Object.keys(allGroups).forEach(function(type) {
                        renderTablePage(type, currentPage[type] || 1);
                    });
                }
            })
            .catch(function(err) { console.error('SAT API fetch error', err); });

        // Initial render of pagination for pre-fetched SSR items
        Object.keys(allGroups).forEach(function(type) {
            renderPaginationControls(type);
        });

        // WebSocket Stream Subscription Helper
        function subscribeWS(slug) {
            if (websockets[slug]) return; // already connected

            var wsUrl = 'wss://market-pulse-ws.khanes.app/ws/currency/price/' + slug.toUpperCase();
            var ws = new WebSocket(wsUrl);

            ws.onmessage = function(event) {
                try {
                    var data = JSON.parse(event.data);
                    if (data.type === 'price_update') {
                        updateSymbolRow(slug, data);
                    }
                } catch (e) {
                    console.error('WS parse error', e);
                }
            };

            ws.onclose = function() {
                delete websockets[slug];
            };

            ws.onerror = function() {
                ws.close();
            };

            websockets[slug] = ws;
        }

        function updateSymbolRow(slug, data) {
            var rows = wrapper.querySelectorAll('tr[data-slug="' + slug + '"]');
            if (!rows.length) return;

            var newPrice = parseFloat(data.price_in_toman) || 0;
            var newChange = data.change_24h !== undefined ? parseFloat(data.change_24h) : null;
            var newChangeToman = data.change_toman !== undefined ? parseFloat(data.change_toman) : null;

            if (newChangeToman === null && newChange !== null && newPrice > 0) {
                newChangeToman = Math.round(newPrice * (newChange / 100));
            }

            rows.forEach(function(row) {
                var priceEl = row.querySelector('[data-role="price"]');
                var badgeEl = row.querySelector('[data-role="badge"]');

                if (priceEl && newPrice > 0) {
                    var oldPriceText = priceEl.textContent.trim();
                    var newPriceText = fmtNumFa(newPrice, 0);

                    if (oldPriceText !== newPriceText) {
                        priceEl.textContent = newPriceText;

                        // Pulse animation
                        var isUp = true;
                        if (oldPriceText !== '---') {
                            var oldVal = parseFloat(oldPriceText.replace(/[٫,]/g, '')) || 0;
                            if (newPrice < oldVal) isUp = false;
                        }
                        row.classList.remove('sat-pulse-up', 'sat-pulse-down');
                        void row.offsetWidth; // trigger reflow
                        row.classList.add(isUp ? 'sat-pulse-up' : 'sat-pulse-down');
                    }
                }

                if (badgeEl) {
                    var chgClass = 'sat-badge--neutral';
                    if (newChange !== null && newChange > 0) chgClass = 'sat-badge--up';
                    else if (newChange !== null && newChange < 0) chgClass = 'sat-badge--down';

                    badgeEl.className = 'sat-badge ' + chgClass;
                    badgeEl.textContent = buildBadgeText(newChange, newChangeToman);
                }
            });
        }

        // Connect WS for initial 16 items of each visible page
        Object.keys(allGroups).forEach(function(type) {
            if (allGroups[type] && allGroups[type].symbols) {
                var items = allGroups[type].symbols.slice(0, itemsPerPage);
                items.forEach(function(sym) {
                    subscribeWS(sym.slug);
                });
            }
        });
    });
    </script>
    <?php
    return ob_get_clean();
}
