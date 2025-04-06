<?php
/**
 * OpenAI API class for Superdraft.
 *
 * @package Superdraft
 * @since 1.0.0
 */

namespace Superdraft;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * OpenAI API class.
 */
class OpenAI_API extends API {

	/**
	 * Selected model.
	 *
	 * @var string
	 */
	protected $model;

	/**
	 * API URL.
	 *
	 * @var string
	 */
	protected $api_url = 'https://api.openai.com/v1/chat/completions';

	/**
	 * Set the model and its parameters.
	 *
	 * @param string $model The model.
	 */
	public function set_model( $model ) {
		$this->model = sanitize_text_field( $model );

		// Set the temperature and max tokens based on the model.
		$model_params = [
			'gpt-4o'            => [
				'temperature' => 0.7,
				'max_tokens'  => 4096,
			],
			'chatgpt-4o-latest' => [
				'temperature' => 0.7,
				'max_tokens'  => 16384,
			],
			'gpt-4o-mini'       => [
				'temperature' => 0.7,
				'max_tokens'  => 4096,
			],
			'gpt-4-turbo'       => [
				'temperature' => 0.7,
				'max_tokens'  => 4096,
			],
			'gpt-3.5-turbo'     => [
				'temperature' => 0.7,
				'max_tokens'  => 4096,
			],
		];

		if ( isset( $model_params[ $model ] ) ) {
			$this->temperature = $model_params[ $model ]['temperature'];
			$this->max_tokens  = $model_params[ $model ]['max_tokens'];
		}
	}

	/**
	 * Send a prompt to the OpenAI API.
	 *
	 * @param string $prompt         The prompt.
	 * @param string $system_message The system message.
	 * @param array  $override_body  The override body.
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
			'model'       => $this->model,
			'messages'    => $messages,
			'temperature' => isset( $this->temperature ) ? $this->temperature : 0.7,
			'max_tokens'  => isset( $this->max_tokens ) ? $this->max_tokens : 1500,
		];

		// Merge override_body if provided.
		if ( ! empty( $override_body ) && is_array( $override_body ) ) {
			$body = array_merge( $body, $override_body );
		}

		$headers = [
			'Authorization' => 'Bearer ' . $this->api_key,
			'Content-Type'  => 'application/json',
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
				__( 'Error communicating with the OpenAI API.', 'superdraft' ) . "\n" . print_r( $data, true ) // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r -- We show the API response for debugging.
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

			if ( ! empty( $response['usage'] ) && is_array( $response['usage'] ) ) {
				$usage['input_tokens']  = $response['usage']['prompt_tokens'];
				$usage['output_tokens'] = $response['usage']['completion_tokens'];
			}
		}

		return $usage;
	}
}
