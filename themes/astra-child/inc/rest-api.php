<?php
// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

add_action('rest_api_init', function () {
    register_rest_route('n8n/v1', '/create-post', array(
        'methods' => 'POST',
        'callback' => 'n8n_handle_custom_post_creation',
        'permission_callback' => function ($request) {
            return is_user_logged_in() && current_user_can('edit_posts');
        }
    ));

    register_rest_route('n8n/v1', '/update-post', array(
        'methods'  => 'POST',
        'callback' => 'n8n_handle_custom_post_update',
        'permission_callback' => function ($request) {
            return is_user_logged_in() && current_user_can('edit_posts');
        }
    ));
});

/**
 * Handle Post Creation
 */
function n8n_handle_custom_post_creation($data)
{
    $params = $data->get_json_params();

    if (empty($params['title']) || empty($params['content'])) {
        return new WP_Error('invalid_payload', 'Missing required fields: title/content', array('status' => 400));
    }

    $post_id = wp_insert_post(array(
        'post_title'   => sanitize_text_field($params['title']),
        'post_content' => $params['content'],
        'post_status'  => !empty($params['status']) ? sanitize_text_field($params['status']) : 'draft',
        'post_name'    => !empty($params['slug']) ? sanitize_title($params['slug']) : '',
        'post_type'    => 'post',
    ), true);

    if (is_wp_error($post_id) || $post_id === 0) {
        return new WP_Error('post_creation_failed', 'Failed to create post', array('status' => 500));
    }

    $post = get_post($post_id);

    // Featured Media
    $featured_media = isset($params['featured_media']) ? absint($params['featured_media']) : (isset($params['featured_image_id']) ? absint($params['featured_image_id']) : 0);
    if ($featured_media > 0 && current_user_can('edit_post', $featured_media)) {
        set_post_thumbnail($post_id, $featured_media);
    }

    // --- Rank Math SEO Update ---
    if (!empty($params['focus_keyword'])) {
        update_post_meta($post_id, 'rank_math_focus_keyword', sanitize_text_field($params['focus_keyword']));
    }
    if (!empty($params['meta_description'])) {
        update_post_meta($post_id, 'rank_math_description', sanitize_text_field($params['meta_description']));
    }

    // ACF FAQ Repeater
    if (!empty($params['faq']) && is_array($params['faq']) && function_exists('update_field')) {
        $repeater_data = [];
        foreach ($params['faq'] as $item) {
            $repeater_data[] = [
                'question' => isset($item['question']) ? sanitize_text_field($item['question']) : '',
                'answer'   => isset($item['answer']) ? wp_kses_post($item['answer']) : '',
            ];
        }
        update_field('faqs', $repeater_data, $post_id);
    }
    
    if (!empty($params['category'])) {

        $category_slug = sanitize_title($params['category']);
        $category = get_category_by_slug($category_slug);

        if ($category && !is_wp_error($category)) {

            wp_set_post_categories($post_id, array($category->term_id));

        } else {
            return new WP_Error(
                'invalid_category',
                'Category slug not found',
                array('status' => 400)
            );
        }
    }

    return new WP_REST_Response(array(
        'success' => true,
        'post_id' => $post_id,
        'title'   => $post->post_title,
        'slug'    => $post->post_name,
        'edit_link' => get_edit_post_link($post_id, ''),
    ), 200);
}

/**
 * Handle Post Update
 */
