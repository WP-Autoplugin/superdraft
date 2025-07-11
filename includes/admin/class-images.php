<?php
/**
 * Superdraft Images module.
 *
 * @package Superdraft
 * @since 1.0.0
 */

namespace Superdraft;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Image Generation module.
 */
class Images {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor_assets' ] );
		add_action( 'rest_api_init', [ $this, 'register_image_endpoint' ] );
	}

	/**
	 * Enqueue the panel’s JS and CSS.
	 */
	public function enqueue_editor_assets() {
		wp_enqueue_script(
			'superdraft-image-generation-js',
			SUPERDRAFT_URL . 'assets/admin/js/dist/images.js',
			[ 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-api-fetch', 'wp-i18n' ],
			SUPERDRAFT_VERSION,
			true
		);
		wp_set_script_translations( 'superdraft-image-generation-js', 'superdraft', SUPERDRAFT_DIR . 'languages' );
		wp_enqueue_style(
			'superdraft-image-generation-css',
			SUPERDRAFT_URL . 'assets/admin/css/images.css',
			[],
			SUPERDRAFT_VERSION
		);
	}

	/**
	 * Register REST endpoint for image generation.
	 */
	public function register_image_endpoint() {
		register_rest_route(
			'superdraft/v1',
			'/image/generate',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'generate_image' ],
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
				'args'                => [
					'postId' => [
						'type' => 'integer',
					],
					'prompt' => [
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);

		register_rest_route(
			'superdraft/v1',
			'/image/edit',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'edit_image' ],
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
				'args'                => [
					'postId'          => [ 'type' => 'integer' ],
					'featuredImageId' => [ 'type' => 'integer' ],
					'prompt'          => [
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);

		register_rest_route(
			'superdraft/v1',
			'/image/generate-prompt',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'generate_prompt' ],
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
				'args'                => [
					'postId'          => [
						'type' => 'integer',
					],
					'postTitle'       => [
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'postContent'     => [
						'type'              => 'string',
						'sanitize_callback' => 'wp_kses_post',
					],
					'postType'        => [
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'previousPrompts' => [
						'type'    => 'array',
						'items'   => [
							'type' => 'string',
						],
						'default' => [],
					],
				],
			]
		);
	}

	/**
	 * Edit an image, and save it.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 */
	public function edit_image( \WP_REST_Request $request ) {
		$settings = get_option( 'superdraft_settings', [] );
		if ( empty( $settings['images']['enabled'] ) ) {
			return new \WP_Error( 'module_disabled', __( 'Image generation module is disabled', 'superdraft' ) );
		}

               $image_model = $settings['images']['image_edit_model'] ?? '';
               if ( '' === $image_model ) {
                       return new \WP_Error( 'edit_not_supported', __( 'Image editing is disabled', 'superdraft' ) );
               }

		$post_id           = $request->get_param( 'postId' );
		$featured_image_id = $request->get_param( 'featuredImageId' );
		$prompt            = $request->get_param( 'prompt' );

		if ( ! $post_id || ! $featured_image_id || ! $prompt ) {
			return new \WP_Error( 'missing_parameters', __( 'Missing postId, featuredImageId, or prompt', 'superdraft' ) );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error( 'permission_denied', __( 'You do not have permission to edit this post', 'superdraft' ) );
		}

		$file_path = get_attached_file( $featured_image_id );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return new \WP_Error( 'file_not_found', __( 'Featured image file not found or path is invalid', 'superdraft' ) );
		}

		$api_keys = get_option( 'superdraft_api_keys', [] );
		$api      = null;
		$response = null;

		// Determine API and call edit.
		if ( 'gpt-image-1' === $image_model ) {
			$openai_key = $api_keys['openai'] ?? '';
			if ( empty( $openai_key ) ) {
				return new \WP_Error( 'missing_api_key', __( 'OpenAI API key is missing', 'superdraft' ) );
			}
			$api = new OpenAI_Image_API();
			$api->set_api_key( $openai_key );
			$api->set_model( $image_model );
			// Pass the single file path directly.
			$response = $api->edit_image( $prompt, $file_path ); // Pass single path string.

		} elseif ( str_starts_with( $image_model, 'gemini-' ) ) { // Check for Gemini models.

			$google_key = $api_keys['google'] ?? '';
			if ( empty( $google_key ) ) {
				return new \WP_Error( 'missing_api_key', __( 'Google API key is missing', 'superdraft' ) );
			}
			$api = new Google_Gemini_Image_API();
			$api->set_api_key( $google_key );
			$api->set_model( $image_model );

			// Gemini needs base64 encoded data.
			$image_data = file_get_contents( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( false === $image_data ) {
				return new \WP_Error( 'file_read_error', __( 'Could not read featured image file.', 'superdraft' ) );
			}
			$image_base64 = base64_encode( $image_data ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			$file_type    = wp_check_filetype( basename( $file_path ), null );

			$override_body = [
				'contents'         => [
					[
						'parts' => [
							[ 'text' => $prompt ],
							[
								'inline_data' => [
									'mime_type' => $file_type['type'],
									'data'      => $image_base64,
								],
							],
						],
					],
				],
				'generationConfig' => [
					'responseModalities' => [ 'Text', 'Image' ],
				],
			];

			// Gemini uses send_prompt with overrides for editing.
			$response = $api->send_prompt( $prompt, '', $override_body );

			// Gemini returns base64, decode it.
			if ( ! is_wp_error( $response ) ) {
				$response = base64_decode( $response ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
				if ( false === $response ) {
					return new \WP_Error( 'decode_error', __( 'Failed to decode base64 image data from Google Gemini.', 'superdraft' ) );
				}
			}
		} else {
			// Replicate and potentially other models don't support editing.
			return new \WP_Error(
				'edit_not_supported',
				// translators: %s: model name.
				sprintf( __( 'Image editing is not available for the selected model (%s).', 'superdraft' ), $image_model )
			);
		}
		// End API determination.

		if ( is_wp_error( $response ) ) {
			// Log the failed request if API object exists.
			if ( $api ) {
				Admin::log_api_request(
					$api,
					[
						'prompt'  => $prompt, // Log prompt even on failure.
						'tool'    => 'image-edit-error',
						'message' => wp_json_encode(
							[
								'error' => $response->get_error_message(),
								'code'  => $response->get_error_code(),
							]
						), // Log error message and code.
					]
				);
			}
			return $response; // Return the WP_Error.
		}

		// Log the successful API request.
		Admin::log_api_request(
			$api,
			[
				'prompt' => $prompt,
				'tool'   => 'image-edit',
			]
		);

		// $response should now contain raw image bytes.
		$edited_image_data = $response;

		if ( ! $edited_image_data ) {
			// This case might be redundant if API classes return WP_Error, but keep as safety net.
			return new \WP_Error( 'invalid_image_data', __( 'API returned empty image data after editing', 'superdraft' ) );
		}

		// Save the new image (common logic).
		$upload = wp_upload_bits( "superdraft-image-{$post_id}-edited-" . time() . '.png', null, $edited_image_data );
		if ( ! empty( $upload['error'] ) ) {
			// translators: %s: error message.
			return new \WP_Error( 'upload_error', sprintf( __( 'Failed to save edited image: %s', 'superdraft' ), $upload['error'] ) );
		}
		$file_path         = $upload['file'];
		$file_name         = basename( $file_path );
		$file_type         = wp_check_filetype( $file_name, null );
		$attachment        = [
			'post_mime_type' => $file_type['type'],
			'post_title'     => sanitize_file_name( $file_name ),
			'post_content'   => '', // Or add prompt here?
			'post_status'    => 'inherit',
		];
		$new_attachment_id = wp_insert_attachment( $attachment, $file_path, $post_id );
		if ( is_wp_error( $new_attachment_id ) ) {
			// Attempt to clean up the uploaded file if attachment creation failed.
			wp_delete_file( $file_path );
			return $new_attachment_id;
		}
		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
		$attach_data = wp_generate_attachment_metadata( $new_attachment_id, $file_path );
		wp_update_attachment_metadata( $new_attachment_id, $attach_data );

		// Store the prompt and original image ID as meta.
		update_post_meta( $new_attachment_id, '_superdraft_image_prompt', $prompt );
		update_post_meta( $new_attachment_id, '_superdraft_original_image_id', $featured_image_id );
		update_post_meta( $new_attachment_id, '_superdraft_image_model', $image_model ); // Store model used.

		return rest_ensure_response(
			[
				'attachment_id' => $new_attachment_id,
				'url'           => wp_get_attachment_url( $new_attachment_id ),
				'message'       => __( 'Edited image generated successfully', 'superdraft' ),
			]
		);
	}

	/**
	 * Generate a prompt for the image generation.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 */
	public function generate_prompt( $request ) {
		$post_id          = $request->get_param( 'postId' );
		$post_title       = $request->get_param( 'postTitle' );
		$post_content     = $request->get_param( 'postContent' );
		$post_type        = $request->get_param( 'postType' );
		$previous_prompts = $request->get_param( 'previousPrompts' );

		if ( ! $post_id || ! $post_title || ! $post_content || ! $post_type ) {
			return new \WP_Error( 'missing_parameters', __( 'Missing parameters', 'superdraft' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new \WP_Error( 'invalid_post', __( 'Invalid post ID', 'superdraft' ) );
		}
		$settings     = get_option( 'superdraft_settings', [] );
		$prompt_model = $settings['images']['prompt_model'] ?? 'gpt-4o-mini';
		$api          = \Superdraft\Admin::get_api( $prompt_model );
		if ( ! $api ) {
			return new \WP_Error( 'invalid_model', __( 'Invalid prompt model', 'superdraft' ) );
		}

		$prompt_template = $api->get_prompt_template( 'image-prompt' );
		if ( ! $prompt_template ) {
			return new \WP_Error( 'invalid_prompt', __( 'Invalid prompt template', 'superdraft' ) );
		}

		// Join previous prompts if they exist.
		$previous_prompts_text = '';
		if ( is_array( $previous_prompts ) && ! empty( $previous_prompts ) ) {
			$previous_prompts_text = implode( "\n###\n", $previous_prompts );
		}

		$prompt = $api->replace_vars(
			$prompt_template,
			[
				'postTitle'       => $post_title,
				'postContent'     => $post_content,
				'postType'        => $post_type,
				'previousPrompts' => $previous_prompts_text,
			]
		);

		// Set a high temperature for more creative responses.
		$api->set_temperature( 1 );

		$response = $api->send_prompt( $prompt );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Log the API request.
		\Superdraft\Admin::log_api_request(
			$api,
			[
				'prompt' => $prompt,
				'tool'   => 'image-prompt',
			]
		);

		return rest_ensure_response( [ 'prompt' => trim( $response ) ] );
	}

	/**
	 * Generate an image, save it, and return the attachment ID.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 */
	public function generate_image( \WP_REST_Request $request ) {
		$post_id = $request->get_param( 'postId' );
		$prompt  = $request->get_param( 'prompt' );

		if ( ! $post_id || ! $prompt ) {
			return new \WP_Error( 'missing_parameters', __( 'Missing postId or prompt', 'superdraft' ) );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error( 'permission_denied', __( 'You do not have permission to edit this post', 'superdraft' ) );
		}

		$settings = get_option( 'superdraft_settings', [] );
		if ( empty( $settings['images']['enabled'] ) ) {
			return new \WP_Error( 'module_disabled', __( 'Image generation module is disabled', 'superdraft' ) );
		}

		$image_model = $settings['images']['image_model'] ?? 'gemini-2.0-flash-exp-image-generation'; // Or your chosen default.
		$api_keys    = get_option( 'superdraft_api_keys', [] );
		$api         = null;
		$response    = null; // Will hold raw image data or WP_Error.

		// Determine API and call generate.
		if ( 'gpt-image-1' === $image_model ) {
			$openai_key = $api_keys['openai'] ?? '';
			if ( empty( $openai_key ) ) {
				return new \WP_Error( 'missing_api_key', __( 'OpenAI API key is missing', 'superdraft' ) );
			}
			$api = new OpenAI_Image_API();
			$api->set_api_key( $openai_key );
			$api->set_model( $image_model );
			$response = $api->send_prompt( $prompt ); // Returns raw bytes or WP_Error.

		} elseif ( str_starts_with( $image_model, 'gemini-' ) ) { // Google Gemini.
			$google_key = $api_keys['google'] ?? '';
			if ( empty( $google_key ) ) {
				return new \WP_Error( 'missing_api_key', __( 'Google API key is missing', 'superdraft' ) );
			}
			$api = new Google_Gemini_Image_API();
			$api->set_api_key( $google_key );
			$api->set_model( $image_model );
			$response = $api->send_prompt( $prompt ); // Returns base64 or WP_Error.

			// Decode if successful.
			if ( ! is_wp_error( $response ) ) {
				$response = base64_decode( $response ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
				if ( false === $response ) {
					return new \WP_Error( 'decode_error', __( 'Failed to decode base64 image data from Google Gemini.', 'superdraft' ) );
				}
			}
		} elseif ( str_contains( $image_model, '/' ) ) { // Replicate.
			$replicate_key = $api_keys['replicate'] ?? '';
			if ( empty( $replicate_key ) ) {
				return new \WP_Error( 'missing_api_key', __( 'Replicate API key is missing', 'superdraft' ) );
			}
			$api = new Replicate_Image_API();
			$api->set_api_key( $replicate_key );
			$api->set_model( $image_model );
			$response = $api->send_prompt( $prompt ); // Returns raw bytes or WP_Error.
		} else {
			// translators: %s: model name.
			return new \WP_Error( 'unknown_model', sprintf( __( 'Unknown or unsupported image model: %s', 'superdraft' ), $image_model ) );
		}
		// End API determination.

		if ( is_wp_error( $response ) ) {
			// Log the failed request if API object exists.
			if ( $api ) {
				Admin::log_api_request(
					$api,
					[
						'prompt'  => $prompt, // Log prompt even on failure.
						'tool'    => 'image-generate-error',
						'message' => wp_json_encode( [ 'error' => $response->get_error_message() ] ), // Log error.
					]
				);
			}
			return $response; // Return the WP_Error.
		}

		// Log the successful API request.
		Admin::log_api_request(
			$api,
			[
				'prompt' => $prompt,
				'tool'   => 'image-generate',
			]
		);

		// $response should now contain raw image bytes.
		$image_data = $response;

		if ( ! $image_data ) {
			// Safety net.
			return new \WP_Error( 'invalid_image_data', __( 'API returned empty image data after generation', 'superdraft' ) );
		}

		// Save the new image (common logic).
		$upload = wp_upload_bits( "superdraft-image-{$post_id}-" . time() . '.png', null, $image_data );
		if ( ! empty( $upload['error'] ) ) {
			// translators: %s: error message.
			return new \WP_Error( 'upload_error', sprintf( __( 'Failed to save generated image: %s', 'superdraft' ), $upload['error'] ) );
		}

		$file_path  = $upload['file'];
		$file_name  = basename( $file_path );
		$file_type  = wp_check_filetype( $file_name, null );
		$attachment = [
			'post_mime_type' => $file_type['type'],
			'post_title'     => sanitize_file_name( $file_name ),
			'post_content'   => '', // Or add prompt here?
			'post_status'    => 'inherit',
		];

		$attachment_id = wp_insert_attachment( $attachment, $file_path, $post_id );
		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}
		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
		$attach_data = wp_generate_attachment_metadata( $attachment_id, $file_path );
		wp_update_attachment_metadata( $attachment_id, $attach_data );

		// Store the prompt and model as meta.
		update_post_meta( $attachment_id, '_superdraft_image_prompt', $prompt );
		update_post_meta( $attachment_id, '_superdraft_image_model', $image_model ); // Store model used.

		return rest_ensure_response(
			[
				'attachment_id' => $attachment_id,
				'url'           => wp_get_attachment_url( $attachment_id ),
				'message'       => __( 'Featured image generated and set successfully', 'superdraft' ),
			]
		);
	}
}
