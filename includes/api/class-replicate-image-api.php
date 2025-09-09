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
 * Image generation and editing wrapper for Replicate.
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
	 *
	 * @param string $model Model name.
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
				// translators: %s: error message.
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

		return wp_remote_retrieve_body( $image_response );  // raw PNG/JPG bytes.
	}

	/**
	 * Edit an image using Replicate image editor models.
	 *
	 * Sends the source image as a data URI (base64 inline) per Replicate guidance.
	 * Returns raw binary image data on success.
	 *
	 * @param string $prompt        Editing instruction.
	 * @param string $file_path     Absolute path to the local image file.
	 * @param array  $override_body Optional extra keys for the input payload.
	 *
	 * @return string|\WP_Error Raw image bytes or error.
	 */
	public function edit_image( $prompt, $file_path, $override_body = [] ) {
		$prompt = $this->trim_prompt( $prompt );

		if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
			return new \WP_Error( 'file_not_found', __( 'Featured image file not found or path is invalid', 'superdraft' ) );
		}

		// Read and encode the image as data URI.
		$image_data = file_get_contents( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $image_data ) {
			return new \WP_Error( 'file_read_error', __( 'Could not read featured image file.', 'superdraft' ) );
		}
		// Prefer WP function for mime type; fall back to PHP if needed.
		$file_type = wp_check_filetype( basename( $file_path ), null );
		$mime_type = ! empty( $file_type['type'] ) ? $file_type['type'] : ( function_exists( 'mime_content_type' ) ? mime_content_type( $file_path ) : 'image/png' );

		$image_base64 = base64_encode( $image_data ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		if ( false === $image_base64 ) {
			return new \WP_Error( 'encode_error', __( 'Failed to base64-encode image data for Replicate.', 'superdraft' ) );
		}
		$data_uri = 'data:' . $mime_type . ';base64,' . $image_base64;

		$url = trailingslashit( $this->api_url ) . $this->model . '/predictions';


		// Some Replicate editor models expect different input keys/params for the source image.
		// Default is a single `image` string (data URI). Others use `image_input` (array) or `input_image` (string).
		$model = (string) $this->model;

		$defaults = [
			'prompt'         => $prompt,
			'output_format'  => 'webp',
			'output_quality' => 80,
		];

		if ( false !== stripos( $model, 'nano-banana' ) ) {
			// Google Nano-Banana expects `image_input` as an array.
			$defaults['image_input']   = [ $data_uri ];
			$defaults['output_format'] = 'jpg';
		} elseif ( false !== stripos( $model, 'flux-kontext' ) ) {
			// FLUX Kontext expects `input_image` and supports extra params.
			$defaults['input_image']   = $data_uri;
			$defaults['aspect_ratio']  = 'match_input_image';
			$defaults['output_format'] = 'jpg';
			if ( false !== stripos( $model, 'flux-kontext-max' ) ) {
				$defaults['safety_tolerance'] = 2;
			} elseif ( false !== stripos( $model, 'flux-kontext-dev' ) ) {
				$defaults['go_fast']             = true;
				$defaults['guidance']            = 2.5;
				$defaults['num_inference_steps'] = 30;
			}
		} else {
			// Default editors like qwen/qwen-image-edit.
			$defaults['image'] = $data_uri;
			if ( false !== stripos( $model, 'qwen-image-edit' ) ) {
				$defaults['go_fast'] = true;
			}
		}

		$input = array_merge( $defaults, (array) $override_body );

		$body = [ 'input' => $input ];

		$headers = [
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $this->api_key,
			'Prefer'        => 'wait',
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
				// translators: %s: error message.
				sprintf( __( 'Replicate API error: %s', 'superdraft' ), is_array( $data['error'] ) ? ( $data['error']['message'] ?? wp_json_encode( $data['error'] ) ) : $data['error'] )
			);
		}

		$output = $data['output'] ?? '';
		if ( empty( $output ) ) {
			return new \WP_Error( 'api_error', __( 'Replicate API returned no output.', 'superdraft' ) );
		}

		// Output can be string or array of URLs.
		if ( is_array( $output ) ) {
			$output = reset( $output );
		}

		$image_response = wp_remote_get( $output );
		if ( is_wp_error( $image_response ) ) {
			return $image_response;
		}

		if ( 200 !== wp_remote_retrieve_response_code( $image_response ) ) {
			return new \WP_Error( 'api_error', __( 'Failed to download edited image from Replicate.', 'superdraft' ) );
		}

		return wp_remote_retrieve_body( $image_response );
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
