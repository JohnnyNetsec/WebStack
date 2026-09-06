<?php 
/*
 * @Theme Name:WebStack
 * @Theme URI:https://www.iotheme.cn/
 * @Author: NetSec
 * @Author URI: https://51sec.org
 * @Date: 2019-02-22 21:26:02
 * @LastEditors: NetSec
 * @LastEditTime: 2024-07-30 23:21:25
 * @FilePath: /WebStack/inc/frame/config/framework.config.php
 * @Description: 
 */
if ( ! defined( 'ABSPATH' ) ) { die; } // Cannot access pages directly.
// ===============================================================================================
// -----------------------------------------------------------------------------------------------
// FRAMEWORK SETTINGS
// -----------------------------------------------------------------------------------------------
// ===============================================================================================
$settings           = array(
  'menu_title'      => __('Theme Settings','io_setting'),
  'menu_type'       => 'menu', // menu, submenu, options, theme, etc.
  'menu_slug'       => 'io_get_option',
  'menu_position'   => 59,
  'menu_icon'       => CS_URI.'/assets/images/setting.png',
  'ajax_save'       => true,
  'show_reset_all'  => false,
  'framework_title' => 'WebStack '.__('Theme Settings','io_setting').'<style>.cs-framework .cs-body {min-height: 700px;}</style><span style="font-size: 14px;"> - V '.wp_get_theme()->get('Version').'</span>',
  //'framework_title' => 'Theme Settings',
);


// ---------------------------------------
// Icons  --------------------------------
// ---------------------------------------
$options[] = array(
    'name' => 'overwiew',
    'title' => 'Icon settings',
    'icon' => 'fa fa-star',
    'fields' => array(
        array(
            'id' => 'logo_normal',
            'type' => 'image',
            'title' => 'Upload logo',
            'add_title' => 'Upload',
            'after'    => '<p class="cs-text-muted">'.'Recommended height: 80px',
            'default'   => get_theme_file_uri('/images/netsec-logo.svg'),
        ),
        array(
            'id' => 'logo_small',
            'type' => 'image',
            'title' => 'Square logo',
            'add_title' => 'Upload',
            'after'    => '<p class="cs-text-muted">'.'Recommended size: 80x80',
            'default'   => get_theme_file_uri('/images/netsec-logo-mark.svg'),
        ),
        array(
            'id' => 'favicon',
            'type' => 'image',
            'title' => 'Upload favicon',
            'add_title' => 'Upload',
            'default'   => get_theme_file_uri('/images/netsec-favicon.png'),
        ),
        array(
            'id' => 'apple_icon',
            'type' => 'image',
            'title' => 'Upload Apple touch icon',
            'add_title' => 'Upload',
            'default'   => get_theme_file_uri('/images/netsec-app-icon.png'),
        ),
        array(
            'id'      => 'login_beautify',
            'type'    => 'switcher',
            'title'   => 'Styled login page',
            'default' => true,
        ),
        array(
            'id' => 'login_img',
            'type' => 'image',
            'title' => 'Login page background',
            'add_title' => 'Upload',
            'default'   => get_theme_file_uri('/images/login.jpg'),
			'dependency' => array( 'login_beautify', '==', true )
        ),
        array(
            'id' => 'login_logo',
            'type' => 'image',
            'title' => 'Login page logo',
            'add_title' => 'Upload',
            'after'    => '<p class="cs-text-muted">'.'Recommended height: 80px',
            'default'   => get_theme_file_uri('/images/netsec-logo-dark.svg'),
			'dependency' => array( 'login_beautify', '==', true )
        ),
        array(
            'id' => 'login_color_l',
            'type' => 'color_picker',
            'title' => 'Login background color - left',
            'default'   => '#7d00a0',
			'dependency' => array( 'login_beautify', '==', true )
        ),
        array(
            'id' => 'login_color_r',
            'type' => 'color_picker',
            'title' => 'Login background color - right',
            'default'   => '#c11b8d',
			'dependency' => array( 'login_beautify', '==', true )
        ),
    ),
);


