<?php
/**
 * Superdraft Admin class.
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
class Admin {

	/**
	 * The built-in models.
	 *
	 * @var array
	 */
	public static $models = [];

	/**
	 * Constructor: Set up hooks and actions.
	 */
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ], 20 );

		// Add settings link on the plugins page.
		add_filter( 'plugin_action_links_' . plugin_basename( SUPERDRAFT_DIR . 'superdraft.php' ), [ $this, 'add_settings_link' ] );

		// AJAX actions for custom models.
		add_action( 'wp_ajax_superdraft_add_model', [ $this, 'ajax_add_model' ] );
		add_action( 'wp_ajax_superdraft_remove_model', [ $this, 'ajax_remove_model' ] );

		// Initialize enabled modules.
		$modules = [
			'tags_categories',
			'writing_tips',
			'autocomplete',
			'images',
		];
		foreach ( $modules as $module ) {
			$enabled = get_option( 'superdraft_settings', [] );
			if ( isset( $enabled[ $module ]['enabled'] ) && $enabled[ $module ]['enabled'] ) {
				$module_class = 'Superdraft\\' . str_replace( ' ', '_', ucwords( str_replace( [ '-', '_' ], ' ', $module ) ) );
				if ( class_exists( $module_class ) ) {
					new $module_class();
				}
			}
		}
	}

	/**
	 * Add the settings page to the admin menu.
	 */
	public function add_admin_menu() {
		add_menu_page(
			'Superdraft',
			'Superdraft',
			'manage_options',
			'superdraft-settings',
			[ $this, 'render_settings_page' ],

			// This is the contents of the SVG icon, base64-encoded (assets/admin/images/superdraft-icon.svg).
			'data:image/svg+xml;base64,PHN2ZyB2aWV3Qm94PSIwIDAgNDI2NyA0MjY3IiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHhtbDpzcGFjZT0icHJlc2VydmUiIGZpbGwtcnVsZT0iZXZlbm9kZCIgY2xpcC1ydWxlPSJldmVub2RkIiBzdHJva2UtbGluZWpvaW49InJvdW5kIiBzdHJva2UtbWl0ZXJsaW1pdD0iMiI+PHBhdGggZD0iTTE4MjkgMjAwMGEzNzcxIDM3NzEgMCAwIDAtNjM3IDE0MjVsLTktNDJjLTktMTcyIDE1LTM0NSA3MS01MDggNC00MiAxNy03OSAzNC0xMTdhODQyIDg0MiAwIDAgMS0xMDUtNjgzYzUwLTE5NiAxNDItMzY3IDI3NS01MTJsLTggMTk1Yy03IDE1OSAzNiAzMTYgMTIxIDQ1MHYtMzNjLTE5LTE3Ny0yMi0zNTYtOC01MzMgMTYtMTM0IDcwLTI1MCAxNTgtMzQ2YTg2MiA4NjIgMCAwIDEgNDIxLTI2M2MtNTAgNzAtODYgMTUwLTEwNCAyMzRhOTc1IDk3NSAwIDAgMC0yMSAzNzVsNC01IDgtNDljNy01MyAxOC0xMDQgMzQtMTU1YTc2NyA3NjcgMCAwIDEgMjE2LTQwOCA3NzUgNzc1IDAgMCAxIDQ2Ny0xODdjMTEzLTExIDIyNi02IDMzNyAxNiA2NyAyNDYgMzQgNDc5LTkxIDcwMGE3MzYgNzM2IDAgMCAxLTQyOSAzMDhjLTExMyAzMC0yMjEgNDYtMzM0IDU5IDE1OSAzNyAzMjEgMzMgNDg0LTEzbDEwMC00MSA0IDRhNjg4IDY4OCAwIDAgMS0yNzUgMjcxYy0xODIgOTQtMzgwIDE1NC01ODQgMTc1bDcxIDEyIDYzIDhjMTQxIDEzIDI3OS00IDQxMi00NWw5IDRhNzcwIDc3MCAwIDAgMS0yODQgMjIxYy0xNDEgNjItMjkxIDEwMC00NDEgMTEyIDk1IDMzIDIwMCA1MCAzMDggNTRsNTAgNWMtOCA0LTggOCAwIDEyYTc2MCA3NjAgMCAwIDEtNzA4IDE3NWMxMTItMzA0IDI2Mi01OTIgNDQ1LTg1NGw1LTdjMTk5LTI3NiA0MzEtNTI4IDY5MS03NTFsLTgtNWEzMTM0IDMxMzQgMCAwIDAtNzQyIDc0MloiIGZpbGw9IiMwMzAzMDMiLz48L3N2Zz4=',
			100
		);

		// Add a submenu page for logs.
		add_submenu_page(
			'superdraft-settings',
			__( 'Superdraft API Logs', 'superdraft' ),
			__( 'API Logs', 'superdraft' ),
			'manage_options',
			'superdraft-logs',
			[ $this, 'render_api_logs_page' ]
		);

		// Modify the first submenu item to show "Settings" instead of "Superdraft".
		global $submenu;
		if ( isset( $submenu['superdraft-settings'] ) ) {
			$submenu['superdraft-settings'][0][0] = __( 'Settings', 'superdraft' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- This is the only way to change the menu item text.
		}
	}

	/**
	 * Render the API logs page.
	 */
	public function render_api_logs_page() {
		// We use our Logs_List_Table class.
		require_once SUPERDRAFT_DIR . 'includes/admin/class-logs-list-table.php';

		$list_table = new Logs_List_Table();
		$list_table->prepare_items();
		?>
		<div class="wrap">
			<h1>
				<?php esc_html_e( 'Superdraft API Logs', 'superdraft' ); ?>
			</h1>

			<form method="get">
				<input type="hidden" name="page" value="superdraft-logs" />
				<?php
				$list_table->search_box( __( 'Search Logs', 'superdraft' ), 'superdraft-logs' );
				$list_table->display();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Register plugin settings.
	 */
	public function register_settings() {
		// Register single settings array for all Superdraft modules.
		register_setting(
			'superdraft_settings',
			'superdraft_settings',
			[
				'default'           => Settings_Config::get_default_module_settings(),
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_settings' ],
				'show_in_rest'      => false,
				'description'       => esc_html__( 'Superdraft plugin settings', 'superdraft' ),
			]
		);

		// API keys are stored separately.
		register_setting(
			'superdraft_settings',
			'superdraft_api_keys',
			[
				'default'           => Settings_Config::get_default_api_keys(),
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_api_keys' ],
				'show_in_rest'      => false,
				'description'       => esc_html__( 'Superdraft API keys', 'superdraft' ),
			]
		);
	}

	/**
	 * Set default options on plugin activation.
	 */
	public static function set_default_options() {
		// Only set if not already set.
		if ( false === get_option( 'superdraft_settings' ) ) {
			add_option( 'superdraft_settings', Settings_Config::get_default_module_settings() );
		}

		if ( false === get_option( 'superdraft_api_keys' ) ) {
			add_option( 'superdraft_api_keys', Settings_Config::get_default_api_keys() );
		}

		if ( false === get_option( 'superdraft_custom_models' ) ) {
			add_option( 'superdraft_custom_models', [] );
		}
	}

	/**
	 * Sanitize settings array.
	 *
	 * @param array $input The settings array to sanitize.
	 * @return array Sanitized settings.
	 */
	public function sanitize_settings( $input ) {
		$sanitized = [];
		$config    = Settings_Config::get_module_settings();

		foreach ( $config as $module => $settings ) {
			$sanitized[ $module ] = [];
			foreach ( $settings as $key => $setting ) {
				if ( isset( $input[ $module ][ $key ] ) ) {
					if ( $setting['type'] === 'boolean' ) {
						$sanitized[ $module ][ $key ] = (bool) $input[ $module ][ $key ];
					} elseif ( isset( $setting['sanitize'] ) ) {
						$sanitized[ $module ][ $key ] = call_user_func( $setting['sanitize'], $input[ $module ][ $key ] );
					} else {
						$sanitized[ $module ][ $key ] = $input[ $module ][ $key ];
					}
				} else {
					$sanitized[ $module ][ $key ] = $setting['default'];
				}
			}
		}

		return $sanitized;
	}

	/**
	 * Sanitize API keys.
	 *
	 * @param array $input The API keys array to sanitize.
	 * @return array Sanitized API keys.
	 */
	public function sanitize_api_keys( $input ) {
		$sanitized = [];
		$config    = Settings_Config::get_api_keys_config();

		foreach ( $config as $key => $setting ) {
			$sanitized[ $key ] = isset( $input[ $key ] ) ?
				call_user_func( $setting['sanitize'], $input[ $key ] ) :
				$setting['default'];
		}

		return $sanitized;
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook The current admin page.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		$settings = get_option( 'superdraft_settings', [] );

		// Add extra items to the settings array to be used in JS.
		$settings['writing_tips']['nonce'] = wp_create_nonce( 'superdraft_writing_tips' );

		// Add Superdraft settings JSON to all admin pages.
		wp_add_inline_script( 'jquery', 'var superdraftSettings = ' . wp_json_encode( $settings ) . ';' );

		// As well as the admin.css file.
		wp_enqueue_style( 'superdraft-admin-global-css', SUPERDRAFT_URL . 'assets/admin/css/admin.css', [], SUPERDRAFT_VERSION );

		if ( 'toplevel_page_superdraft-settings' === $hook ) {
			// Enqueue CSS.
			wp_enqueue_style( 'superdraft-admin-css', SUPERDRAFT_URL . 'assets/admin/css/settings.css', [], SUPERDRAFT_VERSION );

			// Enqueue JS.
			wp_enqueue_script( 'superdraft-admin-js', SUPERDRAFT_URL . 'assets/admin/js/settings.js', [], SUPERDRAFT_VERSION, true );

			// Localize script for AJAX and i18n.
			wp_localize_script(
				'superdraft-admin-js',
				'superdraft',
				[
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'superdraft_settings_nonce' ),
					'i18n'     => [
						'details'          => __( 'Details', 'superdraft' ),
						'url'              => __( 'URL', 'superdraft' ),
						'modelParameter'   => __( 'Model Parameter', 'superdraft' ),
						'apiKey'           => __( 'API Key', 'superdraft' ),
						'headers'          => __( 'Headers', 'superdraft' ),
						'remove'           => __( 'Remove', 'superdraft' ),
						'fillOutFields'    => __( 'Please fill out all required fields.', 'superdraft' ),
						'removeModel'      => __( 'Are you sure you want to remove this model?', 'superdraft' ),
						'errorSavingModel' => __( 'Error saving model', 'superdraft' ),
					],
				]
			);
		} elseif ( 'superdraft_page_superdraft-logs' === $hook ) {
			// Enqueue CSS.
			wp_enqueue_style( 'superdraft-admin-css', SUPERDRAFT_URL . 'assets/admin/css/logs.css', [], SUPERDRAFT_VERSION );

			// Enqueue JS.
			wp_enqueue_script( 'superdraft-admin-js', SUPERDRAFT_URL . 'assets/admin/js/logs.js', [ 'jquery' ], SUPERDRAFT_VERSION, true );
		}
	}

	/**
	 * Render the settings page.
	 */
	public function render_settings_page() {
		include SUPERDRAFT_DIR . 'views/page-settings.php';
	}

	/**
	 * Add a settings link to the plugins page.
	 *
	 * @param array $links Existing plugin action links.
	 * @return array Modified plugin action links.
	 */
	public function add_settings_link( $links ) {
		$settings_link = '<a href="' . admin_url( 'admin.php?page=superdraft-settings' ) . '">' . __( 'Settings', 'superdraft' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * Sanitize model parameters.
	 *
	 * @param array $model The model parameters to sanitize.
	 * @return array Sanitized model parameters.
	 */
	private function sanitize_model_params( $model ) {
		if ( ! is_array( $model ) ) {
			return null;
		}

		return [
			'name'           => isset( $model['name'] ) ? sanitize_text_field( $model['name'] ) : '',
			'url'            => isset( $model['url'] ) ? esc_url_raw( $model['url'] ) : '',
			'modelParameter' => isset( $model['modelParameter'] ) ? sanitize_text_field( $model['modelParameter'] ) : '',
			'apiKey'         => isset( $model['apiKey'] ) ? sanitize_text_field( $model['apiKey'] ) : '',
			'headers'        => isset( $model['headers'] ) ? array_map( 'sanitize_text_field', (array) $model['headers'] ) : [],
		];
	}

	/**
	 * AJAX handler for adding a custom model.
	 */
	public function ajax_add_model() {
		// Verify nonce.
		check_ajax_referer( 'superdraft_settings_nonce', 'nonce' );

		// Check if the user has the capability to manage options.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'You do not have permission to do this.', 'superdraft' ) );
		}

		// Get and sanitize model data.
		$model = isset( $_POST['model'] ) ? $this->sanitize_model_params( wp_unslash( $_POST['model'] ) ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! $model || empty( $model['name'] ) || empty( $model['url'] ) || empty( $model['apiKey'] ) ) {
			wp_send_json_error( __( 'Invalid model data.', 'superdraft' ) );
		}

		// Get existing custom models.
		$custom_models = get_option( 'superdraft_custom_models', [] );
		if ( ! is_array( $custom_models ) ) {
			$custom_models = [];
		}

		// Add the new model.
		$custom_models[] = $model;

		// Update the option.
		update_option( 'superdraft_custom_models', $custom_models );

		wp_send_json_success(
			[
				'models'  => $custom_models,
				'message' => __( 'Custom model added successfully.', 'superdraft' ),
			]
		);
	}

	/**
	 * AJAX handler for removing a custom model.
	 */
	public function ajax_remove_model() {
		// Verify nonce.
		check_ajax_referer( 'superdraft_settings_nonce', 'nonce' );

		// Check if the user has the capability to manage options.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'You do not have permission to do this.', 'superdraft' ) );
		}

		// Get and sanitize index.
		$index = isset( $_POST['index'] ) ? intval( $_POST['index'] ) : null;
		if ( is_null( $index ) ) {
			wp_send_json_error( __( 'Invalid model index.', 'superdraft' ) );
		}

		// Get existing custom models.
		$custom_models = get_option( 'superdraft_custom_models', [] );
		if ( ! is_array( $custom_models ) || ! isset( $custom_models[ $index ] ) ) {
			wp_send_json_error( __( 'Model not found.', 'superdraft' ) );
		}

		// Remove the model.
		unset( $custom_models[ $index ] );

		// Reindex the array.
		$custom_models = array_values( $custom_models );

		// Update the option.
		update_option( 'superdraft_custom_models', $custom_models );

		wp_send_json_success(
			[
				'models'  => $custom_models,
				'message' => __( 'Custom model removed successfully.', 'superdraft' ),
			]
		);
	}

	/**
	 * Get models.
	 *
	 * @return array Models.
	 */
	public static function get_models() {
		self::$models = [
			'OpenAI'    => [
				'gpt-4.5-preview'   => 'GPT-4.5 Preview',
				'o1'                => 'o1',
				'o1-preview'        => 'o1-preview',
				'o1-mini'           => 'o1-mini',
				'o3-mini'           => 'o3-mini',
				'gpt-4o'            => 'GPT-4o',
				'chatgpt-4o-latest' => 'ChatGPT-4o-latest',
				'gpt-4o-mini'       => 'GPT-4o mini',
				'gpt-4-turbo'       => 'GPT-4 Turbo',
				'gpt-3.5-turbo'     => 'GPT-3.5 Turbo',
			],
			'Anthropic' => [
				'claude-3-7-sonnet-latest'   => 'Claude 3.7 Sonnet-latest',
				'claude-3-7-sonnet-20250219' => 'Claude 3.7 Sonnet-20250219',
				'claude-3-5-sonnet-latest'   => 'Claude 3.5 Sonnet-latest',
				'claude-3-5-sonnet-20241022' => 'Claude 3.5 Sonnet-20241022',
				'claude-3-5-sonnet-20240620' => 'Claude 3.5 Sonnet-20240620',
				'claude-3-5-haiku-latest'    => 'Claude 3.5 Haiku-latest',
				'claude-3-5-haiku-20241022'  => 'Claude 3.5 Haiku-20241022',
				'claude-3-opus-20240229'     => 'Claude 3 Opus-20240229',
				'claude-3-sonnet-20240229'   => 'Claude 3 Sonnet-20240229',
				'claude-3-haiku-20240307'    => 'Claude 3 Haiku-20240307',
			],
			'Google'    => [
				'gemini-2.5-pro-exp-03-25'            => 'Gemini 2.5 Pro Experimental 03-25',
				'gemini-2.0-pro-exp-02-05'            => 'Gemini 2.0 Pro Experimental 02-05',
				'gemini-2.0-flash-thinking-exp'       => 'Gemini 2.0 Flash Thinking Experimental',
				'gemini-2.0-flash-exp'                => 'Gemini 2.0 Flash Experimental',
				'gemini-2.0-flash-thinking-exp-01-21' => 'Gemini 2.0 Flash Thinking Experimental 01-21',
				'gemini-exp-1206'                     => 'Gemini Experimental 1206',
				'gemini-exp-1121'                     => 'Gemini Experimental 1121',
				'gemini-1.5-pro'                      => 'Gemini 1.5 Pro',
				'gemini-1.5-flash'                    => 'Gemini 1.5 Flash',
				'gemini-1.0-pro'                      => 'Gemini 1.0 Pro',
			],
			'xAI'       => [
				'grok-2'      => 'Grok 2',
				'grok-beta'   => 'Grok Beta',
				'grok-2-1212' => 'Grok 2-1212',
			],
		];

		$custom_models = get_option( 'superdraft_custom_models', [] );
		$group_label   = 'Custom Models'; // Note: this is mapped to a translation later.
		if ( is_array( $custom_models ) && ! empty( $custom_models ) ) {
			foreach ( $custom_models as $model ) {
				self::$models[ $group_label ][ $model['name'] ] = $model['name'];
			}
		}

		/**
		 * Filter the models.
		 *
		 * @since 1.0.5
		 * @param array $models The models.
		 */
		self::$models = apply_filters( 'superdraft_models', self::$models );

		return self::$models;
	}

	/**
	 * Get the API object based on the model.
	 *
	 * @param string $model The model to use.
	 *
	 * @return API|null
	 */
	public static function get_api( $model ) {
		$openai_api_key    = get_option( 'superdraft_api_keys' )['openai'];
		$anthropic_api_key = get_option( 'superdraft_api_keys' )['anthropic'];
		$google_api_key    = get_option( 'superdraft_api_keys' )['google'];
		$xai_api_key       = get_option( 'superdraft_api_keys' )['xai'];
		$custom_models     = get_option( 'superdraft_custom_models', [] );

		$api = null;

		$models = self::get_models();

		if ( ! empty( $openai_api_key ) && array_key_exists( $model, $models['OpenAI'] ) ) {
			$api = new OpenAI_API();
			$api->set_api_key( $openai_api_key );
			$api->set_model( $model );
		} elseif ( ! empty( $anthropic_api_key ) && array_key_exists( $model, $models['Anthropic'] ) ) {
			$api = new Anthropic_API();
			$api->set_api_key( $anthropic_api_key );
			$api->set_model( $model );
		} elseif ( ! empty( $google_api_key ) && array_key_exists( $model, $models['Google'] ) ) {
			$api = new Google_Gemini_API();
			$api->set_api_key( $google_api_key );
			$api->set_model( $model );
		} elseif ( ! empty( $xai_api_key ) && array_key_exists( $model, $models['xAI'] ) ) {
			$api = new XAI_API();
			$api->set_api_key( $xai_api_key );
			$api->set_model( $model );
		}

		// Check custom models.
		if ( ! empty( $custom_models ) ) {
			foreach ( $custom_models as $custom_model ) {
				// If the "modelParameter" in the DB matches the user’s selected $model.
				if ( $custom_model['name'] === $model ) {
					$api = new Custom_API();
					$api->set_custom_config(
						$custom_model['url'],
						$custom_model['apiKey'],
						$custom_model['modelParameter'],
						$custom_model['headers']
					);
					return $api;
				}
			}
		}

		// If nothing matches, $api will be null.
		return $api;
	}

	/**
	 * Get the model select dropdown, for use in settings page.
	 *
	 * @param string $module The module to get the models for.
	 * @param string $model_key The key to use for the model.
	 *
	 * @return string The model select dropdown.
	 */
	public static function get_model_select( $module, $model_key = 'model' ) {
		if ( 'images' === $module ) {
			$settings = get_option( 'superdraft_settings', [] );
			$selected = $settings['images']['image_model'] ?? 'gemini-2.0-flash-exp-image-generation';
			$output  = '<select name="superdraft_settings[images][image_model]" class="regular-text superdraft-models">';
			$output .= '<option value="gemini-2.0-flash-exp-image-generation"' . selected( $selected, 'gemini-2.0-flash-exp-image-generation', false ) . '>Gemini 2.0 Flash Experimental Image Generation</option>';
			$output .= '</select>';
			return $output;
		}

		$models   = self::get_models();
		$settings = get_option( 'superdraft_settings', [] );

		$output = '<select name="superdraft_settings[' . esc_attr( $module ) . '][' . $model_key . ']" class="regular-text superdraft-models">' . "\n";
		foreach ( $models as $provider => $model ) {
			$label = $provider;
			if ( 'Custom Models' === $provider ) {
				$label = __( 'Custom Models', 'superdraft' );
			}
			$output .= '<optgroup label="' . esc_attr( $label ) . '" class="superdraft-models-group superdraft-models-group-' . esc_attr( sanitize_title( $provider ) ) . '">' . "\n";
			foreach ( $model as $key => $value ) {
				$output .= '<option value="' . esc_attr( $key ) . '" ' .
					selected( $settings[ $module ][ $model_key ], $key, false ) . '>' .
					esc_html( $value ) . '</option>' . "\n";
			}
			$output .= '</optgroup>' . "\n";
		}
		$output .= '</select>';

		return $output;
	}

	/**
	 * Log an API request.
	 *
	 * @param API   $api  The API object.
	 * @param array $data Additional data to log.
	 *
	 * @return int|false The log item ID or false on failure.
	 */
	public static function log_api_request( $api, $data = [] ) {
		$usage        = $api->get_token_usage();
		$default_data = [
			'prompt'        => '',
			'tool'          => '',
			'message'       => '',
			'input_tokens'  => $usage['input_tokens'],
			'output_tokens' => $usage['output_tokens'],
			'model'         => $api->get_model(),
			'response_time' => $api->get_response_time(),
		];

		$messages = [];

		if ( defined( 'SUPERDRAFT_LOG_PROMPTS' ) && SUPERDRAFT_LOG_PROMPTS ) {
			$messages['prompt'] = $data['prompt'];
		}
		unset( $data['prompt'], $default_data['prompt'] ); // We don't have a separate prompt field in the DB.

		if ( defined( 'SUPERDRAFT_LOG_RESPONSES' ) && SUPERDRAFT_LOG_RESPONSES ) {
			$messages['response'] = $api->get_last_response();
		}
		$default_data['message'] = wp_json_encode( $messages, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );

		$data = array_merge( $default_data, $data );

		/**
		 * Filter the data to be logged.
		 *
		 * @param array $data          The data to be logged.
		 * @param API   $api           The API object.
		 */
		$data = apply_filters( 'superdraft_log_data', $data, $api );

		$logger = new Logger();
		$logger->insert_log( $data );

		return $data;
	}
}
