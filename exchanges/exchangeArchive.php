<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * [exchange_archive rate_page="/best-rate/"]
 *
 * Featured cities come from ACF Options → showing_cities (taxonomy field).
 * Override with featured_cities shortcode attribute if needed.
 *
 * Attributes:
 *   featured_cities — comma-separated slugs (overrides options page).
 *   rate_page       — URL for the استعلام نرخ popup button.
 *   per_page        — max exchanges to load (default 50).
 *   title_tag       — heading element override.
 *   title           — 'true'|'false'|'' (auto: show only on tax archive).
 */
function exchange_archive_shortcode( $atts ) {
    $atts = shortcode_atts( [
        'featured_cities' => '',
        'rate_page'       => '',
        'per_page'        => 50,
        'title_tag'       => '',
        'title'           => '',
        'show_all'        => 'true',
        'all_link'        => '',
    ], $atts );

    static $instance = 0;
    $instance++;
    $uid = 'ea' . $instance;

    // ── Current city ──────────────────────────────────────────────────────
    $is_tax_archive = is_tax( 'city' );

    if ( $is_tax_archive ) {
        $current_term = get_queried_object();
    } else {
        $city_slug    = isset( $_GET['city'] ) ? sanitize_text_field( wp_unslash( $_GET['city'] ) ) : '';
        $current_term = $city_slug ? get_term_by( 'slug', $city_slug, 'city' ) : null;
    }

    $show_all_enabled = filter_var( $atts['show_all'], FILTER_VALIDATE_BOOLEAN );

    // ── Featured cities ───────────────────────────────────────────────────
    // Priority: 1) shortcode attr  2) ACF options page  3) top-5 by count
    if ( $atts['featured_cities'] ) {
        $featured_slugs = array_filter( array_map( 'trim', explode( ',', $atts['featured_cities'] ) ) );
        $featured_terms = [];
        foreach ( $featured_slugs as $slug ) {
            $t = get_term_by( 'slug', $slug, 'city' );
            if ( $t && ! is_wp_error( $t ) ) {
                $featured_terms[] = $t;
            }
        }
    } else {
        $featured_terms = [];
        $option_cities  = function_exists( 'get_field' ) ? get_field( 'showing_cities', 'option' ) : null;
        if ( ! empty( $option_cities ) && is_array( $option_cities ) ) {
            foreach ( $option_cities as $city ) {
                if ( $city instanceof WP_Term ) {
                    $featured_terms[] = $city;
                } elseif ( is_numeric( $city ) ) {
                    $t = get_term( (int) $city, 'city' );
                    if ( $t && ! is_wp_error( $t ) ) {
                        $featured_terms[] = $t;
                    }
                }
            }
        }
        if ( empty( $featured_terms ) ) {
            $featured_terms = get_terms( [
                'taxonomy'   => 'city',
                'hide_empty' => true,
                'orderby'    => 'count',
                'order'      => 'DESC',
                'number'     => 5,
            ] );
            if ( is_wp_error( $featured_terms ) ) {
                $featured_terms = [];
            }
        }
    }

    if ( ! $current_term && ! empty( $featured_terms ) && ! $show_all_enabled ) {
        $current_term = $featured_terms[0];
    }

    // ── All cities for the search dropdown ────────────────────────────────
    // hide_empty=false so every city is searchable regardless of post count.
    // Featured cities appear first in the list, rest sorted alphabetically.
    $featured_ids = array_column( (array) $featured_terms, 'term_id' );
    $all_cities   = get_terms( [
        'taxonomy'   => 'city',
        'hide_empty' => false,
        'orderby'    => 'name',
    ] );
    if ( is_wp_error( $all_cities ) ) {
        $all_cities = [];
    }
    // Put featured terms at top, then the rest alphabetically
    $non_featured = array_filter( $all_cities, function ( $t ) use ( $featured_ids ) {
        return ! in_array( $t->term_id, $featured_ids, true );
    } );
    $search_terms = array_merge( (array) $featured_terms, array_values( $non_featured ) );

    // ── Exchange query ────────────────────────────────────────────────────
    $per_page = intval( $atts['per_page'] );
    if ( $per_page === 50 ) {
        $per_page = $is_tax_archive ? -1 : 10;
    }
    $query_args = [
        'post_type'      => 'exchange',
        'posts_per_page' => $per_page,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'no_found_rows'  => true,
    ];
    if ( $current_term ) {
        $query_args['tax_query'] = [ [
            'taxonomy' => 'city',
            'field'    => 'term_id',
            'terms'    => $current_term->term_id,
        ] ];
    }
    $loop  = new WP_Query( $query_args );
    $posts = $loop->posts;

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

    // ── Collect unique areas for the filter panel ─────────────────────────
    $areas = [];
    foreach ( $posts as $_p ) {
        $a = get_field( 'area', $_p->ID );
        if ( $a && ! in_array( $a, $areas, true ) ) {
            $areas[] = $a;
        }
    }
    sort( $areas );

    // ── Helpers ───────────────────────────────────────────────────────────
    $title_tag  = $atts['title_tag'] ?: ( $is_tax_archive ? 'h1' : 'h2' );
    $show_title = $atts['title'] === 'true'  ? true
                : ( $atts['title'] === 'false' ? false
                : $is_tax_archive );

    $base_url = strtok( home_url( $_SERVER['REQUEST_URI'] ?? '/' ), '?' );
    $city_url = function ( $term ) use ( $is_tax_archive, $base_url ) {
        return $is_tax_archive
            ? esc_url( get_term_link( $term ) )
            : esc_url( add_query_arg( 'city', $term->slug, $base_url ) );
    };

    ob_start();
    ?>
    <section class="ea" dir="rtl"
             aria-label="<?php echo $current_term ? esc_attr( 'صرافی‌های ' . $current_term->name ) : 'لیست صرافی‌ها'; ?>">

        <?php if ( $show_title ) : ?>
        <<?php echo esc_attr( $title_tag ); ?> class="ea__title">
            <?php if ( $current_term ) : ?>
                صرافی‌های <?php echo esc_html( $current_term->name ); ?>
            <?php else : ?>
                همه صرافی‌ها
            <?php endif; ?>
        </<?php echo esc_attr( $title_tag ); ?>>
        <?php endif; ?>

        <!-- ── Filter bar ── -->
        <?php if ( ! empty( $featured_terms ) ) : ?>
        <div class="ea__filter-wrap" id="<?php echo esc_attr( $uid ); ?>Wrap">

            <div class="ea__filter-bar">

                <!-- Scrollable city pills (right side in RTL) -->
                <div class="ea__pills-scroll">
                    <nav class="ea__filters" role="tablist" aria-label="فیلتر شهر">
                        <?php if ( $show_all_enabled ) : 
                            $all_active = ! $current_term;
                            $all_url = ! empty( $atts['all_link'] ) ? esc_url( $atts['all_link'] ) : ( $is_tax_archive ? esc_url( home_url( '/' ) ) : esc_url( remove_query_arg( 'city' ) ) );
                        ?>
                        <a href="<?php echo $all_url; ?>"
                           class="ea__pill<?php echo $all_active ? ' ea__pill--active' : ''; ?>"
                           role="tab"
                           aria-selected="<?php echo $all_active ? 'true' : 'false'; ?>"
                           aria-label="نمایش همه صرافی‌ها">
                            همه
                        </a>
                        <?php endif; ?>

                        <?php foreach ( $featured_terms as $term ) :
                            $active = $current_term && $current_term->term_id === $term->term_id;
                        ?>
                        <a href="<?php echo $city_url( $term ); ?>"
                           class="ea__pill<?php echo $active ? ' ea__pill--active' : ''; ?>"
                           role="tab"
                           aria-selected="<?php echo $active ? 'true' : 'false'; ?>"
                           aria-label="<?php echo esc_attr( 'نمایش صرافی‌های ' . $term->name ); ?>">
                            <?php echo esc_html( $term->name ); ?>
                        </a>
                        <?php endforeach; ?>
                    </nav>
                </div>

                <!-- Action buttons (left side in RTL) -->
                <div class="ea__bar-actions">
                    <button type="button"
                            class="ea__bar-btn"
                            id="<?php echo esc_attr( $uid ); ?>SearchBtn"
                            aria-label="جستجوی شهر"
                            aria-expanded="false">
                        <i class="fas fa-search" aria-hidden="true"></i>
                        <span>جستجو</span>
                    </button>

                    <button type="button"
                            class="ea__bar-btn ea__bar-btn--icon"
                            id="<?php echo esc_attr( $uid ); ?>FilterBtn"
                            aria-label="فیلتر"
                            aria-expanded="false">
                        <i class="fas fa-sliders-h" aria-hidden="true"></i>
                        <span class="ea__filter-badge"
                              id="<?php echo esc_attr( $uid ); ?>FilterBadge"
                              hidden>0</span>
                    </button>
                </div>
            </div>

            <!-- ── Search dropdown (absolute) ── -->
            <div class="ea__drop ea__drop--search"
                 id="<?php echo esc_attr( $uid ); ?>SearchDrop"
                 role="dialog"
                 aria-label="جستجوی شهر"
                 hidden>
                <div class="ea__search-row">
                    <i class="fas fa-search" aria-hidden="true"></i>
                    <input type="search"
                           id="<?php echo esc_attr( $uid ); ?>SearchInput"
                           placeholder="نام شهر را وارد کنید…"
                           autocomplete="off"
                           aria-label="جستجوی شهر"
                           aria-autocomplete="list"
                           aria-controls="<?php echo esc_attr( $uid ); ?>SearchList">
                    <button type="button"
                            class="ea__drop-close"
                            id="<?php echo esc_attr( $uid ); ?>SearchClose"
                            aria-label="بستن">
                        <i class="fas fa-times" aria-hidden="true"></i>
                    </button>
                </div>
                <ul class="ea__search-list"
                    id="<?php echo esc_attr( $uid ); ?>SearchList"
                    role="listbox"
                    aria-label="شهرها">
                    <?php
                    foreach ( $search_terms as $t ) :
                        $active = $current_term && $current_term->term_id === $t->term_id;
                    ?>
                    <li class="ea__search-item"
                        data-name="<?php echo esc_attr( $t->name ); ?>"
                        role="option"
                        aria-selected="<?php echo $active ? 'true' : 'false'; ?>">
                        <a href="<?php echo $city_url( $t ); ?>"
                           class="<?php echo $active ? 'is-active' : ''; ?>"
                           aria-label="<?php echo esc_attr( 'نمایش صرافی‌های ' . $t->name ); ?>">
                            <?php echo esc_html( $t->name ); ?>
                            <?php if ( $active ) : ?>
                            <i class="fas fa-check" aria-hidden="true"></i>
                            <?php endif; ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- ── Filter dropdown (absolute) ── -->
            <div class="ea__drop ea__drop--filter"
                 id="<?php echo esc_attr( $uid ); ?>FilterDrop"
                 role="dialog"
                 aria-label="فیلترها"
                 hidden>
               <div class="filter-header">
                 <p class="ea__drop-title">فیلتر کردن</p>
                 <button type="button"
                        class="ea__filter-reset"
                        id="<?php echo esc_attr( $uid ); ?>FilterReset">
                    پاک کردن 
                </button>
               </div>
                <div class="ea__filter-chips" id="<?php echo esc_attr( $uid ); ?>Chips">
                    <button type="button" class="ea__chip" data-filter="verified" aria-pressed="false">
                        <i class="fas fa-check-circle" aria-hidden="true"></i>
                        مجاز
                    </button>
                    <button type="button" class="ea__chip" data-filter="digital" aria-pressed="false">
                        <i class="fas fa-coins" aria-hidden="true"></i>
                        ارز دیجیتال
                    </button>
                    <button type="button" class="ea__chip" data-filter="rated" aria-pressed="false">
                        <i class="fas fa-star" aria-hidden="true"></i>
                        دارای امتیاز
                    </button>
                </div>
                <?php if ( ! empty( $areas ) ) : ?>
                <div class="ea__filter-section">
                    <p class="ea__filter-section-label">منطقه / محله</p>
                    <div class="ea__area-search-row">
                        <i class="fas fa-map-marker-alt" aria-hidden="true"></i>
                        <input type="search"
                               id="<?php echo esc_attr( $uid ); ?>AreaSearch"
                               class="ea__area-search"
                               placeholder="جستجوی منطقه…"
                               autocomplete="off"
                               aria-label="جستجوی منطقه">
                    </div>
                    <div class="ea__area-list" id="<?php echo esc_attr( $uid ); ?>AreaList">
                        <?php foreach ( $areas as $area_opt ) : ?>
                        <button type="button"
                                class="ea__chip ea__chip--area"
                                data-area="<?php echo esc_attr( $area_opt ); ?>"
                                aria-pressed="false">
                            <?php echo esc_html( $area_opt ); ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

            
            </div>

        </div><!-- .ea__filter-wrap -->
        <?php endif; ?>

        <!-- ── Exchange list ── -->
        <ul class="ea__list" role="list" id="<?php echo esc_attr( $uid ); ?>List">
        <?php
        $counter = 0;
        if ( ! empty( $posts ) ) :
            global $post;
            foreach ( $posts as $post ) :
                setup_postdata( $post );
                $counter++;
                $rank             = $rank_map[ $post->ID ] ?: $counter;
                $is_top3          = $counter <= 3;
                $rating           = get_post_meta( $post->ID, '_kksr_avg', true );
                $area             = get_field( 'area' );
                $verified         = get_field( 'verified' );
                $digital_currency = get_field( 'digital_currency' );
                $post_title       = get_the_title();
                $permalink        = get_permalink();
                $logo             = get_the_post_thumbnail_url( $post->ID, 'thumbnail' );
                $is_first         = ( $counter === 1 );
        ?>
            <li class="ea__item"
                data-verified="<?php echo $verified         ? '1' : '0'; ?>"
                data-digital="<?php echo $digital_currency ? '1' : '0'; ?>"
                data-rated="<?php echo $rating             ? '1' : '0'; ?>"
                data-area="<?php echo esc_attr( (string) $area ); ?>">

                <a class="ea__item-link" aria-label="open <?= esc_attr( $post_title ) ?? '' ;?> exchange"
                   href="<?php echo esc_url( $permalink ); ?>"
                   tabindex="-1"></a>

                <div class="ea__item-left">
                    <span class="ea__rank<?php echo $is_top3 ? ' ea__rank--top3' : ''; ?>"
                          aria-label="رتبه <?php echo esc_attr( $rank ); ?>">
                        <?php echo esc_html( $rank ); ?>
                    </span>
                    <div class="ea__logo" role="img" aria-label="لوگو <?php echo esc_attr( $post_title ); ?>">
                        <?php if ( $logo ) : ?>
                        <img src="<?php echo esc_url( $logo ); ?>"
                             alt=""
                             width="50" height="50"
                             decoding="async"
                             <?php echo $is_first ? 'fetchpriority="high"' : 'loading="lazy"'; ?>>
                        <?php else : ?>
                        <i class="fas fa-building" aria-hidden="true"></i>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="ea__info">
                    <p class="ea__name">
                        <a href="<?php echo esc_url( $permalink ); ?>"
                           aria-label="<?php echo esc_attr( 'مشاهده صرافی ' . $post_title ); ?>">
                            <?php echo esc_html( $post_title ); ?>
                        </a>
                        <?php if ( $rating ) : ?>
                        <span class="ea__rating"
                              aria-label="<?php echo esc_attr( 'امتیاز ' . number_format( (float) $rating, 1 ) . ' از ۵' ); ?>">
                            <i class="fas fa-star" aria-hidden="true"></i>
                            <span><?php echo esc_html( number_format( (float) $rating, 1 ) ); ?></span>
                        </span>
                        <?php endif; ?>
                    </p>
                    <div class="ea__meta">
                        <?php if ( $verified ) : ?>
                            <span class="ea__meta-item ea__meta-item--verified">
                                <i class="fas fa-check-circle" aria-hidden="true"></i> معتبر
                            </span>
                        <?php endif; ?>
                        <?php if ( $digital_currency ) : ?>
                            <span class="ea__meta-item ea__meta-item--digital">
                                <i class="fas fa-coins" aria-hidden="true"></i> ارز دیجیتال
                            </span>
                        <?php endif; ?>
                        <?php if ( $area ) : ?>
                            <span class="ea__meta-item">
                                <i class="fas fa-map-marker-alt" aria-hidden="true"></i> <?php echo esc_html( $area ); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <button type="button"
                        class="ea__rate-btn openPorsline"
                        aria-label="<?php echo esc_attr( 'استعلام نرخ از ' . $post_title ); ?>"
                        data-exchange-id="<?php echo esc_attr( get_the_ID() ); ?>"
                        data-exchange-name="<?php echo esc_attr( $post_title ); ?>"
                        data-exchange-url="<?php echo esc_attr( $permalink ); ?>">
                    استعلام نرخ
                </button>

            </li>
        <?php endforeach; wp_reset_postdata();
        else : ?>
            <li class="ea__empty" role="status">صرافی‌ای برای این شهر یافت نشد.</li>
        <?php endif; ?>
        </ul>

        <?php if ( $is_tax_archive ) : ?>
        <div class="ea__load-more-wrap" id="<?php echo esc_attr( $uid ); ?>LoadMoreWrap" hidden>
            <button type="button" class="ea__load-more-btn" id="<?php echo esc_attr( $uid ); ?>LoadMoreBtn">
                نمایش بیشتر
            </button>
        </div>
        <?php endif; ?>

        <!-- Empty-after-filter message (hidden by default) -->
        <p class="ea__filter-empty" id="<?php echo esc_attr( $uid ); ?>FilterEmpty" hidden>
            هیچ صرافی با این فیلترها یافت نشد.
        </p>

    </section>

    <script>
    (function () {
        var uid         = <?php echo wp_json_encode( $uid ); ?>;
        var searchBtn   = document.getElementById( uid + 'SearchBtn' );
        var filterBtn   = document.getElementById( uid + 'FilterBtn' );
        var searchDrop  = document.getElementById( uid + 'SearchDrop' );
        var filterDrop  = document.getElementById( uid + 'FilterDrop' );
        var searchInput = document.getElementById( uid + 'SearchInput' );
        var searchClose = document.getElementById( uid + 'SearchClose' );
        var searchList  = document.getElementById( uid + 'SearchList' );
        var filterChips = document.getElementById( uid + 'Chips' );
        var filterReset = document.getElementById( uid + 'FilterReset' );
        var filterBadge = document.getElementById( uid + 'FilterBadge' );
        var areaList    = document.getElementById( uid + 'AreaList' );
        var areaSearch  = document.getElementById( uid + 'AreaSearch' );
        var list        = document.getElementById( uid + 'List' );
        var emptyMsg    = document.getElementById( uid + 'FilterEmpty' );
        var loadMoreWrap= document.getElementById( uid + 'LoadMoreWrap' );
        var loadMoreBtn = document.getElementById( uid + 'LoadMoreBtn' );

        var activeFilters = {};
        var activeAreas   = new Set();
        var itemsToShow   = 10;
        var isTaxArchive  = <?php echo $is_tax_archive ? 'true' : 'false'; ?>;

        // ── Drop open / close ─────────────────────────────
        function openDrop( drop, triggerBtn ) {
            [searchDrop, filterDrop].forEach( function(d) {
                if ( d !== drop ) closeDrop( d );
            });
            drop.hidden = false;
            requestAnimationFrame( function() { drop.classList.add('is-open'); } );
            if ( triggerBtn ) triggerBtn.setAttribute('aria-expanded', 'true');
        }
        function closeDrop( drop ) {
            if ( !drop || drop.hidden ) return;
            drop.classList.remove('is-open');
            drop.addEventListener('transitionend', function handler() {
                drop.hidden = true;
                drop.removeEventListener('transitionend', handler);
            });
            if ( drop === searchDrop && searchBtn ) searchBtn.setAttribute('aria-expanded', 'false');
            if ( drop === filterDrop && filterBtn ) filterBtn.setAttribute('aria-expanded', 'false');
        }

        if ( searchBtn ) {
            searchBtn.addEventListener('click', function() {
                if ( !searchDrop.hidden ) { closeDrop(searchDrop); return; }
                openDrop( searchDrop, searchBtn );
                setTimeout( function() { searchInput && searchInput.focus(); }, 30 );
            });
        }
        if ( filterBtn ) {
            filterBtn.addEventListener('click', function() {
                if ( !filterDrop.hidden ) { closeDrop(filterDrop); return; }
                openDrop( filterDrop, filterBtn );
            });
        }
        if ( searchClose ) {
            searchClose.addEventListener('click', function() {
                closeDrop(searchDrop);
                if ( searchInput ) searchInput.value = '';
                filterCitySearch('');
            });
        }

        // Click outside closes both drops
        document.addEventListener('click', function(e) {
            var wrap = searchBtn && searchBtn.closest('.ea__filter-wrap');
            if ( wrap && !wrap.contains(e.target) ) {
                closeDrop(searchDrop);
                closeDrop(filterDrop);
            }
        });

        // Escape key
        document.addEventListener('keydown', function(e) {
            if ( e.key === 'Escape' ) { closeDrop(searchDrop); closeDrop(filterDrop); }
        });

        // ── City search (city dropdown) ───────────────────
        if ( searchInput ) {
            searchInput.addEventListener('input', function() {
                filterCitySearch( this.value.trim() );
            });
        }
        function filterCitySearch(q) {
            var ql = q.toLowerCase();
            if ( !searchList ) return;
            searchList.querySelectorAll('.ea__search-item').forEach( function(li) {
                li.hidden = !! ( ql && (li.dataset.name || '').toLowerCase().indexOf(ql) === -1 );
            });
        }

        // ── Boolean filter chips (verified / digital / rated) ─
        if ( filterChips ) {
            filterChips.querySelectorAll('.ea__chip').forEach( function(chip) {
                chip.addEventListener('click', function() {
                    var key    = this.dataset.filter;
                    var active = this.getAttribute('aria-pressed') === 'true';
                    this.setAttribute('aria-pressed', active ? 'false' : 'true');
                    if ( active ) delete activeFilters[key];
                    else activeFilters[key] = true;
                    itemsToShow = 10;
                    applyFilters();
                    updateBadge();
                });
            });
        }

        // ── Area chips ────────────────────────────────────
        if ( areaList ) {
            areaList.querySelectorAll('.ea__chip--area').forEach( function(chip) {
                chip.addEventListener('click', function() {
                    var area   = this.dataset.area;
                    var active = this.getAttribute('aria-pressed') === 'true';
                    this.setAttribute('aria-pressed', active ? 'false' : 'true');
                    if ( active ) activeAreas.delete( area );
                    else activeAreas.add( area );
                    itemsToShow = 10;
                    applyFilters();
                    updateBadge();
                });
            });
        }

        // ── Area search (inside filter panel) ────────────
        if ( areaSearch ) {
            areaSearch.addEventListener('input', function() {
                var q = this.value.trim().toLowerCase();
                if ( !areaList ) return;
                areaList.querySelectorAll('.ea__chip--area').forEach( function(chip) {
                    chip.hidden = !! ( q && chip.dataset.area.toLowerCase().indexOf(q) === -1 );
                });
            });
        }

        // ── Apply all active filters ──────────────────────
        function applyFilters() {
            if ( !list ) return;
            var keys    = Object.keys(activeFilters);
            var items   = list.querySelectorAll('.ea__item');
            var matchedItems = [];

            items.forEach( function(item) {
                var passFlags = keys.every( function(k) {
                    return item.dataset[k] === '1';
                });
                var passArea = activeAreas.size === 0
                    || activeAreas.has( item.dataset.area || '' );
                
                if ( passFlags && passArea ) {
                    matchedItems.push(item);
                } else {
                    item.hidden = true;
                }
            });

            var limit = isTaxArchive ? itemsToShow : matchedItems.length;

            matchedItems.forEach(function(item, index) {
                item.hidden = (index >= limit);
            });

            if ( emptyMsg ) emptyMsg.hidden = matchedItems.length > 0;

            if ( loadMoreWrap ) {
                loadMoreWrap.hidden = (matchedItems.length <= limit);
            }
        }

        if ( loadMoreBtn ) {
            loadMoreBtn.addEventListener('click', function() {
                itemsToShow += 10;
                applyFilters();
            });
        }

        applyFilters();

        // ── Badge: total active filters ───────────────────
        function updateBadge() {
            if ( !filterBadge ) return;
            var count = Object.keys(activeFilters).length + activeAreas.size;
            filterBadge.hidden      = count === 0;
            filterBadge.textContent = count;
        }

        // ── Reset all ─────────────────────────────────────
        if ( filterReset ) {
            filterReset.addEventListener('click', function() {
                activeFilters = {};
                activeAreas.clear();
                itemsToShow = 10;
                if ( filterChips ) {
                    filterChips.querySelectorAll('.ea__chip').forEach( function(c) {
                        c.setAttribute('aria-pressed', 'false');
                    });
                }
                if ( areaList ) {
                    areaList.querySelectorAll('.ea__chip--area').forEach( function(c) {
                        c.setAttribute('aria-pressed', 'false');
                        c.hidden = false;
                    });
                }
                if ( areaSearch ) areaSearch.value = '';
                applyFilters();
                updateBadge();
            });
        }
    })();
    </script>

    <?php
    return ob_get_clean();
}
add_shortcode( 'exchange_archive', 'exchange_archive_shortcode' );
