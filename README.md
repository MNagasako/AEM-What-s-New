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
