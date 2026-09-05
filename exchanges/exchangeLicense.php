<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Exchange Licenses Component
 * Shortcode: [exchange_license] or [exchange_licenses]
 * Displays exchange licenses/certificates in an accordion with an interactive lightbox.
 */
function exchange_license_shortcode( $atts = [] ) {
    $post_id = get_the_ID();
    $empty_fallback = '<style>.license-section, .exchange-license-section, .exd-license-section, .exd-license { display: none !important; }</style>' .
        '<script>(function(){var s=document.currentScript;if(s){var sec=s.closest(".license-section, .exchange-license-section, .exd-license-section, .elementor-section, .e-con, .e-container, .elementor-widget");if(sec && !sec.textContent.trim()) sec.style.display="none";}})();</script>';

    if ( ! $post_id ) {
        return $empty_fallback;
    }

    $license = get_field( 'license', $post_id );
    if ( empty( $license ) || ! is_array( $license ) ) {
        return $empty_fallback;
    }

    $atts = shortcode_atts( [
        'title' => 'مجوزها',
    ], $atts );

    $uid = 'exd_lic_' . absint( $post_id ) . '_' . wp_rand( 100, 999 );

    $license_images = [];
    foreach ( $license as $img ) {
        $license_images[] = [
            'src'   => $img['url'],
            'thumb' => $img['sizes']['medium'] ?? $img['sizes']['thumbnail'] ?? $img['url'],
            'alt'   => $img['alt'] ?: 'مجوز صرافی',
        ];
    }

    ob_start();
    ?>
    <div class="exd-license exd-license--standalone" id="<?php echo esc_attr( $uid ); ?>Wrap" dir="rtl">
        <button class="exd-license__header"
                id="<?php echo esc_attr( $uid ); ?>AccBtn"
                aria-expanded="false"
                aria-controls="<?php echo esc_attr( $uid ); ?>AccPanel">
            <span class="exd-license__header-start">
                <span class="exd-license__icon">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="4" width="18" height="16" rx="3"></rect>
                        <circle cx="9" cy="10" r="2"></circle>
                        <line x1="15" y1="8" x2="17" y2="8"></line>
                        <line x1="15" y1="12" x2="17" y2="12"></line>
                        <line x1="7" y1="16" x2="17" y2="16"></line>
                    </svg>
                </span>
                <span class="exd-license__title"><?php echo esc_html( $atts['title'] ); ?></span>
                <span class="exd-license__count"><?php echo count( $license_images ); ?></span>
            </span>
            <svg class="exd-license__chevron" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="6 9 12 15 18 9"></polyline>
            </svg>
        </button>

        <div class="exd-license__panel"
             id="<?php echo esc_attr( $uid ); ?>AccPanel"
             role="region"
             aria-labelledby="<?php echo esc_attr( $uid ); ?>AccBtn"
             hidden>
            <div class="exd-license__thumbs">
                <?php foreach ( $license_images as $i => $img ) : ?>
                <button type="button"
                        class="exd-license__thumb"
                        data-lb="<?php echo esc_attr( $uid ); ?>"
                        data-index="<?php echo $i; ?>"
                        aria-label="<?php echo esc_attr( 'بزرگ‌نمایی مجوز ' . ( $i + 1 ) ); ?>">
                    <img src="<?php echo esc_url( $img['thumb'] ); ?>"
                         alt="<?php echo esc_attr( $img['alt'] ); ?>"
                         loading="lazy"
                         decoding="async">
                </button>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Lightbox overlay (teleported to <body> via JS) -->
    <div class="exd-lb"
         id="<?php echo esc_attr( $uid ); ?>Lb"
         role="dialog"
         aria-modal="true"
         aria-label="مجوز"
         hidden>
        <div class="exd-lb__bd" id="<?php echo esc_attr( $uid ); ?>LbBd"></div>

        <button type="button"
                class="exd-lb__close"
                id="<?php echo esc_attr( $uid ); ?>LbClose"
                aria-label="بستن">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </button>

        <!-- RTL: right button is previous image in Persian reading flow -->
        <button type="button"
                class="exd-lb__nav exd-lb__prev"
                id="<?php echo esc_attr( $uid ); ?>LbPrev"
                aria-label="تصویر قبلی">
            <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="9 18 15 12 9 6"></polyline>
            </svg>
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

        <button type="button"
                class="exd-lb__nav exd-lb__next"
                id="<?php echo esc_attr( $uid ); ?>LbNext"
                aria-label="تصویر بعدی">
            <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
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

        function ensureLbInBody() {
            if ( lb && document.body && lb.parentNode !== document.body ) {
                document.body.appendChild( lb );
            }
        }
        if ( document.readyState === 'loading' ) {
            document.addEventListener( 'DOMContentLoaded', ensureLbInBody );
        } else {
            ensureLbInBody();
        }

        // ── Accordion toggle ──────────────────────────────
        if ( accBtn && accPanel ) {
            accBtn.addEventListener( 'click', function () {
                var opening = accPanel.hidden;
                accPanel.hidden = ! opening;
                accBtn.setAttribute( 'aria-expanded', opening ? 'true' : 'false' );
            } );
        }

        // ── Thumbnail → open lightbox ─────────────────────
        document.querySelectorAll( '[data-lb="' + uid + '"]' ).forEach( function ( btn ) {
            btn.addEventListener( 'click', function () {
                openLb( parseInt( this.dataset.index, 10 ) );
            } );
        } );

        function openLb( idx ) {
            ensureLbInBody();
            current = idx;
            render();
            lb.hidden = false;
            document.body.style.overflow = 'hidden';
            if ( lbClose ) lbClose.focus();
        }

        function closeLb() {
            if ( ! lb ) return;
            lb.hidden = true;
            document.body.style.overflow = '';
        }

        function render() {
            if ( ! lbImg || ! lbCount ) return;
            var img    = images[ current ];
            lbImg.src  = img.src;
            lbImg.alt  = img.alt;
            lbCount.textContent = ( current + 1 ) + ' / ' + images.length;
            if ( lbPrev ) lbPrev.disabled = ( current === 0 );
            if ( lbNext ) lbNext.disabled = ( current === images.length - 1 );
        }

        if ( lbClose ) lbClose.addEventListener( 'click', closeLb );
        if ( lbBd )    lbBd.addEventListener(    'click', closeLb );
        if ( lbPrev )  lbPrev.addEventListener(  'click', function () { if ( current > 0 )                 { current--; render(); } } );
        if ( lbNext )  lbNext.addEventListener(  'click', function () { if ( current < images.length - 1 ) { current++; render(); } } );

        document.addEventListener( 'keydown', function ( e ) {
            if ( ! lb || lb.hidden ) return;
            if ( e.key === 'Escape'     )  closeLb();
            if ( e.key === 'ArrowRight' && current > 0 )                 { current--; render(); }
            if ( e.key === 'ArrowLeft'  && current < images.length - 1 ) { current++; render(); }
        } );
    } )();
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode( 'exchange_license', 'exchange_license_shortcode' );
add_shortcode( 'exchange_licenses', 'exchange_license_shortcode' );
