<?php
/**
 * Shortcode 1: AI Analysis
 * Usage: [ai_currency_analysis]
 */
add_shortcode( 'ai_currency_analysis', 'sarafee_ai_analysis_shortcode' );
function sarafee_ai_analysis_shortcode() {
    global $post;
    if ( ! $post ) return '';

    // دریافت داده از دیتابیس (بر اساس نام فیلدی که در n8n تنظیم کردید)
    $analysis = get_post_meta( $post->ID, 'analysis', true );
    
    // اگر خالی بود، تلاش برای خواندن از فیلد ترکیبی (در صورت تغییر ساختار)
    if ( empty( $analysis ) ) {
        $feature_price = get_post_meta( $post->ID, 'feature_price', true );
        $decoded = is_string( $feature_price ) ? json_decode( $feature_price, true ) : $feature_price;
        $analysis = $decoded['analysis'] ?? '';
    }

    if ( empty( $analysis ) ) return '';

    ob_start();
    ?>
    <div class="sarafee-ai-card">
        <div class="sarafee-ai-header">
            <div class="sarafee-ai-title">
                <span class="sarafee-ai-icon">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456zM16.894 20.567L16.5 21.75l-.394-1.183a2.25 2.25 0 00-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 001.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 001.423 1.423l1.183.394-1.183.394a2.25 2.25 0 00-1.423 1.423z"></path>
                    </svg>
                </span>
                <h3>تحلیل هوشمند بازار</h3>
            </div>
        </div>
        <div class="sarafee-ai-body">
            <p><?php echo nl2br( esc_html( $analysis ) ); ?></p>
        </div>
    </div>
    <?php
    return ob_get_clean();
}