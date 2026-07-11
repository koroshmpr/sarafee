<?php

function add_custom_button( $buttons ) {
	array_push( $buttons, 'custom_btn' ); // 'custom_btn' is the ID of your new button
	return $buttons;
}
add_filter( 'mce_buttons', 'add_custom_button' );


function add_custom_button_plugin( $plugin_array ) {
	$plugin_array['custom_btn'] = get_stylesheet_directory_uri() . '/assets/js/tiny-mce-button.js
	'; // Adjust path if necessary
	return $plugin_array;
}
add_filter( 'mce_external_plugins', 'add_custom_button_plugin' );


function enqueue_editor_scripts() {

	wp_enqueue_script('custom-editor-script', get_stylesheet_directory_uri() . '/assets/js/tiny-mce-button.js', array('editor'), '1.'. time() , true);
}
add_action('admin_enqueue_scripts', 'enqueue_editor_scripts');
