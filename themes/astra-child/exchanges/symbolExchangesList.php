<?php
/**
 * Non-Reloading exchanges list shortcode for symbol page
 * Usage: [symbol_exchanges title="صرافی‌های معتبر انگلستان"]
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'symbol_exchanges', 'render_symbol_exchanges_list' );

function render_symbol_exchanges_list( $atts ) {
    global $sarafee_exchange_assets_done;

    $atts = shortcode_atts( [
        'title'    => 'صرافی‌های معتبر انگلیس',
        'per_page' => 30,
    ], $atts );

    $per_page = intval( $atts['per_page'] );
    // Capping to prevent low page speed and giant data retrieval
    if ( $per_page <= 0 || $per_page > 100 ) {
        $per_page = 30;
    }

    $query_args = [
        'post_type'      => 'exchange',
        'posts_per_page' => $per_page,
        'post_status'    => 'publish',
        'no_found_rows'  => true,
    ];

    $loop = new WP_Query( $query_args );
    $posts = $loop->posts;

    if ( empty( $posts ) ) {
        return '';
    }

    // Sort exchanges by rank field
    $rank_map = [];
    foreach ( $posts as $_p ) {
        $rank_map[ $_p->ID ] = (int) get_field( 'rank', $_p->ID );
    }
    usort( $posts, function ( $a, $b ) use ( $rank_map ) {
        $ra = $rank_map[ $a->ID ];
        $rb = $rank_map[ $b->ID ];
        if ( $ra > 0 && $rb > 0 ) return $ra - $rb;
        if ( $ra > 0 ) return -1;
        if ( $rb > 0 ) return  1;
        return 0;
    } );

    // Gather active cities in the queried exchanges (limit to 15 to avoid massive DOM/filter bar issues)
    $cities = [];
    $exchange_data = [];

    foreach ( $posts as $post ) {
        $terms = get_the_terms( $post->ID, 'city' );
        $city_slugs = [];
        if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
            foreach ( $terms as $t ) {
                if ( count( $cities ) < 15 || isset( $cities[$t->slug] ) ) {
                    $cities[$t->slug] = [
                        'name' => $t->name,
                        'link' => get_term_link( $t, 'city' )
                    ];
                    $city_slugs[] = $t->slug;
                }
            }
        }

        $rating           = get_post_meta( $post->ID, '_kksr_avg', true );
        $area             = get_field( 'area', $post->ID );
        $verified         = get_field( 'verified', $post->ID );
        $digital_currency = get_field( 'digital_currency', $post->ID );
        $logo             = get_the_post_thumbnail_url( $post->ID, 'thumbnail' );

        $exchange_data[] = [
            'post'       => $post,
            'city_slugs' => $city_slugs,
            'rating'     => $rating,
            'area'       => $area,
            'verified'   => $verified,
            'digital'    => $digital_currency,
            'logo'       => $logo,
        ];
    }

    // Keep unique cities list sorted
    uasort($cities, function($a, $b) {
        return strcmp($a['name'], $b['name']);
    });

    $uid = 'sel_' . uniqid();
    ob_start();
    ?>

    <div class="ea" id="<?php echo esc_attr( $uid ); ?>">
        <?php if ( ! empty( $atts['title'] ) ) : ?>
            <h3 class="ea__title"><?php echo esc_html( $atts['title'] ); ?></h3>
        <?php endif; ?>

        <!-- Pills for cities -->
        <div class="ea__filter-wrap">
            <div class="ea__filter-bar">
                <div class="ea__pills-scroll">
                    <div class="ea__filters" role="tablist" aria-label="فیلتر صرافی‌ها بر اساس شهر">
                        <button type="button" class="ea__pill ea__pill--active" data-city="all" role="tab" aria-selected="true">همه</button>
                        <?php foreach ( $cities as $slug => $data ) : ?>
                            <button type="button" class="ea__pill" data-city="<?php echo esc_attr( $slug ); ?>" data-link="<?php echo esc_url( $data['link'] ); ?>" role="tab" aria-selected="false"><?php echo esc_html( $data['name'] ); ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- List of exchanges -->
        <ul class="ea__list" itemscope itemtype="https://schema.org/ItemList">
            <?php
            $counter = 0;
            foreach ( $exchange_data as $data ) :
                $counter++;
                $rank    = get_field( 'rank', $data['post']->ID ) ?: $counter;
                $is_top3 = $rank <= 3;
            ?>
                <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem" class="ea__item" data-cities="<?php echo esc_attr( implode( ' ', $data['city_slugs'] ) ); ?>">
                    <meta itemprop="position" content="<?php echo esc_attr( $counter ); ?>" />
                    <meta itemprop="name" content="<?php echo esc_attr( get_the_title( $data['post']->ID ) ); ?>" />
                    <meta itemprop="url" content="<?php echo esc_url( get_permalink( $data['post']->ID ) ); ?>" />
                    
                    <a class="ea__item-link" href="<?php echo esc_url( get_permalink( $data['post']->ID ) ); ?>" tabindex="-1" aria-label="<?php echo esc_attr( get_the_title( $data['post']->ID ) ); ?>"></a>

                    <div class="ea__item-left">
                        <span class="ea__rank<?php echo $is_top3 ? ' ea__rank--top3' : ''; ?>">
                            <?php echo esc_html( $rank ); ?>
                        </span>
                        <div class="ea__logo">
                            <?php if ( $data['logo'] ) : ?>
                                <img src="<?php echo esc_url( $data['logo'] ); ?>" alt="لوگوی صرافی <?php echo esc_attr( get_the_title( $data['post']->ID ) ); ?>" width="50" height="50" loading="lazy">
                            <?php else : ?>
                                <i class="fas fa-building" aria-hidden="true"></i>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="ea__info">
                        <p class="ea__name">
                            <a href="<?php echo esc_url( get_permalink( $data['post']->ID ) ); ?>">
                                <?php echo esc_html( get_the_title( $data['post']->ID ) ); ?>
                            </a>
                            <?php if ( $data['rating'] ) : ?>
                                <span class="ea__rating">
                                    <i class="fas fa-star" aria-hidden="true"></i>
                                    <span><?php echo esc_html( number_format( (float) $data['rating'], 1 ) ); ?></span>
                                </span>
                            <?php endif; ?>
                        </p>
                        <div class="ea__meta">
                            <?php if ( $data['verified'] ) : ?>
                                <span class="ea__meta-item ea__meta-item--verified">
                                    <i class="fas fa-check-circle" aria-hidden="true"></i> معتبر
                                </span>
                            <?php endif; ?>
                            <?php if ( $data['digital'] ) : ?>
                                <span class="ea__meta-item ea__meta-item--digital">
                                    <i class="fas fa-coins" aria-hidden="true"></i> ارز دیجیتال
                                </span>
                            <?php endif; ?>
                            <?php if ( $data['area'] ) : ?>
                                <span class="ea__meta-item">
                                    <i class="fas fa-map-marker-alt" aria-hidden="true"></i> <?php echo esc_html( $data['area'] ); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <button type="button"
                            class="ea__rate-btn openPorsline"
                            aria-label="<?php echo esc_attr( 'استعلام نرخ از ' . get_the_title( $data['post']->ID ) ); ?>"
                            data-exchange-id="<?php echo esc_attr( $data['post']->ID ); ?>"
                            data-exchange-name="<?php echo esc_attr( get_the_title( $data['post']->ID ) ); ?>"
                            data-exchange-url="<?php echo esc_url( get_permalink( $data['post']->ID ) ); ?>">
                        استعلام نرخ
                    </button>
                </li>
            <?php endforeach; ?>
        </ul>
        
        <div class="ea__view-all-wrap" id="<?php echo esc_attr( $uid ); ?>ViewAllWrap" style="display: none; text-align: center; margin-top: 20px;">
            <a href="#" class="ea__view-all-btn" id="<?php echo esc_attr( $uid ); ?>ViewAllBtn">
                مشاهده همه صرافی‌های این شهر
            </a>
        </div>
    </div>

    <script>
    (function() {
        var root = document.getElementById(<?php echo wp_json_encode($uid); ?>);
        if (!root) return;

        var pills = root.querySelectorAll('.ea__pill');
        var items = root.querySelectorAll('.ea__item');
        var viewAllWrap = document.getElementById(<?php echo wp_json_encode($uid . 'ViewAllWrap'); ?>);
        var viewAllBtn = document.getElementById(<?php echo wp_json_encode($uid . 'ViewAllBtn'); ?>);

        function applyFilter(selectedPill) {
            var selectedCity = selectedPill.getAttribute('data-city');
            var cityLink = selectedPill.getAttribute('data-link');
            var matchedItems = [];

            // Filter items
            items.forEach(function(item) {
                if (selectedCity === 'all') {
                    matchedItems.push(item);
                } else {
                    var itemCities = (item.getAttribute('data-cities') || '').split(' ');
                    if (itemCities.indexOf(selectedCity) !== -1) {
                        matchedItems.push(item);
                    } else {
                        item.style.display = 'none';
                    }
                }
            });

            matchedItems.forEach(function(item, index) {
                if (index < 10) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });

            if (selectedCity !== 'all' && matchedItems.length > 10 && cityLink) {
                viewAllWrap.style.display = 'block';
                viewAllBtn.href = cityLink;
            } else {
                viewAllWrap.style.display = 'none';
            }
        }

        pills.forEach(function(pill) {
            pill.addEventListener('click', function() {
                // Toggle active class and aria-selected on pills
                pills.forEach(function(p) { 
                    p.classList.remove('ea__pill--active'); 
                    p.setAttribute('aria-selected', 'false');
                });
                pill.classList.add('ea__pill--active');
                pill.setAttribute('aria-selected', 'true');

                applyFilter(pill);
            });
        });

        // Initialize with 'all' tab
        var activePill = root.querySelector('.ea__pill--active');
        if (activePill) {
            applyFilter(activePill);
        }
    })();
    </script>
    <?php
    wp_reset_postdata();
    return ob_get_clean();
}
