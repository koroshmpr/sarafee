<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function exchange_about_shortcode( $atts ) {
    // 1. Prevent Infinite Loops in Elementor/WordPress
    // If this shortcode is placed inside the content it is trying to retrieve, it will cause a fatal error.
    static $is_running = false;
    if ( $is_running ) {
        return '';
    }

    $atts = shortcode_atts( [ 'label' => '' ], $atts );
    $post_id = get_the_ID();

    if ( ! $post_id ) {
        return '';
    }

    // Lock the shortcode from running inside itself
    $is_running = true;
    
    $content = get_the_content();
    if ( ! $content ) {
        $is_running = false;
        return '';
    }

    $uid       = 'ea_about_' . absint( $post_id ) . '_' . wp_rand(100, 999);
    $post_type = get_post_type( $post_id );

    if ( $atts['label'] !== '' ) {
        $label = esc_html( $atts['label'] );
    } else {
        $labels = [
            'exchange' => 'درباره صرافی',
            'symbol'   => 'درباره ارز',
            'post'     => 'درباره این مطلب',
        ];
        // Fallback for ANY other post type
        $label = $labels[ $post_type ] ?? 'درباره';
    }

    ob_start();
    ?>
    <div class="exchange-about" id="<?php echo esc_attr( $uid ); ?>">
        <h2 class="exchange-about__title"><?php echo $label; ?></h2>

        <div class="exchange-about__body">
            <div class="exchange-about__content">
                <?php 
                // Safely output content
                echo apply_filters( 'the_content', $content ); 
                ?>
            </div>
            <div class="exchange-about__fade"></div>
        </div>

        <button class="exchange-about__toggle" aria-expanded="false">
            <span class="exchange-about__toggle-text">مشاهده توضیحات کامل</span>
            <i class="fas fa-chevron-down exchange-about__chevron"></i>
        </button>
    </div>

    <?php
    static $css_js_printed = false;
    if ( ! $css_js_printed ) :
        $css_js_printed = true;
    ?>
    <style>
    .exchange-about {
        background: #ffffff;
        border-radius: 20px;
        padding: 28px 24px 20px;
        border: 1px solid #f0f0f0;
        box-shadow: 0 8px 24px rgba(0,0,0,0.03);
        direction: rtl;
        text-align: right;
        margin-bottom: 30px;
    }
    .exchange-about__title {
        font-size: 18px;
        font-weight: 700;
        color: #1a1a1a;
        margin: 0 0 16px;
        padding: 0;
    }
    .exchange-about__body {
        position: relative;
        max-height: 108px;
        overflow: hidden;
        transition: max-height 0.5s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .exchange-about__body.is-open {
        max-height: 5000px; /* Increased to ensure large content fits */
    }
    .exchange-about__content {
        font-size: 15px;
        line-height: 1.85;
        color: #555555;
    }
    .exchange-about__content p {
        margin: 0 0 12px;
    }
    .exchange-about__content p:last-child {
        margin-bottom: 0;
    }
    .exchange-about__fade {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        height: 64px;
        background: linear-gradient(to bottom, rgba(255,255,255,0), rgba(255,255,255,1));
        pointer-events: none;
        transition: opacity 0.35s ease;
    }
    .exchange-about__body.is-open .exchange-about__fade {
        opacity: 0;
    }
    .exchange-about__toggle {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        width: 100%;
        margin-top: 14px;
        background: none;
        border: none;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        color: #555555;
        transition: color 0.2s ease, background 0.2s ease;
        padding: 15px 0;
        border-radius: 8px;
    }
    .exchange-about__toggle:hover, .exchange-about__toggle:focus {
        color: #1a1a1a;
        background: #f9f9f9;
    }
    .exchange-about__chevron {
        font-size: 13px;
        transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .exchange-about__toggle[aria-expanded="true"] .exchange-about__chevron {
        transform: rotate(180deg);
    }

    @media (max-width: 768px) {
        .exchange-about {
            padding: 22px 18px 16px;
            border-radius: 16px;
        }
        .exchange-about__title {
            font-size: 16px;
        }
        .exchange-about__content {
            font-size: 14px;
        }
    }
    </style>

    <script>
    (function () {
        // Using Event Delegation makes this work flawlessly in Elementor 
        // and supports multiple shortcodes on the exact same page.
        document.addEventListener('click', function (e) {
            var toggleBtn = e.target.closest('.exchange-about__toggle');
            if (!toggleBtn) return;

            var container = toggleBtn.closest('.exchange-about');
            if (!container) return;

            var body = container.querySelector('.exchange-about__body');
            var label = toggleBtn.querySelector('.exchange-about__toggle-text');

            if (body) {
                var isOpen = body.classList.toggle('is-open');
                toggleBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                
                if (label) {
                    label.textContent = isOpen ? 'بستن توضیحات' : 'مشاهده توضیحات کامل';
                }
            }
        });
    })();
    </script>
    <?php endif; ?>

    <?php
    // Unlock the shortcode for subsequent independent uses on the same page
    $is_running = false;
    return ob_get_clean();
}
add_shortcode( 'exchange_about', 'exchange_about_shortcode' );