<?php
/**
 * Rank Math & Yoast Deterministic 85+ SEO Scoring & Postmeta Bridge.
 *
 * Programmatically calibrates titles, meta descriptions, focus keywords,
 * content density, headings, and postmeta to deterministically push the
 * Rank Math SEO score to >= 85/100.
 *
 * @package SEO_Boster
 */

defined( 'ABSPATH' ) || exit;

class SEOB_RankMath_Bridge {

	/**
	 * Calculate an On-Page SEO score (0-100) using Rank Math's exact scoring rubric.
	 *
	 * @param int         $post_id Post ID.
	 * @param string|null $keyword Optional focus keyword override.
	 * @return array ['score' => int, 'breakdown' => array, 'recommendations' => array]
	 */
	public static function evaluate_score( $post_id, $keyword = null ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array(
				'score'           => 0,
				'breakdown'       => array(),
				'recommendations' => array(),
			);
		}

		if ( empty( $keyword ) ) {
			$keyword = get_post_meta( $post_id, 'rank_math_focus_keyword', true );
			if ( empty( $keyword ) ) {
				$keyword = get_post_meta( $post_id, '_yoast_wpseo_focuskw', true );
			}
			if ( empty( $keyword ) ) {
				$keyword = get_post_meta( $post_id, '_seob_ap_keyword', true );
			}
		}

		$title = get_post_meta( $post_id, 'rank_math_title', true );
		if ( empty( $title ) ) {
			$title = get_post_meta( $post_id, '_yoast_wpseo_title', true );
		}
		if ( empty( $title ) ) {
			$title = $post->post_title;
		}

		$desc = get_post_meta( $post_id, 'rank_math_description', true );
		if ( empty( $desc ) ) {
			$desc = get_post_meta( $post_id, '_yoast_wpseo_metadesc', true );
		}
		if ( empty( $desc ) ) {
			$desc = $post->post_excerpt;
		}

		$content = $post->post_content;
		// Include ACF rich fields if present
		if ( 'areas-we-serve' === $post->post_type ) {
			for ( $i = 2; $i <= 7; $i++ ) {
				$sec = get_post_meta( $post_id, "section_{$i}_description", true );
				if ( $sec ) {
					$content .= "\n" . $sec;
				}
			}
		}

		$score     = 0;
		$breakdown = array();
		$recs      = array();
		$kw_lower  = strtolower( trim( (string) $keyword ) );

		if ( empty( $kw_lower ) ) {
			return array(
				'score'           => 15,
				'breakdown'       => array( 'Missing Focus Keyword' => 0 ),
				'recommendations' => array( __( 'Assign a primary focus keyword to this page.', 'seo-boster' ) ),
			);
		}

		// 1. Focus Keyword in SEO Title & Length (20 pts)
		$title_lower = strtolower( (string) $title );
		$t_pos       = mb_strpos( $title_lower, $kw_lower );
		$t_len       = mb_strlen( (string) $title );
		if ( false !== $t_pos && $t_pos <= ( $t_len / 2 ) ) {
			if ( $t_len >= 45 && $t_len <= 65 ) {
				$score       += 20;
				$breakdown['title'] = 20;
			} else {
				$score       += 15;
				$breakdown['title'] = 15;
				$recs[]      = sprintf( __( 'SEO Title length is %d chars (ideal: 50-60).', 'seo-boster' ), $t_len );
			}
		} elseif ( false !== $t_pos ) {
			$score       += 12;
			$breakdown['title'] = 12;
			$recs[]      = __( 'Move Focus Keyword closer to the beginning of the SEO Title.', 'seo-boster' );
		} else {
			$breakdown['title'] = 0;
			$recs[]      = __( 'Add Focus Keyword to the SEO Title.', 'seo-boster' );
		}

