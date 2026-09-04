# WebStack
The WordPress version of the WebStack theme. <a href="http://webstack.iotheme.cn/">Visit the demo site</a>
<br/>

### Disclaimer
Anything you publish with the WebStack theme — articles, text, images, video and so on — is your own doing, and any security or legal risk arising from it is yours to bear.


"Webstack Pro" was a paid project the author tried out in 2019. It is no longer maintained or supported, and it has nothing to do with <a href="https://github.com/WebStackPage/WebStackPage.github.io" target="_blank">Viggo</a>. The name was poorly chosen at the time and caused <a href="https://github.com/WebStackPage/WebStackPage.github.io" target="_blank">Viggo</a> unnecessary trouble, for which the author apologises.<br/>
Any "Webstack Pro" builds circulating online today are stolen copies of the author's 2019 work and are unconnected to the author or <a href="https://github.com/WebStackPage/WebStackPage.github.io" target="_blank">Viggo</a>.

### Homepage screenshot
<br/>

![Thumbnail_index](https://owen0o0.github.io/ioStaticResources/webstack/01.png)
<br/>

### Requirements
+ WordPress 4.4+
+ WordPress pretty permalinks
+ PHP 5.7+ 7.0+
<br/>

### Installation
+ Install WordPress (there are plenty of guides online)
+ Set up pretty permalinks (pick whichever of the rules below matches your server)
```
# Nginx rule
location /
{
    try_files $uri $uri/ /index.php?$args;
}
rewrite /wp-admin$ $scheme://$host$uri/ permanent;

# Apache rule
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteBase /
RewriteRule ^index\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
</IfModule>
```
+ In the WordPress admin go to "Appearance" -> Upload Theme -> Activate. Alternatively, create a `webstack` folder under /wp-content/themes and upload all the files into it
+ If your links return a 404, go to "Settings" -> Permalinks in the WordPress admin and click Save Changes
+ Feedback: <a href="https://www.iowen.cn" target="_blank">iowen</a>

<br/>

### Using the theme
+ Add your entries under the "Sites" post type in the WordPress admin
+ Categories go two levels deep at most, and parent categories should not hold entries of their own
+ Site images are optional — the theme falls back to fetching the target site's favicon automatically
+ The icon shown in front of a navigation menu title comes from the category image description (see the screenshot below); icon styles follow Font Awesome
![Thumbnail_index](https://owen0o0.github.io/ioStaticResources/webstack/02.png)
+ A quicker way to add icons to a category
![Thumbnail_index](https://owen0o0.github.io/ioStaticResources/webstack/07.png)
+ Custom menus can be added below the navigation menu, under Appearance -> Menus in the admin. Set the icon in the menu item's CSS class field (see the screenshot below); icon styles follow Font Awesome
![Thumbnail_index](https://owen0o0.github.io/ioStaticResources/webstack/03.png)
+ If the CSS class field is missing from your menu, enable it as shown below
![Thumbnail_index](https://owen0o0.github.io/ioStaticResources/webstack/04.jpg)
+ <a href="https://www.iotheme.cn/store/onenav.html" target="_blank">Need more features? Click here -></a>
<br/>

### Admin screenshots
<br/>

![Thumbnail_index](https://owen0o0.github.io/ioStaticResources/webstack/05.jpg)
![Thumbnail_index](https://owen0o0.github.io/ioStaticResources/webstack/06.png)
<br/>

### Credits
Thanks to <a href="https://github.com/WebStackPage/WebStackPage.github.io" target="_blank">Viggo</a> for the front-end design.
<br/>

### Updates
<a href="https://github.com/owen0o0/WebStack/releases" target="_blank">Changelog</a>
To update, replace the source files, or delete the theme in the WordPress admin and install it again.
