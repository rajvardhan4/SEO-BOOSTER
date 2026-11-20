<?php
/**
 * AI client for any provider (Claude, OpenAI, Gemini, DeepSeek, OpenRouter, Groq, Mistral, xAI,
 * or any OpenAI-compatible API) and safe API key storage.
 *
 * Keys are stored encrypted with the site's own secret keys (one per provider), are never printed
 * back into any page, and are only sent to that provider's API address.
 *
 * @package SEO_Boster
 */

defined( 'ABSPATH' ) || exit;

class SEOB_AP_AI {

	const OPTION = 'seob_ap_ai';

	/**
	 * Providers. "api" = anthropic (Claude Messages API) or openai (OpenAI-compatible chat completions).
	 * fast / best = default models for the two writing speeds. Empty = picked from the provider's model list.
	 * pick = model name patterns to look for (best first) when the default is not available.
	 */
	public static function providers() {
		$list = array(
			'anthropic'  => array(
				'name' => 'Claude (Anthropic)',
				'api'  => 'anthropic',
				'url'  => 'https://api.anthropic.com/v1',
				'fast' => 'claude-haiku-5-5',
				'best' => 'claude-sonnet-5-5',
				'pick' => array( 'sonnet', 'haiku', 'opus', 'claude' ),
				'keys' => 'console.anthropic.com',
			),
			'openai'     => array(
				'name' => 'OpenAI (ChatGPT)',
				'api'  => 'openai',
				'url'  => 'https://api.openai.com/v1',
				'fast' => 'gpt-4.1-mini',
				'best' => 'gpt-4.1',
				'pick' => array( 'gpt-5-mini', 'gpt-5', 'gpt-4.1-mini', 'gpt-4.1', 'gpt-4o-mini', 'gpt-4o' ),
				'keys' => 'platform.openai.com/api-keys',
			),
			'gemini'     => array(
				'name' => 'Google Gemini',
				'api'  => 'openai',
				'url'  => 'https://generativelanguage.googleapis.com/v1beta/openai',
				'fast' => 'gemini-2.5-flash',
				'best' => 'gemini-2.5-pro',
				'pick' => array( 'gemini-3-flash', 'gemini-3-pro', 'gemini-2.5-flash', 'gemini-2.5-pro', 'flash', 'gemini' ),
				'keys' => 'aistudio.google.com/apikey',
			),
			'deepseek'   => array(
				'name' => 'DeepSeek',
				'api'  => 'openai',
				'url'  => 'https://api.deepseek.com',
				'fast' => 'deepseek-chat',
				'best' => 'deepseek-chat',
				'pick' => array( 'deepseek-chat', 'deepseek' ),
				'keys' => 'platform.deepseek.com/api_keys',
			),
			'openrouter' => array(
				'name' => 'OpenRouter (any model: Claude, GPT, Gemini, Llama, Qwen...)',
				'api'  => 'openai',
				'url'  => 'https://openrouter.ai/api/v1',
				'fast' => 'openrouter/auto',
				'best' => 'openrouter/auto',
				'pick' => array( 'openrouter/auto' ),
				'keys' => 'openrouter.ai/keys',
			),
			'groq'       => array(
				'name' => 'Groq (very fast)',
				'api'  => 'openai',
				'url'  => 'https://api.groq.com/openai/v1',
				'fast' => 'llama-3.3-70b-versatile',
				'best' => 'llama-3.3-70b-versatile',
				'pick' => array( 'gpt-oss-120b', 'llama-3.3-70b', '70b', 'llama' ),
				'keys' => 'console.groq.com/keys',
			),
			'mistral'    => array(
				'name' => 'Mistral',
				'api'  => 'openai',
				'url'  => 'https://api.mistral.ai/v1',
				'fast' => 'mistral-small-latest',
				'best' => 'mistral-large-latest',
				'pick' => array( 'mistral-large', 'mistral-medium', 'mistral-small' ),
				'keys' => 'console.mistral.ai/api-keys',
			),
			'xai'        => array(
				'name' => 'xAI (Grok)',
				'api'  => 'openai',
				'url'  => 'https://api.x.ai/v1',
				'fast' => '',
				'best' => '',
				'pick' => array( 'grok-4-fast', 'grok-4', 'grok-3-mini', 'grok-3', 'grok' ),
				'keys' => 'console.x.ai',
			),
			'custom'     => array(
				'name' => 'Other / custom (any OpenAI-compatible API)',
				'api'  => 'openai',
				'url'  => '',
				'fast' => '',
				'best' => '',
				'pick' => array(),
				'keys' => '',
			),
		);
		/**
		 * Add or change AI providers.
		 *
		 * @param array $list Providers.
		 */
		return apply_filters( 'seo_boster_ai_providers', $list );
	}