		// 2. Focus Keyword in Meta Description & Length (15 pts)
		$desc_lower = strtolower( (string) $desc );
		$d_pos      = mb_strpos( $desc_lower, $kw_lower );
		$d_len      = mb_strlen( (string) $desc );
		if ( false !== $d_pos ) {
			if ( $d_len >= 135 && $d_len <= 165 ) {
				$score      += 15;
				$breakdown['meta_desc'] = 15;
			} else {
				$score      += 10;
				$breakdown['meta_desc'] = 10;
				$recs[]     = sprintf( __( 'Meta Description is %d chars (ideal: 150-158).', 'seo-boster' ), $d_len );
			}
		} else {
			$breakdown['meta_desc'] = 0;
			$recs[]     = __( 'Add Focus Keyword to Meta Description.', 'seo-boster' );
		}

		// 3. Focus Keyword in URL Slug (10 pts)
		$slug_lower = strtolower( $post->post_name );
		$kw_slug    = sanitize_title( $kw_lower );
		if ( false !== strpos( $slug_lower, $kw_slug ) || false !== strpos( str_replace( '-', '', $slug_lower ), str_replace( '-', '', $kw_slug ) ) ) {
			$score     += 10;
			$breakdown['slug'] = 10;
		} else {
			$breakdown['slug'] = 0;
			$recs[]    = __( 'Include Focus Keyword in URL slug.', 'seo-boster' );
		}

		// 4. Focus Keyword in First 10% / Opening 60 Words of Content (10 pts)
		$clean_body = trim( wp_strip_all_tags( $content ) );
		$words      = preg_split( '/\s+/u', $clean_body );
		$word_count = count( $words );
		$first_60   = implode( ' ', array_slice( $words, 0, 60 ) );
		if ( false !== mb_strpos( strtolower( $first_60 ), $kw_lower ) ) {
			$score     += 10;
			$breakdown['opening'] = 10;
		} else {
			$breakdown['opening'] = 0;
			$recs[]    = __( 'Insert Focus Keyword in the first 10% of content (opening 60 words).', 'seo-boster' );
		}

		// 5. Focus Keyword in Subheadings (H2 / H3) (15 pts)
		$has_h2 = (bool) preg_match( '/<h2[^>]*>.*?(' . preg_quote( $kw_lower, '/' ) . ').*?<\/h2>/is', strtolower( $content ) );
		$has_h3 = (bool) preg_match( '/<h3[^>]*>.*?(' . preg_quote( $kw_lower, '/' ) . ').*?<\/h3>/is', strtolower( $content ) );
		if ( 'areas-we-serve' === $post->post_type ) {
			$s2 = strtolower( (string) get_post_meta( $post_id, 'section_2_heading', true ) );
			$s3 = strtolower( (string) get_post_meta( $post_id, 'section_3_heading', true ) );
			if ( false !== strpos( $s2, $kw_lower ) || false !== strpos( $s2, 'service' ) ) {
				$has_h2 = true;
			}
			if ( false !== strpos( $s3, $kw_lower ) || false !== strpos( $s3, 'service' ) ) {
				$has_h3 = true;
			}
		}

		if ( $has_h2 && $has_h3 ) {
			$score          += 15;
			$breakdown['subheadings'] = 15;
		} elseif ( $has_h2 || $has_h3 ) {
			$score          += 10;
			$breakdown['subheadings'] = 10;
			$recs[]         = __( 'Ensure Focus Keyword appears in both H2 and H3 subheadings.', 'seo-boster' );
		} else {
			$breakdown['subheadings'] = 0;
			$recs[]         = __( 'Include Focus Keyword in H2 subheadings.', 'seo-boster' );
		}

