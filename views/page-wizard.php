<?php
/**
 * Admin view for the Superdraft Setup Wizard page.
 *
 * @package Superdraft
 * @since 1.1.5
 */

namespace Superdraft;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$api_keys = get_option( 'superdraft_api_keys', [] );

$providers = [
	'openai'    => [
		'name' => 'OpenAI',
		'desc' => __( 'GPT-4o, GPT-4o-mini, o3, and more', 'superdraft' ),
		'icon' => 'openai',
		'key'  => $api_keys['openai'] ?? '',
	],
	'anthropic' => [
		'name' => 'Anthropic',
		'desc' => __( 'Claude 3.5 Sonnet, Claude 3 Opus, and more', 'superdraft' ),
		'icon' => 'anthropic',
		'key'  => $api_keys['anthropic'] ?? '',
	],
	'google'    => [
		'name' => 'Google / Gemini',
		'desc' => __( 'Gemini 2.5 Pro, Gemini 2.5 Flash, and more', 'superdraft' ),
		'icon' => 'google',
		'key'  => $api_keys['google'] ?? '',
	],
	'xai'       => [
		'name' => 'xAI',
		'desc' => __( 'Grok 3, Grok 3 Mini, and more', 'superdraft' ),
		'icon' => 'xai',
		'key'  => $api_keys['xai'] ?? '',
	],
	'custom'    => [
		'name' => __( 'Custom / Other', 'superdraft' ),
		'desc' => __( 'Use a custom API endpoint', 'superdraft' ),
		'icon' => 'custom',
		'key'  => '',
	],
];

$current_step = 1;
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only wizard step selector.
if ( isset( $_GET['step'] ) ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only wizard step selector.
	$current_step = absint( $_GET['step'] );
}

// Module definitions with descriptions.
$modules = [
	'smart_compose'   => [
		'title' => __( 'Smart Compose', 'superdraft' ),
		'desc'  => __( 'AI-powered suggestions as you type in the editor.', 'superdraft' ),
		'icon'  => '✍️',
	],
	'autocomplete'    => [
		'title' => __( 'Autocomplete', 'superdraft' ),
		'desc'  => __( 'Complete sentences and paragraphs with AI.', 'superdraft' ),
		'icon'  => '🔮',
	],
	'tags_categories' => [
		'title' => __( 'AI Tags & Categories', 'superdraft' ),
		'desc'  => __( 'Auto-suggest and bulk-generate tags for your posts.', 'superdraft' ),
		'icon'  => '🏷️',
	],
	'images'          => [
		'title' => __( 'Image Generation', 'superdraft' ),
		'desc'  => __( 'Generate featured images with AI prompts.', 'superdraft' ),
		'icon'  => '🖼️',
	],
	'writing_tips'    => [
		'title' => __( 'Writing Tips', 'superdraft' ),
		'desc'  => __( 'SEO and readability suggestions in the sidebar.', 'superdraft' ),
		'icon'  => '💡',
	],
];

