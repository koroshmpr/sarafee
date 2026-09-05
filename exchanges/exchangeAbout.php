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

    $queried_object = get_queried_object();
    $is_term = ( $queried_object instanceof WP_Term );

    if ( $is_term ) {
        $content = term_description( $queried_object->term_id );
        $uid     = 'ea_about_term_' . absint( $queried_object->term_id ) . '_' . wp_rand(100, 999);
    } else {
        $post_id = get_the_ID();
        if ( ! $post_id ) {
            return '';
        }
        $content   = get_the_content();
        $uid       = 'ea_about_' . absint( $post_id ) . '_' . wp_rand(100, 999);
        $post_type = get_post_type( $post_id );
    }

    $empty_fallback = '<style>.about-section, .exchange-about-section, .exchange-about { display: none !important; }</style>' .
        '<script>(function(){var s=document.currentScript;if(s){var sec=s.closest(".about-section, .exchange-about-section, .elementor-section, .e-con, .e-container, .elementor-widget");if(sec && !sec.textContent.trim()) sec.style.display="none";}})();</script>';

    if ( ! $content ) {
        return $empty_fallback;
    }

    // Lock the shortcode from running inside itself
    $is_running = true;

    if ( $atts['label'] !== '' ) {
        $label = esc_html( $atts['label'] );
    } else {
        if ( $is_term ) {
            $label = 'درباره ' . esc_html( $queried_object->name );
        } else {
            $labels = [
                'exchange' => 'درباره صرافی',
                'symbol'   => 'درباره ارز',
                'post'     => 'درباره این مطلب',
            ];
            // Fallback for ANY other post type
            $label = $labels[ $post_type ] ?? 'درباره';
        }
    }

    ob_start();
    ?>
    <div class="exchange-about" id="<?php echo esc_attr( $uid ); ?>">
        <h2 class="exchange-about__title"><?php echo $label; ?></h2>

        <div class="exchange-about__body">
            <div class="exchange-about__content" id="post_content">
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