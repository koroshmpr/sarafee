<?php
function my_acf_faq_shortcode( $atts ) {
    $atts = shortcode_atts( [
        'title' => 'سوالات متداول',
    ], $atts );

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
        $q = get_sub_field( 'question' );
        $a = get_sub_field( 'answer' );
        if ( ! empty( $q ) && ! empty( $a ) ) {
            $faqs[] = [
                'question' => $q,
                'answer'   => $a,
            ];
        }
    endwhile;

    if ( empty( $faqs ) ) {
        return '';
    }

    $unique_id = 'scf_faq_' . wp_rand( 100, 999 );

    ob_start();
    ?>
    <section class="scf-faq-card" aria-label="<?php echo esc_attr( $atts['title'] ); ?>" dir="rtl">
        <h2 class="scf-faq-card__title"><?php echo esc_html( $atts['title'] ); ?></h2>

        <div class="scf-faq-list" role="presentation">
            <?php foreach ( $faqs as $i => $faq ) :
                $n = $i + 1;
                $item_content_id = $unique_id . '_content_' . $n;
            ?>
            <div class="scf-faq-item">
                <button type="button"
                        class="scf-faq-header"
                        aria-expanded="false"
                        aria-controls="<?php echo esc_attr( $item_content_id ); ?>">
                    <span class="scf-faq-question"><?php echo esc_html( $faq['question'] ); ?></span>
                    <span class="scf-faq-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </span>
                </button>
                <div id="<?php echo esc_attr( $item_content_id ); ?>"
                     class="scf-faq-body"
                     role="region"
                     hidden>
                    <div class="scf-faq-content">
                        <?php echo wp_kses_post( $faq['answer'] ); ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

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

    <?php
    static $faq_script_printed = false;
    if ( ! $faq_script_printed ) :
        $faq_script_printed = true;
    ?>
    <script>
    (function () {
        document.addEventListener("click", function (e) {
            var header = e.target.closest(".scf-faq-header");
            if (!header) return;

            var item = header.closest(".scf-faq-item");
            var card = header.closest(".scf-faq-card");
            if (!item || !card) return;

            var body = item.querySelector(".scf-faq-body");
            if (!body) return;

            var isExpanded = header.getAttribute("aria-expanded") === "true";

            // Close all other items in this FAQ card
            card.querySelectorAll(".scf-faq-item").forEach(function (otherItem) {
                if (otherItem !== item) {
                    var otherHeader = otherItem.querySelector(".scf-faq-header");
                    var otherBody = otherItem.querySelector(".scf-faq-body");
                    if (otherHeader) otherHeader.setAttribute("aria-expanded", "false");
                    if (otherBody) otherBody.hidden = true;
                    otherItem.classList.remove("is-open");
                }
            });

            // Toggle clicked item
            if (isExpanded) {
                header.setAttribute("aria-expanded", "false");
                body.hidden = true;
                item.classList.remove("is-open");
            } else {
                header.setAttribute("aria-expanded", "true");
                body.hidden = false;
                item.classList.add("is-open");
            }
        });
    })();
    </script>
    <?php endif; ?>
    <?php
    return ob_get_clean();
}
add_shortcode( 'acf_faqs', 'my_acf_faq_shortcode' );

