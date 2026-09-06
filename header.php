<?php
/*
 * @Author: iowen
 * @Author URI: https://www.iowen.cn/
 * @Date: 2021-02-21 21:26:02
 * @LastEditors: iowen
 * @LastEditTime: 2024-07-30 19:49:22
 * @FilePath: /WebStack/header.php
 * @Description: 
 */ 
if ( ! defined( 'ABSPATH' ) ) { exit; } 
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0, user-scalable=no">
<?php if ( is_home() || is_front_page() ) : ?>
<title><?php bloginfo('name'); ?> | <?php bloginfo( 'description');?></title>
<?php else : ?>
<title><?php wp_title( '|', true, 'right' ); bloginfo('name'); ?></title>
<?php endif; ?>
<meta name="theme-color" content="#2C2E2F" />
<meta name="keywords" content="<?php echo io_get_option('seo_home_keywords') ?>">
<meta name="description" content="<?php echo io_get_option('seo_home_desc') ?>">
<meta property="og:type" content="article">
<meta property="og:url" content="<?php echo home_url() ?>">
<meta property="og:title" content="<?php echo io_get_option('seo_home_desc') ?>">
<meta property="og:description" content="<?php echo io_get_option('seo_home_keywords') ?>">
<meta property="og:image" content="<?php echo get_theme_file_uri('/screenshot.jpg') ?>">
<meta property="og:site_name" content="<?php echo io_get_option('seo_home_desc') ?>">
<link rel="shortcut icon" href="<?php echo io_get_option('favicon') ?>">
<link rel="apple-touch-icon" href="<?php echo io_get_option('apple_icon') ?>">
<?php wp_head(); ?>
</head> 
 <body <?php body_class('page-body '.io_get_option('theme_mode')) ?>>
    <script>
    (function(){
        // Apply the visitor's own light/dark/system choice as early as possible,
        // before anything paints, so there is no flash of the wrong theme.
        // Falls back to whatever this site's own "Color scheme" setting rendered
        // server-side (the "black" body class above) when there's no stored
        // choice and the OS itself has no preference either way.
        var KEY = 'io_theme_pref';
        var serverDark = document.body.classList.contains('black');
        function readPref(){
            // Private-browsing/blocked-storage configurations can throw here
            // rather than just returning null -- never let that break the page.
            try { return localStorage.getItem(KEY) || 'system'; } catch (e) { return 'system'; }
        }
        function writePref(pref){
            try { localStorage.setItem(KEY, pref); } catch (e) {}
        }
        function systemPrefersDark(){
            return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        }
        function systemPrefersLight(){
            return window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches;
        }
        function resolveIsDark(pref){
            if (pref === 'dark') return true;
            if (pref === 'light') return false;
            if (systemPrefersDark()) return true;
            if (systemPrefersLight()) return false;
            return serverDark;
        }
        function apply(pref){
            document.body.classList.toggle('black', resolveIsDark(pref));
        }
        apply(readPref());
        window.ioApplyThemePref = apply;
        window.ioReadThemePref = readPref;
        window.ioWriteThemePref = writePref;
        if (window.matchMedia) {
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(){
                if (readPref() === 'system') apply('system');
            });
        }
    })();
    </script>
    <div class="page-container">
