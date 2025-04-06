<?php
/**
 * The xAI API class for Superdraft.
 *
 * @package Superdraft
 * @since 1.0.0
 */

namespace Superdraft;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The xAI API class. Their API is compatible with OpenAI's, so this class extends OpenAI_API.
 */
class XAI_API extends OpenAI_API {

	/**
	 * Selected model.
	 *
	 * @var string
	 */
	protected $model;

	/**
	 * API URL.
	 *
	 * @var string
	 */
	protected $api_url = 'https://api.x.ai/v1/chat/completions';

	/**
	 * Max tokens parameter.
	 *
	 * @var int
	 */
	protected $max_tokens = 8192;

	/**
	 * Set the model.
	 *
	 * @param string $model The model.
	 */
	public function set_model( $model ) {
		$this->model = sanitize_text_field( $model );
	}

	/**
	 * Get allowed parameters.
	 *
	 * @return array The allowed parameters.
	 */
	protected function get_allowed_parameters() {
		return [
			'model',
			'temperature',
			'max_tokens',
			'messages',
		];
	}
}
