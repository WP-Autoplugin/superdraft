<?php
/**
 * Anthropic API class for Superdraft.
 *
 * @package Superdraft
 * @since 1.0.0
 */

namespace Superdraft;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Anthropic API class.
 */
class Anthropic_API extends API {

	/**
	 * Selected model.
	 *
	 * @var string
	 */
	protected $model;

	/**
	 * Temperature parameter.
	 *
	 * @var float
	 */
	protected $temperature = 0.2;

	/**
	 * Max tokens parameter.
	 *
	 * @var int
	 */
	protected $max_tokens = 4096;

	/**
	 * API URL.
	 *
	 * @var string
	 */
	protected $api_url = 'https://api.anthropic.com/v1/messages';

	/**
	 * Set the model and its parameters.
	 *
	 * @param string $model The model.
	 */
	public function set_model( $model ) {
		$this->model = sanitize_text_field( $model );

		$model_params = [
			'claude-3-5-sonnet-20240620' => [
				'temperature' => 0.2,
				'max_tokens'  => 8192,
			],
			'claude-3-5-sonnet-latest'   => [
				'temperature' => 0.2,
				'max_tokens'  => 8192,
			],
			'claude-3-5-haiku-latest'    => [
				'temperature' => 0.2,
				'max_tokens'  => 8192,
			],
			'claude-3-5-haiku-20241022'  => [
				'temperature' => 0.2,
				'max_tokens'  => 8192,
			],
			'claude-3-opus-20240229'     => [
				'temperature' => 0.2,
				'max_tokens'  => 4096,
			],
			'claude-3-sonnet-20240229'   => [
				'temperature' => 0.2,
				'max_tokens'  => 4096,
			],
			'claude-3-haiku-20240307'    => [
				'temperature' => 0.2,
				'max_tokens'  => 4096,
			],
		];

		if ( isset( $model_params[ $model ] ) ) {
			$this->temperature = $model_params[ $model ]['temperature'];
			$this->max_tokens  = $model_params[ $model ]['max_tokens'];
		}
	}

	/**
	 * Send a prompt to the Anthropic API.
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
		if ( ! empty( $system_message ) ) {
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
			'temperature' => $this->temperature,
			'max_tokens'  => $this->max_tokens,
			'messages'    => $messages,
		];

		$allowed_keys  = [ 'model', 'temperature', 'max_tokens', 'messages', 'thinking' ];
		$override_body = array_intersect_key( $override_body, array_flip( $allowed_keys ) );
		$body          = array_merge( $body, $override_body );

		$headers = [
			'X-API-Key'         => $this->api_key,
			'Anthropic-Version' => '2023-06-01',
			'Content-Type'      => 'application/json',
		];

		// Optional beta header example (extend if needed).
		if ( 'claude-3-5-sonnet-20240620' === $this->model ) {
			$headers['Anthropic-Beta'] = 'max-tokens-3-5-sonnet-2024-07-15';
		}

		/**
		 * Filters the body of the request sent to the Anthropic API.
		 *
		 * @since 1.0.0
		 *
		 * @param array          $body The request body array.
		 * @param Anthropic_API  $this Current API instance.
		 */
		$body = apply_filters( 'superdraft_api_request_body', $body, $this );

		/**
		 * Filters the headers of the request sent to the Anthropic API.
		 *
		 * @since 1.0.0
		 *
		 * @param array          $headers The request headers array.
		 * @param Anthropic_API  $this    Current API instance.
		 */
		$headers = apply_filters( 'superdraft_api_request_headers', $headers, $this );

		$response = $this->request(
			$this->api_url,
			[
				'body'    => wp_json_encode( $body ),
				'headers' => $headers,
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$this->last_response = $response;
		$data                = json_decode( wp_remote_retrieve_body( $response ), true );

		// New Messages API primary path.
		if ( isset( $data['content'][0]['text'] ) ) {
			return $data['content'][0]['text'];
		}

		// Fallback for (unlikely) old completion style.
		if ( isset( $data['completion'] ) ) {
			return $data['completion'];
		}

		return new \WP_Error(
			'api_error',
			__( 'Error communicating with the Anthropic API.', 'superdraft' ) . "\n" . print_r( $data, true ) // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
		);
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
				$usage['input_tokens']  = $response['usage']['input_tokens'];
				$usage['output_tokens'] = $response['usage']['output_tokens'];
			}
		}

		return $usage;
	}
}
