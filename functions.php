<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
date_default_timezone_set('Asia/Shanghai');
require get_template_directory() . '/inc/inc.php';

   
// Point the login page logo link at the homepage
add_filter('login_headerurl',function() {return get_bloginfo('url');});
// Use the site tagline as the login logo title
add_filter('login_headertext',function() {return get_bloginfo( 'description' );});

// Remove block-library CSS on WordPress 5.0+
add_action( 'wp_enqueue_scripts', 'fanly_remove_block_library_css', 100 );
function fanly_remove_block_library_css() {
	wp_dequeue_style( 'wp-block-library' );
}
