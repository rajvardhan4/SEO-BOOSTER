<?php
/**
 * Writes a natural sentence that contains the keyword exactly once.
 *
 * Templates rotate per page so one page does not repeat the same line.
 * Sites can replace or extend the templates with the
 * 'seo_boster_sentence_templates' filter.
 *
 * @package SEO_Boster
 */

defined( 'ABSPATH' ) || exit;

class SEOB_Sentence_Writer {

	/** Words that keep a capital letter inside a sentence. */
	private $proper = array( 'nevada', 'wyoming', 'delaware', 'florida', 'texas', 'california', 'new', 'york', 'america', 'usa', 'us', 'uk', 'dubai', 'india' );

	/** @var string */
	private $site;

	public function __construct( $site_name ) {
		$this->site = trim( (string) $site_name ) !== '' ? (string) $site_name : 'our team';
	}

	/**
	 * Templates. %1$s = keyword with a/an, %2$s = site name, %3$s = keyword alone.
	 */
	public function templates() {
		$t = array(
			'sale'    => array(
				'Looking for %1$s? %2$s has options ready for a fast transfer.',
				'Browse every %3$s we currently offer at %2$s.',
				'Each %3$s comes with complete records and guidance from our team.',
				'If you need %1$s, our team can walk you through the full process.',
				'Clients worldwide choose %2$s when they want %1$s with clean records.',
			),
			'general' => array(
				'%2$s helps clients worldwide with every %3$s need.',
				'Learn how %1$s can support your business goals.',
				'Our team explains every %3$s option in clear, simple terms.',
				'Choosing the right %3$s is easier with expert support from %2$s.',
				'Ask us any question about %1$s and we will guide you step by step.',
			),
		);
		if ( function_exists( 'apply_filters' ) ) {
			$t = apply_filters( 'seo_boster_sentence_templates', $t );
		}
		return $t;
	}

	/**
	 * Display form of the keyword inside a sentence.
	 *
	 * @param string $keyword Raw keyword.
	 */
	public function display_keyword( $keyword ) {
		$words = preg_split( '/\s+/', trim( (string) $keyword ) );
		foreach ( $words as $i => $w ) {
			$plain = preg_replace( '/[^A-Za-z]/', '', $w );
			if ( strlen( $plain ) >= 2 && strtoupper( $plain ) === $plain ) {
				continue; // Acronym like LLC, WY, EIN.
			}
			$w = strtolower( $w );
			if ( in_array( $plain ? strtolower( $plain ) : $w, $this->proper, true ) ) {
				$w = ucfirst( $w );
			}
			$words[ $i ] = $w;
		}
		return implode( ' ', $words );
	}

	public function with_article( $kw ) {
		$words = explode( ' ', $kw );
		$last  = strtolower( end( $words ) );
		$plain = preg_replace( '/[^A-Za-z]/', '', end( $words ) );
		$acr   = strlen( $plain ) >= 2 && strtoupper( $plain ) === $plain;

		// Plurals read better without an article.
		if ( ! $acr && strlen( $last ) > 3 && 's' === substr( $last, -1 ) && 'ss' !== substr( $last, -2 ) ) {
			return $kw;
		}
		$first = strtolower( substr( $kw, 0, 1 ) );
		return ( in_array( $first, array( 'a', 'e', 'i', 'o', 'u' ), true ) ? 'an ' : 'a ' ) . $kw;
	}

	/**
	 * Write a sentence for a keyword.
	 *
	 * @param string $keyword Keyword.
	 * @param int    $slot    Position of this keyword on its page (rotates templates).
	 */
	public function write( $keyword, $slot = 0 ) {
		$kw    = $this->display_keyword( $keyword );
		$group = preg_match( '/\bsales?\b/i', $keyword ) ? 'sale' : 'general';
		$tpls  = $this->templates();
		$list  = ! empty( $tpls[ $group ] ) ? array_values( $tpls[ $group ] ) : array( '%2$s offers %3$s.' );
		$tpl   = $list[ absint( $slot ) % count( $list ) ];

		$sentence = sprintf( $tpl, $this->with_article( $kw ), $this->site, $kw );
		return ucfirst( trim( preg_replace( '/\s+/', ' ', $sentence ) ) );
	}
}
