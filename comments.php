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
<style>
/* ─────────────────────────────────────────────────────────────────────────────
   Sarfee comment form  —  all selectors prefixed with #respond for specificity
   ───────────────────────────────────────────────────────────────────────────── */
#respond {
    background: #fff;
    border-radius: 2rem;
    padding: 2rem 2rem 1.75rem;
    border: 1px solid #f0f0f0;
    box-shadow: 0 2px 16px rgba(0,0,0,.05);
    margin: 2.5rem 0 0;
    box-sizing: border-box;
    direction: rtl;
    font-family: inherit;
}

/* title */
#respond .comment-reply-title {
    font-size: 1.0625rem;
    font-weight: 700;
    color: #111827;
    margin: 0 0 1.75rem;
    padding: 0;
    line-height: 1.3;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border: none;
}
#respond .comment-reply-title small { font-size: .875rem; }
#respond .comment-reply-title small a {
    font-weight: 500;
    color: #6b7280;
    text-decoration: none;
    transition: color .15s;
}
#respond .comment-reply-title small a:hover { color: #0A1128; }

/* form layout */
#respond .scf__form {
    display: flex;
    flex-direction: column;
    gap: 1.125rem;
}
#respond .scf__meta-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}
#respond .scf__field {
    display: flex;
    flex-direction: column;
    gap: .375rem;
    margin: 0;
    padding: 0;
}

/* labels */
#respond .scf__label {
    display: block;
    font-size: .875rem;
    font-weight: 600;
    color: #374151;
    margin: 0;
}
#respond .scf__req { color: #ef4444; }

/* inputs & textarea */
#respond .scf__input,
#respond .scf__textarea {
    display: block;
    width: 100%;
    padding: .75rem 1rem;
    font-size: .9375rem;
    font-family: inherit;
    color: #111827;
    background: #f8f9fa;
    border: 1.5px solid #e5e7eb;
    border-radius: .875rem;
    outline: none;
    transition: border-color .18s, box-shadow .18s, background .18s;
    box-sizing: border-box;
    direction: rtl;
    box-shadow: none;
    margin: 0;
}
#respond .scf__input:focus,
#respond .scf__textarea:focus {
    border-color: #0A1128;
    box-shadow: 0 0 0 3px rgba(10,17,40,.07);
    background: #fff;
}
#respond .scf__textarea {
    resize: none;
    min-height: 9.5rem;
    line-height: 1.75;
}

/* submit row */
#respond .scf__submit-row {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: 1rem;
    margin: .25rem 0 0;
}
#respond .scf__btn {
    display: inline-flex;
    align-items: center;
    padding: .8rem 2.25rem;
    font-size: .9375rem;
    font-weight: 700;
    font-family: inherit;
    background: #0A1128;
    color: #fff;
    border: none;
    border-radius: 1rem;
    cursor: pointer;
    line-height: 1;
    text-decoration: none;
    transition: background .18s, box-shadow .18s;
    box-shadow: 0 2px 10px rgba(10,17,40,.22);
}
#respond .scf__btn:hover  { background: #1a2a50; box-shadow: 0 4px 16px rgba(10,17,40,.3); color: #fff; }
#respond .scf__btn:active { background: #060c1a; }
#respond .scf__btn:disabled { opacity: .5; cursor: default; }

/* hide any leftover WP notices inside the form */
#respond .comment-form > p:not([class*="scf"]),
#respond .logged-in-as,
#respond .comment-notes,
#respond .comment-form-cookies-consent {
    display: none !important;
}

/* ── existing comments list ──────────────────────────────────────────── */
.comments-area {
    direction: rtl;
    margin-top: 3rem;
}
.comments-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: #111827;
    margin-bottom: 2rem;
    padding: 0.75rem!important;
    border-bottom: 2px solid #f3f4f6;
}

/* Hide default navigation links unless user has JS disabled */
.comment-navigation {
    display: none !important;
}

/* Comments list styling */
.ast-comment-list {
    list-style: none !important;
    margin: 0 0 2rem 0 !important;
    padding: 0 !important;
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}
.ast-comment-list li {
    list-style: none !important;
    margin: 0 !important;
    padding: 0 !important;
}

/* Comment card wrapper */
.ast-comment {
    background: #ffffff;
    border: 1px solid #f0f0f0;
    border-radius: 1.25rem;
    padding: 1.5rem;
    box-shadow: 0 2px 12px rgba(0,0,0,0.02);
    display: flex;
    flex-direction: column;
    gap: 1rem;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.ast-comment:hover {
    border-color: #e5e7eb;
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
}

/* Author and meta info row */
.ast-comment-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}
.ast-comment-avatar-wrap {
    flex-shrink: 0;
}
.ast-comment-avatar-wrap img {
    border-radius: 50% !important;
    border: 2px solid #f3f4f6;
    display: block;
    width: 48px;
    height: 48px;
    object-fit: cover;
}

