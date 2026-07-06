<?php
/**
 * Superdraft API key test helper.
 *
 * @package Superdraft
 * @since 1.1.5
 */

namespace Superdraft;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared API key validation and connection testing.
 */
class API_Key_Tester {

	/**
	 * Test an API key for the given provider.
	 *
	 * @param string $provider The provider.
	 * @param string $api_key  The API key.
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public static function test( $provider, $api_key ) {
		$provider = sanitize_key( $provider );
		$api_key  = trim( (string) $api_key );

		$validation = self::validate_key_format( $provider, $api_key );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$test_url = '';
		$headers  = [
			'Content-Type' => 'application/json',
		];

		switch ( $provider ) {
			case 'openai':
				$test_url                 = 'https://api.openai.com/v1/models';
				$headers['Authorization'] = 'Bearer ' . $api_key;
				break;
			case 'anthropic':
				$test_url                     = 'https://api.anthropic.com/v1/models';
				$headers['x-api-key']         = $api_key;
				$headers['anthropic-version'] = '2023-06-01';
				break;
			case 'google':
				$test_url = add_query_arg( 'key', $api_key, 'https://generativelanguage.googleapis.com/v1beta/models' );
				break;
			case 'xai':
				$test_url                 = 'https://api.x.ai/v1/models';
				$headers['Authorization'] = 'Bearer ' . $api_key;
				break;
			case 'replicate':
				$test_url                 = 'https://api.replicate.com/v1/account';
				$headers['Authorization'] = 'Bearer ' . $api_key;
				break;
			case 'custom':
				return true;
			default:
				return new \WP_Error( 'invalid_provider', __( 'Invalid provider selected.', 'superdraft' ) );
		}

		$response = wp_remote_get(
			$test_url,
			[
				'timeout' => 15,
				'headers' => $headers,
			]
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'connection_error', __( 'Could not connect to the API. Please check your internet connection.', 'superdraft' ) );
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 === $code ) {
			return true;
		}

		$body    = wp_remote_retrieve_body( $response );
		$data    = json_decode( $body, true );
		$message = __( 'Invalid API key or API error. Please check your key and try again.', 'superdraft' );

		if ( ! empty( $data['error']['message'] ) ) {
			$message = $data['error']['message'];
		} elseif ( ! empty( $data['detail'] ) && is_string( $data['detail'] ) ) {
			$message = $data['detail'];
		} elseif ( ! empty( $data['error'] ) && is_string( $data['error'] ) ) {
			$message = $data['error'];
		} elseif ( 401 === $code ) {
			$message = __( 'Invalid API key. Please check your key and try again.', 'superdraft' );
		}

		return new \WP_Error( 'api_error', $message );
	}

	/**
	 * Validate API key format.
	 *
	 * @param string $provider The provider.
	 * @param string $api_key  The API key.
	 * @return true|\WP_Error True on valid, WP_Error on invalid.
	 */
	public static function validate_key_format( $provider, $api_key ) {
		$provider = sanitize_key( $provider );
		$api_key  = trim( (string) $api_key );

		if ( empty( $api_key ) ) {
			return new \WP_Error( 'empty_key', __( 'API key cannot be empty.', 'superdraft' ) );
		}

		switch ( $provider ) {
			case 'openai':
				if ( strpos( $api_key, 'sk-' ) !== 0 ) {
					return new \WP_Error( 'invalid_format', __( 'OpenAI API keys should start with "sk-".', 'superdraft' ) );
				}
				break;
			case 'anthropic':
				if ( strpos( $api_key, 'sk-ant-' ) !== 0 ) {
					return new \WP_Error( 'invalid_format', __( 'Anthropic API keys should start with "sk-ant-".', 'superdraft' ) );
				}
				break;
			case 'xai':
				if ( strpos( $api_key, 'xai-' ) !== 0 ) {
					return new \WP_Error( 'invalid_format', __( 'xAI API keys should start with "xai-".', 'superdraft' ) );
				}
				break;
			case 'google':
				if ( strlen( $api_key ) < 20 ) {
					return new \WP_Error( 'invalid_format', __( 'Google API key seems too short. Please check your key.', 'superdraft' ) );
				}
				break;
			case 'custom':
			case 'replicate':
				break;
		}

		return true;
	}
}
