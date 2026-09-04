<?php
/*
 * @Theme Name:WebStack
 * @Theme URI:https://www.iotheme.cn/
 * @Author: iowen
 * @Author URI: https://www.iowen.cn/
 * @Date: 2021-08-22 19:00:30
 * @LastEditors: iowen
 * @LastEditTime: 2024-07-30 18:14:07
 * @FilePath: /WebStack/inc/frame/config/metabox.config.php
 * @Description: 
 */
if ( ! defined( 'ABSPATH' ) ) { die; } // Cannot access pages directly.

$options[] = array(
    'id' => 'sites_meta',
    'title' => 'Site link settings',
    'post_type' => 'sites',
    'data_type' => 'unserialize',
    'context' => 'normal',
    'priority' => 'high',
    'sections'  => array(
        array(
            'name'   => 'section_4',
            'fields' => array(
                array(
                    'id' => '_visible',
                    'type' => 'radio',
                    'title' => 'Who can view',
                    'class'   => 'horizontal',
                    'options' => array(
                        '1' => 'Administrators only',
                        '2' => 'Logged-in users',
                        '0' => 'Everyone',
                    ),
                    'default' => '0',
                ),
                array(
                    "id" => "_sites_link",
                    "type"=>"text",
                    "title" => "Website URL",
                    'after' =>'Must include http(s)://<br><span style="font-weight: normal;color: crimson;margin-top: 10px;display: block;">Note: you may fill in both the website URL and the WeChat QR code, but at least one of them is required.</span>',
                ),
            
                array(
                    "id" => "_sites_sescribe",
                    "type"=>"text",
                    "title" => "Description",
                ),
            
                array(
                    "id" => "_sites_order",
                    "std" => "0",
                    "title" => "Order (higher numbers come first)",
                    "type"=>"text"
                ),
            
                array(
                    "id" => "_thumbnail",
                    "type"=>"image",
                    "title" => "Custom icon URL",
                    'add_title' => 'Add icon',
                ),
            
                array(
                    "id" => "_wechat_qr",
                    "type"=>"image",
                    "title" => "WeChat QR code",
                    'add_title' => 'Add QR code',
                ),
            ),
        ),

    ),
);
CSFramework_Metabox::instance( $options );