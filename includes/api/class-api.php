<?php
/**
 * Main API class for Superdraft.
 *
 * @package Superdraft
 * @since 1.0.0
 */

namespace Superdraft;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * API base class.
 */
class API {

	/**
	 * API key.
	 *
	 * @var string
	 */
	protected $api_key;

	/**
	 * API URL.
	 *
	 * @var string
	 */
	protected $api_url;

	/**
	 * Model.
	 *
	 * @var string
	 */
	protected $model;

	/**
	 * Temperature.
	 *
	 * @var float
	 */
	protected $temperature = 0.3;

	/**
	 * Max tokens.
	 *
	 * @var int
	 */
	protected $max_tokens = 1500;

	/**
	 * Default prompt directories.
	 *
	 * @var array
	 */
	protected $prompt_directories = [];

	/**
	 * Last response.
	 *
	 * @var string
	 */
	protected $last_response;

	/**
	 * Last request (headers and body).
	 *
	 * @var array
	 */
	protected $last_request = [];

	/**
	 * Response timer (in ms).
	 *
	 * @var int
	 */
	protected $response_timer;

	/**
	 * Set the API key.
	 *
	 * @param string $api_key The API key.
	 */
	public function set_api_key( $api_key ) {
		$this->api_key = sanitize_text_field( $api_key );
	}

	/**
	 * Trim the prompt for better AI processing.
	 *
	 * @param string $prompt The prompt to trim.
	 * @return string The trimmed prompt.
	 */
	public function trim_prompt( $prompt ) {
		// Maybe implement prompt trimming logic.
		return trim( $prompt );
	}

	/**
	 * Get the prompt directories.
	 *
	 * @return array Array of directory paths.
	 */
	protected function get_prompt_directories() {
		if ( empty( $this->prompt_directories ) ) {
			$this->prompt_directories = [
				SUPERDRAFT_DIR . 'prompts',
				get_stylesheet_directory() . '/superdraft',
			];
		}

		/**
		 * Filter the prompt directories.
		 *
		 * @param array $prompt_directories Array of directory paths.
		 */
		return apply_filters( 'superdraft_prompt_directories', $this->prompt_directories );
	}

