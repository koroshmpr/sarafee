<?php 
add_action('rest_api_init', function () {
    register_rest_route('custom/v1', '/update-currency/', array(
        'methods' => 'POST',
        'callback' => 'handle_currency_auto_update',
        'permission_callback' => '__return_true', // در محیط واقعی حتماً یک سکرت‌کی در هدر چک کنید
    ));
});

function handle_currency_auto_update($request) {
    // دریافت سمبل و تبدیل آن به حروف کوچک برای جستجو در اسلاگ
    $symbol_slug = strtolower(sanitize_text_field($request->get_param('symbol'))); 
    
    $analysis_text = wp_kses_post($request->get_param('analysis'));
    $future_prices = $request->get_param('future_prices'); 

    // پیدا کردن پست بر اساس اسلاگ
    $args = array(
        'name'           => $symbol_slug, // پارامتر name دقیقاً اسلاگ پست را جستجو می‌کند
        'post_type'      => 'symbol',     // تغییر پست‌تایپ به symbol
        'post_status'    => 'publish',    // جستجو فقط در پست‌های منتشر شده (برای اطمینان)
        'posts_per_page' => 1
    );
    
    $posts = get_posts($args);
    
    if (empty($posts)) {
        return new WP_REST_Response(array('status' => 'error', 'message' => 'Currency not found for slug: ' . $symbol_slug), 404);
    }

    $post_id = $posts[0]->ID;

    // آپدیت فیلدهای ACF
    update_field('analysis', $analysis_text, $post_id);
    update_field('future_prices', is_array($future_prices) ? json_encode($future_prices) : $future_prices, $post_id);

    return new WP_REST_Response(array('status' => 'success', 'message' => 'Fields updated for ' . strtoupper($symbol_slug)), 200);
}