<?php
/**
 * Replicate Image API class for Superdraft.
 *
 * @package Superdraft
 * @since 1.1.1
 */

namespace Superdraft;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Image‑generation only (no editing) wrapper for Replicate.
 */
class Replicate_Image_API extends API {

	/**
	 * The selected Replicate model, e.g. ideogram-ai/ideogram-v2a.
	 *
	 * @var string
	 */
	protected $model;

	/**
	 * Base URL.
	 *
	 * @var string
	 */
	protected $api_url = 'https://api.replicate.com/v1/models';

	/**
	 * Set model.
	 */
	public function set_model( $model ) {
		$this->model = sanitize_text_field( $model );
	}

	/**
	 * Generate an image and return its **raw binary** contents.
	 *
	 * @param string $prompt         Prompt text.
	 * @param string $system_message Unused, kept for interface parity.
	 * @param array  $override_body  Extra keys for the `"input"` object.
	 *
	 * @return string|\WP_Error  Binary data or error.
	 */
	public function send_prompt( $prompt, $system_message = '', $override_body = [] ) {
		$prompt = $this->trim_prompt( $prompt );

		$url = trailingslashit( $this->api_url ) . $this->model . '/predictions';

		$body = [
			'input' => array_merge(
				[
					'prompt' => $prompt,
				],
				$override_body
			),
		];

		$headers = [
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $this->api_key,
			'Prefer'        => 'wait',           // ‑‑ wait synchronously
		];

		$body    = apply_filters( 'superdraft_api_request_body', $body, $this );
		$headers = apply_filters( 'superdraft_api_request_headers', $headers, $this );

		$response = $this->request(
			$url,
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

		if ( ! empty( $data['error'] ) ) {
			return new \WP_Error(
				'api_error',
				sprintf( __( 'Replicate API error: %s', 'superdraft' ), $data['error'] )
			);
		}

		$output = $data['output'] ?? '';
		if ( empty( $output ) ) {
			return new \WP_Error( 'api_error', __( 'Replicate API returned no output.', 'superdraft' ) );
		}

		// Output can be string or array.
		if ( is_array( $output ) ) {
			$output = reset( $output );
		}

		$image_response = wp_remote_get( $output );
		if ( is_wp_error( $image_response ) ) {
			return $image_response;
		}

		if ( 200 !== wp_remote_retrieve_response_code( $image_response ) ) {
			return new \WP_Error( 'api_error', __( 'Failed to download generated image.', 'superdraft' ) );
		}

		return wp_remote_retrieve_body( $image_response );  // raw PNG/JPG bytes
	}

	/**
	 * Replicate doesn’t provide usage metadata.
	 */
	public function get_token_usage() {
		return [
			'input_tokens'  => 0,
			'output_tokens' => 0,
		];
	}
}