.ast-comment-data-wrap {
    flex-grow: 1;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.ast-comment-meta {
    display: flex !important;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem 1rem;
    margin: 0;
    padding: 0;
    border: none !important;
}

.ast-comment-cite-wrap {
    font-style: normal;
}
.ast-comment-cite-wrap cite {
    font-style: normal;
}
.ast-comment-cite-wrap cite b.fn {
    font-size: 0.95rem;
    font-weight: 700;
    color: #111827;
    font-style: normal;
}

.ast-comment-time {
    font-size: 0.8125rem;
    color: #9ca3af;
    display: inline-flex;
    align-items: center;
}
.ast-comment-time a {
    color: #9ca3af !important;
    text-decoration: none !important;
}
.ast-comment-time a:hover {
    color: #0A1128 !important;
}

/* Separator dot between name and date */
.ast-comment-cite-wrap + .ast-comment-time::before {
    content: "•";
    margin-left: 0.5rem;
    color: #d1d5db;
    font-weight: bold;
}

/* Comment content styling */
.ast-comment-content {
    font-size: 0.9375rem;
    line-height: 1.8;
    color: #374151;
    padding: 0 0.5rem!important;
}
.ast-comment-content p {
    margin: 0 0 1rem 0;
}
.ast-comment-content p:last-child {
    margin: 0;
}

/* Actions: reply & edit */
.ast-comment-edit-reply-wrap {
    display: flex;
    justify-content: flex-end!important;
    gap: 0.75rem;
    margin-top: 0.25rem;
    padding: 0 0.5rem;
}
.comment-reply-link,
.comment-edit-link {
    display: inline-flex;
    align-items: center;
    padding: 0.45rem 1.15rem;
    font-size: 0.8125rem;
    font-weight: 600;
    color: #4b5563 !important;
    background: #f3f4f6;
    border-radius: 0.75rem;
    text-decoration: none !important;
    transition: background 0.18s, color 0.18s, transform 0.1s;
}
.comment-reply-link:hover,
.comment-edit-link:hover {
    background: #0A1128;
    color: #ffffff !important;
}
.comment-reply-link:active,
.comment-edit-link:active {
    transform: scale(0.97);
}

/* Nested replies */
.ast-comment-list .children {
    list-style: none !important;
    margin: 1.5rem 2.5rem 0 0 !important;
    padding: 0 !important;
    border-right: 2px solid #e5e7eb;
    padding-right: 1.5rem !important;
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

/* Load More Button Wrapper */
.scf-load-more-wrapper {
    text-align: center;
    margin: 2.5rem 0 1rem;
}
.scf-load-more-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    padding: 0.85rem 2.5rem;
    font-size: 0.9375rem;
    font-weight: 700;
    font-family: inherit;
    background: #0A1128;
    color: #ffffff;
    border: none;
    border-radius: 1rem;
    cursor: pointer;
    line-height: 1;
    transition: background .18s, box-shadow .18s, transform 0.1s;
    box-shadow: 0 4px 12px rgba(10,17,40,0.15);
}
.scf-load-more-btn:hover {
    background: #1a2a50;
    box-shadow: 0 6px 18px rgba(10,17,40,0.25);
}
.scf-load-more-btn:active {
    transform: scale(0.98);
}
.scf-load-more-btn:disabled {
    opacity: 0.8;
    cursor: not-allowed;
}
.scf-no-more-comments {
    font-size: 0.875rem;
    color: #6b7280;
    margin: 0;
}

/* Spinner styling */
.btn-spinner {
    width: 18px;
    height: 18px;
}
.spinner {
    animation: rotate 2s linear infinite;
    width: 100%;
    height: 100%;
}
.spinner .path {
    stroke: #ffffff;
    stroke-linecap: round;
    animation: dash 1.5s ease-in-out infinite;
}
@keyframes rotate {
    100% { transform: rotate(360deg); }
}
@keyframes dash {
    0% { stroke-dasharray: 1, 150; stroke-dashoffset: 0; }
    50% { stroke-dasharray: 90, 150; stroke-dashoffset: -35; }
    100% { stroke-dasharray: 90, 150; stroke-dashoffset: -124; }
}

@media (max-width: 600px) {
    #respond                { padding: 1.5rem 1.25rem 1.25rem; border-radius: 1.5rem; }
    #respond .scf__meta-row { grid-template-columns: 1fr; }
    .ast-comment { padding: 1.25rem; }
    .ast-comment-list .children {
        margin-right: 1.25rem !important;
        padding-right: 0.75rem !important;
    }
}
</style>

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
