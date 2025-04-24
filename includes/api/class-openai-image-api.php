<?php
/**
 * OpenAI Image API class for Superdraft.
 *
 * @package Superdraft
 * @since 1.1.2
 */

namespace Superdraft;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * OpenAI Image API class.
 */
class OpenAI_Image_API extends API {

	/**
	 * API Generation URL.
	 *
	 * @var string
	 */
	protected $generation_url = 'https://api.openai.com/v1/images/generations';

	/**
	 * API Edit URL.
	 *
	 * @var string
	 */
	protected $edit_url = 'https://api.openai.com/v1/images/edits';

	/**
	 * Set the model (currently only gpt-image-1).
	 *
	 * @param string $model The model.
	 */
	public function set_model( $model ) {
		// For now, we hardcode or just validate it's the expected one.
		$this->model = 'gpt-image-1';
	}

	/**
	 * Send an image generation prompt.
	 * Returns raw image data (binary string) on success.
	 *
	 * @param string $prompt         The user prompt.
	 * @param string $system_message Optional system message (unused for image generation).
	 * @param array  $override_body  Optional body to override the default request body.
	 *
	 * @return string|\WP_Error The raw image data or an error.
	 */
	public function send_prompt( $prompt, $system_message = '', $override_body = [] ) {
		$prompt = $this->trim_prompt( $prompt );

		$body = [
			'model'  => $this->model,
			'prompt' => $prompt,
			// Add other parameters like 'n', 'size', 'quality', 'style' if needed later
		];

		// Merge override_body if provided.
		if ( ! empty( $override_body ) && is_array( $override_body ) ) {
			$body = array_merge( $body, $override_body );
		}

		$headers = [
			'Authorization' => 'Bearer ' . $this->api_key,
			'Content-Type'  => 'application/json',
		];

		$body    = apply_filters( 'superdraft_openai_image_api_request_body', $body, $this );
		$headers = apply_filters( 'superdraft_openai_image_api_request_headers', $headers, $this );

		$response = $this->request(
			$this->generation_url,
			[
				'timeout' => 100, // Image generation can take longer
				'headers' => $headers,
				'body'    => wp_json_encode( $body ),
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$this->last_response = $response;
		$data                = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $data['error'] ) ) {
			$error_message = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Unknown API error', 'superdraft' );
			return new \WP_Error( 'api_error', sprintf( __( 'OpenAI API Error: %s', 'superdraft' ), $error_message ) );
		}

		if ( empty( $data['data'][0]['b64_json'] ) ) {
			return new \WP_Error(
				'api_error',
				__( 'No image data found in the OpenAI API response.', 'superdraft' ) . "\n" . print_r( $data, true ) // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			);
		}

		// Decode the base64 image data
		$image_data = base64_decode( $data['data'][0]['b64_json'] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		if ( false === $image_data ) {
			return new \WP_Error( 'decode_error', __( 'Failed to decode base64 image data from OpenAI.', 'superdraft' ) );
		}

		return $image_data; // Return raw image bytes
	}

	/**
	 * Send an image editing request.
	 * Returns raw image data (binary string) on success.
	 *
	 * @param string $prompt      The editing instruction prompt.
	 * @param array  $image_paths Array of absolute paths to the reference image(s).
	 *                            Currently, the plugin UI likely only supports one.
	 * @param array  $override_form_params Optional form parameters to override/add.
	 *
	 * @return string|\WP_Error The raw edited image data or an error.
	 */
	public function edit_image( $prompt, $image_paths, $override_form_params = [] ) {
		if ( empty( $image_paths ) ) {
			return new \WP_Error( 'missing_image', __( 'No reference image path provided for editing.', 'superdraft' ) );
		}

		// Prepare multipart/form-data body
		// NOTE: wp_remote_post handles the Content-Type header automatically for multipart
		$body = [
			'model'           => $this->model,
			'prompt'          => $prompt,
		];

		// Add image files. wp_remote_post expects file paths directly for multipart.
		// However, CURLFile is more robust if available. Check for it.
		foreach ( $image_paths as $index => $path ) {
			if ( ! file_exists( $path ) ) {
				return new \WP_Error( 'file_not_found', sprintf( __( 'Reference image file not found: %s', 'superdraft' ), $path ) );
			}
			// Use CURLFile if available (PHP 5.5+) for better multipart handling
			if ( class_exists( 'CURLFile' ) ) {
				$mime_type = mime_content_type( $path );
				$filename  = basename( $path );
				$body[ "image[$index]" ] = new \CURLFile( $path, $mime_type, $filename );
			} else {
				// Fallback for older PHP - might be less reliable with wp_remote_post
				$body[ "image[$index]" ] = '@' . $path;
			}
		}

		// Merge overrides
		if ( ! empty( $override_form_params ) && is_array( $override_form_params ) ) {
			$body = array_merge( $body, $override_form_params );
		}

		$headers = [
			'Authorization' => 'Bearer ' . $this->api_key,
			// Content-Type: multipart/form-data is set automatically by wp_remote_post
		];

		$body    = apply_filters( 'superdraft_openai_image_edit_api_request_body', $body, $this );
		$headers = apply_filters( 'superdraft_openai_image_edit_api_request_headers', $headers, $this );

		$response = $this->request(
			$this->edit_url,
			[
				'method'  => 'POST', // Explicitly set method
				'timeout' => 100,
				'headers' => $headers,
				'body'    => $body, // Pass the array directly for multipart
			]
		);

		if ( is_wp_error( $response ) ) {
			// Check if the error is due to CURLFile fallback issue
			if ( ! class_exists( 'CURLFile' ) && strpos( $response->get_error_message(), 'fopen' ) !== false ) {
				// Potentially add a more specific error message about PHP version or CURLFile requirement
			}
			return $response;
		}

		$this->last_response = $response;
		$response_code       = wp_remote_retrieve_response_code( $response );
		$response_body       = wp_remote_retrieve_body( $response );
		$data                = json_decode( $response_body, true );

		if ( $response_code >= 400 || isset( $data['error'] ) ) {
			$error_message = __( 'Unknown API error', 'superdraft' );
			if ( isset( $data['error']['message'] ) ) {
				$error_message = $data['error']['message'];
			} elseif ( ! empty( $response_body ) ) {
				$error_message = $response_body; // Show raw body if JSON parse failed or no error structure
			}
			return new \WP_Error( 'api_error', sprintf( __( 'OpenAI Edit API Error (%d): %s', 'superdraft' ), $response_code, $error_message ) );
		}


		if ( empty( $data['data'][0]['b64_json'] ) ) {
			return new \WP_Error(
				'api_error',
				__( 'No edited image data found in the OpenAI API response.', 'superdraft' ) . "\n" . print_r( $data, true ) // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			);
		}

		// Decode the base64 image data
		$image_data = base64_decode( $data['data'][0]['b64_json'] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		if ( false === $image_data ) {
			return new \WP_Error( 'decode_error', __( 'Failed to decode base64 edited image data from OpenAI.', 'superdraft' ) );
		}

		return $image_data; // Return raw image bytes
	}

	/**
	 * OpenAI image models do not report token usage.
	 *
	 * @return array The token usage (all zeros).
	 */
	public function get_token_usage() {
		return [
			'input_tokens'  => 0,
			'output_tokens' => 0,
		];
	}
}
