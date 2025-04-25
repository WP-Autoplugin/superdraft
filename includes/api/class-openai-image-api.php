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
	 * Send an image editing request using a single reference image via the Requests library.
	 * Returns raw image data (binary string) on success.
	 *
	 * @param string $prompt      The editing instruction prompt.
	 * @param string $image_path  Absolute path to the single reference image.
	 * @param array  $override_form_params Optional form parameters to override/add.
	 *
	 * @return string|\WP_Error The raw edited image data or an error.
	 */
	public function edit_image( $prompt, $image_path, $override_form_params = [] ) {

		// Essential check for CURLFile (PHP 5.5+) as this method relies on it
		if ( ! class_exists( 'CURLFile' ) ) {
			return new \WP_Error( 'curlfile_missing', __( 'PHP 5.5+ with CURLFile support is required for reliable image editing with wp_remote_post.', 'superdraft' ) );
		}
		// Check if cURL itself is available via WP_Http
        if ( ! wp_http_supports( ['curl'] ) ) {
             // Although the hook wouldn't run, good to check proactively
             // You could potentially implement the fsockopen fallback from the gist here if needed
             return new \WP_Error( 'curl_transport_missing', __( 'The cURL transport for WP_Http is not available, cannot guarantee multipart upload.', 'superdraft' ) );
        }


		if ( empty( $image_path ) ) {
			return new \WP_Error( 'missing_image', __( 'No reference image path provided for editing.', 'superdraft' ) );
		}
		if ( ! file_exists( $image_path ) ) {
			return new \WP_Error( 'file_not_found', sprintf( __( 'Reference image file not found: %s', 'superdraft' ), $image_path ) );
		}

		// --- Prepare Body Array for wp_remote_post ---
		$body_args = [
			'model'  => $this->model,
			'prompt' => $prompt,
		];

		// Add the image using CURLFile
		$mime_type = mime_content_type( $image_path );
		$filename  = basename( $image_path );
		$body_args['image'] = new \CURLFile( $image_path, $mime_type, $filename );

		// Merge overrides
		if ( ! empty( $override_form_params ) && is_array( $override_form_params ) ) {
			foreach ($override_form_params as $key => $value) {
				// Ensure overrides don't conflict with essential fields or the file
				if ( ! in_array($key, ['model', 'prompt', 'image'], true) ) {
					$body_args[$key] = $value;
				}
			}
		}

		// Apply filters to the body array *before* passing it to the hook/wp_remote_post
		$body_args = apply_filters( 'superdraft_openai_image_edit_api_request_body', $body_args, $this );

		// --- Prepare Headers for wp_remote_post ---
		$headers = [
			'Authorization' => 'Bearer ' . $this->api_key,
			// IMPORTANT: Do NOT set 'Content-Type: multipart/form-data' here.
			// Let the hook ensure cURL handles it correctly.
		];
		// Apply filters to headers
		$headers = apply_filters( 'superdraft_openai_image_edit_api_request_headers', $headers, $this );


		// --- Set up the Hook Function (adapted from Gist) ---
		// We need this anonymous function to pass $body_args into the hook's scope.
		$curl_hook_function = function( $handle, $r, $url ) use ( $body_args ) {
			// Check if the body arguments contain a CURLFile object
			$has_curl_file = false;
			if ( is_array( $body_args ) ) {
				foreach ( $body_args as $value ) {
					if ( is_object( $value ) && $value instanceof \CURLFile ) {
						$has_curl_file = true;
						break;
					}
				}
			}

			// If it has a CURLFile, force CURLOPT_POSTFIELDS to use the array
			// This makes cURL automatically use multipart/form-data
			if ( $has_curl_file && is_resource( $handle ) ) {
				curl_setopt( $handle, CURLOPT_POSTFIELDS, $body_args );
			}
            // Note: The Gist's fsockopen hook ('requests-fsockopen.before_send')
            // is omitted here for simplicity, as cURL is far more common.
            // It could be added if needed, requiring the build_data_files function.
		};

		// --- Add the hook, execute request, remove the hook ---
		add_action( 'http_api_curl', $curl_hook_function, 10, 3 );

		$start_time = microtime( true );

		// Call wp_remote_post using the standard API::request method
        // Pass the $body_args array directly. The hook will modify the cURL options.
		$response = $this->request(
			$this->edit_url,
			[
				'method'  => 'POST',
				'timeout' => 100,
				'headers' => $headers,
				'body'    => $body_args, // Pass the array with CURLFile
			]
		);

		$this->response_timer = round( ( microtime( true ) - $start_time ) * 1000 ); // Record time

		// IMPORTANT: Remove the hook immediately after the request
		remove_action( 'http_api_curl', $curl_hook_function, 10 );
		// --- End Hooked Request Execution ---


		// --- Process wp_remote_post Response ---
		if ( is_wp_error( $response ) ) {
			return $response; // Return WP_Error from wp_remote_post
		}

		// $this->last_response = $response; // Store the raw WP HTTP API response array if needed

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		$data          = json_decode( $response_body, true );

		if ( $response_code >= 400 || ( json_last_error() === JSON_ERROR_NONE && isset( $data['error'] ) ) ) {
			$error_message = __( 'Unknown API error', 'superdraft' );
			if ( json_last_error() === JSON_ERROR_NONE && isset( $data['error']['message'] ) ) {
				$error_message = $data['error']['message'];
			} elseif ( ! empty( $response_body ) && is_string($response_body) ) {
				$error_message = substr( $response_body, 0, 500 ); // Limit raw output
			}
			// Include the original error message from WP_Error if available (though we checked is_wp_error already)
            // $original_wp_error_msg = is_wp_error($response) ? $response->get_error_message() : '';
			return new \WP_Error( 'api_error', sprintf( __( 'OpenAI Edit API Error (%d): %s', 'superdraft' ), $response_code, $error_message ) );
		}

		if ( json_last_error() !== JSON_ERROR_NONE || empty( $data['data'][0]['b64_json'] ) ) {
			return new \WP_Error(
				'api_error',
				__( 'Invalid or empty JSON response received from OpenAI Edit API.', 'superdraft' ) . "\n" . print_r( $response_body, true ) // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			);
		}

		// Decode the base64 image data
		$image_data = base64_decode( $data['data'][0]['b64_json'] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		if ( false === $image_data ) {
			return new \WP_Error( 'decode_error', __( 'Failed to decode base64 edited image data from OpenAI.', 'superdraft' ) );
		}

		return $image_data; // Return raw image bytes
		// --- End Response Processing ---
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
