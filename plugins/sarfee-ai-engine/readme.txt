=== Sarfee AI Engine (GEO & AEO) ===
Contributors: sarfee
Tags: seo, geo, aeo, ai, llms.txt, schema, indexnow, perplexity, chatgpt
Requires at least: 5.8
Tested up to: 6.7
Stable tag: 1.0.0
License: GPLv2 or later

افزونه اختصاصی بهینه‌سازی سایت صرفی برای موتورهای جستجوی هوش مصنوعی (Generative Engine Optimization).

== Description ==

پلاگین **Sarfee AI Engine** به طور ویژه برای نیازهای سایت «صرفی» و آماده‌سازی داده‌های صرافی‌ها، ارزها و مقالات برای موتورهای پاسخ‌گوی هوش مصنوعی (مانند Perplexity, ChatGPT Search, Claude, Google Gemini) توسعه یافته است.

=== امکانات کلیدی ===
1. **پروتکل جهانی llms.txt و llms-full.txt**:
   - تولید خودکار خلاصه‌های ساختاریافته از صرافی‌ها (رتبه، وضعیت مجوز، ارزهای دیجیتال، آدرس و مشخصات).
   - سیستم کش خودکار ۱۲ ساعته با Transient API برای حفظ بالاترین سرعت لود.
   - تزریق متاتگ `<link rel="alternate" type="text/markdown">` در هدر تمام صفحات.

2. **مدیریت ربات‌های AI و ai.txt**:
   - ارائه فایل `/ai.txt` با مجوزهای استاندارد استناد (Attribution) و استنتاج.
   - بهینه‌سازی دسترسی کراولرهای GPTBot, PerplexityBot, ClaudeBot, Google-Extended در robots.txt.
   - تنظیم هدرهای X-Robots-Tag برای دریافت حداکثر اسنیپت و استناد در AI Overviews.

3. **گراف دانش و اسکیمای اختصاصی صرافی‌ها (JSON-LD)**:
   - تولید خودکار اسکیمای استاندارد `FinancialService` و `ExchangeOffice` برای صفحات تکی صرافی‌ها.
   - اتصال فیلدهای ACF (مجوزها، رتبه، ارز دیجیتال، تلفن، آدرس و نقشه) به هویت وب‌سایت در `@graph`.
   - شورت‌کد `[sarfee_ai_facts]` جهت نمایش باکس چکیده فکت‌های کلیدی صرافی مناسب استناد هوش مصنوعی.

4. **ارسال بلادرنگ تغییرات با پروتکل IndexNow**:
   - اطلاع‌رسانی خودکار و لحظه‌ای به موتورهای Bing, Copilot و Yandex هنگام انتشار یا به‌روزرسانی صرافی‌ها، نمادها و نوشته‌ها.
   - مدیریت کلید امنیتی و اعتبارسنجی خودکار.
   - لاگ آخرین ارسال‌ها در پیشخوان وردپرس.

5. **پیشخوان مدیریت فارسی (AI Readiness Dashboard)**:
   - مشاهده وضعیت سلامت و لینک‌های تست.
   - دکمه تخلیه دستی کش llms.txt.
   - دکمه تست ارسال پینگ آزمایشی IndexNow.

== Installation ==

1. پوشه افزونه در مسیر `wp-content/plugins/sarfee-ai-engine/` قرار گرفته است.
2. به منوی **افزونه‌ها > افزونه‌های نصب‌شده** در پیشخوان وردپرس بروید.
3. افزونه **Sarfee AI Engine (GEO & AEO)** را فعال (Activate) کنید.
4. تنظیمات و گزارش‌های آن در منوی **تنظیمات > هوش مصنوعی صرفی (GEO)** در دسترس خواهد بود.
