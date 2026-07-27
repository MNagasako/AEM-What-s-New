# AEM What's New

トップページ等に「新着情報」一覧を表示するための、軽量なWordPressプラグインです。
WordPress標準API(`WP_Query` + ショートコードAPI)だけで実装されており、外部ライブラリやビルド手順は不要です。

外部プラグイン「What's New Generator」の内製・置き換え版として、東北大学金属材料研究所
分析電顕室サイト(www.aem.imr.tohoku.ac.jp)向けに作られたものを、他サイトでも
再利用できる形でこのリポジトリに切り出しています。

## 謝辞・経緯

長らく本サイトの新着情報表示を支えてくれた、田中秀樹氏作のWordPressプラグイン
「[What's New Generator](https://wordpress.org/plugins/whats-new-genarator/)」
(スラッグ: `whats-new-genarator`、ショートコード `[showwhatsnew]`)に、
まずこの場を借りて感謝します。

同プラグインはWordPress.org公式ディレクトリで配布されていましたが、**2024年6月15日付で
「セキュリティ上の問題(Security Issue)」を理由に公開停止**となり、ダウンロードできなく
なりました。最終バージョンは2.0.2で、以降の更新もありません。配布停止に伴いソース
コードや詳しい仕様の情報を改めて入手することができなかったため、移行にあたっては
同プラグインのコードを流用・参照するのではなく、本番サイトで実際に運用していた
`[showwhatsnew]` の出力(HTML)や設定値・挙動を観察した上で、**WordPress標準APIのみを
使ってゼロから独自に実装し直したもの**が本プラグインです。オリジナルの実装とは
無関係な、機能面での再現・置き換えである点にご留意ください。

## 特徴

- ショートコード `[aem_whatsnew]` を好きな場所(固定ページ・投稿・ウィジェットのテキストエリア等)に置くだけ
- 対象カテゴリ・除外カテゴリは **スラッグ / カテゴリ名 / カテゴリID のいずれでも指定可能**。
  サイトごとにカテゴリIDが異なっていても、コードを書き換えずに使い回せる
- 新着(`NEW!`)マークの表示日数、最新1件への強制付与、投稿種別(`post`/`page`等)、
  並び順(投稿日/更新日)などを属性で細かく調整可能
- `User Access Manager` 等の閲覧制限プラグインと併用しても安全(`WP_Query`経由のため、
  閲覧権限のない記事は一覧に出ない。旧プラグインは`get_posts()`のフィルタ抑制設定により
  この制限が効いていなかった)
- 旧プラグイン「What's New Generator」からの移行を考慮し、同名ショートコード `[showwhatsnew]`
  も(旧プラグインが有効でない場合に限り)自動的に登録される

## インストール

1. このリポジトリを `wp-content/plugins/aem-whatsnew/` としてWordPressに配置する
   (ディレクトリ名・メインファイル名 `aem-whatsnew.php` は変更しないこと。過去に別名の
   「What's New Generator」を使っていた場合、ショートコード名の競合を避けるための
   判定に使われるため)
2. 管理画面の「プラグイン」から **AEM What's New** を有効化する
3. 表示したいページ/投稿に `[aem_whatsnew]` を挿入する

設定画面はありません。表示内容はすべてショートコードの属性で指定します。

## 使い方(USAGE)

### 基本形

```
[aem_whatsnew]
```

これだけで、`アップロード除外カテゴリ(既定は「新着除外」「未分類」)` を除く全カテゴリの
最新10件を、投稿日の新しい順に表示します。

### よく使う指定例

```
[aem_whatsnew number="5"]
```
表示件数を5件にする。

```
[aem_whatsnew category="お知らせ"]
```
「お知らせ」カテゴリ(カテゴリ名でもスラッグでもIDでも可)の記事だけを対象にする。
複数指定はカンマ区切り: `category="お知らせ,メンテナンス関係"`

```
[aem_whatsnew exclude_category="exclude-from-whatsnew,uncategorized,tips"]
```
既定の除外カテゴリに加えて「TIPS」カテゴリも除外する。
**`exclude_category` を指定すると既定値を上書きする**ので、既定の除外(新着除外・未分類)を
維持したい場合は上記のように既定値も含めて書くこと。

```
[aem_whatsnew post_type="post,page" title="お知らせ一覧" title_tag="h3"]
```
固定ページも対象に含め、見出しを `<h3>お知らせ一覧</h3>` にする。
`title=""` にすると見出し自体を出さない。

```
[aem_whatsnew newmark_days="0"]
```
`NEW!` マークを常に出さないようにする(`newmark_latest` が既定`yes`のままだと
最新1件だけには付く点に注意。両方消したい場合は `newmark_latest="no"` も合わせて指定)。

```
[aem_whatsnew orderby="modified" date_format="Y/m/d"]
```
更新日順で並べ、日付表示を `2026/07/27` 形式にする。

### 属性一覧

| 属性 | 既定値 | 説明 |
| --- | --- | --- |
| `title` | `新着情報` | 見出しテキスト。空文字 `""` で非表示 |
| `title_tag` | `p` | 見出しのタグ。`p`/`h2`〜`h6`/`div` 以外を指定すると `p` にフォールバック |
| `post_type` | `post,page` | 対象の投稿タイプ(カンマ区切り)。公開(`public`)なタイプのみ有効、それ以外は無視される |
| `number` | `10` | 表示件数(1〜50) |
| `orderby` | `date` | `date`(投稿日)または `modified`(更新日) |
| `category` | (空=全カテゴリ) | 対象カテゴリ。スラッグ/カテゴリ名/ID、カンマ区切りで複数指定可 |
| `exclude_category` | `exclude-from-whatsnew,uncategorized` | 除外カテゴリ。指定すると既定値を上書きする |
| `exclude_ids` | (空) | 個別に除外したい投稿ID/固定ページID(カンマ区切り) |
| `newmark_days` | `30` | この日数以内に投稿されたものに `NEW!` を付ける。`0` で無効化 |
| `newmark_latest` | `yes` | 最新1件には日数に関わらず常に `NEW!` を付けるか |
| `date_format` | (空="設定 > 一般"の日付フォーマット) | 日付の表示形式(PHPの`date()`書式) |
| `empty_text` | `現在、新着情報はありません。` | 該当記事が0件のときの表示文言。空文字で非表示 |

### カテゴリ指定の仕組み(複数サイトでの再利用について)

`category` / `exclude_category` はカテゴリの **term_id を直接書く必要はありません**。
次の順で解決を試みるため、スラッグ・カテゴリ名・IDのどれで書いても動作します。

1. 数字のみ → カテゴリID (`term_id`) として解決
2. スラッグとして完全一致を検索(日本語カテゴリ名はDB上パーセントエンコードされたスラッグに
   なるため、`sanitize_title()` を通した再試行も行う)
3. カテゴリ名として完全一致を検索

このため、サイトごとにカテゴリIDが異なっていても、`category="お知らせ"` のように
**カテゴリ名やスラッグで書いておけば設定ファイルの書き換えなしに他サイトへそのまま流用できる**。

### フィルターフック

より細かい制御が必要な場合、`aem_whatsnew_query_args` フィルターで `WP_Query` の引数を
直接差し替えられる。

```php
add_filter( 'aem_whatsnew_query_args', function ( array $args, array $atts ) {
	// 例: 抜粋(excerpt)をメタキャッシュに含めたい場合など
	$args['update_post_meta_cache'] = true;
	return $args;
}, 10, 2 );
```

## 旧プラグイン「What's New Generator」からの移行

- 旧プラグインの本番設定値(`whats_new_options`)と同じ見た目・挙動になるよう、
  既定値(`category`空/`exclude_category`=`exclude-from-whatsnew,uncategorized`/
  `number`=10/`newmark_days`=30 等)を合わせてある
- 旧ショートコード `[showwhatsnew]` は、旧プラグインがまだ有効な間は上書きしない
  (`shortcode_exists()`で判定)。そのため **両プラグインを一時的に同時有効化しても競合しない**
- 移行手順の目安:
  1. 本プラグインを有効化(旧プラグインはそのまま)→ `[aem_whatsnew]` で表示確認
  2. 問題なければ本文中の `[showwhatsnew]` を `[aem_whatsnew]` に置き換え(必須ではないが、
     旧プラグインへの依存を切るために推奨)
  3. 旧プラグイン「What's New Generator」を無効化・削除

## 動作要件

- WordPress 6.0以上
- PHP 7.4以上

## ライセンス

GPL-2.0-or-later. 詳細は [LICENSE](LICENSE) を参照。
