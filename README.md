# Mngsk Recent Content List

WordPressの投稿・固定ページから新着一覧を表示する軽量プラグインです。公開名とすべての公開識別子には、固有の `mngsk` 接頭辞を使用します。

## 基本

投稿または固定ページに次を挿入します。

```
[mngsk_recent_content]
```

設定は **設定 > Mngsk Recent Content List** から変更できます。ショートコード属性を明示した場合は、そちらが優先されます。

```text
[mngsk_recent_content number="5" category="announcements"]
[mngsk_recent_content post_type="post,page" orderby="modified"]
[mngsk_recent_content pagination="yes" pagination_style="load_more"]
```

主な属性は `title`、`post_type`、`category`、`exclude_category`、`number`、`orderby`、`layout`、`date_format`、`pagination` です。ページネーションのURLパラメータには `mngsk_recent_content_page` を使用します。

## CSSによるカスタマイズ

プラグインには任意CSSを入力・保存する機能はありません。テーマの「追加CSS」またはサイトエディターで、次のクラスを使って調整してください。

| クラス | 用途 |
| --- | --- |
| `.mngsk-recent-content` | 一覧全体 |
| `.mngsk-recent-content__title` | 見出し |
| `.mngsk-recent-content__item` | 各記事リンク |
| `.mngsk-recent-content__date` | 日付 |
| `.mngsk-recent-content__type` / `__category` | タイプ・カテゴリ |
| `.mngsk-recent-content__newmark` | NEW! マーク |
| `.mngsk-recent-content__pagination` | ページネーション |

```css
.mngsk-recent-content__newmark {
  background-color: #005a9c;
}
```

## 動作要件

- WordPress 6.0以上
- PHP 7.4以上

ライセンスは GPL-2.0-or-later です。

## WordPress.org

本プラグインはWordPress.orgで公開済みです。最新版は次のページから入手できます。

<https://wordpress.org/plugins/mngsk-recent-content-list/>
