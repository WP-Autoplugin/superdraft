<?php
/**
 * Google Gemini API class for Superdraft.
 *
 * @package Superdraft
 * @since 1.0.0
 */

namespace Superdraft;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Google Gemini API class.
 */
class Google_Gemini_API extends API {

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
	 * Send a prompt to the Google Gemini API.
	 *
	 * @param string $prompt         The user prompt.
	 * @param string $system_message Optional system message.
	 * @param array  $override_body  Optional array to override/add request body parameters.
	 *
	 * @return string|\WP_Error The response or a WP_Error object on failure.
	 */
	public function send_prompt( $prompt, $system_message = '', $override_body = [] ) {
		$prompt = $this->trim_prompt( $prompt );

		// Build the full endpoint with the selected model and API key.
		$url = $this->api_url . '/' . $this->model . ':generateContent?key=' . $this->api_key;

		// Combine system message + prompt (similar to how OpenAI system & user messages are handled).
		$messages = [];
		if ( $system_message ) {
			$messages[] = $system_message;
		}
		$messages[] = $prompt;

		// Convert each message into a 'part' for Google's generative API.
		$parts = [];
		foreach ( $messages as $message ) {
			$parts[] = [ 'text' => $message ];
		}

		// Default request body for Gemini.
		$body = [
			'contents'         => [
				[
					'parts' => $parts,
				],
			],
			'generationConfig' => [
				'temperature'     => isset( $this->temperature ) ? $this->temperature : 0.2,
				'maxOutputTokens' => isset( $this->max_tokens ) ? $this->max_tokens : 8192,
			],
		];

		// Merge in any overrides if provided.
		if ( ! empty( $override_body ) && is_array( $override_body ) ) {
			// Handle generationConfig override specifically.
			if ( isset( $override_body['generationConfig'] ) && is_array( $override_body['generationConfig'] ) ) {
				$body['generationConfig'] = array_merge(
					$body['generationConfig'],
					$override_body['generationConfig']
				);
				unset( $override_body['generationConfig'] );
			}

			// If safetySettings or other fields are passed in override_body, merge them.
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

		// POST request to the Google Gemini endpoint.
		$response = $this->request(
			$url,
			[
				'headers' => $headers,
				'body'    => wp_json_encode( $body ),
			]
		);

		// Check for WP errors.
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$this->last_response = $response;
		$data                = json_decode( wp_remote_retrieve_body( $response ), true );

		// Validate the response structure.
		if ( empty( $data['candidates'][0]['content']['parts'] ) ) {
			return new \WP_Error(
				'api_error',
				__( 'Error communicating with the Google Gemini API.', 'superdraft' ) . "\n" . print_r( $data, true ) // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r -- We show the API response for debugging.
			);
		}

		// Return the text from the last part.
		$parts     = $data['candidates'][0]['content']['parts'];
		$last_part = end( $parts );
		return $last_part['text'];
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

			if ( ! empty( $response['usageMetadata'] && is_array( $response['usageMetadata'] ) ) ) {
				$usage['input_tokens']  = empty( $response['usageMetadata']['promptTokenCount'] ) ? 0 : $response['usageMetadata']['promptTokenCount'];
				$usage['output_tokens'] = empty( $response['usageMetadata']['candidatesTokenCount'] ) ? 0 : $response['usageMetadata']['candidatesTokenCount'];
			}
		}

		return $usage;
	}
}
