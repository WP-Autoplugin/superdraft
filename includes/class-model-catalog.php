<?php
/**
 * Built-in model catalog and provider metadata.
 *
 * @package Superdraft
 * @since 1.1.5
 */

namespace Superdraft;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides the single source of truth for built-in models.
 *
 * Model metadata belongs here so the settings UI, API factory, and provider
 * clients do not need to maintain separate, drifting lists of model IDs.
 */
class Model_Catalog {

	/**
	 * Get the text model catalog, grouped by provider.
	 *
	 * @return array
	 */
	public static function get_text_catalog() {
		return [
			'OpenAI'    => [
				'api_key'   => 'openai',
				'api_class' => OpenAI_API::class,
				'models'    => [
					'gpt-5.6-sol'   => [
						'label'     => 'GPT-5.6 Sol',
						'transport' => 'responses',
					],
					'gpt-5.6-terra' => [
						'label'     => 'GPT-5.6 Terra',
						'transport' => 'responses',
					],
					'gpt-5.6-luna'  => [
						'label'     => 'GPT-5.6 Luna',
						'transport' => 'responses',
					],
					'gpt-5.5'       => [
						'label'     => 'GPT-5.5',
						'transport' => 'responses',
					],
					'gpt-5.4'       => [
						'label'     => 'GPT-5.4',
						'transport' => 'responses',
					],
					'gpt-5.4-mini'  => [
						'label'     => 'GPT-5.4 Mini',
						'transport' => 'responses',
					],
					'gpt-5.4-nano'  => [
						'label'     => 'GPT-5.4 Nano',
						'transport' => 'responses',
					],
					'gpt-5.1'       => [ 'label' => 'GPT-5.1', 'transport' => 'responses', 'max_tokens' => 4096 ],
					'gpt-5'         => [ 'label' => 'GPT-5', 'transport' => 'responses', 'max_tokens' => 4096 ],
					'gpt-5-mini'    => [ 'label' => 'GPT-5 Mini', 'transport' => 'responses', 'max_tokens' => 4096 ],
					'gpt-5-nano'    => [ 'label' => 'GPT-5 Nano', 'transport' => 'responses', 'max_tokens' => 4096 ],
					'gpt-4.1'       => [ 'label' => 'GPT-4.1' ],
					'gpt-4.1-mini'  => [ 'label' => 'GPT-4.1 Mini' ],
					'gpt-4.1-nano'  => [ 'label' => 'GPT-4.1 Nano' ],
					'gpt-4o'        => [
						'label'      => 'GPT-4o',
						'max_tokens' => 4096,
					],
					'gpt-4o-mini'   => [
						'label'      => 'GPT-4o Mini',
						'max_tokens' => 4096,
					],
					'o3'            => [ 'label' => 'o3' ],
					'o4-mini'       => [ 'label' => 'o4 Mini' ],
				],
			],
			'Anthropic' => [
				'api_key'   => 'anthropic',
				'api_class' => Anthropic_API::class,
				'models'    => [
					'claude-fable-5'            => [
						'label'                => 'Claude Fable 5',
						'supports_temperature' => false,
					],
					'claude-opus-4-8'           => [
						'label'                => 'Claude Opus 4.8',
						'supports_temperature' => false,
					],
					'claude-sonnet-5'           => [
						'label'                => 'Claude Sonnet 5',
						'supports_temperature' => false,
					],
					'claude-haiku-4-5-20251001' => [
						'label'                => 'Claude Haiku 4.5',
						'supports_temperature' => true,
					],
					'claude-3-5-haiku-latest'   => [
						'label'                => 'Claude 3.5 Haiku',
						'supports_temperature' => true,
					],
				],
			],
			'Google'    => [
				'api_key'   => 'google',
				'api_class' => Google_Gemini_API::class,
				'models'    => [
					'gemini-3.5-flash'       => [ 'label' => 'Gemini 3.5 Flash' ],
					'gemini-3.1-pro-preview' => [ 'label' => 'Gemini 3.1 Pro (Preview)' ],
					'gemini-3.1-flash-lite'  => [ 'label' => 'Gemini 3.1 Flash-Lite' ],
					'gemini-2.5-pro'         => [ 'label' => 'Gemini 2.5 Pro' ],
					'gemini-2.5-flash'       => [ 'label' => 'Gemini 2.5 Flash' ],
					'gemini-2.5-flash-lite'  => [ 'label' => 'Gemini 2.5 Flash-Lite' ],
				],
			],
			'xAI'       => [
				'api_key'   => 'xai',
				'api_class' => XAI_API::class,
				'models'    => [
					'grok-4.5' => [ 'label' => 'Grok 4.5' ],
				],
			],
		];
	}

