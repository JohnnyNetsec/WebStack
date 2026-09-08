<?php
/*
 * @Theme Name:WebStack
 * @Theme URI:https://www.iotheme.cn/
 * @Author: NetSec
 * @Author URI: https://51sec.org
 * @Date: 2019-02-22 21:26:02
 * @LastEditors: NetSec
 * @LastEditTime: 2024-07-30 21:03:08
 * @FilePath: /WebStack/templates/site-card.php
 * @Description: 
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }  ?>

            <?php
            $title = $link_url;
            $is_html = '';
            $tooltip = 'data-toggle="tooltip" data-placement="bottom"';
            if(get_post_meta($post->ID, '_wechat_qr', true)){
                $title="<img src='" . get_post_meta(get_the_ID(), '_wechat_qr', true) . "' width='128'>";
                $is_html = 'data-html="true"';
            } else {
                switch(io_get_option('po_prompt')) {
                    case 'null':  
                        $title = get_the_title();
                        $tooltip = '';
                        break;
                    case 'url': 
                        if($link_url=="")
                            $title = __('Invalid URL!','i_theme');
                        break;
                    case 'summary':
                        // Prefix with the full title (bold, on its own line) since the
                        // card's own on-screen title is truncated with an ellipsis when
                        // it's too long to fit -- otherwise a long title had no way to
                        // be read in full.
                        $title = '<strong>' . esc_html( get_the_title() ) . '</strong><br>' . esc_html( get_post_meta($post->ID, '_sites_sescribe', true) );
                        $is_html = 'data-html="true"';
                        break;
                    case 'qr':
                        if($link_url=="")
                            $title = __('Invalid URL!','i_theme');
                        else{
                            $title = "<img src='//api.qrserver.com/v1/create-qr-code/?size=150x150&margin=10&data=" . $link_url . "' width='128'>";
                            $is_html = 'data-html="true"';
                        }
                        break;
                    default: 
                } 
            }
            $url = '';
            $blank = '_blank';
            if(io_get_option('details_page')){ 
                $url=get_permalink();
            }else{ 
                if($link_url==""){
                    $url = 'javascript:';
                    $blank = '';
                }else{
                    if(io_get_option('is_go'))
                        $url = home_url().'/go/?id='.$post->ID.'&url='.$link_url ;
                    else
                        $url = $link_url;
                }
            }
            $ico = io_theme_get_thumb();
            // Check whether this is a blog post
            if(get_post_type() == 'post'){
                $title = '';
                $url = get_permalink();
            }else{
                $ico = $ico ?: (io_get_option('ico_url') . format_url($link_url) . io_get_option('ico_png'));
            }

            $hc_dot = '';
            if ( 'sites' === get_post_type() && class_exists( 'IO_Link_Health' ) ) {
                $hc_status = get_post_meta( $post->ID, IO_Link_Health::META_STATUS, true );
                if ( $hc_status ) {
                    $hc_detail = get_post_meta( $post->ID, IO_Link_Health::META_DETAIL, true );
                    $hc_reason = ( is_array( $hc_detail ) && ! empty( $hc_detail['message'] ) ) ? $hc_detail['message'] : '';
                    $hc_title  = IO_Link_Health::status_label( $hc_status ) . ( $hc_reason ? ': ' . $hc_reason : '' );
                    $hc_dot    = '<span class="io-hc-dot io-hc-dot-' . esc_attr( $hc_status ) . '" title="' . esc_attr( $hc_title ) . '"></span>';
                }
            }

            // "New" ribbon for sites published in the last 30 days -- a curated
            // directory's own publish date is the only signal available for
            // "when was this added", so that's what this goes by. Compared in
            // GMT (get_post_time(..., true)) against time() (always UTC)
            // rather than the site's local time, so it isn't off by the site's
            // UTC offset.
            $new_badge = '';
            if ( 'sites' === get_post_type() ) {
                $published_gmt = get_post_time( 'U', true, $post );
                if ( $published_gmt && ( time() - $published_gmt ) < 30 * DAY_IN_SECONDS ) {
                    $new_badge = '<span class="io-new-badge">' . __( 'New', 'i_theme' ) . '</span>';
                }
            }
            ?>
            <a href="<?php echo $url ?>" target="<?php echo $blank ?>" class="xe-widget xe-conversations box2 label-info" <?php echo $tooltip . ' ' . $is_html ?> title="<?php echo $title ?>">
                <?php echo $new_badge; ?>
                <div class="xe-comment-entry">
                    <div class="xe-user-img">
                        <?php echo $hc_dot; ?>
                        <?php if(io_get_option('lazyload')): ?>
                        <img class="img-circle lazy" src="<?php echo $default_ico; ?>" data-src="<?php echo $ico ?>" onerror="javascript:this.src='<?php echo $default_ico; ?>'" width="20" height="20">
                        <?php else: ?>
                        <img class="img-circle lazy" src="<?php echo $ico ?>" onerror="javascript:this.src='<?php echo $default_ico; ?>'" width="20" height="20">
                        <?php endif ?>
                    </div>
                    <div class="xe-comment">
                        <div class="xe-user-name overflowClip_1">
                            <strong><?php the_title() ?></strong>
                        </div>
                        <p class="overflowClip_1"><?php echo get_post_meta($post->ID, '_sites_sescribe', true) ?: preg_replace("/(\s|\&nbsp\;|　|\xc2\xa0)/","",get_the_excerpt($post->ID)); ?></p>
                    </div>
                </div>
            </a>
            
