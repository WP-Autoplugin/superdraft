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
	 * Whether the selected model uses the Responses API.
	 *
	 * @var bool
	 */
	protected $uses_responses_api = false;

	/**
	 * Set the model and its parameters.
	 *
	 * @param string $model The model.
	 */
	public function set_model( $model ) {
		$this->model = sanitize_text_field( $model );

		$config                   = Model_Catalog::get_request_config( 'OpenAI', $this->model );
		$this->uses_responses_api = ( isset( $config['transport'] ) && 'responses' === $config['transport'] ) || (bool) preg_match( '/^(gpt-5(?:[.-]|$)|o[0-9])/', $this->model );
		if ( isset( $config['max_tokens'] ) ) {
			$this->max_tokens = $config['max_tokens'];
		}
		if ( $this->uses_responses_api ) {
			$this->api_url = 'https://api.openai.com/v1/responses';
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

		$body = $this->uses_responses_api ?
			$this->get_responses_body( $prompt, $system_message ) :
			$this->get_chat_completions_body( $prompt, $system_message );

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
		$content             = $this->uses_responses_api ? $this->get_responses_content( $data ) : ( $data['choices'][0]['message']['content'] ?? '' );
		if ( empty( $content ) ) {
			return new \WP_Error(
				'api_error',
				__( 'Error communicating with the OpenAI API.', 'superdraft' ) . "\n" . print_r( $data, true ) // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r -- We show the API response for debugging.
			);
		}

		return $content;
	}

	/**
	 * Build a Chat Completions request body for legacy-compatible models.
	 *
	 * @param string $prompt         User prompt.
	 * @param string $system_message System prompt.
	 * @return array
	 */
	protected function get_chat_completions_body( $prompt, $system_message ) {
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

		return [
			'model'       => $this->model,
			'messages'    => $messages,
			'temperature' => $this->temperature,
			'max_tokens'  => $this->max_tokens,
		];
	}

	/**
	 * Build a Responses API body for current OpenAI models.
	 *
	 * @param string $prompt         User prompt.
	 * @param string $system_message System prompt.
	 * @return array
	 */
	protected function get_responses_body( $prompt, $system_message ) {
		$body = [
			'model'             => $this->model,
			'input'             => $prompt,
			'max_output_tokens' => $this->max_tokens,
		];
		if ( $system_message ) {
			$body['instructions'] = $system_message;
		}

		return $body;
	}

	/**
	 * Extract text from a Responses API response.
	 *
	 * @param array $data Decoded response body.
	 * @return string
	 */
	protected function get_responses_content( $data ) {
		if ( ! empty( $data['output_text'] ) ) {
			return $data['output_text'];
		}

		foreach ( $data['output'] ?? [] as $item ) {
			foreach ( $item['content'] ?? [] as $content ) {
				if ( isset( $content['text'] ) ) {
					return $content['text'];
				}
			}
		}

		return '';
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
				$usage['input_tokens']  = $response['usage']['prompt_tokens'] ?? $response['usage']['input_tokens'] ?? 0;
				$usage['output_tokens'] = $response['usage']['completion_tokens'] ?? $response['usage']['output_tokens'] ?? 0;
			}
		}

		return $usage;
	}
}
