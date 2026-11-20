<?php
/**
 * Writes one Area page: builds the AI prompt from the team's rules, checks the
 * answer, and fills every field. Fixed rules (titles, phones, buttons, images)
 * are done in code so they are always right.
 *
 * @package SEO_Boster
 */

defined( 'ABSPATH' ) || exit;

class SEOB_AP_Writer {

	/** Word ranges from the team prompt: [min, max]. */
	public static function word_rules( $faq_count ) {
		$r = array(
			'section_2_heading'             => array( 6, 8 ),
			'section_2_description'         => array( 100, 150 ),
			'section_2_left_image_alt_tag'  => array( 6, 10 ),
			'section_3_heading'             => array( 6, 8 ),
			'section_3_description'         => array( 100, 150 ),
			'section_3_right_image_alt_tag' => array( 6, 10 ),
			'section_4_heading'             => array( 6, 8 ),
			'section_4_description'         => array( 100, 150 ),
			'section_4_right_image_alt_tag' => array( 6, 10 ),
			'section_5_main_heading'        => array( 8, 14 ),
			'section_5_description'         => array( 50, 80 ),
			'section_6_main_heading'        => array( 8, 14 ),
			'section_7_heading'             => array( 8, 14 ),
			'section_7_description'         => array( 40, 60 ),
		);
		for ( $i = 1; $i <= 6; $i++ ) {
			$r[ "section_5_box_{$i}_heading" ]     = array( 5, 9 );
			$r[ "section_5_box_{$i}_description" ] = array( 35, 55 );
		}
		for ( $i = 1; $i <= $faq_count; $i++ ) {
			$r[ "faq_{$i}_heading" ]     = array( 6, 10 );
			$r[ "faq_{$i}_description" ] = array( 55, 75 );
		}
		return $r;
	}

	/** Fields stored as rich text (wrapped in a paragraph). */
	private static function rich_fields() {
		$f = array( 'section_3_description', 'section_4_description', 'section_5_description', 'section_7_description' );
		for ( $i = 1; $i <= 10; $i++ ) {
			$f[] = "faq_{$i}_description";
			if ( $i <= 6 ) {
				$f[] = "section_5_box_{$i}_description";
			}
		}
		return $f;
	}

	/* ---------------- Small text helpers ---------------- */

	public static function words( $text ) {
		$text = trim( wp_strip_all_tags( (string) $text ) );
		return '' === $text ? 0 : count( preg_split( '/\s+/u', $text ) );
	}

	/** Fix characters that turned into "â€‘" on the old CSV import. */
	public static function clean( $text ) {
		$text = (string) $text;
		$text = str_replace(
			array( "\xE2\x80\x90", "\xE2\x80\x91", "\xE2\x80\x92", "\xC2\xA0", "\xE2\x80\x93", "\xE2\x80\x94", "\xE2\x80\x98", "\xE2\x80\x99", "\xE2\x80\x9C", "\xE2\x80\x9D" ),
			array( '-', '-', '-', ' ', ', ', ', ', "'", "'", '"', '"' ),
			$text
		);
		$text = preg_replace( '/\s+,/', ',', $text );
		$text = preg_replace( '/,\s*,/', ',', $text );
		return trim( preg_replace( '/[ \t]+/', ' ', $text ) );
	}

	/** First letter of every word capital, the rest untouched (keeps AC, LLC, FL). */
	public static function title_case( $text ) {
		return preg_replace_callback(
			'/(^|[\s\-\/(])([a-z])/u',
			static function ( $m ) {
				return $m[1] . strtoupper( $m[2] );
			},
			(string) $text
		);
	}

	public static function city( $location ) {
		$parts = explode( ',', (string) $location );
		return trim( $parts[0] );
	}

	/**
	 * Phone in tel:+1XXXXXXXXXX form.
	 *
	 * @param string $phone   Raw phone.
	 * @param string $country Country code without +.
	 */
	public static function tel( $phone, $country = '1' ) {
		$digits  = preg_replace( '/\D/', '', (string) $phone );
		$country = preg_replace( '/\D/', '', (string) $country );
		$country = '' === $country ? '1' : $country;
		if ( '' === $digits ) {
			return '';
		}
		if ( 0 === strpos( $digits, $country ) && strlen( $digits ) > 10 ) {
			return 'tel:+' . $digits;
		}
		return 'tel:+' . $country . ltrim( $digits, '0' );
	}

