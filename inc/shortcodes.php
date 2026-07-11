<?php
// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/**
 * Shortcode: [post-notice]
 * Replicates Next.js design without Tailwind dependencies
 */
function register_post_notice_shortcode($atts) {
    $args = shortcode_atts(array(
        'title'   => '',
        'content' => '',
        'color'   => 'progress',
    ), $atts);

    $color_map = array(
        'success'   => 'success',
        'green'     => 'success',
        'warning'   => 'warning',
        'yellow'    => 'warning',
        'error'     => 'error',
        'secondary' => 'secondary',
        'gray'      => 'secondary',
        'progress'  => 'progress',
    );

    $color_key = isset($color_map[$args['color']]) ? $color_map[$args['color']] : 'progress';
    $clean_content = stripslashes($args['content']);

    ob_start(); ?>
    <blockquote class="post-notice-box notice-color-<?php echo esc_attr($color_key); ?>">
        <?php if (!empty($args['title'])) : ?>
            <span class="notice-title"><?php echo esc_html($args['title']); ?></span>
        <?php endif; ?>

        <div class="notice-content">
            <?php echo wp_kses_post($clean_content); ?>
        </div>
    </blockquote>
    <?php
    return ob_get_clean();
}
add_shortcode('post-notice', 'register_post_notice_shortcode');