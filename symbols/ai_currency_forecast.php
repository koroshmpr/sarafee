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
    static $sarafee_forecast_styles_printed = false;
    if ( !$sarafee_forecast_styles_printed ) {
        $sarafee_forecast_styles_printed = true;
        ?>
        <style>
            :root {
                --ai-primary: #8b5cf6;
                --ai-primary-hover: #7c3aed;
                --ai-primary-glow: rgba(139, 92, 246, 0.15);
                --ai-accent: #ec4899;
                --ai-card-bg: #ffffff;
                --ai-card-border: rgba(139, 92, 246, 0.12);
                --ai-shadow: 0 10px 30px -10px rgba(139, 92, 246, 0.08);
                --ai-shadow-hover: 0 12px 35px -8px rgba(139, 92, 246, 0.15);
                --ai-text-main: #1f2937;
                --ai-text-muted: #6b7280;
                --ai-success: #10b981;
                --ai-danger: #ef4444;
            }

            .sarafee-ai-card {
                background: var(--ai-card-bg);
                border: 1px solid var(--ai-card-border);
                border-radius: 16px;
                padding: 24px;
                margin-bottom: 24px;
                box-shadow: var(--ai-shadow);
                direction: rtl;
                text-align: right;
                position: relative;
                overflow: hidden;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }

            .sarafee-ai-card:hover {
                box-shadow: var(--ai-shadow-hover);
                border-color: rgba(139, 92, 246, 0.25);
            }

            .sarafee-ai-card::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                height: 4px;
                background: linear-gradient(90deg, var(--ai-primary), var(--ai-accent));
            }

            .sarafee-ai-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
                gap: 15px;
            }

            .sarafee-ai-title {
                display: flex;
                align-items: center;
                gap: 12px;
            }

            .sarafee-ai-icon {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 36px;
                height: 36px;
                border-radius: 10px;
                background: var(--ai-primary-glow);
                color: var(--ai-primary);
                flex-shrink: 0;
            }

            .sarafee-ai-icon svg {
                width: 20px;
                height: 20px;
            }

            .sarafee-ai-title h3 {
                margin: 0 !important;
                font-size: 1.15rem;
                font-weight: 700;
                color: var(--ai-text-main);
            }

            /* Forecast-specific Styles */
            .sarafee-ai-chart-wrap {
                position: relative;
                margin-bottom: 24px;
                border-radius: 12px;
                background: rgba(249, 250, 251, 0.5);
                border: 1px dashed var(--ai-card-border);
                padding: 16px;
            }

            .sarafee-ai-chart {
                height: 220px;
                width: 100%;
            }

            .sarafee-ai-tooltip {
                position: absolute;
                background: rgba(15, 23, 42, 0.9);
                backdrop-filter: blur(8px);
                border: 1px solid rgba(255, 255, 255, 0.1);
                border-radius: 8px;
                padding: 8px 12px;
                color: #ffffff;
                pointer-events: none;
                z-index: 10;
                display: flex;
                flex-direction: column;
                gap: 4px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                font-size: 0.85rem;
                transition: opacity 0.15s ease, transform 0.15s ease;
                transform: translate(-50%, -100%);
                margin-top: -10px;
            }

            .sarafee-ai-tooltip::after {
                content: '';
                position: absolute;
                bottom: -6px;
                left: 50%;
                transform: translateX(-50%);
                border-width: 6px 6px 0;
                border-style: solid;
                border-color: rgba(15, 23, 42, 0.9) transparent transparent;
            }

            .sarafee-ai-tooltip-date {
                color: #94a3b8;
                font-weight: 500;
            }

            .sarafee-ai-tooltip-price {
                font-weight: 700;
                color: #c084fc;
            }

            .sarafee-ai-grid {
                display: grid;
                grid-template-columns: repeat(7, 1fr);
                gap: 12px;
                margin-top: 15px;
            }

            .sarafee-ai-day {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 8px;
                padding: 12px 8px;
                background: #ffffff;
                border: 1px solid #f3f4f6;
                border-radius: 12px;
                transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
                min-width: 80px;
            }

            .sarafee-ai-day:hover {
                border-color: rgba(139, 92, 246, 0.3);
                box-shadow: 0 4px 12px rgba(139, 92, 246, 0.06);
                transform: translateY(-2px);
            }

            .sarafee-ai-day .day-name {
                font-size: 0.8rem;
                color: var(--ai-text-muted);
                font-weight: 500;
            }

            .sarafee-ai-day .day-price {
                font-size: 0.9rem;
                font-weight: 700;
                color: var(--ai-text-main);
            }

            .sarafee-ai-day .day-trend {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 24px;
                height: 24px;
                border-radius: 50%;
                flex-shrink: 0;
            }

            .sarafee-ai-day .trend-up {
                color: var(--ai-success);
                background: rgba(16, 185, 129, 0.1);
            }

            .sarafee-ai-day .trend-down {
                color: var(--ai-danger);
                background: rgba(239, 68, 68, 0.1);
            }

            .sarafee-ai-day .trend-neutral {
                color: var(--ai-text-muted);
                background: rgba(156, 163, 175, 0.1);
            }
            .sarafee-ai-chart td,
            .sarafee-ai-chart table{
                border:unset;
            }

            @media (max-width: 768px) {
                .sarafee-ai-card {
                    padding: 16px;
                    margin-bottom: 16px;
                }
                .sarafee-ai-header {
                    margin-bottom: 14px;
                }
                .sarafee-ai-chart-wrap {
                    margin-bottom: 16px;
                    padding: 8px;
                }
                .sarafee-ai-chart {
                    height: 160px;
                }
                .sarafee-ai-grid {
                    display: flex;
                    flex-direction: row;
                    overflow-x: auto;
                    gap: 8px;
                    padding: 4px 2px;
                    margin-top: 10px;
                    scrollbar-width: none; /* Firefox */
                    -ms-overflow-style: none; /* IE 10+ */
                }
                .sarafee-ai-grid::-webkit-scrollbar {
                    display: none; /* Safari and Chrome */
                }
                .sarafee-ai-day {
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    padding: 10px 6px;
                    flex: 0 0 27%;
                    min-width: unset;
                    width: auto;
                    gap: 6px;
                }
            }
        </style>
        <?php
    }
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