	/**
	 * Image for a service following the team's rules:
	 * one image given = used for every service; otherwise image N goes to service N, missing = empty.
	 *
	 * @param int[] $ids           Attachment IDs by service index.
	 * @param int   $service_index Service index.
	 */
	public static function pick_image( array $ids, $service_index ) {
		$filled = array_filter( array_map( 'absint', $ids ) );
		if ( 1 === count( $filled ) && isset( $filled[0] ) ) {
			return (int) $filled[0];
		}
		return isset( $ids[ $service_index ] ) ? absint( $ids[ $service_index ] ) : 0;
	}

	/** The 4 section photo fields, in the order photos are given to a page. */
	public static function photo_fields() {
		$all  = array( 'section_2_left_image', 'section_3_right_image', 'section_4_right_image', 'section_7_background_image' );
		$used = array_values( array_intersect( $all, SEOB_AP_Design::used_image_fields() ) );
		return $used ? $used : $all;
	}

	/**
	 * Different photos for every page: the images picked in the form for the service come first
	 * (so the first location shows exactly those), then the matching photos found for it.
	 * A column with only one image for all services stays the same on every page.
	 *
	 * @param array $job  Job settings.
	 * @param array $item Item.
	 * @return array field => attachment ID
	 */
	public static function page_images( array $job, array $item ) {
		$si    = (int) $item['service_index'];
		$fixed = array();
		$pool  = array();
		foreach ( self::photo_fields() as $field ) {
			$ids    = isset( $job['images'][ $field ] ) ? array_map( 'absint', (array) $job['images'][ $field ] ) : array();
			$filled = array_filter( $ids );
			if ( 1 === count( $filled ) && ! empty( $ids[0] ) && count( (array) $job['services'] ) > 1 ) {
				$fixed[ $field ] = (int) $ids[0];
				continue;
			}
			if ( ! empty( $ids[ $si ] ) ) {
				$pool[] = (int) $ids[ $si ];
			}
		}
		$extra = isset( $job['image_pool'][ $si ] ) ? array_map( 'absint', (array) $job['image_pool'][ $si ] ) : array();
		$open  = array_values( array_diff( self::photo_fields(), array_keys( $fixed ) ) );

		// Magnific: new photos for every page, unless images were picked by hand for this service.
		if ( ! $pool && isset( $job['image_source'] ) && 'magnific' === $job['image_source'] && $open ) {
			$svc  = isset( $job['services'][ $si ] ) ? (array) $job['services'][ $si ] : array( 'name' => $item['service'], 'keywords' => array() );
			$svc += array( 'name' => $item['service'], 'keywords' => array() );
			$base = sanitize_title( $item['service'] . ' in ' . $item['location'] );
			$got  = SEOB_AP_Images::magnific_for_page( $svc, (int) $item['location_index'], count( (array) $job['locations'] ), $base, count( $open ) );
			foreach ( $got as $id ) {
				update_post_meta( $id, '_seob_ap_page_only', 1 );
			}
			if ( count( $got ) < count( $open ) ) {
				// Not enough from Magnific: fill the rest from the Media Library.
				$more = SEOB_AP_Images::for_page( array_values( array_diff( $extra, $got ) ), (int) $item['location_index'], count( $open ) );
				$got  = array_values( array_unique( array_filter( array_merge( $got, $more ) ) ) );
			}
			$out = $fixed;
			foreach ( $open as $k => $field ) {
				$out[ $field ] = isset( $got[ $k ] ) ? (int) $got[ $k ] : 0;
			}
			return $out;
		}

		$pool  = array_values( array_unique( array_filter( array_merge( $pool, $extra ) ) ) );
		$set   = SEOB_AP_Images::for_page( $pool, (int) $item['location_index'], count( $open ) );
		$out   = $fixed;
		foreach ( $open as $k => $field ) {
			$out[ $field ] = isset( $set[ $k ] ) ? (int) $set[ $k ] : 0;
		}
		return $out;
	}

