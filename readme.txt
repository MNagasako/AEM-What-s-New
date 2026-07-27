=== AEM What's New ===
Contributors: mnagasako
Tags: shortcode, whats-new, news, recent-posts, category
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

トップページ等に「新着情報」一覧を表示する軽量プラグイン。WordPress標準API(WP_Query + ショートコードAPI)だけで動作し、ビルド手順や外部ライブラリは不要。

== Description ==

`[aem_whatsnew]` ショートコードで、指定カテゴリの新着記事一覧をシンプルなリストとして表示します。

* 対象/除外カテゴリはスラッグ・カテゴリ名・IDのいずれでも指定可能 — サイトごとにカテゴリIDが異なっていても、コードを書き換えずに複数サイトで使い回せます。
* `NEW!` マークの表示日数、投稿種別、並び順(投稿日/更新日)などを属性で調整可能。
* `WP_Query` を使って一覧を取得するため、User Access Manager 等の閲覧制限プラグインと併用しても、権限のない記事は一覧に出ません。
* 旧プラグイン「What's New Generator」からの移行を想定し、同名ショートコード `[showwhatsnew]` も(旧プラグインが有効でない場合に限り)自動登録します。

詳しい属性一覧・使用例は [README](https://github.com/MNagasako/AEM-What-s-New#使い方usage) を参照してください。

== Installation ==

1. `aem-whatsnew` フォルダごと `wp-content/plugins/` に配置する。
2. 管理画面の「プラグイン」から有効化する。
3. 表示したいページ/投稿に `[aem_whatsnew]` を挿入する。

設定画面はありません。表示内容はすべてショートコードの属性で指定します。

== Frequently Asked Questions ==

= カテゴリIDを直接指定しないといけませんか? =

いいえ。`category="お知らせ"` のようにカテゴリ名やスラッグで指定できます。数値を渡した場合のみIDとして扱われます。

= 旧プラグイン「What's New Generator」と同時に有効化しても大丈夫ですか? =

移行期間中は問題ありません。旧プラグインの `[showwhatsnew]` ショートコードが既に登録されている場合、本プラグインはそれを上書きしません。

= 固定ページも一覧に含めたいのですが =

`post_type="post,page"` を指定してください(既定値がこれです)。投稿タイプを絞りたい場合は `post_type="post"` のように指定します。

== Changelog ==

= 1.0.0 =
* 初版。外部プラグイン「What's New Generator」の置き換えとして、東北大学金研分析電顕室サイトで運用してきたものをリポジトリ化。

== Upgrade Notice ==

= 1.0.0 =
初版リリース。
