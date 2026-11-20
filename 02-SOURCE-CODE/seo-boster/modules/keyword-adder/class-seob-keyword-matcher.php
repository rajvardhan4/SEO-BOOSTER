<?php
/**
 * Decides which page each keyword belongs to.
 *
 * Each page is scored for each keyword using the page title, URL slug,
 * Yoast focus keyphrase / SEO title and the page text. Synonyms are folded
 * (corp = corporation, LLC = limited liability company, WY = Wyoming...)
 * so "aged corp" still finds the "Aged Corporations" page.
 *
 * This class has no WordPress calls so it can be unit tested on its own.
 *
 * @package SEO_Boster
 */

defined( 'ABSPATH' ) || exit;

class SEOB_Keyword_Matcher {

	const W_TITLE   = 4.0;
	const W_SLUG    = 4.0;
	const W_FOCUS   = 3.0;
	const W_CONTENT = 3.0; // Max per token from body text.

	const MIN_SCORE  = 3.0;
	const HIGH_SCORE = 14.0;
	const MID_SCORE  = 7.0;

	/** Words that carry no topic meaning for matching. */
	private $stopwords = array(
		'a', 'an', 'the', 'and', 'or', 'for', 'of', 'in', 'on', 'to', 'with', 'your', 'our', 'you', 'we',
		'is', 'are', 'be', 'by', 'at', 'from', 'sale', 'sell', 'sells', 'selling', 'buy', 'buying', 'best',
		'top', 'get', 'new', 'now', 'service', 'services', 'page', 'us', 'inc', 'legal',
	);

	/** Words that mean the same thing for matching. */
	private $synonyms = array(
		'corp'          => 'corp',
		'corps'         => 'corp',
		'corporation'   => 'corp',
		'corporations'  => 'corp',
		'corporate'     => 'corp',
		'incorporated'  => 'corp',
		'incorporation' => 'corp',
		'llc'           => 'llc',
		'llcs'          => 'llc',
		'wy'            => 'wyoming',
		'wyo'           => 'wyoming',
		'wyoming'       => 'wyoming',
		'nv'            => 'nevada',
		'nevada'        => 'nevada',
		'de'            => 'delaware',
		'delaware'      => 'delaware',
		'entity'        => 'entity',
		'entities'      => 'entity',
		'company'       => 'company',
		'companies'     => 'company',
		'aged'          => 'aged',
		'age'           => 'aged',
		'shelf'         => 'shelf',
	);

	/** Phrases folded before splitting into words. */
	private $phrases = array(
		'limited liability companies' => ' llc ',
		'limited liability company'   => ' llc ',
	);

	/** Front page is usually generic, so it gets a smaller share. */
	private $front_page_factor = 0.75;

	public function __construct( array $args = array() ) {
		if ( isset( $args['front_page_factor'] ) ) {
			$this->front_page_factor = (float) $args['front_page_factor'];
		}
	}

	/**
	 * Turn text into normalized topic tokens.
	 *
	 * @param string $text Any text.
	 * @return string[]
	 */
	public function tokens( $text ) {
		$text = strtolower( html_entity_decode( (string) $text, ENT_QUOTES, 'UTF-8' ) );
		$text = str_replace( array_keys( $this->phrases ), array_values( $this->phrases ), $text );
		$text = preg_replace( '/[^a-z0-9]+/', ' ', $text );
		$out  = array();
		foreach ( preg_split( '/\s+/', trim( (string) $text ) ) as $word ) {
			if ( '' === $word ) {
				continue;
			}
			if ( isset( $this->synonyms[ $word ] ) ) {
				$word = $this->synonyms[ $word ];
			} elseif ( strlen( $word ) > 4 && 's' === substr( $word, -1 ) && 'ss' !== substr( $word, -2 ) ) {
				$word = substr( $word, 0, -1 );
			}
			if ( in_array( $word, $this->stopwords, true ) ) {
				continue;
			}
			$out[] = $word;
		}
		return $out;
	}

	/**
	 * Prepare a page once so it can be scored against many keywords.
	 *
	 * @param array $page id, title, url, slug, focus, seo_title, text, is_front.
	 */
	public function index_page( array $page ) {
		$content_tokens = $this->tokens( isset( $page['text'] ) ? $page['text'] : '' );
		$counts         = array_count_values( $content_tokens );
		$title_tokens   = array_unique( $this->tokens( isset( $page['title'] ) ? $page['title'] : '' ) );

		return array(
			'id'           => (int) $page['id'],
			'title'        => (string) ( isset( $page['title'] ) ? $page['title'] : '' ),
			'is_front'     => ! empty( $page['is_front'] ),
			'title_tokens' => $title_tokens,
			'slug_tokens'  => array_unique( $this->tokens( str_replace( '-', ' ', isset( $page['slug'] ) ? $page['slug'] : '' ) ) ),
			'focus_tokens' => array_unique( $this->tokens( ( isset( $page['focus'] ) ? $page['focus'] : '' ) . ' ' . ( isset( $page['seo_title'] ) ? $page['seo_title'] : '' ) ) ),
			'counts'       => $counts,
			'title_norm'   => ' ' . implode( ' ', $this->tokens( isset( $page['title'] ) ? $page['title'] : '' ) ) . ' ',
			'text_norm'    => ' ' . implode( ' ', $content_tokens ) . ' ',
			'focus_norm'   => ' ' . implode( ' ', $this->tokens( isset( $page['focus'] ) ? $page['focus'] : '' ) ) . ' ',
		);
	}

