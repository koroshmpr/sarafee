<?php


function render_post_notice_shortcode($atts)
{
    // 1. Extract and normalize attributes
    $atts = shortcode_atts(
        array(
            'title' => '',
            'content' => '',
            'color' => 'secondary', // default fallback
        ),
        $atts,
        'post-notice'
    );

    // 2. Sanitize inputs for security
    $title = sanitize_text_field($atts['title']);
    $content = wp_kses_post($atts['content']); // Allow bold/italic inside content
    $color = in_array($atts['color'], ['success', 'warning', 'error', 'secondary', 'green', 'yellow', 'gray']) ? $atts['color'] : 'secondary';

    // 3. Build the output HTML
    // We add a dynamic class "notice-color-{color}" to handle the variations
    $output = '<blockquote class="post-notice-box notice-color-' . esc_attr($color) . '">';

    // Only render title if it exists
    if (!empty($title)) {
        $output .= '<span class="notice-title">' . esc_html($title) . '</span>';
    }

    $output .= '<p class="notice-content">' . $content . '</p>';
    $output .= '</blockquote>';

    return $output;
}

// 4. Register the shortcode
add_shortcode('post-notice', 'render_post_notice_shortcode');