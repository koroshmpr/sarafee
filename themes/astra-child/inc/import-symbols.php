<?php
/**
 * Symbols Admin Import & Sync from REST API
 * Page location: WordPress Admin -> Symbols (نمادها) -> همگام‌سازی نمادها
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// 1. Add admin menu item under 'symbol' custom post type
add_action( 'admin_menu', 'register_symbols_import_admin_menu' );

function register_symbols_import_admin_menu() {
    add_submenu_page(
        'edit.php?post_type=symbol',
        'همگام‌سازی نمادها از API',
        'همگام‌سازی نمادها',
        'manage_options',
        'import-symbols-api',
        'render_symbols_import_admin_page'
    );
}

// 2. Render Admin Page
function render_symbols_import_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'شما اجازه دسترسی به این صفحه را ندارید.' );
    }

    $sync_report = null;

    // Process Sync on Form Submission
    if ( isset( $_POST['sync_symbols_nonce'] ) && wp_verify_nonce( $_POST['sync_symbols_nonce'], 'sync_symbols_action' ) ) {
        $sync_report = sync_symbols_from_api_action();
    }
    ?>
    <div class="wrap" dir="rtl">
        <h1 class="wp-heading-inline">همگام‌سازی نمادها از REST API</h1>
        <hr class="wp-header-end">

        <?php if ( ! empty( $sync_report ) ) : ?>
            <div class="notice notice-<?php echo esc_attr( $sync_report['status'] ); ?> is-dismissible" style="padding: 12px 15px; margin-top: 15px;">
                <h3 style="margin-top:0;"><?php echo esc_html( $sync_report['title'] ); ?></h3>
                <p><?php echo wp_kses_post( $sync_report['message'] ); ?></p>
                <?php if ( ! empty( $sync_report['details'] ) ) : ?>
                    <div style="background:#f8fafc; border:1px solid #e2e8f0; padding:10px 15px; border-radius:8px; max-height:250px; overflow-y:auto; font-size:13px;">
                        <?php echo $sync_report['details']; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="card" style="max-width: 700px; margin-top: 20px; padding: 20px; border-radius: 12px;">
            <h2 style="margin-top:0;">راهنمای همگام‌سازی نمادها</h2>
            <p style="font-size: 14px; line-height: 1.8; color: #475569;">
                با کلیک بر روی دکمه زیر، تمامی نمادها از REST API سیستم دریافت شده و پست‌تایپ <strong>symbol</strong> در وردپرس به‌روز می‌شود:
            </p>
            <ul style="list-style: disc; margin-right: 20px; font-size: 13px; line-height: 1.8; color: #334155;">
                <li><strong>پست‌های موجود:</strong> عنوان و محتوای اصلی پست دست‌نخورده باقی می‌ماند، فیلد ACF با نام <code>fa_name</code> بروزرسانی شده و دسته‌بندی نماد (تاکسونومی <code>symbol-category</code>) بر اساس <code>asset_type</code> (طلا ➔ <code>gold</code>، ارز ➔ <code>currencies</code>) انتساب داده می‌شود.</li>
                <li><strong>پست‌های جدید:</strong> اگر اسلاگ نماد در وردپرس وجود نداشته باشد، پست جدید ایجاد شده، فیلد <code>fa_name</code> مقداردهی شده و دسته‌بندی <code>symbol-category</code> مربوطه اختصاص می‌یابد.</li>
                <li><strong>حذف نمادهای قدیمی:</strong> تمام نمادهایی که در وردپرس وجود دارند اما در API جدید وجود ندارند، به صورت خودکار حذف می‌شوند.</li>
            </ul>

            <form method="post" action="" style="margin-top: 20px;">
                <?php wp_nonce_field( 'sync_symbols_action', 'sync_symbols_nonce' ); ?>
                <button type="submit" name="sync_symbols_api" class="button button-primary button-large" style="height: 42px; padding: 0 24px; font-weight: bold;">
                    همگام‌سازی و بروزرسانی نمادها از API
                </button>
            </form>
        </div>
    </div>
    <?php
}

/**
 * Assign category term to symbol post dynamically based on asset_type from API
 * Uses exclusively the custom taxonomy 'symbol-category'
 */
function assign_symbol_asset_category( $post_id, $asset_type ) {
    $cat_slug = ( $asset_type === 'gold' ) ? 'gold' : 'currencies';
    $cat_name = ( $asset_type === 'gold' ) ? 'طلا' : 'ارزها';
    $taxonomy = 'symbol-category';

    if ( ! taxonomy_exists( $taxonomy ) ) {
        register_taxonomy( $taxonomy, 'symbol', [
            'hierarchical' => true,
            'label'        => 'دسته بندی نمادها',
            'public'       => true,
            'show_ui'      => true,
        ] );
    }

    $term = get_term_by( 'slug', $cat_slug, $taxonomy );
    if ( ! $term ) {
        $new_term = wp_insert_term( $cat_name, $taxonomy, [ 'slug' => $cat_slug ] );
        $term_id = ( ! is_wp_error( $new_term ) && isset( $new_term['term_id'] ) ) ? $new_term['term_id'] : 0;
    } else {
        $term_id = $term->term_id;
    }

    if ( ! empty( $term_id ) ) {
        wp_set_object_terms( $post_id, [ (int) $term_id ], $taxonomy, false );
    }
}