// ---------------------------------------
// Other  --------------------------------
// ---------------------------------------
$options[] = array(
    'name' => 'other_settings',
    'title' => 'General settings',
    'icon' => 'fa fa-list',
    'fields' => array(
        array(
            'id'      => 'details_page',
            'type'    => 'switcher',
            'title'   => 'Detail page',
            'desc'    => 'Show the site detail page',
            'after'   => '<br><p>When off, site cards link straight to the target URL</p>',
            'default' => false,
        ),
        array(
            'id'      => 'po_prompt',
            'type'    => 'radio',
            'title'   => 'Site card tooltip',
            'desc'    => 'Default tooltip content for site cards',
            'default' => 'url',
            'class'   => 'horizontal',
            'options' => array(
                'null'      => 'None',
                'url'       => 'Link',
                'summary'   => 'Summary',
                'qr'        => 'QR code'
            ),
            'after'   => 'Ignored when the site has its own QR code',
        ),
        array(
            'id'      => 'columns',
            'type'    => 'radio',
            'title'   => 'Cards per row',
            'desc'    => 'How many cards to show per row',
            'default' => 'col-sm-4 col-md-3',
            'class'   => 'horizontal',
            'options' => array(
                'col-sm-6'                    => '2',
                'col-sm-4'                    => '3',
                'col-sm-4 col-md-3'           => '4',
                'col-sm-4 col-md-3 col-lg-2'  => '6'
            ),
        ),
        

        
        array(
            'id'         => 'bulletin',
            'type'       => 'switcher',
            'title'      => 'Show bulletins',
            'desc'      => 'Show bulletins at the top of the homepage',
            'default'    => true,
        ),
        array(
            'id'         => 'bulletin_n',
            'type'       => 'text',
            'title'      => 'Number of bulletins',
            'after'      => 'How many bulletins to display',
            'default'    => 2,
            'dependency' => array( 'bulletin', '==', 'true' )
        ),
        array(
            'id'         => 'links',
            'type'       => 'switcher',
            'title'      => 'Friendly links',
            'label'      => 'Show friendly links at the bottom of the homepage',
            'default'    => true,
        ),


        array(
            'type'    => 'notice',
            'content' => 'Other settings',
            'class'   => 'info',
        ),
        array(
            'id'      => 'theme_mode',
            'type'    => 'radio',
            'title'   => 'Color scheme',
            'default' => 'white',
            'class'   => 'horizontal',
            'options' => array(
                'black'     => 'Dark',
                'white'     => 'Light'
            ),
        ),
		array(
			'id'      => 'site_n',
			'type'    => 'number',
			'title'   => 'Number of sites',
			'default' => '-1',
            'desc'    => 'How many sites to show under each homepage category',
            'after'   => '<p>-1 shows every site in the category</p>',
		),
        array(
            'id'      => 'icp',
            'type'    => 'text',
            'title'   => 'ICP license number',
        ),
        array(
            'id'      => 'police_icp',
            'type'    => 'text',
            'title'   => 'Public security registration number',
        ),
        array(
            'id'      => 'lazyload',
            'type'    => 'switcher',
            'title'   => 'Lazy-load icons',
            'default' => false,
        ),
        array(
            'id'      => 'is_search',
            'type'    => 'switcher',
            'title'   => 'Search',
            'default' => true,
        ),
        array(
            'id'      => 'is_go',
            'type'    => 'switcher',
            'title'   => 'Internal redirect',
            'default' => false,
        ),
        array(
            'type'    => 'notice',
            'content' => 'Icon source settings',
            'class'   => 'info',
        ),
        array(
            'id'      => 'ico_url',
            'type'    => 'text',
            'title'   => 'Icon source',
            'default' => 'https://t3.gstatic.cn/faviconV2?client=SOCIAL&type=FAVICON&fallback_opts=TYPE,SIZE,URL&size=128&url=',
            'desc'    => 'API URL',
            'after'   => 'Default API URL: https://t3.gstatic.cn/faviconV2?client=SOCIAL&type=FAVICON&fallback_opts=TYPE,SIZE,URL&size=128&url=<br>If icon fetching stops working, search for a website favicon API and swap in one that works',
        ),
        array(
            'id'      => 'url_format',
            'type'    => 'switcher',
            'title'   => 'Exclude http(s)://',
            'default' => false,
            'desc'    => 'Enable this if your icon API expects URLs without the protocol prefix',
        ),
        array(
            'id'      => 'ico_png',
            'type'    => 'text',
            'title'   => 'Icon API suffix',
            'desc'    => 'For example .png - set this to match your API, or leave it empty if not needed',
        ),
    ),
);

