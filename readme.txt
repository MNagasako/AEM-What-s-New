=== Mngsk Recent Content List ===
Contributors: nagasako
Tags: shortcode, news, recent-posts, category
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.6.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display a configurable list of recent posts and pages with a distinctive shortcode and theme-ready CSS classes.

== Description ==

Use `[mngsk_recent_content]` to display recent published posts or pages. It uses WordPress core APIs only and has no external dependencies or build step.

* Filter by category, post type, date range, or individual post IDs.
* Configure the title, date format, NEW! mark, list layout, and optional category and post-type labels.
* Choose normal or in-place asynchronous pagination, including numbered, previous/next, and load-more styles.
* Use the Settings screen to set site-wide defaults, with a live preview.
* Style the output from the theme's Additional CSS feature using the documented `mngsk-recent-content` CSS classes. This plugin does not accept or output arbitrary CSS.

The primary CSS classes are `.mngsk-recent-content`, `__title`, `__item`, `__date`, `__type`, `__category`, `__newmark`, and `__pagination`.

== Installation ==

1. Upload the `mngsk-recent-content-list` folder to `/wp-content/plugins/`, or install the ZIP from the Plugins screen.
2. Activate **Mngsk Recent Content List**.
3. Insert `[mngsk_recent_content]` into a page or post.

Set defaults at **Settings > Mngsk Recent Content List**. Explicit shortcode attributes take priority.

== Frequently Asked Questions ==

= Can I include pages? =

Yes. `post_type="post,page"` is the default.

= How can I change the appearance? =

Use your theme's Additional CSS feature or the Site Editor. Target the plugin's `mngsk-recent-content` CSS classes; the plugin has no custom-CSS input field.

= Does pagination require JavaScript? =

Only `pagination_mode="async"`. The default `sync` mode works with ordinary links and no JavaScript.

== Changelog ==

= 1.6.3 =
* Fixed initial pagination page count in multilingual (Bogo) setups by ensuring language meta query is applied during initial page render.

= 1.6.2 =
* Added multilingual support (Bogo, Polylang) for asynchronous pagination, ensuring language filtering is preserved during Ajax page navigation.
* Preserved originating page URLs in Ajax-generated pagination links.

= 1.6.1 =
* Renamed packaged files and completed documentation updates for the `mngsk` public identifiers.

= 1.6.0 =
* Renamed the plugin and public identifiers with the distinctive `mngsk` prefix.
* Removed the arbitrary Custom CSS setting; use theme CSS classes instead.

== Upgrade Notice ==

= 1.6.1 =
Use `[mngsk_recent_content]`; earlier shortcode names are no longer registered.
