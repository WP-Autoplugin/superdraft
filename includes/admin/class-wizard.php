<?php
/**
 * Superdraft Setup Wizard class.
 *
 * @package Superdraft
 * @since 1.1.5
 */

namespace Superdraft;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Setup Wizard class.
 */
class Wizard {

	/**
	 * Constructor: Set up hooks and actions.
	 */
	public function __construct() {
		add_action( 'admin_init', [ $this, 'maybe_redirect_to_wizard' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ], 20 );
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );

		// Add AJAX handlers for the wizard.
		add_action( 'wp_ajax_superdraft_wizard_save_api', [ $this, 'ajax_save_api' ] );
		add_action( 'wp_ajax_superdraft_wizard_test_api', [ $this, 'ajax_test_api' ] );
		add_action( 'wp_ajax_superdraft_wizard_dismiss', [ $this, 'ajax_dismiss' ] );
		add_action( 'wp_ajax_superdraft_wizard_create_demo', [ $this, 'ajax_create_demo' ] );
		add_action( 'wp_ajax_superdraft_wizard_save_modules', [ $this, 'ajax_save_modules' ] );
	}

	/**
	 * Check if the wizard should be shown.
	 *
	 * @return bool
	 */
	public static function should_show_wizard() {
		// If dismissed, don't show.
		if ( get_option( 'superdraft_wizard_dismissed' ) ) {
			return false;
		}

		// If API keys are configured, don't show.
		$api_keys = get_option( 'superdraft_api_keys', [] );
		$has_key  = false;
		foreach ( $api_keys as $key => $value ) {
			if ( ! empty( $value ) ) {
				$has_key = true;
				break;
			}
		}

		return ! $has_key;
	}

	/**
	 * Maybe redirect to the wizard on first activation.
	 */
	public function maybe_redirect_to_wizard() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin page check.
		if ( isset( $_GET['page'] ) && 'superdraft-wizard' === sanitize_text_field( wp_unslash( $_GET['page'] ) ) ) {
			return;
		}

		if ( ! self::should_show_wizard() ) {
			return;
		}

		// Only redirect once.
		if ( get_transient( 'superdraft_wizard_redirect' ) ) {
			delete_transient( 'superdraft_wizard_redirect' );
			wp_safe_redirect( admin_url( 'admin.php?page=superdraft-wizard' ) );
			exit;
		}
	}

	/**
	 * Trigger wizard redirect on activation.
	 */
	public static function trigger_redirect() {
		set_transient( 'superdraft_wizard_redirect', true, 30 );
	}

	/**
	 * Enqueue wizard assets.
	 *
	 * @param string $hook The current admin page.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'superdraft_page_superdraft-wizard' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'superdraft-wizard-css', SUPERDRAFT_URL . 'assets/admin/css/wizard.css', [], SUPERDRAFT_VERSION );
		wp_enqueue_script( 'superdraft-wizard-js', SUPERDRAFT_URL . 'assets/admin/js/dist/wizard.js', [ 'jquery' ], SUPERDRAFT_VERSION, true );

		wp_localize_script(
			'superdraft-wizard-js',
			'superdraftWizard',
			[
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'superdraft_wizard_nonce' ),
				'i18n'     => [
					'invalidKey'        => __( 'Please enter a valid API key.', 'superdraft' ),
					'testing'           => __( 'Testing connection...', 'superdraft' ),
					'connectionSuccess' => __( 'Connection successful! Your API key is valid.', 'superdraft' ),
					'connectionError'   => __( 'Connection failed. Please check your API key and try again.', 'superdraft' ),
					'saving'            => __( 'Saving...', 'superdraft' ),
					'creatingDemo'      => __( 'Creating demo post...', 'superdraft' ),
					'demoCreated'       => __( 'Demo post created! Opening editor...', 'superdraft' ),
				],
			]
		);
	}

	/**
	 * Register REST API routes.
	 */
	public function register_rest_routes() {
		register_rest_route(
			'superdraft/v1',
			'/test-api',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'rest_test_api' ],
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
				'args'                => [
					'provider' => [
						'required' => true,
						'type'     => 'string',
					],
					'api_key'  => [
						'required' => true,
						'type'     => 'string',
					],
				],
			]
		);
	}

	/**
	 * REST API handler for testing API key.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response The response.
	 */
	public function rest_test_api( $request ) {
		$provider = sanitize_text_field( $request->get_param( 'provider' ) );
		$api_key  = sanitize_text_field( $request->get_param( 'api_key' ) );

		$result = $this->test_api_key( $provider, $api_key );

		if ( is_wp_error( $result ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => $result->get_error_message(),
				],
				400
			);
		}

		return new \WP_REST_Response(
			[
				'success' => true,
				'message' => __( 'Connection successful!', 'superdraft' ),
			],
			200
		);
	}

	/**
	 * AJAX handler for saving API key.
	 */
	public function ajax_save_api() {
		check_ajax_referer( 'superdraft_wizard_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'You do not have permission to do this.', 'superdraft' ) );
		}

		$provider = isset( $_POST['provider'] ) ? sanitize_text_field( wp_unslash( $_POST['provider'] ) ) : '';
		$api_key  = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';
		if ( empty( $provider ) || empty( $api_key ) ) {
			wp_send_json_error( __( 'Invalid provider or API key.', 'superdraft' ) );
		}

		$api_keys = get_option( 'superdraft_api_keys', [] );
		if ( ! is_array( $api_keys ) ) {
			$api_keys = [];
		}

		$api_keys[ $provider ] = $api_key;
		update_option( 'superdraft_api_keys', $api_keys );

		// Save feature-specific model defaults in settings.
		$settings = get_option( 'superdraft_settings', [] );
		if ( ! is_array( $settings ) ) {
			$settings = [];
		}

		$model_defaults = self::get_provider_model_defaults( $provider );
		if ( ! isset( $settings['tags_categories'] ) || ! is_array( $settings['tags_categories'] ) ) {
			$settings['tags_categories'] = [];
		}
		if ( ! isset( $settings['writing_tips'] ) || ! is_array( $settings['writing_tips'] ) ) {
			$settings['writing_tips'] = [];
		}
		if ( ! isset( $settings['autocomplete'] ) || ! is_array( $settings['autocomplete'] ) ) {
			$settings['autocomplete'] = [];
		}
		if ( ! isset( $settings['images'] ) || ! is_array( $settings['images'] ) ) {
			$settings['images'] = [];
		}

		$settings['tags_categories']['model']                 = $model_defaults['tags_categories'];
		$settings['tags_categories']['suggestions_context']   = 5;
		$settings['writing_tips']['model']                    = $model_defaults['writing_tips'];
		$settings['autocomplete']['model']                    = $model_defaults['autocomplete'];
		$settings['autocomplete']['context_length']           = 1;
		$settings['autocomplete']['smart_compose_model']      = $model_defaults['smart_compose'];
		$settings['autocomplete']['smart_compose_max_tokens'] = 10;
		$settings['images']['prompt_model']                   = $model_defaults['image_prompt'];

		update_option( 'superdraft_settings', $settings );

		wp_send_json_success( __( 'API key saved successfully.', 'superdraft' ) );
	}

	/**
	 * Get provider-specific wizard model defaults.
	 *
	 * These favor low-latency models for inline UX, a stronger fast model for
	 * autocomplete, and the strongest practical model for review-style features.
	 *
	 * @param string|null $provider Optional provider key.
	 * @return array
	 */
	public static function get_provider_model_defaults( $provider = null ) {
		$defaults = [
			'openai'    => [
				'smart_compose'   => 'gpt-5-nano',
				'autocomplete'    => 'gpt-5.1-instant',
				'tags_categories' => 'gpt-5.1-instant',
				'writing_tips'    => 'gpt-5.2',
				'image_prompt'    => 'gpt-5.1-instant',
			],
			'anthropic' => [
				'smart_compose'   => 'claude-3-haiku-20240307',
				'autocomplete'    => 'claude-3-5-haiku-20241022',
				'tags_categories' => 'claude-3-5-haiku-20241022',
				'writing_tips'    => 'claude-opus-4-5-20251101',
				'image_prompt'    => 'claude-3-5-haiku-20241022',
			],
			'google'    => [
				'smart_compose'   => 'gemini-2.5-flash-lite',
				'autocomplete'    => 'gemini-2.5-flash',
				'tags_categories' => 'gemini-2.5-flash',
				'writing_tips'    => 'gemini-3-pro-preview',
				'image_prompt'    => 'gemini-2.5-flash',
			],
			'xai'       => [
				'smart_compose'   => 'grok-3-mini',
				'autocomplete'    => 'grok-4-1-fast-non-reasoning',
				'tags_categories' => 'grok-4-1-fast-non-reasoning',
				'writing_tips'    => 'grok-4',
				'image_prompt'    => 'grok-4-1-fast-non-reasoning',
			],
			'custom'    => [
				'smart_compose'   => '',
				'autocomplete'    => '',
				'tags_categories' => '',
				'writing_tips'    => '',
				'image_prompt'    => '',
			],
		];

		if ( null !== $provider ) {
			return $defaults[ $provider ] ?? $defaults['custom'];
		}

		return $defaults;
	}

	/**
	 * AJAX handler for testing API key.
	 */
	public function ajax_test_api() {
		check_ajax_referer( 'superdraft_wizard_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'You do not have permission to do this.', 'superdraft' ) );
		}

		$provider = isset( $_POST['provider'] ) ? sanitize_text_field( wp_unslash( $_POST['provider'] ) ) : '';
		$api_key  = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';

		if ( empty( $provider ) || empty( $api_key ) ) {
			wp_send_json_error( __( 'Invalid provider or API key.', 'superdraft' ) );
		}

		$result = $this->test_api_key( $provider, $api_key );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( __( 'Connection successful! Your API key is valid.', 'superdraft' ) );
	}

	/**
	 * Test an API key for the given provider.
	 *
	 * @param string $provider The provider.
	 * @param string $api_key  The API key.
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	private function test_api_key( $provider, $api_key ) {
		// Basic format validation.
		$validation = $this->validate_key_format( $provider, $api_key );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Try to make a lightweight API call.
		$test_url = '';
		$headers  = [
			'Content-Type' => 'application/json',
		];

		switch ( $provider ) {
			case 'openai':
				$test_url                 = 'https://api.openai.com/v1/models';
				$headers['Authorization'] = 'Bearer ' . $api_key;
				break;
			case 'anthropic':
				$test_url                     = 'https://api.anthropic.com/v1/models';
				$headers['x-api-key']         = $api_key;
				$headers['anthropic-version'] = '2023-06-01';
				break;
			case 'google':
				$test_url = 'https://generativelanguage.googleapis.com/v1beta/models?key=' . $api_key;
				break;
			case 'xai':
				$test_url                 = 'https://api.x.ai/v1/models';
				$headers['Authorization'] = 'Bearer ' . $api_key;
				break;
			case 'custom':
				// Custom models don't have a known test endpoint.
				return true;
			default:
				return new \WP_Error( 'invalid_provider', __( 'Invalid provider selected.', 'superdraft' ) );
		}

		$response = wp_remote_get(
			$test_url,
			[
				'timeout' => 15,
				'headers' => $headers,
			]
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'connection_error', __( 'Could not connect to the API. Please check your internet connection.', 'superdraft' ) );
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 === $code ) {
			return true;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		$message = __( 'Invalid API key or API error. Please check your key and try again.', 'superdraft' );
		if ( ! empty( $data['error']['message'] ) ) {
			$message = $data['error']['message'];
		} elseif ( 401 === $code ) {
			$message = __( 'Invalid API key. Please check your key and try again.', 'superdraft' );
		}

		return new \WP_Error( 'api_error', $message );
	}

	/**
	 * Validate API key format.
	 *
	 * @param string $provider The provider.
	 * @param string $api_key  The API key.
	 * @return true|\WP_Error True on valid, WP_Error on invalid.
	 */
	private function validate_key_format( $provider, $api_key ) {
		$api_key = trim( $api_key );

		if ( empty( $api_key ) ) {
			return new \WP_Error( 'empty_key', __( 'API key cannot be empty.', 'superdraft' ) );
		}

		switch ( $provider ) {
			case 'openai':
				if ( strpos( $api_key, 'sk-' ) !== 0 ) {
					return new \WP_Error( 'invalid_format', __( 'OpenAI API keys should start with "sk-".', 'superdraft' ) );
				}
				break;
			case 'anthropic':
				if ( strpos( $api_key, 'sk-ant-' ) !== 0 ) {
					return new \WP_Error( 'invalid_format', __( 'Anthropic API keys should start with "sk-ant-".', 'superdraft' ) );
				}
				break;
			case 'xai':
				if ( strpos( $api_key, 'xai-' ) !== 0 ) {
					return new \WP_Error( 'invalid_format', __( 'xAI API keys should start with "xai-".', 'superdraft' ) );
				}
				break;
			case 'google':
				// Google keys don't have a specific prefix but are typically long.
				if ( strlen( $api_key ) < 20 ) {
					return new \WP_Error( 'invalid_format', __( 'Google API key seems too short. Please check your key.', 'superdraft' ) );
				}
				break;
			case 'custom':
				// No validation for custom.
				break;
		}

		return true;
	}

	/**
	 * AJAX handler for dismissing the wizard.
	 */
	public function ajax_dismiss() {
		check_ajax_referer( 'superdraft_wizard_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'You do not have permission to do this.', 'superdraft' ) );
		}

		update_option( 'superdraft_wizard_dismissed', true );
		wp_send_json_success( __( 'Wizard dismissed.', 'superdraft' ) );
	}

	/**
	 * AJAX handler for creating a demo post.
	 */
	public function ajax_create_demo() {
		check_ajax_referer( 'superdraft_wizard_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'You do not have permission to do this.', 'superdraft' ) );
		}

		$post_id = wp_insert_post(
			[
				'post_title'   => __( 'Welcome to Superdraft — Your AI Writing Assistant', 'superdraft' ),
				'post_content' => $this->get_demo_content(),
				'post_status'  => 'draft',
				'post_type'    => 'post',
			]
		);

		if ( is_wp_error( $post_id ) ) {
			wp_send_json_error( __( 'Failed to create demo post.', 'superdraft' ) );
		}

		// Enable all modules for the demo.
		$settings = get_option( 'superdraft_settings', [] );
		$modules  = [ 'tags_categories', 'writing_tips', 'autocomplete', 'images' ];
		foreach ( $modules as $module ) {
			if ( isset( $settings[ $module ] ) ) {
				$settings[ $module ]['enabled'] = true;
			} else {
				$settings[ $module ] = [ 'enabled' => true ];
			}
		}
		update_option( 'superdraft_settings', $settings );

		wp_send_json_success(
			[
				'post_id'  => $post_id,
				'edit_url' => admin_url( 'post.php?post=' . $post_id . '&action=edit' ),
			]
		);
	}

	/**
	 * AJAX handler for saving enabled modules.
	 */
	public function ajax_save_modules() {
		check_ajax_referer( 'superdraft_wizard_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'You do not have permission to do this.', 'superdraft' ) );
		}

		$modules  = isset( $_POST['modules'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['modules'] ) ) : [];
		$settings = get_option( 'superdraft_settings', [] );

		if ( ! is_array( $settings ) ) {
			$settings = [];
		}

		foreach ( $modules as $module => $enabled ) {
			if ( isset( $settings[ $module ] ) ) {
				$settings[ $module ]['enabled'] = (bool) $enabled;
			} else {
				$settings[ $module ] = [ 'enabled' => (bool) $enabled ];
			}
		}

		update_option( 'superdraft_settings', $settings );
		wp_send_json_success( __( 'Modules saved successfully.', 'superdraft' ) );
	}

	/**
	 * Get demo post content.
	 *
	 * @return string
	 */
	private function get_demo_content() {
		$content  = '<!-- wp:paragraph -->' . "\n";
		$content .= '<p>' . __( 'Welcome to Superdraft! This is a demo post to help you explore the AI-powered features of the plugin. Superdraft enhances your WordPress writing experience with smart automation, intelligent recommendations, and predictive features.', 'superdraft' ) . '</p>' . "\n";
		$content .= '<!-- /wp:paragraph -->' . "\n\n";

		$content .= '<!-- wp:heading -->' . "\n";
		$content .= '<h2 class="wp-block-heading">' . __( 'Try Smart Compose', 'superdraft' ) . '</h2>' . "\n";
		$content .= '<!-- /wp:heading -->' . "\n\n";

		$content .= '<!-- wp:paragraph -->' . "\n";
		$content .= '<p>' . __( 'Smart Compose provides real-time suggestions as you type in paragraph blocks. Just start typing, and the AI will suggest how to continue your sentence. This feature is great for overcoming writer\'s block and speeding up your content creation process.', 'superdraft' ) . '</p>' . "\n";
		$content .= '<!-- /wp:paragraph -->' . "\n\n";

		$content .= '<!-- wp:heading -->' . "\n";
		$content .= '<h2 class="wp-block-heading">' . __( 'Try Autocomplete', 'superdraft' ) . '</h2>' . "\n";
		$content .= '<!-- /wp:heading -->' . "\n\n";

		$content .= '<!-- wp:paragraph -->' . "\n";
		$content .= '<p>' . __( 'Use the autocomplete feature by typing the trigger prefix (default is ~) followed by your query. The AI will suggest completions based on your content context. This helps you write faster and more consistently.', 'superdraft' ) . '</p>' . "\n";
		$content .= '<!-- /wp:paragraph -->' . "\n\n";

		$content .= '<!-- wp:heading -->' . "\n";
		$content .= '<h2 class="wp-block-heading">' . __( 'Try AI Tags & Categories', 'superdraft' ) . '</h2>' . "\n";
		$content .= '<!-- /wp:heading -->' . "\n\n";

		$content .= '<!-- wp:paragraph -->' . "\n";
		$content .= '<p>' . __( 'Superdraft can automatically suggest and select tags and categories for your posts based on the content. This saves time and helps with SEO and content organization.', 'superdraft' ) . '</p>' . "\n";
		$content .= '<!-- /wp:paragraph -->' . "\n\n";

		$content .= '<!-- wp:heading -->' . "\n";
		$content .= '<h2 class="wp-block-heading">' . __( 'Try AI Image Generation', 'superdraft' ) . '</h2>' . "\n";
		$content .= '<!-- /wp:heading -->' . "\n\n";

		$content .= '<!-- wp:paragraph -->' . "\n";
		$content .= '<p>' . __( 'Generate a featured image for your post using AI. Simply describe what you want, and Superdraft will create an image for you. You can also use AI to enhance your image prompts based on your post content.', 'superdraft' ) . '</p>' . "\n";
		$content .= '<!-- /wp:paragraph -->' . "\n\n";

		$content .= '<!-- wp:heading -->' . "\n";
		$content .= '<h2 class="wp-block-heading">' . __( 'Try Writing Tips', 'superdraft' ) . '</h2>' . "\n";
		$content .= '<!-- /wp:heading -->' . "\n\n";

		$content .= '<!-- wp:paragraph -->' . "\n";
		$content .= '<p>' . __( 'Get real-time writing, SEO, and readability tips while editing your posts. The AI analyzes your content and provides actionable suggestions to improve your writing quality and search engine visibility.', 'superdraft' ) . '</p>' . "\n";
		$content .= '<!-- /wp:paragraph -->';

		return $content;
	}

	/**
	 * Show wizard notice on admin pages.
	 */
	public function show_wizard_notice() {
		if ( ! self::should_show_wizard() ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		// Don't show on the wizard page itself.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin page check.
		if ( isset( $_GET['page'] ) && 'superdraft-wizard' === sanitize_text_field( wp_unslash( $_GET['page'] ) ) ) {
			return;
		}

		// Only show on Superdraft settings and dashboard.
		$allowed_screens = [ 'toplevel_page_superdraft-settings', 'dashboard' ];
		if ( ! in_array( $screen->id, $allowed_screens, true ) ) {
			return;
		}
		?>
		<div class="notice notice-info is-dismissible">
			<p>
				<?php esc_html_e( 'Welcome to Superdraft! Let\'s set up your AI writing assistant.', 'superdraft' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=superdraft-wizard' ) ); ?>" class="button button-primary">
					<?php esc_html_e( 'Start Setup Wizard', 'superdraft' ); ?>
				</a>
				<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-ajax.php?action=superdraft_wizard_dismiss' ), 'superdraft_wizard_nonce' ) ); ?>" class="button button-secondary">
					<?php esc_html_e( 'Dismiss', 'superdraft' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Render the wizard page.
	 */
	public function render_wizard_page() {
		include SUPERDRAFT_DIR . 'views/page-wizard.php';
	}
}
