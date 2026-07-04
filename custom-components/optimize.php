<?php

// 1. Remove Astra's legacy flexibility script
add_action( 'wp_enqueue_scripts', 'remove_astra_flexibility_script', 20 );
function remove_astra_flexibility_script() {
    wp_dequeue_script( 'astra-flexibility' );
    wp_deregister_script( 'astra-flexibility' );
}

// 2. Safely Remove Unused CSS (Blocks & Dashicons)
add_action('wp_enqueue_scripts', 'remove_unused_css', 100);
function remove_unused_css() {
    // Only remove Dashicons for regular visitors so your Admin Bar doesn't break
    if ( ! is_user_logged_in() ) {
        wp_dequeue_style('dashicons');
        wp_deregister_style('dashicons');
    }
    
    // Remove Gutenberg block library (since you use Elementor)
    wp_dequeue_style('wp-block-library');
    wp_deregister_style('wp-block-library');
    
    // Remove Font Awesome (if you are manually handling icons)
    wp_dequeue_style('font-awesome');
    wp_deregister_style('font-awesome');
}

// 3. Optimized LCP Featured Image Shortcode
add_shortcode( 'lcp_featured_image', 'my_custom_lcp_image_shortcode' );
function my_custom_lcp_image_shortcode( $atts ) {
    if ( has_post_thumbnail() ) {
        
        // Dynamically grab the exact width and height of the image
        $image_id = get_post_thumbnail_id();
        $image_meta = wp_get_attachment_metadata($image_id);
        $width = isset($image_meta['width']) ? $image_meta['width'] : 16;
        $height = isset($image_meta['height']) ? $image_meta['height'] : 9;

        $attr = [
            'fetchpriority' => 'high',
            'loading'       => 'eager', 
            'class'         => 'custom-lcp-featured-image w-full h-auto',
            // Force the browser to reserve the exact box size before the image downloads
            'style'         => "aspect-ratio: {$width} / {$height};", 
            'sizes'         => '(max-width: 768px) 100vw, 800px' 
        ];

        return get_the_post_thumbnail( null, 'full', $attr );
    }
    return '';
}
// Remove hyperlink from the comment author's name globally
add_filter( 'get_comment_author_link', 'disable_comment_author_links', 10, 3 );
function disable_comment_author_links( $return, $author, $comment_ID ) {
    // This returns just the plain text name, completely stripping the <a href="..."> tag
    return $author;
}
// Preload critical WordPress scripts to fix Lighthouse Network Dependency Tree
add_action('wp_head', 'preload_elementor_critical_scripts', 1);
function preload_elementor_critical_scripts() {
    global $wp_scripts;
    if ( ! wp_scripts() ) {
        wp_scripts();
    }
    
    // The exact scripts Lighthouse is complaining about
    $scripts_to_preload = ['wp-hooks', 'wp-i18n'];
    
    foreach ( $scripts_to_preload as $handle ) {
        if ( isset( $wp_scripts->registered[ $handle ] ) ) {
            $src = $wp_scripts->registered[ $handle ]->src;
            $ver = $wp_scripts->registered[ $handle ]->ver;
            
            // Format the URL exactly as WordPress does to prevent double-downloading
            $url = $src . ( $ver ? '?ver=' . $ver : '' );
            
            // Ensure it is a full URL
            if ( strpos( $url, '/' ) === 0 && strpos( $url, '//' ) !== 0 ) {
                $url = site_url( $url );
            }
            
            // Inject the preload tag into the <head>
            echo "<link rel='preload' href='" . esc_url( $url ) . "' as='script'>\n";
        }
    }
}