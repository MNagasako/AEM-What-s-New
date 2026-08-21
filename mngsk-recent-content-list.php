<?php
/**
 * Plugin Name: Mngsk Recent Content List
 * Plugin URI:  https://github.com/MNagasako/AEM-What-s-New
 * Description: 新着情報一覧をWordPress標準API(WP_Query + ショートコードAPI)だけで表示する。外部プラグイン「What's New Generator」の置き換え。
 * Version:     1.7.0
 * Author:      M.N.
 * License:     GPL-2.0-or-later
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: mngsk-recent-content-list
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Mngsk_Recent_Content_List' ) ) {

final class Mngsk_Recent_Content_List {

	const VERSION           = '1.7.0';
	const SHORTCODE         = 'mngsk_recent_content';
	const STYLE_HANDLE      = 'mngsk-recent-content';
	const OPTION_NAME       = 'mngsk_recent_content_options';
	const SETTINGS_GROUP    = 'mngsk_recent_content_group';
	const SETTINGS_SLUG     = 'mngsk-recent-content';
	const SCRIPT_HANDLE     = 'mngsk-recent-content';
	const AJAX_ACTION       = 'mngsk_recent_content_paginate';
	const MAX_AJAX_ATTS_BYTES = 4096;
	const MAX_AJAX_ATTRIBUTE_BYTES = 512;
	const MAX_LIST_VALUES = 50;
	/** ページネーション有効時に使うURLクエリ引数名。WPの`paged`は既存のアーカイブ/投稿ページ分割と衝突するため専用の名前にしてある。 */
	const PAGE_QUERY_VAR    = 'mngsk_recent_content_page';
	/** カテゴリフィルタ有効時に使うURLクエリ引数名。 */
	const CATEGORY_QUERY_VAR = 'mngsk_recent_content_category';

	/** 見出しに許可するタグ。これ以外が指定されたら p に落とす。 */
	const ALLOWED_TITLE_TAGS = array( 'p', 'h2', 'h3', 'h4', 'h5', 'h6', 'div' );

	public static function boot() {
		add_action( 'init', array( __CLASS__, 'register_shortcodes' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_style' ) );
		add_action( 'admin_menu', array( __CLASS__, 'register_settings_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_enqueue_style' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( __CLASS__, 'add_settings_link' ) );
		// ページネーション(pagination_mode="async")用。読み取り専用の公開データしか返さないため、
		// ログイン有無に関わらず同じハンドラで応答する(wp_ajax_ / wp_ajax_nopriv_ 両方に登録)。
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( __CLASS__, 'ajax_paginate' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION, array( __CLASS__, 'ajax_paginate' ) );
	}

	/**
	 * ショートコードを登録する。
	 *
	 * ディレクトリ向けの固有接頭辞を持つショートコードを登録する。
	 */
	public static function register_shortcodes() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
	}

	public static function register_style() {
		wp_register_style(
			self::STYLE_HANDLE,
			plugins_url( 'mngsk-recent-content-list.css', __FILE__ ),
			array(),
			self::VERSION
		);
		self::register_pagination_script();

		// 本文にショートコードがあるページでだけ読み込む(<head>に入れてFOUCを避ける)。
		// テンプレート直書き等でrender()が直接呼ばれた場合はrender()側でenqueueする。
		$post = get_post();
		if ( $post instanceof WP_Post
			&& has_shortcode( $post->post_content, self::SHORTCODE ) ) {
			wp_enqueue_style( self::STYLE_HANDLE );
		}
	}

	/**
	 * 非同期ページネーション用スクリプトの登録(まだショートコード有無に関わらず毎回呼んでよい。
	 * 実際に読み込まれるのは有効化された場合のみ)。
	 */
	private static function register_pagination_script() {
		wp_register_script(
			self::SCRIPT_HANDLE,
			plugins_url( 'mngsk-recent-content-list.js', __FILE__ ),
			array(),
			self::VERSION,
			true
		);
		wp_localize_script(
			self::SCRIPT_HANDLE,
			'MngskRecentContent',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'action'    => self::AJAX_ACTION,
				'pageParam' => self::PAGE_QUERY_VAR,
				'catParam'  => self::CATEGORY_QUERY_VAR,
			)
		);
	}

	/**
	 * ショートコードのハード既定値(設定画面が未保存の場合のフォールバック)。
	 *
	 * 旧プラグインのwhats_new_options(移行時点の本番値)と同じ表示になるようにしてある。
	 */
	private static function hard_defaults() {
		return array(
			'title'                   => self::t( 'default_title' ),
			'title_tag'               => 'p',
			'title_max_length'        => '0',               // 0で切り詰めなし
			'post_type'               => 'post,page',
			'show_type'               => 'no',               // 投稿タイプ列を表示するか
			'number'                  => '10',
			'orderby'                 => 'date',            // date | modified
			'layout'                  => 'stacked',          // stacked(積み重ね) | inline(1行)
			'category'                => '',                // 表示対象に含めるカテゴリ(空=全部)
			'show_category'           => 'no',               // カテゴリ列を表示するか
			'category_limit'          => '3',               // 1件あたりのカテゴリ列挙数上限(0で上限なし)
			'exclude_category'        => 'exclude-from-whatsnew,uncategorized',
			'exclude_ids'             => '',                // 投稿ID/固定ページIDで個別除外
			'newmark_days'            => '30',              // 0でNEW!マークを無効化
			'newmark_latest'          => 'yes',             // 最新1件には常にNEW!を付ける
			'newmark_text'            => self::t( 'default_newmark_text' ),
			'date_format'             => '',                // 空=「設定 > 一般」の日付フォーマット
			'empty_text'              => self::t( 'default_empty_text' ),
			'ui_language'             => 'auto',             // auto | ja | en (管理画面表示と上記既定文言に使う)
			'pagination'              => 'no',               // ページネーションを有効にするか
			'pagination_mode'         => 'sync',              // sync(通常のリンク遷移) | async(Ajaxでその場更新)
			'pagination_style'        => 'numbers',           // numbers | prev_next | load_more
			'pagination_position'     => 'bottom_left',   // bottom_left | bottom_right | top_right(見出しの右端)
			'pagination_max_items'    => '0',            // ページネーションで辿れる総件数の上限(0で上限なし)
			'date_from'               => '',                // 対象期間の開始日(Y-m-d、空で無指定)
			'date_to'                 => '',                // 対象期間の終了日(Y-m-d、空で無指定)
			'category_filter'         => '',                // 閲覧者が切り替えられる候補カテゴリ(空でフィルタ無効)
			'category_filter_default' => 'all',             // 初期選択カテゴリ(all または スラッグ/ID/名前)
			'category_filter_style'   => 'buttons',         // buttons(ボタン) | select(プルダウン)
			'category_filter_all'     => 'yes',             // 「すべて」を表示するか(yes | no)
			'instance'                => '',                // 同一ページ内の配置インスタンス識別子(空で共通クエリ変数)
		);
	}

	/**
	 * 管理画面・既定文言の表示言語を決める(auto/ja/en)。
	 *
	 * hard_defaults()から呼ばれるため、循環参照を避けるためdefaults()は経由せず
	 * 保存済みオプションを直接読む。
	 */
	private static function current_lang() {
		$saved   = self::saved_options();
		$setting = ( is_array( $saved ) && isset( $saved['ui_language'] ) ) ? $saved['ui_language'] : 'auto';

		if ( in_array( $setting, array( 'ja', 'en' ), true ) ) {
			return $setting;
		}

		return ( 0 === strpos( (string) get_locale(), 'ja' ) ) ? 'ja' : 'en';
	}

	/**
	 * 表示言語(ja/en)ごとの管理画面文言・既定文言のテーブル。
	 *
	 * ビルド手順を持たないプラグインのため、.po/.mo翻訳ファイルではなく
	 * この配列から直接文字列を引く方式にしてある。
	 */
	private static function strings() {
		return array(
			'ja' => array(
				'settings_link'          => '設定',
				'page_heading'           => 'Mngsk Recent Content List — 設定',
				'intro'                  => 'ここで指定した値は、ショートコード [mngsk_recent_content] の既定値になります。ショートコード側で属性を明示指定した場合は、そちらが優先されます。',
				'preview_heading'        => 'プレビュー',
				'preview_desc'           => '現在保存されている設定で [mngsk_recent_content] を表示した場合の見た目です。',
				'checkbox_enable'        => '有効にする',
				'label_title'            => '見出しテキスト',
				'desc_title'             => '空にすると見出しを表示しない',
				'label_title_tag'        => '見出しタグ',
				'label_title_max_length' => 'タイトル最大文字数',
				'desc_title_max_length'  => 'この文字数を超える部分は「…」に置き換える。0で切り詰めなし',
				'label_post_type'        => '対象投稿タイプ',
				'desc_post_type'         => 'このサイトで公開状態(public)になっている投稿タイプの一覧。何もチェックしない場合は post のみが対象になる',
				'label_show_type'        => '投稿タイプ列を表示する',
				'label_number'           => '表示件数',
				'label_orderby'          => '並び順',
				'choice_orderby_date'     => '投稿日',
				'choice_orderby_modified' => '更新日',
				'label_layout'           => '一覧のレイアウト',
				'desc_layout'            => '「1行」を選ぶと日付・タイプ・カテゴリ・タイトルが横一列に並ぶ',
				'choice_layout_stacked'  => '積み重ね(既定、日付の下にタイトル等を表示)',
				'choice_layout_inline'   => '1行(日付・タイプ・カテゴリ・タイトルを横一列に表示)',
				'label_category'         => '対象カテゴリ',
				'desc_category'          => 'スラッグ/カテゴリ名/IDをカンマ区切りで指定。空で全カテゴリ',
				'label_show_category'    => 'カテゴリ列を表示する',
				'label_category_limit'   => 'カテゴリ列の表示数上限',
				'desc_category_limit'    => '1記事あたりに列挙するカテゴリ数の上限(カテゴリ列を表示する場合のみ有効)。0で上限なし',
				'label_exclude_category' => '除外カテゴリ',
				'desc_exclude_category'  => 'スラッグ/カテゴリ名/IDをカンマ区切りで指定',
				'label_exclude_ids'      => '個別除外ID',
				'desc_exclude_ids'       => '投稿ID/固定ページIDをカンマ区切りで指定',
				'label_newmark_days'     => 'NEW!表示日数',
				'desc_newmark_days'      => '0でNEW!マークを無効化',
				'label_newmark_latest'   => '最新1件に常にNEW!を付ける',
				'label_newmark_text'     => 'NEW!マークの文字列',
				'desc_newmark_text'      => '空にするとマーク自体を表示しない',
				'label_date_format'      => '日付フォーマット',
				'desc_date_format'       => '空欄で「設定 > 一般」の日付形式を使用(PHPのdate()書式)。和暦表示用の特別な値も指定できる(明治以降の元号に対応): wareki=令和7年7月27日、wareki_year=令和元/令和7、wareki_year_numeric=令和1/令和7、wareki_year_02d=令和01/令和07。これらのキーワードの直後に続けた文字はdate()書式として評価される(例: wareki_year_02d年 → 令和07年、wareki_year年n月j日 → 令和7年7月27日)',
				'label_empty_text'       => '0件時の表示文言',
				'desc_empty_text'        => '空にすると非表示',
				'label_ui_language'      => '管理画面・既定文言の表示言語',
				'desc_ui_language'       => '「自動」はサイトの言語設定(ja/それ以外)に追従する',
				'choice_lang_auto'       => '自動(サイトの言語設定に従う)',
				'choice_lang_ja'         => '日本語',
				'choice_lang_en'         => 'English',
				'label_pagination'       => 'ページネーションを有効にする',
				'desc_pagination'        => '有効にすると「表示件数」が1ページあたりの件数になり、URLに ?mngsk_recent_content_page=2 のように付けてページを切り替えられる(同一ページに複数配置している場合、ページ番号はすべての配置で共有される)',
				'label_pagination_mode'  => 'ページ切り替えの方式',
				'desc_pagination_mode'   => '「非同期」はJavaScriptでその場更新(ページ全体の再読み込みなし)、「同期」は通常のリンク遷移。非同期では次ページの先読みキャッシュも行う(表示済みページ・リンクへのホバー先読み分はAjax再取得なしで即表示)',
				'choice_pagination_mode_sync'  => '同期(通常のリンク遷移)',
				'choice_pagination_mode_async' => '非同期(Ajaxでその場更新)',
				'label_pagination_style' => 'ページネーションのスタイル',
				'desc_pagination_style'  => '「もっと見る」を選ぶと、次ページの内容がそれまでの一覧に積み増しされる(同期方式でも動作するが、非同期方式と組み合わせるのが自然)',
				'choice_style_numbers'   => '番号付き(既定)',
				'choice_style_prev_next' => '前へ/次へのみ',
				'choice_style_load_more' => 'もっと見るボタン',
				'label_pagination_position' => 'ページネーションの表示位置',
				'desc_pagination_position'  => '「見出しの右端」は一覧の下ではなく、見出し(新着情報等)と同じ行の右端に表示する',
				'choice_position_bottom_left'  => '下・左寄せ(既定、一覧の下)',
				'choice_position_bottom_right' => '下・右寄せ(一覧の下)',
				'choice_position_top_right'    => '見出しの右端(一覧の上)',
				'label_pagination_max_items' => 'ページネーションの件数上限',
				'desc_pagination_max_items'  => 'ページ送りで辿れる総件数の上限。0で上限なし(例: 表示件数10・上限50なら最大5ページまで)',
				'label_date_from'        => '対象期間(開始日)',
				'desc_date_from'         => 'この日付以降の投稿のみを対象にする(投稿日/更新日は「並び順」の設定に従う)。空欄で無指定',
				'label_date_to'          => '対象期間(終了日)',
				'desc_date_to'           => 'この日付以前の投稿のみを対象にする。空欄で無指定',
				'label_category_filter'  => 'カテゴリフィルタ(切替候補)',
				'desc_category_filter'   => '閲覧者がボタン等で切り替えられるカテゴリ候補。チェックしたカテゴリが切替対象になる(空ですべてのカテゴリフィルタを無効化)',
				'label_category_filter_default' => '初期選択カテゴリ',
				'desc_category_filter_default'  => '初期表示時に選択されるカテゴリのスラッグ/名前/ID(all で「すべて」)',
				'label_category_filter_style'   => 'フィルタの表示方式',
				'desc_category_filter_style'    => '「ボタン」は横並びのボタンスタイル、「プルダウン」はセレクトボックス',
				'choice_filter_style_buttons'   => 'ボタン(既定)',
				'choice_filter_style_select'    => 'プルダウン(セレクト)',
				'label_category_filter_all'     => '「すべて」の選択肢を表示する',
				'desc_category_filter_all'      => 'チェックを外すと「すべて」ボタンを非表示にし、いずれかの候補カテゴリのみ選択可能にする',
				'filter_all_text'        => 'すべて',
				'filter_select_label'    => 'カテゴリで絞り込み',
				'filter_select_submit'   => '絞り込む',
				'load_more_text'         => 'もっと見る',
				'pagination_prev'        => '前へ',
				'pagination_next'        => '次へ',
				'default_title'          => '新着情報',
				'default_newmark_text'   => 'NEW!',
				'default_empty_text'     => '現在、新着情報はありません。',
			),
			'en' => array(
				'settings_link'          => 'Settings',
				'page_heading'           => 'Mngsk Recent Content List — Settings',
				'intro'                  => 'Values set here become the defaults for the [mngsk_recent_content] shortcode. Explicit shortcode attributes always take priority over these settings.',
				'preview_heading'        => 'Preview',
				'preview_desc'           => 'This is how [mngsk_recent_content] currently renders with the settings saved below.',
				'checkbox_enable'        => 'Enable',
				'label_title'            => 'Heading text',
				'desc_title'             => 'Leave empty to hide the heading',
				'label_title_tag'        => 'Heading tag',
				'label_title_max_length' => 'Title max length',
				'desc_title_max_length'  => 'Titles longer than this are truncated with a trailing "…". 0 disables truncation',
				'label_post_type'        => 'Post types',
				'desc_post_type'         => 'Public post types registered on this site. If none are checked, only "post" is used',
				'label_show_type'        => 'Show a post-type column',
				'label_number'           => 'Number of items',
				'label_orderby'          => 'Order by',
				'choice_orderby_date'     => 'Publish date',
				'choice_orderby_modified' => 'Modified date',
				'label_layout'           => 'List layout',
				'desc_layout'            => '"Single line" puts the date, type, category and title on one row',
				'choice_layout_stacked'  => 'Stacked (default — title etc. below the date)',
				'choice_layout_inline'   => 'Single line (date, type, category and title in one row)',
				'label_category'         => 'Categories',
				'desc_category'          => 'Comma-separated slugs, names, or IDs. Leave empty for all categories',
				'label_show_category'    => 'Show a category column',
				'label_category_limit'   => 'Category column limit',
				'desc_category_limit'    => 'Max number of categories listed per item (only used when the category column is shown). 0 = no limit',
				'label_exclude_category' => 'Excluded categories',
				'desc_exclude_category'  => 'Comma-separated slugs, names, or IDs',
				'label_exclude_ids'      => 'Excluded post IDs',
				'desc_exclude_ids'       => 'Comma-separated post/page IDs to exclude individually',
				'label_newmark_days'     => '"NEW!" mark duration (days)',
				'desc_newmark_days'      => '0 disables the "NEW!" mark',
				'label_newmark_latest'   => 'Always mark the newest item as new',
				'label_newmark_text'     => '"NEW!" mark text',
				'desc_newmark_text'      => 'Leave empty to hide the mark entirely',
				'label_date_format'      => 'Date format',
				'desc_date_format'       => "Leave empty to use the site's Settings > General date format (PHP date() syntax). Special values render the Japanese era calendar instead (all eras from Meiji onward): wareki=令和7年7月27日, wareki_year=令和元/令和7, wareki_year_numeric=令和1/令和7, wareki_year_02d=令和01/令和07. Any text right after one of these keywords is evaluated as a PHP date() format (e.g. wareki_year_02d年 → 令和07年, wareki_year年n月j日 → 令和7年7月27日)",
				'label_empty_text'       => 'Empty-state text',
				'desc_empty_text'        => 'Shown when there are no matching items. Leave empty to hide',
				'label_ui_language'      => 'Admin screen & default text language',
				'desc_ui_language'       => '"Auto" follows the site\'s language setting (Japanese vs. everything else)',
				'choice_lang_auto'       => 'Auto (follow site language)',
				'choice_lang_ja'         => '日本語 (Japanese)',
				'choice_lang_en'         => 'English',
				'label_pagination'       => 'Enable pagination',
				'desc_pagination'        => 'When enabled, "Number of items" becomes the per-page count, and the list can be paged via ?mngsk_recent_content_page=2 in the URL (the page number is shared across all instances if you place the shortcode more than once on the same page)',
				'label_pagination_mode'  => 'Pagination mode',
				'desc_pagination_mode'   => '"Async" updates the list in place via JavaScript (no full page reload); "Sync" is a normal link navigation. Async also prefetches and caches the next page (and any page you hover/focus), so already-fetched pages display instantly without another Ajax round trip',
				'choice_pagination_mode_sync'  => 'Sync (normal link navigation)',
				'choice_pagination_mode_async' => 'Async (in-place update via Ajax)',
				'label_pagination_style' => 'Pagination style',
				'desc_pagination_style'  => '"Load more" appends the next page\'s items to the existing list (works in sync mode too, but pairs most naturally with async)',
				'choice_style_numbers'   => 'Numbered (default)',
				'choice_style_prev_next' => 'Prev/Next only',
				'choice_style_load_more' => '"Load more" button',
				'label_pagination_position' => 'Pagination position',
				'desc_pagination_position'  => '"Top-right (next to heading)" places it on the same row as the heading (e.g. "What\'s New") instead of below the list',
				'choice_position_bottom_left'  => 'Bottom-left (default, below the list)',
				'choice_position_bottom_right' => 'Bottom-right (below the list)',
				'choice_position_top_right'    => 'Top-right (next to the heading)',
				'label_pagination_max_items' => 'Pagination item cap',
				'desc_pagination_max_items'  => 'Maximum total items reachable via pagination. 0 = no limit (e.g. 10 per page with a cap of 50 allows at most 5 pages)',
				'label_date_from'        => 'Date range (from)',
				'desc_date_from'         => 'Only include items on or after this date (publish date or modified date, matching the "Order by" setting). Leave empty for no lower bound',
				'label_date_to'          => 'Date range (to)',
				'desc_date_to'           => 'Only include items on or before this date. Leave empty for no upper bound',
				'label_category_filter'  => 'Category filter (choices)',
				'desc_category_filter'   => 'Categories users can switch between. Checked categories will appear as filter options (leave empty to disable the filter)',
				'label_category_filter_default' => 'Default category',
				'desc_category_filter_default'  => 'Slug, name, or ID of the category selected on load ("all" for all categories)',
				'label_category_filter_style'   => 'Filter style',
				'desc_category_filter_style'    => '"Buttons" shows a row of clickable button-links, "Dropdown" shows a select box',
				'choice_filter_style_buttons'   => 'Buttons (default)',
				'choice_filter_style_select'    => 'Dropdown (Select)',
				'label_category_filter_all'     => 'Show "All" option',
				'desc_category_filter_all'      => 'Uncheck to hide the "All" option and require selecting a specific category',
				'filter_all_text'        => 'All',
				'filter_select_label'    => 'Filter by category',
				'filter_select_submit'   => 'Filter',
				'load_more_text'         => 'Load more',
				'pagination_prev'        => 'Prev',
				'pagination_next'        => 'Next',
				'default_title'          => 'What\'s New',
				'default_newmark_text'   => 'NEW!',
				'default_empty_text'     => 'No new updates at this time.',
			),
		);
	}

	/**
	 * current_lang()に応じた文言を返す。未定義キーはenへフォールバックする。
	 */
	private static function t( $key ) {
		$lang    = self::current_lang();
		$strings = self::strings();

		return $strings[ $lang ][ $key ] ?? ( $strings['en'][ $key ] ?? $key );
	}

	/**
	 * ショートコードの既定値。
	 *
	 * 設定画面で保存された値をハード既定値の上に重ねたもの。
	 * ショートコード側で属性を明示指定した場合は、shortcode_atts()の仕様によりこちらではなく
	 * 明示指定された値が使われる(= ショートコード属性が最優先)。
	 */
	private static function defaults() {
		$saved = self::saved_options();

		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::hard_defaults() );
	}

	/**
	 * 保存済み設定を読み込む。
	 */
	private static function saved_options() {
		$saved = get_option( self::OPTION_NAME, null );
		if ( is_array( $saved ) ) {
			return $saved;
		}

		return array();
	}

	/**
	 * 設定画面で編集可能なフィールドの定義。
	 */
	private static function fields() {
		return array(
			'title'            => array(
				'type'  => 'text',
				'label' => self::t( 'label_title' ),
				'desc'  => self::t( 'desc_title' ),
			),
			'title_tag'        => array(
				'type'    => 'select',
				'label'   => self::t( 'label_title_tag' ),
				'choices' => array_combine( self::ALLOWED_TITLE_TAGS, self::ALLOWED_TITLE_TAGS ),
			),
			'title_max_length' => array(
				'type'  => 'number',
				'label' => self::t( 'label_title_max_length' ),
				'min'   => 0,
				'max'   => 200,
				'desc'  => self::t( 'desc_title_max_length' ),
			),
			'post_type'        => array(
				'type'  => 'post_types',
				'label' => self::t( 'label_post_type' ),
				'desc'  => self::t( 'desc_post_type' ),
			),
			'show_type'        => array(
				'type'  => 'checkbox',
				'label' => self::t( 'label_show_type' ),
			),
			'number'           => array(
				'type'  => 'number',
				'label' => self::t( 'label_number' ),
				'min'   => 1,
				'max'   => 50,
			),
			'pagination'       => array(
				'type'  => 'checkbox',
				'label' => self::t( 'label_pagination' ),
				'desc'  => self::t( 'desc_pagination' ),
			),
			'pagination_mode'  => array(
				'type'    => 'select',
				'label'   => self::t( 'label_pagination_mode' ),
				'desc'    => self::t( 'desc_pagination_mode' ),
				'choices' => array(
					'sync'  => self::t( 'choice_pagination_mode_sync' ),
					'async' => self::t( 'choice_pagination_mode_async' ),
				),
			),
			'pagination_style' => array(
				'type'    => 'select',
				'label'   => self::t( 'label_pagination_style' ),
				'desc'    => self::t( 'desc_pagination_style' ),
				'choices' => array(
					'numbers'   => self::t( 'choice_style_numbers' ),
					'prev_next' => self::t( 'choice_style_prev_next' ),
					'load_more' => self::t( 'choice_style_load_more' ),
				),
			),
			'pagination_position' => array(
				'type'    => 'select',
				'label'   => self::t( 'label_pagination_position' ),
				'desc'    => self::t( 'desc_pagination_position' ),
				'choices' => array(
					'bottom_left'  => self::t( 'choice_position_bottom_left' ),
					'bottom_right' => self::t( 'choice_position_bottom_right' ),
					'top_right'    => self::t( 'choice_position_top_right' ),
				),
			),
			'pagination_max_items' => array(
				'type'  => 'number',
				'label' => self::t( 'label_pagination_max_items' ),
				'min'   => 0,
				'max'   => 500,
				'desc'  => self::t( 'desc_pagination_max_items' ),
			),
			'date_from'        => array(
				'type'  => 'date',
				'label' => self::t( 'label_date_from' ),
				'desc'  => self::t( 'desc_date_from' ),
			),
			'date_to'          => array(
				'type'  => 'date',
				'label' => self::t( 'label_date_to' ),
				'desc'  => self::t( 'desc_date_to' ),
			),
			'category_filter'  => array(
				'type'  => 'categories',
				'label' => self::t( 'label_category_filter' ),
				'desc'  => self::t( 'desc_category_filter' ),
			),
			'category_filter_default' => array(
				'type'  => 'text',
				'label' => self::t( 'label_category_filter_default' ),
				'desc'  => self::t( 'desc_category_filter_default' ),
			),
			'category_filter_style' => array(
				'type'    => 'select',
				'label'   => self::t( 'label_category_filter_style' ),
				'desc'    => self::t( 'desc_category_filter_style' ),
				'choices' => array(
					'buttons' => self::t( 'choice_filter_style_buttons' ),
					'select'  => self::t( 'choice_filter_style_select' ),
				),
			),
			'category_filter_all' => array(
				'type'  => 'checkbox',
				'label' => self::t( 'label_category_filter_all' ),
				'desc'  => self::t( 'desc_category_filter_all' ),
			),
			'orderby'          => array(
				'type'    => 'select',
				'label'   => self::t( 'label_orderby' ),
				'choices' => array(
					'date'     => self::t( 'choice_orderby_date' ),
					'modified' => self::t( 'choice_orderby_modified' ),
				),
			),
			'layout'           => array(
				'type'    => 'select',
				'label'   => self::t( 'label_layout' ),
				'desc'    => self::t( 'desc_layout' ),
				'choices' => array(
					'stacked' => self::t( 'choice_layout_stacked' ),
					'inline'  => self::t( 'choice_layout_inline' ),
				),
			),
			'category'         => array(
				'type'  => 'text',
				'label' => self::t( 'label_category' ),
				'desc'  => self::t( 'desc_category' ),
			),
			'show_category'    => array(
				'type'  => 'checkbox',
				'label' => self::t( 'label_show_category' ),
			),
			'category_limit'   => array(
				'type'  => 'number',
				'label' => self::t( 'label_category_limit' ),
				'min'   => 0,
				'max'   => 20,
				'desc'  => self::t( 'desc_category_limit' ),
			),
			'exclude_category' => array(
				'type'  => 'text',
				'label' => self::t( 'label_exclude_category' ),
				'desc'  => self::t( 'desc_exclude_category' ),
			),
			'exclude_ids'      => array(
				'type'  => 'text',
				'label' => self::t( 'label_exclude_ids' ),
				'desc'  => self::t( 'desc_exclude_ids' ),
			),
			'newmark_days'     => array(
				'type'  => 'number',
				'label' => self::t( 'label_newmark_days' ),
				'min'   => 0,
				'max'   => 3650,
				'desc'  => self::t( 'desc_newmark_days' ),
			),
			'newmark_latest'   => array(
				'type'  => 'checkbox',
				'label' => self::t( 'label_newmark_latest' ),
			),
			'newmark_text'     => array(
				'type'  => 'text',
				'label' => self::t( 'label_newmark_text' ),
				'desc'  => self::t( 'desc_newmark_text' ),
			),
			'date_format'      => array(
				'type'  => 'text',
				'label' => self::t( 'label_date_format' ),
				'desc'  => self::t( 'desc_date_format' ),
			),
			'empty_text'       => array(
				'type'  => 'text',
				'label' => self::t( 'label_empty_text' ),
				'desc'  => self::t( 'desc_empty_text' ),
			),
			'ui_language'      => array(
				'type'    => 'select',
				'label'   => self::t( 'label_ui_language' ),
				'desc'    => self::t( 'desc_ui_language' ),
				'choices' => array(
					'auto' => self::t( 'choice_lang_auto' ),
					'ja'   => self::t( 'choice_lang_ja' ),
					'en'   => self::t( 'choice_lang_en' ),
				),
			),
		);
	}

	/**
	 * 設定画面を「設定」メニュー配下に登録する。
	 */
	public static function register_settings_page() {
		add_options_page(
			'Mngsk Recent Content List',
			'Mngsk Recent Content List',
			'manage_options',
			self::SETTINGS_SLUG,
			array( __CLASS__, 'render_settings_page' )
		);
	}

	/**
	 * Settings APIに設定項目を登録する。
	 */
	public static function register_settings() {
		register_setting(
			self::SETTINGS_GROUP,
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_options' ),
				'default'           => self::hard_defaults(),
			)
		);
	}

	/**
	 * 設定画面でだけ、プレビュー表示用のCSSを読み込む。
	 */
	public static function admin_enqueue_style( $hook_suffix ) {
		if ( 'settings_page_' . self::SETTINGS_SLUG !== $hook_suffix ) {
			return;
		}

		wp_register_style(
			self::STYLE_HANDLE,
			plugins_url( 'mngsk-recent-content-list.css', __FILE__ ),
			array(),
			self::VERSION
		);
		wp_enqueue_style( self::STYLE_HANDLE );
	}

	/**
	 * プラグイン一覧に「設定」リンクを追加する。
	 */
	public static function add_settings_link( $links ) {
		$url = admin_url( 'options-general.php?page=' . self::SETTINGS_SLUG );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html( self::t( 'settings_link' ) ) . '</a>' );

		return $links;
	}

	/**
	 * 非同期ページネーション(pagination_mode="async")用のAjaxハンドラ。
	 *
	 * 公開済み投稿(post_status=publish)のみを返す読み取り専用のエンドポイントで、状態変更を
	 * 一切行わない。閲覧できる内容は通常のショートコード表示(専用URLパラメータ付きURL)で
	 * 誰でも同じものを見られるため、CSRF対策のnonceは要求していない
	 * (nonceを必須にすると、ページキャッシュ済みHTML内のnonceがキャッシュ有効期間中に
	 * 期限切れになりAjaxが失敗し続ける、という別の不具合を生みやすいため)。
	 * 送信された$attsはrender()内部のshortcode_atts()・各種min/max制限を必ず経由するため、
	 * クライアントから任意の値を渡されても安全側に丸められる。
	 *
	 * mngsk-recent-content-list.js側の先読み(プリフェッチ)・ホバー先読みも同じこのエンドポイントを叩く
	 * (表示に使うか、クライアント側キャッシュに保持するだけかはJS側の判断で、サーバー側の
	 * 処理はクリック時と変わらない)。
	 */
	public static function ajax_paginate() {
		$atts = self::ajax_atts_from_request();
		$page = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$incremental_load_more = isset( $_POST['incremental_load_more'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['incremental_load_more'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- 読み取り専用

		$requested_category = null;
		if ( isset( $_POST['category'] ) && is_string( $_POST['category'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- 読み取り専用
			$requested_category = sanitize_text_field( wp_unslash( $_POST['category'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( strlen( $requested_category ) > self::MAX_AJAX_ATTRIBUTE_BYTES ) {
				$requested_category = null;
			}
		}

		$locale = '';
		if ( isset( $_POST['locale'] ) && is_string( $_POST['locale'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- 読み取り専用
			$raw_locale = sanitize_text_field( wp_unslash( $_POST['locale'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( preg_match( '/^[a-zA-Z0-9_-]{2,30}$/', $raw_locale ) ) {
				$locale = $raw_locale;
			}
		}

		$current_url = '';
		if ( isset( $_POST['current_url'] ) && is_string( $_POST['current_url'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- 読み取り専用
			$current_url = esc_url_raw( wp_unslash( $_POST['current_url'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}

		$switched = false;
		if ( '' !== $locale && function_exists( 'switch_to_locale' ) ) {
			$switched = switch_to_locale( $locale );
		}

		$html = self::render_list( $atts, max( 1, $page ), $incremental_load_more, $locale, $current_url, $requested_category );

		if ( $switched && function_exists( 'restore_previous_locale' ) ) {
			restore_previous_locale();
		}

		wp_send_json_success( array( 'html' => $html ) );
	}

	/**
	 * 公開Ajaxへ渡された属性を、既知の短いスカラー値だけに限定する。
	 * nonceは公開ページに埋め込めないため使わないが、任意の巨大なカテゴリ指定で
	 * カテゴリ解決クエリを増幅させないよう、入力サイズと型はここで厳密に制限する。
	 *
	 * @return array<string, string>
	 */
	private static function ajax_atts_from_request() {
		if ( ! isset( $_POST['atts'] ) || ! is_string( $_POST['atts'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- 読み取り専用
			wp_send_json_error( array( 'message' => 'Invalid attributes.' ), 400 );
		}

		$raw = sanitize_textarea_field( wp_unslash( $_POST['atts'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- 読み取り専用
		if ( strlen( $raw ) > self::MAX_AJAX_ATTS_BYTES ) {
			wp_send_json_error( array( 'message' => 'Attributes are too large.' ), 400 );
		}

		$decoded = json_decode( $raw, true, 10 );
		if ( ! is_array( $decoded ) ) {
			wp_send_json_error( array( 'message' => 'Invalid attributes.' ), 400 );
		}

		$allowed = array_flip( array_keys( self::hard_defaults() ) );
		$atts    = array();
		foreach ( $decoded as $key => $value ) {
			if ( ! is_string( $key ) || ! isset( $allowed[ $key ] ) ) {
				continue;
			}
			if ( ! is_scalar( $value ) ) {
				wp_send_json_error( array( 'message' => 'Invalid attribute value.' ), 400 );
			}

			$value = (string) $value;
			if ( strlen( $value ) > self::MAX_AJAX_ATTRIBUTE_BYTES ) {
				wp_send_json_error( array( 'message' => 'Attribute value is too large.' ), 400 );
			}
			$atts[ $key ] = $value;
		}

		return $atts;
	}

	/**
	 * 保存前のサニタイズ。値の正規化(カテゴリ解決等)はrender()側でまとめて行うため、
	 * ここでは型・範囲のチェックのみ行う。
	 */
	public static function sanitize_options( $input ) {
		$input = is_array( $input ) ? $input : array();
		$out   = array();

		$out['title']            = isset( $input['title'] ) ? sanitize_text_field( $input['title'] ) : '';
		$out['title_tag']        = in_array( $input['title_tag'] ?? '', self::ALLOWED_TITLE_TAGS, true ) ? $input['title_tag'] : 'p';
		$out['title_max_length'] = (string) max( 0, absint( $input['title_max_length'] ?? 0 ) );
		$checked_post_types      = is_array( $input['post_type'] ?? null ) ? $input['post_type'] : array();
		$valid_post_types        = array_values( array_intersect( $checked_post_types, self::public_post_type_names() ) );
		$out['post_type']        = ! empty( $valid_post_types ) ? implode( ',', $valid_post_types ) : 'post';
		$out['show_type']        = ! empty( $input['show_type'] ) ? 'yes' : 'no';
		$out['number']           = (string) min( 50, max( 1, absint( $input['number'] ?? 10 ) ) );
		$out['pagination']       = ! empty( $input['pagination'] ) ? 'yes' : 'no';
		$out['pagination_mode']  = ( isset( $input['pagination_mode'] ) && 'async' === $input['pagination_mode'] ) ? 'async' : 'sync';
		$out['pagination_style'] = in_array( $input['pagination_style'] ?? '', array( 'prev_next', 'load_more' ), true ) ? $input['pagination_style'] : 'numbers';
		$out['pagination_position'] = in_array( $input['pagination_position'] ?? '', array( 'bottom_right', 'top_right' ), true ) ? $input['pagination_position'] : 'bottom_left';
		$out['pagination_max_items'] = (string) max( 0, absint( $input['pagination_max_items'] ?? 0 ) );
		$out['date_from']        = isset( $input['date_from'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $input['date_from'] ) ? $input['date_from'] : '';
		$out['date_to']          = isset( $input['date_to'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $input['date_to'] ) ? $input['date_to'] : '';

		if ( isset( $input['category_filter'] ) && is_array( $input['category_filter'] ) ) {
			$filter_slugs = array();
			foreach ( $input['category_filter'] as $val ) {
				$filter_slugs[] = sanitize_text_field( (string) $val );
			}
			$out['category_filter'] = implode( ',', array_filter( $filter_slugs ) );
		} else {
			$out['category_filter'] = isset( $input['category_filter'] ) ? sanitize_text_field( $input['category_filter'] ) : '';
		}

		$out['category_filter_default'] = isset( $input['category_filter_default'] ) ? sanitize_text_field( $input['category_filter_default'] ) : 'all';
		$out['category_filter_style']   = ( isset( $input['category_filter_style'] ) && 'select' === $input['category_filter_style'] ) ? 'select' : 'buttons';
		$out['category_filter_all']     = ! empty( $input['category_filter_all'] ) ? 'yes' : 'no';

		$out['orderby']          = ( isset( $input['orderby'] ) && 'modified' === $input['orderby'] ) ? 'modified' : 'date';
		$out['layout']           = ( isset( $input['layout'] ) && 'inline' === $input['layout'] ) ? 'inline' : 'stacked';
		$out['category']         = isset( $input['category'] ) ? sanitize_text_field( $input['category'] ) : '';
		$out['show_category']    = ! empty( $input['show_category'] ) ? 'yes' : 'no';
		$out['category_limit']   = (string) max( 0, absint( $input['category_limit'] ?? 3 ) );
		$out['exclude_category'] = isset( $input['exclude_category'] ) ? sanitize_text_field( $input['exclude_category'] ) : '';
		$out['exclude_ids']      = isset( $input['exclude_ids'] ) ? sanitize_text_field( $input['exclude_ids'] ) : '';
		$out['newmark_days']     = (string) max( 0, absint( $input['newmark_days'] ?? 30 ) );
		$out['newmark_latest']   = ! empty( $input['newmark_latest'] ) ? 'yes' : 'no';
		$out['newmark_text']     = isset( $input['newmark_text'] ) ? sanitize_text_field( $input['newmark_text'] ) : self::t( 'default_newmark_text' );
		$out['date_format']      = isset( $input['date_format'] ) ? sanitize_text_field( $input['date_format'] ) : '';
		$out['empty_text']       = isset( $input['empty_text'] ) ? sanitize_text_field( $input['empty_text'] ) : '';
		$out['ui_language']      = in_array( $input['ui_language'] ?? '', array( 'ja', 'en' ), true ) ? $input['ui_language'] : 'auto';

		return $out;
	}

	/**
	 * 設定画面を出力する。オリジナル「What's New Generator」にあったプレビュー機能も再現し、
	 * 保存済み設定での表示結果をその場で確認できるようにしてある。
	 */
	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$options = self::defaults();
		?>
<div class="wrap">
	<h1><?php echo esc_html( self::t( 'page_heading' ) ); ?></h1>
	<p><?php echo esc_html( self::t( 'intro' ) ); ?></p>
	<form method="post" action="options.php">
		<?php settings_fields( self::SETTINGS_GROUP ); ?>
		<table class="form-table" role="presentation">
			<?php foreach ( self::fields() as $key => $field ) : ?>
			<tr>
				<th scope="row">
					<label for="mngsk-recent-content-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
				</th>
				<td>
					<?php self::render_field( $key, $field, $options[ $key ] ?? '' ); ?>
					<?php if ( ! empty( $field['desc'] ) ) : ?>
					<p class="description"><?php echo esc_html( $field['desc'] ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<?php endforeach; ?>
		</table>
		<?php submit_button(); ?>
	</form>

	<h2><?php echo esc_html( self::t( 'preview_heading' ) ); ?></h2>
	<p class="description"><?php echo esc_html( self::t( 'preview_desc' ) ); ?></p>
	<div style="max-width:480px;border:1px solid #ccd0d4;padding:12px;background:#fff;">
		<?php echo self::render( array() ); // phpcs:ignore WordPress.Security.EscapeOutput -- render()内でエスケープ済み ?>
	</div>
</div>
		<?php
	}

	/**
	 * 設定画面の1フィールド分の入力欄を出力する。
	 */
	private static function render_field( $key, array $field, $value ) {
		$id   = 'mngsk-recent-content-' . $key;
		$name = self::OPTION_NAME . '[' . $key . ']';

		switch ( $field['type'] ) {
			case 'select':
				echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
				foreach ( $field['choices'] as $choice_value => $choice_label ) {
					printf(
						'<option value="%s"%s>%s</option>',
						esc_attr( $choice_value ),
						selected( $value, $choice_value, false ),
						esc_html( $choice_label )
					);
				}
				echo '</select>';
				break;

			case 'checkbox':
				printf(
					'<label><input type="checkbox" id="%1$s" name="%2$s" value="1"%3$s /> %4$s</label>',
					esc_attr( $id ),
					esc_attr( $name ),
					checked( 'yes', $value, false ),
					esc_html( self::t( 'checkbox_enable' ) )
				);
				break;

			case 'post_types':
				$checked_list = self::split_list( $value );
				foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $post_type ) {
					printf(
						'<label style="margin-right:1em;"><input type="checkbox" name="%1$s[]" value="%2$s"%3$s /> %4$s (%2$s)</label>',
						esc_attr( $name ),
						esc_attr( $post_type->name ),
						checked( in_array( $post_type->name, $checked_list, true ), true, false ),
						esc_html( $post_type->labels->singular_name )
					);
				}
				break;

			case 'categories':
				$checked_cats = self::split_list( $value );
				$all_cats     = get_categories( array( 'hide_empty' => false ) );
				if ( ! empty( $all_cats ) ) {
					echo '<div style="max-height:160px;overflow-y:auto;border:1px solid #ccd0d4;padding:6px 10px;background:#fff;max-width:400px;">';
					foreach ( $all_cats as $cat ) {
						$is_checked = in_array( $cat->slug, $checked_cats, true ) || in_array( (string) $cat->term_id, $checked_cats, true ) || in_array( $cat->name, $checked_cats, true );
						printf(
							'<div><label><input type="checkbox" name="%1$s[]" value="%2$s"%3$s /> %4$s <code style="font-size:85%%;">(%2$s)</code></label></div>',
							esc_attr( $name ),
							esc_attr( $cat->slug ),
							checked( $is_checked, true, false ),
							esc_html( $cat->name )
						);
					}
					echo '</div>';
				} else {
					printf(
						'<input type="text" id="%1$s" name="%2$s" value="%3$s" class="regular-text" />',
						esc_attr( $id ),
						esc_attr( $name ),
						esc_attr( $value )
					);
				}
				break;

			case 'number':
				printf(
					'<input type="number" id="%1$s" name="%2$s" value="%3$s" min="%4$s" max="%5$s" class="small-text" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $value ),
					esc_attr( $field['min'] ?? 0 ),
					esc_attr( $field['max'] ?? 9999 )
				);
				break;

			case 'textarea':
				printf(
					'<textarea id="%1$s" name="%2$s" rows="8" class="large-text code">%3$s</textarea>',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_textarea( $value )
				);
				break;

			case 'date':
				printf(
					'<input type="date" id="%1$s" name="%2$s" value="%3$s" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $value )
				);
				break;

			default:
				printf(
					'<input type="text" id="%1$s" name="%2$s" value="%3$s" class="regular-text" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $value )
				);
		}
	}

	/**
	 * ショートコードのコールバック。第2引数はWordPressが渡す囲みショートコードの本文であり、
	 * ページ番号には使わない。内部のAjaxレンダリングはrender_list()を直接呼ぶ。
	 */
	public static function render( $atts = array(), $content = null, $shortcode_tag = '' ) {
		return self::render_list( $atts );
	}

	/**
	 * 現在のリクエストにおける有効なロケールを取得する。
	 * Bogo または WordPress の標準関数から判定する。
	 *
	 * @return string
	 */
	private static function current_locale() {
		if ( function_exists( 'bogo_get_locale' ) ) {
			return (string) bogo_get_locale();
		}
		if ( function_exists( 'determine_locale' ) ) {
			return (string) determine_locale();
		}

		return (string) get_locale();
	}

	/**
	 * 現在のリクエストURLを取得する(ページネーションのbase URL用)。
	 *
	 * @return string
	 */
	private static function current_request_url() {
		if ( ! empty( $_SERVER['HTTP_HOST'] ) && ! empty( $_SERVER['REQUEST_URI'] ) ) {
			$scheme = is_ssl() ? 'https://' : 'http://';
			return esc_url_raw( $scheme . wp_unslash( $_SERVER['HTTP_HOST'] ) . wp_unslash( $_SERVER['REQUEST_URI'] ) );
		}

		return home_url( '/' );
	}

	/**
	 * @param array       $atts                  ショートコード属性。
	 * @param int|null    $forced_page           Ajaxから指定するページ番号。通常表示ではnull。
	 * @param bool        $incremental_load_more Ajaxの「もっと見る」で、当該ページ分だけを返すかどうか。
	 * @param string      $forced_locale         Ajaxから指定するロケール(空で通常判定)。
	 * @param string      $base_url              ページネーションの基準URL(Ajax時は元のページURL)。
	 * @param string|null $forced_category       Ajaxから指定するカテゴリフィルタ値(空・nullで通常判定)。
	 */
	private static function render_list( $atts = array(), $forced_page = null, $incremental_load_more = false, $forced_locale = '', $base_url = '', $forced_category = null ) {
		$atts = shortcode_atts( self::defaults(), $atts, self::SHORTCODE );

		$active_locale   = ( '' !== $forced_locale ) ? $forced_locale : self::current_locale();
		$request_url     = ( '' !== $base_url ) ? $base_url : self::current_request_url();

		$instance        = self::resolve_instance( $atts['instance'] ?? '' );
		$page_query_var  = self::page_query_var( $instance );
		$cat_query_var   = self::category_query_var( $instance );

		$filter_terms    = self::resolve_filter_categories( $atts['category_filter'] );
		$has_filter      = ! empty( $filter_terms );
		$requested_cat   = ( null !== $forced_category ) ? $forced_category : self::current_category( $instance );
		$active_info     = self::resolve_active_category(
			$filter_terms,
			$atts['category_filter_default'],
			$requested_cat,
			self::is_truthy( $atts['category_filter_all'] ?? 'yes' )
		);
		$active_filter_id = $active_info['term_id'];
		$active_slug      = $active_info['slug'];

		$title_tag       = in_array( $atts['title_tag'], self::ALLOWED_TITLE_TAGS, true ) ? $atts['title_tag'] : 'p';
		$title_max_len   = max( 0, (int) $atts['title_max_length'] );
		$orderby         = ( 'modified' === $atts['orderby'] ) ? 'modified' : 'date';
		$date_field      = ( 'modified' === $orderby ) ? 'modified' : 'date';
		// wareki: 令和7年7月27日(年月日) / wareki_year 系: 年のみ(後述のwareki_year_only()参照)。
		// キーワードは前方一致で検出し、続く文字列(例: "年"、"年度")があればそのまま末尾に
		// 付与する(例: date_format="wareki_year_02d年" → "令和07年")。判定順は誤マッチを
		// 避けるため、より長く具体的なキーワードから先にチェックする。
		$wareki_prefixes = array(
			'wareki_year_numeric' => 'numeric',
			'wareki_year_02d'     => '02d',
			'wareki_year'         => 'gan',
			'wareki'              => 'full',
		);
		$wareki_style    = null;
		$wareki_suffix   = '';
		foreach ( $wareki_prefixes as $prefix => $style ) {
			if ( 0 === strpos( $atts['date_format'], $prefix ) ) {
				$wareki_style  = $style;
				$wareki_suffix = substr( $atts['date_format'], strlen( $prefix ) );
				break;
			}
		}
		$use_wareki      = ( null !== $wareki_style );
		$date_format     = ( '' !== $atts['date_format'] && ! $use_wareki ) ? $atts['date_format'] : get_option( 'date_format' );
		$newmark_days    = max( 0, (int) $atts['newmark_days'] );
		$mark_latest     = self::is_truthy( $atts['newmark_latest'] );
		$newmark_text    = (string) $atts['newmark_text'];
		$show_type       = self::is_truthy( $atts['show_type'] );
		$show_category   = self::is_truthy( $atts['show_category'] );
		$category_limit  = max( 0, (int) $atts['category_limit'] );
		$layout_inline   = ( 'inline' === $atts['layout'] );
		$paginate        = self::is_truthy( $atts['pagination'] );
		$pagination_mode = ( 'async' === $atts['pagination_mode'] ) ? 'async' : 'sync';
		$pagination_style = in_array( $atts['pagination_style'], array( 'prev_next', 'load_more' ), true ) ? $atts['pagination_style'] : 'numbers';
		$pagination_position = in_array( $atts['pagination_position'], array( 'bottom_right', 'top_right' ), true ) ? $atts['pagination_position'] : 'bottom_left';
		$max_items       = max( 0, (int) $atts['pagination_max_items'] );
		$per_page        = min( 50, max( 1, (int) $atts['number'] ) );
		$current_page    = $paginate ? ( null !== $forced_page ? max( 1, (int) $forced_page ) : self::current_page( $instance ) ) : 1;

		$max_pages       = 1;
		$effective_total = 0;
		if ( $paginate ) {
			// 実際に取得する前に件数だけ数え、current_pageを総ページ数(上限反映後)の範囲に丸める。
			// これをしないと、load_more(累積取得)で専用URLパラメータを大きくされた場合に
			// posts_per_page(= per_page * current_page)が際限なく膨らんでしまう。
			$found_total     = self::count_matching_posts( $atts, $orderby, $active_locale, $active_filter_id );
			$effective_total = $found_total;
			if ( $max_items > 0 ) {
				$effective_total = min( $effective_total, $max_items );
			}
			$max_pages    = max( 1, (int) ceil( $effective_total / $per_page ) );
			$current_page = min( $current_page, $max_pages );
		}

		// "load_more"はページを重ねるごとに一覧を積み増す仕様のため、都度「先頭からNページ分」を
		// 一括取得する(offsetで切り出す通常のページ送りとは異なり、常にpaged=1で件数だけ増やす)。
		// 一方で通常・前へ次へのページネーション(およびAjaxでのもっと見る追加取得)は、
		// WP_Queryのオフセット計算((paged-1)*posts_per_page)がずれて前ページの投稿と重複するのを防ぐため、
		// posts_per_pageには常に固定の$per_pageを渡す。
		$is_cumulative  = ( $paginate && 'load_more' === $pagination_style && ! $incremental_load_more );
		$query_per_page = $is_cumulative
			? min( $per_page * $current_page, $effective_total )
			: $per_page;
		$query_per_page = max( 1, $query_per_page );
		$query_paged    = $is_cumulative ? 1 : $current_page;

		$query = self::query_posts( $atts, $orderby, $query_paged, $query_per_page, $active_locale, $active_filter_id );
		$posts = $query->posts;

		// ページネーションの件数上限(pagination_max_items)が指定されている場合、
		// 最終ページで上限件数を超える余剰記事を切り詰める。
		if ( $paginate && $max_items > 0 && is_array( $posts ) && ! empty( $posts ) ) {
			$allowed_count = max( 0, $effective_total - ( ( $current_page - 1 ) * $per_page ) );
			if ( count( $posts ) > $allowed_count ) {
				$posts = array_slice( $posts, 0, $allowed_count );
			}
		}

		$pagination_html = $paginate
			? self::render_pagination( $current_page, $max_pages, $pagination_style, $pagination_position, $request_url, $instance, $active_slug )
			: '';

		$filter_html = $has_filter
			? self::render_category_filter( $filter_terms, $active_info, $atts, $request_url )
			: '';

		wp_enqueue_style( self::STYLE_HANDLE );
		$is_async_enabled = ( 'async' === $pagination_mode && ( $paginate || $has_filter ) );
		if ( $is_async_enabled ) {
			wp_enqueue_script( self::SCRIPT_HANDLE );
		}

		$async_attrs = '';
		if ( $is_async_enabled ) {
			$async_attrs  = ' data-mngsk-recent-content-async="1"';
			$async_attrs .= ' data-mngsk-recent-content-atts="' . esc_attr( (string) wp_json_encode( $atts ) ) . '"';
			$async_attrs .= ' data-mngsk-recent-content-locale="' . esc_attr( $active_locale ) . '"';
			$async_attrs .= ' data-mngsk-recent-content-url="' . esc_url( $request_url ) . '"';
			// JS側が現在ページ・総ページ数をリンクのhrefを解析せず把握できるようにしておく。
			// 「次ページの先読み(プリフェッチ)キャッシュ」の起点として使う。
			$async_attrs .= ' data-mngsk-recent-content-page="' . (int) $current_page . '"';
			$async_attrs .= ' data-mngsk-recent-content-max-pages="' . (int) $max_pages . '"';
			$async_attrs .= ' data-mngsk-recent-content-active-category="' . esc_attr( $active_slug ) . '"';
			$async_attrs .= ' data-mngsk-recent-content-cat-param="' . esc_attr( $cat_query_var ) . '"';
			$async_attrs .= ' data-mngsk-recent-content-page-param="' . esc_attr( $page_query_var ) . '"';
			if ( '' !== $instance ) {
				$async_attrs .= ' data-mngsk-recent-content-instance="' . esc_attr( $instance ) . '"';
			}
			if ( 'load_more' === $pagination_style && $paginate ) {
				$async_attrs .= ' data-mngsk-recent-content-load-more="1"';
			}
		}

		ob_start();
		?>
<div class="mngsk-recent-content"<?php echo $async_attrs; // phpcs:ignore WordPress.Security.EscapeOutput -- $async_attrsは組み立て時にesc_attr済み ?>>
		<?php $show_header_pagination = ( $pagination_html && 'top_right' === $pagination_position ); ?>
		<?php if ( '' !== $atts['title'] || $show_header_pagination ) : ?>
	<div class="mngsk-recent-content__header">
			<?php if ( '' !== $atts['title'] ) : ?>
		<<?php echo $title_tag; // phpcs:ignore WordPress.Security.EscapeOutput -- ALLOWED_TITLE_TAGSで検証済み ?> class="mngsk-recent-content__title"><?php echo esc_html( $atts['title'] ); ?></<?php echo $title_tag; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
			<?php endif; ?>
			<?php if ( $show_header_pagination ) : ?>
		<?php echo $pagination_html; // phpcs:ignore WordPress.Security.EscapeOutput -- render_pagination()内で組み立て時にエスケープ済み ?>
			<?php endif; ?>
	</div>
		<?php endif; ?>
		<?php if ( '' !== $filter_html ) : ?>
			<?php echo $filter_html; // phpcs:ignore WordPress.Security.EscapeOutput -- render_category_filter()内で組み立て時にエスケープ済み ?>
		<?php endif; ?>
		<?php if ( empty( $posts ) ) : ?>
			<?php if ( '' !== $atts['empty_text'] ) : ?>
	<p class="mngsk-recent-content__empty"><?php echo esc_html( $atts['empty_text'] ); ?></p>
			<?php endif; ?>
		<?php else : ?>
	<div class="mngsk-recent-content__content">
	<hr />
			<?php foreach ( $posts as $index => $post ) : ?>
				<?php
				$timestamp = get_post_timestamp( $post, $date_field );
				$global_index = $is_cumulative ? $index : ( ( $current_page - 1 ) * $per_page ) + $index;
				$is_new    = self::is_new( $global_index, $timestamp, $newmark_days, $mark_latest );
				if ( $use_wareki ) {
					$date_text = ( 'full' === $wareki_style )
						? self::wareki_date( $timestamp )
						: self::wareki_year_only( $timestamp, $wareki_style );
					// 接尾辞はリテラルではなく、通常のdate()書式として投稿のタイムスタンプで評価する
					// (例: "wareki_year_02d年n月j日" → 令和07年 + "年n月j日"をwp_date()で展開)。
					if ( $timestamp && '' !== $wareki_suffix ) {
						$date_text .= wp_date( $wareki_suffix, $timestamp );
					}
				} else {
					$date_text = ( 'modified' === $date_field )
						? get_the_modified_date( $date_format, $post )
						: get_the_date( $date_format, $post );
				}
				$title     = self::truncate_title( get_the_title( $post ), $title_max_len );
				$datetime  = $timestamp ? gmdate( 'c', $timestamp ) : '';

				$title_markup = '';
				if ( $is_new && '' !== trim( $newmark_text ) ) {
					$title_markup .= '<span class="mngsk-recent-content__newmark">' . esc_html( $newmark_text ) . '</span> ';
				}
				$title_markup .= esc_html( $title );
				?>
	<a class="mngsk-recent-content__item" href="<?php echo esc_url( (string) get_permalink( $post ) ); ?>">
			<?php if ( $layout_inline ) : ?>
		<div class="mngsk-recent-content__row">
			<span class="mngsk-recent-content__date"><time datetime="<?php echo esc_attr( $datetime ); ?>"><?php echo esc_html( $date_text ); ?></time></span>
				<?php if ( $show_type ) : ?>
			<span class="mngsk-recent-content__type"><?php echo esc_html( self::post_type_label( $post ) ); ?></span>
				<?php endif; ?>
				<?php if ( $show_category ) : ?>
			<span class="mngsk-recent-content__category"><?php echo esc_html( self::category_names_for_post( $post, $category_limit ) ); ?></span>
				<?php endif; ?>
			<span class="mngsk-recent-content__item-title"><?php echo $title_markup; // phpcs:ignore WordPress.Security.EscapeOutput -- $title_markupは組み立て時にesc_html済み ?></span>
		</div>
			<?php else : ?>
		<dl>
			<dt><time datetime="<?php echo esc_attr( $datetime ); ?>"><?php echo esc_html( $date_text ); ?></time></dt>
			<dd class="mngsk-recent-content__item-title"><?php echo $title_markup; // phpcs:ignore WordPress.Security.EscapeOutput -- $title_markupは組み立て時にesc_html済み ?></dd>
				<?php if ( $show_type ) : ?>
			<dd class="mngsk-recent-content__type"><?php echo esc_html( self::post_type_label( $post ) ); ?></dd>
				<?php endif; ?>
				<?php if ( $show_category ) : ?>
			<dd class="mngsk-recent-content__category"><?php echo esc_html( self::category_names_for_post( $post, $category_limit ) ); ?></dd>
				<?php endif; ?>
		</dl>
			<?php endif; ?>
	</a>
	<hr />
			<?php endforeach; ?>
	</div>
		<?php endif; ?>
		<?php if ( $pagination_html && ! $show_header_pagination ) : ?>
	<?php echo $pagination_html; // phpcs:ignore WordPress.Security.EscapeOutput -- render_pagination()内で組み立て時にエスケープ済み ?>
		<?php endif; ?>
</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * インスタンス名を安全な文字列(英数字、ハイフン、アンダースコア、最大32文字)に正規化する。
	 *
	 * @param mixed $instance
	 * @return string
	 */
	private static function resolve_instance( $instance ) {
		if ( ! is_scalar( $instance ) ) {
			return '';
		}
		$clean = sanitize_key( (string) $instance );

		return substr( $clean, 0, 32 );
	}

	/**
	 * ページ番号用のURLクエリ引数名を取得する。
	 *
	 * @param string $instance
	 * @return string
	 */
	private static function page_query_var( $instance = '' ) {
		$inst = self::resolve_instance( $instance );
		if ( '' !== $inst ) {
			return 'mngsk_recent_content_' . $inst . '_page';
		}

		return self::PAGE_QUERY_VAR;
	}

	/**
	 * カテゴリフィルタ用のURLクエリ引数名を取得する。
	 *
	 * @param string $instance
	 * @return string
	 */
	private static function category_query_var( $instance = '' ) {
		$inst = self::resolve_instance( $instance );
		if ( '' !== $inst ) {
			return 'mngsk_recent_content_' . $inst . '_category';
		}

		return self::CATEGORY_QUERY_VAR;
	}

	/**
	 * category_filter属性の指定から有効なWP_Termオブジェクトのリストを取得する。
	 *
	 * @param mixed $value
	 * @return WP_Term[]
	 */
	private static function resolve_filter_categories( $value ) {
		$ids = self::resolve_category_ids( $value );
		if ( empty( $ids ) ) {
			return array();
		}

		$terms = array();
		foreach ( $ids as $id ) {
			$term = get_term( $id, 'category' );
			if ( $term instanceof WP_Term && ! is_wp_error( $term ) ) {
				$terms[] = $term;
			}
		}

		return $terms;
	}

	/**
	 * 現在のアクティブカテゴリ(スラッグまたは'all')を解決する。
	 *
	 * @param WP_Term[]    $filter_terms 許可された候補カテゴリ
	 * @param string       $default      初期選択 (all または スラッグ/ID/名前)
	 * @param string|null  $requested    GETまたはAjaxからリクエストされた値
	 * @param bool         $show_all     「すべて」が有効かどうか
	 * @return array{slug: string, term_id: int, term: WP_Term|null}
	 */
	private static function resolve_active_category( array $filter_terms, $default, $requested = null, $show_all = true ) {
		if ( empty( $filter_terms ) ) {
			return array(
				'slug'    => 'all',
				'term_id' => 0,
				'term'    => null,
			);
		}

		$term_by_slug = array();
		$term_by_id   = array();
		foreach ( $filter_terms as $term ) {
			$term_by_slug[ $term->slug ] = $term;
			$term_by_id[ $term->term_id ] = $term;
		}

		$target = ( null !== $requested && '' !== $requested ) ? (string) $requested : (string) $default;
		$target = trim( $target );

		// 1. 'all' の判定
		if ( 'all' === strtolower( $target ) || '' === $target ) {
			if ( $show_all ) {
				return array(
					'slug'    => 'all',
					'term_id' => 0,
					'term'    => null,
				);
			}
			$first = reset( $filter_terms );
			return array(
				'slug'    => $first->slug,
				'term_id' => (int) $first->term_id,
				'term'    => $first,
			);
		}

		// 2. スラッグで直接一致
		if ( isset( $term_by_slug[ $target ] ) ) {
			$t = $term_by_slug[ $target ];
			return array(
				'slug'    => $t->slug,
				'term_id' => (int) $t->term_id,
				'term'    => $t,
			);
		}

		// 3. sanitize_titleしたスラッグで一致 (日本語スラッグ等)
		$sanitized = sanitize_title( $target );
		if ( isset( $term_by_slug[ $sanitized ] ) ) {
			$t = $term_by_slug[ $sanitized ];
			return array(
				'slug'    => $t->slug,
				'term_id' => (int) $t->term_id,
				'term'    => $t,
			);
		}

		// 4. 数値IDで一致
		if ( ctype_digit( $target ) && isset( $term_by_id[ (int) $target ] ) ) {
			$t = $term_by_id[ (int) $target ];
			return array(
				'slug'    => $t->slug,
				'term_id' => (int) $t->term_id,
				'term'    => $t,
			);
		}

		// 5. カテゴリ名で一致
		foreach ( $filter_terms as $term ) {
			if ( $term->name === $target ) {
				return array(
					'slug'    => $term->slug,
					'term_id' => (int) $term->term_id,
					'term'    => $term,
				);
			}
		}

		// リクエスト値が候補に見つからなかった場合、初期設定値で再試行
		if ( null !== $requested && $default !== $requested ) {
			return self::resolve_active_category( $filter_terms, $default, null, $show_all );
		}

		// それでも見つからない場合は 'all' または先頭候補へフォールバック
		if ( $show_all ) {
			return array(
				'slug'    => 'all',
				'term_id' => 0,
				'term'    => null,
			);
		}
		$first = reset( $filter_terms );
		return array(
			'slug'    => $first->slug,
			'term_id' => (int) $first->term_id,
			'term'    => $first,
		);
	}

	/**
	 * カテゴリフィルタUIをレンダリングする。
	 *
	 * @param WP_Term[] $filter_terms
	 * @param array     $active_info
	 * @param array     $atts
	 * @param string    $base_url
	 * @return string
	 */
	private static function render_category_filter( array $filter_terms, array $active_info, array $atts, $base_url = '' ) {
		if ( empty( $filter_terms ) ) {
			return '';
		}

		$style          = ( 'select' === ( $atts['category_filter_style'] ?? '' ) ) ? 'select' : 'buttons';
		$show_all       = self::is_truthy( $atts['category_filter_all'] ?? 'yes' );
		$instance       = self::resolve_instance( $atts['instance'] ?? '' );
		$cat_query_var  = self::category_query_var( $instance );
		$page_query_var = self::page_query_var( $instance );

		$active_slug = $active_info['slug'];

		$items = array();
		if ( $show_all ) {
			$items[] = array(
				'slug' => 'all',
				'name' => self::t( 'filter_all_text' ),
			);
		}
		foreach ( $filter_terms as $term ) {
			$items[] = array(
				'slug' => $term->slug,
				'name' => $term->name,
			);
		}

		ob_start();
		if ( 'select' === $style ) {
			$form_id = 'mngsk-filter-select-' . ( '' !== $instance ? $instance : 'default' );
			?>
<div class="mngsk-recent-content__filters mngsk-recent-content__filters--select" role="group" aria-label="<?php echo esc_attr( self::t( 'label_category_filter' ) ); ?>">
	<form method="get" action="<?php echo esc_url( $base_url ); ?>" class="mngsk-recent-content__filter-form">
			<?php
			$parsed_url = wp_parse_url( $base_url );
			if ( ! empty( $parsed_url['query'] ) ) {
				parse_str( $parsed_url['query'], $existing_params );
				unset( $existing_params[ $cat_query_var ], $existing_params[ $page_query_var ] );
				foreach ( $existing_params as $param_k => $param_v ) {
					if ( is_scalar( $param_v ) ) {
						printf( '<input type="hidden" name="%s" value="%s" />', esc_attr( (string) $param_k ), esc_attr( (string) $param_v ) );
					}
				}
			}
			?>
		<label for="<?php echo esc_attr( $form_id ); ?>" class="screen-reader-text"><?php echo esc_html( self::t( 'filter_select_label' ) ); ?></label>
		<select id="<?php echo esc_attr( $form_id ); ?>" name="<?php echo esc_attr( $cat_query_var ); ?>" class="mngsk-recent-content__filter-select">
			<?php foreach ( $items as $item ) : ?>
				<?php
				$is_selected = ( $item['slug'] === $active_slug );
				$opt_url     = remove_query_arg( $page_query_var, $base_url );
				if ( 'all' === $item['slug'] ) {
					$opt_url = remove_query_arg( $cat_query_var, $opt_url );
				} else {
					$opt_url = add_query_arg( $cat_query_var, $item['slug'], $opt_url );
				}
				?>
				<option value="<?php echo esc_attr( $item['slug'] ); ?>" data-url="<?php echo esc_url( $opt_url ); ?>" <?php selected( $is_selected, true ); ?>>
					<?php echo esc_html( $item['name'] ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<noscript>
			<button type="submit" class="mngsk-recent-content__filter-submit"><?php echo esc_html( self::t( 'filter_select_submit' ) ); ?></button>
		</noscript>
	</form>
</div>
			<?php
		} else {
			?>
<nav class="mngsk-recent-content__filters mngsk-recent-content__filters--buttons" aria-label="<?php echo esc_attr( self::t( 'label_category_filter' ) ); ?>">
			<?php foreach ( $items as $item ) : ?>
				<?php
				$is_active  = ( $item['slug'] === $active_slug );
				$filter_url = remove_query_arg( $page_query_var, $base_url );
				if ( 'all' === $item['slug'] ) {
					$filter_url = remove_query_arg( $cat_query_var, $filter_url );
				} else {
					$filter_url = add_query_arg( $cat_query_var, $item['slug'], $filter_url );
				}
				$link_classes = 'mngsk-recent-content__filter';
				if ( $is_active ) {
					$link_classes .= ' mngsk-recent-content__filter--active';
				}
				?>
	<a class="<?php echo esc_attr( $link_classes ); ?>" href="<?php echo esc_url( $filter_url ); ?>" data-category="<?php echo esc_attr( $item['slug'] ); ?>"<?php echo $is_active ? ' aria-current="true"' : ''; ?>>
		<?php echo esc_html( $item['name'] ); ?>
	</a>
			<?php endforeach; ?>
</nav>
			<?php
		}

		return (string) ob_get_clean();
	}

	/**
	 * カテゴリ指定(カンマ区切り)をterm_idの配列に解決する。
	 *
	 * スラッグ / 日本語のカテゴリ名 / 数値ID のいずれでも指定できる。
	 * 日本語スラッグはDB上パーセントエンコードで保存されているため、
	 * 生の文字列で引けなければsanitize_title()を通して再試行する。
	 *
	 * @return int[]
	 */
	private static function resolve_category_ids( $value ) {
		$ids = array();

		foreach ( self::split_list( $value ) as $token ) {
			$term = false;

			if ( ctype_digit( $token ) ) {
				$term = get_term( (int) $token, 'category' );
			} else {
				$term = get_term_by( 'slug', $token, 'category' );
				if ( ! $term ) {
					$term = get_term_by( 'slug', sanitize_title( $token ), 'category' );
				}
				if ( ! $term ) {
					$term = get_term_by( 'name', $token, 'category' );
				}
			}

			if ( $term && ! is_wp_error( $term ) ) {
				$ids[] = (int) $term->term_id;
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * post_type指定を、実在する公開post typeだけに絞って返す。
	 *
	 * @return string[]
	 */
	private static function parse_post_types( $value ) {
		$requested = array_values( array_intersect( self::split_list( $value ), self::public_post_type_names() ) );

		return ! empty( $requested ) ? $requested : array( 'post' );
	}

	/**
	 * このサイトで公開状態(public)になっている投稿タイプのスラッグ一覧。
	 *
	 * @return string[]
	 */
	private static function public_post_type_names() {
		return get_post_types( array( 'public' => true ), 'names' );
	}

	/**
	 * @return int[]
	 */
	private static function parse_ids( $value ) {
		$ids = array();
		foreach ( self::split_list( $value ) as $token ) {
			$id = absint( $token );
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * @return string[]
	 */
	private static function split_list( $value ) {
		if ( is_array( $value ) ) {
			$parts = $value;
		} else {
			$parts = explode( ',', (string) $value );
		}

		$values = array();
		foreach ( $parts as $part ) {
			if ( ! is_scalar( $part ) ) {
				continue;
			}
			$part = trim( (string) $part );
			if ( '' !== $part && strlen( $part ) <= self::MAX_AJAX_ATTRIBUTE_BYTES ) {
				$values[] = $part;
			}
		}

		return array_slice( $values, 0, self::MAX_LIST_VALUES );
	}

	private static function is_truthy( $value ) {
		return in_array( strtolower( (string) $value ), array( 'yes', 'true', 'on', '1' ), true );
	}

	/**
	 * NEW!マークを付けるかどうか。
	 *
	 * $mark_latestが有効なら、先頭1件は日付によらず常にNEW!(旧プラグインのwng_latest_new相当)。
	 */
	private static function is_new( $index, $timestamp, $days, $mark_latest ) {
		if ( 0 === $index && $mark_latest ) {
			return true;
		}
		if ( $days <= 0 || ! $timestamp ) {
			return false;
		}

		return ( time() - $timestamp ) < ( $days * DAY_IN_SECONDS );
	}

	/**
	 * タイトルを指定文字数(マルチバイト対応)に切り詰める。0以下なら切り詰めない。
	 */
	private static function truncate_title( $title, $max_length ) {
		if ( $max_length <= 0 || mb_strlen( $title ) <= $max_length ) {
			return $title;
		}

		return mb_substr( $title, 0, $max_length ) . '…';
	}

	/**
	 * 明治以降の元号境界(開始日のUNIXタイムスタンプ、新しい順)。
	 *
	 * @return array<int, array{name: string, start: int}>
	 */
	private static function wareki_eras() {
		$timezone = wp_timezone();

		return array(
			array(
				'name'  => '令和',
				'start' => ( new DateTimeImmutable( '2019-05-01 00:00:00', $timezone ) )->getTimestamp(),
			),
			array(
				'name'  => '平成',
				'start' => ( new DateTimeImmutable( '1989-01-08 00:00:00', $timezone ) )->getTimestamp(),
			),
			array(
				'name'  => '昭和',
				'start' => ( new DateTimeImmutable( '1926-12-25 00:00:00', $timezone ) )->getTimestamp(),
			),
			array(
				'name'  => '大正',
				'start' => ( new DateTimeImmutable( '1912-07-30 00:00:00', $timezone ) )->getTimestamp(),
			),
			array(
				'name'  => '明治',
				'start' => ( new DateTimeImmutable( '1868-01-25 00:00:00', $timezone ) )->getTimestamp(),
			),
		);
	}

	/**
	 * タイムスタンプが属する元号名・元号年(1始まり)を返す。明治より前は該当なし(null)。
	 * サイトのタイムゾーン設定に合わせるため、gmdate()ではなくwp_date()で年を取り出す。
	 *
	 * @return array{name: string, year: int}|null
	 */
	private static function wareki_era_year( $timestamp ) {
		$year = (int) wp_date( 'Y', $timestamp );

		foreach ( self::wareki_eras() as $era ) {
			if ( $timestamp >= $era['start'] ) {
				return array(
					'name' => $era['name'],
					'year' => $year - (int) wp_date( 'Y', $era['start'] ) + 1,
				);
			}
		}

		return null;
	}

	/**
	 * date_format="wareki"指定時に使う、和暦の年月日表示(例: 令和7年7月27日、元年は「令和元年」)。
	 */
	private static function wareki_date( $timestamp ) {
		if ( ! $timestamp ) {
			return '';
		}

		$month = (int) wp_date( 'n', $timestamp );
		$day   = (int) wp_date( 'j', $timestamp );
		$era   = self::wareki_era_year( $timestamp );

		if ( null === $era ) {
			// 明治より前(1868-01-25より前)は元号を特定せず西暦のみで表示する。
			return wp_date( 'Y', $timestamp ) . '年' . $month . '月' . $day . '日';
		}

		$year_label = ( 1 === $era['year'] ) ? '元' : (string) $era['year'];

		return $era['name'] . $year_label . '年' . $month . '月' . $day . '日';
	}

	/**
	 * date_format="wareki_year"系で使う、和暦の元号+年のみの表示(「年」は付けない)。
	 *
	 * $style:
	 * - 'gan'     : 元年は「元」、それ以外は算用数字(例: 令和元, 令和7)
	 * - 'numeric' : 元年も含め常に算用数字(例: 令和1, 令和7)
	 * - '02d'     : 常に算用数字を2桁ゼロ埋め(例: 令和01, 令和07)
	 */
	private static function wareki_year_only( $timestamp, $style ) {
		if ( ! $timestamp ) {
			return '';
		}

		$era = self::wareki_era_year( $timestamp );
		if ( null === $era ) {
			return (string) wp_date( 'Y', $timestamp );
		}

		switch ( $style ) {
			case '02d':
				$year_label = sprintf( '%02d', $era['year'] );
				break;
			case 'numeric':
				$year_label = (string) $era['year'];
				break;
			default: // 'gan'
				$year_label = ( 1 === $era['year'] ) ? '元' : (string) $era['year'];
		}

		return $era['name'] . $year_label;
	}

	/**
	 * 投稿タイプのラベル(単数形)を返す。
	 */
	private static function post_type_label( WP_Post $post ) {
		$post_type = get_post_type_object( $post->post_type );

		return $post_type ? $post_type->labels->singular_name : $post->post_type;
	}

	/**
	 * 投稿に紐づくカテゴリ名を、上限件数までカンマ区切りで返す。
	 */
	private static function category_names_for_post( WP_Post $post, $limit ) {
		$categories = get_the_category( $post->ID );
		if ( empty( $categories ) ) {
			return '';
		}

		$names = wp_list_pluck( $categories, 'name' );
		if ( $limit > 0 ) {
			$names = array_slice( $names, 0, $limit );
		}

		return implode( ', ', $names );
	}

}

Mngsk_Recent_Content_List::boot();

}
