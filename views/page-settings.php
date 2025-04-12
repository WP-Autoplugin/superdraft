<?php
/**
 * Admin view for the Superdraft Settings page.
 *
 * @package Superdraft
 * @since 1.0.0
 */

namespace Superdraft;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="wrap superdraft-settings">
	<h1>
		<?php esc_html_e( 'Superdraft Settings', 'superdraft' ); ?>
	</h1>
	<?php settings_errors(); ?>
	<h2 class="nav-tab-wrapper">
		<a href="#tags-categories" class="nav-tab nav-tab-active"><?php esc_html_e( 'Auto Tags & Categories', 'superdraft' ); ?></a>
		<a href="#writing-tips" class="nav-tab"><?php esc_html_e( 'Writing Tips', 'superdraft' ); ?></a>
		<a href="#autocomplete" class="nav-tab"><?php esc_html_e( 'Autocomplete', 'superdraft' ); ?></a>
		<a href="#images" class="nav-tab"><?php esc_html_e( 'Image Generation', 'superdraft' ); ?></a>
		<a href="#api-setup" class="nav-tab"><?php esc_html_e( 'API Setup', 'superdraft' ); ?></a>
	</h2>
	<form method="post" action="options.php" id="superdraft-settings-form">
		<?php
		settings_fields( 'superdraft_settings' );
		$settings = get_option(
			'superdraft_settings',
			[
				'tags_categories' => [
					'enabled' => false,
					'model'   => 'gpt-4o-mini',
				],
				'writing_tips'    => [
					'enabled' => false,
					'model'   => 'gpt-4o-mini',
				],
				'autocomplete'    => [
					'enabled' => false,
					'model'   => 'gpt-4o-mini',
				],
			]
		);
		?>
		<div id="tags-categories" class="tab-content">
			<h3><?php esc_html_e( 'Auto Tags & Categories', 'superdraft' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Suggest new tags and categories based on your content, and automatically choose the right tags and categories for your posts. Also works in bulk edit mode.', 'superdraft' ); ?></p>
			<table class="form-table">
				<tr valign="top" class="superdraft-tags-categories-enabled-row superdraft-module-toggle-row">
					<th scope="row"><?php esc_html_e( 'Enable Auto Tags & Categories', 'superdraft' ); ?></th>
					<td>
						<input type="checkbox" name="superdraft_settings[tags_categories][enabled]" id="superdraft_tags_categories_enabled" 
							value="1" <?php checked( 1, $settings['tags_categories']['enabled'], true ); ?> />
						<label for="superdraft_tags_categories_enabled"><?php esc_html_e( 'Automatically categorize and tag posts using AI', 'superdraft' ); ?></label>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Never Deselect Terms', 'superdraft' ); ?></th>
					<td>
						<input type="hidden" name="superdraft_settings[tags_categories][never_deselect]" value="0" />
						<input type="checkbox" name="superdraft_settings[tags_categories][never_deselect]" id="superdraft_tags_categories_never_deselect" 
							value="1" <?php checked( 1, $settings['tags_categories']['never_deselect'] ?? true, true ); ?> />
						<label for="superdraft_tags_categories_never_deselect"><?php esc_html_e( 'When this option is enabled, the AI will never deselect terms that are already selected for a post, only suggest new ones', 'superdraft' ); ?></label>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'New Term Suggestions', 'superdraft' ); ?></th>
					<td>
						<input type="number" name="superdraft_settings[tags_categories][min_suggestions]" id="superdraft_tags_categories_min_suggestions" 
							value="<?php echo esc_attr( $settings['tags_categories']['min_suggestions'] ?? 3 ); ?>" 
							min="1" max="10" />
						<p class="description"><?php esc_html_e( 'Minimum number of terms to suggest when creating new tags/categories. Default: 3', 'superdraft' ); ?></p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Context for Suggestions', 'superdraft' ); ?></th>
					<td>
						<input type="number" name="superdraft_settings[tags_categories][suggestions_context]" id="superdraft_tags_categories_suggestions_context" 
							value="<?php echo esc_attr( $settings['tags_categories']['suggestions_context'] ?? 20 ); ?>" 
							min="1" max="999" />
						<p class="description"><?php esc_html_e( 'Number of post excerpts to use as context for generating suggestions. Default: 20', 'superdraft' ); ?></p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Select AI Model', 'superdraft' ); ?></th>
					<td>
						<?php
							$allowed_html = [
								'select' => [
									'name' => true,
									'class' => true,
								],
								'optgroup' => [
									'label' => true,
									'class' => true,
								],
								'option' => [
									'value' => true,
									'selected' => true,
								],
							];
							echo wp_kses( self::get_model_select( 'tags_categories' ), $allowed_html );
						?>
					</td>
				</tr>
			</table>
		</div>

		<div id="writing-tips" class="tab-content" style="display:none;">
			<h3><?php esc_html_e( 'AI Writing Tips', 'superdraft' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Get writing, SEO, and readability tips while editing your posts.', 'superdraft' ); ?></p>
			<table class="form-table">
				<tr valign="top" class="superdraft-writing-tips-enabled-row superdraft-module-toggle-row">
					<th scope="row"><?php esc_html_e( 'Enable AI Writing Tips', 'superdraft' ); ?></th>
					<td>
						<input type="checkbox" id="superdraft_writing_tips_enabled" 
							name="superdraft_settings[writing_tips][enabled]" 
							value="1" <?php checked( 1, $settings['writing_tips']['enabled'], true ); ?> />
						<label for="superdraft_writing_tips_enabled"><?php esc_html_e( 'Provide tips while editing', 'superdraft' ); ?></label>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Minimum Tips', 'superdraft' ); ?></th>
					<td>
						<input type="number" id="superdraft_writing_tips_min_tips" 
							name="superdraft_settings[writing_tips][min_tips]" 
							value="<?php echo esc_attr( $settings['writing_tips']['min_tips'] ?? 5 ); ?>" 
							min="1" max="20" />
						<p class="description"><?php esc_html_e( 'Minimum number of tips to generate. The AI may add more if needed.', 'superdraft' ); ?></p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Auto-update Interval', 'superdraft' ); ?></th>
					<td>
						<input type="number" id="superdraft_writing_tips_auto_update" 
							name="superdraft_settings[writing_tips][auto_update]" 
							value="<?php echo esc_attr( $settings['writing_tips']['auto_update'] ?? 0 ); ?>" 
							min="0" max="60" />
						<p class="description"><?php esc_html_e( 'Automatically update tips every X minutes while editing (only if content changes). Set to 0 to disable and update manually.', 'superdraft' ); ?></p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Select AI Model', 'superdraft' ); ?></th>
					<td>
						<?php
							echo wp_kses( self::get_model_select( 'writing_tips' ), $allowed_html );
						?>
					</td>
				</tr>
			</table>
		</div>

		<div id="autocomplete" class="tab-content" style="display:none;">
			<h3><?php esc_html_e( 'Autocomplete for Post Contents', 'superdraft' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Get AI-powered autocomplete suggestions while writing your posts. Type the trigger prefix to see suggestions and continue typing a word to influence the suggestions.', 'superdraft' ); ?></p>
			<table class="form-table">
				<tr valign="top" class="superdraft-autocomplete-enabled-row superdraft-module-toggle-row">
					<th scope="row"><?php esc_html_e( 'Enable AI Autocomplete', 'superdraft' ); ?></th>
					<td>
						<input type="checkbox" id="superdraft_autocomplete_enabled" 
							name="superdraft_settings[autocomplete][enabled]" 
							value="1" <?php checked( 1, $settings['autocomplete']['enabled'], true ); ?> />
						<label for="superdraft_autocomplete_enabled"><?php esc_html_e( 'Provide autocomplete suggestions while writing content', 'superdraft' ); ?></label>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Trigger Prefix', 'superdraft' ); ?></th>
					<td>
						<input type="text" id="superdraft_autocomplete_prefix" 
							name="superdraft_settings[autocomplete][prefix]" 
							value="<?php echo esc_attr( $settings['autocomplete']['prefix'] ?? '~' ); ?>" 
							maxlength="3" style="width: 50px" />
						<p class="description"><?php esc_html_e( 'Character(s) that trigger autocomplete suggestions. Default: ~', 'superdraft' ); ?></p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Empty Search Suggestions', 'superdraft' ); ?></th>
					<td>
						<input type="hidden" name="superdraft_settings[autocomplete][empty_search]" value="0" />
						<input type="checkbox" id="superdraft_autocomplete_empty_search" 
							name="superdraft_settings[autocomplete][empty_search]" 
							value="1" <?php checked( 1, $settings['autocomplete']['empty_search'] ?? true, true ); ?> />
						<label for="superdraft_autocomplete_empty_search">
							<?php esc_html_e( 'Show suggestions when only the trigger prefix is typed', 'superdraft' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'When disabled, suggestions will only show when typing one or more characters after the trigger prefix.', 'superdraft' ); ?></p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Number of Suggestions', 'superdraft' ); ?></th>
					<td>
						<input type="number" id="superdraft_autocomplete_suggestions" 
							name="superdraft_settings[autocomplete][suggestions]" 
							value="<?php echo esc_attr( $settings['autocomplete']['suggestions'] ?? 3 ); ?>" 
							min="1" max="10" />
						<p class="description"><?php esc_html_e( 'Number of suggestions to show (1-10). Default: 3', 'superdraft' ); ?></p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Context Length', 'superdraft' ); ?></th>
					<td>
						<fieldset>
							<label>
								<input type="radio" name="superdraft_settings[autocomplete][context_length]" 
									value="1" <?php checked( '1', $settings['autocomplete']['context_length'], true ); ?> />
								<?php esc_html_e( 'Small (2 blocks)', 'superdraft' ); ?>
							</label><br>
							<label>
								<input type="radio" name="superdraft_settings[autocomplete][context_length]" 
									value="3" <?php checked( '3', $settings['autocomplete']['context_length'], true ); ?> />
								<?php esc_html_e( 'Large (6 blocks)', 'superdraft' ); ?>
							</label><br>
							<label>
								<input type="radio" name="superdraft_settings[autocomplete][context_length]" 
									value="999" <?php checked( '999', $settings['autocomplete']['context_length'], true ); ?> />
								<?php esc_html_e( 'Full Post', 'superdraft' ); ?>
							</label>
						</fieldset>
						<p class="description"><?php esc_html_e( 'Amount of text to use as context for generating suggestions.', 'superdraft' ); ?></p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Debounce Delay', 'superdraft' ); ?></th>
					<td>
						<input type="number" id="superdraft_autocomplete_debounce" 
							name="superdraft_settings[autocomplete][debounce_delay]" 
							value="<?php echo esc_attr( $settings['autocomplete']['debounce_delay'] ?? 500 ); ?>" 
							min="100" max="10000" step="100" />
						<p class="description"><?php esc_html_e( 'Delay in milliseconds before showing suggestions. Default: 500', 'superdraft' ); ?></p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Select AI Model', 'superdraft' ); ?></th>
					<td>
						<?php
							echo wp_kses( self::get_model_select( 'autocomplete' ), $allowed_html );
						?>
					</td>
				</tr>
				<!-- Smart Compose Settings -->
				<tr valign="top" class="superdraft-smart-compose-enabled-row superdraft-module-toggle-row">
					<th scope="row"><?php esc_html_e( 'Smart Compose', 'superdraft' ); ?></th>
					<td>
						<fieldset>
							<legend class="screen-reader-text"><?php esc_html_e( 'Smart Compose Settings', 'superdraft' ); ?></legend>
							<input type="hidden" name="superdraft_settings[autocomplete][smart_compose_enabled]" value="0" />
							<input type="checkbox" id="superdraft_autocomplete_smart_compose_enabled" 
								name="superdraft_settings[autocomplete][smart_compose_enabled]" 
								value="1" <?php checked( 1, $settings['autocomplete']['smart_compose_enabled'] ?? false, true ); ?> />
							<label for="superdraft_autocomplete_smart_compose_enabled">
								<?php esc_html_e( 'Enable Smart Compose (beta)', 'superdraft' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Provides real-time suggestions as you type in paragraph blocks.', 'superdraft' ); ?></p>
						</fieldset>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Smart Compose Model', 'superdraft' ); ?></th>
					<td>
						<?php
							echo wp_kses( self::get_model_select( 'autocomplete', 'smart_compose_model' ), $allowed_html );
						?>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Smart Compose Delay', 'superdraft' ); ?></th>
					<td>
						<input type="number" id="superdraft_autocomplete_smart_compose_delay" 
							name="superdraft_settings[autocomplete][smart_compose_delay]" 
							value="<?php echo esc_attr( $settings['autocomplete']['smart_compose_delay'] ?? 1500 ); ?>" 
							min="500" max="5000" step="100" />
						<p class="description"><?php esc_html_e( 'Delay in milliseconds before showing Smart Compose suggestions. Default: 1500', 'superdraft' ); ?></p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Smart Compose Max Tokens', 'superdraft' ); ?></th>
					<td>
						<input type="number" id="superdraft_autocomplete_smart_compose_max_tokens" 
							name="superdraft_settings[autocomplete][smart_compose_max_tokens]" 
							value="<?php echo esc_attr( $settings['autocomplete']['smart_compose_max_tokens'] ?? 10 ); ?>" 
							min="1" max="1000" />
						<p class="description"><?php esc_html_e( 'Maximum number of tokens to generate for Smart Compose suggestions. One token is generally one word or a few characters in English. Default: 10', 'superdraft' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<div id="images" class="tab-content" style="display:none;">
			<h3><?php esc_html_e( 'AI Image Generation', 'superdraft' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Generate a featured image for your post using AI based on a textual prompt.', 'superdraft' ); ?></p>
			<table class="form-table">
				<tr valign="top" class="superdraft-image-generation-enabled-row superdraft-module-toggle-row">
					<th scope="row"><?php esc_html_e( 'Enable AI Image Generation', 'superdraft' ); ?></th>
					<td>
						<input type="checkbox" id="superdraft_image_generation_enabled"
							name="superdraft_settings[images][enabled]" 
							value="1" <?php checked( 1, $settings['images']['enabled'] ?? false, true ); ?> />
						<label for="superdraft_image_generation_enabled"><?php esc_html_e( 'Generate featured images using AI', 'superdraft' ); ?></label>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Select Image Model', 'superdraft' ); ?></th>
					<td>
						<?php echo wp_kses( \Superdraft\Admin::get_model_select( 'images', 'image_model' ), $allowed_html ); ?>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Prompt Generator Model', 'superdraft' ); ?></th>
					<td>
						<?php echo wp_kses( \Superdraft\Admin::get_model_select( 'images', 'prompt_model' ), $allowed_html ); ?>
					</td>
				</tr>
			</table>
		</div>

		<div id="api-setup" class="tab-content" style="display:none;">
			<h3><?php esc_html_e( 'API Setup', 'superdraft' ); ?></h3>
			<?php
				require SUPERDRAFT_DIR . 'views/api-setup.php';
			?>
		</div>

		<?php submit_button(); ?>
	</form>
</div>