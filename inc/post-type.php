<?php
/*
 * @Theme Name:WebStack
 * @Theme URI:https://www.iotheme.cn/
 * @Author: NetSec
 * @Author URI: https://51sec.org
 * @Date: 2020-02-22 21:26:05
 * @LastEditors: NetSec
 * @LastEditTime: 2024-07-30 21:12:27
 * @FilePath: /WebStack/inc/post-type.php
 * @Description: 
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }


// Sites
add_action( 'init', 'post_type_sites' );
function post_type_sites() {
	$labels = array(
		'name'               => 'Sites', 'post type general name', 'your-plugin-textdomain',
		'singular_name'      => 'Site', 'post type singular name', 'your-plugin-textdomain',
		'menu_name'          => 'Sites', 'admin menu', 'your-plugin-textdomain',
		'name_admin_bar'     => 'Site', 'add new on admin bar', 'your-plugin-textdomain',
		'add_new'            => 'Add New', 'sites', 'your-plugin-textdomain',
		'add_new_item'       => 'Add New Site', 'your-plugin-textdomain',
		'new_item'           => 'New Site', 'your-plugin-textdomain',
		'edit_item'          => 'Edit Site', 'your-plugin-textdomain',
		'view_item'          => 'View Site', 'your-plugin-textdomain',
		'all_items'          => 'All Sites', 'your-plugin-textdomain',
		'search_items'       => 'Search Sites', 'your-plugin-textdomain',
		'parent_item_colon'  => 'Parent Site:', 'your-plugin-textdomain',
		'not_found'          => 'No sites found.', 'your-plugin-textdomain',
		'not_found_in_trash' => 'No sites found in Trash.', 'your-plugin-textdomain'
	);

	$args = array(
		'labels'             => $labels,
		'public'             => true,
		'publicly_queryable' => true,
		'show_ui'            => true,
		'show_in_menu'       => true,
		'query_var'          => true,
		'rewrite'            => array( 'slug' => 'sites' ),
		'capability_type'    => 'post',
		'menu_icon'          => 'dashicons-admin-site',
		'has_archive'        => false,
		'hierarchical'       => false,
		'menu_position'      => 10,
		'supports'           => array( 'title',  'author', 'editor', 'comments', 'custom-fields' )//'editor','excerpt',
	);

	register_post_type( 'sites', $args );
}


// Site categories
add_action( 'init', 'create_sites_taxonomies', 0 );
function create_sites_taxonomies() {
	$labels = array(
		'name'              => 'Site Categories', 'taxonomy general name',
		'singular_name'     => 'Site Category', 'taxonomy singular name',
		'search_items'      => 'Search Site Categories',
		'all_items'         => 'All Site Categories',
		'parent_item'       => 'Parent Category',
		'parent_item_colon' => 'Parent Category:',
		'edit_item'         => 'Edit Site Category',
		'update_item'       => 'Update Site Category',
		'add_new_item'      => 'Add New Site Category',
		'new_item_name'     => 'New Genre Name',
		'menu_name'         => 'Site Categories',
	);

	$args = array(
		'hierarchical'      => true,
		'labels'            => $labels,
		'show_ui'           => true,
		'show_admin_column' => true,
		'query_var'         => true,
		'rewrite'           => array( 'slug' => 'favorites' ),
	);

	register_taxonomy( 'favorites', array( 'sites' ), $args );
}

/*
 * Site Category selection: required, single-select, leaf categories only.
 *
 * The default taxonomy meta box lets you check any number of categories,
 * including a parent that already has subcategories under it -- but the
 * theme's own convention (see README) is a 2-level hierarchy where parent
 * categories should not hold entries of their own. This replaces that meta
 * box with a single <select>: any category that has children is rendered
 * as an <optgroup> label (a group heading, not a selectable option) with
 * its children listed under it, so a parent-with-children simply cannot be
 * chosen, and native <select> semantics rule out choosing more than one.
 *
 * Enforcement also happens server-side in io_save_site_category(), since
 * the dropdown only prevents the common case -- a tampered or programmatic
 * POST could still submit something invalid.
 */
