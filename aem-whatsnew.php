<?php
/**
 * Plugin Name: AEM What's New
 * Plugin URI:  https://github.com/MNagasako/AEM-What-s-New
 * Description: 新着情報一覧をWordPress標準API(WP_Query + ショートコードAPI)だけで表示する。外部プラグイン「What's New Generator」の置き換え。
 * Version:     1.1.0
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

	const VERSION           = '1.1.0';
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
			'post_type'        => 'post,page',
			'number'           => '10',
			'orderby'          => 'date',            // date | modified
			'category'         => '',                // 表示対象に含めるカテゴリ(空=全部)
			'exclude_category' => 'exclude-from-whatsnew,uncategorized',
			'exclude_ids'      => '',                // 投稿ID/固定ページIDで個別除外
			'newmark_days'     => '30',              // 0でNEW!マークを無効化
			'newmark_latest'   => 'yes',             // 最新1件には常にNEW!を付ける
			'date_format'      => '',                // 空=「設定 > 一般」の日付フォーマット
			'empty_text'       => '現在、新着情報はありません。',
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
			'post_type'        => array(
				'type'  => 'text',
				'label' => '対象投稿タイプ',
				'desc'  => 'カンマ区切り(例: post,page)。公開状態の投稿タイプのみ有効',
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
		$out['post_type']        = isset( $input['post_type'] ) ? sanitize_text_field( $input['post_type'] ) : 'post,page';
		$out['number']           = (string) min( 50, max( 1, absint( $input['number'] ?? 10 ) ) );
		$out['orderby']          = ( isset( $input['orderby'] ) && 'modified' === $input['orderby'] ) ? 'modified' : 'date';
		$out['category']         = isset( $input['category'] ) ? sanitize_text_field( $input['category'] ) : '';
		$out['exclude_category'] = isset( $input['exclude_category'] ) ? sanitize_text_field( $input['exclude_category'] ) : '';
		$out['exclude_ids']      = isset( $input['exclude_ids'] ) ? sanitize_text_field( $input['exclude_ids'] ) : '';
		$out['newmark_days']     = (string) max( 0, absint( $input['newmark_days'] ?? 30 ) );
		$out['newmark_latest']   = ! empty( $input['newmark_latest'] ) ? 'yes' : 'no';
		$out['date_format']      = isset( $input['date_format'] ) ? sanitize_text_field( $input['date_format'] ) : '';
		$out['empty_text']       = isset( $input['empty_text'] ) ? sanitize_text_field( $input['empty_text'] ) : '';

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

		$title_tag    = in_array( $atts['title_tag'], self::ALLOWED_TITLE_TAGS, true ) ? $atts['title_tag'] : 'p';
		$orderby      = ( 'modified' === $atts['orderby'] ) ? 'modified' : 'date';
		$date_field   = ( 'modified' === $orderby ) ? 'modified' : 'date';
		$date_format  = '' !== $atts['date_format'] ? $atts['date_format'] : get_option( 'date_format' );
		$newmark_days = max( 0, (int) $atts['newmark_days'] );
		$mark_latest  = self::is_truthy( $atts['newmark_latest'] );

		$posts = self::query_posts( $atts, $orderby );

		wp_enqueue_style( self::STYLE_HANDLE );

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
				?>
	<a class="whatsnew-item" href="<?php echo esc_url( (string) get_permalink( $post ) ); ?>">
		<dl>
			<dt><time datetime="<?php echo esc_attr( $timestamp ? gmdate( 'c', $timestamp ) : '' ); ?>"><?php echo esc_html( $date_text ); ?></time></dt>
			<dd><?php if ( $is_new ) : ?><span class="newmark">NEW!</span> <?php endif; ?><?php echo esc_html( get_the_title( $post ) ); ?></dd>
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
			'update_post_term_cache' => false,
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
		$available = get_post_types( array( 'public' => true ), 'names' );
		$requested = array_values( array_intersect( self::split_list( $value ), $available ) );

		return ! empty( $requested ) ? $requested : array( 'post' );
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
}

AEM_WhatsNew::boot();
