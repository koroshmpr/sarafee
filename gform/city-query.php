<?php 
if ( ! defined( 'ABSPATH' ) ) exit;

// Register shortcode
add_shortcode('sarafee_exchanges', 'display_exchanges_by_city');

// Register AJAX actions for logged-in and guest users
add_action('wp_ajax_get_exchanges_by_city', 'ajax_get_exchanges_by_city');
add_action('wp_ajax_nopriv_get_exchanges_by_city', 'ajax_get_exchanges_by_city');

/**
 * AJAX handler to retrieve and return exchanges list HTML.
 */
function ajax_get_exchanges_by_city() {
    $city_name = isset($_GET['city']) ? sanitize_text_field(wp_unslash($_GET['city'])) : '';
    if (empty($city_name)) {
        wp_send_json_error(array('message' => 'نام شهر خالی است.'));
    }

    $html = get_exchanges_list_html($city_name);
    wp_send_json_success(array('html' => $html));
}

/**
 * Main function to generate exchanges list HTML.
 */
function get_exchanges_list_html($city_name) {
    if (empty($city_name)) {
        return '<p style="text-align: center; color: #777; padding: 20px;">لطفا جهت دریافت اطلاعات با پشتیبانی تماس بگیرید.</p>';
    }



    $term = null;
    if (is_numeric($city_name)) {
        $term = get_term(intval($city_name), 'city');
    }
    if (!$term) {
        $term = get_term_by('name', $city_name, 'city');
    }
    if (!$term) {
        $term = get_term_by('slug', $city_name, 'city');
    }

    $args = array(
        'post_type'      => 'exchange',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'no_found_rows'  => true,
    );

    if ($term) {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'city',
                'field'    => 'term_id',
                'terms'    => $term->term_id,
            ),
        );
    } else {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'city',
                'field'    => 'name',
                'terms'    => $city_name,
            ),
        );
    }

    $exchanges = new WP_Query($args);
    $posts = $exchanges->posts;

    if (empty($posts)) {
        return '<p style="text-align: center; color: #777; padding: 20px;">در حال حاضر صرافی فیزیکی ثبت‌شده‌ای در این شهر یافت نشد.</p>';
    }

    // Sort exchanges by rank field
    $rank_map = [];
    foreach ($posts as $_p) {
        $rank_map[$_p->ID] = (int) get_field('rank', $_p->ID);
    }
    usort($posts, function ($a, $b) use ($rank_map) {
        $ra = $rank_map[$a->ID];
        $rb = $rank_map[$b->ID];
        if ($ra > 0 && $rb > 0) return $ra - $rb;
        if ($ra > 0) return -1;
        if ($rb > 0) return 1;
        return 0;
    });

    ob_start();
    ?>
    <h4 class="ea__suggestion-title">صرافی‌های پیشنهادی بر اساس نیاز شما:</h4>
    <ul class="ea__list" itemscope itemtype="https://schema.org/ItemList">
        <?php
        $counter = 0;
        foreach ($posts as $post) :
            $counter++;
            $hidden           = $counter > 5 ? ' style="display: none;"' : '';
            $rank             = get_field('rank', $post->ID) ?: $counter;
            $is_top3          = $rank <= 3;
            $rating           = get_post_meta($post->ID, '_kksr_avg', true);
            $area             = get_field('area', $post->ID);
            $verified         = get_field('verified', $post->ID);
            $digital_currency = get_field('digital_currency', $post->ID);
            $post_title       = get_the_title($post->ID);
            $permalink        = get_permalink($post->ID);
            $logo             = get_the_post_thumbnail_url($post->ID, 'thumbnail');
        ?>
            <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem" class="ea__item"<?php echo $hidden; ?>>
                <meta itemprop="position" content="<?php echo esc_attr($counter); ?>" />
                <meta itemprop="name" content="<?php echo esc_attr($post_title); ?>" />
                <meta itemprop="url" content="<?php echo esc_url($permalink); ?>" />
                
                <a target="_blank" class="ea__item-link" href="<?php echo esc_url($permalink); ?>" tabindex="-1" aria-label="<?php echo esc_attr($post_title); ?>"></a>

                <div class="ea__item-left">
                    <span class="ea__rank<?php echo $is_top3 ? ' ea__rank--top3' : ''; ?>">
                        <?php echo esc_html($rank); ?>
                    </span>
                    <div class="ea__logo">
                        <?php if ($logo) : ?>
                            <img src="<?php echo esc_url($logo); ?>" alt="لوگوی صرافی <?php echo esc_attr($post_title); ?>" width="50" height="50" loading="lazy">
                        <?php else : ?>
                            <i class="fas fa-building" aria-hidden="true"></i>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="ea__info">
                    <p class="ea__name">
                        <a href="<?php echo esc_url($permalink); ?>">
                            <?php echo esc_html($post_title); ?>
                        </a>
                        <?php if ($rating) : ?>
                            <span class="ea__rating">
                                <i class="fas fa-star" aria-hidden="true"></i>
                                <span><?php echo esc_html(number_format((float) $rating, 1)); ?></span>
                            </span>
                        <?php endif; ?>
                    </p>
                    <div class="ea__meta">
                        <?php if ($verified) : ?>
                            <span class="ea__meta-item ea__meta-item--verified">
                                <i class="fas fa-check-circle" aria-hidden="true"></i> معتبر
                            </span>
                        <?php endif; ?>
                        <?php if ($digital_currency) : ?>
                            <span class="ea__meta-item ea__meta-item--digital">
                                <i class="fas fa-coins" aria-hidden="true"></i> ارز دیجیتال
                            </span>
                        <?php endif; ?>
                        <?php if ($area) : ?>
                            <span class="ea__meta-item">
                                <i class="fas fa-map-marker-alt" aria-hidden="true"></i> <?php echo esc_html($area); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <a href="<?php echo esc_url($permalink); ?>" 
                   class="ea__rate-btn" target="_blank"
                   aria-label="مشاهده صرافی <?php echo esc_attr($post_title); ?>">
                    مشاهده صرافی
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php if ( count($posts) > 5 ) : ?>
    <div class="ea__load-more-wrap" style="text-align: center; margin-top: 20px;">
        <button type="button" class="ea__load-more-btn">نمایش بیشتر</button>
    </div>
    <?php endif; ?>
    <?php
    wp_reset_postdata();
    return ob_get_clean();
}

