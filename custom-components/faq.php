<?php
function my_acf_faq_shortcode( $atts ) {
    $queried_object = get_queried_object();
    $is_term = ( $queried_object instanceof WP_Term );

    $selector = false;
    if ( $is_term ) {
        $selector = 'term_' . $queried_object->term_id;
    } else {
        $selector = get_the_ID();
    }

    if ( ! $selector || ! have_rows( 'faqs', $selector ) ) {
        return '';
    }

    // Collect all rows first so we can use the data for both the accordion and schema.
    $faqs = [];
    while ( have_rows( 'faqs', $selector ) ) : the_row();
        $faqs[] = [
            'question' => get_sub_field( 'question' ),
            'answer'   => get_sub_field( 'answer' ),
        ];
    endwhile;

    if ( empty( $faqs ) ) {
        return '';
    }

    ob_start();
    ?>
    <div class="elementor-widget-container faq-title">
        <p class="elementor-heading-title elementor-size-default">سوالات متداول</p>
    </div>

    <div class="elementor-accordion">
        <?php foreach ( $faqs as $i => $faq ) :
            $n = $i + 1;
        ?>
        <div class="elementor-accordion-item">
            <div class="elementor-tab-title elementor-clearfix p-2"
                 data-tab="<?php echo $n; ?>"
                 role="button"
                 aria-expanded="false"
                 tabindex="0"
                 aria-controls="faq-content-<?php echo $n; ?>">
                <span class="elementor-accordion-icon elementor-accordion-icon-right" aria-hidden="true">
                    <span class="elementor-accordion-icon-closed">+</span>
                    <span class="elementor-accordion-icon-opened" style="display:none;">–</span>
                </span>
                <p class="elementor-accordion-title w-max"><?php echo esc_html( $faq['question'] ); ?></p>
            </div>
            <div id="faq-content-<?php echo $n; ?>"
                 class="elementor-tab-content elementor-clearfix"
                 data-tab="<?php echo $n; ?>"
                 role="region"
                 style="display:none;"
                 hidden="hidden">
                <article class="p-1"><?php echo wp_kses_post( $faq['answer'] ); ?></article>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- FAQPage structured data -->
    <?php if ( ! is_tax( 'city' ) && ! is_singular( 'symbol' ) ) : ?>
    <script type="application/ld+json">
    <?php
    $schema = [
        '@context'   => 'https://schema.org',
        '@type'      => 'FAQPage',
        'mainEntity' => array_map( function ( $faq ) {
            return [
                '@type' => 'Question',
                'name'  => wp_strip_all_tags( $faq['question'] ),
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => wp_strip_all_tags( $faq['answer'] ),
                ],
            ];
        }, $faqs ),
    ];
    echo wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
    ?>
    </script>
    <?php endif; ?>

    <script>
    document.addEventListener("DOMContentLoaded", function () {
        document.querySelectorAll(".elementor-tab-title").forEach(function (tab) {

            const toggleAccordion = function () {
                const content = tab.nextElementSibling;
                const isOpen  = content.style.display === "block";

                // Close all
                document.querySelectorAll(".elementor-tab-content").forEach(function (c) {
                    c.style.display = "none";
                    c.hidden = true;
                });
                document.querySelectorAll(".elementor-tab-title").forEach(function (t) {
                    t.setAttribute("aria-expanded", "false");
                    var ic = t.querySelector(".elementor-accordion-icon-closed");
                    var io = t.querySelector(".elementor-accordion-icon-opened");
                    if ( ic && io ) { ic.style.display = "inline"; io.style.display = "none"; }
                });

                // Open clicked one
                if ( ! isOpen ) {
                    content.style.display = "block";
                    content.hidden = false;
                    tab.setAttribute("aria-expanded", "true");
                    var ic = tab.querySelector(".elementor-accordion-icon-closed");
                    var io = tab.querySelector(".elementor-accordion-icon-opened");
                    if ( ic && io ) { ic.style.display = "none"; io.style.display = "inline"; }
                }
            };

            tab.addEventListener("click", toggleAccordion);
            tab.addEventListener("keydown", function (e) {
                if ( e.key === "Enter" || e.key === " " ) {
                    e.preventDefault();
                    toggleAccordion();
                }
            });
        });
    });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode( 'acf_faqs', 'my_acf_faq_shortcode' );