function n8n_handle_custom_post_update($request)
{
    $params = $request->get_json_params();

    if (empty($params['post_id'])) {
        return new WP_Error('missing_id', 'You must provide a post_id', array('status' => 400));
    }

    $post_id = absint($params['post_id']);
    if (!current_user_can('edit_post', $post_id)) {
        return new WP_Error('forbidden', 'No permission', array('status' => 403));
    }

    $update_data = array('ID' => $post_id);

    if (array_key_exists('title', $params)) $update_data['post_title'] = sanitize_text_field($params['title']);
    if (array_key_exists('content', $params)) $update_data['post_content'] = wp_kses_post($params['content']);
    if (array_key_exists('status', $params)) $update_data['post_status'] = sanitize_text_field($params['status']);
    if (array_key_exists('slug', $params)) $update_data['post_name'] = sanitize_title($params['slug']);

    wp_update_post($update_data);

    // Featured Image
    if (array_key_exists('featured_media', $params) || array_key_exists('featured_image_id', $params)) {
        $fid = isset($params['featured_media']) ? absint($params['featured_media']) : absint($params['featured_image_id']);
        $fid > 0 ? set_post_thumbnail($post_id, $fid) : delete_post_thumbnail($post_id);
    }

    // --- Rank Math SEO Update ---
    if (array_key_exists('focus_keyword', $params)) {
        update_post_meta($post_id, 'rank_math_focus_keyword', sanitize_text_field($params['focus_keyword']));
    }
    if (array_key_exists('meta_description', $params)) {
        update_post_meta($post_id, 'rank_math_description', sanitize_text_field($params['meta_description']));
    }

    // ACF FAQ Repeater
    if (array_key_exists('faq', $params) && is_array($params['faq']) && function_exists('update_field')) {
        $repeater_data = array();
        foreach ($params['faq'] as $item) {
            $repeater_data[] = array(
                'question' => isset($item['question']) ? sanitize_text_field($item['question']) : '',
                'answer'   => isset($item['answer']) ? wp_kses_post($item['answer']) : '',
            );
        }
        update_field('faqs', $repeater_data, $post_id);
    }

    clean_post_cache($post_id);
    $post = get_post($post_id);

    return new WP_REST_Response(array(
        'success' => true,
        'post_id' => $post_id,
        'updated_at' => $post->post_modified,
    ), 200);
}




// 1. Register the Custom REST API Route
add_action( 'rest_api_init', function () {
    register_rest_route( 'custom/v1', '/create-service-category', array(
        'methods'             => 'POST',
        'callback'            => 'create_service_category_endpoint',
        'permission_callback' => function ($request) {
            return is_user_logged_in() && current_user_can('edit_posts');
        }
    ) );
} );

// 2. Helper Function: Download External Image and Attach to Media Library
function custom_sideload_image_from_url( $url ) {
    if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
        return false;
    }

    require_once( ABSPATH . 'wp-admin/includes/media.php' );
    require_once( ABSPATH . 'wp-admin/includes/file.php' );
    require_once( ABSPATH . 'wp-admin/includes/image.php' );

    $attachment_id = media_sideload_image( $url, 0, null, 'id' );

    if ( is_wp_error( $attachment_id ) ) {
        return false;
    }

    return $attachment_id;
}

