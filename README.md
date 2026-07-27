# What's New List by M.N.

トップページ等に「新着情報」一覧を表示するための、軽量なWordPressプラグインです。
WordPress標準API(`WP_Query` + ショートコードAPI)だけで実装されており、外部ライブラリやビルド手順は不要です。

外部プラグイン「What's New Generator」の内製・置き換え版として、東北大学金属材料研究所分析電顕室サイト(www.aem.imr.tohoku.ac.jp) 向けに作成したものを、他サイトでも再利用できる形でこのリポジトリに切り出しています。

## 謝辞・経緯

長らく本サイトの新着情報表示を支えてくれた、Hideki Tanaka氏(WordPress.orgユーザー名:
`hidakabizplugin`)作のWordPressプラグイン
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

1. WordPress.org からインストールする場合は、管理画面の「プラグインを追加」から配布ZIPを選択する。
   手動配置の場合は、`whats-new-list-by-m-n` フォルダーを `wp-content/plugins/` に配置する
   (メインファイル名 `aem-whatsnew.php` はそのまま使う)
2. 管理画面の「プラグイン」から **What's New List by M.N.** を有効化する
3. 表示したいページ/投稿に `[aem_whatsnew]` を挿入する

表示内容の既定値は「設定 > What's New List by M.N.」の管理画面で変更できます(プレビュー付き)。
ショートコード側で属性を明示指定した場合は、そちらが優先されます。

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
[aem_whatsnew orderby="modified" date_format="wareki"]
```
更新日順で並べ、日付表示を和暦(例: `令和7年7月27日`)にする。

```
[aem_whatsnew pagination="yes" number="10" pagination_style="load_more"]
```
10件ずつの「もっと見る」ボタン式ページネーションにする。

### 属性一覧

属性が多いため、機能ごとに分けて掲載する。既定値の空欄は「未指定・無効」を表す。

#### 見出し

| 属性 | 既定値 | 説明 |
| --- | --- | --- |
| `title` | `新着情報` | 見出しテキスト。空文字 `""` で非表示 |
| `title_tag` | `p` | 見出しのタグ。`p`/`h2`〜`h6`/`div` 以外を指定すると `p` にフォールバック |

#### 対象の絞り込み

| 属性 | 既定値 | 説明 |
| --- | --- | --- |
| `post_type` | `post,page` | 対象の投稿タイプ(カンマ区切り)。公開(`public`)なタイプのみ有効、それ以外は無視される |
| `category` | (空=全カテゴリ) | 対象カテゴリ。スラッグ/カテゴリ名/ID、カンマ区切りで複数指定可 |
| `exclude_category` | `exclude-from-whatsnew,uncategorized` | 除外カテゴリ。指定すると既定値を上書きする |
| `exclude_ids` | (空) | 個別に除外したい投稿ID/固定ページID(カンマ区切り) |
| `date_from` | (空) | この日付(`Y-m-d`)以降の投稿のみを対象にする。日付の基準は`orderby`に従う |
| `date_to` | (空) | この日付(`Y-m-d`)以前の投稿のみを対象にする |
| `number` | `10` | 表示件数(1〜50)。`pagination="yes"`の場合は1ページあたりの件数になる |
| `orderby` | `date` | `date`(投稿日)または `modified`(更新日) |

#### 表示項目・レイアウト

| 属性 | 既定値 | 説明 |
| --- | --- | --- |
| `title_max_length` | `0` | タイトルの最大表示文字数(マルチバイト対応)。超えた分は`…`に置き換える。`0`で切り詰めなし |
| `show_type` | `no` | 投稿タイプ列(`post`/`page`等のラベル)を表示するか |
| `show_category` | `no` | カテゴリ列(投稿に紐づくカテゴリ名)を表示するか |
| `category_limit` | `3` | カテゴリ列に列挙するカテゴリ数の上限(1記事あたり)。`0`で上限なし。`show_category="yes"`の場合のみ有効 |
| `layout` | `stacked` | `stacked`(既定、日付の下にタイトル・タイプ・カテゴリを積み重ねて表示)または`inline`(日付・タイプ・カテゴリ・タイトルを横一列で表示) |
| `date_format` | (空="設定 > 一般"の日付フォーマット) | 日付の表示形式(PHPの`date()`書式)。特別な値 `wareki` で和暦表示(後述) |
| `empty_text` | `現在、新着情報はありません。` | 該当記事が0件のときの表示文言。空文字で非表示 |

#### NEW!マーク

| 属性 | 既定値 | 説明 |
| --- | --- | --- |
| `newmark_days` | `30` | この日数以内に投稿されたものに `NEW!` を付ける。`0` で無効化 |
| `newmark_latest` | `yes` | 最新1件には日数に関わらず常に `NEW!` を付けるか |
| `newmark_text` | `NEW!` | 新着マークの文字列。空文字にするとマーク自体を表示しない |

#### ページネーション

| 属性 | 既定値 | 説明 |
| --- | --- | --- |
| `pagination` | `no` | `yes`にするとページネーションを有効化する |
| `pagination_mode` | `sync` | `sync`(通常のリンク遷移)または`async`(JavaScriptでその場更新) |
| `pagination_style` | `numbers` | `numbers`(番号付き)/`prev_next`(前へ・次へのみ)/`load_more`(もっと見るボタン。次ページ分を一覧に積み増す) |
| `pagination_position` | `bottom_left` | `bottom_left`(既定、一覧の下・左寄せ)/`bottom_right`(一覧の下・右寄せ)/`top_right`(一覧の上、見出しと同じ行の右端) |
| `pagination_max_items` | `0` | ページ送りで辿れる総件数の上限。`0`で上限なし(例: `number="10"`・上限`50`なら最大5ページ) |

詳細は後述の「ページネーション」の節を参照。

`custom_css`(カスタムCSS)と`ui_language`(表示言語)はサイト全体に効く設定のため、
ショートコード属性ではなく「設定 > What's New List by M.N.」の管理画面からのみ編集する
(後述の各節を参照)。

### 日付フォーマットと和暦

`date_format`にPHPの`date()`書式(例: `Y/m/d`)を指定するのが基本だが、以下の特別な値を
指定すると和暦表示になる。明治以降の改元(明治・大正・昭和・平成・令和)に対応している。

| `date_format`の値 | 出力例(元年) | 出力例(それ以外) | 備考 |
| --- | --- | --- | --- |
| `wareki` | `令和元年5月1日` | `令和7年7月27日` | 年月日をすべて表示 |
| `wareki_year` | `令和元` | `令和7` | 年のみ(「年」は付けない)。元年は「元」 |
| `wareki_year_numeric` | `令和1` | `令和7` | 年のみ、元年も算用数字 |
| `wareki_year_02d` | `令和01` | `令和07` | 年のみ、常に2桁ゼロ埋め |

```
[aem_whatsnew date_format="wareki"]
```
```
[aem_whatsnew date_format="wareki_year"]
```

これらのキーワードの直後に文字を続けると、その部分は通常のPHP `date()`書式として
投稿のタイムスタンプで評価される(リテラルとしてそのまま出るのではなく、`n`(月)や
`j`(日)などのトークンは実際の値に置き換わる)。

```
[aem_whatsnew date_format="wareki_year_02d年n月j日"]
```
→ `令和08年7月27日` のように表示される。

### ページネーション

`pagination="yes"`を指定すると、URLに`?whatsnew_page=2`のようなクエリ引数でページを
切り替えられるようになる。WPの`paged`ではなく専用のクエリ引数`whatsnew_page`を使っている
(アーカイブページや投稿本文中の`<!--nextpage-->`分割など、既存のページ送りと衝突しない
ようにするため)。同じページに`[aem_whatsnew]`を複数回配置している場合、ページ番号は
すべての配置で共有される(配置ごとに独立したページ送りは意図的にサポートしていない)。

**方式(`pagination_mode`)**

- `sync`(既定): 通常の`<a href>`によるページ遷移。JavaScript不要
- `async`: クリック時にAjax(`admin-ajax.php`)でその場更新する。読み取り専用・公開データの
  みを返すエンドポイントのため、CSRF対策のnonceは要求していない(ページキャッシュ環境で
  nonceが期限切れになりAjaxが失敗し続ける、という不具合を避けるため)。`aem-whatsnew.js`
  (ビルド不要の素のJS)が自動で読み込まれる
  - **先読み(プリフェッチ)キャッシュ**: ページ表示直後、ブラウザがアイドルなタイミングで
    「次のページ」を裏で取得しキャッシュしておく。ページネーションのリンクにマウスオーバー/
    フォーカスした時点でも同様に先読みする。一度取得したページへ戻る/進む場合はAjaxを
    発行せずキャッシュから即時表示する(同一ページ内、コンテナ単位のメモリキャッシュ)。
    `navigator.connection`(データセーバー/低速回線)が検出できる場合は先読みを控える

**スタイル(`pagination_style`)**

- `numbers`(既定): `paginate_links()`による番号付きページネーション
- `prev_next`: 「前へ」「次へ」のリンクのみ
- `load_more`: 「もっと見る」ボタン。クリックすると次ページの内容がそれまでの一覧に
  積み増される(ページ内容自体を「先頭からNページ分」まとめて再取得する方式のため、
  `sync`方式でも正しく積み増し表示になる)。件数の多いサイトで使う場合は
  `pagination_max_items`を併用して上限を設けることを推奨

**表示位置(`pagination_position`)**

- `bottom_left`(既定): 一覧の下、左寄せ
- `bottom_right`: 一覧の下、右寄せ
- `top_right`: 一覧の上、見出し(`title`)と同じ行の右端

**件数上限(`pagination_max_items`)**

ページ送りで辿れる総件数そのものを制限する。例えば`number="10"`・
`pagination_max_items="50"`なら、実際の投稿数に関わらず最大5ページまでしか辿れない。
`0`(既定)で無制限。

**その他の注意点**

- 管理画面のプレビューでは、ブラウザ側から`?whatsnew_page=`を付けて再読み込みする手段が
  ないため、常に1ページ目の見た目のみが確認できる(リンクの見た目自体は確認可能)
- ページ内容の取得(WP_Query)は常に`post_status=publish`のみを対象にしており、
  Ajaxハンドラも同じ経路(`render()`)を通るため、閲覧権限の扱いは通常表示と変わらない

### 表示言語(管理画面・既定文言)

設定画面の最後にある「管理画面・既定文言の表示言語」(`ui_language`)で、管理画面のラベルや
`title`/`newmark_text`/`empty_text`の既定文言を日本語/英語で切り替えられる。

- `auto`(既定): サイトの言語設定(`get_locale()`)が`ja`から始まるかどうかで自動判定
- `ja` / `en`: サイトの言語設定に関わらず固定する

.po/.moファイルによる翻訳ではなく、プラグイン内の文字列テーブルを直接切り替える方式にしてある
(このプラグインはビルド手順を持たない設計のため)。WordPress公式ディレクトリへの登録を想定し、
readme.txtは英語を主とし、末尾に日本語セクションを設けている。

### カスタムCSS

同梱の`aem-whatsnew.css`に加えて、「設定 > What's New List by M.N.」の「カスタムCSS」欄に書いた
CSSが追加で読み込まれる(`wp_add_inline_style()`)。対象になる主なクラス:

| クラス | 用途 |
| --- | --- |
| `.whatsnew` | 全体のラッパー |
| `.whatsnew-title` | 見出し |
| `.whatsnew-header` | `pagination_position="top_right"`時の、見出しとページネーションの行 |
| `.whatsnew-item` | 各記事の`<a>`リンク全体 |
| `.whatsnew-row` | `layout="inline"`時の、日付・タイプ・カテゴリ・タイトルの横並び行 |
| `.newmark` | `NEW!`マーク |
| `.whatsnew-type` / `.whatsnew-category` | タイプ列・カテゴリ列 |
| `.whatsnew-pagination` / `.whatsnew-load-more` | ページネーション全体・「もっと見る」ボタン |

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
- `pagination_mode="async"` を使う場合、閲覧側ブラウザに`fetch`/`URL`/`URLSearchParams`が
  必要(モダンブラウザであれば標準搭載。`aem-whatsnew.js`はビルド不要の素のJS)

## ライセンス

GPL-2.0-or-later. 詳細は [LICENSE](LICENSE) を参照。
