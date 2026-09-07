# WebStack
The WordPress version of the WebStack theme. <a href="http://bestit.eu.org/">Visit the demo site</a>
<br/>

**Author:** NetSec ([51sec.org](https://51sec.org)) &middot; **Contact:** [jyan@51sec.org](mailto:jyan@51sec.org) &middot; **Source:** [github.com/JohnnyNetsec/WebStack](https://github.com/JohnnyNetsec/WebStack)
<br/>

### Disclaimer
Anything you publish with the WebStack theme — articles, text, images, video and so on — is your own doing, and any security or legal risk arising from it is yours to bear.


"Webstack Pro" was a paid project the author tried out in 2019. It is no longer maintained or supported, and it has nothing to do with <a href="https://github.com/WebStackPage/WebStackPage.github.io" target="_blank">Viggo</a>. The name was poorly chosen at the time and caused <a href="https://github.com/WebStackPage/WebStackPage.github.io" target="_blank">Viggo</a> unnecessary trouble, for which the author apologises.<br/>
Any "Webstack Pro" builds circulating online today are stolen copies of the author's 2019 work and are unconnected to the author or <a href="https://github.com/WebStackPage/WebStackPage.github.io" target="_blank">Viggo</a>.

### Homepage screenshot
<br/>

![Thumbnail_index](https://owen0o0.github.io/ioStaticResources/webstack/01.png)
<br/>

### Features

**Directory &amp; categories**
+ A dedicated **Sites** post type holds each directory entry (title, URL, description, icon, order, visibility), separate from ordinary posts/pages.
+ Site Categories go two levels deep at most, and a category with subcategories can't hold entries directly -- the admin editor enforces this with a single-select dropdown (a parent-with-children shows only as a group heading, not a selectable option) and re-validates it server-side on save, reverting an invalid publish attempt back to Draft with an explanation rather than silently accepting it.
+ Per-entry and per-category **visibility levels** -- Everyone, Logged-in users only, or Administrators only -- so parts of the directory can be private without a separate membership plugin.
+ Custom permalinks (`sites/{id}.html`), sortable "Order" columns with Quick Edit support on both Sites and Categories, and a category filter dropdown on the Sites list screen.
+ Optional **detail pages** per site (toggle in Theme Settings) -- off by default, so cards link straight to the target URL; when on, each site gets its own page with a QR code, related-sites suggestions, and comments.
+ Outbound clicks can be routed through an internal `/go/` redirect rather than linking straight out, if you'd rather not leak your own domain as the referrer -- and when it's on, each click is counted per site, visible as a sortable "Clicks" column on the Sites list.

**Link Health Check** (Sites -> Link Health)
Automatically or on demand, checks every site's URL and flags broken, unreachable, or duplicate entries, with one-click Trash (never a permanent delete). See [Link Health Check](#link-health-check) below for the full detail.

**Import/Export Bookmarks** (Sites -> Import/Export)
Import a browser's bookmarks HTML export straight into the directory (with a preview before anything is written), or export the whole directory back out as a bookmarks file. See [Import/Export Bookmarks](#importexport-bookmarks) below.

**Visitor-submitted sites**
A "Submit a Site" page template lets visitors propose a new entry (title, URL, description, icon or WeChat QR upload, category) without admin access. Submissions land as Pending, so nothing appears publicly until you review and publish it; a cookie-based cooldown limits repeat submissions from the same visitor.

**Search**
Front-end search matches against post titles as well as each site's description and URL (not just post content), and an optional "super search" widget lets visitors jump straight to ~30 third-party search engines and tools (Google, Bing, job boards, SEO tools, and more) without leaving the page.

**Appearance**
+ A light / dark / system color-scheme toggle, remembered per visitor, with no flash of the wrong theme on page load.
+ A site-count badge next to every category, in both the sidebar and the homepage.
+ Each homepage category section can be collapsed/expanded by clicking its heading -- handy once you have a lot of categories -- and remembers which ones you've collapsed the next time you visit.
+ When the site-card tooltip is set to "Summary," hovering a card shows the site's full title (in case it's too long to fit on the card itself) above its description.
+ Configurable cards-per-row, homepage bulletin ticker, "Friendly Links" (WordPress's built-in Links/Blogroll manager), and three independent ad slots (homepage, detail-page sidebar, footer).
+ Logo, favicon, and login-page branding are all swappable from Theme Settings without editing code.
+ A one-click shortcut back to wp-admin in the header, visible only to users who can manage the site -- useful since the theme hides WordPress's own admin bar on the front end.

**Theme Settings** (a full settings page under its own admin menu)
Covers icons/branding, general behavior (detail pages, tooltip content, cards-per-row, bulletins, friendly links, color scheme, how many sites to preview per homepage category), SEO meta (homepage keywords/description), custom CSS/JS injection, ad slot content, a settings backup/restore tool, and a set of optimization toggles: trimming unnecessary `<head>` output, restricting the REST API to logged-in users, disabling XML-RPC pingbacks and RSS/Atom feeds, and choosing a Gravatar mirror.

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
+ Feedback: <a href="https://51sec.org" target="_blank">NetSec</a> (<a href="mailto:jyan@51sec.org">jyan@51sec.org</a>)

<br/>

### Using the theme
+ Add your entries under the "Sites" post type in the WordPress admin
+ Categories go two levels deep at most, and parent categories should not hold entries of their own
  -- the editor enforces this: exactly one category is required before a Site can be published, only
  one category can ever be selected, and a category that has subcategories is shown only as a group
  heading (it cannot be assigned directly, since its subcategories are the ones you actually pick)
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

### Import/Export Bookmarks
Under **Sites -> Import/Export** in the admin, you can bring in an existing bookmark
collection or take one back out.

+ **Import** accepts a standard bookmarks HTML export from Chrome, Edge, or any
  browser using the same format (Firefox, Safari, etc.).
  - Folders become Site Categories automatically, matching an existing category
    of the same name in the same place rather than creating a duplicate. Folders
    nested more than two levels deep (the theme's own category depth limit) are
    flattened -- their bookmarks attach to the deepest category still allowed
    rather than being dropped or erroring out.
  - A link already in your library, or repeated more than once inside the file
    itself, is skipped rather than imported again, using the same address
    comparison Link Health uses (so the two features always agree on what counts
    as "the same link").
  - Nothing is written to your site until you confirm. Uploading a file only
    shows a **preview**: how many links were found, how many are duplicates or
    not usable, how many new categories would be created, and how many would
    actually be added -- so you can decide whether to proceed before anything
    changes.
  - Confirming imports in short batches with a progress bar, the same approach
    Link Health uses for checking links, so even a large bookmark collection
    never risks timing out the request.
+ **Export** downloads every Sites entry as a bookmarks HTML file, grouped by its
  current Site Category (matching the same two-level structure), ready to import
  into a browser or back into this same importer.

### Admin screenshots
<br/>

![Thumbnail_index](https://owen0o0.github.io/ioStaticResources/webstack/05.jpg)
![Thumbnail_index](https://owen0o0.github.io/ioStaticResources/webstack/06.png)
<br/>

### Credits
Originally created by <a href="https://www.iowen.cn" target="_blank">owen</a> (and LoveDoLove), whose work this theme is forked from. Thanks to <a href="https://github.com/WebStackPage/WebStackPage.github.io" target="_blank">Viggo</a> for the front-end design. This fork has since been extensively rewritten (Link Health, Import/Export Bookmarks, category redesign, the light/dark theme, and more) and is maintained by <a href="https://51sec.org" target="_blank">NetSec</a>.
<br/>

### License
Licensed under the <a href="https://www.gnu.org/licenses/old-licenses/gpl-2.0.html" target="_blank">GNU General Public License v2 (or later)</a> -- see [LICENSE](LICENSE) for the full text and copyright notices (both the original authors' and NetSec's). A fork or redistribution needs to keep those copyright notices intact and stay open-source under GPL-compatible terms, per the license; it isn't required to keep any particular visible credit in the software's own UI.
<br/>

### Updates
<a href="https://github.com/JohnnyNetsec/WebStack/releases" target="_blank">Changelog</a>
To update, replace the source files, or delete the theme in the WordPress admin and install it again.
