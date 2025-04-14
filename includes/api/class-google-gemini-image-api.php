<?php
/**
 * Google Gemini Image API class for Superdraft.
 *
 * @package Superdraft
 * @since 1.1.0
 */

namespace Superdraft;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Google Gemini Image API class.
 */
class Google_Gemini_Image_API extends API {
	/**
	 * Selected model.
	 *
	 * @var string
	 */
	protected $model;

	/**
	 * API base URL.
	 *
	 * @var string
	 */
	protected $api_url = 'https://generativelanguage.googleapis.com/v1beta/models';

	/**
	 * Temperature parameter (default).
	 *
	 * @var float
	 */
	protected $temperature = 0.2;

	/**
	 * Max tokens parameter (default).
	 *
	 * @var int
	 */
	protected $max_tokens = 8192;

	/**
	 * Set the model.
	 *
	 * @param string $model The model.
	 */
	public function set_model( $model ) {
		$this->model = sanitize_text_field( $model );
	}

	/**
	 * Send an image generation prompt.
	 *
	 * @param string $prompt         The user prompt.
	 * @param string $system_message Optional system message.
	 * @param array  $override_body  Optional body to override the default request body.
	 *
	 * @return string|WP_Error The response from the API or an error.
	 */
	public function send_prompt( $prompt, $system_message = '', $override_body = [] ) {
		$prompt = $this->trim_prompt( $prompt );
		$url    = $this->api_url . '/' . $this->model . ':generateContent?key=' . $this->api_key;

		$parts = [
			[ 'text' => $prompt ],
		];

		$body = [
			'contents'         => [
				[
					'parts' => $parts,
				],
			],
			'generationConfig' => [
				'responseModalities' => [ 'Text', 'Image' ],
			],
		];

		if ( ! empty( $override_body ) && is_array( $override_body ) ) {
			if ( isset( $override_body['generationConfig'] ) && is_array( $override_body['generationConfig'] ) ) {
				$body['generationConfig'] = array_merge(
					$body['generationConfig'],
					$override_body['generationConfig']
				);
				unset( $override_body['generationConfig'] );
			}
			$body = array_merge( $body, $override_body );
		}

		$headers = [
			'Content-Type' => 'application/json',
		];

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
			$url,
			[
				'headers' => $headers,
				'body'    => wp_json_encode( $body ),
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$this->last_response = $response;
		$data                = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $data['candidates'][0]['content']['parts'] ) ) {
			return new \WP_Error(
				'api_error',
				__( 'Error communicating with the Google Gemini Image API.', 'superdraft' ) . "\n" . print_r( $data, true ) // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r -- We show the API response for debugging.
			);
		}

		foreach ( $data['candidates'][0]['content']['parts'] as $part ) {
			if ( isset( $part['inlineData'] ) && isset( $part['inlineData']['data'] ) ) {
				return $part['inlineData']['data'];
			}
		}

		return new \WP_Error(
			'api_error',
			__( 'No image data found in the API response.', 'superdraft' )
		);
	}
}