	/**
	 * Keyword plan for one page.
	 *
	 * @param array $service  name, keywords[].
	 * @param int   $loc_index Location index.
	 */
	public static function keywords_for( array $service, $loc_index ) {
		$kws = array_values( array_filter( array_map( 'trim', (array) $service['keywords'] ) ) );
		if ( ! $kws ) {
			return array( 'primary' => '', 'secondary' => array() );
		}
		$n       = count( $kws );
		$primary = $kws[ $loc_index % $n ];
		return array(
			'primary'   => $primary,
			'secondary' => array_values( array_diff( $kws, array( $primary ) ) ),
		);
	}

	/* ---------------- Prompt ---------------- */

	public static function system_prompt( $faq_count ) {
		$rules = self::word_rules( $faq_count );
		$lines = array();
		foreach ( $rules as $field => $r ) {
			$lines[] = "- {$field}: {$r[0]}-{$r[1]} words";
		}
		$keys = array_merge( array( 'primary_keyword', 'seo_title', 'meta_desc' ), array_keys( $rules ) );

		$prompt = <<<PROMPT
You write local SEO landing pages for service businesses. You write ONE page per request and answer with ONE JSON object only, no markdown, no comments.

CONTENT RULES
- Human-written, natural, specific to the service AND the location. Strong local SEO and strong E-E-A-T.
- 100% unique: no sentence may repeat inside the page, and do not reuse the headings listed under "Already used".
- No filler, no robotic phrasing, no keyword stuffing. Do not use em dashes or en dashes.
- Plain text only. No HTML, no markdown, no emojis.
- Use the given keywords (primary and secondary) naturally across seo_title, meta_desc, headings and descriptions. Never use another service's keywords.
- If no keywords are given, create one high-intent primary keyword from the service name with a strong adjective (do not use "reliable" or "expert") and return it in primary_keyword.

FIELD RULES
- seo_title: "{keyword} in {city} | {business}", 40-60 characters, must contain the keyword.
- meta_desc: "{business} offers {keyword} in {location}" plus 1-2 benefits, at most 155 characters.
- section_2_heading: service keyword + location + a strong adjective (never "professional", "reliable" or "expert"). Capitalize every word.
- section_3_heading: secondary keyword or benefit + location. Capitalize every word.
- section_4_heading: secondary keyword or benefit + worker certification theme + location. Capitalize every word. section_4_description is about certification, training and standards.
- section_2/3/4 descriptions: relevant to their heading, each includes one secondary keyword.
- Alt tags: describe the image, include service and location, 6-10 words.
- section_5: six boxes, each a different sub-service or benefit of this service.
- FAQ headings: real questions people in that location ask about this service, include the location.
- section_7: closing call to action.

WORD LIMITS (count carefully)
{LINES}

JSON KEYS (all required, string values):
{KEYS}
PROMPT;
		return str_replace( array( '{LINES}', '{KEYS}' ), array( implode( "\n", $lines ), implode( ', ', $keys ) ), $prompt );
	}

	/**
	 * User message for one page.
	 *
	 * @param array $job  Job settings.
	 * @param array $item service_index, location.
	 * @param array $used Headings already used for this service.
	 */
	public static function user_prompt( array $job, array $item, array $used ) {
		$service = $job['services'][ $item['service_index'] ];
		$kw      = self::keywords_for( $service, (int) $item['location_index'] );
		$data    = array(
			'business'           => $job['business'],
			'website'            => $job['website'],
			'service'            => $service['name'],
			'location'           => $item['location'],
			'city'               => self::city( $item['location'] ),
			'primary_keyword'    => $kw['primary'] ? $kw['primary'] : '(none given, create one)',
			'secondary_keywords' => $kw['secondary'],
			'all_services'       => wp_list_pluck( $job['services'], 'name' ),
			'notes'              => $job['notes'],
		);
		$msg = "Write the page for this combination:\n" . wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( $used ) {
			$msg .= "\n\nAlready used on other pages (do not repeat):\n- " . implode( "\n- ", array_slice( $used, -40 ) );
		}
		return $msg;
	}

