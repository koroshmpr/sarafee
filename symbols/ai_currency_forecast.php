<?php
/**
 * Shortcode 2: AI Price Forecast Chart & Table
 * Usage: [ai_currency_forecast]
 */
add_shortcode( 'ai_currency_forecast', 'sarafee_ai_forecast_shortcode' );
function sarafee_ai_forecast_shortcode() {
    global $post;
    if ( ! $post ) return '';

    // دریافت آرایه قیمت‌ها
    $future_prices = get_post_meta( $post->ID, 'future_prices', true );

    if ( empty( $future_prices ) ) {
        $feature_price = get_post_meta( $post->ID, 'feature_price', true );
        $decoded = is_string( $feature_price ) ? json_decode( $feature_price, true ) : $feature_price;
        $future_prices = $decoded['future_prices'] ?? [];
    }

    // اطمینان از ساختار JSON برای جاوا اسکریپت
    $forecast_json = is_string( $future_prices ) ? $future_prices : wp_json_encode( $future_prices );

    if ( empty( $forecast_json ) || $forecast_json == '[]' ) return '';

    // لود کردن اسکریپت نمودار اصلی شما (در صورت نیاز)
    if ( function_exists( 'ncc_enqueue_lightweight_charts' ) ) {
        ncc_enqueue_lightweight_charts();
    }

    $uid = 'ai_forecast_' . uniqid();
    ob_start();
    ?>
    <div class="sarafee-ai-card sarafee-forecast-wrapper" id="<?php echo esc_attr( $uid ); ?>" data-forecast="<?php echo esc_attr( $forecast_json ); ?>">
        <div class="sarafee-ai-header">
            <div class="sarafee-ai-title">
                <span class="sarafee-ai-icon">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941"></path>
                    </svg>
                </span>
                <h3>پیش‌بینی ۷ روز آینده</h3>
            </div>
        </div>

        <div class="sarafee-ai-chart-wrap">
            <div class="sarafee-ai-chart"></div>
            <div class="sarafee-ai-tooltip" hidden></div>
        </div>

        <div class="sarafee-ai-grid">
            <!-- جاوا اسکریپت این بخش را پر می‌کند -->
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            var wrapper = document.getElementById("<?php echo $uid; ?>");
            if (!wrapper) return;

            var forecastData = [];
            try {
                forecastData = JSON.parse(wrapper.getAttribute("data-forecast"));
            } catch (e) { return; }

            if (!forecastData.length) return;

            // 1. Build grid immediately so layout is visible
            var gridEl = wrapper.querySelector(".sarafee-ai-grid");
            var gridHTML = '';
            forecastData.forEach(function(item) {
                var trendClass = 'trend-neutral';
                var trendIcon = '<path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12h15"/>';
                if (item.trend.includes("صعودی")) {
                    trendClass = 'trend-up';
                    trendIcon = '<path stroke-linecap="round" stroke-linejoin="round" d="M4.5 19.5l15-15m0 0H8.25m11.25 0v11.25"/>';
                } else if (item.trend.includes("نزولی")) {
                    trendClass = 'trend-down';
                    trendIcon = '<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 4.5l-15 15m0 0h11.25m-11.25 0V8.25"/>';
                }

                gridHTML += `
                <div class="sarafee-ai-day">
                    <span class="day-name">${item.day}</span>
                    <span class="day-price">${item.display_price.replace(' تومان', '')}</span>
                    <span class="day-trend ${trendClass}">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">${trendIcon}</svg>
                    </span>
                </div>
            `;
            });
            gridEl.innerHTML = gridHTML;

            // 2. Initialize chart when LightweightCharts is loaded
            var retries = 0;
            function initChart() {
                if (!window.LightweightCharts) {
                    if (retries < 100) {
                        retries++;
                        setTimeout(initChart, 50);
                    }
                    return;
                }

                // ساخت داده‌های زمانی فیک برای نمودار (از فردا شروع می‌شود)
                var now = Math.floor(Date.now() / 1000);
                var chartData = forecastData.map(function(item, index) {
                    return {
                        time: now + ((index + 1) * 86400),
                        value: item.chart_price,
                        dayLabel: item.day,
                        displayPrice: item.display_price
                    };
                });

                var chartEl = wrapper.querySelector(".sarafee-ai-chart");
                var tooltipEl = wrapper.querySelector(".sarafee-ai-tooltip");
                if (tooltipEl) {
                    tooltipEl.style.display = 'none';
                }

                var chart = LightweightCharts.createChart(chartEl, {
                    autoSize: true,
                    layout: { background: { type: 'solid', color: 'transparent' }, textColor: '#888', fontFamily: 'inherit', attributionLogo: false },
                    grid: { vertLines: { visible: false }, horzLines: { visible: false } },
                    rightPriceScale: { visible: false, borderVisible: false },
                    leftPriceScale: { visible: false, borderVisible: false },
                    timeScale: { visible: false, borderVisible: false },
                    crosshair: { 
                        mode: LightweightCharts.CrosshairMode.Normal, 
                        vertLine: { width: 1, style: LightweightCharts.LineStyle.Dashed, color: 'rgba(139, 92, 246, 0.3)', labelVisible: false }, 
                        horzLine: { visible: false, labelVisible: false } 
                    },
                    handleScroll: false, handleScale: false,
                });

                var series = chart.addSeries(LightweightCharts.AreaSeries, {
                    lineColor: '#8b5cf6',
                    topColor: 'rgba(139, 92, 246, 0.25)',
                    bottomColor: 'rgba(139, 92, 246, 0)',
                    lineWidth: 2.5,
                    priceLineVisible: false,
                    lastValueVisible: false,
                    crosshairMarkerRadius: 6,
                    crosshairMarkerBorderColor: '#ffffff',
                    crosshairMarkerBackgroundColor: '#8b5cf6',
                    crosshairMarkerBorderWidth: 2,
                });

                series.setData(chartData);
                chart.timeScale().fitContent();

                chart.subscribeCrosshairMove(function(param) {
                    var priceData = param.time && param.seriesData ? param.seriesData.get(series) : null;
                    if (!param.point || !priceData) { 
                        if (tooltipEl) tooltipEl.style.display = 'none'; 
                        return; 
                    }

                    var match = chartData.find(c => c.time === param.time);

                    if (tooltipEl) {
                        tooltipEl.style.display = 'flex';
                        tooltipEl.innerHTML = '<span class="sarafee-ai-tooltip-date">' + (match ? match.dayLabel : '') + '</span>' +
                            '<span class="sarafee-ai-tooltip-price">' + (match ? match.displayPrice : priceData.value) + '</span>';
                        var left = Math.min(Math.max(param.point.x, 60), chartEl.clientWidth - 60);
                        tooltipEl.style.left = left + 'px';
                        tooltipEl.style.top = Math.max(param.point.y - 12, 0) + 'px';
                    }
                });
            }

            initChart();
        });
    </script>
    <?php
    return ob_get_clean();
}