/**
 * Shortcode callback.
 * Attributes:
 *   - city: (Optional) Hardcoded city name or slug.
 *   - field_id: (Optional) Gravity Forms input field selector or ID (e.g. "input_2_5" or simply the field ID "5").
 */
function display_exchanges_by_city($atts) {
    $atts = shortcode_atts(array(
        'city'     => '',
        'field_id' => '',
        'whatsapp' => '',
    ), $atts);

    $city_name = sanitize_text_field($atts['city']);
    $field_id  = sanitize_text_field($atts['field_id']);
    $whatsapp_url = !empty($atts['whatsapp']) ? esc_url($atts['whatsapp']) : 'https://wa.me/989123456789';
    
    // Treat unresolved Gravity Forms merge tags as empty
    if (preg_match('/^\{.*\}$/', $city_name)) {
        $city_name = '';
    }
    
    $unique_id = 'sfe_' . uniqid();

    ob_start();

    // Output styles if not already outputted
    global $sarafee_exchange_assets_done;
    if (empty($sarafee_exchange_assets_done)) {
        $sarafee_exchange_assets_done = true;
        ?>
        <style>
        .ea { direction: rtl; text-align: right; }
        .ea *, .ea *::before, .ea *::after { box-sizing: border-box; }
        .ea__list {
            list-style: none;
            margin: 0; padding: 0;
            display: flex;
            flex-direction: column;
            border: 1px solid #ededed;
            border-radius: 10px;
            overflow: hidden;
        }
        .ea__item {
            position: relative;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 10px;
            border-bottom: 1px solid #f5f5f5;
            transition: background .15s;
        }
        .ea__item:last-child  { border-bottom: none; }
        .ea__item:hover       { background: #fafafa; }
        .ea__item-link {
            position: absolute;
            inset: 0;
            z-index: 1;
            border-radius: inherit;
        }
        .ea__item-left {
            position: relative;
            z-index: 0;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }
        .ea__rank {
            font-size: 17px;
            font-weight: 700;
            color: #aaa;
            min-width: 28px;
            text-align: center;
        }
        .ea__rank--top3 {
            color: #e1b93b;
        }
        .ea__logo {
            width: 50px; height: 50px;
            border-radius: 50%;
            background: #f2f2f242;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
            border: 1px solid #ebebeb;
        }
        .ea__logo img { width: 100%; height: 100%; object-fit: cover; }
        .ea__logo i   { font-size: 20px; color: #bbb; }
        .ea__info {
            position: relative;
            z-index: 0;
            display: flex;
            flex-direction: column;
            gap: 4px;
            flex: 1;
            min-width: 0;
        }
        .ea__name {
            font-size: 15px;
            font-weight: 700;
            color: #1a1a1a;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .ea__name a { color: inherit; text-decoration: none; }
        .ea__rating {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            font-size: 11px;
            font-weight: 700;
            color: #f39c12;
            background: #fcf3cf;
            padding: 2px 6px;
            border-radius: 4px;
            line-height: 1;
        }
        .ea__meta {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 12px;
            font-size: 12px;
            color: #777;
            margin-top: 6px;
        }
        .ea__meta-item { display: flex; align-items: center; gap: 4px; }
        .ea__meta-item i { font-size: 11px; color: #aaa; }
        .ea__meta-item--verified { color: #27ae60; font-weight: 600; }
        .ea__meta-item--verified i { color: #2ecc71; }
        .ea__meta-item--digital i { color: #9b59b6; }
        .ea__rate-btn {
            position: relative;
            z-index: 2;
            flex-shrink: 0;
            padding: 8px 15px;
            border: 1.5px solid #d8d8d8;
            border-radius: 15px;
            font-size: 14px;
            font-weight: 600;
            color: #333;
            background: transparent;
            white-space: nowrap;
            cursor: pointer;
            text-decoration: none;
            transition: border-color .18s, background .18s, color .18s;
            font-family: inherit;
        }
        .ea__rate-btn:hover { border-color: #0f1d3a; background: #0f1d3a; color: #fff; text-decoration: none; }
        .ea__load-more-btn {
            display: inline-block;
            background: #f7f7f7;
            border: 1px solid #ebebeb;
            color: #555;
            font-family: inherit;
            font-size: 14px;
            font-weight: 600;
            padding: 10px 24px;
            border-radius: 999px;
            cursor: pointer;
            transition: background 0.2s, color 0.2s;
            text-decoration: none;
        }
        .ea__load-more-btn:hover {
            background: #0f1d3a;
            color: #fff;
            border-color: #0f1d3a;
        }
        .ea__spinner {
            width: 32px;
            height: 32px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #0f1d3a;
            border-radius: 50%;
            animation: ea-spin 0.8s linear infinite;
            margin: 20px auto;
        }
        .ea__suggestion-title {
            font-size: 16px;
            font-weight: 700;
            color: #1a1a1a;
            margin: 0 0 16px;
            padding: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .ea__suggestion-title::before {
            content: '';
            display: inline-block;
            width: 4px;
            height: 16px;
            background: #0f1d3a;
            border-radius: 2px;
        }
        .ea__contact-box {
            margin-top: 24px;
            padding: 20px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 14px;
        }
        .ea__contact-text {
            font-size: 14px;
            color: #4a5568;
            margin: 0;
            line-height: 1.7;
            font-weight: 500;
        }
        .ea__whatsapp-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #25d366;
            color: #fff;
            padding: 10px 24px;
            border-radius: 999px;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            transition: background 0.2s, transform 0.2s;
            box-shadow: 0 4px 12px rgba(37, 211, 102, 0.25);
        }
        .ea__whatsapp-btn:hover {
            background: #20ba5a;
            color: #fff;
            text-decoration: none;
            transform: translateY(-1px);
        }
        .ea__whatsapp-btn i {
            font-size: 18px;
        }
        @keyframes ea-spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        @media (max-width: 600px) {
            .ea__suggestion-wrap .ea__item {
                display: grid;
                grid-template-areas:
                    "left info"
                    "btn btn";
                grid-template-columns: auto 1fr;
                gap: 12px;
                padding: 16px 12px;
            }
            .ea__suggestion-wrap .ea__item-left {
                grid-area: left;
                align-self: center;
            }
            .ea__suggestion-wrap .ea__info {
                grid-area: info;
                gap: 8px;
            }
            .ea__suggestion-wrap .ea__name {
                flex-wrap: wrap;
                gap: 6px;
            }
            .ea__suggestion-wrap .ea__meta {
                gap: 8px 12px;
            }
            .ea__suggestion-wrap .ea__rate-btn {
                grid-area: btn;
                display: block;
                text-align: center;
                width: 100%;
                padding: 8px 16px;
                border-radius: 8px;
                background: #0f1d3a;
                color: #fff;
                font-size: 14px;
                border: none;
            }
            .ea__suggestion-wrap .ea__rate-btn:hover {
                background: #1a2f56;
                color: #fff;
            }
        }
        </style>
        <?php
    }
    ?>

    <div class="ea__suggestion-wrap" id="<?php echo esc_attr($unique_id); ?>_wrap">
        
        <div class="ea" id="<?php echo esc_attr($unique_id); ?>_container">
            <?php 
            // If a static city is passed, render it directly
            if (!empty($city_name)) {
                echo get_exchanges_list_html($city_name);
            } else {
                echo '<p class="ea__placeholder" style="text-align: center; color: #777; padding: 20px;">در انتظار انتخاب شهر...</p>';
            }
            ?>
        </div>

        <div class="ea__contact-box">
            <p class="ea__contact-text">همکاران ما در سریع‌ترین زمان ممکن جهت راهنمایی با شما تماس خواهند گرفت. همچنین می‌توانید از طریق واتساپ با ما در ارتباط باشید.</p>
            <a href="<?php echo esc_url($whatsapp_url); ?>" class="ea__whatsapp-btn" target="_blank" rel="noopener noreferrer">
                <i class="fab fa-whatsapp" aria-hidden="true"></i> ارتباط در واتساپ
            </a>
        </div>
    </div>

    <script>
    document.addEventListener('click', function(e) {
        if (e.target && e.target.classList.contains('ea__load-more-btn')) {
            var wrap = e.target.closest('.ea__suggestion-wrap') || e.target.parentElement.parentElement;
            if (!wrap) return;
            var list = wrap.querySelector('.ea__list');
            if (list) {
                var items = list.querySelectorAll('.ea__item');
                var currentlyVisible = 0;
                items.forEach(function(item) {
                    if (item.style.display !== 'none') currentlyVisible++;
                });
                
                var newVisible = currentlyVisible + 5;
                items.forEach(function(item, index) {
                    if (index < newVisible) {
                        item.style.display = '';
                    }
                });

                if (newVisible >= items.length) {
                    e.target.parentElement.style.display = 'none';
                }
            }
        }
    });
    </script>
    
    <?php if (empty($city_name)) : ?>
    <script>
    (function() {
        var debounceTimeout = null;

        function init() {
            var container = document.getElementById('<?php echo esc_js($unique_id); ?>_container');
            if (!container) return;

            var targetFieldId = '<?php echo esc_js($field_id); ?>';
            
            function fetchExchanges(cityName) {
                var wrap = document.getElementById('<?php echo esc_js($unique_id); ?>_wrap');
                
                if (!cityName || cityName.trim() === '') {
                    container.innerHTML = '';
                    return;
                }
                
                // Show loader spinner
                container.innerHTML = '<div class="ea__spinner" aria-label="بارگذاری..."></div>';

                var ajaxUrl = '<?php echo esc_url(admin_url('admin-ajax.php')); ?>';
                var url = ajaxUrl + '?action=get_exchanges_by_city&city=' + encodeURIComponent(cityName);

                fetch(url)
                    .then(function(res) { return res.json(); })
                    .then(function(data) {
                        if (data.success && data.data.html) {
                            container.innerHTML = data.data.html;
                        } else {
                            container.innerHTML = '<p style="text-align: center; color: #777; padding: 20px;">صرافی‌ای برای این شهر یافت نشد.</p>';
                        }
                    })
                    .catch(function(err) {
                        container.innerHTML = '<p style="text-align: center; color: #777; padding: 20px;">خطا در ارتباط با سرور.</p>';
                    });
            }

            function debounceFetchExchanges(cityName) {
                if (debounceTimeout) {
                    clearTimeout(debounceTimeout);
                }
                debounceTimeout = setTimeout(function() {
                    fetchExchanges(cityName);
                }, 300);
            }

            function handleFieldChange() {
                debounceFetchExchanges(this.value);
            }

            // Bind to Gravity Forms fields
            function bindField() {
                var field = null;
                if (targetFieldId) {
                    field = document.getElementById(targetFieldId) || 
                            document.getElementById('input_' + targetFieldId) ||
                            document.querySelector('[id$="' + targetFieldId + '"]') ||
                            document.querySelector('[id$="_' + targetFieldId + '"]') ||
                            document.querySelector('input[name="input_' + targetFieldId + '"]') ||
                            document.querySelector('select[name="input_' + targetFieldId + '"]') ||
                            document.querySelector('[name$="' + targetFieldId + '"]');
                }

                if (field) {
                    if (field.value) {
                        debounceFetchExchanges(field.value);
                    } else {
                        container.innerHTML = '';
                    }

                    // Remove existing listeners to avoid duplicates, then add
                    field.removeEventListener('change', handleFieldChange);
                    field.removeEventListener('input', handleFieldChange);
                    field.addEventListener('change', handleFieldChange);
                    field.addEventListener('input', handleFieldChange);
                } else {
                    container.innerHTML = '';
                }
            }

            bindField();
        }

        if (document.readyState === 'interactive' || document.readyState === 'complete') {
            init();
        } else {
            document.addEventListener('DOMContentLoaded', init);
        }

        // Support Gravity Forms dynamic step transition (loaded via AJAX)
        jQuery(document).on('gform_page_loaded', function(event, form_id, current_page) {
            init();
        });
    })();
    </script>
    <?php endif; ?>

    <?php
    return ob_get_clean();
}