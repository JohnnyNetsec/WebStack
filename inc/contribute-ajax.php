<?php 
/**
 * Deprecated, moved to ajax.php
 */
if ( 'POST' != $_SERVER['REQUEST_METHOD'] ) {
	header('Allow: POST');
	header('HTTP/1.1 405 Method Not Allowed');
	header('Content-Type: text/plain');
	exit;
}

require dirname(__FILE__).'/../../../../wp-load.php';
nocache_headers();

if( isset($_COOKIE["tougao"]) && ( time() - $_COOKIE["tougao"] ) < 120 ){
	error('{"status":2,"msg":"You are submitting too quickly. Please take a break!"}');
} 

// Initialise form variables
$sites_link = isset( $_POST['tougao_sites_link'] ) ? trim(htmlspecialchars($_POST['tougao_sites_link'], ENT_QUOTES)) : '';
$sites_sescribe = isset( $_POST['tougao_sites_sescribe'] ) ? trim(htmlspecialchars($_POST['tougao_sites_sescribe'], ENT_QUOTES)) : '';
$title = isset( $_POST['tougao_title'] ) ? trim(htmlspecialchars($_POST['tougao_title'], ENT_QUOTES)) : '';
$category = isset( $_POST['tougao_cat'] ) ? $_POST['tougao_cat'] : '0';
$sites_ico = isset( $_POST['tougao_sites_ico'] ) ? trim(htmlspecialchars($_POST['tougao_sites_ico'], ENT_QUOTES)) : '';
$wechat_qr = isset( $_POST['tougao_wechat_qr'] ) ? trim(htmlspecialchars($_POST['tougao_wechat_qr'], ENT_QUOTES)) : '';
$content = isset( $_POST['tougao_content'] ) ? trim(htmlspecialchars($_POST['tougao_content'], ENT_QUOTES)) : '';

// Validate form fields
if ( $category == "0" ){
	error('{"status":4,"msg":"Please select a category."}');
}
if ( !empty(get_term_children($category, 'favorites'))){
	error('{"status":4,"msg":"A parent category cannot be used."}');
}
if ( empty($sites_sescribe) || mb_strlen($sites_sescribe) > 50 ) {
	error('{"status":4,"msg":"A site description is required and must be 50 characters or fewer."}');
}
if ( empty($sites_link) && empty($wechat_qr) ){
	error('{"status":3,"msg":"Provide at least one of: website URL or WeChat QR code."}');
}
elseif ( !empty($sites_link) && !preg_match('/http(s)?:\/\/[\w.]+[\w\/]*[\w.]*\??[\w=&\+\%]*/is', $sites_link)) {
	error('{"status":4,"msg":"The website link must be a valid URL."}');
}
if ( empty($title) || mb_strlen($title) > 30 ) {
	error('{"status":4,"msg":"A site name is required and must be 30 characters or fewer."}');
}
//if ( empty($content) || mb_strlen($content) > 10000 || mb_strlen($content) < 6) {
//	error('{"status":4,"msg":"Content is required and must be between 6 and 10000 characters."}');
//}
$tougao = array(
	'comment_status'   => 'closed',
	'ping_status'      => 'closed',
	//'post_author'      => 1,// User ID used for submissions
	'post_title'       => $title,
	'post_content'     => $content,
	'post_status'      => 'pending',
	'post_type'        => 'sites',
	//'tax_input'        => array( 'favorites' => array($category) ) // Not available to guests
);

// Insert the post into the database
$status = wp_insert_post( $tougao );
if ($status != 0){
	global $wpdb;
	add_post_meta($status, '_sites_sescribe', $sites_sescribe);
	add_post_meta($status, '_sites_link', $sites_link);
	add_post_meta($status, '_sites_order', '0');
	if( !empty($sites_ico))
		add_post_meta($status, '_thumbnail', $sites_ico); 
	if( !empty($wechat_qr))
		add_post_meta($status, '_wechat_qr', $wechat_qr); 
	wp_set_post_terms( $status, array($category), 'favorites'); // Set the post category
	setcookie("tougao", time(), time()+30);
	error('{"status":1,"msg":"Submission received!"}');
}else{
	error('{"status":4,"msg":"Submission failed!"}');
}

function error($ErrMsg) {
    echo $ErrMsg;
    exit;
} 
