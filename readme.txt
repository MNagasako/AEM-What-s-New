=== AEM What's New ===
Contributors: mnagasako
Tags: shortcode, whats-new, news, recent-posts, category
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.2.0
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

= 謝辞・経緯 =

長らく運用していた、Hideki Tanaka氏(WordPress.orgユーザー名: `hidakabizplugin`)作の
WordPressプラグイン「What's New Generator」(WordPress.org スラッグ: `whats-new-genarator`)
に感謝します。同プラグインはWordPress.org
公式ディレクトリで配布されていましたが、2024年6月15日付で「セキュリティ上の問題」を理由に
公開停止となりダウンロードできなくなりました(最終版は v2.0.2)。ソースコードや仕様の情報を
改めて入手できなかったため、本番サイトでの出力・挙動の観察をもとに、WordPress標準APIのみで
ゼロから独自に再実装したものが本プラグインです。

== Installation ==

1. `aem-whatsnew` フォルダごと `wp-content/plugins/` に配置する。
2. 管理画面の「プラグイン」から有効化する。
3. 表示したいページ/投稿に `[aem_whatsnew]` を挿入する。

表示内容の既定値は「設定 > AEM What's New」の管理画面で変更できます(プレビュー付き)。
ショートコード側で属性を明示指定した場合は、そちらが優先されます。

== Frequently Asked Questions ==

= カテゴリIDを直接指定しないといけませんか? =

いいえ。`category="お知らせ"` のようにカテゴリ名やスラッグで指定できます。数値を渡した場合のみIDとして扱われます。

= 旧プラグイン「What's New Generator」と同時に有効化しても大丈夫ですか? =

移行期間中は問題ありません。旧プラグインの `[showwhatsnew]` ショートコードが既に登録されている場合、本プラグインはそれを上書きしません。

= 固定ページも一覧に含めたいのですが =

`post_type="post,page"` を指定してください(既定値がこれです)。投稿タイプを絞りたい場合は `post_type="post"` のように指定します。

== Changelog ==

= 1.2.0 =
* 一覧にカテゴリ列・投稿タイプ列を追加できるようにした(`show_category`/`show_type`、カテゴリ列は`category_limit`で列挙数を制限可能)。
* タイトルの最大表示文字数を指定できるようにした(`title_max_length`、超過分は「…」表示)。
* `NEW!` マークの文字列を変更できるようにした(`newmark_text`)。
* 設定画面に「カスタムCSS」欄を追加。同梱CSSに追加で読み込まれ、NEW!マークや一覧全体の見た目を上書きできる。

= 1.1.1 =
* 設定画面の「対象投稿タイプ」を自由入力からチェックボックスに変更(このサイトで公開状態になっている投稿タイプを自動列挙)。

= 1.1.0 =
* 管理画面(設定 > AEM What's New)を追加。プレビュー付きでショートコードの既定値を編集できるようになった。ショートコード属性を明示指定した場合はそちらが優先される。

= 1.0.0 =
* 初版。外部プラグイン「What's New Generator」の置き換えとして、東北大学金研分析電顕室サイトで運用してきたものをリポジトリ化。

== Upgrade Notice ==

= 1.2.0 =
カテゴリ/投稿タイプ列、タイトル文字数制限、NEW!文字列変更、カスタムCSSを追加。既定では何も表示が変わらない(すべてopt-in)。

= 1.1.1 =
設定画面の「対象投稿タイプ」がチェックボックス選択式になった。既存の保存値は自動的に引き継がれる。

= 1.1.0 =
管理画面(設定 > AEM What's New)を追加。既存のショートコード運用に影響なし。

= 1.0.0 =
初版リリース。
