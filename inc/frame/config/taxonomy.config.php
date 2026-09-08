<?php
/*
 * @Theme Name:WebStack
 * @Theme URI:https://www.iotheme.cn/
 * @Author: NetSec
 * @Author URI: https://51sec.org
 * @Date: 2021-08-22 19:00:30
 * @LastEditors: NetSec
 * @LastEditTime: 2024-07-30 19:27:48
 * @FilePath: /WebStack/inc/frame/config/taxonomy.config.php
 * @Description: 
 */
if ( ! defined( 'ABSPATH' ) ) { die; } // Cannot access pages directly.

$options[] = array(
    'id' => 'favorites_meta',
    'title' => 'Icon settings',
    'taxonomy' => 'favorites',
    'data_type' => 'unserialize',
    'fields' => array(
        array(
            'type'    => 'notice',
            'content' => '<h2 style="color: red;">'.__('Note: two levels at most, and parent categories should have no content','i_theme').'</h2>',
            'class'   => 'info',
        ),
        array(
            'id' => '_view_user',
            'type' => 'radio',
            'title' => 'Who can view',
            'class'   => 'horizontal',
            'options' => array(
                '1' => 'Administrators only',
                '2' => 'Logged-in users',
                '0' => 'Everyone',
            ),
            'default' => '0',
            'after' => 'Note: sites inside this category are not affected by this setting.<br />Permissions follow the parent: if the parent is set to "Administrators only", its children are visible to administrators only as well.',
        ),
        array(
            'id' => '_term_ico',
            'type' => 'icon',
            'title' => 'Menu icon',
            'default' => 'fa fa-chrome'
        ),
        array(
            'id' => '_term_order',
            'type' => 'text',
            'title' => 'Order',
            'after' =>'Higher numbers come first',
            'default'   => '0',
        ),
        array(
            'id' => '_cat_color',
            'type' => 'color_picker',
            'title' => 'Custom color',
            'after' => "Overrides the automatic color used for this category's heading and sidebar link. Leave empty to use the automatic color.",
            'default' => '',
        ),
        array(
            'type'    => 'notice',
            'content' => '<b><span style="color:red">Note:</span> if a new category does not show up on the homepage, check that its Order field has a value. If it is empty, set one - the default is 0.</b>',
            'class'   => 'info',
        ),
    ),
);
CSFramework_Taxonomy::instance( $options );