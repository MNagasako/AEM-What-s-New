=== AEM What's New ===
Contributors: mnagasako
Tags: shortcode, whats-new, news, recent-posts, category
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A lightweight "what's new" list plugin. Works with WordPress core APIs (WP_Query + Shortcode API) only — no build step, no external libraries.

== Description ==

The `[aem_whatsnew]` shortcode displays a simple list of recent posts/pages, optionally filtered by category.

* Included/excluded categories can be specified by slug, name, or ID — the same shortcode works across multiple sites even when category IDs differ.
* The "NEW!" mark's duration, post types, and sort order (publish/modified date) are all configurable via shortcode attributes.
* Optional category and post-type columns, a title character limit, a customizable "NEW!" mark string, and a custom-CSS box in the settings screen.
* An optional single-line layout (date, type, category, and title in one row) in addition to the default stacked layout.
* A "Settings > AEM What's New" admin screen (with a live preview) lets you edit all of the above defaults without touching a shortcode. Explicit shortcode attributes always win over the saved settings.
* The admin screen and the built-in default text (heading, "NEW!" mark, empty-state text) can be shown in Japanese or English, either automatically (based on the site's language) or forced manually.
* Uses `WP_Query` (not `get_posts()` with filters suppressed), so plugins like User Access Manager that restrict post visibility are respected — restricted posts never leak into the list.
* Designed as a drop-in replacement for the discontinued "What's New Generator" plugin: it registers the same `[showwhatsnew]` shortcode automatically, but only while the old plugin is not active, so both can be enabled at once during migration.

See the [README](https://github.com/MNagasako/AEM-What-s-New) on GitHub for the full attribute reference and examples.

= Acknowledgments =

Many thanks to Hideki Tanaka (WordPress.org username `hidakabizplugin`), author of the WordPress plugin "What's New Generator" (slug: `whats-new-genarator`), which this project replaces after years of use on our site.

That plugin was closed on WordPress.org on 2024-06-15 for a security issue and is no longer downloadable (its last release was 2.0.2). Since the source and detailed specification were no longer available to us, this plugin was written from scratch using WordPress core APIs only, based on observing the original plugin's output and configured behavior on our production site rather than reusing any of its code.

== Installation ==

1. Copy the `aem-whatsnew` folder into `wp-content/plugins/`.
2. Activate **AEM What's New** from the Plugins screen.
3. Insert `[aem_whatsnew]` into any page or post where you want the list to appear.

Default display values can be changed from "Settings > AEM What's New" (with a live preview). Attributes set explicitly on the shortcode always take priority over these settings.

== Frequently Asked Questions ==

= Do I have to use numeric category IDs? =

No. You can use a category name or slug, e.g. `category="announcements"`. A value is only treated as an ID when it is purely numeric.

= Can this run alongside "What's New Generator"? =

Yes, temporarily. If the old plugin's `[showwhatsnew]` shortcode is already registered, this plugin will not override it, so both plugins can be active at once during migration.

= Can pages be included in the list, not just posts? =

Yes — set `post_type="post,page"` (this is already the default). Use `post_type="post"` to restrict it to posts only.

= Can I change the admin screen language? =

Yes. The "Admin screen & default text language" setting supports Auto (follows the site's language), Japanese, or English.

== Changelog ==

= 1.3.0 =
* Added a `layout` option: the default "stacked" layout, or a new "inline" layout that puts date/type/category/title on a single row.
* Added a Japanese/English switch (`ui_language`: auto/ja/en) for the admin screen and the built-in default text.
* readme.txt is now primarily in English, with a Japanese section at the end, ahead of a planned WordPress.org submission.

= 1.2.0 =
* Added optional category and post-type columns (`show_category`/`show_type`; `category_limit` caps how many categories are listed per item).
* Added a title character limit (`title_max_length`; overflow is replaced with "…").
* The "NEW!" mark text is now editable (`newmark_text`).
* Added a "Custom CSS" box in the settings screen, loaded in addition to the bundled stylesheet, to restyle the "NEW!" mark and the list layout.

= 1.1.1 =
* The "Post types" setting changed from free text to checkboxes, automatically listing the site's actual public post types.

= 1.1.0 =
* Added a "Settings > AEM What's New" admin screen with a live preview, covering every shortcode default. Explicit shortcode attributes still take priority.

= 1.0.0 =
* Initial release. Packaged from the version already running in production on the Institute for Materials Research (Tohoku University) AEM/analytical electron microscopy site, as a replacement for the external "What's New Generator" plugin.

== Upgrade Notice ==

= 1.3.0 =
Adds an inline layout option and a Japanese/English display-language switch. No visual change unless you opt in.

= 1.2.0 =
Adds category/type columns, a title length limit, a custom "NEW!" string, and custom CSS. Everything defaults to off — no visual change unless you opt in.

= 1.1.1 =
The "Post types" setting is now a checkbox list. Existing saved values are carried over automatically.

= 1.1.0 =
Adds a "Settings > AEM What's New" admin screen. No effect on existing shortcode usage.

= 1.0.0 =
Initial release.

== 日本語 (Japanese) ==

トップページ等に「新着情報」一覧を表示する軽量プラグイン。WordPress標準API(WP_Query + ショートコードAPI)だけで動作し、ビルド手順や外部ライブラリは不要です。

`[aem_whatsnew]` ショートコードで、指定カテゴリの新着記事一覧をシンプルなリストとして表示します。対象/除外カテゴリはスラッグ・カテゴリ名・IDのいずれでも指定可能、`NEW!`マークの表示日数・投稿種別・並び順なども属性で調整できます。カテゴリ列・投稿タイプ列の追加表示、タイトルの文字数制限、`NEW!`マーク文言の変更、レイアウト(積み重ね/1行)の切り替え、カスタムCSSによる見た目の上書きにも対応しています。

「設定 > AEM What's New」の管理画面(プレビュー付き)から、ショートコードを書かずに既定値を編集できます(ショートコード属性を明示指定した場合はそちらが優先)。管理画面表示・既定文言は日本語/英語を自動または手動で切り替え可能です。

`WP_Query`を使って一覧を取得するため、User Access Manager等の閲覧制限プラグインと併用しても、権限のない記事は一覧に出ません。

旧プラグイン「What's New Generator」(Hideki Tanaka氏作、WordPress.orgスラッグ`whats-new-genarator`)からの移行を想定し、同名ショートコード`[showwhatsnew]`も(旧プラグインが有効でない場合に限り)自動登録します。同プラグインは2024年6月15日付でセキュリティ上の問題によりWordPress.orgから公開停止となっており、本プラグインはそのソースコードを流用せず、本番サイトでの出力・挙動の観察をもとにゼロから独自に再実装したものです。

属性の全一覧・使用例など詳細は [GitHubのREADME](https://github.com/MNagasako/AEM-What-s-New) を参照してください。