$enabled_modules = get_option( 'superdraft_enabled_modules', [] );
if ( ! is_array( $enabled_modules ) ) {
	$enabled_modules = [];
}
?>
<div class="wrap superdraft-wizard">
	<div class="superdraft-wizard-header">
		<h1><?php esc_html_e( 'Welcome to Superdraft!', 'superdraft' ); ?></h1>
		<p class="superdraft-wizard-subtitle"><?php esc_html_e( 'Let\'s set up your AI writing assistant in just a few steps.', 'superdraft' ); ?></p>
	</div>

	<div class="superdraft-wizard-progress">
		<div class="superdraft-wizard-progress-bar" data-step="<?php echo esc_attr( $current_step ); ?>">
			<div class="superdraft-wizard-step active" data-step="1">
				<span class="step-number">1</span>
				<span class="step-label"><?php esc_html_e( 'Provider', 'superdraft' ); ?></span>
			</div>
			<div class="superdraft-wizard-step" data-step="2">
				<span class="step-number">2</span>
				<span class="step-label"><?php esc_html_e( 'API Setup', 'superdraft' ); ?></span>
			</div>
			<div class="superdraft-wizard-step" data-step="3">
				<span class="step-number">3</span>
				<span class="step-label"><?php esc_html_e( 'Features', 'superdraft' ); ?></span>
			</div>
			<div class="superdraft-wizard-step" data-step="4">
				<span class="step-number">4</span>
				<span class="step-label"><?php esc_html_e( 'Try it!', 'superdraft' ); ?></span>
			</div>
		</div>
	</div>

	<div class="superdraft-wizard-container">
		<!-- Step 1: Choose Provider -->
		<div class="superdraft-wizard-step-content" data-step="1">
			<h2><?php esc_html_e( 'Choose Your AI Provider', 'superdraft' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Select the AI provider you want to use. You can change this later in settings.', 'superdraft' ); ?></p>

			<div class="superdraft-wizard-providers">
				<?php foreach ( $providers as $key => $provider ) : ?>
				<div class="superdraft-wizard-provider-card" data-provider="<?php echo esc_attr( $key ); ?>">
					<svg class="provider-icon"><use href="<?php echo esc_url( SUPERDRAFT_URL . 'assets/admin/images/provider-logos.svg#logo-' . $provider['icon'] ); ?>"></use></svg>
					<h3><?php echo esc_html( $provider['name'] ); ?></h3>
					<p><?php echo esc_html( $provider['desc'] ); ?></p>
					<?php if ( ! empty( $provider['key'] ) ) : ?>
						<span class="provider-configured"><?php esc_html_e( 'Already configured', 'superdraft' ); ?></span>
					<?php endif; ?>
				</div>
				<?php endforeach; ?>
			</div>
			<p class="description" style="margin-top:10px;">
				<?php esc_html_e( 'You can add custom models in the Settings page after setup.', 'superdraft' ); ?>
			</p>

			<div class="superdraft-wizard-actions">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=superdraft-settings' ) ); ?>" class="button button-secondary">
					<?php esc_html_e( 'Skip to Settings', 'superdraft' ); ?>
				</a>
				<button type="button" class="button button-primary superdraft-wizard-next" disabled>
					<?php esc_html_e( 'Next', 'superdraft' ); ?> →
				</button>
			</div>
		</div>

		<!-- Step 2: API Setup -->
		<div class="superdraft-wizard-step-content" data-step="2" style="display:none;">
			<h2><?php esc_html_e( 'API Setup', 'superdraft' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Enter your API key. Superdraft will test it before continuing and choose sensible models for each feature.', 'superdraft' ); ?>
			</p>

			<div class="superdraft-wizard-api-setup">
				<div class="api-setup-section">
					<h3><?php esc_html_e( 'Enter Your API Key', 'superdraft' ); ?></h3>
					<div class="form-field">
						<label for="superdraft-wizard-api-key">
							<?php esc_html_e( 'API Key', 'superdraft' ); ?>
							<span class="required">*</span>
						</label>
						<input
							type="text"
							id="superdraft-wizard-api-key"
							class="regular-text"
							placeholder=""
							autocomplete="off"
							autocapitalize="off"
							autocorrect="off"
							spellcheck="false"
							data-lpignore="true"
							data-1p-ignore="true"
							data-bwignore="true"
							style="-webkit-text-security: disc;"
						/>
						<p class="description" id="superdraft-wizard-key-hint">
							<?php esc_html_e( 'Enter your API key here.', 'superdraft' ); ?>
						</p>
					</div>

					<div class="superdraft-wizard-key-links">
						<a href="https://platform.openai.com/api-keys" target="_blank" class="key-link" data-provider="openai">
							<?php esc_html_e( 'Get an OpenAI API key →', 'superdraft' ); ?>
						</a>
						<a href="https://console.anthropic.com/settings/keys" target="_blank" class="key-link" data-provider="anthropic">
							<?php esc_html_e( 'Get an Anthropic API key →', 'superdraft' ); ?>
						</a>
						<a href="https://aistudio.google.com/app/apikey" target="_blank" class="key-link" data-provider="google">
							<?php esc_html_e( 'Get a Google API key →', 'superdraft' ); ?>
						</a>
						<a href="https://console.x.ai/" target="_blank" class="key-link" data-provider="xai">
							<?php esc_html_e( 'Get an xAI API key →', 'superdraft' ); ?>
						</a>
						<span class="key-link" data-provider="custom">
							<?php esc_html_e( 'Enter your custom API endpoint details.', 'superdraft' ); ?>
						</span>
					</div>

					<div class="superdraft-wizard-test-result" style="display:none;">
						<div class="test-result-icon"></div>
						<div class="test-result-message"></div>
					</div>
				</div>
			</div>

			<div class="superdraft-wizard-actions">
				<button type="button" class="button button-secondary superdraft-wizard-prev">
					← <?php esc_html_e( 'Back', 'superdraft' ); ?>
				</button>
				<button type="button" class="button button-primary superdraft-wizard-next" disabled>
					<?php esc_html_e( 'Next', 'superdraft' ); ?> →
				</button>
			</div>
		</div>

		<!-- Step 3: Enable Features (Modules) -->
		<div class="superdraft-wizard-step-content" data-step="3" style="display:none;">
			<h2><?php esc_html_e( 'Enable Features', 'superdraft' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Choose which AI features to enable. You can always change these later in Settings.', 'superdraft' ); ?>
			</p>

			<div class="superdraft-wizard-carousel">
				<button type="button" class="carousel-nav carousel-prev" aria-label="<?php esc_attr_e( 'Previous feature', 'superdraft' ); ?>">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
						<polyline points="15 18 9 12 15 6"></polyline>
					</svg>
				</button>

				<div class="carousel-slides">
					<?php foreach ( $modules as $key => $module ) : ?>
					<div class="superdraft-wizard-module-card" data-module="<?php echo esc_attr( $key ); ?>">
						<div class="module-header">
							<h3><?php echo esc_html( $module['title'] ); ?></h3>
							<div class="module-toggle">
								<input type="checkbox" id="module-<?php echo esc_attr( $key ); ?>" class="module-checkbox" checked />
								<label for="module-<?php echo esc_attr( $key ); ?>" class="toggle-label"></label>
								<span class="toggle-status"><?php esc_html_e( 'Enabled', 'superdraft' ); ?></span>
							</div>
						</div>
						<p class="module-description"><?php echo esc_html( $module['desc'] ); ?></p>
						<div class="module-preview">
							<div class="module-preview-svg">
								<?php
								$svg_map  = [
									'smart_compose'   => 'smart-compose.svg',
									'autocomplete'    => 'autocomplete.svg',
									'tags_categories' => 'auto-tags.svg',
									'images'          => 'image-gen.svg',
									'writing_tips'    => 'writing-tips.svg',
								];
								$svg_file = $svg_map[ $key ] ?? '';
								if ( $svg_file && file_exists( SUPERDRAFT_DIR . 'assets/admin/images/features/' . $svg_file ) ) :
										echo file_get_contents( SUPERDRAFT_DIR . 'assets/admin/images/features/' . $svg_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPress.Security.EscapeOutput.OutputNotEscaped -- Local bundled SVG preview.
								else :
									?>
								<svg class="preview-placeholder" viewBox="0 0 200 100" xmlns="http://www.w3.org/2000/svg">
									<rect x="10" y="10" width="180" height="80" rx="5" fill="#f0f6fc" />
									<text x="100" y="55" text-anchor="middle" font-size="14" fill="#2271b1"><?php echo esc_html( $module['title'] ); ?></text>
								</svg>
								<?php endif; ?>
							</div>
						</div>
					</div>
					<?php endforeach; ?>
				</div>

				<button type="button" class="carousel-nav carousel-next" aria-label="<?php esc_attr_e( 'Next feature', 'superdraft' ); ?>">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
						<polyline points="9 18 15 12 9 6"></polyline>
					</svg>
				</button>
			</div>

			<div class="carousel-dots">
				<?php
				$module_index = 0;
				foreach ( $modules as $key => $module ) :
					?>
					<button type="button" class="carousel-dot" data-slide="<?php echo esc_attr( $module_index ); ?>" aria-label="<?php printf( /* translators: %s: Feature name */ esc_attr__( 'Feature: %s', 'superdraft' ), esc_attr( $module['title'] ) ); ?>" aria-current="<?php echo 0 === $module_index ? 'true' : 'false'; ?>"></button>
					<?php
					++$module_index;
				endforeach;
				?>
			</div>

			<div class="superdraft-wizard-actions">
				<button type="button" class="button button-secondary superdraft-wizard-prev">
					← <?php esc_html_e( 'Back', 'superdraft' ); ?>
				</button>
				<button type="button" class="button button-primary superdraft-wizard-next">
					<?php esc_html_e( 'Next', 'superdraft' ); ?> →
				</button>
			</div>
		</div>

		<!-- Step 4: Try it now -->
		<div class="superdraft-wizard-step-content" data-step="4" style="display:none;">
			<h2><?php esc_html_e( 'You\'re All Set!', 'superdraft' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Your AI writing assistant is ready. Try it out by creating a demo post, or dive straight into the settings.', 'superdraft' ); ?>
			</p>

			<div class="superdraft-wizard-try-it">
				<div class="superdraft-wizard-features-grid">
					<?php foreach ( $modules as $key => $module ) : ?>
					<div class="feature-card" data-module="<?php echo esc_attr( $key ); ?>">
						<span class="feature-icon"><?php echo esc_html( $module['icon'] ); ?></span>
						<h3><?php echo esc_html( $module['title'] ); ?></h3>
						<p><?php echo esc_html( $module['desc'] ); ?></p>
					</div>
					<?php endforeach; ?>
				</div>

				<div class="superdraft-wizard-demo-actions">
					<button type="button" class="button button-primary superdraft-wizard-create-demo">
						<?php esc_html_e( 'Create a Demo Post', 'superdraft' ); ?>
					</button>
					<a href="<?php echo esc_url( admin_url( 'post-new.php' ) ); ?>" class="button button-secondary">
						<?php esc_html_e( 'Create a New Post', 'superdraft' ); ?>
					</a>
				</div>
			</div>

			<div class="superdraft-wizard-actions">
				<button type="button" class="button button-secondary superdraft-wizard-prev">
					← <?php esc_html_e( 'Back', 'superdraft' ); ?>
				</button>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=superdraft-settings' ) ); ?>" class="button button-primary superdraft-wizard-finish">
					<?php esc_html_e( 'Finish & Go to Settings', 'superdraft' ); ?> →
				</a>
			</div>
		</div>
	</div>

	<div class="superdraft-wizard-footer">
		<a href="#" class="superdraft-wizard-dismiss">
			<?php esc_html_e( 'I\'ll set this up later — Dismiss Wizard', 'superdraft' ); ?>
		</a>
	</div>
</div>

<script type="text/javascript">
// Pass enabled modules to JS.
var superdraftWizardApiKeys = <?php echo wp_json_encode( $api_keys ); ?>;
var superdraftEnabledModules = <?php echo wp_json_encode( $enabled_modules ); ?>;
</script>
