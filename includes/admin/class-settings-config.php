<?php
/**
 * Settings Configuration class.
 *
 * @package Superdraft
 * @since 1.0.0
 */

namespace Superdraft;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings Configuration class.
 */
class Settings_Config {
	/**
	 * Get module settings configuration.
	 *
	 * @return array
	 */
	public static function get_module_settings() {
		return [
			'tags_categories' => [
				'enabled'             => [
					'type'    => 'boolean',
					'default' => false,
				],
				'model'               => [
					'type'     => 'string',
					'default'  => 'gpt-4o-mini',
					'sanitize' => 'sanitize_text_field',
				],
				'never_deselect'      => [
					'type'    => 'boolean',
					'default' => true,
				],
				'min_suggestions'     => [
					'type'     => 'integer',
					'default'  => 3,
					'sanitize' => 'absint',
				],
				'suggestions_context' => [
					'type'     => 'integer',
					'default'  => 20,
					'sanitize' => 'absint',
				],
			],
			'writing_tips'    => [
				'enabled'     => [
					'type'    => 'boolean',
					'default' => false,
				],
				'model'       => [
					'type'     => 'string',
					'default'  => 'gpt-4o-mini',
					'sanitize' => 'sanitize_text_field',
				],
				'auto_update' => [
					'type'     => 'integer',
					'default'  => 0,
					'sanitize' => 'absint',
				],
				'min_tips'    => [
					'type'     => 'integer',
					'default'  => 5,
					'sanitize' => 'absint',
				],
			],
			'autocomplete'    => [
				'enabled'                  => [
					'type'    => 'boolean',
					'default' => false,
				],
				'model'                    => [
					'type'     => 'string',
					'default'  => 'gpt-4o-mini',
					'sanitize' => 'sanitize_text_field',
				],
				'prefix'                   => [
					'type'     => 'string',
					'default'  => '~',
					'sanitize' => 'sanitize_text_field',
				],
				'suggestions'              => [
					'type'     => 'integer',
					'default'  => 3,
					'sanitize' => 'intval',
				],
				'empty_search'             => [
					'type'    => 'boolean',
					'default' => true,
				],
				'context_length'           => [
					'type'     => 'integer',
					'default'  => 1,
					'sanitize' => 'intval',
				],
				'debounce_delay'           => [
					'type'     => 'integer',
					'default'  => 500,
					'sanitize' => 'absint',
				],
				'smart_compose_enabled'    => [
					'type'    => 'boolean',
					'default' => false,
				],
				'smart_compose_model'      => [
					'type'     => 'string',
					'default'  => 'gpt-4o-mini',
					'sanitize' => 'sanitize_text_field',
				],
				'smart_compose_delay'      => [
					'type'     => 'integer',
					'default'  => 500,
					'sanitize' => 'absint',
				],
				'smart_compose_max_tokens' => [
					'type'     => 'integer',
					'default'  => 100,
					'sanitize' => 'absint',
				],
			],
			'images'          => [
				'enabled'      => [
					'type'    => 'boolean',
					'default' => false,
				],
				'image_model'  => [
					'type'     => 'string',
					'default'  => 'gemini-2.0-flash-exp-image-generation',
					'sanitize' => 'sanitize_text_field',
				],
				'prompt_model' => [
					'type'     => 'string',
					'default'  => 'gpt-4o-mini',
					'sanitize' => 'sanitize_text_field',
				],
			],
		];
	}

	/**
	 * Get API keys configuration.
	 *
	 * @return array
	 */
	public static function get_api_keys_config() {
		return [
			'openai'    => [
				'type'     => 'string',
				'default'  => '',
				'sanitize' => 'sanitize_text_field',
			],
			'anthropic' => [
				'type'     => 'string',
				'default'  => '',
				'sanitize' => 'sanitize_text_field',
			],
			'google'    => [
				'type'     => 'string',
				'default'  => '',
				'sanitize' => 'sanitize_text_field',
			],
			'xai'       => [
				'type'     => 'string',
				'default'  => '',
				'sanitize' => 'sanitize_text_field',
			],
			'replicate' => [
				'type'     => 'string',
				'default'  => '',
				'sanitize' => 'sanitize_text_field',
			],
		];
	}

	/**
	 * Get default values for module settings.
	 *
	 * @return array
	 */
	public static function get_default_module_settings() {
		$defaults = [];
		foreach ( self::get_module_settings() as $module => $settings ) {
			$defaults[ $module ] = [];
			foreach ( $settings as $key => $config ) {
				$defaults[ $module ][ $key ] = $config['default'];
			}
		}
		return $defaults;
	}

	/**
	 * Get default values for API keys.
	 *
	 * @return array
	 */
	public static function get_default_api_keys() {
		$defaults = [];
		foreach ( self::get_api_keys_config() as $key => $config ) {
			$defaults[ $key ] = $config['default'];
		}
		return $defaults;
	}
}
