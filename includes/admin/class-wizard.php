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

		$result = API_Key_Tester::test( $provider, $api_key );

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

		$custom_model = null;
		if ( 'custom' === $provider ) {
			$custom_model = $this->get_custom_model_from_request( $api_key );
			if ( is_wp_error( $custom_model ) ) {
				wp_send_json_error( $custom_model->get_error_message() );
			}
			$this->save_custom_model( $custom_model );
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
		if ( 'custom' === $provider && ! empty( $custom_model['name'] ) ) {
			$model_defaults = array_fill_keys( array_keys( $model_defaults ), $custom_model['name'] );
		}

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

		if ( 'custom' === $provider ) {
			$custom_model = $this->get_custom_model_from_request( $api_key );
			if ( is_wp_error( $custom_model ) ) {
				wp_send_json_error( $custom_model->get_error_message() );
			}
		}

		$result = API_Key_Tester::test( $provider, $api_key );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( __( 'Connection successful! Your API key is valid.', 'superdraft' ) );
	}

	/**
	 * Get the custom model payload posted by the wizard.
	 *
	 * @param string $api_key API key entered in the wizard's shared API key field.
	 * @return array|\WP_Error
	 */
	private function get_custom_model_from_request( $api_key ) {
		$raw_model = [];

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce is checked by the AJAX handlers before this helper is called.
		if ( isset( $_POST['custom_model'] ) && is_array( $_POST['custom_model'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Values are sanitized below.
			$raw_model = wp_unslash( (array) $_POST['custom_model'] );
		}

		$model = [
			'name'           => isset( $raw_model['name'] ) ? sanitize_text_field( $raw_model['name'] ) : '',
			'url'            => isset( $raw_model['url'] ) ? esc_url_raw( $raw_model['url'] ) : '',
			'modelParameter' => isset( $raw_model['modelParameter'] ) ? sanitize_text_field( $raw_model['modelParameter'] ) : '',
			'apiKey'         => sanitize_text_field( $api_key ),
			'headers'        => isset( $raw_model['headers'] ) ? array_map( 'sanitize_text_field', (array) $raw_model['headers'] ) : [],
		];

		if ( empty( $model['name'] ) || empty( $model['url'] ) || empty( $model['apiKey'] ) ) {
			return new \WP_Error( 'invalid_custom_model', __( 'Enter your custom model name, endpoint URL, and API key.', 'superdraft' ) );
		}

		return $model;
	}

	/**
	 * Save or replace a custom model configured from the wizard.
	 *
	 * @param array $model Sanitized custom model data.
	 */
	private function save_custom_model( $model ) {
		$custom_models = get_option( 'superdraft_custom_models', [] );
		if ( ! is_array( $custom_models ) ) {
			$custom_models = [];
		}

		$updated = false;
		foreach ( $custom_models as $index => $custom_model ) {
			if ( isset( $custom_model['name'] ) && $custom_model['name'] === $model['name'] ) {
				$custom_models[ $index ] = $model;
				$updated                 = true;
				break;
			}
		}

		if ( ! $updated ) {
			$custom_models[] = $model;
		}

		update_option( 'superdraft_custom_models', $custom_models );
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
	 * AJAX handler for saving enabled modules.
	 */
	public function ajax_save_modules() {
		check_ajax_referer( 'superdraft_wizard_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'You do not have permission to do this.', 'superdraft' ) );
		}

		$enabled_modules = $this->get_enabled_modules_from_request();

		$this->save_enabled_modules( $enabled_modules );
		wp_send_json_success( __( 'Modules saved successfully.', 'superdraft' ) );
	}

	/**
	 * Get wizard module keys.
	 *
	 * @return string[]
	 */
	private function get_wizard_module_keys() {
		return [ 'smart_compose', 'autocomplete', 'tags_categories', 'images', 'writing_tips' ];
	}

	/**
	 * Get enabled modules posted by the wizard.
	 *
	 * @param bool $fallback_to_settings Whether to use saved settings when no modules were posted.
	 * @return array<string,bool>
	 */
	private function get_enabled_modules_from_request( $fallback_to_settings = false ) {
		$enabled_modules = array_fill_keys( $this->get_wizard_module_keys(), false );
		$raw_modules     = [];

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce is checked by AJAX handlers before this helper is called; values are validated against known boolean module keys below.
		if ( isset( $_POST['modules'] ) && is_array( $_POST['modules'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce is checked by AJAX handlers before this helper is called; values are validated against known boolean module keys below.
			$raw_modules = wp_unslash( (array) $_POST['modules'] );
		}

		if ( empty( $raw_modules ) && $fallback_to_settings ) {
			return $this->get_enabled_modules_from_settings();
		}

		foreach ( $this->get_wizard_module_keys() as $module ) {
			if ( array_key_exists( $module, $raw_modules ) ) {
				$enabled_modules[ $module ] = wp_validate_boolean( $raw_modules[ $module ] );
			}
		}

		return $enabled_modules;
	}

	/**
	 * Get enabled modules from saved Superdraft settings.
	 *
	 * @return array<string,bool>
	 */
	private function get_enabled_modules_from_settings() {
		$settings        = get_option( 'superdraft_settings', [] );
		$enabled_modules = array_fill_keys( $this->get_wizard_module_keys(), false );

		if ( ! is_array( $settings ) ) {
			return $enabled_modules;
		}

		$enabled_modules['smart_compose']   = ! empty( $settings['autocomplete']['smart_compose_enabled'] );
		$enabled_modules['autocomplete']    = ! empty( $settings['autocomplete']['enabled'] );
		$enabled_modules['tags_categories'] = ! empty( $settings['tags_categories']['enabled'] );
		$enabled_modules['images']          = ! empty( $settings['images']['enabled'] );
		$enabled_modules['writing_tips']    = ! empty( $settings['writing_tips']['enabled'] );

		return $enabled_modules;
	}

	/**
	 * Save enabled modules to Superdraft settings.
	 *
	 * @param array<string,bool> $enabled_modules Enabled wizard modules.
	 */
	private function save_enabled_modules( $enabled_modules ) {
		$settings = get_option( 'superdraft_settings', [] );
		if ( ! is_array( $settings ) ) {
			$settings = [];
		}

		foreach ( [ 'tags_categories', 'writing_tips', 'images', 'autocomplete' ] as $module ) {
			if ( ! isset( $settings[ $module ] ) || ! is_array( $settings[ $module ] ) ) {
				$settings[ $module ] = [];
			}
		}

		$settings['tags_categories']['enabled']            = ! empty( $enabled_modules['tags_categories'] );
		$settings['writing_tips']['enabled']               = ! empty( $enabled_modules['writing_tips'] );
		$settings['images']['enabled']                     = ! empty( $enabled_modules['images'] );
		$settings['autocomplete']['enabled']               = ! empty( $enabled_modules['autocomplete'] );
		$settings['autocomplete']['smart_compose_enabled'] = ! empty( $enabled_modules['smart_compose'] );

		update_option( 'superdraft_settings', $settings );
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