	/* ---------------- Settings ---------------- */

	public static function settings() {
		$s = get_option( self::OPTION, array() );
		$s = is_array( $s ) ? $s : array();
		$s = wp_parse_args(
			$s,
			array(
				'provider'   => 'anthropic',
				'model'      => '',
				'key'        => '',
				'last4'      => '',
				'speed'      => 'fast',
				'keys'       => array(),
				'base_url'   => '',
				'auto_model' => array(),
			)
		);
		// Older versions kept one key: it belongs to the provider saved with it.
		if ( '' !== $s['key'] && empty( $s['keys'] ) ) {
			$s['keys'] = array(
				$s['provider'] => array(
					'key'   => $s['key'],
					'last4' => $s['last4'],
				),
			);
		}
		$s['keys']       = is_array( $s['keys'] ) ? $s['keys'] : array();
		$s['auto_model'] = is_array( $s['auto_model'] ) ? $s['auto_model'] : array();
		$cur             = isset( $s['keys'][ $s['provider'] ] ) ? $s['keys'][ $s['provider'] ] : array();
		$s['key']        = isset( $cur['key'] ) ? (string) $cur['key'] : '';
		$s['last4']      = isset( $cur['last4'] ) ? (string) $cur['last4'] : '';
		return $s;
	}

	public static function provider() {
		$p = self::settings()['provider'];
		return isset( self::providers()[ $p ] ) ? $p : 'anthropic';
	}

	public static function info( $provider = null ) {
		$all = self::providers();
		$p   = null === $provider ? self::provider() : $provider;
		return isset( $all[ $p ] ) ? $all[ $p ] : $all['anthropic'];
	}

	/** API base address of the current provider (no trailing slash). */
	public static function base_url() {
		$info = self::info();
		$url  = 'custom' === self::provider() ? (string) self::settings()['base_url'] : (string) $info['url'];
		$url  = untrailingslashit( preg_replace( '#/(chat/completions|messages)/?$#', '', trim( $url ) ) );
		return $url;
	}

	public static function model() {
		$s = self::settings();
		$m = trim( (string) $s['model'] );
		if ( '' !== $m ) {
			return $m;
		}
		$p = self::provider();
		if ( ! empty( $s['auto_model'][ $p ] ) ) {
			return (string) $s['auto_model'][ $p ];
		}
		$info = self::info();
		return (string) ( 'fast' === $s['speed'] ? $info['fast'] : $info['best'] );
	}

	public static function has_key() {
		return '' !== self::settings()['key'];
	}

	/** Providers that have a saved key (to show in the form). */
	public static function saved_keys() {
		$out = array();
		foreach ( self::settings()['keys'] as $p => $k ) {
			if ( ! empty( $k['key'] ) ) {
				$out[ $p ] = isset( $k['last4'] ) ? (string) $k['last4'] : '';
			}
		}
		return $out;
	}