	/* ---------------- Checking ---------------- */

	/**
	 * Clean the AI answer and list anything that breaks the rules.
	 *
	 * @param array $ai   Parsed answer.
	 * @param array $job  Job.
	 * @param array $item Item.
	 * @return array [clean data, problems[]]
	 */
	public static function check( array $ai, array $job, array $item ) {
		$faq   = (int) $job['faq_count'];
		$rules = self::word_rules( $faq );
		$city  = strtolower( self::city( $item['location'] ) );
		$out   = array();
		$prob  = array();

		foreach ( array_merge( array( 'primary_keyword', 'seo_title', 'meta_desc' ), array_keys( $rules ) ) as $key ) {
			$out[ $key ] = isset( $ai[ $key ] ) && is_scalar( $ai[ $key ] ) ? self::clean( wp_strip_all_tags( (string) $ai[ $key ] ) ) : '';
		}

		foreach ( $rules as $key => $r ) {
			if ( '' === $out[ $key ] ) {
				$prob[] = "{$key} is missing";
				continue;
			}
			$n   = self::words( $out[ $key ] );
			$min = (int) floor( $r[0] * 0.85 );
			$max = (int) ceil( $r[1] * 1.15 );
			if ( $n < $min || $n > $max ) {
				$prob[] = "{$key} has {$n} words, needs {$r[0]}-{$r[1]}";
			}
		}

		foreach ( array( 'section_2_heading', 'section_3_heading', 'section_4_heading' ) as $h ) {
			$out[ $h ] = self::title_case( $out[ $h ] );
			if ( '' !== $out[ $h ] && false === strpos( strtolower( $out[ $h ] ), $city ) ) {
				$prob[] = "{$h} must include the location";
			}
		}
		if ( preg_match( '/\b(professional|reliable|expert)\b/i', $out['section_2_heading'] ) ) {
			$prob[] = 'section_2_heading must not use professional, reliable or expert';
		}
		for ( $i = 1; $i <= $faq; $i++ ) {
			$h = "faq_{$i}_heading";
			if ( '' !== $out[ $h ] && false === strpos( strtolower( $out[ $h ] ), $city ) ) {
				$prob[] = "{$h} must include the location";
			}
		}
		$len = function_exists( 'mb_strlen' ) ? mb_strlen( $out['meta_desc'] ) : strlen( $out['meta_desc'] );
		if ( $len > 160 ) {
			$prob[] = "meta_desc is {$len} characters, max 155";
		}

		// Same sentence twice on one page.
		$seen = array();
		foreach ( $out as $key => $text ) {
			foreach ( preg_split( '/(?<=[.!?])\s+/', $text ) as $s ) {
				$s = strtolower( trim( $s ) );
				if ( self::words( $s ) < 6 ) {
					continue;
				}
				if ( isset( $seen[ $s ] ) && $seen[ $s ] !== $key ) {
					$prob[] = "{$key} repeats a sentence from {$seen[$s]}";
				}
				$seen[ $s ] = $key;
			}
		}

		return array( $out, array_values( array_unique( $prob ) ) );
	}

	/* ---------------- Final values ---------------- */

