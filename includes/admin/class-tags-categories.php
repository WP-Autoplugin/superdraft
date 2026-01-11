<?php
/**
 * Superdraft Taxonomy Auto-select module.
 *
 * Handles auto-selection for tags and categories, including bulk processing. Also provides
 * a form for suggesting new terms based on AI-generated suggestions.
 *
 * @package Superdraft
 * @since 1.0.0
 */

namespace Superdraft;

use League\HTMLToMarkdown\HtmlConverter; // Import the converter.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tags and Categories class.
 */
class Tags_Categories {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_taxonomy_autoselect_endpoint' ] );
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor_assets' ] );

		// Register our custom bulk action.
		add_filter( 'bulk_actions-edit-post', [ $this, 'register_bulk_action' ], 9 );
		add_filter( 'handle_bulk_actions-edit-post', [ $this, 'handle_bulk_action' ], 10, 3 );

		// Action Scheduler callback.
		add_action( 'superdraft_process_autoselect', [ $this, 'process_autoselect_job' ], 10, 3 );

		// Bulk process status.
		add_action( 'admin_notices', [ $this, 'display_bulk_process_notice' ] );
		add_action( 'admin_init', [ $this, 'handle_cancel_bulk_process' ] );
		add_action( 'superdraft_bulk_process_completed', [ $this, 'cleanup_bulk_process_data' ] );