// 3. Core Sync Function
function sync_symbols_from_api_action() {
    $api_url = 'https://market-pulse.khanes.app/api/v2/currencies?per_page=500';
    $response = wp_remote_get( $api_url, [ 'timeout' => 15 ] );

    if ( is_wp_error( $response ) ) {
        return [
            'status'  => 'error',
            'title'   => 'خطا در دریافت اطلاعات از API',
            'message' => 'ارتباط با API برقرار نشد: ' . esc_html( $response->get_error_message() ),
            'details' => ''
        ];
    }

    $body = wp_remote_retrieve_body( $response );
    $json = json_decode( $body, true );

    if ( empty( $json['data']['models'] ) || ! is_array( $json['data']['models'] ) ) {
        return [
            'status'  => 'error',
            'title'   => 'اطلاعاتی دریافت نشد',
            'message' => 'پاسخ دریافت شده از API معتبر نمی‌باشد یا آرایه مدل‌ها خالی است.',
            'details' => ''
        ];
    }

    $models = $json['data']['models'];
    $api_slugs = [];

    foreach ( $models as $m ) {
        if ( empty( $m['symbol'] ) ) continue;
        $slug = strtolower( trim( $m['symbol'] ) );
        $fa_name = ! empty( $m['name_fa'] ) ? trim( $m['name_fa'] ) : trim( $m['name'] );
        $asset_type = ( isset( $m['asset_type'] ) && $m['asset_type'] === 'gold' ) ? 'gold' : 'currency';

        $api_slugs[$slug] = [
            'fa_name'    => $fa_name,
            'asset_type' => $asset_type,
            'raw'        => $m,
        ];
    }

    $added_count   = 0;
    $updated_count = 0;
    $deleted_count = 0;
    $log_html      = '';

    // 1. Process items from API (Create or Update ACF fa_name & symbol-category)
    foreach ( $api_slugs as $slug => $info ) {
        $existing = get_posts([
            'post_type'      => 'symbol',
            'name'           => $slug,
            'post_status'    => 'any',
            'posts_per_page' => 1,
        ]);

        if ( ! empty( $existing ) ) {
            $post_id = $existing[0]->ID;
            // Update only ACF field fa_name without altering existing post data
            if ( function_exists( 'update_field' ) ) {
                update_field( 'fa_name', $info['fa_name'], $post_id );
            } else {
                update_post_meta( $post_id, 'fa_name', $info['fa_name'] );
            }

            // Assign category based on asset_type exclusively to symbol-category
            assign_symbol_asset_category( $post_id, $info['asset_type'] );

            $cat_label = ( $info['asset_type'] === 'gold' ) ? 'gold' : 'currencies';
            $log_html .= "<p style='color:#0284c7; margin:4px 0;'>🔄 بروزرسانی نام فارسی (fa_name) و دسته‌بندی نماد ({$cat_label}): <strong>{$info['fa_name']}</strong> ({$slug})</p>";
            $updated_count++;
        } else {
            // Create new symbol post
            $post_id = wp_insert_post([
                'post_type'   => 'symbol',
                'post_title'  => $info['fa_name'],
                'post_name'   => $slug,
                'post_status' => 'publish',
            ]);

            if ( ! is_wp_error( $post_id ) ) {
                if ( function_exists( 'update_field' ) ) {
                    update_field( 'fa_name', $info['fa_name'], $post_id );
                } else {
                    update_post_meta( $post_id, 'fa_name', $info['fa_name'] );
                }

                // Assign category based on asset_type exclusively to symbol-category
                assign_symbol_asset_category( $post_id, $info['asset_type'] );

                $cat_label = ( $info['asset_type'] === 'gold' ) ? 'gold' : 'currencies';
                $log_html .= "<p style='color:#16a34a; margin:4px 0;'>✔️ ایجاد شد (دسته‌بندی نماد {$cat_label}): <strong>{$info['fa_name']}</strong> ({$slug})</p>";
                $added_count++;
            } else {
                $log_html .= "<p style='color:#dc2626; margin:4px 0;'>❌ خطا در ایجاد: {$slug}</p>";
            }
        }
    }

    // 2. Remove symbols in WP that are NOT present in API
    $all_wp_symbols = get_posts([
        'post_type'      => 'symbol',
        'post_status'    => 'any',
        'posts_per_page' => -1,
    ]);

    if ( ! empty( $all_wp_symbols ) ) {
        foreach ( $all_wp_symbols as $wp_post ) {
            $wp_slug = strtolower( trim( $wp_post->post_name ) );
            if ( ! isset( $api_slugs[$wp_slug] ) ) {
                $deleted_title = $wp_post->post_title;
                wp_delete_post( $wp_post->ID, true );
                $log_html .= "<p style='color:#dc2626; margin:4px 0;'>🗑️ حذف شد (عدم وجود در API): <strong>{$deleted_title}</strong> ({$wp_slug})</p>";
                $deleted_count++;
            }
        }
    }

    return [
        'status'  => 'success',
        'title'   => 'همگام‌سازی با موفقیت انجام شد!',
        'message' => "نتایج: <strong>{$added_count}</strong> نماد جدید ایجاد شد | <strong>{$updated_count}</strong> نماد به‌روز شد (نام فارسی و دسته‌بندی symbol-category) | <strong>{$deleted_count}</strong> نماد حذف شد.",
        'details' => $log_html
    ];
}

// 4. Legacy URL trigger support (for backwards compatibility)
add_action( 'init', function() {
    if ( isset( $_GET['import_symbols'] ) && $_GET['import_symbols'] === '1' && current_user_can( 'administrator' ) ) {
        $result = sync_symbols_from_api_action();
        echo "<div style='background:#fff; padding:2rem; font-family:tahoma;' dir='rtl'>";
        echo "<h2>{$result['title']}</h2>";
        echo "<p>{$result['message']}</p>";
        echo "<div>{$result['details']}</div>";
        echo "</div>";
        exit;
    }
});
