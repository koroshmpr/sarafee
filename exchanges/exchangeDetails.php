<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function exchange_details_shortcode() {
    $post_id = get_the_ID();

    $verified = get_field( 'verified',         $post_id );
    $rank     = get_field( 'rank',             $post_id );
    $currency = get_field( 'digital_currency', $post_id ); // ACF true/false
    $website  = get_field( 'website',          $post_id );
    $map      = get_field( 'map',              $post_id );
    $phone    = get_field( 'phone',            $post_id );
    $license  = get_field( 'license',          $post_id ); // ACF gallery → array

    $has_badges  = $verified || $rank || $currency;
    $has_actions = $website  || $map  || $phone;
    $has_license = ! empty( $license ) && is_array( $license );

    if ( ! $has_badges && ! $has_actions && ! $has_license ) {
        return '';
    }

    // Unique per-post ID so multiple shortcodes on one page never clash.
    $uid = 'exd_' . absint( $post_id );

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

    <?php if ( $has_actions ) : ?>
    <div class="exd-action-card" dir="rtl">

        <?php if ( $website ) : ?>
        <div class="exd-action-item">
            <div class="exd-icon-circle"><i class="fas fa-globe" aria-hidden="true"></i></div>
            <span dir="ltr"><?php echo esc_html( preg_replace( '#^https?://#', '', rtrim( $website, '/' ) ) ); ?></span>
        </div>
        <?php endif; ?>

        <?php if ( $map ) : ?>
        <a href="<?php echo esc_url( $map['url'] ?? '' ); ?>"
           class="exd-action-item"
           target="_blank"
           rel="noopener noreferrer">
            <div class="exd-icon-circle"><i class="fas fa-map-marker-alt" aria-hidden="true"></i></div>
            <span>نقشه</span>
        </a>
        <?php endif; ?>

        <?php if ( $phone ) : ?>
        <div class="exd-action-item">
            <div class="exd-icon-circle"><i class="fas fa-phone-alt" aria-hidden="true"></i></div>
            <span dir="ltr"><?php echo esc_html( $phone ); ?></span>
        </div>
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

    <?php
    static $css_printed = false;
    if ( ! $css_printed ) :
        $css_printed = true;
    ?>
    <style>
    /* ── Badges ──────────────────────────────────────────── */
    .exd-badges {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 10px;
        margin-bottom: 24px;
    }
    .exd-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 16px;
        border-radius: 12px;
        font-size: 13px;
        font-weight: 600;
        line-height: 1;
    }
    .exd-badge--blue   { background: #eef5ff; color: #0066ff; }
    .exd-badge--gold   { background: #fff8e5; color: #d4a017; }
    .exd-badge--purple { background: #f3eeff; color: #6c3fc9; }

    /* ── Action card ─────────────────────────────────────── */
    .exd-action-card {
        display: flex;
        justify-content: space-around;
        background: #fff;
        border-radius: 24px;
        padding: 25px 15px;
        border: 1px solid #f0f0f0;
        box-shadow: 0 8px 24px rgba(0,0,0,.02);
        margin-bottom: 24px;
    }
    .exd-action-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
        text-decoration: none !important;
        color: #333;
        transition: color .2s;
    }
    .exd-action-item:hover .exd-icon-circle { background: #f5f5f5; }
    .exd-icon-circle {
        width: 56px; height: 56px;
        border-radius: 50%;
        border: 1px solid #e5e5e5;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        color: #1a1a1a;
        transition: background .2s;
    }
    .exd-action-item span { font-size: 14px; font-weight: 500; }

    /* ── License accordion ───────────────────────────────── */
    .exd-license {
        background: #fff;
        border: 1px solid #f0f0f0;
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 8px 24px rgba(0,0,0,.03);
        margin-bottom: 24px;
    }
    .exd-license__header {
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 18px 20px;
        background: none;
        border: none;
        cursor: pointer;
        font-family: inherit;
        font-size: 15px;
        font-weight: 700;
        color: #1a1a1a;
        text-align: right;
        transition: background .15s;
    }
    .exd-license__header:hover { background: #fafafa; color:black; }
    .exd-license__header-start {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .exd-license__header-start > i { color: #6c3fc9; font-size: 17px; }
    .exd-license__count {
        font-size: 12px;
        font-weight: 500;
        color: #888;
        background: #f2f2f2;
        padding: 2px 10px;
        border-radius: 999px;
    }
    .exd-license__chevron {
        font-size: 13px;
        color: #bbb;
        flex-shrink: 0;
        transition: transform .3s ease;
    }
    .exd-license__header[aria-expanded="true"] .exd-license__chevron {
        transform: rotate(180deg);
    }
    .exd-license__panel { padding: 4px 16px 16px; }
    .exd-license__panel[hidden] { display: none; }
    .exd-license__thumbs {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }
    .exd-license__thumb {
        padding: 0;
        background: none;
        border: 2px solid #ebebeb;
        border-radius: 12px;
        overflow: hidden;
        cursor: zoom-in;
        width: 80px; height: 80px;
        flex-shrink: 0;
        transition: border-color .2s, transform .2s, box-shadow .2s;
    }
    .exd-license__thumb:hover {
        border-color: #6c3fc9;
        transform: scale(1.05);
        box-shadow: 0 4px 14px rgba(108,63,201,.22);
    }
    .exd-license__thumb img {
        width: 100%; height: 100%;
        object-fit: cover;
        display: block;
        pointer-events: none;
    }

    /* ── Lightbox ────────────────────────────────────────── */
    .exd-lb {
        position: fixed;
        inset: 0;
        z-index: 99999;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .exd-lb[hidden] { display: none; }
    .exd-lb__bd {
        position: absolute;
        inset: 0;
        background: rgba(0,0,0,.9);
        cursor: zoom-out;
    }
    .exd-lb__stage {
        position: relative;
        z-index: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
    }
    .exd-lb__img {
        max-width: 88vw;
        max-height: 80vh;
        object-fit: contain;
        border-radius: 8px;
        display: block;
        box-shadow: 0 8px 48px rgba(0,0,0,.5);
    }
    .exd-lb__counter {
        margin: 14px 0 0;
        color: rgba(255,255,255,.6);
        font-size: 13px;
        letter-spacing: .04em;
    }
    .exd-lb__close {
        position: fixed;
        top: 16px; left: 16px;
        z-index: 2;
        width: 42px; height: 42px;
        border-radius: 50%;
        border: none;
        background: rgba(255,255,255,.15);
        color: #fff;
        font-size: 17px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(4px);
        transition: background .2s;
    }
    .exd-lb__close:hover { background: rgba(255,255,255,.28); }
    .exd-lb__nav {
        position: relative;
        z-index: 2;
        flex-shrink: 0;
        width: 50px; height: 50px;
        border-radius: 50%;
        border: none;
        background: rgba(255,255,255,.12);
        color: #fff;
        font-size: 20px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 14px;
        backdrop-filter: blur(4px);
        transition: background .2s;
    }
    .exd-lb__nav:hover:not(:disabled) { background: rgba(255,255,255,.25); }
    .exd-lb__nav:disabled { opacity: .2; cursor: default; }

    @media (max-width: 480px) {
        .exd-lb__nav    { width: 40px; height: 40px; font-size: 16px; margin: 0 6px; }
        .exd-lb__img    { max-width: 96vw; }
        .exd-lb__close  { top: 10px; left: 10px; }
        .exd-license__thumb { width: 68px; height: 68px; }
    }
    </style>
    <?php endif; ?>

    <?php
    return ob_get_clean();
}
add_shortcode( 'exchange_details', 'exchange_details_shortcode' );
