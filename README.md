# WebStack
The WordPress version of the WebStack theme. <a href="http://bestit.eu.org/">Visit the demo site</a>
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

### Link Health Check
Under **Sites -> Link Health** in the admin, the theme can check whether the URL on
every "Sites" entry is still reachable.

+ **Check all links now** runs a check on demand. Links are checked several at a
  time (configurable, default 3) rather than strictly one by one, so a large
  library finishes noticeably faster; either way, larger libraries are checked in
  short batches so the request never times out.
+ **Automatic checks** can run Daily, Weekly or Monthly via WP-Cron, or stay Manual
  only. Scheduled runs only ever update the report — they never delete or modify
  anything on their own.
+ Each link is classified as:
  - **OK** — reachable (a redirect counts as reachable).
  - **Broken** — a 404, a server error, or the domain could not be resolved at all.
  - **Could not verify** — the request timed out, hit an SSL problem, or the site
    returned 401/403/405/429. These responses usually mean the site is blocking
    automated requests (Cloudflare and similar are common causes), not that it is
    down, so they are kept separate from Broken rather than counted as dead links.
  - **Duplicate** — another Sites entry already points at the same address. The
    oldest entry is left alone; newer ones pointing at the same place are flagged
    here instead. This check is instant (no network request), so it always runs as
    part of a scan and does not slow anything down.
  - A link only becomes Broken after failing two checks in a row (configurable), so
    a brief outage on the target site does not misreport it.
+ Results are filterable by status, and each entry has a one-click **Recheck**.
+ A small colored dot (green/yellow/red/blue) appears on each site's icon on the
  front end, showing its most recent status at a glance. Hover it to see why. It
  only appears once a link has been checked at least once.
+ Broken, unreachable and duplicate entries can be moved to **Trash** — one at a
  time from the row, or in bulk by selecting several (or using "select all") and
  clicking **Move selected to Trash**. This always uses WordPress's Trash, never a
  permanent delete, so anything removed by mistake can still be restored from
  Sites > Trash.

### Admin screenshots
<br/>

![Thumbnail_index](https://owen0o0.github.io/ioStaticResources/webstack/05.jpg)
![Thumbnail_index](https://owen0o0.github.io/ioStaticResources/webstack/06.png)
<br/>

### Credits
Thanks to <a href="https://github.com/WebStackPage/WebStackPage.github.io" target="_blank">Viggo</a> for the front-end design.
<br/>

### Updates
<a href="https://github.com/JohnnyNetsec/WebStack/releases" target="_blank">Changelog</a>
To update, replace the source files, or delete the theme in the WordPress admin and install it again.
