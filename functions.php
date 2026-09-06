<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
// NOTE: do not call date_default_timezone_set() here. WordPress deliberately runs
// PHP on UTC and derives local time from Settings > General > Timezone. Overriding
// the PHP timezone desynchronises the REST API's date handling, which makes the
// block editor fail to save with "Publishing failed" -- the bulletin post type is
// the only one registered with show_in_rest, so it was the only one affected.
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