add_action( 'add_meta_boxes', 'io_replace_site_category_metabox' );
function io_replace_site_category_metabox() {
	remove_meta_box( 'favoritesdiv', 'sites', 'side' );
	add_meta_box(
		'io_site_category',
		__( 'Site Category', 'i_theme' ),
		'io_render_site_category_metabox',
		'sites',
		'side',
		'high'
	);
}

function io_render_site_category_metabox( $post ) {
	wp_nonce_field( 'io_site_category_save', 'io_site_category_nonce' );

	$current_terms = wp_get_object_terms( $post->ID, 'favorites', array( 'fields' => 'ids' ) );
	$current       = ( ! is_wp_error( $current_terms ) && ! empty( $current_terms ) ) ? (int) $current_terms[0] : 0;

	$all_terms = get_terms( array( 'taxonomy' => 'favorites', 'hide_empty' => false ) );
	if ( is_wp_error( $all_terms ) ) {
		$all_terms = array();
	}

	$by_parent = array();
	foreach ( $all_terms as $term ) {
		$by_parent[ (int) $term->parent ][] = $term;
	}

	echo '<select name="io_site_category" id="io_site_category" style="width:100%">';
	echo '<option value="0">' . esc_html__( '&mdash; Select a category &mdash;', 'i_theme' ) . '</option>';

	$top_terms = isset( $by_parent[0] ) ? $by_parent[0] : array();
	foreach ( $top_terms as $top ) {
		if ( ! empty( $by_parent[ $top->term_id ] ) ) {
			echo '<optgroup label="' . esc_attr( $top->name ) . '">';
			foreach ( $by_parent[ $top->term_id ] as $child ) {
				printf(
					'<option value="%d"%s>%s</option>',
					(int) $child->term_id,
					selected( $current, $child->term_id, false ),
					esc_html( $child->name )
				);
			}
			echo '</optgroup>';
		} else {
			printf(
				'<option value="%d"%s>%s</option>',
				(int) $top->term_id,
				selected( $current, $top->term_id, false ),
				esc_html( $top->name )
			);
		}
	}
	echo '</select>';
	echo '<p class="description">' . esc_html__( 'Required. A category that has subcategories is shown only as a group heading above them and cannot be selected directly -- choose one of its subcategories instead.', 'i_theme' ) . '</p>';
}