	/**
	 * Save settings. An empty key keeps the provider's existing key.
	 *
	 * @param string      $provider Provider ID.
	 * @param string      $model    Model name ('' = automatic).
	 * @param string      $key      New API key or ''.
	 * @param string|null $speed    fast|balanced.
	 * @param string|null $base_url Custom provider address.
	 */
	public static function save( $provider, $model, $key, $speed = null, $base_url = null ) {
		$s = self::settings();
		if ( null !== $speed ) {
			$s['speed'] = 'fast' === $speed ? 'fast' : 'balanced';
		}
		$old           = $s['provider'];
		$s['provider'] = isset( self::providers()[ $provider ] ) ? $provider : 'anthropic';
		$s['model']    = preg_replace( '/[^A-Za-z0-9._:\/@\-]/', '', (string) $model );
		if ( null !== $base_url ) {
			$url           = esc_url_raw( trim( (string) $base_url ), array( 'https', 'http' ) );
			$s['base_url'] = $url;
		}
		$key = trim( (string) $key );
		if ( '' !== $key ) {
			$s['keys'][ $s['provider'] ] = array(
				'key'   => self::encrypt( $key ),
				'last4' => substr( $key, -4 ),
			);
			unset( $s['auto_model'][ $s['provider'] ] );
		}
		if ( $old !== $s['provider'] || null !== $speed ) {
			unset( $s['auto_model'][ $s['provider'] ] );
		}
		unset( $s['key'], $s['last4'] );
		update_option( self::OPTION, $s, false );
	}

	public static function forget_key() {
		$s = self::settings();
		unset( $s['keys'][ $s['provider'] ], $s['auto_model'][ $s['provider'] ], $s['key'], $s['last4'] );
		update_option( self::OPTION, $s, false );
	}

	private static function remember_model( $model ) {
		$s                                  = self::settings();
		$s['auto_model'][ self::provider() ] = $model;
		unset( $s['key'], $s['last4'] );
		update_option( self::OPTION, $s, false );
	}

	/* ---------------- Encryption ---------------- */

	private static function secret() {
		$material = ( defined( 'AUTH_KEY' ) ? AUTH_KEY : '' ) . ( defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : '' ) . ( defined( 'NONCE_SALT' ) ? NONCE_SALT : '' );
		if ( '' === $material ) {
			$material = wp_salt( 'auth' );
		}
		return hash( 'sha256', 'seo-boster|' . $material, true );
	}