	/**
	 * Read prompt template from files in the prompts directories.
	 *
	 * @param string $template The filename of the prompt.
	 * @return string The prompt.
	 */
	public function get_prompt_template( $template ) {
		$template = sanitize_title( $template ); // Alphanumeric characters, underscore (_) and dash (-) only.

		/**
		 * Filter the prompt template name.
		 *
		 * @param string $template The prompt template.
		 */
		$template = apply_filters( 'superdraft_prompt_template', $template );

		/**
		 * Short-circuit the prompt text. Return a non-empty value to skip reading the prompt from files.
		 *
		 * @param string $contents The prompt text.
		 */
		$contents = apply_filters( 'superdraft_pre_prompt_text', '', $template );

		if ( $contents ) {
			return $contents;
		}

		$locale = get_locale();
		$code   = explode( '_', $locale )[0]; // "en_US" to "en".

		$prompt_directories = $this->get_prompt_directories();

		// Reverse the array to prioritize custom prompts over default ones.
		$prompt_directories = array_reverse( $prompt_directories );

		foreach ( $prompt_directories as $directory ) {
			$directory = trailingslashit( $directory );

			$file_variants = [];
			// Add fully localized version first if not English.
			if ( $locale !== 'en_US' ) {
				$file_variants[] = [
					'path' => $directory . $template . '-' . $locale . '.txt',
					'code' => $locale,
				];
				$file_variants[] = [
					'path' => $directory . $template . '-' . $code . '.txt',
					'code' => $code,
				];
			}
			// Add default version.
			$file_variants[] = [
				'path' => $directory . $template . '.txt',
				'code' => '',
			];
		}

		foreach ( $file_variants as $variant ) {
			if ( file_exists( $variant['path'] ) ) {
				$contents = file_get_contents( $variant['path'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- We're reading a file.
				break;
			}
		}

		/**
		 * Filter the prompt text.
		 *
		 * @param string $contents The prompt text.
		 * @param string $template The prompt template.
		 * @param string $path     The path to the prompt file.
		 */
		return apply_filters( 'superdraft_prompt_text', $contents, $template );
	}

	/**
	 * Replace {{variables}} in the prompt with actual values.
	 *
	 * @param string $prompt The prompt.
	 * @param array  $vars   The variables to replace.
	 *
	 * @return string The prompt with replaced variables.
	 */
	public function replace_vars( $prompt, $vars ) {

		/**
		 * Filter the prompt variables.
		 *
		 * @param array $vars The prompt variables.
		 */
		$vars = apply_filters( 'superdraft_prompt_vars', $vars, $prompt );

		// Handle ((? conditional strings with {{variables}} )) that should be removed if all variables evaluate to false.
		$pattern = '/\(\(\?(.*?)\)\)/s';
		$prompt  = preg_replace_callback(
			$pattern,
			function ( $matches ) use ( $vars ) {
				$content = $matches[1];

				// Check if any variables in the conditional block have non-empty values.
				$has_value = false;
				foreach ( $vars as $key => $value ) {
					$var = '{{' . $key . '}}';
					if ( strpos( $content, $var ) !== false && ! empty( $value ) ) {
						$has_value = true;
						break;
					}
				}

				// Return the content without the conditional markers if any variable has value,
				// otherwise return empty string to remove the conditional block.
				return $has_value ? $content : '';
			},
			$prompt
		);

		foreach ( $vars as $key => $value ) {
			$var    = '{{' . $key . '}}';
			$prompt = str_replace( $var, $value, $prompt );
		}

		return $prompt;
	}

	/**
	 * Make the API request using wp_remote_post.
	 *
	 * @param string $url    The URL to send the request to.
	 * @param array  $config The request configuration.
	 *
	 * @return WP_Error|array The response or WP_Error on failure.
	 */
	protected function request( $url, $config ) {
		$default_config = [
			'timeout' => 100,
			'headers' => [
				'Content-Type' => 'application/json',
			],
		];

		$config = wp_parse_args( $config, $default_config );

		$start_time = microtime( true );

		$request = wp_remote_post(
			$url,
			$config
		);

		$this->last_request = [
			'url'     => $url,
			'headers' => $config['headers'],
			'body'    => isset( $config['body'] ) ? json_decode( $config['body'], true ) : [],
		];

		$this->response_timer = round( ( microtime( true ) - $start_time ) * 1000 ); // Convert to milliseconds.

		return $request;
	}

	/**
	 * Get the response time in milliseconds.
	 *
	 * @return int Response time in milliseconds.
	 */
	public function get_response_time() {
		return $this->response_timer;
	}

	/**
	 * Get input/output token usage for the last request.
	 * API-specific implementations should override this method.
	 *
	 * @return array The token usage.
	 */
	public function get_token_usage() {
		$usage = [
			'input_tokens'  => 0,
			'output_tokens' => 0,
		];

		return $usage;
	}

	/**
	 * Get the last response.
	 *
	 * @return string The last response.
	 */
	public function get_last_response() {
		return $this->last_response;
	}

	/**
	 * Get the last request (headers and body).
	 *
	 * @return array The last request.
	 */
	public function get_last_request() {
		return $this->last_request;
	}

	/**
	 * Get the model.
	 *
	 * @return string The model.
	 */
	public function get_model() {
		return $this->model;
	}

	/**
	 * Get the temperature.
	 *
	 * @return float The temperature.
	 */
	public function get_temperature() {
		return $this->temperature;
	}

	/**
	 * Set temperature.
	 *
	 * @param float $temperature The temperature.
	 */
	public function set_temperature( $temperature ) {
		$this->temperature = floatval( $temperature );
	}

	/**
	 * Set the max tokens.
	 *
	 * @param int $max_tokens The max tokens.
	 */
	public function set_max_tokens( $max_tokens ) {
		$this->max_tokens = absint( $max_tokens );
	}
}
