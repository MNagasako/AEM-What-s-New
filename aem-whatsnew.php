<?php
/**
 * Plugin Name: AEM What's New
 * Plugin URI:  https://github.com/MNagasako/AEM-What-s-New
 * Description: 新着情報一覧をWordPress標準API(WP_Query + ショートコードAPI)だけで表示する。外部プラグイン「What's New Generator」の置き換え。
 * Version:     1.0.0
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

	const VERSION           = '1.0.0';
	const SHORTCODE         = 'aem_whatsnew';
	const LEGACY_SHORTCODE  = 'showwhatsnew';
	const STYLE_HANDLE      = 'aem-whatsnew';

	/** 見出しに許可するタグ。これ以外が指定されたら p に落とす。 */
	const ALLOWED_TITLE_TAGS = array( 'p', 'h2', 'h3', 'h4', 'h5', 'h6', 'div' );

	public static function boot() {
		add_action( 'init', array( __CLASS__, 'register_shortcodes' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_style' ) );
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
	 * ショートコードの既定値。
	 *
	 * 旧プラグインのwhats_new_options(移行時点の本番値)と同じ表示になるようにしてある。
	 */
	private static function defaults() {
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
