<?php 
/*
 * @Theme Name:WebStack
 * @Theme URI:https://github.com/JohnnyNetsec/WebStack
 * @Author: NetSec
 * @Author URI: https://51sec.org
 * @Date: 2019-02-22 21:26:02
 * @LastEditors: NetSec
 * @LastEditTime: 2023-04-24 00:42:32
 * @FilePath: \WebStack\footer.php
 * @Description: 
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$_icp = '';
if(io_get_option('icp')){
    $_icp .= '<a href="https://beian.miit.gov.cn/" target="_blank" rel="link noopener">' . io_get_option('icp') . '</a>&nbsp;';
}
if ($police_icp = io_get_option('police_icp')) {
    if (preg_match('/\d+/', $police_icp, $arr)) {
        $_icp .= ' <a href="http://www.beian.gov.cn/portal/registerSystemInfo?recordcode=' . $arr[0] . '" target="_blank" class="'.$class.'" rel="noopener">' . $police_icp . '</a>&nbsp;';
    }
}
?>
            <footer class="main-footer sticky footer-type-1">
                <div class="go-up">
                    <a href="#" rel="go-top">
                        <i class="fa fa-angle-up"></i>
                    </a>
                </div>
                <div class="footer-inner">
                    <div class="footer-text">
                        Copyright © <?php echo date('Y') ?> <?php bloginfo('name'); ?> <?php echo $_icp ?>
                        <span class="io-theme-credit">| <?php esc_html_e( 'Theme by', 'i_theme' ); ?> <a href="https://51sec.org" target="_blank" rel="noopener">NetSec</a></span>
                    </div>
                </div>
            </footer>
        </div>
    </div>
<?php if (is_home() || is_front_page()): ?>
    <script type="text/javascript">
    $(document).ready(function() {
        setTimeout(function () { 
            if($('a.smooth[href="'+window.location.hash+'"]')[0]){
                $('a.smooth[href="'+window.location.hash+'"]').click();
            } else if(window.location.hash != ''){
                $("html, body").animate({
                    scrollTop: $(window.location.hash).offset().top - 80
                }, {
                    duration: 500,
                    easing: "swing"
                });
            }
        }, 300);
        $(document).on('click', '.has-sub', function(){
            var _this = $(this)
            if(!$(this).hasClass('expanded')) {
                setTimeout(function(){
                    _this.find('ul').attr("style","")
                }, 300);
            } else {
                $('.has-sub ul').each(function(id,ele){
                    var _that = $(this)
                    if(_this.find('ul')[0] != ele) {
                        setTimeout(function(){
                            _that.attr("style","")
                        }, 300);
                    }
                })
            }
        })
        $('.user-info-menu .hidden-xs').click(function(){
            if($('.sidebar-menu').hasClass('collapsed')) {
                $('.has-sub.expanded > ul').attr("style","")
            } else {
                $('.has-sub.expanded > ul').show()
            }
        })
        $("#main-menu li ul li").click(function() {
            $(this).siblings('li').removeClass('active'); // Remove the active style from sibling elements
            $(this).addClass('active'); // Add the active style to the current element
        });
        $("a.smooth").click(function(ev) {
            ev.preventDefault();
            if($("#main-menu").hasClass('mobile-is-visible') != true)
                return;
            public_vars.$mainMenu.add(public_vars.$sidebarProfile).toggleClass('mobile-is-visible');
            ps_destroy();
            $("html, body").animate({
                scrollTop: $($(this).attr("href")).offset().top - 80
            }, {
                duration: 500,
                easing: "swing"
            });
        });
        $(document).on('click', '.io-cat-heading', function(){
            var $h = $(this);
            var target = $h.data('target');
            var $body = $(target);
            if (!$body.length) return;
            var key = 'io_cat_collapsed_' + target;
            var wasCollapsed = $h.hasClass('collapsed');
            $h.toggleClass('collapsed');
            $body.slideToggle(200);
            try { localStorage.setItem(key, wasCollapsed ? '0' : '1'); } catch (e) {}
        });
        $('.io-cat-heading').each(function(){
            var $h = $(this);
            var target = $h.data('target');
            var key = 'io_cat_collapsed_' + target;
            var saved;
            try { saved = localStorage.getItem(key); } catch (e) { saved = null; }
            if (saved === '1') {
                $h.addClass('collapsed');
                $(target).hide();
            }
        });
        return false;
    });

    var href = "";
    var pos = 0;
    $("a.smooth").click(function(e) {
        e.preventDefault();
        if($("#main-menu").hasClass('mobile-is-visible') === true)
            return;
        $("#main-menu li").each(function() {
            $(this).removeClass("active");
        });
        $(this).parent("li").addClass("active");
        href = $(this).attr("href");
        pos = $(href).position().top - 100;
        $("html,body").animate({
            scrollTop: pos
        }, 500);
    });
    </script>
<?php endif; ?>
<?php wp_footer(); ?>
<!-- Custom code -->
<?php echo io_get_option('code_2_footer');?>
<!-- end custom code -->
</body>
</html>