// 3. The Main API Endpoint Callback
function create_service_category_endpoint( WP_REST_Request $request ) {
    $params = $request->get_json_params();

    // Parse & Validate Category Name
    $cat_name = isset( $params['name'] ) ? sanitize_text_field( $params['name'] ) : '';
    $cat_slug = isset( $params['slug'] ) ? sanitize_title( $params['slug'] ) : '';

    if ( empty( $cat_name ) ) {
        return new WP_Error( 'missing_name', 'Category name is required', array( 'status' => 400 ) );
    }

    $term_args = array();
    if ( ! empty( $cat_slug ) ) {
        $term_args['slug'] = $cat_slug;
    }

    // Insert or Update the Category
    $term = wp_insert_term( $cat_name, 'category', $term_args );

    if ( is_wp_error( $term ) ) {
        if ( isset( $term->error_data['term_exists'] ) ) {
            $term_id = $term->error_data['term_exists']; 
        } else {
            return $term; 
        }
    } else {
        $term_id = $term['term_id'];
    }

    // ACF Target Format for Taxonomies
    $acf_target_id = 'category_' . $term_id;

    // Process ACF Fields
    if ( isset( $params['acf'] ) && is_array( $params['acf'] ) ) {
        
        $image_fields = array( 'problem_image', 'definition_image', 'audience_image', 'author_photo' );
        $gallery_fields = array( 'trust_items' );

        foreach ( $params['acf'] as $field_name => $value ) {
            
            if ( in_array( $field_name, $image_fields ) ) {
                if ( is_string( $value ) && filter_var( $value, FILTER_VALIDATE_URL ) ) {
                    $new_id = custom_sideload_image_from_url( $value );
                    if ( $new_id ) update_field( $field_name, $new_id, $acf_target_id );
                } else {
                    update_field( $field_name, $value, $acf_target_id );
                }
            } 
            elseif ( in_array( $field_name, $gallery_fields ) && is_array( $value ) ) {
                $gallery_ids = array();
                foreach ( $value as $item ) {
                    if ( is_string( $item ) && filter_var( $item, FILTER_VALIDATE_URL ) ) {
                        $new_id = custom_sideload_image_from_url( $item );
                        if ( $new_id ) $gallery_ids[] = $new_id;
                    } else {
                        $gallery_ids[] = (int) $item;
                    }
                }
                update_field( $field_name, $gallery_ids, $acf_target_id );
            } 
            else {
                update_field( $field_name, $value, $acf_target_id );
            }
        }
    }

    // Process Rank Math SEO Meta
    if ( isset( $params['rank_math'] ) && is_array( $params['rank_math'] ) ) {
        if ( ! empty( $params['rank_math']['focus_keyword'] ) ) {
            update_term_meta( $term_id, 'rank_math_focus_keyword', sanitize_text_field( $params['rank_math']['focus_keyword'] ) );
        }
        
        if ( ! empty( $params['rank_math']['meta_description'] ) ) {
            $meta_desc = sanitize_textarea_field( $params['rank_math']['meta_description'] );
            
            if ( mb_strlen( $meta_desc ) >= 160 ) {
                $meta_desc = mb_substr( $meta_desc, 0, 156 ) . '...';
            }
            
            update_term_meta( $term_id, 'rank_math_description', $meta_desc );
        }
    }

    // Process Rank Math Schema (Native Custom JSON Editor)
    // Process Rank Math Custom Schema (Native Template UI Integration)
    if ( isset( $params['schema'] ) && is_array( $params['schema'] ) ) {
        $schema_headline = isset( $params['schema']['headline'] ) ? sanitize_text_field( $params['schema']['headline'] ) : '';
        $schema_desc     = isset( $params['schema']['description'] ) ? sanitize_textarea_field( $params['schema']['description'] ) : '';
        $schema_type     = isset( $params['schema']['service_type'] ) ? sanitize_text_field( $params['schema']['service_type'] ) : 'Service';

        // Generate a unique ID for Rank Math's shortcode system
        $schema_id = 's-' . uniqid();
        
        // Build the array EXACTLY as Rank Math expects it for the "rank_math_schema_Service" key
        $service_schema_array = array(
            'metadata' => array(
                'type'      => 'template',
                'shortcode' => $schema_id,
                'isPrimary' => '1',
                'title'     => 'Service'
            ),
            '@type'       => 'Service',
            'name'        => $schema_headline,
            'description' => $schema_desc,
            'serviceType' => $schema_type,
            'offers'      => array(
                '@type'        => 'Offer',
                'availability' => 'InStock'
            ),
            'image'       => array(
                '@type' => 'ImageObject',
                'url'   => '%post_thumbnail%'
            )
        );

        // 1. Delete the old corrupted generic schema key to prevent conflicts
        delete_term_meta( $term_id, 'rank_math_schema' );

        // 2. Save the data to Rank Math's specific "Service" meta key
        update_term_meta( $term_id, 'rank_math_schema_Service', $service_schema_array );

        // 3. Save the crucial secondary pointer key so Rank Math UI can find it
        update_term_meta( $term_id, 'rank_math_shortcode_schema_' . $schema_id, $term_id );
    }

    return rest_ensure_response( array(
        'status'  => 'success',
        'message' => 'Category and Custom Schema processed successfully.',
        'term_id' => $term_id,
    ) );
}


add_action( 'rest_api_init', function () {
    register_rest_route( 'custom/v1', '/get-all-meta/(?P<id>\d+)', array(
        'methods'  => 'GET',
        'callback' => function( WP_REST_Request $request ) {
            $term_id = $request['id'];
            
            // Get ALL metadata for this category to see exactly what Rank Math saved
            $all_meta = get_term_meta( $term_id );
            
            return rest_ensure_response( $all_meta );
        },
        'permission_callback' => '__return_true'
    ) );
} );