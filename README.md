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
[mngsk_recent_content category_filter="news,event,equipment"]
[mngsk_recent_content category_filter="manual,tips,notice" category_filter_style="links"]
[mngsk_recent_content category="info" category_filter="news,event" category_filter_style="select"]
[mngsk_recent_content post_type="post,page" orderby="modified"]
[mngsk_recent_content pagination="yes" pagination_style="load_more"]
[mngsk_recent_content instance="news" category_filter="news,event"]
```

主な属性は `title`、`post_type`、`category`、`category_filter`、`category_filter_default`、`category_filter_style`（`links` [既定], `underline`, `pills`, `select`）、`category_filter_all`、`instance`、`exclude_category`、`number`、`orderby`、`layout`、`date_format`、`pagination` です。

管理画面（**設定 > Mngsk Recent Content List**）では、各カテゴリに対して「使用チェック」「日本語ラベル」「English label」「表示順」を個別に設定できます。ショートコードで `category_filter` を明示指定した場合はショートコードの記述順が最優先され、管理画面設定準拠の場合は管理画面の「表示順」昇順で表示されます。

ページネーションのURLパラメータには `mngsk_recent_content_page`（インスタンス指定時は `mngsk_recent_content_{instance}_page`）、カテゴリフィルタのURLパラメータには `mngsk_recent_content_category`（インスタンス指定時は `mngsk_recent_content_{instance}_category`）を使用します。

## CSSによるカスタマイズ

プラグインには任意CSSを入力・保存する機能はありません。テーマの「追加CSS」またはサイトエディターで、次のクラスを使って調整してください。

| クラス | 用途 |
| --- | --- |
| `.mngsk-recent-content` | 一覧全体 |
| `.mngsk-recent-content__title` | 見出し |
| `.mngsk-recent-content__filters` | フィルタナビゲーション / フォーム |
| `.mngsk-recent-content__filters--links` | テキストリンク型フィルタ (既定) |
| `.mngsk-recent-content__filters--underline` | 下線タブ型フィルタ |
| `.mngsk-recent-content__filters--pills` | ピル/ボタン型フィルタ |
| `.mngsk-recent-content__filters--select` | プルダウン型フィルタ |
| `.mngsk-recent-content__filter` | 各カテゴリ切替リンク |
| `.mngsk-recent-content__filter--active` | 選択中のカテゴリ |
| `.mngsk-recent-content__filter--category-{slug}` | 特定カテゴリスラッグ専用クラス（個別装飾用） |
| `.mngsk-recent-content__filter--category-all` | 「すべて」リンク |
| `.mngsk-recent-content__filter-select` | フィルタセレクトボックス |
| `.mngsk-recent-content__filter-submit` | フィルタ絞り込みボタン |
| `.mngsk-recent-content__item` | 各記事リンク |
| `.mngsk-recent-content__date` | 日付 |
| `.mngsk-recent-content__type` / `__category` | タイプ・カテゴリ |
| `.mngsk-recent-content__newmark` | NEW! マーク |
| `.mngsk-recent-content__pagination` | ページネーション |

```css
.mngsk-recent-content__newmark {
  background-color: #005a9c;
}
.mngsk-recent-content__filter--active {
  color: #005a9c;
  font-weight: bold;
}
.mngsk-recent-content__filter--category-news {
  /* 特定カテゴリのカスタム装飾 */
}
```

## 動作要件

- WordPress 6.0以上
- PHP 7.4以上

ライセンスは GPL-2.0-or-later です。

## WordPress.org

本プラグインはWordPress.orgで公開済みです。最新版は次のページから入手できます。

<https://wordpress.org/plugins/mngsk-recent-content-list/>

### 更新の反映手順(SVN)

このgitリポジトリはソース管理用で、公開自体はWordPress.org独自のSVNリポジトリへのコミットで行います。別マシンで作業する場合は以下の手順で環境を再現できます。

1. Subversionクライアントを導入する(Windowsは winget が簡単)。

   ```powershell
   winget install --id Slik.Subversion -e
   ```

   インストール後、PowerShellセッションでPATHを通す。

   ```powershell
   $env:Path = "C:\Program Files\SlikSvn\bin;$env:Path"
   ```

2. SVN作業コピーをチェックアウトする(読み取りは認証不要)。

   ```powershell
   svn checkout https://plugins.svn.wordpress.org/mngsk-recent-content-list/ <checkout先パス> --depth=immediates
   svn update <checkout先パス>/trunk --set-depth=infinity
   ```

3. このリポジトリの以下のファイルを `trunk/` にコピーする(サブフォルダなしのフラット構成)。

   - `mngsk-recent-content-list.php`
   - `mngsk-recent-content-list.css`
   - `mngsk-recent-content-list.js`
   - `readme.txt`
   - `LICENSE`

4. バージョンを上げる場合は `trunk` を対応バージョンの `tags/` にコピーしてから両方をコミットする。

   ```powershell
   svn copy trunk tags/<バージョン>
   ```

5. コミットする(WordPress.orgアカウントのユーザー名・パスワードでの認証が必要)。

   ```powershell
   svn commit -m "<コミットメッセージ>" --username <WordPress.orgユーザー名>
   ```

   パスワードはコマンド実行時にプロンプトで入力する。認証情報はこのリポジトリやコマンド履歴に残さないこと。

反映後、通常5〜30分でプラグインページ(上記URL)に最新情報が表示される。
