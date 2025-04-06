<?php
/**
 * Superdraft Writing Tips module.
 *
 * @package Superdraft
 * @since 1.0.0
 */

namespace Superdraft;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WritingTips class.
 */
class Writing_Tips {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor_assets' ] );
		add_action( 'rest_api_init', [ $this, 'register_writing_tips_endpoint' ] );
		add_action( 'save_post', [ $this, 'save_writing_tips_meta' ] );
		add_action( 'init', [ $this, 'register_post_meta' ] );
	}

	/**
	 * Enqueue editor scripts and styles.
	 */
	public function enqueue_editor_assets() {
		wp_enqueue_script(
			'superdraft-writing-tips-js',
			SUPERDRAFT_URL . 'assets/admin/js/dist/writing-tips.js',
			[ 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-api-fetch', 'wp-i18n' ],
			SUPERDRAFT_VERSION,
			true
		);

		wp_set_script_translations( 'superdraft-writing-tips-js', 'superdraft', SUPERDRAFT_DIR . 'languages' );

		wp_enqueue_style(
			'superdraft-writing-tips-css',
			SUPERDRAFT_URL . 'assets/admin/css/writing-tips.css',
			[],
			SUPERDRAFT_VERSION
		);
	}

	/**
	 * Register the Writing Tips REST endpoint: /superdraft/v1/writing-tips/analyze
	 *
	 * This endpoint receives the post title/content, calls the AI or external API,
	 * and returns an array of tips.
	 */
	public function register_writing_tips_endpoint() {
		register_rest_route(
			'superdraft/v1',
			'/writing-tips/analyze',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'analyze_tips' ],
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
				'args'                => [
					'postTitle'   => [
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'postContent' => [
						'type'              => 'string',
						'sanitize_callback' => 'wp_kses_post',
					],
				],
			]
		);
	}

	/**
	 * Callback for the /writing-tips/analyze endpoint.
	 *
	 * Receives the post's title and content, then returns a list of "tips" (array).
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function analyze_tips( WP_REST_Request $request ) {
		$settings = get_option( 'superdraft_settings', [] );

		$post_title   = $request->get_param( 'postTitle' );
		$post_content = $request->get_param( 'postContent' );
		$post_type    = $request->get_param( 'postType' );

		$post_type_name = get_post_type_object( $post_type )->labels->singular_name;

		// IDs are not needed for the AI.
		$current_tips = $request->get_param( 'currentTips' );
		$current_tips = array_map(
			function ( $tip ) {
				return [
					'text'      => $tip['text'],
					'completed' => $tip['completed'],
				];
			},
			$current_tips
		);
		$current_tips = wp_json_encode( $current_tips, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );

		$api = Admin::get_api( get_option( 'superdraft_settings', [] )['writing_tips']['model'] );

		$prompt_template = $api->get_prompt_template( 'writing-tips' );
		$prompt          = $api->replace_vars(
			$prompt_template,
			[
				'postTitle'   => $post_title,
				'postContent' => $post_content,
				'postType'    => $post_type_name,
				'currentTips' => $current_tips,
				'tipsNumber'  => max( (int) $request->get_param( 'minTips' ), 5 ), // Use minTips parameter.
			]
		);

		if ( ! $prompt ) {
			return new WP_Error(
				'invalid_prompt',
				__( 'Invalid prompt template', 'superdraft' )
			);
		}

		try {
			$api_response = $api->send_prompt( $prompt );

			if ( is_wp_error( $api_response ) ) {
				return $api_response;
			}

			Admin::log_api_request(
				$api,
				[
					'prompt' => $prompt,
					'tool'   => 'writing-tips',
				]
			);

			// Strip off potential markdown.
			$api_response = preg_replace( '/^```(json)?\n(.*)\n```$/s', '$2', $api_response );

			$tips = json_decode( $api_response, true );

			if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $tips ) ) {
				return new WP_Error(
					'invalid_response',
					__( 'Invalid response format from API', 'superdraft' )
				);
			}

			// Format tips with unique IDs if they don't already have them.
			$formatted_tips = [];
			foreach ( $tips as $tip ) {
				if ( is_string( $tip ) ) {
					$formatted_tips[] = [
						'id'        => uniqid( 'tip_' ),
						'text'      => $tip,
						'completed' => false,
					];
				} else {
					if ( ! isset( $tip['id'] ) ) {
						$tip['id'] = uniqid( 'tip_' );
					}
					$formatted_tips[] = $tip;
				}
			}

			return rest_ensure_response( $formatted_tips );

		} catch ( \Exception $e ) {
			return new WP_Error(
				'api_error',
				$e->getMessage()
			);
		}
	}

	/**
	 * Save the writing tips data to post meta.
	 *
	 * @param int $post_id The ID of the post being saved.
	 */
	public function save_writing_tips_meta( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST['superdraft_writing_tips_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['superdraft_writing_tips_nonce'] ) ), 'superdraft_writing_tips_nonce' ) ) {
			return;
		}

		if ( ! isset( $_POST['superdraft_writing_tips'] ) ) {
			return;
		}

		$sanitized_tips = $this->sanitize_writing_tips( wp_unslash( $_POST['superdraft_writing_tips'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( $sanitized_tips !== null ) {
			update_post_meta( $post_id, 'superdraft_writing_tips', $sanitized_tips );
		}
	}

	/**
	 * Sanitize writing tips array.
	 *
	 * @param string $tips_raw Raw JSON string of tips.
	 * @return array|null Sanitized tips array or null if invalid.
	 */
	private function sanitize_writing_tips( $tips_raw ) {
		$tips = json_decode( $tips_raw, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return null; // Return null for invalid JSON.
		}

		if ( ! is_array( $tips ) ) {
			return null; // Return null for non-array values.
		}

		$sanitized_tips = [];
		foreach ( $tips as $tip ) {
			if ( ! isset( $tip['id'], $tip['text'], $tip['completed'] ) ) {
				continue;
			}

			$sanitized_tips[] = [
				'id'        => sanitize_text_field( $tip['id'] ),
				'text'      => sanitize_text_field( $tip['text'] ),
				'completed' => (bool) $tip['completed'],
			];
		}

		return $sanitized_tips; // Return the array even if empty.
	}

	/**
	 * Register post meta for writing tips.
	 */
	public function register_post_meta() {
		register_post_meta(
			'post',
			'superdraft_writing_tips',
			[
				'type'         => 'array',
				'single'       => true,
				'show_in_rest' => [
					'schema' => [
						'type'  => 'array',
						'items' => [
							'type'       => 'object',
							'properties' => [
								'id'        => [ 'type' => 'string' ],
								'text'      => [ 'type' => 'string' ],
								'completed' => [ 'type' => 'boolean' ],
							],
						],
					],
				],
			]
		);
	}
}