// ----------------------------------------
// SEO-------------------------------------
// ----------------------------------------
$options[] = array(
    'name' => 'speed',
    'title' => 'SEO settings',
    'icon' => 'fa fa-magic',
    'fields' => array(

        array(
            'id' => 'seo_home_keywords', // this is must be unique
            'type' => 'text',
            'title' => 'Homepage keywords',
        ),

        array(
            'id' => 'seo_home_desc', // this is must be unique
            'type' => 'textarea',
            'title' => 'Homepage description',
        ),
    ),
);
// ----------------------------------------
// Custom code-------------------------------
// ----------------------------------------
$options[] = array(
    'name' => 'code',
    'title' => 'Custom code',
    'icon' => 'fa fa-code',
    'fields' => array(
        array(
            'id' => 'custom_css',
            'type' => 'wysiwyg',
            'title' => 'Custom CSS',
            'desc' => 'Output before &lt;/head&gt;',
            'after'    => '<p class="cs-text-muted">'.__('Custom CSS for your own styling. For example:','io_setting').'body .test{color:#ff0000;}</p>',
            'settings' => array(
                'textarea_rows' => 5,
                'tinymce'       => false,
                'media_buttons' => false,
            )
        ),
        array(
            'id' => 'code_head_js',
            'type' => 'wysiwyg',
            'title' => 'Custom JS in head',
            'desc' => 'Output before &lt;/head&gt;',
            'after'    => '<p class="cs-text-muted">'.__('Appears before &lt;/head&gt;','io_setting').'</p>',
            'settings' => array(
                'textarea_rows' => 5,
                'tinymce'       => false,
                'media_buttons' => false,
            )
        ),
        array(
            'id' => 'code_2_footer',
            'type' => 'wysiwyg',
            'title' => 'Custom JS in footer',
            'desc' => 'Output at the bottom of the page',
            'after'    => '<p class="cs-text-muted">'.__('Appears before the closing body tag, typically used for analytics code...</p>','io_setting'),
            'settings' => array(
                'textarea_rows' => 5,
                'tinymce'       => false,
                'media_buttons' => false,
            )
        ),
    )
);
// ----------------------------------------
// Ads-------------------------------
// ----------------------------------------
$options[] = array(
    'name' => 'ad',
    'title' => 'Ads',
    'icon' => 'fa fa-google',
    'fields' => array(
        array(
            'id'      => 'ad_home_s',
            'type'    => 'switcher',
            'title'   => 'Homepage top ad slot',
            'default' => false,
        ),
        array(
            'id'      => 'ad_right_s',
            'type'    => 'switcher',
            'title'   => 'Detail page right ad slot',
            'default' => true,
        ),
        array(
            'id'      => 'ad_footer_s',
            'type'    => 'switcher',
            'title'   => 'Footer ad slot',
            'default' => false,
        ),
        array(
            'id'         => 'ad_home',
            'type'       => 'wysiwyg',
            'title'      => 'Homepage top ad content',
            'default'    => '',
            'settings'   => array(
              'textarea_rows' => 5,
              'tinymce'       => false,
              'media_buttons' => false,
            ),
			'dependency' => array( 'ad_home_s', '==', true )
        ),
        array(
            'id'         => 'ad_right',
            'type'       => 'wysiwyg',
            'title'      => 'Detail page right ad content',
            'default'    => '',
            'settings'   => array(
              'textarea_rows' => 5,
              'tinymce'       => false,
              'media_buttons' => false,
            ),
			'dependency' => array( 'ad_right_s', '==', true )
        ),
        array(
            'id'         => 'ad_footer',
            'type'       => 'wysiwyg',
            'title'      => 'Footer ad content',
            'default'    => '',
            'settings'   => array(
              'textarea_rows' => 5,
              'tinymce'       => false,
              'media_buttons' => false,
            ),
			'dependency' => array( 'ad_footer_s', '==', true )
        ),
    )
);