	/**
	 * Every field value for the page, with code rules applied over the AI text.
	 *
	 * @param array $ai   Checked AI data.
	 * @param array $job  Job.
	 * @param array $item Item.
	 * @return array field => value, plus _seo keys.
	 */
	public static function build( array $ai, array $job, array $item ) {
		$service = $job['services'][ $item['service_index'] ];
		$name    = $service['name'];
		$loc     = $item['location'];
		$city    = self::city( $loc );
		$kw      = self::keywords_for( $service, (int) $item['location_index'] );
		$primary = '' !== $kw['primary'] ? $kw['primary'] : ( '' !== $ai['primary_keyword'] ? $ai['primary_keyword'] : $name );

		$phones = array_values( array_filter( (array) $job['phones'] ) );
		$phone  = $phones ? $phones[ (int) $item['row_index'] % count( $phones ) ] : '';
		$tel    = self::tel( $phone, $job['country_code'] );
		$cta    = (string) $job['contact_url'];

		$v = array(
			'post_title'               => "{$name} in {$loc}",
			'page_heading'             => "Best {$name} in {$loc}",
			'section_2_call_button'    => $tel,
			'section_2_contact_button' => $cta,
			'section_3_call_button'    => $tel,
			'section_4_call_button'    => $tel,
			'section_4_contact_button' => $cta,
			'section_7_call_button'    => $tel,
			'section_7_contact_button' => $cta,
			'location'                 => $loc,
			'service'                  => $name,
		);

		foreach ( self::word_rules( (int) $job['faq_count'] ) as $key => $r ) {
			$text = $ai[ $key ];
			if ( in_array( $key, self::rich_fields(), true ) && '' !== $text ) {
				$text = '<p>' . esc_html( $text ) . '</p>';
			}
			$v[ $key ] = $text;
		}

		// Alt tags must name service and city; fall back to a safe sentence.
		foreach ( array( 'section_2_left_image_alt_tag', 'section_3_right_image_alt_tag', 'section_4_right_image_alt_tag' ) as $i => $alt ) {
			$n = self::words( $v[ $alt ] );
			if ( $n < 5 || $n > 12 || false === stripos( $v[ $alt ], $city ) ) {
				$variants  = array( "{$name} work completed for a client in {$city}", "Our team providing {$name} services in {$city}", "Finished {$name} project for a property in {$city}" );
				$v[ $alt ] = $variants[ $i ];
			}
		}

		// Elementor layout fields that mirror section 5.
		$v['section_5_heading'] = $v['section_5_main_heading'];

		// Images.
		foreach ( array_keys( SEOB_AP_Schema::image_fields() ) as $field ) {
			$ids         = isset( $job['images'][ $field ] ) ? (array) $job['images'][ $field ] : array();
			$img         = self::pick_image( $ids, (int) $item['service_index'] );
			$v[ $field ] = $img ? $img : '';
		}
		if ( isset( $job['image_mode'] ) && 'page' === $job['image_mode'] ) {
			foreach ( self::page_images( $job, $item ) as $field => $id ) {
				$v[ $field ] = $id ? $id : '';
			}
		}
		$v['section_5_right_image'] = $v['section_5_same_image'];

		// SEO title and description (Rank Math 85+ calibrated).
		$title = $ai['seo_title'];
		$tlen  = function_exists( 'mb_strlen' ) ? mb_strlen( $title ) : strlen( $title );
		if ( '' === $title || $tlen < 45 || $tlen > 65 || false === stripos( $title, $city ) ) {
			$title = SEOB_RankMath_Bridge::build_calibrated_title( $primary, $city, $job['business'] );
		}
		$meta = $ai['meta_desc'];
		$mlen = function_exists( 'mb_strlen' ) ? mb_strlen( $meta ) : strlen( $meta );
		if ( '' === $meta || $mlen < 140 || $mlen > 165 ) {
			$meta = SEOB_RankMath_Bridge::build_calibrated_desc( $primary, $name, $loc, $phone );
		}

		$v['seo_title']       = $title;
		$v['meta_desc']       = $meta;
		$v['primary_keyword'] = $primary;
		return $v;
	}

	/**
	 * Cut text to a length at a sentence or word end.
	 *
	 * @param string $text Text.
	 * @param int    $max  Max chars.
	 */
	public static function trim_chars( $text, $max ) {
		if ( strlen( $text ) <= $max ) {
			return $text;
		}
		$cut  = substr( $text, 0, $max );
		$stop = max( strrpos( $cut, '. ' ) ?: 0, strrpos( $cut, '! ' ) ?: 0 );
		if ( $stop > $max * 0.6 ) {
			return substr( $cut, 0, $stop + 1 );
		}
		return rtrim( substr( $cut, 0, strrpos( $cut, ' ' ) ), ' ,;:' ) . '.';
	}

	/* ---------------- One AI call per request ---------------- */

