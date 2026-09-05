<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Builds a no-API-key Google Maps embed URL.
 * Prefers lat/lng parsed out of a pasted Google Maps share link (…@lat,lng,z…),
 * falls back to the free-text address, then to the raw map link itself.
 */
function exd_map_embed_src( $map_url, $address ) {
    if ( $map_url && preg_match( '#@(-?\d+\.\d+),(-?\d+\.\d+)#', $map_url, $m ) ) {
        $q = $m[1] . ',' . $m[2];
    } elseif ( $address ) {
        $q = $address;
    } elseif ( $map_url ) {
        $q = $map_url;
    } else {
        return '';
    }
    return 'https://www.google.com/maps?q=' . rawurlencode( $q ) . '&output=embed';
}

function exchange_details_shortcode() {
    $post_id = get_the_ID();

    $verified = get_field( 'verified',         $post_id );
    $rank     = get_field( 'rank',             $post_id );
    $currency = get_field( 'digital_currency', $post_id ); // ACF true/false
    $website  = get_field( 'website',          $post_id );
    $map      = get_field( 'map',              $post_id );
    $phone    = get_field( 'phone',            $post_id );
    $address  = get_field( 'address',          $post_id ); // ACF textarea

    $has_badges  = $verified || $rank || $currency;
    $has_info    = $address  || $phone || $website || $map;

    if ( ! $has_badges && ! $has_info ) {
        return '<style>.details-section, .exchange-details-section, .exd-info, .exd-badges { display: none !important; }</style>' .
        '<script>(function(){var s=document.currentScript;if(s){var sec=s.closest(".details-section, .exchange-details-section, .elementor-section, .e-con, .e-container, .elementor-widget");if(sec && !sec.textContent.trim()) sec.style.display="none";}})();</script>';
    }

    // Unique per-post ID so multiple shortcodes on one page never clash.
    $uid = 'exd_' . absint( $post_id );

    $map_url       = $map['url'] ?? '';
    $map_embed_src = ( $address || $map_url ) ? exd_map_embed_src( $map_url, $address ) : '';

    ob_start();
    ?>

    <?php if ( $has_badges ) : ?>
    <div class="exd-badges" dir="rtl">

        <?php if ( $verified ) : ?>
        <span class="exd-badge exd-badge--verified" title="صرافی مجاز">
            <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
            مجاز
        </span>
        <?php endif; ?>

        <?php if ( $currency ) : ?>
        <span class="exd-badge exd-badge--currency" title="پشتیبانی از ارز دیجیتال">
            <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="9"></circle>
                <path d="M14.5 9h-4a1 1 0 0 0-1 1v4a1 1 0 0 0 1 1h4"></path>
                <path d="M12 7v10"></path>
            </svg>
            ارز دیجیتال
        </span>
        <?php endif; ?>

        <?php if ( $rank ) : ?>
        <span class="exd-badge exd-badge--rank" title="<?php echo esc_attr( 'رتبه ' . $rank ); ?>">
            <svg viewBox="0 0 24 24" width="11" height="11" fill="currentColor" stroke="none">
                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
            </svg>
            رتبه <?php echo esc_html( $rank ); ?>
        </span>
        <?php endif; ?>

    </div>
    <?php endif; ?>

    <?php if ( $has_info ) : ?>
    <!-- ── Contact info: Google-Maps-style card (display only, not linkable) ── -->
    <div class="exd-info" dir="rtl">

        <?php if ( $address ) : ?>
        <div class="exd-info__row exd-info__row--address">
            <span class="exd-info__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 21s-7-5.5-7-11a7 7 0 0 1 14 0c0 5.5-7 11-7 11z"></path>
                    <circle cx="12" cy="10" r="2.5"></circle>
                </svg>
            </span>
            <div class="exd-info__text exd-info__text--address">
                <span><?php echo nl2br( esc_html( $address ) ); ?></span>
            </div>
        </div>
        <?php endif; ?>

        <?php if ( $phone ) : ?>
        <div class="exd-info__row exd-info__row--phone">
            <span class="exd-info__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                </svg>
            </span>
            <div class="exd-info__text exd-info__text--phone">
                <span dir="ltr" class="exd-ltr-val"><?php echo esc_html( $phone ); ?></span>
            </div>
        </div>
        <?php endif; ?>

        <?php if ( $website ) : ?>
        <div class="exd-info__row exd-info__row--website">
            <span class="exd-info__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="2" y1="12" x2="22" y2="12"></line>
                    <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
                </svg>
            </span>
            <div class="exd-info__text exd-info__text--website">
                <span dir="ltr" class="exd-ltr-val"><?php echo esc_html( preg_replace( '#^https?://#', '', rtrim( $website, '/' ) ) ); ?></span>
            </div>
        </div>
        <?php endif; ?>

        <?php if ( $map_embed_src ) : ?>
        <button type="button"
                class="exd-info__map-btn"
                id="<?php echo esc_attr( $uid ); ?>MapBtn"
                aria-expanded="false"
                aria-controls="<?php echo esc_attr( $uid ); ?>MapSheet"
                data-src="<?php echo esc_url( $map_embed_src ); ?>">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"></polygon>
                <line x1="8" y1="2" x2="8" y2="18"></line>
                <line x1="16" y1="6" x2="16" y2="22"></line>
            </svg>
            <span>نمایش روی نقشه</span>
        </button>
        <?php endif; ?>

    </div>
    <?php endif; ?>

    <?php if ( $map_embed_src ) : ?>
    <!-- Map bottom-sheet (offcanvas). Trigger is a <button>, so clicking it never navigates away. -->
    <div class="exd-map-sheet" id="<?php echo esc_attr( $uid ); ?>MapSheet" role="dialog" aria-modal="true" aria-label="نقشه" hidden>
        <div class="exd-map-sheet__bd" id="<?php echo esc_attr( $uid ); ?>MapSheetBd"></div>
        <div class="exd-map-sheet__panel">
            <div class="exd-map-sheet__handle" aria-hidden="true"></div>
            <div class="exd-map-sheet__header">
                <span>موقعیت روی نقشه</span>
                <button class="exd-map-sheet__close" id="<?php echo esc_attr( $uid ); ?>MapSheetClose" aria-label="بستن">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </div>
            <div class="exd-map-sheet__frame">
                <iframe id="<?php echo esc_attr( $uid ); ?>MapFrame"
                        loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"
                        title="نقشه موقعیت"></iframe>
            </div>
            <?php if ( $map_url ) : ?>
            <a href="<?php echo esc_url( $map_url ); ?>" target="_blank" rel="noopener noreferrer" class="exd-map-sheet__external">
                <i class="fas fa-external-link-alt" aria-hidden="true"></i>
                مشاهده در Google Maps
            </a>
            <?php endif; ?>
        </div>
    </div>

    <script>
    (function () {
        var uid         = <?php echo wp_json_encode( $uid ); ?>;
        var mapBtn      = document.getElementById( uid + 'MapBtn' );
        var sheet       = document.getElementById( uid + 'MapSheet' );
        var sheetBd     = document.getElementById( uid + 'MapSheetBd' );
        var sheetClose  = document.getElementById( uid + 'MapSheetClose' );
        var frame       = document.getElementById( uid + 'MapFrame' );
        var frameLoaded = false;

        if ( ! mapBtn || ! sheet ) return;

        function ensureMapInBody() {
            if ( sheet && document.body && sheet.parentNode !== document.body ) {
                document.body.appendChild( sheet );
            }
        }
        if ( document.readyState === 'loading' ) {
            document.addEventListener( 'DOMContentLoaded', ensureMapInBody );
        } else {
            ensureMapInBody();
        }

        function openSheet() {
            ensureMapInBody();
            if ( ! frameLoaded ) {
                frame.src = mapBtn.dataset.src;
                frameLoaded = true;
            }
            sheet.hidden = false;
            mapBtn.setAttribute( 'aria-expanded', 'true' );
            document.body.style.overflow = 'hidden';
            requestAnimationFrame( function () { sheet.classList.add( 'is-open' ); } );
        }

        function closeSheet() {
            sheet.classList.remove( 'is-open' );
            mapBtn.setAttribute( 'aria-expanded', 'false' );
            document.body.style.overflow = '';
            setTimeout( function () { 
                if ( ! sheet.classList.contains( 'is-open' ) ) {
                    sheet.hidden = true; 
                }
            }, 250 );
        }

        mapBtn.addEventListener( 'click', function ( e ) {
            e.preventDefault();
            openSheet();
        } );
        if ( sheetClose ) sheetClose.addEventListener( 'click', closeSheet );
        if ( sheetBd )    sheetBd.addEventListener(    'click', closeSheet );
        document.addEventListener( 'keydown', function ( e ) {
            if ( e.key === 'Escape' && sheet && ! sheet.hidden ) closeSheet();
        } );
    } )();
    </script>
    <?php endif; ?>

    <?php
    return ob_get_clean();
}
add_shortcode( 'exchange_details', 'exchange_details_shortcode' );