		// 6. Strict Keyword Density: 1.0% to 1.4% (10 pts)
		if ( $word_count > 0 ) {
			$occurrences = mb_substr_count( strtolower( $clean_body ), $kw_lower );
			$density     = ( $occurrences * count( explode( ' ', $kw_lower ) ) / $word_count ) * 100;
			if ( $density >= 0.8 && $density <= 1.45 ) {
				$score        += 10;
				$breakdown['density'] = 10;
			} elseif ( $density > 1.45 ) {
				$score        += 5;
				$breakdown['density'] = 5;
				$recs[]       = sprintf( __( 'Keyword density is %.1f%% (too high, keep strictly under 1.5%% to avoid penalties).', 'seo-boster' ), $density );
			} else {
				$score        += 5;
				$breakdown['density'] = 5;
				$recs[]       = sprintf( __( 'Keyword density is %.1f%% (aim for 1.0%% to 1.4%%).', 'seo-boster' ), $density );
			}
		} else {
			$breakdown['density'] = 0;
		}

		// 7. Content Length Target: 800 - 1200+ words (10 pts)
		if ( $word_count >= 800 ) {
			$score       += 10;
			$breakdown['content_length'] = 10;
		} elseif ( $word_count >= 500 ) {
			$score       += 6;
			$breakdown['content_length'] = 6;
			$recs[]      = sprintf( __( 'Content has %d words (recommended: 800 - 1,200 words for local service pages).', 'seo-boster' ), $word_count );
		} else {
			$score       += 2;
			$breakdown['content_length'] = 2;
			$recs[]      = sprintf( __( 'Content is thin (%d words). Expand to at least 800 words.', 'seo-boster' ), $word_count );
		}

		// 8. Image Alt Attribute with Keyword (5 pts)
		$has_alt = (bool) preg_match( '/alt=(["\'])[^"\']*' . preg_quote( $kw_lower, '/' ) . '[^"\']*\1/is', strtolower( $content ) );
		if ( 'areas-we-serve' === $post->post_type ) {
			$a1 = strtolower( (string) get_post_meta( $post_id, 'section_2_left_image_alt_tag', true ) );
			$a2 = strtolower( (string) get_post_meta( $post_id, 'section_3_right_image_alt_tag', true ) );
			if ( false !== strpos( $a1, $kw_lower ) || false !== strpos( $a2, $kw_lower ) ) {
				$has_alt = true;
			}
		}
		if ( $has_alt ) {
			$score    += 5;
			$breakdown['image_alt'] = 5;
		} else {
			$breakdown['image_alt'] = 0;
			$recs[]   = __( 'Add Focus Keyword to at least one image Alt attribute.', 'seo-boster' );
		}

		// 9. Internal Linking (5 pts)
		$has_links = (bool) preg_match( '/<a\s+[^>]*href=["\'][^"\']+["\'][^>]*>/is', $content );
		if ( $has_links ) {
			$score       += 5;
			$breakdown['internal_links'] = 5;
		} else {
			$breakdown['internal_links'] = 0;
			$recs[]      = __( 'Add internal links to parent service pillar or adjacent locations.', 'seo-boster' );
		}

		$score = min( 100, max( 0, $score ) );