	/**
	 * Make ONE AI call for a page. Short requests survive hosts that stop long PHP requests.
	 * The state (best answer so far, problems to fix) is kept between calls by the job runner.
	 *
	 * @param array $job   Job.
	 * @param array $item  Item.
	 * @param array $used  Headings already used.
	 * @param array $state State from the previous call ([] the first time).
	 * @return array|WP_Error ['done' => bool, 'state' => [], 'values' => [], 'warnings' => []]
	 */
	public static function attempt( array $job, array $item, array $used, array $state ) {
		$state = wp_parse_args(
			$state,
			array(
				'tries'      => 0,
				'best'       => null,
				'best_p'     => array(),
				'last'       => null,
				'problems'   => array(),
				'json_fail'  => false,
			)
		);
		$max = (int) apply_filters( 'seo_boster_area_attempts', 2 );

		$user = self::user_prompt( $job, $item, $used );
		if ( $state['last'] && $state['problems'] ) {
			$state['problems'] = self::hard( $state['problems'] );
			$user .= "\n\nYour previous answer:\n" . wp_json_encode( $state['last'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
				. "\n\nFix ONLY these problems and return the full corrected JSON:\n- " . implode( "\n- ", $state['problems'] );
		} elseif ( $state['json_fail'] ) {
			$user .= "\n\nYour last answer was not valid JSON. Answer with one JSON object only.";
		}

		$answer = SEOB_AP_AI::complete( self::system_prompt( (int) $job['faq_count'] ), $user, 16000 );
		if ( is_wp_error( $answer ) ) {
			return $answer; // The job runner decides whether to retry.
		}

		++$state['tries'];
		$json = SEOB_AP_AI::parse_json( $answer );
		if ( ! $json ) {
			$state['json_fail'] = true;
			$state['last']      = null;
			$state['problems']  = array();
		} else {
			$state['json_fail']       = false;
			list( $clean, $problems ) = self::check( $json, $job, $item );
			if ( null === $state['best'] || count( $problems ) < count( $state['best_p'] ) ) {
				$state['best']   = $clean;
				$state['best_p'] = $problems;
			}
			$state['last']     = $clean;
			$state['problems'] = $problems;
			// Small things (a few words over or under, a long meta description) are noted, not rewritten:
			// asking again only for real problems keeps pages fast.
			if ( ! self::hard( $problems ) ) {
				return self::finish( $state, $job, $item );
			}
		}

		if ( $state['tries'] >= $max ) {
			if ( null === $state['best'] ) {
				return new WP_Error( 'seob_ai_json', __( 'The AI answer was not valid JSON.', 'seo-boster' ) );
			}
			return self::finish( $state, $job, $item );
		}
		return array( 'done' => false, 'state' => $state );
	}

	/**
	 * Problems worth a second AI call.
	 *
	 * @param string[] $problems Problems.
	 */
	public static function hard( array $problems ) {
		return array_values(
			array_filter(
				$problems,
				static function ( $p ) {
					if ( false !== strpos( $p, ' words, needs ' ) ) {
						// Only far-off word counts matter (half or less of the minimum).
						if ( preg_match( '/has (\d+) words, needs (\d+)-/', $p, $m ) ) {
							return (int) $m[1] < (int) $m[2] * 0.5;
						}
						return false;
					}
					return false === strpos( $p, 'meta_desc is' );
				}
			)
		);
	}

	private static function finish( array $state, array $job, array $item ) {
		return array(
			'done'     => true,
			'state'    => $state,
			'values'   => self::build( $state['best'], $job, $item ),
			'warnings' => $state['best_p'],
		);
	}

	/**
	 * Whole page in one go (calls attempt() until done). Kept for code that wants a single call.
	 *
	 * @param array $job  Job.
	 * @param array $item Item.
	 * @param array $used Headings already used.
	 * @return array|WP_Error
	 */
	public static function write( array $job, array $item, array $used ) {
		$state = array();
		for ( $i = 0; $i < 10; $i++ ) {
			$r = self::attempt( $job, $item, $used, $state );
			if ( is_wp_error( $r ) || $r['done'] ) {
				return $r;
			}
			$state = $r['state'];
		}
		return new WP_Error( 'seob_ai_failed', __( 'Could not get a usable answer.', 'seo-boster' ) );
	}
}