	/**
	 * Get text models in the public filter-compatible format.
	 *
	 * @return array
	 */
	public static function get_text_models() {
		$models = [];
		foreach ( self::get_text_catalog() as $provider => $provider_config ) {
			$models[ $provider ] = wp_list_pluck( $provider_config['models'], 'label' );
		}

		return $models;
	}

	/**
	 * Get the metadata for one built-in text model.
	 *
	 * @param string $model Model ID.
	 * @return array|null
	 */
	public static function get_text_model( $model ) {
		$catalog = self::get_text_catalog();
		foreach ( $catalog as $provider => $provider_config ) {
			if ( isset( $provider_config['models'][ $model ] ) ) {
				return array_merge(
					$provider_config['models'][ $model ],
					[
						'provider'  => $provider,
						'api_key'   => $provider_config['api_key'],
						'api_class' => $provider_config['api_class'],
					]
				);
			}
		}

		foreach ( self::get_legacy_text_models() as $provider => $legacy_models ) {
			if ( isset( $legacy_models[ $model ] ) && isset( $catalog[ $provider ] ) ) {
				return [
					'provider'  => $provider,
					'api_key'   => $catalog[ $provider ]['api_key'],
					'api_class' => $catalog[ $provider ]['api_class'],
				];
			}
		}

		return null;
	}

	/**
	 * Get retired IDs that remain dispatchable for saved settings.
	 *
	 * They are intentionally excluded from the settings catalog, but retaining
	 * them here avoids changing the behavior of an existing configuration.
	 *
	 * @return array
	 */
	private static function get_legacy_text_models() {
		return [
			'OpenAI'    => [
				'gpt-5.2'                 => true,
				'gpt-5.2-pro'             => true,
				'gpt-5.2-codex'           => true,
				'gpt-5.1-instant'         => true,
				'gpt-5.1-thinking'        => true,
				'gpt-5-chat-latest'       => true,
				'gpt-4.5-preview'         => true,
				'gpt-4.1-2025-04-14'      => true,
				'gpt-4.1-mini-2025-04-14' => true,
				'gpt-4.1-nano-2025-04-14' => true,
				'gpt-4o-latest'           => true,
				'chatgpt-4o-latest'       => true,
				'o1'                      => true,
				'o3-pro'                  => true,
				'o3-mini'                 => true,
				'gpt-4-turbo'             => true,
				'gpt-3.5-turbo'           => true,
			],
			'Anthropic' => [
				'claude-opus-4-6-20260101'   => true,
				'claude-opus-4-5-20251101'   => true,
				'claude-opus-4-1-20250805'   => true,
				'claude-opus-4-20250514'     => true,
				'claude-sonnet-4-5-20250929' => true,
				'claude-sonnet-4-20250514'   => true,
				'claude-3-7-sonnet-latest'   => true,
				'claude-3-7-sonnet-20250219' => true,
				'claude-3-5-sonnet-latest'   => true,
				'claude-4-5-haiku-latest'    => true,
			],
			'Google'    => [
				'gemini-3-pro-preview'   => true,
				'gemini-3-flash-preview' => true,
				'gemini-2.0-flash'       => true,
				'gemini-2.0-flash-lite'  => true,
				'gemini-1.5-flash'       => true,
				'gemma-3-27b-it'         => true,
			],
			'xAI'       => [
				'grok-4-1-fast-non-reasoning' => true,
				'grok-4-1-fast-reasoning'     => true,
				'grok-code-fast-1'            => true,
				'grok-4-fast-non-reasoning'   => true,
				'grok-4-fast-reasoning'       => true,
				'grok-4'                      => true,
				'grok-4-latest'               => true,
				'grok-4-0709'                 => true,
				'grok-3'                      => true,
				'grok-3-mini'                 => true,
				'grok-2-1212'                 => true,
			],
		];
	}

