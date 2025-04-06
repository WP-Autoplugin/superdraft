<?php
/**
 * Custom API class for Superdraft.
 *
 * @package Superdraft
 * @since 1.0.0
 */

namespace Superdraft;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Custom API class that connects to user-defined OpenAI-compatible endpoints.
 */
class Custom_API extends API {

	/**
	 * Additional headers specified by the user.
	 *
	 * @var array
	 */
	protected $extra_headers = [];

	/**
	 * Configure the custom API with user-defined settings.
	 *
	 * @param string $endpoint   The custom API endpoint URL.
	 * @param string $api_key    The API key for authentication.
	 * @param string $model      The model parameter sent to the API.
	 * @param array  $headers    Additional headers (key/value pairs).
	 */
	public function set_custom_config( $endpoint, $api_key, $model, $headers = [] ) {
		$this->api_url       = esc_url_raw( $endpoint );
		$this->api_key       = sanitize_text_field( $api_key );
		$this->model         = sanitize_text_field( $model );
		$this->extra_headers = $this->parse_extra_headers( $headers );
	}

	/**
	 * Parse additional headers into an associative array.
	 *
	 * @param array $headers Array of header strings like "Name=Value".
	 * @return array Parsed headers.
	 */
	protected function parse_extra_headers( $headers ) {
		$parsed = [];
		foreach ( $headers as $header_line ) {
			if ( strpos( $header_line, '=' ) !== false ) {
				list( $key, $value ) = explode( '=', $header_line, 2 );
				$key                 = trim( sanitize_text_field( $key ) );
				$value               = trim( sanitize_text_field( $value ) );
				if ( $key && $value ) {
					$parsed[ $key ] = $value;
				}
			}
		}
		return $parsed;
	}

	/**
	 * Send a prompt to the custom API.
	 *
	 * @param string $prompt         The user prompt.
	 * @param string $system_message Optional system message.
	 * @param array  $override_body  Optional parameters to override in the request body.
	 *
	 * @return string|\WP_Error The response or a WP_Error object on failure.
	 */
	public function send_prompt( $prompt, $system_message = '', $override_body = [] ) {
		$prompt = $this->trim_prompt( $prompt );

		$messages = [];
		if ( $system_message ) {
			$messages[] = [
				'role'    => 'system',
				'content' => $system_message,
			];
		}

		$messages[] = [
			'role'    => 'user',
			'content' => $prompt,
		];

		$body = [
			'model'    => $this->model,
			'messages' => $messages,
		];

		// Merge override_body if provided.
		if ( ! empty( $override_body ) && is_array( $override_body ) ) {
			$body = array_merge( $body, $override_body );
		}

		// Merge default auth header with any extra headers.
		$headers = array_merge(
			[
				'Authorization' => 'Bearer ' . $this->api_key,
				'Content-Type'  => 'application/json',
			],
			$this->extra_headers
		);

		/**
		 * Filters the body of the request to the OpenAI API.
		 *
		 * @param array $body The body of the request.
		 * @param object $this The current instance of the API class.
		 */
		$body = apply_filters( 'superdraft_api_request_body', $body, $this );

		/**
		 * Filters the headers of the request to the OpenAI API.
		 *
		 * @param array $headers The headers of the request.
		 * @param object $this The current instance of the API class.
		 */
		$headers = apply_filters( 'superdraft_api_request_headers', $headers, $this );

		$response = $this->request(
			$this->api_url,
			[
				'timeout' => 60,
				'headers' => $headers,
				'body'    => wp_json_encode( $body ),
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$this->last_response = $response;
		$data                = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $data['choices'][0]['message']['content'] ) ) {
			return new \WP_Error(
				'api_error',
				__( 'Error communicating with the API.', 'superdraft' ) . "\n" . print_r( $data, true ) // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r -- We show the API response for debugging.
			);
		}

		return $data['choices'][0]['message']['content'];
	}

	/**
	 * Get input/output token usage for the last request.
	 *
	 * @return array The token usage.
	 */
	public function get_token_usage() {
		$usage = [
			'input_tokens'  => 0,
			'output_tokens' => 0,
		];

		if ( ! empty( $this->last_response ) ) {
			$response = json_decode( wp_remote_retrieve_body( $this->last_response ), true );

			if ( empty( $response['usage'] ) || ! is_array( $response['usage'] ) ) {
				return $usage;
			}

			// Check prompt_tokens/completion_tokens and then fallback to input_tokens/output_tokens.
			if ( ! empty( $response['usage']['prompt_tokens'] ) ) {
				$usage['input_tokens']  = $response['usage']['prompt_tokens'];
				$usage['output_tokens'] = $response['usage']['completion_tokens'];
			} elseif ( ! empty( $response['usage']['input_tokens'] ) ) {
				$usage['input_tokens']  = $response['usage']['input_tokens'];
				$usage['output_tokens'] = $response['usage']['output_tokens'];
			}
		}

		return $usage;
	}
}
