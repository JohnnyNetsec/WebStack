<?php  
/*
 * @Theme Name:WebStack
 * @Theme URI:https://www.iotheme.cn/
 * @Author: NetSec
 * @Author URI: https://51sec.org
 * @Date: 2020-02-22 21:26:05
 * @LastEditors: NetSec
 * @LastEditTime: 2024-07-30 21:51:29
 * @FilePath: /WebStack/inc/fav-content.php
 * @Description: 
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
function fav_con($mid, $visible) { ?>
        <h4 class="text-gray io-cat-heading" data-target="#io-cat-body-<?php echo $mid->term_id; ?>" style="display: inline-block;"><i class="fa fa-angle-down io-cat-chevron"></i><i class="icon-io-tag" style="margin-right: 27px;" id="term-<?php echo $mid->term_id; ?>"></i><?php echo $mid->name; ?><span class="io-cat-count"><?php echo (int) $mid->count; ?></span></h4>
        <?php
        if($visible == 2){
            echo '<div class="login-notice">'.__('Please log in to view this category','i_theme').'</div>';
            return;
        }
        $site_n           = io_get_option('site_n');
        // $mid->count is the real WP_Term property (was $mid->category_count, which does
        // not exist on a term object and always evaluated to null -- silently breaking the
        // "more+" link below, and truncating the site_n==0 case to zero results).
        $category_count   = (int) $mid->count;
        $count            = $site_n;
        if($site_n == 0)  $count = min(get_option('posts_per_page'),$category_count);
        if($site_n >= 0 && $count < $category_count){
          $link = esc_url( get_term_link( $mid, 'res_category' ) );
          echo "<a class='btn-move' href='$link'>more+</a>";
        }
        ?>
        <div class="io-cat-body" id="io-cat-body-<?php echo $mid->term_id; ?>">
        <div class="row">
        <?php
          // Declare $post as a global so later output is not the same post
          global $post;
          // The posts_per_page setting below matters most
          $args = array(
            'post_type'           => 'sites',        // Custom post type, here 'sites'
            'ignore_sticky_posts' => 1,              // Ignore sticky posts
            'posts_per_page'      => $site_n,        // Number of posts to show
            'meta_key'            => '_sites_order',
            'orderby'             => array( 'meta_value_num' => 'DESC', 'ID' => 'DESC' ),
            'tax_query'           => array(
                array(
                    'taxonomy' => 'favorites',       // Taxonomy name
                    'field'    => 'id',              // Which term field to query by, here the ID
                    'terms'    => $mid->term_id,     // Term IDs: one category ID, or several as array(1,2)
                )
            ),
          );
          $myposts = new WP_Query( $args );
          if(!$myposts->have_posts()): ?>
          <div class="col-lg-12">
            <div class="nothing"><?php _e('No content found','i_theme') ?></div>
          </div>
          <?php
          elseif ($myposts->have_posts()): while ($myposts->have_posts()): $myposts->the_post(); 
            $link_url = get_post_meta($post->ID, '_sites_link', true); 
            $default_ico = get_theme_file_uri('/images/favicon.png');
            if(io_is_visible( get_post_meta($post->ID, '_visible', true))):
          ?>
            <div class="xe-card <?php echo io_get_option('columns') ?> <?php echo get_post_meta($post->ID, '_wechat_qr', true)? 'wechat':''?>">
              <?php include( get_theme_file_path() .'/templates/site-card.php' ); ?>
            </div>
          <?php endif; endwhile; endif; wp_reset_postdata(); ?>
        </div>
        </div>
        <br />
<?php } ?>