	/**
	 * Score one keyword against one indexed page.
	 *
	 * @param string[] $kw_tokens Keyword tokens.
	 * @param array    $page      Indexed page.
	 * @return array score, reasons.
	 */
	public function score( array $kw_tokens, array $page ) {
		$kw_tokens = array_values( array_unique( $kw_tokens ) );
		if ( ! $kw_tokens ) {
			return array( 'score' => 0.0, 'reasons' => array() );
		}

		$score       = 0.0;
		$matched     = 0;
		$in_title    = array();
		$in_slug     = array();
		$in_focus    = array();
		$content_hit = 0;
		$all_in_head = true;

		foreach ( $kw_tokens as $t ) {
			$hit = false;
			if ( in_array( $t, $page['title_tokens'], true ) ) {
				$score     += self::W_TITLE;
				$in_title[] = $t;
				$hit        = true;
			}
			if ( in_array( $t, $page['slug_tokens'], true ) ) {
				$score    += self::W_SLUG;
				$in_slug[] = $t;
				$hit       = true;
			}
			if ( ! in_array( $t, $page['title_tokens'], true ) && ! in_array( $t, $page['slug_tokens'], true ) ) {
				$all_in_head = false;
			}
			if ( in_array( $t, $page['focus_tokens'], true ) ) {
				$score     += self::W_FOCUS;
				$in_focus[] = $t;
				$hit        = true;
			}
			if ( ! empty( $page['counts'][ $t ] ) ) {
				$c            = (int) $page['counts'][ $t ];
				$score       += min( self::W_CONTENT, 1 + log( $c ) );
				$content_hit += $c;
				$hit          = true;
			}
			if ( $hit ) {
				++$matched;
			}
		}

		$coverage = $matched / count( $kw_tokens );
		$score   *= ( 0.5 + $coverage );

		$phrase = ' ' . implode( ' ', $kw_tokens ) . ' ';
		if ( $all_in_head ) {
			$score += 4;
		}
		if ( false !== strpos( $page['title_norm'], $phrase ) ) {
			$score += 3;
		}
		if ( false !== strpos( $page['text_norm'], $phrase ) ) {
			$score += 2;
		}
		// The page is already targeting this exact keyword in its SEO settings.
		$focus_exact = trim( $page['focus_norm'] ) === trim( $phrase );
		if ( $focus_exact ) {
			$score += 25;
		}

		// A page whose title is about other topics is a weaker fit.
		$extra  = count( array_diff( $page['title_tokens'], $kw_tokens ) );
		$score -= 0.3 * $extra;

		if ( $page['is_front'] ) {
			$score *= $this->front_page_factor;
		}

		$reasons = array();
		if ( $in_title ) {
			$reasons[] = 'title: ' . implode( ', ', array_unique( $in_title ) );
		}
		if ( $in_slug ) {
			$reasons[] = 'URL: ' . implode( ', ', array_unique( $in_slug ) );
		}
		if ( $focus_exact ) {
			$reasons[] = 'page already targets this keyphrase';
		} elseif ( $in_focus ) {
			$reasons[] = 'SEO keyphrase: ' . implode( ', ', array_unique( $in_focus ) );
		}
		if ( $content_hit ) {
			$reasons[] = 'page text mentions it ' . $content_hit . 'x';
		}

		return array(
			'score'   => round( max( 0, $score ), 2 ),
			'reasons' => $reasons,
		);
	}

	/**
	 * Find the best page for each keyword.
	 *
	 * @param string[] $keywords Keywords.
	 * @param array[]  $pages    Raw pages (see index_page).
	 * @return array[] One row per keyword: keyword, post_id, score, level, reasons, alternatives.
	 */
	public function match( array $keywords, array $pages ) {
		$indexed = array();
		foreach ( $pages as $p ) {
			$indexed[] = $this->index_page( $p );
		}

		$rows = array();
		foreach ( $keywords as $kw ) {
			$kw_tokens = $this->tokens( $kw );
			$scored    = array();
			foreach ( $indexed as $page ) {
				$s = $this->score( $kw_tokens, $page );
				if ( $s['score'] > 0 ) {
					$scored[] = array(
						'post_id' => $page['id'],
						'title'   => $page['title'],
						'score'   => $s['score'],
						'reasons' => $s['reasons'],
					);
				}
			}
			usort(
				$scored,
				static function ( $a, $b ) {
					return $b['score'] <=> $a['score'];
				}
			);

			$best = isset( $scored[0] ) && $scored[0]['score'] >= self::MIN_SCORE ? $scored[0] : null;

			$rows[] = array(
				'keyword'      => $kw,
				'post_id'      => $best ? $best['post_id'] : 0,
				'score'        => $best ? $best['score'] : 0,
				'level'        => $best ? $this->level( $best['score'] ) : 'none',
				'reasons'      => $best ? $best['reasons'] : array(),
				'alternatives' => array_slice( $scored, 1, 3 ),
			);
		}
		return $rows;
	}

	public function level( $score ) {
		if ( $score >= self::HIGH_SCORE ) {
			return 'high';
		}
		if ( $score >= self::MID_SCORE ) {
			return 'medium';
		}
		return 'low';
	}
}
