<?php
/**
 * Superdraft Autocomplete module.
 *
 * @package Superdraft
 * @since 1.0.0
 */

namespace Superdraft;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin class.
 */
class Autocomplete {
	/**
	 * API instance.
	 *
	 * @var object
	 */
	private $api;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor_assets' ] );
		add_action( 'rest_api_init', [ $this, 'register_autocomplete_endpoint' ] );
		add_action( 'rest_api_init', [ $this, 'register_smartcompose_endpoint' ] );

		add_action( 'enqueue_block_assets', [ $this, 'enqueue_iframe_styles' ] );
	}

	/**
	 * Enqueue editor scripts and styles.
	 */
	public function enqueue_editor_assets() {
		wp_enqueue_script(
			'superdraft-autocomplete-js',
			SUPERDRAFT_URL . 'assets/admin/js/dist/autocomplete.js',
			[ 'wp-blocks', 'wp-editor', 'wp-element', 'wp-api-fetch', 'wp-hooks', 'wp-components' ],
			SUPERDRAFT_VERSION,
			true
		);

		wp_enqueue_style(
			'superdraft-autocomplete-css',
			SUPERDRAFT_URL . 'assets/admin/css/autocomplete.css',
			[],
			SUPERDRAFT_VERSION
		);

		// Only load Smart Compose if it's enabled in settings.
		$settings              = get_option( 'superdraft_settings', [] );
		$smart_compose_enabled = isset( $settings['autocomplete']['smart_compose_enabled'] ) ?
			(bool) $settings['autocomplete']['smart_compose_enabled'] : false;

		if ( $smart_compose_enabled ) {
			wp_enqueue_script(
				'superdraft-smartcompose-js',
				SUPERDRAFT_URL . 'assets/admin/js/dist/smartcompose.js',
				[ 'wp-blocks', 'wp-editor', 'wp-element', 'wp-api-fetch', 'wp-hooks', 'wp-components' ],
				SUPERDRAFT_VERSION,
				true
			);
		}
	}

	/**
	 * Enqueue iframe styles for the editor.
	 */
	public function enqueue_iframe_styles() {
		if ( is_admin() ) {
			wp_enqueue_style(
				'superdraft-autocomplete-editor-css',
				SUPERDRAFT_URL . 'assets/admin/css/autocomplete-editor.css',
				[],
				SUPERDRAFT_VERSION
			);
		}
	}

	/**
	 * Register the autocomplete endpoint.
	 */
	public function register_autocomplete_endpoint() {
		register_rest_route(
			'superdraft/v1',
			'/autocomplete',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'get_autocomplete_suggestions' ],
				'args'                => [
					'search'           => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'blockContent'     => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => [ $this, 'sanitize_block_content' ],
					],
					'prevBlockContent' => [
						'type'              => 'string',
						'sanitize_callback' => [ $this, 'sanitize_block_content' ],
					],
					'nextBlockContent' => [
						'type'              => 'string',
						'sanitize_callback' => [ $this, 'sanitize_block_content' ],
					],
					'postTitle'        => [
						'type'              => 'string',
						'sanitize_callback' => [ $this, 'sanitize_post_title' ],
					],
				],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	/**
	 * Register the smartcompose endpoint.
	 */
	public function register_smartcompose_endpoint() {
		register_rest_route(
			'superdraft/v1',
			'/smartcompose',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'get_smartcompose_suggestion' ],
				'args'                => [
					'text'             => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => [ $this, 'sanitize_smartcompose_text' ],
					],
					'blockContent'     => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => [ $this, 'sanitize_block_content' ],
					],
					'prevBlockContent' => [
						'type'              => 'string',
						'sanitize_callback' => [ $this, 'sanitize_block_content' ],
					],
					'nextBlockContent' => [
						'type'              => 'string',
						'sanitize_callback' => [ $this, 'sanitize_block_content' ],
					],
					'postTitle'        => [
						'type'              => 'string',
						'sanitize_callback' => [ $this, 'sanitize_post_title' ],
					],
				],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	/**
	 * Check if the user has permission to access the autocomplete endpoint.
	 *
	 * @return bool Whether the user has permission.
	 */
	public function check_permission() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Sanitize block content. Allows only certain HTML tags.
	 *
	 * @param string $content The block content.
	 * @return string The sanitized block content.
	 */
	public function sanitize_block_content( $content ) {
		return wp_kses_post( $content );
	}

	/**
	 * Sanitize post title.
	 *
	 * @param string $title The post title.
	 * @return string The sanitized post title.
	 */
	public function sanitize_post_title( $title ) {
		return sanitize_text_field( $title );
	}

	/**
	 * Sanitize smartcompose text. Allows only certain HTML tags.
	 *
	 * @param string $text The text.
	 * @return string The sanitized text.
	 */
	public function sanitize_smartcompose_text( $text ) {
		return wp_kses_post( $text );
	}

	/**
	 * Get autocomplete suggestions.
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return WP_REST_Response The REST response.
	 */
	public function get_autocomplete_suggestions( $request ) {
		$settings = get_option( 'superdraft_settings', [] );
		$model    = $settings['autocomplete']['model'];

		$this->api = Admin::get_api( $model );

		$search             = $request->get_param( 'search' );
		$block_content      = $request->get_param( 'blockContent' );
		$prev_block_content = $request->get_param( 'prevBlockContent' );
		$prev_block_content = $prev_block_content ? $prev_block_content : '';
		$next_block_content = $request->get_param( 'nextBlockContent' );
		$next_block_content = $next_block_content ? $next_block_content : '';
		$post_title         = $request->get_param( 'postTitle' );
		$post_title         = $post_title ? $post_title : '';
		$post_type          = $request->get_param( 'postType' );
		$post_type          = $post_type ? $post_type : 'post';

		$post_type_name = get_post_type_object( $post_type )->labels->singular_name;

		$prompt_template = $this->api->get_prompt_template( 'autocomplete' );
		$prompt          = $this->api->replace_vars(
			$prompt_template,
			[
				'search'        => $search,
				'resultsNumber' => $settings['autocomplete']['suggestions'],
				'triggerPrefix' => $settings['autocomplete']['prefix'],
				'postTitle'     => $post_title,
				'previousBlock' => $prev_block_content,
				'currentBlock'  => $block_content,
				'nextBlock'     => $next_block_content,
				'postType'      => $post_type_name,
			]
		);

		try {
			$api_response = $this->api->send_prompt( $prompt );

			if ( is_wp_error( $api_response ) ) {
				return $api_response;
			}

			Admin::log_api_request(
				$this->api,
				[
					'prompt' => $prompt,
					'tool'   => 'autocomplete',
				]
			);

			// Strip off potential markdown.
			$api_response = preg_replace( '/^```(json)?\n(.*)\n```$/s', '$2', $api_response );

			$suggestions = json_decode( $api_response, true );

			if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $suggestions ) ) {
				return new \WP_Error(
					'invalid_response',
					__( 'Invalid response format from API', 'superdraft' )
				);
			}

			$response = [];
			foreach ( $suggestions as $index => $suggestion ) {
				$response[] = [
					'label'      => mb_strlen( $suggestion ) > 50 ? mb_substr( $suggestion, 0, 50 ) . '...' : $suggestion,
					'completion' => $suggestion,
					'keywords'   => [ $search ],
				];
			}

			return rest_ensure_response( $response );

		} catch ( \Exception $e ) {
			return new \WP_Error(
				'api_error',
				$e->getMessage()
			);
		}
	}

	/**
	 * Get smartcompose suggestions. This is a simplified version of the
	 * autocomplete endpoint. It only sends the text to the API and uses a
	 * low max_tokens value for speedy responses.
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return WP_REST_Response The REST response.
	 */
	public function get_smartcompose_suggestion( $request ) {
		$settings = get_option( 'superdraft_settings', [] );
		$model    = $settings['autocomplete']['smart_compose_model'];

		$this->api = Admin::get_api( $model );

		$text               = $request->get_param( 'text' );
		$block_content      = $request->get_param( 'blockContent' );
		$prev_block_content = $request->get_param( 'prevBlockContent' );
		$prev_block_content = $prev_block_content ? $prev_block_content : '';
		$next_block_content = $request->get_param( 'nextBlockContent' );
		$next_block_content = $next_block_content ? $next_block_content : '';
		$post_title         = $request->get_param( 'postTitle' );
		$post_title         = $post_title ? $post_title : '';
		$post_type          = $request->get_param( 'postType' );
		$post_type          = $post_type ? $post_type : 'post';

		$prompt_template = $this->api->get_prompt_template( 'smartcompose' );
		$prompt          = $this->api->replace_vars(
			$prompt_template,
			[
				'text'          => $text,
				'previousText'  => wp_strip_all_tags( $prev_block_content ),
				'postTitle'     => $post_title,
				'previousBlock' => $prev_block_content,
				'currentBlock'  => $block_content,
				'nextBlock'     => $next_block_content,
				'postType'      => get_post_type_object( $post_type )->labels->singular_name,
			]
		);

		try {
			$max_tokens = isset( $settings['autocomplete']['smart_compose_max_tokens'] ) ?
				intval( $settings['autocomplete']['smart_compose_max_tokens'] ) : 10;

			$this->api->set_max_tokens( $max_tokens );
			$this->api->set_temperature( 0.9 );
			$api_response = $this->api->send_prompt( $prompt );

			if ( is_wp_error( $api_response ) ) {
				return $api_response;
			}

			Admin::log_api_request(
				$this->api,
				[
					'prompt' => $prompt,
					'tool'   => 'smartcompose',
				]
			);

			if ( empty( $api_response ) ) {
				return new \WP_Error(
					'api_error',
					__( 'Error communicating with the API.', 'superdraft' )
				);
			}

			// If the text ends with a space and the response starts with a space, remove the first space.
			if ( substr( $text, -1 ) === ' ' && substr( $api_response, 0, 1 ) === ' ' ) {
				$api_response = substr( $api_response, 1 );
			}

			// Strip off possible newline at the end (but not a space).
			$api_response = rtrim( $api_response, "\n" );

			// Replace newlines with spaces.
			$api_response = preg_replace( '/\n+/', ' ', $api_response );

			// Replace multiple spaces with a single space.
			$api_response = preg_replace( '/\s+/', ' ', $api_response );

			return rest_ensure_response( [ 'text' => $api_response ] );

		} catch ( \Exception $e ) {
			return new \WP_Error(
				'api_error',
				$e->getMessage()
			);
		}
	}
}
