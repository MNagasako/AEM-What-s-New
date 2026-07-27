<?php
/**
 * Plugin Name: AEM What's New
 * Plugin URI:  https://github.com/MNagasako/AEM-What-s-New
 * Description: 新着情報一覧をWordPress標準API(WP_Query + ショートコードAPI)だけで表示する。外部プラグイン「What's New Generator」の置き換え。
 * Version:     1.2.0
 * Author:      分析電顕室
 * License:     GPL-2.0-or-later
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: aem-whatsnew
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AEM_WhatsNew {

	const VERSION           = '1.2.0';
	const SHORTCODE         = 'aem_whatsnew';
	const LEGACY_SHORTCODE  = 'showwhatsnew';
	const STYLE_HANDLE      = 'aem-whatsnew';
	const OPTION_NAME       = 'aem_whatsnew_options';
	const SETTINGS_GROUP    = 'aem_whatsnew_group';
	const SETTINGS_SLUG     = 'aem-whatsnew';

	/** 見出しに許可するタグ。これ以外が指定されたら p に落とす。 */
	const ALLOWED_TITLE_TAGS = array( 'p', 'h2', 'h3', 'h4', 'h5', 'h6', 'div' );

	public static function boot() {
		add_action( 'init', array( __CLASS__, 'register_shortcodes' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_style' ) );
		add_action( 'admin_menu', array( __CLASS__, 'register_settings_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_enqueue_style' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( __CLASS__, 'add_settings_link' ) );
	}

	/**
	 * ショートコードを登録する。
	 *
	 * 旧What's New Generatorはプラグイン読み込み時に[showwhatsnew]を登録するため、
	 * initの時点で既に存在していれば旧プラグインがまだ有効ということ。その場合は
	 * 上書きせず、新ショートコードだけを提供する(移行期間中の同時有効化対策)。
	 */
	public static function register_shortcodes() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );

		if ( ! shortcode_exists( self::LEGACY_SHORTCODE ) ) {
			add_shortcode( self::LEGACY_SHORTCODE, array( __CLASS__, 'render' ) );
		}
	}

	public static function register_style() {
		wp_register_style(
			self::STYLE_HANDLE,
			plugins_url( 'aem-whatsnew.css', __FILE__ ),
			array(),
			self::VERSION
		);
		// wp_add_inline_style()は該当ハンドルの<link>が出力される前に呼ぶ必要があるため、
		// render()側(the_content内、wp_head以降に実行される)ではなくここで呼んでおく。
		self::ensure_custom_css();

		// 本文にショートコードがあるページでだけ読み込む(<head>に入れてFOUCを避ける)。
		// テンプレート直書き等でrender()が直接呼ばれた場合はrender()側でenqueueする。
		$post = get_post();
		if ( $post instanceof WP_Post
			&& ( has_shortcode( $post->post_content, self::SHORTCODE )
				|| has_shortcode( $post->post_content, self::LEGACY_SHORTCODE ) ) ) {
			wp_enqueue_style( self::STYLE_HANDLE );
		}
	}

	/**
	 * ショートコードのハード既定値(設定画面が未保存の場合のフォールバック)。
	 *
	 * 旧プラグインのwhats_new_options(移行時点の本番値)と同じ表示になるようにしてある。
	 */
	private static function hard_defaults() {
		return array(
			'title'            => '新着情報',
			'title_tag'        => 'p',
			'title_max_length' => '0',               // 0で切り詰めなし
			'post_type'        => 'post,page',
			'show_type'        => 'no',               // 投稿タイプ列を表示するか
			'number'           => '10',
			'orderby'          => 'date',            // date | modified
			'category'         => '',                // 表示対象に含めるカテゴリ(空=全部)
			'show_category'    => 'no',               // カテゴリ列を表示するか
			'category_limit'   => '3',               // 1件あたりのカテゴリ列挙数上限(0で上限なし)
			'exclude_category' => 'exclude-from-whatsnew,uncategorized',
			'exclude_ids'      => '',                // 投稿ID/固定ページIDで個別除外
			'newmark_days'     => '30',              // 0でNEW!マークを無効化
			'newmark_latest'   => 'yes',             // 最新1件には常にNEW!を付ける
			'newmark_text'     => 'NEW!',
			'date_format'      => '',                // 空=「設定 > 一般」の日付フォーマット
			'empty_text'       => '現在、新着情報はありません。',
			'custom_css'       => '',                // 設定画面のみで編集。ショートコード属性としては扱わない
		);
	}

	/**
	 * ショートコードの既定値。
	 *
	 * 設定画面(「設定 > AEM What's New」)で保存された値をハード既定値の上に重ねたもの。
	 * ショートコード側で属性を明示指定した場合は、shortcode_atts()の仕様によりこちらではなく
	 * 明示指定された値が使われる(= ショートコード属性が最優先)。
	 */
	private static function defaults() {
		$saved = get_option( self::OPTION_NAME, array() );

		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::hard_defaults() );
	}

	/**
	 * 設定画面で編集可能なフィールドの定義。
	 */
	private static function fields() {
		return array(
			'title'            => array(
				'type'  => 'text',
				'label' => '見出しテキスト',
				'desc'  => '空にすると見出しを表示しない',
			),
			'title_tag'        => array(
				'type'    => 'select',
				'label'   => '見出しタグ',
				'choices' => array_combine( self::ALLOWED_TITLE_TAGS, self::ALLOWED_TITLE_TAGS ),
			),
			'title_max_length' => array(
				'type'  => 'number',
				'label' => 'タイトル最大文字数',
				'min'   => 0,
				'max'   => 200,
				'desc'  => 'この文字数を超える部分は「…」に置き換える。0で切り詰めなし',
			),
			'post_type'        => array(
				'type'  => 'post_types',
				'label' => '対象投稿タイプ',
				'desc'  => 'このサイトで公開状態(public)になっている投稿タイプの一覧。何もチェックしない場合は post のみが対象になる',
			),
			'show_type'        => array(
				'type'  => 'checkbox',
				'label' => '投稿タイプ列を表示する',
			),
			'number'           => array(
				'type'  => 'number',
				'label' => '表示件数',
				'min'   => 1,
				'max'   => 50,
			),
			'orderby'          => array(
				'type'    => 'select',
				'label'   => '並び順',
				'choices' => array(
					'date'     => '投稿日',
					'modified' => '更新日',
				),
			),
			'category'         => array(
				'type'  => 'text',
				'label' => '対象カテゴリ',
				'desc'  => 'スラッグ/カテゴリ名/IDをカンマ区切りで指定。空で全カテゴリ',
			),
			'show_category'    => array(
				'type'  => 'checkbox',
				'label' => 'カテゴリ列を表示する',
			),
			'category_limit'   => array(
				'type'  => 'number',
				'label' => 'カテゴリ列の表示数上限',
				'min'   => 0,
				'max'   => 20,
				'desc'  => '1記事あたりに列挙するカテゴリ数の上限(カテゴリ列を表示する場合のみ有効)。0で上限なし',
			),
			'exclude_category' => array(
				'type'  => 'text',
				'label' => '除外カテゴリ',
				'desc'  => 'スラッグ/カテゴリ名/IDをカンマ区切りで指定',
			),
			'exclude_ids'      => array(
				'type'  => 'text',
				'label' => '個別除外ID',
				'desc'  => '投稿ID/固定ページIDをカンマ区切りで指定',
			),
			'newmark_days'     => array(
				'type'  => 'number',
				'label' => 'NEW!表示日数',
				'min'   => 0,
				'max'   => 3650,
				'desc'  => '0でNEW!マークを無効化',
			),
			'newmark_latest'   => array(
				'type'  => 'checkbox',
				'label' => '最新1件に常にNEW!を付ける',
			),
			'newmark_text'     => array(
				'type'  => 'text',
				'label' => 'NEW!マークの文字列',
				'desc'  => '空にするとマーク自体を表示しない',
			),
			'date_format'      => array(
				'type'  => 'text',
				'label' => '日付フォーマット',
				'desc'  => '空欄で「設定 > 一般」の日付形式を使用(PHPのdate()書式)',
			),
			'empty_text'       => array(
				'type'  => 'text',
				'label' => '0件時の表示文言',
				'desc'  => '空にすると非表示',
			),
			'custom_css'       => array(
				'type'  => 'textarea',
				'label' => 'カスタムCSS',
				'desc'  => '同梱の ' . self::STYLE_HANDLE . '.css に追加で読み込まれる。NEW!マークや日付/タイトル/カテゴリ/タイプの並び方など、見た目全般をここで上書きできる(対象クラス: .whatsnew, .whatsnew-title, .whatsnew-item, .newmark, .whatsnew-type, .whatsnew-category 等)',
			),
		);
	}

	/**
	 * 設定画面を「設定」メニュー配下に登録する。
	 */
	public static function register_settings_page() {
		add_options_page(
			"AEM What's New",
			"AEM What's New",
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
			plugins_url( 'aem-whatsnew.css', __FILE__ ),
			array(),
			self::VERSION
		);
		self::ensure_custom_css();
		wp_enqueue_style( self::STYLE_HANDLE );
	}

	/**
	 * プラグイン一覧に「設定」リンクを追加する。
	 */
	public static function add_settings_link( $links ) {
		$url = admin_url( 'options-general.php?page=' . self::SETTINGS_SLUG );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">設定</a>' );

		return $links;
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
		$out['orderby']          = ( isset( $input['orderby'] ) && 'modified' === $input['orderby'] ) ? 'modified' : 'date';
		$out['category']         = isset( $input['category'] ) ? sanitize_text_field( $input['category'] ) : '';
		$out['show_category']    = ! empty( $input['show_category'] ) ? 'yes' : 'no';
		$out['category_limit']   = (string) max( 0, absint( $input['category_limit'] ?? 3 ) );
		$out['exclude_category'] = isset( $input['exclude_category'] ) ? sanitize_text_field( $input['exclude_category'] ) : '';
		$out['exclude_ids']      = isset( $input['exclude_ids'] ) ? sanitize_text_field( $input['exclude_ids'] ) : '';
		$out['newmark_days']     = (string) max( 0, absint( $input['newmark_days'] ?? 30 ) );
		$out['newmark_latest']   = ! empty( $input['newmark_latest'] ) ? 'yes' : 'no';
		$out['newmark_text']     = isset( $input['newmark_text'] ) ? sanitize_text_field( $input['newmark_text'] ) : 'NEW!';
		$out['date_format']      = isset( $input['date_format'] ) ? sanitize_text_field( $input['date_format'] ) : '';
		$out['empty_text']       = isset( $input['empty_text'] ) ? sanitize_text_field( $input['empty_text'] ) : '';
		$out['custom_css']       = isset( $input['custom_css'] ) ? sanitize_textarea_field( $input['custom_css'] ) : '';

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
	<h1>AEM What's New — 設定</h1>
	<p>
		ここで指定した値は、ショートコード <code>[aem_whatsnew]</code> / <code>[showwhatsnew]</code> の既定値になります。
		ショートコード側で属性を明示指定した場合は、そちらが優先されます。
	</p>
	<form method="post" action="options.php">
		<?php settings_fields( self::SETTINGS_GROUP ); ?>
		<table class="form-table" role="presentation">
			<?php foreach ( self::fields() as $key => $field ) : ?>
			<tr>
				<th scope="row">
					<label for="aem-whatsnew-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
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

	<h2>プレビュー</h2>
	<p class="description">現在保存されている設定で <code>[aem_whatsnew]</code> を表示した場合の見た目です。</p>
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
		$id   = 'aem-whatsnew-' . $key;
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
					'<label><input type="checkbox" id="%1$s" name="%2$s" value="1"%3$s /> 有効にする</label>',
					esc_attr( $id ),
					esc_attr( $name ),
					checked( 'yes', $value, false )
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

			default:
				printf(
					'<input type="text" id="%1$s" name="%2$s" value="%3$s" class="regular-text" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $value )
				);
		}
	}

	public static function render( $atts = array() ) {
		$atts = shortcode_atts( self::defaults(), $atts, self::SHORTCODE );

		$title_tag      = in_array( $atts['title_tag'], self::ALLOWED_TITLE_TAGS, true ) ? $atts['title_tag'] : 'p';
		$title_max_len  = max( 0, (int) $atts['title_max_length'] );
		$orderby        = ( 'modified' === $atts['orderby'] ) ? 'modified' : 'date';
		$date_field     = ( 'modified' === $orderby ) ? 'modified' : 'date';
		$date_format    = '' !== $atts['date_format'] ? $atts['date_format'] : get_option( 'date_format' );
		$newmark_days   = max( 0, (int) $atts['newmark_days'] );
		$mark_latest    = self::is_truthy( $atts['newmark_latest'] );
		$newmark_text   = (string) $atts['newmark_text'];
		$show_type      = self::is_truthy( $atts['show_type'] );
		$show_category  = self::is_truthy( $atts['show_category'] );
		$category_limit = max( 0, (int) $atts['category_limit'] );

		$posts = self::query_posts( $atts, $orderby );

		wp_enqueue_style( self::STYLE_HANDLE );
		self::ensure_custom_css();

		ob_start();
		?>
<div class="whatsnew">
		<?php if ( '' !== $atts['title'] ) : ?>
	<<?php echo $title_tag; // phpcs:ignore WordPress.Security.EscapeOutput -- ALLOWED_TITLE_TAGSで検証済み ?> class="whatsnew-title"><?php echo esc_html( $atts['title'] ); ?></<?php echo $title_tag; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
		<?php endif; ?>
		<?php if ( empty( $posts ) ) : ?>
			<?php if ( '' !== $atts['empty_text'] ) : ?>
	<p class="whatsnew-empty"><?php echo esc_html( $atts['empty_text'] ); ?></p>
			<?php endif; ?>
		<?php else : ?>
	<hr />
			<?php foreach ( $posts as $index => $post ) : ?>
				<?php
				$timestamp = get_post_timestamp( $post, $date_field );
				$is_new    = self::is_new( $index, $timestamp, $newmark_days, $mark_latest );
				$date_text = ( 'modified' === $date_field )
					? get_the_modified_date( $date_format, $post )
					: get_the_date( $date_format, $post );
				$title     = self::truncate_title( get_the_title( $post ), $title_max_len );
				?>
	<a class="whatsnew-item" href="<?php echo esc_url( (string) get_permalink( $post ) ); ?>">
		<dl>
			<dt><time datetime="<?php echo esc_attr( $timestamp ? gmdate( 'c', $timestamp ) : '' ); ?>"><?php echo esc_html( $date_text ); ?></time></dt>
			<dd class="whatsnew-item-title"><?php if ( $is_new && '' !== trim( $newmark_text ) ) : ?><span class="newmark"><?php echo esc_html( $newmark_text ); ?></span> <?php endif; ?><?php echo esc_html( $title ); ?></dd>
			<?php if ( $show_type ) : ?>
			<dd class="whatsnew-type"><?php echo esc_html( self::post_type_label( $post ) ); ?></dd>
			<?php endif; ?>
			<?php if ( $show_category ) : ?>
			<dd class="whatsnew-category"><?php echo esc_html( self::category_names_for_post( $post, $category_limit ) ); ?></dd>
			<?php endif; ?>
		</dl>
	</a>
	<hr />
			<?php endforeach; ?>
		<?php endif; ?>
</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * 表示対象の投稿を取得する。
	 *
	 * 旧プラグインはget_posts()(suppress_filters既定true)を使っていたため
	 * User Access Manager等の閲覧制限フィルタが効いていなかった。ここではWP_Queryを
	 * 使うので、制限対象の記事は閲覧権限のない利用者には出ない。
	 *
	 * @return WP_Post[]
	 */
	private static function query_posts( array $atts, $orderby ) {
		$args = array(
			'post_type'           => self::parse_post_types( $atts['post_type'] ),
			'post_status'         => 'publish',
			'posts_per_page'      => min( 50, max( 1, (int) $atts['number'] ) ),
			'orderby'             => $orderby,
			'order'               => 'DESC',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
			'update_post_meta_cache' => false,
			// カテゴリ列を表示する場合はget_the_category()がpost毎にDBを叩かないよう事前キャッシュする。
			'update_post_term_cache' => self::is_truthy( $atts['show_category'] ?? 'no' ),
		);

		$tax_query = array();

		$include = self::resolve_category_ids( $atts['category'] );
		if ( ! empty( $include ) ) {
			$tax_query[] = array(
				'taxonomy'         => 'category',
				'field'            => 'term_id',
				'terms'            => $include,
				'operator'         => 'IN',
				'include_children' => true,
			);
		}

		$exclude = self::resolve_category_ids( $atts['exclude_category'] );
		if ( ! empty( $exclude ) ) {
			$tax_query[] = array(
				'taxonomy'         => 'category',
				'field'            => 'term_id',
				'terms'            => $exclude,
				'operator'         => 'NOT IN',
				'include_children' => true,
			);
		}

		if ( count( $tax_query ) > 1 ) {
			$tax_query['relation'] = 'AND';
		}
		if ( ! empty( $tax_query ) ) {
			$args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}

		$exclude_ids = self::parse_ids( $atts['exclude_ids'] );
		if ( ! empty( $exclude_ids ) ) {
			$args['post__not_in'] = $exclude_ids;
		}

		/**
		 * 新着情報のクエリ引数を差し替えるためのフィルタ。
		 *
		 * @param array $args WP_Queryの引数
		 * @param array $atts 正規化前のショートコード属性
		 */
		$args = apply_filters( 'aem_whatsnew_query_args', $args, $atts );

		$query = new WP_Query( $args );

		return $query->posts;
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

		return array_values( array_filter( array_map( 'trim', $parts ), 'strlen' ) );
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

	/**
	 * 設定画面で保存したカスタムCSSを、1リクエストにつき1回だけ登録済みスタイルに追加する。
	 *
	 * ショートコードが同一ページに複数回出現しても重複出力しないよう静的フラグで制御する。
	 */
	private static function ensure_custom_css() {
		static $added = false;
		if ( $added ) {
			return;
		}
		$added = true;

		$css = trim( (string) ( self::defaults()['custom_css'] ?? '' ) );
		if ( '' === $css ) {
			return;
		}

		// <style>タグ内に出力されるため、閉じタグ文字列だけ念のためエスケープしておく。
		$css = str_replace( '</style', '<\/style', $css );

		wp_add_inline_style( self::STYLE_HANDLE, $css );
	}
}

AEM_WhatsNew::boot();