add_action( 'save_post', 'io_save_site_category' );
function io_save_site_category( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( wp_is_post_revision( $post_id ) ) {
		return;
	}
	if ( 'sites' !== get_post_type( $post_id ) ) {
		return;
	}
	if ( ! isset( $_POST['io_site_category_nonce'] ) || ! wp_verify_nonce( $_POST['io_site_category_nonce'], 'io_site_category_save' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$submitted = isset( $_POST['io_site_category'] ) ? absint( $_POST['io_site_category'] ) : 0;
	$valid     = false;

	if ( $submitted > 0 ) {
		$term = get_term( $submitted, 'favorites' );
		if ( $term && ! is_wp_error( $term ) ) {
			$children = get_terms( array(
				'taxonomy'   => 'favorites',
				'parent'     => $submitted,
				'hide_empty' => false,
				'fields'     => 'ids',
			) );
			// A term with no children is a valid leaf, whether it sits at
			// the top level or is itself already a child.
			if ( is_wp_error( $children ) || empty( $children ) ) {
				$valid = true;
			}
		}
	}

	if ( $valid ) {
		wp_set_object_terms( $post_id, array( $submitted ), 'favorites', false );
		return;
	}

	// Invalid or missing: leave any existing category assignment alone, and
	// only block the post from staying live.
	$post = get_post( $post_id );
	if ( $post && 'publish' === $post->post_status ) {
		remove_action( 'save_post', 'io_save_site_category' );
		wp_update_post( array( 'ID' => $post_id, 'post_status' => 'draft' ) );
		add_action( 'save_post', 'io_save_site_category' );
		set_transient( 'io_site_cat_invalid_' . $post_id, 1, 60 );
	}
}

add_filter( 'redirect_post_location', 'io_site_category_redirect_notice', 10, 2 );
function io_site_category_redirect_notice( $location, $post_id ) {
	if ( 'sites' === get_post_type( $post_id ) && get_transient( 'io_site_cat_invalid_' . $post_id ) ) {
		delete_transient( 'io_site_cat_invalid_' . $post_id );
		$location = add_query_arg( 'io_site_cat_error', '1', $location );
	}
	return $location;
}

add_action( 'admin_notices', 'io_site_category_admin_notice' );
function io_site_category_admin_notice() {
	$screen = get_current_screen();
	if ( ! $screen || 'sites' !== $screen->post_type ) {
		return;
	}
	if ( isset( $_GET['io_site_cat_error'] ) && '1' === $_GET['io_site_cat_error'] ) {
		echo '<div class="notice notice-error"><p>' . esc_html__( 'This site was kept as a Draft because a Site Category was not selected (or the selected category was a parent with subcategories, which cannot be assigned directly). Please select exactly one category, then publish again.', 'i_theme' ) . '</p></div>';
	}
}


// Bulletins
add_action( 'init', 'post_type_bulletin' );
function post_type_bulletin() {
	$labels = array(
		'name'               => 'Bulletins', 'post type general name', 'your-plugin-textdomain',
		'singular_name'      => 'Bulletin', 'post type singular name', 'your-plugin-textdomain',
		'menu_name'          => 'Bulletins', 'admin menu', 'your-plugin-textdomain',
		'name_admin_bar'     => 'Bulletin', 'add new on admin bar', 'your-plugin-textdomain',
		'add_new'            => 'Add New', 'bulletin', 'your-plugin-textdomain',
		'add_new_item'       => 'Add New Bulletin', 'your-plugin-textdomain',
		'new_item'           => 'New Bulletin', 'your-plugin-textdomain',
		'edit_item'          => 'Edit Bulletin', 'your-plugin-textdomain',
		'view_item'          => 'View Bulletin', 'your-plugin-textdomain',
		'all_items'          => 'All Bulletins', 'your-plugin-textdomain',
		'search_items'       => 'Search Bulletins', 'your-plugin-textdomain',
		'parent_item_colon'  => 'Parent Bulletin:', 'your-plugin-textdomain',
		'not_found'          => 'No bulletins found.', 'your-plugin-textdomain',
		'not_found_in_trash' => 'No bulletins found in Trash.', 'your-plugin-textdomain'
	);

	$args = array(
		'labels'             => $labels,
		'public'             => true,
		'publicly_queryable' => true,
		'show_ui'            => true,
		'show_in_menu'       => true,
		'query_var'          => true,
		'rewrite'            => array( 'slug' => 'bulletin' ),
		'capability_type'    => 'post',
		'menu_icon'          => 'dashicons-controls-volumeon',
		'has_archive'        => false,
		'hierarchical'       => false,
		'menu_position'      => 10,
		'show_in_rest'       => true,
		'supports'           => array( 'title', 'editor', 'author', 'comments', 'custom-fields' )
	);

	register_post_type( 'bulletin', $args );
}

/**
 * Save ordering
 *
 * @param int $term_id
 */
//add_action( 'edited_favorites', 'save_term_order' );
//add_action('created_favorites','save_term_order',10,1);
add_action('edit_favorites','save_term_order',10,1);
function save_term_order( $term_id ) {
	//if (isset($_POST['_term_order'])) {
   		//update_term_meta( $term_id, '_term_order', $_POST[ '_term_order' ] );
	//}
	$ca_menu_id = esc_attr($_POST['ca_ordinal']);
	if ($ca_menu_id)
		update_term_meta( $term_id, '_term_order', $ca_menu_id);
}


/**
 * Set the permalink structure of the 'sites' post type to ID.html 
 * https://www.wpdaxue.com/custom-post-type-permalink-code.html
 */
add_filter('post_type_link', 'custom_sites_link', 1, 3);
function custom_sites_link( $link, $post = 0 ){
    if ( $post->post_type == 'sites' ){
        return home_url( 'sites/' . $post->ID .'.html' );
    } else {
        return $link;
    }
}
add_action( 'init', 'custom_sites_rewrites_init' );
function custom_sites_rewrites_init(){
    add_rewrite_rule(
        'sites/([0-9]+)?.html$',
        'index.php?post_type=sites&p=$matches[1]',
		'top' 
	);
    add_rewrite_rule(
        'sites/([0-9]+)?.html/comment-page-([0-9]{1,})$',
        'index.php?post_type=sites&p=$matches[1]&cpage=$matches[2]',
        'top'
    );
}


// Build the category dropdown menu
add_action('restrict_manage_posts','io_post_type_filter',10,2);
function io_post_type_filter($post_type, $which){
    if('sites' !== $post_type){ // Custom post type, change as needed
      return; // Check this is the post type we want
    }
    $taxonomy_slug     = 'favorites'; // Custom taxonomy, change as needed
    $taxonomy          = get_taxonomy($taxonomy_slug);
    $selected          = '';
    $request_attr      = 'favorites'; // Custom taxonomy, change as needed
    if ( isset($_REQUEST[$request_attr] ) ) {
      $selected = $_REQUEST[$request_attr];
    }
    wp_dropdown_categories(array(
      'show_option_all' =>  __("All {$taxonomy->label}"),
      'taxonomy'        =>  $taxonomy_slug,
      'name'            =>  $request_attr,
      'orderby'         =>  'name',
      'selected'        =>  $selected,
      'hierarchical'    =>  true,
      'depth'           =>  5,
      'show_count'      =>  true, // Show number of post in parent term
      'hide_empty'      =>  false, // Don't show posts w/o terms
    ));
}
// List all posts in the selected category
add_filter('parse_query','io_work_convert_restrict'); 
function io_work_convert_restrict($query) {  
    global $pagenow;  
    global $typenow;  
    if ($pagenow=='edit.php') {  
        $filters = get_object_taxonomies($typenow);  
        foreach ($filters as $tax_slug) {  
            $var = &$query->query_vars[$tax_slug];  
            if ( isset($var) && $var>0) {  
                $term = get_term_by('id',$var,$tax_slug);  
                $var = $term->slug;  
            }  
        }  
    }  
    return $query;  
} 

/**
 * Add custom columns to the post list
 * https://www.iowen.cn/wordpress-quick-edit
 */
add_filter('manage_edit-sites_columns', 'io_ordinal_manage_posts_columns');
add_action('manage_posts_custom_column','io_ordinal_manage_posts_custom_column',10,2);
function io_ordinal_manage_posts_columns($columns){
    $columns['link']       = 'Link';
	$columns['ordinal']    = 'Order'; 
	$columns['visible']    = 'Visibility'; 
	return $columns;
}
function io_ordinal_manage_posts_custom_column($column_name,$id){ 
	switch( $column_name ) :
		case 'link': {
			echo get_post_meta($id, '_sites_link', true);
			break;
		}
		case 'ordinal': {
			echo get_post_meta($id, '_sites_order', true);
			break;
		}
		case 'visible': {
			switch (get_post_meta($id, '_visible', true)) {
				case '1':
					echo "Administrators";
					break;
				case '2':
					echo "Logged-in users";
					break;
				default:
					echo "Everyone";
					break;
			}
			break;
		}
	endswitch;
}

// Add custom columns to the category list
add_filter('manage_edit-favorites_columns', 'io_id_manage_tags_columns');
add_action('manage_favorites_custom_column','io_id_manage_tags_custom_column',10,3);
function io_id_manage_tags_columns($columns){
	$columns['ca_ordinal']    = 'Menu order'; 
	$columns['id']    = 'ID'; 
    return $columns;
}
function io_id_manage_tags_custom_column($null,$column_name,$id){
    if ($column_name == 'ca_ordinal') {
        echo get_term_meta($id, '_term_order', true);
    }
    if ($column_name == 'id') {
        echo $id;
    }
}

/**
 * Add custom columns to the post list
 * 
 */
add_action( 'admin_head', 'io_custom_css' );
function io_custom_css(){
	echo '<style>
		#ordinal{
			width:80px;
		} 
	</style>';
}

// Add sorting rules to the post list
add_filter('manage_edit-sites_sortable_columns', 'sort_sites_order_column');
//add_filter('manage_edit-favorites_sortable_columns', 'sort_favorites_order_column');
add_action('pre_get_posts', 'sort_sites_order');
function sort_sites_order_column($defaults)
{
    $defaults['ordinal'] = 'ordinal';
    return $defaults;
}
function sort_favorites_order_column($defaults)
{
    $defaults['ca_ordinal'] = 'ca_ordinal';
    return $defaults;
}
function sort_sites_order($query) {
    if(!is_admin())
		return;
    $orderby = $query->get('orderby');
    if('ordinal' == $orderby) {
        $query->set('meta_key', '_sites_order');
        $query->set('orderby', 'meta_value_num');
    }
    if('ca_ordinal' == $orderby) {
        $query->set('meta_key', '_term_order');
        $query->set('orderby', 'meta_value_num');
    }
}


add_action('quick_edit_custom_box',  'io_add_quick_edit', 10, 2);
function io_add_quick_edit($column_name, $post_type) {
	if ($column_name == 'ordinal') {
		// Note: the <fieldset> class can be:
		//inline-edit-col-left，inline-edit-col-center，inline-edit-col-right
		// all columns are float: left,
		// so use a clear: both element if you want the left column
		echo '
		<fieldset class="inline-edit-col-left" style="clear: both;">
			<div class="inline-edit-col"> 
				<label class="alignleft">
					<span class="title">Order</span>
					<span class="input-text-wrap"><input type="number" name="ordinal" class="ptitle" value=""></span>
				</label> 
				<em class="alignleft inline-edit-or"> Higher numbers come first</em>
			</div>
		</fieldset>';
	}
	if ($column_name == 'ca_ordinal') {  
	  	echo '
	  	<fieldset>
		  	<div class="inline-edit-col"> 
			  	<label class="alignleft">
				  	<span class="title">Order</span>
				  	<span class="input-text-wrap"><input type="number" name="ca_ordinal" class="ptitle" value=""></span>
			  	</label> 
			  	<em class="alignleft inline-edit-or"> Higher numbers come first</em>
		  	</div>
	  	</fieldset>';
	}
}


// Save and update data
add_action('save_post', 'io_save_quick_edit_data');
function io_save_quick_edit_data($post_id) {
    // Skip autosaves, which are not our submitted data
    if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE )
        return $post_id;
    // Check permissions; 'sites' is the post type here, the default would be 'post'
    if (isset($_POST['post_type']) && 'sites' ==  $_POST['post_type'] ) {
        if ( !current_user_can( 'edit_page', $post_id ) )
            return $post_id;
    } 
	$post = get_post($post_id); 
	// 'ordinal' matches the code above
    if (isset($_POST['ordinal']) && ($post->post_type != 'revision')) {
        $left_menu_id = esc_attr($_POST['ordinal']);
        if ($left_menu_id)
			update_post_meta( $post_id, '_sites_order', $left_menu_id);// '_sites_order' is the custom field
    } 
}

