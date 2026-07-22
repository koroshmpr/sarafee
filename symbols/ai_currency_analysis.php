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
    static $sarafee_ai_styles_printed = false;
    if ( !$sarafee_ai_styles_printed ) {
        $sarafee_ai_styles_printed = true;
        ?>
        <style>
            :root {
                --ai-primary: #8b5cf6;
                --ai-primary-hover: #7c3aed;
                --ai-primary-glow: rgba(139, 92, 246, 0.15);
                --ai-accent: #ec4899;
                --ai-card-bg: #ffffff;
                --ai-card-border: rgba(139, 92, 246, 0.12);
                --ai-shadow: 0 10px 30px -10px rgba(139, 92, 246, 0.08);
                --ai-shadow-hover: 0 12px 35px -8px rgba(139, 92, 246, 0.15);
                --ai-text-main: #1f2937;
                --ai-text-muted: #6b7280;
                --ai-success: #10b981;
                --ai-danger: #ef4444;
            }

            .sarafee-ai-card {
                background: var(--ai-card-bg);
                border: 1px solid var(--ai-card-border);
                border-radius: 16px;
                padding: 24px;
                margin-bottom: 24px;
                box-shadow: var(--ai-shadow);
                direction: rtl;
                text-align: right;
                position: relative;
                overflow: hidden;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }

            .sarafee-ai-card:hover {
                box-shadow: var(--ai-shadow-hover);
                border-color: rgba(139, 92, 246, 0.25);
            }

            .sarafee-ai-card::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                height: 4px;
                background: linear-gradient(90deg, var(--ai-primary), var(--ai-accent));
            }

            .sarafee-ai-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
                gap: 15px;
            }

            .sarafee-ai-title {
                display: flex;
                align-items: center;
                gap: 12px;
            }

            .sarafee-ai-icon {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 36px;
                height: 36px;
                border-radius: 10px;
                background: var(--ai-primary-glow);
                color: var(--ai-primary);
                flex-shrink: 0;
            }

            .sarafee-ai-icon svg {
                width: 20px;
                height: 20px;
            }

            .sarafee-ai-title h3 {
                margin: 0 !important;
                font-size: 1.15rem;
                font-weight: 700;
                color: var(--ai-text-main);
            }

            .sarafee-ai-body p {
                font-size: 0.95rem;
                line-height: 1.8;
                color: #4b5563;
                margin: 0;
                text-align: justify;
            }
        </style>
        <?php
    }
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