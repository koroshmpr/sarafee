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
.comments-area { direction: rtl; }
.comments-title {
    font-size: 1.0625rem;
    font-weight: 700;
    color: #111827;
}

@media (max-width: 600px) {
    #respond                { padding: 1.5rem 1.25rem 1.25rem; border-radius: 1.5rem; }
    #respond .scf__meta-row { grid-template-columns: 1fr; }
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
