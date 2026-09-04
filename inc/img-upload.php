<?php 
/**
 * Image upload for the WordPress submission page, guest uploads supported
 * Source: https://www.iowen.cn/wordpress-visitors-upload-pictures
 * iowen
 * WebStack navigation theme
 * 
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

$extArr = array("jpg", "png", "jpeg");
$file = $_FILES['files'];
if ( !empty( $file ) ) {
    $wp_upload_dir = wp_upload_dir();                                     // Get upload directory info
    $basename = $file['name'];
    $basesize = $file['size'];
    $baseext = pathinfo($basename, PATHINFO_EXTENSION);
    /*  Validated by front-end JS
    if (!in_array($baseext, $extArr)) { 
        echo '{"status":3,"msg":"Images must be jpeg, jpg or png!"}'; 
        exit();
    }  
    if ($basesize > (1000 * 1024)) { 
        echo '{"status":3,"msg":"Image size must not exceed 1M"}'; 
        exit();
    } 
    */
    $dataname = date("YmdHis_").substr(md5(time()), 0, 8) . '.' . $baseext;
    $filename = $wp_upload_dir['path'] . '/' . $dataname;
    rename( $file['tmp_name'], $filename );                               // Move the uploaded image into the upload directory
    $attachment = array(
        'guid'           => $wp_upload_dir['url'] . '/' . $dataname,      // External link URL
        'post_mime_type' => $file['type'],                                // File MIME type
        'post_title'     => preg_replace( '/\.[^.]+$/', '', $basename ),  // Attachment title: the filename without its extension
        'post_content'   => '',                                           // Post content, left empty
        'post_status'    => 'inherit'
    );
    $attach_id = wp_insert_attachment( $attachment, $filename );          // Insert the attachment record
    if($attach_id != 0){
        require_once( ABSPATH . 'wp-admin/includes/image.php' );          // Required, because wp_generate_attachment_metadata() depends on this file.
        $attach_data = wp_generate_attachment_metadata( $attach_id, $filename );
        wp_update_attachment_metadata( $attach_id, $attach_data );        // Generate attachment metadata and update the database record.
        // Return the response to the front end
        print_r(json_encode(array('status'=>1,'msg'=>'Image uploaded successfully','data'=>array('id'=>$attach_id,'src'=>wp_get_attachment_url( $attach_id ),'title'=>time()))));
        exit();
    }else{
        echo '{"status":4,"msg":"Image upload failed!"}';
        exit();
    }
} 