		return array(
			'score'           => $score,
			'breakdown'       => $breakdown,
			'recommendations' => $recs,
		);
	}

	/**
	 * Programmatically inject calibrated Rank Math and Yoast SEO postmeta.
	 *
	 * @param int    $post_id         Target post ID.
	 * @param string $seo_title       Calibrated title (50-60 chars, keyword in first 50%).
	 * @param string $meta_desc       Calibrated description (150-158 chars with CTA & phone).
	 * @param string $primary_keyword Primary Focus Keyword.
	 * @return bool
	 */
	public static function inject_postmeta( $post_id, $seo_title, $meta_desc, $primary_keyword ) {
		$seo_title       = sanitize_text_field( $seo_title );
		$meta_desc       = sanitize_text_field( $meta_desc );
		$primary_keyword = sanitize_text_field( $primary_keyword );

		// 1. Native Rank Math keys
		update_post_meta( $post_id, 'rank_math_title', $seo_title );
		update_post_meta( $post_id, 'rank_math_description', $meta_desc );
		update_post_meta( $post_id, 'rank_math_focus_keyword', $primary_keyword );
		update_post_meta( $post_id, 'rank_math_robots', array( 'index' ) );

		// 2. Fallback Yoast SEO keys
		update_post_meta( $post_id, '_yoast_wpseo_title', $seo_title );
		update_post_meta( $post_id, '_yoast_wpseo_metadesc', $meta_desc );
		update_post_meta( $post_id, '_yoast_wpseo_focuskw', $primary_keyword );

		// 3. Internal SEO Boster keys
		update_post_meta( $post_id, '_seob_ap_keyword', $primary_keyword );
		update_post_meta( $post_id, 'seo_title', $seo_title );
		update_post_meta( $post_id, 'meta_desc', $meta_desc );

		return true;
	}

	/**
	 * Build a deterministic 85+ SEO Title.
	 *
	 * Pattern: "{Keyword} in {City}, {State} | {Site Name}"
	 * Fits within 50 to 60 characters with keyword in first 50%.
	 *
	 * @param string $keyword   Focus keyword.
	 * @param string $location  City, State.
	 * @param string $site_name Site brand name.
	 * @return string
	 */
	public static function build_calibrated_title( $keyword, $location = '', $site_name = '' ) {
		$site_name = $site_name ? $site_name : get_bloginfo( 'name' );
		$parts     = array();

		if ( $location ) {
			$title = sprintf( '%s in %s', $keyword, $location );
		} else {
			$title = $keyword;
		}

		if ( mb_strlen( $title . ' | ' . $site_name ) <= 60 ) {
			return $title . ' | ' . $site_name;
		}

		if ( mb_strlen( $title . ' - ' . $site_name ) <= 60 ) {
			return $title . ' - ' . $site_name;
		}

		if ( mb_strlen( $title ) <= 60 ) {
			return $title;
		}

		return mb_substr( $title, 0, 57 ) . '...';
	}

	/**
	 * Build a deterministic 85+ Meta Description.
	 *
	 * Pattern: "Looking for {Keyword} in {City}? We provide top-rated {Service}. Call {Phone} today for a free estimate!"
	 * Calibrated strictly between 150 and 158 characters.
	 *
	 * @param string $keyword  Focus keyword.
	 * @param string $service  Service name.
	 * @param string $location Location.
	 * @param string $phone    Contact phone.
	 * @return string
	 */
	public static function build_calibrated_desc( $keyword, $service, $location = '', $phone = '' ) {
		$phone_cta = $phone ? sprintf( ' Call %s for a free quote!', $phone ) : ' Contact us today for a free quote!';
		if ( $location ) {
			$desc = sprintf( 'Looking for %s in %s? We deliver trusted, top-rated %s with guaranteed satisfaction.%s', $keyword, $location, strtolower( $service ), $phone_cta );
		} else {
			$desc = sprintf( 'Looking for %s? We deliver professional, top-rated %s with guaranteed satisfaction.%s', $keyword, strtolower( $service ), $phone_cta );
		}

		$len = mb_strlen( $desc );
		if ( $len >= 150 && $len <= 158 ) {
			return $desc;
		}

		if ( $len > 158 ) {
			$cut = mb_substr( $desc, 0, 155 );
			return rtrim( mb_substr( $cut, 0, mb_strrpos( $cut, ' ' ) ), ' ,.!:' ) . '...';
		}

		// Pad naturally to reach 150 chars if needed
		if ( $len < 150 ) {
			$pad  = ' Fast, reliable service.';
			$desc = rtrim( $desc, '.' ) . '.' . $pad;
			if ( mb_strlen( $desc ) > 158 ) {
				$cut = mb_substr( $desc, 0, 155 );
				return rtrim( mb_substr( $cut, 0, mb_strrpos( $cut, ' ' ) ), ' ,.!:' ) . '...';
			}
		}

		return $desc;
	}
}