// Output JS
add_action('admin_footer', 'ashuwp_quick_edit_javascript');
function ashuwp_quick_edit_javascript() {
	$current_screen = get_current_screen(); 
    if (!is_object($current_screen) || ($current_screen->post_type != 'sites'))return;
	if($current_screen->id == 'edit-sites'){
 	echo"
    <script type='text/javascript'>
    jQuery(function($){
		var wp_inline_edit_function = inlineEditPost.edit;
		inlineEditPost.edit = function( post_id ) {
			wp_inline_edit_function.apply( this, arguments );
			var id = 0;
			if ( typeof( post_id ) == 'object' ) {
				id = parseInt( this.getId( post_id ) );
			}
			if ( id > 0 ) {
				var specific_post_edit_row = $( '#edit-' + id ),
						specific_post_row = $( '#post-' + id ),
						product_price = $( '.column-ordinal', specific_post_row ).text(); 

				$('input[name=\"ordinal\"]', specific_post_edit_row ).val( product_price ); 
			}
		}
	});
    </script>";
	} 
	if($current_screen->id == 'edit-favorites'){
 	echo"
    <script type='text/javascript'>
    jQuery(function($){
		var wp_inline_edit_function = inlineEditTax.edit;
		inlineEditTax.edit = function( post_id ) {
			wp_inline_edit_function.apply( this, arguments );
			var id = 0;
			if ( typeof( post_id ) == 'object' ) {
				id = parseInt( this.getId( post_id ) );
			}
		console.log('debug: '+id);
			if ( id > 0 ) {
				var specific_post_edit_row = $( '#edit-' + id ),
						specific_post_row = $( '#tag-' + id ),
						product_price = $( '.column-ca_ordinal', specific_post_row ).text(); 

				$('input[name=\"ca_ordinal\"]', specific_post_edit_row ).val( product_price ); 
			}
		}
	});
    </script>";
	} 
}

