<?php
/**
 * SEMrush Bulk CSV Drop-In & Keyword Clustering Engine.
 *
 * Generates zero-cost SEMrush bulk query strings, parses exported CSVs,
 * and clusters keywords by Service and Location tokens based on Search Volume
 * and Keyword Difficulty (KD%).
 *
 * @package SEO_Boster
 */

defined( 'ABSPATH' ) || exit;

class SEOB_CSV_Parser {

	/**
	 * Generate a formatted list of search query strings for SEMrush Bulk Analysis.
	 *
	 * Format: "{Service} {City}"
	 *
	 * @param array $services  List of service names.
	 * @param array $locations List of target locations.
	 * @return string[]
	 */
	public static function generate_bulk_queries( array $services, array $locations ) {
		$queries = array();
		foreach ( $services as $service ) {
			$service = trim( (string) $service );
			if ( '' === $service ) {
				continue;
			}
			foreach ( $locations as $loc ) {
				$loc = trim( (string) $loc );
				if ( '' === $loc ) {
					continue;
				}
				$city = trim( strtok( $loc, ',' ) );
				$queries[] = sprintf( '%s %s', $service, $city );
			}
		}
		return array_unique( $queries );
	}

	/**
	 * Parse a raw SEMrush CSV string or file into structured rows.
	 *
	 * Detects delimiter (, or ; or \t) and standard SEMrush column headers:
	 * Keyword, Search Volume, Keyword Difficulty, Intent, CPC, etc.
	 *
	 * @param string $csv_content Raw CSV string or path.
	 * @return array[] List of associative arrays with normalized keys.
	 */
	public static function parse_semrush_csv( $csv_content ) {
		if ( file_exists( $csv_content ) && is_readable( $csv_content ) ) {
			$csv_content = (string) file_get_contents( $csv_content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		}

		// Strip UTF-8 BOM if present
		if ( 0 === strncmp( $csv_content, "\xEF\xBB\xBF", 3 ) ) {
			$csv_content = substr( $csv_content, 3 );
		}

		$lines = preg_split( '/\r\n|\r|\n/', trim( $csv_content ) );
		if ( empty( $lines ) ) {
			return array();
		}

		// Detect delimiter from first non-empty line
		$first_line = $lines[0];
		$delim      = ',';
		if ( substr_count( $first_line, ';' ) > substr_count( $first_line, ',' ) ) {
			$delim = ';';
		} elseif ( substr_count( $first_line, "\t" ) > substr_count( $first_line, ',' ) ) {
			$delim = "\t";
		}

		$stream = fopen( 'php://temp', 'r+' );
		fwrite( $stream, $csv_content );
		rewind( $stream );

		$header = fgetcsv( $stream, 0, $delim );
		if ( ! is_array( $header ) ) {
			fclose( $stream );
			return array();
		}

		// Normalize header names
		$col_map = array();
		foreach ( $header as $idx => $col ) {
			$col_clean = strtolower( trim( preg_replace( '/[^A-Za-z0-9]/', '', (string) $col ) ) );
			if ( false !== strpos( $col_clean, 'keyword' ) && false === strpos( $col_clean, 'difficulty' ) ) {
				$col_map['keyword'] = $idx;
			} elseif ( false !== strpos( $col_clean, 'volume' ) || 'sv' === $col_clean ) {
				$col_map['volume'] = $idx;
			} elseif ( false !== strpos( $col_clean, 'difficulty' ) || 'kd' === $col_clean ) {
				$col_map['kd'] = $idx;
			} elseif ( false !== strpos( $col_clean, 'intent' ) ) {
				$col_map['intent'] = $idx;
			}
		}

		if ( ! isset( $col_map['keyword'] ) ) {
			$col_map['keyword'] = 0;
		}

		$rows = array();
		while ( ( $row = fgetcsv( $stream, 0, $delim ) ) !== false ) {
			if ( empty( $row ) || ! isset( $row[ $col_map['keyword'] ] ) ) {
				continue;
			}
			$kw = trim( (string) $row[ $col_map['keyword'] ] );
			if ( '' === $kw ) {
				continue;
			}

			$volume = isset( $col_map['volume'], $row[ $col_map['volume'] ] ) ? (int) preg_replace( '/\D/', '', $row[ $col_map['volume'] ] ) : 0;
			$kd     = isset( $col_map['kd'], $row[ $col_map['kd'] ] ) ? (float) preg_replace( '/[^0-9.]/', '', $row[ $col_map['kd'] ] ) : 0.0;
			$intent = isset( $col_map['intent'], $row[ $col_map['intent'] ] ) ? sanitize_text_field( $row[ $col_map['intent'] ] ) : '';

			$rows[] = array(
				'keyword' => $kw,
				'volume'  => $volume,
				'kd'      => $kd,
				'intent'  => $intent,
			);
		}
		fclose( $stream );

		return $rows;
	}

	/**
	 * Cluster parsed SEMrush keywords to Service x Location combinations.
	 *
	 * Selects Primary Keyword (Highest Volume, lowest KD%) and Secondary LSI keywords.
	 *
	 * @param array $rows      Parsed CSV rows.
	 * @param array $services  List of service strings.
	 * @param array $locations List of location strings.
	 * @return array Keyed by "{service}|{location}".
	 */
	public static function cluster_keywords( array $rows, array $services, array $locations ) {
		$clusters = array();

		foreach ( $services as $service ) {
			$svc_clean = strtolower( trim( (string) $service ) );
			$svc_words = preg_split( '/\s+/', $svc_clean );

			foreach ( $locations as $location ) {
				$loc_clean = strtolower( trim( (string) $location ) );
				$city      = trim( strtok( $loc_clean, ',' ) );
				$combo_key = $service . '|' . $location;

				$matches = array();

				foreach ( $rows as $row ) {
					$kw_lower = strtolower( $row['keyword'] );

					// Must match city token
					if ( false === strpos( $kw_lower, $city ) ) {
						continue;
					}

					// Must match at least one significant service word
					$svc_match = false;
					foreach ( $svc_words as $w ) {
						if ( mb_strlen( $w ) >= 3 && false !== strpos( $kw_lower, $w ) ) {
							$svc_match = true;
							break;
						}
					}

					if ( $svc_match ) {
						$matches[] = $row;
					}
				}

				// Sort matches: Highest volume first, then lowest KD
				usort(
					$matches,
					static function ( $a, $b ) {
						if ( $a['volume'] === $b['volume'] ) {
							return ( $a['kd'] < $b['kd'] ) ? -1 : 1;
						}
						return ( $a['volume'] > $b['volume'] ) ? -1 : 1;
					}
				);

				$primary   = ! empty( $matches[0] ) ? $matches[0]['keyword'] : sprintf( '%s %s', $service, $city );
				$volume    = ! empty( $matches[0] ) ? $matches[0]['volume'] : 0;
				$kd        = ! empty( $matches[0] ) ? $matches[0]['kd'] : 0;
				$secondary = array();

				for ( $i = 1; $i < count( $matches ) && $i <= 4; $i++ ) {
					$secondary[] = $matches[ $i ]['keyword'];
				}

				$clusters[ $combo_key ] = array(
					'service'             => $service,
					'location'            => $location,
					'city'                => $city,
					'primary_keyword'     => $primary,
					'volume'              => $volume,
					'kd'                  => $kd,
					'secondary_keywords'  => $secondary,
					'total_matches_found' => count( $matches ),
				);
			}
		}

		return $clusters;
	}
}
