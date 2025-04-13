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
					'postId'          => [ 'type' => 'integer' ], // if needed for other checks
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
	public function edit_image( $request ) {
		$post_id         = $request->get_param( 'postId' );
		$featuredImageId = $request->get_param( 'featuredImageId' );
		$prompt          = $request->get_param( 'prompt' );

		if ( ! $post_id || ! $featuredImageId || ! $prompt ) {
			return new \WP_Error( 'missing_parameters', __( 'Missing postId, featuredImageId, or prompt', 'superdraft' ) );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error( 'permission_denied', __( 'You do not have permission to edit this post', 'superdraft' ) );
		}

		$file_path = get_attached_file( $featuredImageId );
		if ( ! file_exists( $file_path ) ) {
			return new \WP_Error( 'file_not_found', __( 'Featured image file not found', 'superdraft' ) );
		}
		$image_data   = file_get_contents( $file_path );
		$image_base64 = base64_encode( $image_data );
		$file_type    = wp_check_filetype( basename( $file_path ), null );

		$settings = get_option( 'superdraft_settings', [] );
		if ( empty( $settings['images']['enabled'] ) ) {
			return new \WP_Error( 'module_disabled', __( 'Image generation module is disabled', 'superdraft' ) );
		}
		$image_model    = ! empty( $settings['images']['image_model'] ) ? $settings['images']['image_model'] : 'gemini-2.0-flash-exp-image-generation';
		$google_api_key = get_option( 'superdraft_api_keys', [] )['google'] ?? '';
		if ( empty( $google_api_key ) ) {
			return new \WP_Error( 'missing_api_key', __( 'Google API key is missing', 'superdraft' ) );
		}

		$api = new Google_Gemini_Image_API();
		$api->set_api_key( $google_api_key );
		$api->set_model( $image_model );

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

		$response = $api->send_prompt( $prompt, '', $override_body );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$edited_image_data = base64_decode( $response );
		if ( ! $edited_image_data ) {
			return new \WP_Error( 'invalid_image_data', __( 'Failed to decode image data', 'superdraft' ) );
		}

		$upload = wp_upload_bits( "superdraft-image-{$post_id}-edited-" . time() . '.png', null, $edited_image_data );
		if ( $upload['error'] ) {
			return new \WP_Error( 'upload_error', __( 'Failed to save edited image', 'superdraft' ) );
		}
		$file_path         = $upload['file'];
		$file_name         = basename( $file_path );
		$file_type         = wp_check_filetype( $file_name, null );
		$attachment        = [
			'post_mime_type' => $file_type['type'],
			'post_title'     => sanitize_file_name( $file_name ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		];
		$new_attachment_id = wp_insert_attachment( $attachment, $file_path, $post_id );
		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
		$attach_data = wp_generate_attachment_metadata( $new_attachment_id, $file_path );
		wp_update_attachment_metadata( $new_attachment_id, $attach_data );

		// Store the prompt and original image ID as meta.
		update_post_meta( $new_attachment_id, '_superdraft_image_prompt', $prompt );
		update_post_meta( $new_attachment_id, '_superdraft_original_image_id', $featuredImageId );

		// We set the new image as the featured image in the editor, in JS. No need to set it here.
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
		$prevPromptsText = '';
		if ( is_array( $previous_prompts ) && ! empty( $previous_prompts ) ) {
			$prevPromptsText = implode( "\n###\n", $previous_prompts );
		}

		$prompt = $api->replace_vars(
			$prompt_template,
			[
				'postTitle'       => $post_title,
				'postContent'     => $post_content,
				'postType'        => $post_type,
				'previousPrompts' => $prevPromptsText,
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
	public function generate_image( $request ) {
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

		$image_model = ! empty( $settings['images']['image_model'] )
			? $settings['images']['image_model']
			: 'gemini-2.0-flash-exp-image-generation';

		$google_api_key = get_option( 'superdraft_api_keys', [] )['google'] ?? '';
		if ( empty( $google_api_key ) ) {
			return new \WP_Error( 'missing_api_key', __( 'Google API key is missing', 'superdraft' ) );
		}

		// Use our new Image API class.
		$api = new Google_Gemini_Image_API();
		$api->set_api_key( $google_api_key );
		$api->set_model( $image_model );

		$response = $api->send_prompt( $prompt );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Log the API request.
		\Superdraft\Admin::log_api_request(
			$api,
			[
				'prompt' => $prompt,
				'tool'   => 'image-generation',
			]
		);

		// Decode the returned base64 image data.
		$image_data = base64_decode( $response );
		if ( ! $image_data ) {
			return new \WP_Error( 'invalid_image_data', __( 'Failed to decode image data', 'superdraft' ) );
		}

		// Save the image to the uploads directory.
		$upload = wp_upload_bits( "superdraft-image-{$post_id}-" . time() . '.png', null, $image_data );
		if ( $upload['error'] ) {
			return new \WP_Error( 'upload_error', __( 'Failed to save generated image', 'superdraft' ) );
		}

		$file_path  = $upload['file'];
		$file_name  = basename( $file_path );
		$file_type  = wp_check_filetype( $file_name, null );
		$attachment = [
			'post_mime_type' => $file_type['type'],
			'post_title'     => sanitize_file_name( $file_name ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		];

		$attachment_id = wp_insert_attachment( $attachment, $file_path, $post_id );
		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
		$attach_data = wp_generate_attachment_metadata( $attachment_id, $file_path );
		wp_update_attachment_metadata( $attachment_id, $attach_data );

		// Store the prompt as meta
		update_post_meta( $attachment_id, '_superdraft_image_prompt', $prompt );

		// No need to set the image as the featured image in the editor, in JS.
		return rest_ensure_response(
			[
				'attachment_id' => $attachment_id,
				'url'           => wp_get_attachment_url( $attachment_id ),
				'message'       => __( 'Featured image generated and set successfully', 'superdraft' ),
			]
		);
	}
}
