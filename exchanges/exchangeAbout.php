<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function exchange_about_shortcode() {
    $content = get_the_content();

    if ( ! $content ) {
        return '';
    }

    ob_start();
    ?>
    <div class="exchange-about">
        <h2 class="exchange-about__title">درباره صرافی</h2>

        <div class="exchange-about__body" id="exchangeAboutBody">
            <div class="exchange-about__content">
                <?php $content; ?>
            </div>
            <div class="exchange-about__fade" id="exchangeAboutFade"></div>
        </div>

        <button class="exchange-about__toggle" id="exchangeAboutToggle" aria-expanded="false">
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
        max-height: 2000px;
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
        padding: 8px 0;
        transition: color 0.2s ease;
    }
    .exchange-about__toggle:hover {
        color: #1a1a1a;
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
        var toggle = document.getElementById('exchangeAboutToggle');
        var body   = document.getElementById('exchangeAboutBody');
        var label  = toggle ? toggle.querySelector('.exchange-about__toggle-text') : null;

        if ( ! toggle || ! body ) return;

        toggle.addEventListener('click', function () {
            var isOpen = body.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            label.textContent = isOpen ? 'بستن توضیحات' : 'مشاهده توضیحات کامل';
        });
    })();
    </script>
    <?php endif; ?>

    <?php
    return ob_get_clean();
}
add_shortcode( 'exchange_about', 'exchange_about_shortcode' );
