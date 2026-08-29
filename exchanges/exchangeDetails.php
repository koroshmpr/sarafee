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
    $license  = get_field( 'license',          $post_id ); // ACF gallery → array

    $has_badges  = $verified || $rank || $currency;
    $has_info    = $address  || $phone || $website || $map;
    $has_license = ! empty( $license ) && is_array( $license );

    if ( ! $has_badges && ! $has_info && ! $has_license ) {
        return '';
    }

    // Unique per-post ID so multiple shortcodes on one page never clash.
    $uid = 'exd_' . absint( $post_id );

    $map_url       = $map['url'] ?? '';
    $map_embed_src = ( $address || $map_url ) ? exd_map_embed_src( $map_url, $address ) : '';

    // Build image list once; passed to JS lightbox as JSON.
    $license_images = [];
    if ( $has_license ) {
        foreach ( $license as $img ) {
            $license_images[] = [
                'src'   => $img['url'],
                'thumb' => $img['sizes']['medium'] ?? $img['sizes']['thumbnail'] ?? $img['url'],
                'alt'   => $img['alt'] ?: 'مجوز',
            ];
        }
    }

    ob_start();
    ?>

    <?php if ( $has_badges ) : ?>
    <div class="exd-badges" dir="rtl">

        <?php if ( $verified ) : ?>
        <span class="exd-badge exd-badge--blue">
            <i class="fas fa-check-circle" aria-hidden="true"></i>
            مجاز
        </span>
        <?php endif; ?>

        <?php if ( $currency ) : ?>
        <span class="exd-badge exd-badge--purple">
            <i class="fas fa-coins" aria-hidden="true"></i>
            تبدیل ارز دیجیتال
        </span>
        <?php endif; ?>

        <?php if ( $rank ) : ?>
        <span class="exd-badge exd-badge--gold">
            <i class="fas fa-star" aria-hidden="true"></i>
            رتبه <?php echo esc_html( $rank ); ?>
        </span>
        <?php endif; ?>

    </div>
    <?php endif; ?>

    <?php if ( $has_info ) : ?>
    <!-- ── Contact info: Google-Maps-style card (display only, not linkable) ── -->
    <div class="exd-info" dir="rtl">

        <?php if ( $address ) : ?>
        <div class="exd-info__row">
            <div class="exd-info__icon"><i class="fas fa-map-marker-alt" aria-hidden="true"></i></div>
            <div class="exd-info__text"><?php echo nl2br( esc_html( $address ) ); ?></div>
        </div>
        <?php endif; ?>

        <?php if ( $phone ) : ?>
        <div class="exd-info__row">
            <div class="exd-info__icon"><i class="fas fa-phone-alt" aria-hidden="true"></i></div>
            <div class="exd-info__text" dir="ltr"><?php echo esc_html( $phone ); ?></div>
        </div>
        <?php endif; ?>

        <?php if ( $website ) : ?>
        <div class="exd-info__row">
            <div class="exd-info__icon"><i class="fas fa-globe" aria-hidden="true"></i></div>
            <div class="exd-info__text" dir="ltr"><?php echo esc_html( preg_replace( '#^https?://#', '', rtrim( $website, '/' ) ) ); ?></div>
        </div>
        <?php endif; ?>

        <?php if ( $map_embed_src ) : ?>
        <button type="button"
                class="exd-info__map-btn"
                id="<?php echo esc_attr( $uid ); ?>MapBtn"
                aria-expanded="false"
                aria-controls="<?php echo esc_attr( $uid ); ?>MapSheet"
                data-src="<?php echo esc_url( $map_embed_src ); ?>">
            <i class="fas fa-map-marked-alt" aria-hidden="true"></i>
            نمایش روی نقشه
        </button>
        <?php endif; ?>

    </div>
    <?php endif; ?>

    <?php if ( $has_license ) : ?>
    <!-- ── License: accordion + lightbox ── -->
    <div class="exd-license" dir="rtl">

        <button class="exd-license__header"
                id="<?php echo esc_attr( $uid ); ?>AccBtn"
                aria-expanded="false"
                aria-controls="<?php echo esc_attr( $uid ); ?>AccPanel">
            <span class="exd-license__header-start">
                <i class="fas fa-id-card" aria-hidden="true"></i>
                مجوزها
                <span class="exd-license__count"><?php echo count( $license ); ?></span>
            </span>
            <i class="fas fa-chevron-down exd-license__chevron" aria-hidden="true"></i>
        </button>

        <div class="exd-license__panel"
             id="<?php echo esc_attr( $uid ); ?>AccPanel"
             role="region"
             aria-labelledby="<?php echo esc_attr( $uid ); ?>AccBtn"
             hidden>
            <div class="exd-license__thumbs">
                <?php foreach ( $license_images as $i => $img ) : ?>
                <button class="exd-license__thumb"
                        data-lb="<?php echo esc_attr( $uid ); ?>"
                        data-index="<?php echo $i; ?>"
                        aria-label="<?php echo esc_attr( 'بزرگ‌نمایی مجوز ' . ( $i + 1 ) ); ?>">
                    <img src="<?php echo esc_url( $img['thumb'] ); ?>"
                         alt="<?php echo esc_attr( $img['alt'] ); ?>"
                         width="80" height="80"
                         loading="lazy"
                         decoding="async">
                </button>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

    <!-- Lightbox overlay (one per post, filled by JS) -->
    <div class="exd-lb"
         id="<?php echo esc_attr( $uid ); ?>Lb"
         role="dialog"
         aria-modal="true"
         aria-label="مجوز"
         hidden>
        <div class="exd-lb__bd" id="<?php echo esc_attr( $uid ); ?>LbBd"></div>

        <button class="exd-lb__close"
                id="<?php echo esc_attr( $uid ); ?>LbClose"
                aria-label="بستن">
            <i class="fas fa-times" aria-hidden="true"></i>
        </button>

        <!-- RTL: right side = chronologically previous -->
        <button class="exd-lb__nav exd-lb__prev"
                id="<?php echo esc_attr( $uid ); ?>LbPrev"
                aria-label="تصویر قبلی">
            <i class="fas fa-chevron-right" aria-hidden="true"></i>
        </button>

        <div class="exd-lb__stage">
            <img class="exd-lb__img"
                 id="<?php echo esc_attr( $uid ); ?>LbImg"
                 src=""
                 alt="">
            <p class="exd-lb__counter"
               id="<?php echo esc_attr( $uid ); ?>LbCount"
               aria-live="polite"></p>
        </div>

        <button class="exd-lb__nav exd-lb__next"
                id="<?php echo esc_attr( $uid ); ?>LbNext"
                aria-label="تصویر بعدی">
            <i class="fas fa-chevron-left" aria-hidden="true"></i>
        </button>
    </div>

    <script>
    (function () {
        var images  = <?php echo wp_json_encode( $license_images ); ?>;
        var uid     = <?php echo wp_json_encode( $uid ); ?>;
        var current = 0;

        var accBtn   = document.getElementById( uid + 'AccBtn' );
        var accPanel = document.getElementById( uid + 'AccPanel' );
        var lb       = document.getElementById( uid + 'Lb' );
        var lbBd     = document.getElementById( uid + 'LbBd' );
        var lbClose  = document.getElementById( uid + 'LbClose' );
        var lbPrev   = document.getElementById( uid + 'LbPrev' );
        var lbNext   = document.getElementById( uid + 'LbNext' );
        var lbImg    = document.getElementById( uid + 'LbImg' );
        var lbCount  = document.getElementById( uid + 'LbCount' );

        // ── Accordion toggle ──────────────────────────────
        accBtn.addEventListener( 'click', function () {
            var opening = accPanel.hidden;
            accPanel.hidden = ! opening;
            accBtn.setAttribute( 'aria-expanded', opening ? 'true' : 'false' );
        } );

        // ── Thumbnail → open lightbox ─────────────────────
        document.querySelectorAll( '[data-lb="' + uid + '"]' ).forEach( function ( btn ) {
            btn.addEventListener( 'click', function () {
                openLb( parseInt( this.dataset.index, 10 ) );
            } );
        } );

        function openLb( idx ) {
            current = idx;
            render();
            lb.hidden = false;
            document.body.style.overflow = 'hidden';
            lbClose.focus();
        }

        function closeLb() {
            lb.hidden = true;
            document.body.style.overflow = '';
        }

        function render() {
            var img    = images[ current ];
            lbImg.src  = img.src;
            lbImg.alt  = img.alt;
            lbCount.textContent = ( current + 1 ) + ' / ' + images.length;
            lbPrev.disabled = ( current === 0 );
            lbNext.disabled = ( current === images.length - 1 );
        }

        lbClose.addEventListener( 'click', closeLb );
        lbBd.addEventListener(    'click', closeLb );
        lbPrev.addEventListener(  'click', function () { if ( current > 0 )                 { current--; render(); } } );
        lbNext.addEventListener(  'click', function () { if ( current < images.length - 1 ) { current++; render(); } } );

        document.addEventListener( 'keydown', function ( e ) {
            if ( lb.hidden ) return;
            if ( e.key === 'Escape'     )  closeLb();
            // In RTL right arrow = back (previous), left arrow = forward (next)
            if ( e.key === 'ArrowRight' && current > 0 )                 { current--; render(); }
            if ( e.key === 'ArrowLeft'  && current < images.length - 1 ) { current++; render(); }
        } );
    } )();
    </script>

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

        function openSheet() {
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
            setTimeout( function () { sheet.hidden = true; }, 250 );
        }

        mapBtn.addEventListener( 'click', function ( e ) {
            e.preventDefault();
            openSheet();
        } );
        sheetClose.addEventListener( 'click', closeSheet );
        sheetBd.addEventListener(    'click', closeSheet );
        document.addEventListener( 'keydown', function ( e ) {
            if ( e.key === 'Escape' && ! sheet.hidden ) closeSheet();
        } );
    } )();
    </script>
    <?php endif; ?>

    <?php
    return ob_get_clean();
}
add_shortcode( 'exchange_details', 'exchange_details_shortcode' );