	/**
	 * Get request metadata for a model used by a provider client.
	 *
	 * @param string $provider Provider label.
	 * @param string $model    Model ID.
	 * @return array
	 */
	public static function get_request_config( $provider, $model ) {
		$model_config = self::get_text_model( $model );
		if ( ! $model_config || $provider !== $model_config['provider'] ) {
			return [];
		}

		return $model_config;
	}

	/**
	 * Get image models grouped for a settings control.
	 *
	 * @param string $capability Either generate or edit.
	 * @return array
	 */
	public static function get_image_models( $capability ) {
		$models = [
			'Google'    => [
				'gemini-3-pro-image'          => 'Gemini 3 Pro Image (Nano Banana Pro)',
				'gemini-3.1-flash-image'      => 'Gemini 3.1 Flash Image (Nano Banana 2)',
				'gemini-3.1-flash-lite-image' => 'Gemini 3.1 Flash-Lite Image (Nano Banana 2 Lite)',
			],
			'OpenAI'    => [
				'gpt-image-2' => 'GPT Image 2',
				'gpt-image-1' => 'GPT Image 1',
			],
			'Replicate' => [
				'google/nano-banana-2-lite'        => 'Nano Banana 2 Lite',
				'google/nano-banana-2'             => 'Nano Banana 2',
				'google/nano-banana-pro'           => 'Nano Banana Pro',
				'google/imagen-4'                  => 'Imagen 4',
				'google/imagen-4-ultra'            => 'Imagen 4 Ultra',
				'google/imagen-4-fast'             => 'Imagen 4 Fast',
				'black-forest-labs/flux-2-pro'     => 'FLUX 2 Pro',
				'black-forest-labs/flux-2-dev'     => 'FLUX 2 Dev',
				'black-forest-labs/flux-2-flex'    => 'FLUX 2 Flex',
				'ideogram-ai/ideogram-v3-turbo'    => 'Ideogram v3 Turbo',
				'ideogram-ai/ideogram-v3-quality'  => 'Ideogram v3 Quality',
				'ideogram-ai/ideogram-v3-balanced' => 'Ideogram v3 Balanced',
				'bytedance/seedream-4.5'           => 'Seedream 4.5',
				'qwen/qwen-image'                  => 'Qwen Image',
			],
		];

		if ( 'edit' === $capability ) {
			$models['Replicate'] = [
				'google/nano-banana-pro'             => 'Nano Banana Pro',
				'black-forest-labs/flux-kontext-max' => 'FLUX Kontext Max',
				'black-forest-labs/flux-kontext-dev' => 'FLUX Kontext Dev',
				'black-forest-labs/flux-2-pro'       => 'FLUX 2 Pro',
				'black-forest-labs/flux-2-dev'       => 'FLUX 2 Dev',
				'black-forest-labs/flux-2-flex'      => 'FLUX 2 Flex',
				'bytedance/seedream-4.5'             => 'Seedream 4.5',
				'bytedance/seededit-3.0'             => 'SeedEdit 3.0',
				'qwen/qwen-image-edit'               => 'Qwen Image Edit',
			];
		}

		return $models;
	}

	/**
	 * Determine the provider used by a built-in image model.
	 *
	 * @param string $model Image model ID.
	 * @return string|null
	 */
	public static function get_image_provider( $model ) {
		foreach ( [ 'generate', 'edit' ] as $capability ) {
			foreach ( self::get_image_models( $capability ) as $provider => $models ) {
				if ( isset( $models[ $model ] ) ) {
					return $provider;
				}
			}
		}

		if ( str_starts_with( $model, 'gemini-' ) ) {
			return 'Google';
		}
		if ( str_contains( $model, '/' ) ) {
			return 'Replicate';
		}

		return null;
	}
}
