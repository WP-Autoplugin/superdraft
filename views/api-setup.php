<?php
/**
 * Settings > API Setup.
 *
 * @package Superdraft
 * @since 1.0.0
 */

namespace Superdraft;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<table class="form-table">
	<tr valign="top">
		<th scope="row"><?php esc_html_e( 'OpenAI API Key', 'superdraft' ); ?></th>
		<td><input type="password" name="superdraft_api_keys[openai]" value="<?php echo esc_attr( get_option( 'superdraft_api_keys' )['openai'] ); ?>" class="large-text" /></td>
	</tr>
	<tr valign="top">
		<th scope="row"><?php esc_html_e( 'Anthropic API Key', 'superdraft' ); ?></th>
		<td><input type="password" name="superdraft_api_keys[anthropic]" value="<?php echo esc_attr( get_option( 'superdraft_api_keys' )['anthropic'] ); ?>" class="large-text" /></td>
	</tr>
	<tr valign="top">
		<th scope="row"><?php esc_html_e( 'Google Gemini API Key', 'superdraft' ); ?></th>
		<td><input type="password" name="superdraft_api_keys[google]" value="<?php echo esc_attr( get_option( 'superdraft_api_keys' )['google'] ); ?>" class="large-text" /></td>
	</tr>
	<tr valign="top">
		<th scope="row"><?php esc_html_e( 'xAI API Key', 'superdraft' ); ?></th>
		<td><input type="password" name="superdraft_api_keys[xai]" value="<?php echo esc_attr( get_option( 'superdraft_api_keys' )['xai'] ); ?>" class="large-text" /></td>
	</tr>
	<tr valign="top">
		<th scope="row"><?php esc_html_e( 'Replicate API Token', 'superdraft' ); ?></th>
		<td>
			<input type="password"
				name="superdraft_api_keys[replicate]"
				value="<?php echo esc_attr( get_option( 'superdraft_api_keys' )['replicate'] ); ?>"
				class="large-text" />
			<p class="description">
				<?php
				printf(
					// translators: %s: replicate.com link.
					esc_html__( 'The Replicate API offers a variety of models for image generation. You can find more information at %s.', 'superdraft' ),
					'<a href="https://replicate.com" target="_blank" rel="noopener noreferrer">replicate.com</a>'
				);
				?>
			</p>
		</td>
	</tr>

	<tr valign="top">
		<th scope="row"><?php esc_html_e( 'Custom Language Models', 'superdraft' ); ?></th>
		<td>
			<div id="custom-models-list">
				<!-- List will be populated via JS -->
				<div class="custom-models-items"></div>
			</div>

			<div id="add-custom-model-form">
				<input type="text" id="custom-model-name" placeholder="<?php esc_attr_e( 'Model Name (User-defined Label)', 'superdraft' ); ?>" class="large-text">
				<input type="url" id="custom-model-url" placeholder="<?php esc_attr_e( 'API Endpoint URL', 'superdraft' ); ?>" class="large-text">
				<input type="text" id="custom-model-parameter" placeholder="<?php esc_attr_e( '"model" Parameter Value', 'superdraft' ); ?>" class="large-text">
				<input type="password" id="custom-model-api-key" placeholder="<?php esc_attr_e( 'API Key', 'superdraft' ); ?>" class="large-text">
				<textarea id="custom-model-headers" placeholder="<?php esc_attr_e( 'Additional Headers (one per line, name=value)', 'superdraft' ); ?>" rows="4" class="large-text"></textarea>
				<button type="button" id="add-custom-model" class="button"><?php esc_html_e( 'Save Custom Model', 'superdraft' ); ?></button>
			</div>

			<input type="hidden" name="superdraft_custom_models" id="superdraft_custom_models" value="<?php echo esc_attr( wp_json_encode( get_option( 'superdraft_custom_models', [] ) ) ); ?>">

			<p class="description"><?php esc_html_e( 'Add any custom models you want to use with Superdraft. These models will be available in the model selection dropdown. The API must be compatible with the OpenAI API.', 'superdraft' ); ?></p>
		</td>
	</tr>
</table>