		// Taxonomy suggestions.
		add_action( 'category_add_form_fields', [ $this, 'render_suggestion_form' ] );
		add_action( 'post_tag_add_form_fields', [ $this, 'render_suggestion_form' ] );
		add_action( 'wp_ajax_superdraft_suggest_terms', [ $this, 'handle_suggest_terms' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_taxonomy_assets' ] );
	}

	/**
	 * Register the taxonomy auto-select endpoint.
	 */
	public function register_taxonomy_autoselect_endpoint() {
		register_rest_route(
			'superdraft/v1',
			'/taxonomy-autoselect',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_autoselect' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'postTitle'      => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'postContent'    => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'wp_kses_post',
					],
					'taxonomy'       => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'availableTerms' => [
						'required' => true,
						'type'     => 'array',
					],
				],
			]
		);
	}

	/**
	 * Enqueue editor scripts and styles.
	 */
	public function enqueue_editor_assets() {
		wp_enqueue_script(
			'superdraft-tags-categories-js',
			SUPERDRAFT_URL . 'assets/admin/js/dist/tags-categories.js',
			[ 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-api-fetch', 'wp-i18n' ],
			SUPERDRAFT_VERSION,
			true
		);

		wp_set_script_translations( 'superdraft-tags-categories-js', 'superdraft', SUPERDRAFT_DIR . 'languages' );

		wp_enqueue_style(
			'superdraft-tags-categories-css',
			SUPERDRAFT_URL . 'assets/admin/css/tags-categories.css',
			[],
			SUPERDRAFT_VERSION
		);
	}

	/**
	 * Enqueue assets for taxonomy pages.
	 *
	 * @param string $hook The current admin page.
	 * @return void
	 */
	public function enqueue_taxonomy_assets( $hook ) {
		$screens = [ 'edit-tags.php', 'term.php' ];
		if ( ! in_array( $hook, $screens, true ) ) {
			return;
		}

		wp_enqueue_script(
			'superdraft-taxonomy-suggestions',
			SUPERDRAFT_URL . 'assets/admin/js/taxonomy-suggestions.js',
			[ 'jquery', 'wp-i18n' ],
			SUPERDRAFT_VERSION,
			true
		);

		wp_localize_script(
			'superdraft-taxonomy-suggestions',
			'superdraftTax',
			[
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'superdraft-suggest-terms' ),
			]
		);

		wp_set_script_translations( 'superdraft-taxonomy-suggestions', 'superdraft', SUPERDRAFT_DIR . 'languages' );

		wp_enqueue_style(
			'superdraft-taxonomy-suggestions',
			SUPERDRAFT_URL . 'assets/admin/css/taxonomy-suggestions.css',
			[],
			SUPERDRAFT_VERSION
		);
	}

	/**
	 * Add our custom bulk action to the Posts list screen.
	 *
	 * @param array $bulk_actions Existing bulk actions.
	 * @return array
	 */
	public function register_bulk_action( $bulk_actions ) {
		if ( ! $this->check_permission() ) {
			return $bulk_actions;
		}

		$bulk_actions['superdraft_auto_select_categories'] = __( 'Auto-select Categories', 'superdraft' );
		$bulk_actions['superdraft_auto_select_tags']       = __( 'Auto-select Tags', 'superdraft' );
		return $bulk_actions;
	}

	/**
	 * Handle our custom bulk action and schedule background jobs.
	 *
	 * @param string $redirect_url URL to redirect to after bulk action.
	 * @param string $action       The action name.
	 * @param array  $post_ids     IDs of the selected posts.
	 * @return string
	 */
	public function handle_bulk_action( $redirect_url, $action, $post_ids ) {
		if ( ! $this->check_permission() ) {
			return $redirect_url;
		}

		/**
		 * Filter the interval between bulk process actions.
		 * Default is 60 seconds.
		 *
		 * @param int    $interval The interval in seconds.
		 * @param string $action   The bulk action name.
		 * @param array  $post_ids The post IDs.
		 */
		$interval = apply_filters( 'superdraft_bulk_process_interval', 60, $action, $post_ids );

		if ( 'superdraft_auto_select_categories' === $action || 'superdraft_auto_select_tags' === $action ) {
			$taxonomy = ( 'superdraft_auto_select_categories' === $action ) ? 'category' : 'post_tag';

			// Calculate the first scheduled time (start in 1 minute).
			$schedule_time = time() + $interval;

			// Store bulk process data.
			$bulk_data = [
				'total'          => count( $post_ids ),
				'processed'      => 0,
				'start_time'     => time(),
				'post_ids'       => $post_ids,
				'last_scheduled' => $schedule_time + ( ( count( $post_ids ) - 1 ) * $interval ), // Store when the last action will run.
			];
			update_option( 'superdraft_bulk_process_data', $bulk_data );

			// Queue each post for scheduled processing, one minute apart.
			foreach ( $post_ids as $post_id ) {
				// Schedule the action via Action Scheduler.
				as_schedule_single_action(
					$schedule_time,
					'superdraft_process_autoselect',
					[
						'post_id'      => $post_id,
						'taxonomy'     => $taxonomy,
						'bulk_process' => true,
					],
					'superdraft'
				);

				// Increment schedule time by 1 minute for the next post.
				$schedule_time += $interval;
			}
		}

		return $redirect_url;
	}

	/**
	 * The background job that processes a single post.
	 * Converts HTML to Markdown, then assigns tags/categories with AI help.
	 *
	 * @param int    $post_id The post to process.
	 * @param string $taxonomy The taxonomy to assign terms to.
	 * @param bool   $bulk_process Whether this is part of a bulk process.
	 */
	public function process_autoselect_job( $post_id, $taxonomy, $bulk_process = false ) {
		$post = get_post( $post_id );
		if ( ! $post || 'trash' === get_post_status( $post ) ) {
			return;
		}

		$settings = get_option( 'superdraft_settings', [] );

		// Convert HTML to Markdown.
		$converter       = new HtmlConverter();
		$post_content_md = $converter->convert( $post->post_content );
		$post_title      = $post->post_title;

		$taxonomy        = $taxonomy ? $taxonomy : 'category'; // Default to category.
		$available_terms = get_terms(
			[
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			]
		);
		$available       = array_map(
			function ( $term ) {
				return $term->name;
			},
			$available_terms
		);

		$selected_terms = $this->auto_select_via_ai( $post_title, $post_content_md, $taxonomy, $available, $bulk_process );

		// If the AI returns valid terms, assign them to the post.
		if ( is_array( $selected_terms ) && ! empty( $selected_terms ) ) {
			$term_ids = [];
			foreach ( $selected_terms as $term_name ) {
				$term = get_term_by( 'name', $term_name, $taxonomy );
				if ( $term ) {
					$term_ids[] = $term->term_id;
				}
			}

			// Assign the terms to the post.
			$append = ! empty( $settings['tags_categories']['never_deselect'] );
			wp_set_post_terms( $post_id, $term_ids, $taxonomy, $append );
		}

		if ( $bulk_process ) {
			// Update progress counter.
			$bulk_data = get_option( 'superdraft_bulk_process_data' );
			if ( $bulk_data ) {
				++$bulk_data['processed'];
				update_option( 'superdraft_bulk_process_data', $bulk_data );

				// If all posts are processed, trigger completion.
				if ( $bulk_data['processed'] >= $bulk_data['total'] ) {
					/**
					 * Fires when a bulk process is completed.
					 */
					do_action( 'superdraft_bulk_process_completed' );
				}
			}
		}
	}

	/**
	 * Auto-select taxonomy terms using AI.
	 *
	 * @param string $title          Post title (plain text).
	 * @param string $content_md     Post content in Markdown.
	 * @param string $taxonomy       e.g. "category" or "post_tag".
	 * @param array  $available_slugs An array of available term slugs or names.
	 * @param bool   $bulk_process   Whether this is part of a bulk process.
	 *
	 * @return array Array of chosen term slugs (or names) on success, empty array otherwise.
	 */
	protected function auto_select_via_ai( $title, $content_md, $taxonomy, $available_slugs, $bulk_process = false ) {
		// Get your API settings.
		$settings = get_option( 'superdraft_settings', [] );
		$model    = ! empty( $settings['tags_categories']['model'] ) ? $settings['tags_categories']['model'] : 'default-model';

		// Get the API instance.
		$api = Admin::get_api( $model );

		// Get prompt template for taxonomy auto-select.
		$prompt_template = $api->get_prompt_template( 'assign-terms' );
		if ( empty( $prompt_template ) ) {
			return [];
		}

		// Build the prompt. Note that we are passing the Markdown version of content here.
		$prompt = $api->replace_vars(
			$prompt_template,
			[
				'postTitle'      => $title,
				'postContent'    => $content_md,
				'taxonomy'       => $taxonomy,
				'availableTerms' => wp_json_encode( $available_slugs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
			]
		);

		try {
			$response = $api->send_prompt( $prompt );

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			Admin::log_api_request(
				$api,
				[
					'prompt'  => $prompt,
					'tool'    => 'taxonomy-auto-select',
					'user_id' => $bulk_process ? 0 : get_current_user_id(),
				]
			);

			// Remove ```json wrappers if present.
			$response     = preg_replace( '/^```(json)?\n(.*)\n```$/s', '$2', $response );
			$decoded      = json_decode( $response, true );
			$is_valid_arr = ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) );

			return $is_valid_arr ? $decoded : [];

		} catch ( \Exception $e ) {
			// Log error if needed.
			return [];
		}
	}

	/**
	 * Check if the user has permission to auto-select taxonomy terms.
	 *
	 * @return bool Whether the user can edit posts.
	 */
	public function check_permission() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Handle the auto-select request (REST).
	 * This receives Markdown content, so no need to convert HTML to Markdown.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_autoselect( \WP_REST_Request $request ) {
		$post_title   = $request->get_param( 'postTitle' );
		$post_content = $request->get_param( 'postContent' );
		$taxonomy     = $request->get_param( 'taxonomy' );
		$available    = $request->get_param( 'availableTerms' );

		// Use the existing method to avoid code duplication.
		$selected_terms = $this->auto_select_via_ai( $post_title, $post_content, $taxonomy, $available );

		// Handle errors if auto_select_via_ai returns WP_Error.
		if ( is_wp_error( $selected_terms ) ) {
			return $selected_terms;
		}

		// If we got an empty array and it's not a WP_Error, consider it an error.
		if ( empty( $selected_terms ) ) {
			return new \WP_Error(
				'invalid_response',
				__( 'Invalid response format from API or no terms selected', 'superdraft' )
			);
		}

		return rest_ensure_response( $selected_terms );
	}

	/**
	 * Display admin notice for bulk process status.
	 */
	public function display_bulk_process_notice() {
		if ( ! $this->check_permission() ) {
			return;
		}

		$bulk_data = get_option( 'superdraft_bulk_process_data' );

		if ( ! $bulk_data ) {
			return;
		}

		if ( ! is_array( $bulk_data ) || empty( $bulk_data['total'] ) ) {
			return;
		}

		$total      = $bulk_data['total'];
		$processed  = $bulk_data['processed'];
		$percentage = round( ( $processed / $total ) * 100 );
		$taxonomy   = isset( $bulk_data['taxonomy'] ) ? $bulk_data['taxonomy'] : 'category';

		// Calculate remaining time.
		$completion_time = isset( $bulk_data['last_scheduled'] ) ? $bulk_data['last_scheduled'] : 0;
		$time_remaining  = '';
		if ( $completion_time > time() ) {
			$time_remaining = human_time_diff( time(), $completion_time );
		}

		$cancel_url = wp_nonce_url(
			add_query_arg( 'superdraft_cancel_bulk', '1' ),
			'superdraft_cancel_bulk'
		);

		?>
		<div class="notice notice-info">
			<p>
				<strong>
					<?php
						echo esc_html(
							sprintf(
								// translators: %s is the taxonomy name.
								__( 'Superdraft is processing auto-select for posts (%s)', 'superdraft' ),
								( 'category' === $taxonomy ) ? __( 'Categories', 'superdraft' ) : __( 'Tags', 'superdraft' )
							)
						);
					?>
				</strong>
				<br>
				<?php
					echo esc_html(
						sprintf(
							// translators: %1$d is the number of posts processed, %2$d is the total number of posts, %3$d is the percentage.
							__( 'Progress: %1$d of %2$d posts processed (%3$d%%)', 'superdraft' ),
							$processed,
							$total,
							$percentage
						)
					);
				if ( $time_remaining ) {
					echo ' • ' . esc_html(
						sprintf(
							// translators: %s is the time remaining.
							__( 'Estimated completion in %s', 'superdraft' ),
							$time_remaining
						)
					);
				}
				?>
			</p>
			<p>
				<a href="<?php echo esc_url( $cancel_url ); ?>" class="button">
					<?php esc_html_e( 'Cancel Process', 'superdraft' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Handle cancellation of bulk process.
	 */
	public function handle_cancel_bulk_process() {
		if (
			! isset( $_GET['superdraft_cancel_bulk'] ) ||
			! check_admin_referer( 'superdraft_cancel_bulk' ) ||
			! $this->check_permission()
		) {
			return;
		}

		// Clear scheduled actions.
		as_unschedule_all_actions( 'superdraft_process_autoselect', [], 'superdraft' );

		// Clean up process data.
		delete_option( 'superdraft_bulk_process_data' );

		// Redirect back.
		wp_safe_redirect( remove_query_arg( 'superdraft_cancel_bulk' ) );
		exit;
	}

	/**
	 * Clean up process data when complete.
	 */
	public function cleanup_bulk_process_data() {
		delete_option( 'superdraft_bulk_process_data' );
	}

	/**
	 * Render the suggestion form.
	 *
	 * @param string $taxonomy The taxonomy name.
	 * @return void
	 */
	public function render_suggestion_form( $taxonomy ) {
		?>
		<div class="form-field term-suggest-wrap">
			<div class="term-suggest-form">
				<h2>
					<?php
					$taxonomy_obj = get_taxonomy( $taxonomy );
					/* translators: %s: Taxonomy name */
					printf( esc_html__( 'Suggest New %s', 'superdraft' ), esc_html( $taxonomy_obj->labels->name ) );
					?>
				</h2>
				<p><?php esc_html_e( 'AI-powered suggestions for new terms based on your content.', 'superdraft' ); ?></p>
				<button type="button" class="button button-secondary" id="superdraft-suggest-terms">
					<?php esc_html_e( 'Generate Suggestions', 'superdraft' ); ?>
				</button>
				<div class="spinner"></div>
			</div>
			<div id="superdraft-term-suggestions"></div>
		</div>
		<?php
	}

	/**
	 * Handle AJAX request for term suggestions
	 */
	public function handle_suggest_terms() {
		check_ajax_referer( 'superdraft-suggest-terms', 'nonce' );

		if ( ! current_user_can( 'manage_categories' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		if ( ! isset( $_POST['taxonomy'] ) ) {
			wp_send_json_error( 'Missing parameters' );
		}

		$taxonomy = sanitize_text_field( wp_unslash( $_POST['taxonomy'] ) );
		if ( ! taxonomy_exists( $taxonomy ) ) {
			wp_send_json_error( 'Invalid taxonomy' );
		}

		// Get generated terms from request.
		$generated_terms = isset( $_POST['generatedTerms'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['generatedTerms'] ) ) : [];

		// Get post type based on taxonomy.
		$post_type = 'post';
		if ( 'category' === $taxonomy ) {
			$post_type = 'post';
		} elseif ( 'post_tag' === $taxonomy ) {
			$post_type = 'post';
		} else {
			$taxonomy_obj = get_taxonomy( $taxonomy );
			if ( $taxonomy_obj ) {
				$post_type = $taxonomy_obj->object_type[0];
			}
		}

		if ( ! post_type_exists( $post_type ) ) {
			wp_send_json_error( 'Invalid post type' );
		}

		$settings = get_option( 'superdraft_settings', [] );

		// Get recent posts for context.
		$posts = get_posts(
			[
				'post_type'      => $post_type,
				'posts_per_page' => $settings['tags_categories']['suggestions_context'] ?? 20,
				'orderby'        => 'date',
				'order'          => 'DESC',
			]
		);

		$posts_context = [];
		foreach ( $posts as $post ) {
			$posts_context[] = [
				'title'   => $post->post_title,
				'excerpt' => wp_strip_all_tags( get_the_excerpt( $post ) ),
			];
		}

		// Get existing terms and combine with previously generated terms.
		$existing_terms = get_terms(
			[
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'fields'     => 'names',
			]
		);
		$existing_terms = array_merge( $existing_terms, $generated_terms );
		$existing_terms = array_unique( $existing_terms );

		// Get API instance and prompt.
		$settings = get_option( 'superdraft_settings', [] );
		$model    = ! empty( $settings['tags_categories']['model'] ) ? $settings['tags_categories']['model'] : 'default-model';
		$api      = Admin::get_api( $model );

		$prompt_template = $api->get_prompt_template( 'add-terms' );
		$prompt          = $api->replace_vars(
			$prompt_template,
			[
				'existingTerms'  => wp_json_encode( $existing_terms ),
				'taxonomy'       => $taxonomy,
				'recentPosts'    => wp_json_encode( $posts_context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ),
				'minSuggestions' => $settings['tags_categories']['min_suggestions'] ?? 3,
			]
		);

		try {
			$response = $api->send_prompt( $prompt );

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			Admin::log_api_request(
				$api,
				[
					'prompt' => $prompt,
					'tool'   => 'taxonomy-suggest',
				]
			);

			// Remove ```json wrappers if present.
			$response     = preg_replace( '/^```(json)?\n(.*)\n```$/s', '$2', $response );
			$decoded      = json_decode( $response, true );
			$is_valid_arr = ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) );
			$suggestions  = $is_valid_arr ? $decoded : [];

			if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $suggestions ) ) {
				wp_send_json_error( 'Invalid API response' );
			}

			wp_send_json_success( $suggestions );
		} catch ( \Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}
}