// ----------------------------------------
// Optimization-------------------------------
// ----------------------------------------
$options[] = array(
	'name'  => 'optimization',
	'title' => __('Optimization','io_setting'),
	'icon'  => 'fa fa-wordpress',

  	'fields' => array(
		array(
			'id'      => 'ioc_article',
			'type'    => 'switcher',
			'title'   => __('Go to the post list after admin login','io_setting'),
			'desc'    => __('WordPress normally opens the dashboard after login. With this on, the post list is shown instead (on by default)','io_setting'),
			'default' => true
		),
		array(
			'id'      => 'ioc_wp_head',
			'type'    => 'switcher',
			'title'   => __('Remove unnecessary head output','io_setting'),
			'desc'    => __('Removes surplus tags from the WordPress head, which improves site security (on by default)','io_setting'),
			'default' => true
		),
		array(
			'id'      => 'ioc_api',
			'type'    => 'switcher',
			'title'   => __('Disable the REST API','io_setting'),
			'desc'    => __('Disables the REST API and removes the wp-json link (off by default; recommended if your site has no companion app)','io_setting'),
			'default' => false
		),
		array(
			'id'      => 'ioc_pingback',
			'type'    => 'switcher',
			'title'   => 'XML-RPC',
			'desc'    => __('Closes the XML-RPC pingback endpoint (on by default, improves security)','io_setting'),
			'default' => true
		),
		array(
			'id'      => 'ioc_feed',
			'type'    => 'switcher',
			'title'   => 'Feed',
			'desc'    => __('Feeds are easily scraped and waste server resources (on by default)','io_setting'),
			'default' => true
		),
		array(
			'id'      => 'ioc_category',
			'type'    => 'switcher',
			'title'   => __('Remove the category base','io_setting'),
			'desc'    => __('Removes /category/ from URLs, which helps SEO. Re-save your permalinks each time you toggle this! (off by default)','io_setting'),
			'default' => true
		),
		array(
			'id'      => 'ioc_login_language',
			'type'    => 'switcher',
			'title'   => 'Remove login language switcher',
			'desc'    => __('Removes the language switcher added to the login page in WP 5.9. Turn this off if you use the styled login page.','io_setting'),
			'default' => true
		),
        array(
            'id'      => 'gravatar',
            'type'    => 'select',
            'title'   => 'Gravatar acceleration',
            'default' => 'chinayes',
            'options' => array(
                'gravatar'    => __('Use the official Gravatar servers','io_setting'),
                'cravatar'    => __('Use the Cravatar mirror','io_setting'),
                'iocdn'    => 'CDN mirror (cdn.iocdn.cc)',
                'chinayes'    => __('Use the wp-china-yes.cn mirror','io_setting')
            ),
        ),
		
	),
);

// ----------------------------------------
// Backup-------------------------------------
// ----------------------------------------
$options[] = array(
    'name' => 'advanced',
    'title' => 'Backup',
    'icon' => 'fa fa-shield',
    'fields' => array(

        array(
            'type' => 'notice',
            'class' => 'danger',
            'content' => 'Save your current options, download a backup, or import one. (Importing overwrites your current settings, so proceed with care.)',
        ),

        // Backup
        array(
            'type' => 'backup',
        ),

    )
);
CSFramework::instance( $settings, $options );
