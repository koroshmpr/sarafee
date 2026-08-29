<?php
/**
 * Comments template — Sarfee child theme.
 * Overrides astra/comments.php. Safe from parent-theme updates.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( post_password_required() || false === astra_get_option( 'enable-comments-area', true ) ) {
    return;
}

$comment_form_position = astra_get_option( 'comment-form-position', 'below' );
$container_selector    = 'outside' === astra_get_option( 'comments-box-placement' )
    ? 'ast-container--' . astra_get_option( 'comments-box-container-width', '' )
    : '';

// ── Build comment_form() args ─────────────────────────────────────────────────
$commenter = wp_get_current_commenter();
$req       = get_option( 'require_name_email' );
$req_attr  = $req ? ' required' : '';
$req_star  = $req ? ' <span class="scf__req" aria-hidden="true">*</span>' : '';

$scf_fields = [
    'author' => sprintf(
        '<div class="scf__field"><label class="scf__label" for="author">نام%s</label>'
        . '<input id="author" name="author" type="text" class="scf__input" value="%s" autocomplete="name"%s></div>',
        $req_star, esc_attr( $commenter['comment_author'] ), $req_attr
    ),
    'email' => sprintf(
        '<div class="scf__field"><label class="scf__label" for="email">ایمیل%s</label>'
        . '<input id="email" name="email" type="email" class="scf__input" value="%s" autocomplete="email"%s></div>',
        $req_star, esc_attr( $commenter['comment_author_email'] ), $req_attr
    ),
];

$scf_args = [
    'fields'               => $scf_fields,
    'comment_field'        => '<div class="scf__field"><label class="scf__label" for="comment">دیدگاه شما<span class="scf__req" aria-hidden="true"> *</span></label><textarea id="comment" name="comment" class="scf__textarea" rows="6" required></textarea></div>',
    'title_reply'          => 'دیدگاه بگذارید',
    'title_reply_to'       => 'پاسخ به %s',
    'cancel_reply_link'    => 'لغو پاسخ',
    'label_submit'         => 'ارسال دیدگاه',
    'class_form'           => 'scf__form',
    'submit_button'        => '<button name="%1$s" type="submit" id="%2$s" class="scf__btn %3$s" value="%4$s">ارسال دیدگاه</button>',
    'submit_field'         => '<div class="scf__submit-row">%1$s %2$s</div>',
    'logged_in_as'         => '',   // suppress the "logged in as…" message
    'comment_notes_before' => '',
    'comment_notes_after'  => '',
    'must_log_in'          => '',
];

// Wrap name + email in a two-column row (closures kept as variables for remove_action)
$scf_open  = static function () { echo '<div class="scf__meta-row">'; };
$scf_close = static function () { echo '</div>'; };

?>

<div id="comments" class="comments-area comment-form-position-<?php echo esc_attr( $comment_form_position ); ?> <?php echo esc_attr( $container_selector ); ?>">

    <?php astra_comments_before(); ?>

    <?php if ( 'above' === $comment_form_position ) : ?>
        <?php
        add_action( 'comment_form_before_fields', $scf_open );
        add_action( 'comment_form_after_fields',  $scf_close );
        comment_form( $scf_args );
        remove_action( 'comment_form_before_fields', $scf_open );
        remove_action( 'comment_form_after_fields',  $scf_close );
        ?>
    <?php endif; ?>

    <?php if ( have_comments() ) : ?>

        <?php astra_markup_open( 'comment-count-wrapper' ); ?>
        <?php $title_tag = apply_filters( 'astra_comment_title_tag', 'h3' ); ?>
        <<?php echo esc_attr( $title_tag ); ?> class="comments-title">
            <?php
            echo esc_html( apply_filters(
                'astra_comment_form_title',
                sprintf(
                    _nx(
                        '%1$s دیدگاه برای "%2$s"',
                        '%1$s دیدگاه برای "%2$s"',
                        get_comments_number(),
                        'comments title',
                        'astra'
                    ),
                    number_format_i18n( get_comments_number() ),
                    get_the_title()
                )
            ) );
            ?>
        </<?php echo esc_attr( $title_tag ); ?>>
        <?php astra_markup_close( 'comment-count-wrapper' ); ?>

        <?php if ( get_post_type() !== 'post' ) : ?>
        <!-- نوتیس حقوقی نظرات کاربران -->
        <div class="scf-comments-legal-notice">
            <div class="scf-notice-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
            </div>
            <div class="scf-notice-text">
                نظرات کاربران بیانگر تجربه و دیدگاه نویسندگان آن‌هاست و لزوماً دیدگاه این وب‌سایت نیست. ما صحت تمام ادعاهای کاربران را تضمین نمی‌کنیم. برای گزارش نظر جعلی، نادرست، توهین‌آمیز یا احتمالاً غیرقانونی از دکمه «گزارش این نظر» استفاده کنید.
            </div>
        </div>
        <?php endif; ?>

        <?php if ( get_comment_pages_count() > 1 && get_option( 'page_comments' ) ) : ?>
        <nav id="comment-nav-above" class="navigation comment-navigation" aria-label="<?php esc_attr_e( 'Comments Navigation', 'astra' ); ?>">
            <div class="nav-links">
                <div class="nav-previous"><?php previous_comments_link( astra_default_strings( 'string-comment-navigation-previous', false ) ); ?></div>
                <div class="nav-next"><?php next_comments_link( astra_default_strings( 'string-comment-navigation-next', false ) ); ?></div>
            </div>
        </nav>
        <?php endif; ?>

        <ol class="ast-comment-list">
            <?php wp_list_comments( [ 'callback' => 'astra_theme_comment', 'style' => 'ol' ] ); ?>
        </ol>

        <?php
        // Determine the next page URL for AJAX Load More
        $next_comments_url = '';
        if ( get_comment_pages_count() > 1 && get_option( 'page_comments' ) ) {
            $prev_html = get_previous_comments_link( '' );
            $next_html = get_next_comments_link( '' );
            
            if ( 'last' === get_option( 'default_comments_page' ) ) {
                if ( preg_match( '/href="([^"]+)"/', $prev_html, $matches ) ) {
                    $next_comments_url = $matches[1];
                }
            } else {
                if ( preg_match( '/href="([^"]+)"/', $next_html, $matches ) ) {
                    $next_comments_url = $matches[1];
                }
            }
        }
        ?>

        <?php if ( ! empty( $next_comments_url ) ) : ?>
            <div class="scf-load-more-wrapper">
                <button id="scf-load-more-comments-btn" class="scf-load-more-btn" data-next-url="<?php echo esc_url( $next_comments_url ); ?>">
                    <span class="btn-text">مشاهده دیدگاه‌های بیشتر</span>
                    <span class="btn-spinner" style="display: none;">
                        <svg class="spinner" viewBox="0 0 50 50">
                            <circle class="path" cx="25" cy="25" r="20" fill="none" stroke-width="5" stroke="#fff"></circle>
                        </svg>
                    </span>
                </button>
            </div>

            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const loadMoreBtn = document.getElementById('scf-load-more-comments-btn');
                if (!loadMoreBtn) return;

                loadMoreBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    let nextUrl = loadMoreBtn.getAttribute('data-next-url');
                    if (!nextUrl) return;

                    // Show spinner
                    const textSpan = loadMoreBtn.querySelector('.btn-text');
                    const spinnerSpan = loadMoreBtn.querySelector('.btn-spinner');
                    if (textSpan) textSpan.textContent = 'در حال بارگذاری...';
                    if (spinnerSpan) spinnerSpan.style.display = 'inline-block';
                    loadMoreBtn.disabled = true;

                    fetch(nextUrl)
                        .then(response => response.text())
                        .then(html => {
                            const parser = new DOMParser();
                            const doc = parser.parseFromString(html, 'text/html');
                            
                            // Get the comment list elements from the fetched page
                            const newComments = doc.querySelectorAll('.ast-comment-list > li');
                            const targetList = document.querySelector('.ast-comment-list');
                            
                            if (newComments.length && targetList) {
                                newComments.forEach(comment => {
                                    // Append with a simple fade-in transition
                                    comment.style.opacity = '0';
                                    comment.style.transition = 'opacity 0.4s ease';
                                    targetList.appendChild(comment);
                                    setTimeout(() => {
                                        comment.style.opacity = '1';
                                    }, 50);
                                });
                            }
                            
                            // Find the next link from the fetched page
                            const defaultPageSetting = '<?php echo esc_js( get_option( 'default_comments_page' ) ); ?>';
                            let nextLinkSelector = '.nav-next a';
                            if (defaultPageSetting === 'last') {
                                nextLinkSelector = '.nav-previous a';
                            }
                            
                            const newNextLink = doc.querySelector('#comment-nav-below ' + nextLinkSelector + ', #comment-nav-above ' + nextLinkSelector);
                            
                            if (newNextLink && newNextLink.getAttribute('href')) {
                                loadMoreBtn.setAttribute('data-next-url', newNextLink.getAttribute('href'));
                                if (textSpan) textSpan.textContent = 'مشاهده دیدگاه‌های بیشتر';
                                if (spinnerSpan) spinnerSpan.style.display = 'none';
                                loadMoreBtn.disabled = false;
                            } else {
                                // No more pages, remove button wrapper
                                const wrapper = document.querySelector('.scf-load-more-wrapper');
                                if (wrapper) {
                                    wrapper.innerHTML = '<p class="scf-no-more-comments">دیدگاه دیگری وجود ندارد.</p>';
                                }
                            }
                        })
                        .catch(err => {
                            console.error('Error loading comments:', err);
                            if (textSpan) textSpan.textContent = 'خطا در بارگذاری. تلاش مجدد';
                            if (spinnerSpan) spinnerSpan.style.display = 'none';
                            loadMoreBtn.disabled = false;
                        });
                });
            });
            </script>
        <?php endif; ?>

        <?php if ( get_comment_pages_count() > 1 && get_option( 'page_comments' ) ) : ?>
        <nav id="comment-nav-below" class="navigation comment-navigation" aria-label="<?php esc_attr_e( 'Comments Navigation', 'astra' ); ?>">
            <div class="nav-links">
                <div class="nav-previous"><?php previous_comments_link( astra_default_strings( 'string-comment-navigation-previous', false ) ); ?></div>
                <div class="nav-next"><?php next_comments_link( astra_default_strings( 'string-comment-navigation-next', false ) ); ?></div>
            </div>
        </nav>
        <?php endif; ?>

    <?php endif; ?>

    <?php if ( ! comments_open() && get_comments_number() && post_type_supports( get_post_type(), 'comments' ) ) : ?>
        <p class="no-comments"><?php echo esc_html( astra_default_strings( 'string-comment-closed', false ) ); ?></p>
    <?php endif; ?>

    <?php if ( 'below' === $comment_form_position ) : ?>
        <?php
        add_action( 'comment_form_before_fields', $scf_open );
        add_action( 'comment_form_after_fields',  $scf_close );
        comment_form( $scf_args );
        remove_action( 'comment_form_before_fields', $scf_open );
        remove_action( 'comment_form_after_fields',  $scf_close );
        ?>
    <?php endif; ?>

    <?php astra_comments_after(); ?>

</div><!-- #comments -->

<?php do_action( 'astra_after_comments_module' ); ?>
