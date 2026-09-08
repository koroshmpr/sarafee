<?php
add_shortcode('kk_persian_rating', 'custom_kk_persian_rating_display');

function custom_kk_persian_rating_display() {
    $post_id = get_the_ID();
    if (!$post_id) return '';

    // Fetch the total rating score and number of votes from KK Star Ratings meta
    $score = (int) get_post_meta($post_id, '_kksr_ratings', true);
    $votes = (int) get_post_meta($post_id, '_kksr_casts', true);

    // NEW: If there are no votes, return nothing (hides the widget entirely)
    if ($votes === 0) {
        return '';
    }

    // Calculate the average
    $avg = ($score > 0 && $votes > 0) ? round((float)($score / $votes), 1) : 0;

    // Ensure it always shows 1 decimal place
    $avg_formatted = number_format($avg, 1);

    // Arrays to replace English numbers with Persian numbers
    $english_digits = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9');
    $persian_digits = array('۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹');

    // Swap the numbers out
    $persian_avg = str_replace($english_digits, $persian_digits, $avg_formatted);

    // Generate the stars (Solid star: ★, Empty star: ☆)
    $full_stars = round($avg);
    $stars_html = '<span style="color: #E2A63B; letter-spacing: 2px;">';

    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $full_stars) {
            $stars_html .= '★';
        } else {
            $stars_html .= '☆';
        }
    }
    $stars_html .= '</span>';

    // Output the custom HTML
    return '<div class="kk-persian-read-only" style="display: flex; align-items: center; justify-content:center; gap: 8px; font-size: 18px; direction: rtl;">'
        . '<span style="font-weight: bold; color: #333;">' . $persian_avg . '</span>'
        . $stars_html
        . '</div>';
}