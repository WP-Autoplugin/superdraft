<?php
/**
 * OpenAI Image‑generation (DALL·E) API class for Superdraft.
 *
 * @package Superdraft
 * @since   1.1.1
 */

namespace Superdraft;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * OpenAI DALL·E image‑generation wrapper.
 */
class OpenAI_Image_API extends API {

	/**
	 * Model to call (eg. dall-e-3).
	 *
	 * @var string
	 */
	protected $model = 'dall-e-3';

	/**
	 * Endpoint.
	 *
	 * @var string
	 */
	protected $api_url = 'https://api.openai.com/v1/images/generations';

	/**
	 * Set model.
	 *
	 * @param string $model Model identifier (eg. dall-e-3).
	 */
	public function set_model( $model ) {
		$this->model = sanitize_text_field( $model );
	}

	/**
	 * Send prompt to DALL·E and return raw image data (base64‑encoded PNG).
	 *
	 * @param string $prompt        Prompt text.
	 * @param string $system_message Ignored – kept for API parity.
	 * @param array  $override_body  Extra / overriding body keys.
	 *
	 * @return string|\WP_Error Base64 string on success, WP_Error on failure.
	 */
	public function send_prompt( $prompt, $system_message = '', $override_body = [] ) {

		$prompt = $this->trim_prompt( $prompt );

		$body = array_merge(
			[
				'model'           => $this->model,
				'prompt'          => $prompt,
				'n'               => 1,                 // DALL·E 3 supports only one image per call.
				'response_format' => 'b64_json',
				'size'            => '1024x1024',
			],
			$override_body
		);

		$headers = [
			'Authorization' => 'Bearer ' . $this->api_key,
			'Content-Type'  => 'application/json',
		];

		$body    = apply_filters( 'superdraft_api_request_body', $body, $this );
		$headers = apply_filters( 'superdraft_api_request_headers', $headers, $this );

		error_log( 'OpenAI Image API request: ' . print_r( $body, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r -- Debugging.

		$response = $this->request(
			$this->api_url,
			[
				'timeout' => 60,
				'headers' => $headers,
				'body'    => wp_json_encode( $body ),
			]
		);

		error_log( 'OpenAI Image API response: ' . print_r( $response, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r -- Debugging.

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$this->last_response = $response;
		$data                = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $data['data'][0]['b64_json'] ) ) {
			return new \WP_Error(
				'api_error',
				__( 'Error communicating with the OpenAI Image API.', 'superdraft' ) . "\n" . print_r( $data, true ) // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r -- We show the API response for debugging.
			);
		}

		return $data['data'][0]['b64_json'];
	}

	/**
	 * DALL·E responses don’t include token usage; return zeroes.
	 *
	 * @return array
	 */
	public function get_token_usage() {
		return [
			'input_tokens'  => 0,
			'output_tokens' => 0,
		];
	}
}
