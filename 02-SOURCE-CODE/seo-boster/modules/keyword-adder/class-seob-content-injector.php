<?php
/**
 * Shows the keyword lines on the page.
 *
 * The page's own content is never rewritten. The lines are added when the
 * page is displayed, so it works with any page builder (Divi, Elementor,
 * Gutenberg, classic) and every change can be undone in one click.
 *
 * @package SEO_Boster
 */

defined( 'ABSPATH' ) || exit;

class SEOB_Content_Injector {

	const META_BLOCK    = '_seob_block';
	const META_POSITION = '_seob_position';

	/** @var int[] Posts already handled in this request (some builders call the_content twice). */
	private static $done = array();

	public function hooks() {
		add_filter( 'the_content', array( $this, 'inject' ), 20 );
		add_action( 'wp_enqueue_scripts', array( $this, 'styles' ) );
	}

	public function styles() {
		if ( is_singular() && get_post_meta( get_queried_object_id(), self::META_BLOCK, true ) ) {
			wp_enqueue_style( 'seob-front', SEOB_URL . 'assets/css/front.css', array(), SEOB_VERSION );
		}
	}

	/**
	 * Add the stored block to the main content of a single page or post.
	 *
	 * @param string $content Post content HTML.
	 */
	public function inject( $content ) {
		if ( is_admin() || is_feed() || ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$post_id = get_the_ID();
		if ( ! $post_id || (int) $post_id !== (int) get_queried_object_id() || in_array( $post_id, self::$done, true ) ) {
			return $content;
		}
		$block = get_post_meta( $post_id, self::META_BLOCK, true );
		if ( ! $block ) {
			return $content;
		}
		self::$done[] = (int) $post_id;

		$after = self::paragraph_position( $post_id );
		if ( $after > 0 ) {
			return self::insert_after_paragraph( $content, $block, $after );
		}
		return $content . $block;
	}

	/**
	 * Paragraph number to insert after, 0 means end of content.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function paragraph_position( $post_id ) {
		return absint( get_post_meta( $post_id, self::META_POSITION, true ) );
	}

	public static function insert_after_paragraph( $content, $block, $n ) {
		$offset = 0;
		for ( $i = 0; $i < $n; $i++ ) {
			$pos = stripos( $content, '</p>', $offset );
			if ( false === $pos ) {
				return $content . $block;
			}
			$offset = $pos + 4;
		}
		return substr( $content, 0, $offset ) . $block . substr( $content, $offset );
	}

	/**
	 * Rebuild the stored HTML for a post from its active placements and renumber the lines.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function rebuild( $post_id ) {
		$rows = SEOB_DB::active_for_post( $post_id );
		if ( ! $rows ) {
			delete_post_meta( $post_id, self::META_BLOCK );
			delete_post_meta( $post_id, self::META_POSITION );
			return;
		}

		$lines = '';
		$n     = 0;
		foreach ( $rows as $row ) {
			++$n;
			if ( (int) $row->line_no !== $n ) {
				SEOB_DB::set_line( $row->id, $n );
			}
			$lines .= '<p class="seob-line" id="seob-line-' . (int) $n . '">' . esc_html( $row->sentence ) . '</p>';
		}

		$html = '<div class="seob-block" data-seob="1"><div class="seob-inner">' . $lines . '</div></div>';
		update_post_meta( $post_id, self::META_BLOCK, $html );
	}

	/**
	 * Plain words for where a line sits on the page.
	 *
	 * @param int $post_id Post ID.
	 * @param int $line    Line number.
	 */
	public static function describe( $post_id, $line ) {
		$after = self::paragraph_position( $post_id );
		if ( $after > 0 ) {
			/* translators: 1: line number, 2: paragraph number */
			return sprintf( __( 'Line %1$d of the SEO Boster section, placed after paragraph %2$d of the page', 'seo-boster' ), $line, $after );
		}
		/* translators: %d: line number */
		return sprintf( __( 'Line %d of the SEO Boster section, at the end of the page content', 'seo-boster' ), $line );
	}

	public static function line_url( $post_id, $line ) {
		return get_permalink( $post_id ) . '#seob-line-' . (int) $line;
	}
}
