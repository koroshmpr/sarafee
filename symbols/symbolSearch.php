<?php
/**
 * Symbols Search Shortcode
 * Usage: [symbols_search]
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'symbols_search', 'render_symbols_search_shortcode' );

function render_symbols_search_shortcode( $atts ) {
    // Query WP symbol posts for custom names and permalinks
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
                'name' => $fa_name,
                'url'  => get_permalink($p->ID)
            ];
        }
    }

    $uid = 'ss_' . uniqid();
    ob_start();
    ?>
    <style>
        .ss-wrapper {
            position: relative;
            width: 100% !important;
            max-width: 100% !important;
            direction: rtl !important;
            font-family: inherit !important;
            box-sizing: border-box !important;
        }
        .ss-input-box {
            position: relative;
            display: flex;
            align-items: center;
            width: 100% !important;
            box-sizing: border-box !important;
        }
        .ss-input {
            width: 100% !important;
            padding: 1rem 3.5rem 1rem 1.5rem !important;
            border-radius: 15px !important;
            border: 1px solid #e2e8f0 !important;
            font-size: 1rem !important;
            outline: none !important;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03) !important;
            transition: all 0.3s !important;
            background: #fff !important;
            color: #0f172a !important;
            box-sizing: border-box !important;
            line-height: 1.5 !important;
            height: auto !important;
        }
        .ss-input:focus {
            border-color: #3b82f6 !important;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15) !important;
        }
        .ss-input::placeholder {
            color: #94a3b8 !important;
        }
        .ss-search-icon {
            position: absolute;
            right: 1.2rem;
            color: #94a3b8;
            pointer-events: none;
        }
        .ss-results {
            position: absolute;
            top: calc(100% + 8px);
            left: 0;
            right: 0;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            max-height: 350px;
            overflow-y: auto;
            z-index: 100;
            display: none;
            border: 1px solid #f1f5f9;
        }
        .ss-result-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.85rem 1.25rem;
            border-bottom: 1px solid #f8fafc;
            text-decoration: none;
            color: #0f172a;
            transition: background 0.2s;
        }
        .ss-result-item:last-child {
            border-bottom: none;
        }
        .ss-result-item:hover {
            background: #f8fafc;
        }
        .ss-result-item:hover .ss-result-name {
            color: #3b82f6;
        }
        .ss-result-left {
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        .ss-icon-img {
            width: 20px;
            height: 14px;
            object-fit: cover;
            border-radius: 3px;
            flex-shrink: 0;
            display: inline-block;
            vertical-align: middle;
        }
        .ss-icon-img--gold {
            width: 20px;
            height: 20px;
            border-radius: 50%;
        }
        .ss-icon-img--no-radius {
            border-radius: 0 !important;
        }
        .ss-result-name {
            font-weight: 700;
            font-size: 0.95rem;
            transition: color 0.2s;
        }
        .ss-result-slug {
            font-size: 0.75rem;
            color: #94a3b8;
            text-transform: uppercase;
            background: #f1f5f9;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
        }
        .ss-loading {
            padding: 1.5rem;
            text-align: center;
            color: #64748b;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        .ss-spinner {
            width: 1rem;
            height: 1rem;
            border: 2px solid #e2e8f0;
            border-top-color: #3b82f6;
            border-radius: 50%;
            animation: ss-spin 1s linear infinite;
        }
        @keyframes ss-spin {
            to { transform: rotate(360deg); }
        }
        .ss-no-results {
            padding: 1.5rem;
            text-align: center;
            color: #ef4444;
            font-size: 0.9rem;
        }
    </style>

    <div class="ss-wrapper" id="<?php echo esc_attr($uid); ?>">
        <div class="ss-input-box">
            <svg class="ss-search-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
            <input type="text" class="ss-input" placeholder="جستجوی ارز یا نماد..." autocomplete="off">
        </div>
        <div class="ss-results"></div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var wrapper = document.getElementById('<?php echo esc_js($uid); ?>');
        if (!wrapper) return;
        
        var input = wrapper.querySelector('.ss-input');
        var resultsBox = wrapper.querySelector('.ss-results');
        var wpSymbolsMap = <?php echo wp_json_encode($wp_symbols); ?> || {};
        var homeUrl = '<?php echo esc_js(home_url('/')); ?>';
        var debounceTimer;
        var currentSearchController = null;

        function getIconHtml(iconUrl, assetType, name) {
            if (iconUrl) {
                var noRadius = (assetType === 'currency') ? ' ss-icon-img--no-radius' : '';
                return '<img src="' + iconUrl + '" alt="' + name + '" class="ss-icon-img' + noRadius + '" width="20" height="14" loading="lazy" />';
            }
            if (assetType === 'gold') {
                return '<svg class="ss-icon-img ss-icon-img--gold" width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">' +
                    '<circle cx="12" cy="12" r="9" fill="url(#ss-gold-grad-js)" stroke="#D97706" stroke-width="1.5"/>' +
                    '<path d="M12 7V17M9 9.5C9 8.5 10.2 7.5 12 7.5C13.8 7.5 15 8.5 15 9.5C15 11 13 11.5 12 12C11 12.5 9 13 9 14.5C9 15.5 10.2 16.5 12 16.5C13.8 16.5 15 15.5 15 14.5" stroke="#92400E" stroke-width="1.5" stroke-linecap="round"/>' +
                    '<defs>' +
                        '<linearGradient id="ss-gold-grad-js" x1="3" y1="3" x2="21" y2="21" gradientUnits="userSpaceOnUse">' +
                            '<stop stop-color="#FDE047"/>' +
                            '<stop offset="0.5" stop-color="#EAB308"/>' +
                            '<stop offset="1" stop-color="#CA8A04"/>' +
                        '</linearGradient>' +
                    '</defs>' +
                '</svg>';
            }
            return '<svg class="ss-icon-img ss-icon-img--no-radius" width="20" height="14" viewBox="0 0 20 14" fill="none" xmlns="http://www.w3.org/2000/svg">' +
                '<rect width="20" height="14" fill="#CBD5E1"/>' +
                '<circle cx="10" cy="7" r="3" stroke="#475569" stroke-width="1.5"/>' +
            '</svg>';
        }

        function performSearch(q) {
            if (currentSearchController) {
                currentSearchController.abort();
            }
            if (typeof AbortController !== 'undefined') {
                currentSearchController = new AbortController();
            }

            var apiUrl = 'https://market-pulse.khanes.app/api/v2/currencies?search=' + encodeURIComponent(q);
            var options = currentSearchController ? { signal: currentSearchController.signal } : {};

            fetch(apiUrl, options)
                .then(function(res) { return res.json(); })
                .then(function(res) {
                    if (res && res.data && Array.isArray(res.data.models) && res.data.models.length > 0) {
                        var html = '';
                        res.data.models.forEach(function(item) {
                            var slug = (item.symbol || '').toLowerCase();
                            var assetType = item.asset_type === 'gold' ? 'gold' : 'currency';
                            var wpInfo = wpSymbolsMap[slug] || {};
                            var name = wpInfo.name || item.name_fa || item.name || slug;
                            var url = wpInfo.url || (homeUrl + slug);
                            var iconHtml = getIconHtml(item.iconUrl, assetType, name);

                            html += '<a href="' + url + '" class="ss-result-item">';
                            html += '  <div class="ss-result-left">';
                            html +=      iconHtml;
                            html += '    <span class="ss-result-name">' + name + '</span>';
                            html += '  </div>';
                            html += '  <span class="ss-result-slug">' + slug + '</span>';
                            html += '</a>';
                        });
                        resultsBox.innerHTML = html;
                    } else {
                        resultsBox.innerHTML = '<div class="ss-no-results">نتیجه‌ای یافت نشد.</div>';
                    }
                })
                .catch(function(err) {
                    if (err.name === 'AbortError') return;
                    resultsBox.innerHTML = '<div class="ss-no-results">خطا در دریافت اطلاعات.</div>';
                });
        }

        input.addEventListener('input', function() {
            var val = this.value.trim();
            if (val.length < 2) {
                resultsBox.style.display = 'none';
                return;
            }
            
            clearTimeout(debounceTimer);
            resultsBox.style.display = 'block';
            resultsBox.innerHTML = '<div class="ss-loading"><div class="ss-spinner"></div> در حال جستجو...</div>';
            
            debounceTimer = setTimeout(function() {
                performSearch(val);
            }, 300);
        });
        
        // Hide when clicking outside
        document.addEventListener('click', function(e) {
            if (!wrapper.contains(e.target)) {
                resultsBox.style.display = 'none';
            }
        });
        
        // Show again when clicking input if there's text
        input.addEventListener('click', function() {
            if (this.value.trim().length >= 2 && resultsBox.innerHTML !== '') {
                resultsBox.style.display = 'block';
            }
        });
    });
    </script>
    <?php
    return ob_get_clean();
}

// AJAX Handler (Server-side API search fallback)
add_action('wp_ajax_search_symbols_ajax', 'handle_search_symbols_ajax');
add_action('wp_ajax_nopriv_search_symbols_ajax', 'handle_search_symbols_ajax');

function handle_search_symbols_ajax() {
    $q = isset($_POST['q']) ? sanitize_text_field($_POST['q']) : '';
    if (empty($q)) {
        wp_send_json_error();
    }

    $response = wp_remote_get( 'https://market-pulse.khanes.app/api/v2/currencies?search=' . urlencode($q), [ 'timeout' => 5 ] );
    $results = [];

    if ( ! is_wp_error( $response ) ) {
        $body = wp_remote_retrieve_body( $response );
        $json = json_decode( $body, true );
        if ( ! empty( $json['data']['models'] ) && is_array( $json['data']['models'] ) ) {
            foreach ( $json['data']['models'] as $model ) {
                $slug = isset($model['symbol']) ? strtolower($model['symbol']) : '';
                $name_fa = isset($model['name_fa']) ? $model['name_fa'] : '';
                $name_en = isset($model['name']) ? $model['name'] : '';

                $results[] = [
                    'name'       => esc_html( ! empty($name_fa) ? $name_fa : $name_en ),
                    'slug'       => esc_html( $slug ),
                    'url'        => esc_url( home_url( '/' . $slug ) ),
                    'icon'       => ! empty($model['iconUrl']) ? esc_url($model['iconUrl']) : '',
                    'asset_type' => isset($model['asset_type']) ? $model['asset_type'] : 'currency'
                ];
            }
        }
    }

    wp_send_json_success($results);
}