	public static function encrypt( $plain ) {
		if ( function_exists( 'openssl_encrypt' ) ) {
			$iv  = random_bytes( 16 );
			$enc = openssl_encrypt( $plain, 'aes-256-cbc', self::secret(), OPENSSL_RAW_DATA, $iv );
			$mac = hash_hmac( 'sha256', $iv . $enc, self::secret(), true );
			return 'v1:' . base64_encode( $iv . $mac . $enc ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		}
		return 'v0:' . base64_encode( $plain ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	public static function decrypt( $stored ) {
		$stored = (string) $stored;
		if ( 0 === strpos( $stored, 'v1:' ) && function_exists( 'openssl_decrypt' ) ) {
			$raw = base64_decode( substr( $stored, 3 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			if ( false === $raw || strlen( $raw ) < 49 ) {
				return '';
			}
			$iv  = substr( $raw, 0, 16 );
			$mac = substr( $raw, 16, 32 );
			$enc = substr( $raw, 48 );
			if ( ! hash_equals( $mac, hash_hmac( 'sha256', $iv . $enc, self::secret(), true ) ) ) {
				return ''; // Site keys changed or data was tampered with.
			}
			$plain = openssl_decrypt( $enc, 'aes-256-cbc', self::secret(), OPENSSL_RAW_DATA, $iv );
			return false === $plain ? '' : $plain;
		}
		if ( 0 === strpos( $stored, 'v0:' ) ) {
			$plain = base64_decode( substr( $stored, 3 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			return false === $plain ? '' : $plain;
		}
		return '';
	}

	/* ---------------- Calls ---------------- */

	private static function post( $url, array $headers, array $body ) {
		$args = array(
			'timeout'     => 120,
			'headers'     => $headers,
			'body'        => wp_json_encode( $body ),
			'data_format' => 'body',
		);
		// A custom address typed by an admin must not reach the server's own network.
		$response = 'custom' === self::provider() ? wp_safe_remote_post( $url, $args ) : wp_remote_post( $url, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		return array(
			'code' => (int) wp_remote_retrieve_response_code( $response ),
			'data' => is_array( $data ) ? $data : array(),
		);
	}

	private static function error_text( array $data, $code ) {
		if ( isset( $data['error']['message'] ) ) {
			return (string) $data['error']['message'];
		}
		if ( isset( $data[0]['error']['message'] ) ) {
			return (string) $data[0]['error']['message'];
		}
		if ( isset( $data['error'] ) && is_string( $data['error'] ) ) {
			return $data['error'];
		}
		if ( isset( $data['message'] ) && is_string( $data['message'] ) ) {
			return $data['message'];
		}
		return 'HTTP ' . $code;
	}

	/**
	 * Send one request and return the text answer.
	 *
	 * @param string $system System instructions.
	 * @param string $user   User message.
	 * @param int    $max    Max output tokens.
	 * @param bool   $retry  Internal: allowed to pick another model once.
	 * @return string|WP_Error
	 */
	public static function complete( $system, $user, $max = 4096, $retry = true ) {
		$key = self::decrypt( self::settings()['key'] );
		if ( '' === $key ) {
			return new WP_Error( 'seob_no_key', __( 'No API key saved for this AI provider. Add it in Area Pages > AI & images.', 'seo-boster' ) );
		}
		$base = self::base_url();
		$parts = wp_parse_url( $base );
		if ( '' === $base || empty( $parts['host'] ) || ! in_array( isset( $parts['scheme'] ) ? $parts['scheme'] : '', array( 'https', 'http' ), true ) ) {
			return new WP_Error( 'seob_no_url', __( 'Add the API address of your AI provider (AI & images tab).', 'seo-boster' ) );
		}
		$model = self::model();
		if ( '' === $model ) {
			$model = self::auto_pick_model();
			if ( is_wp_error( $model ) ) {
				return $model;
			}
		}
		$api = self::info()['api'];

		if ( 'anthropic' === $api ) {
			$url     = $base . '/messages';
			$headers = array(
				'x-api-key'         => $key,
				'anthropic-version' => '2023-06-01',
				'Content-Type'      => 'application/json',
			);
			$body    = array(
				'model'      => $model,
				'max_tokens' => $max,
				'system'     => $system,
				'messages'   => array( array( 'role' => 'user', 'content' => $user ) ),
				// The page rules are given in full, so no extended thinking is needed; it would use up the token budget.
				'thinking'   => array( 'type' => 'disabled' ),
			);
		} else {
			$url     = $base . '/chat/completions';
			$headers = array(
				'Authorization' => 'Bearer ' . $key,
				'Content-Type'  => 'application/json',
			);
			if ( 'openrouter' === self::provider() ) {
				$headers['HTTP-Referer'] = home_url( '/' );
				$headers['X-Title']      = 'SEO Boster';
			}
			$body = array(
				'model'           => $model,
				'response_format' => array( 'type' => 'json_object' ),
				'messages'        => array(
					array( 'role' => 'system', 'content' => $system ),
					array( 'role' => 'user', 'content' => $user ),
				),
			);
			// OpenAI's newer models want max_completion_tokens; every other provider uses max_tokens.
			$body[ 'openai' === self::provider() ? 'max_completion_tokens' : 'max_tokens' ] = $max;
		}

		// Send, and adjust the request when a provider or model does not accept a setting.
		for ( $try = 0; $try < 5; $try++ ) {
			$r = self::post( $url, $headers, $body );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
			$code = $r['code'];
			$data = $r['data'];
			if ( $code >= 200 && $code < 300 ) {
				break;
			}
			$msg = strtolower( self::error_text( $data, $code ) );
			if ( 400 === $code || 422 === $code ) {
				if ( isset( $body['thinking'] ) && false !== strpos( $msg, 'thinking' ) ) {
					unset( $body['thinking'] );
					continue;
				}
				if ( isset( $body['response_format'] ) && ( false !== strpos( $msg, 'response_format' ) || false !== strpos( $msg, 'json' ) ) ) {
					unset( $body['response_format'] );
					continue;
				}
				if ( isset( $body['max_completion_tokens'] ) && false !== strpos( $msg, 'max_completion_tokens' ) ) {
					$body['max_tokens'] = $body['max_completion_tokens'];
					unset( $body['max_completion_tokens'] );
					continue;
				}
				if ( isset( $body['max_tokens'] ) && 'anthropic' !== $api && false !== strpos( $msg, 'max_tokens' ) && false !== strpos( $msg, 'max_completion_tokens' ) ) {
					$body['max_completion_tokens'] = $body['max_tokens'];
					unset( $body['max_tokens'] );
					continue;
				}
				// Output limit too high for this model: try a smaller one.
				$limit = isset( $body['max_tokens'] ) ? 'max_tokens' : ( isset( $body['max_completion_tokens'] ) ? 'max_completion_tokens' : '' );
				if ( $limit && $body[ $limit ] > 8192 && preg_match( '/token|max|limit|range|exceed/', $msg ) ) {
					$body[ $limit ] = 8192;
					continue;
				}
			}
			// The model name is not available for this key: pick one from the provider's list, once.
			if ( $retry && '' === trim( (string) self::settings()['model'] ) && ( 404 === $code || preg_match( '/model.{0,40}(not.?found|does not exist|not exist|invalid|unknown|not available|not supported|deprecated|decommission)|(invalid|unknown|no such).{0,20}model/', $msg ) ) ) {
				$picked = self::auto_pick_model( $model );
				if ( ! is_wp_error( $picked ) && $picked !== $model ) {
					return self::complete( $system, $user, $max, false );
				}
			}
			return new WP_Error( 'seob_ai_http', sanitize_text_field( self::error_text( $data, $code ) ), array( 'status' => $code ) );
		}
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'seob_ai_http', sanitize_text_field( self::error_text( $data, $code ) ), array( 'status' => $code ) );
		}

		if ( 'anthropic' === $api ) {
			$text = '';
			foreach ( (array) ( isset( $data['content'] ) ? $data['content'] : array() ) as $part ) {
				if ( isset( $part['type'], $part['text'] ) && 'text' === $part['type'] ) {
					$text .= $part['text'];
				}
			}
			$why = ( isset( $data['stop_reason'] ) ? (string) $data['stop_reason'] : '' ) . ' / ' . implode( ',', wp_list_pluck( (array) ( isset( $data['content'] ) ? $data['content'] : array() ), 'type' ) );
		} else {
			$content = isset( $data['choices'][0]['message']['content'] ) ? $data['choices'][0]['message']['content'] : '';
			if ( is_array( $content ) ) { // Some providers send a list of parts.
				$content = implode( '', wp_list_pluck( array_filter( $content, 'is_array' ), 'text' ) );
			}
			$text = (string) $content;
			$why  = isset( $data['choices'][0]['finish_reason'] ) ? (string) $data['choices'][0]['finish_reason'] : '';
		}
		// Reasoning models may wrap their thoughts in <think> tags before the answer.
		$text = trim( preg_replace( '#<think>.*?</think>#s', '', $text ) );
		if ( '' === $text ) {
			/* translators: %s: reason from the AI provider */
			return new WP_Error( 'seob_ai_empty', sprintf( __( 'The AI returned an empty answer (%s).', 'seo-boster' ), sanitize_text_field( $why ) ) );
		}
		return $text;
	}

	/**
	 * Models this key can use, from the provider's own list.
	 *
	 * @return string[]|WP_Error
	 */
	public static function list_models() {
		$key  = self::decrypt( self::settings()['key'] );
		$base = self::base_url();
		if ( '' === $key ) {
			return new WP_Error( 'seob_no_key', __( 'Save the API key first.', 'seo-boster' ) );
		}
		if ( '' === $base ) {
			return new WP_Error( 'seob_no_url', __( 'Add the API address first.', 'seo-boster' ) );
		}
		$headers = 'anthropic' === self::info()['api']
			? array( 'x-api-key' => $key, 'anthropic-version' => '2023-06-01' )
			: array( 'Authorization' => 'Bearer ' . $key );
		$args     = array( 'timeout' => 20, 'headers' => $headers );
		$url      = $base . '/models' . ( 'anthropic' === self::info()['api'] ? '?limit=100' : '' );
		$response = 'custom' === self::provider() ? wp_safe_remote_get( $url, $args ) : wp_remote_get( $url, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$data = is_array( $data ) ? $data : array();
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'seob_ai_models', sanitize_text_field( self::error_text( $data, $code ) ) );
		}
		$rows = isset( $data['data'] ) ? $data['data'] : ( isset( $data['models'] ) ? $data['models'] : $data );
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$id = is_array( $row ) ? ( isset( $row['id'] ) ? $row['id'] : ( isset( $row['name'] ) ? $row['name'] : '' ) ) : $row;
			$id = preg_replace( '#^models/#', '', (string) $id );
			// Only models that write text.
			if ( '' !== $id && ! preg_match( '/embed|whisper|tts|dall-e|image|audio|realtime|moderation|transcribe|speech|vision-preview|guard|rerank|ocr|veo|imagen/i', $id ) ) {
				$out[] = sanitize_text_field( $id );
			}
		}
		$out = array_values( array_unique( $out ) );
		return $out;
	}

	/**
	 * Choose a good model from the provider's list and remember it.
	 *
	 * @param string $not Model that just failed.
	 * @return string|WP_Error
	 */
	public static function auto_pick_model( $not = '' ) {
		$models = self::list_models();
		if ( is_wp_error( $models ) ) {
			return $models;
		}
		$models = array_values( array_diff( $models, array( $not ) ) );
		if ( ! $models ) {
			return new WP_Error( 'seob_ai_models', __( 'This key has no text models. Type a model name in the Model box.', 'seo-boster' ) );
		}
		$fast  = 'fast' === self::settings()['speed'];
		$pick  = '';
		foreach ( (array) self::info()['pick'] as $pattern ) {
			$found = array_values(
				array_filter(
					$models,
					static function ( $m ) use ( $pattern ) {
						return false !== stripos( $m, $pattern ) && ! preg_match( '/preview|exp|beta|search|audio|-(\d{4})-(\d{2})-(\d{2})$/i', $m );
					}
				)
			);
			if ( ! $found ) {
				$found = array_values(
					array_filter(
						$models,
						static function ( $m ) use ( $pattern ) {
							return false !== stripos( $m, $pattern );
						}
					)
				);
			}
			if ( $found ) {
				usort(
					$found,
					static function ( $a, $b ) {
						return strnatcasecmp( $b, $a ); // Newest version number first.
					}
				);
				if ( $fast ) {
					foreach ( $found as $m ) {
						if ( preg_match( '/mini|flash|haiku|small|fast|lite|nano/i', $m ) ) {
							$pick = $m;
							break;
						}
					}
				}
				$pick = $pick ? $pick : $found[0];
				break;
			}
		}
		$pick = $pick ? $pick : $models[0];
		self::remember_model( $pick );
		return $pick;
	}

	/**
	 * Pull the JSON object out of an answer (handles code fences or extra text).
	 *
	 * @param string $text Answer.
	 * @return array|null
	 */
	public static function parse_json( $text ) {
		$text  = trim( (string) $text );
		$text  = preg_replace( '/^```(?:json)?\s*|\s*```$/i', '', $text );
		$start = strpos( $text, '{' );
		$end   = strrpos( $text, '}' );
		if ( false === $start || false === $end || $end <= $start ) {
			return null;
		}
		$data = json_decode( substr( $text, $start, $end - $start + 1 ), true );
		return is_array( $data ) ? $data : null;
	}

	/**
	 * Quick check that the key and model work.
	 *
	 * @return true|WP_Error
	 */
	public static function test() {
		$r = self::complete( 'Reply with JSON only.', 'Return {"ok":true}', 200 );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$j = self::parse_json( $r );
		return ( is_array( $j ) && ! empty( $j['ok'] ) ) ? true : new WP_Error( 'seob_ai_test', __( 'Connected, but the answer was not in the expected format.', 'seo-boster' ) );
	}
}
