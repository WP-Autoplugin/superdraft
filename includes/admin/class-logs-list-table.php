<?php
/**
 * WP_List_Table to display Superdraft API logs.
 *
 * @package Superdraft
 * @since 1.0.0
 */

namespace Superdraft;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Make sure the WP_List_Table is loaded (this is included in WP Admin).
if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class Logs_List_Table
 */
class Logs_List_Table extends \WP_List_Table {

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct(
			[
				'singular' => 'API Log',
				'plural'   => 'API Logs',
				'ajax'     => false,
			]
		);
	}

	/**
	 * Define the columns that are going to be used in the table
	 *
	 * @return array
	 */
	public function get_columns() {
		$columns = [
			'tool'          => __( 'Tool / Module', 'superdraft' ),
			'input_tokens'  => __( 'Input Tokens', 'superdraft' ),
			'output_tokens' => __( 'Output Tokens', 'superdraft' ),
			'model'         => __( 'Model', 'superdraft' ),
			'timestamp'     => __( 'Timestamp', 'superdraft' ),
			'user_id'       => __( 'User', 'superdraft' ),
		];

		if ( ( defined( 'SUPERDRAFT_LOG_PROMPTS' ) && SUPERDRAFT_LOG_PROMPTS ) ||
			( defined( 'SUPERDRAFT_LOG_RESPONSES' ) && SUPERDRAFT_LOG_RESPONSES ) ||
			( defined( 'SUPERDRAFT_LOG_REQUESTS' ) && SUPERDRAFT_LOG_REQUESTS ) ) {
			$columns['message'] = __( 'Message', 'superdraft' );
		}

		return $columns;
	}

	/**
	 * Define sortable columns
	 */
	protected function get_sortable_columns() {
		return [
			'id'        => [ 'id', false ],
			'tool'      => [ 'tool', false ],
			'model'     => [ 'model', false ],
			'timestamp' => [ 'timestamp', false ],
			'user_id'   => [ 'user_id', false ],
		];
	}

	/**
	 * Default column rendering.
	 *
	 * @param array  $item        The current item.
	 * @param string $column_name The column name.
	 * @return string
	 */
	protected function column_default( $item, $column_name ) {
		return isset( $item[ $column_name ] ) ? nl2br( esc_html( $item[ $column_name ] ) ) : '';
	}

	/**
	 * Render the checkbox column.
	 *
	 * @param array $item The current item.
	 * @return string
	 */
	protected function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="bulk-delete[]" value="%s" />',
			$item['id']
		);
	}

	/**
	 * Render the message column.
	 *
	 * @param array $item The current item.
	 * @return string
	 */
	public function column_message( $item ) {
		if ( empty( $item['message'] ) ) {
			return __( 'N/A', 'superdraft' );
		}
		return sprintf(
			'<a href="#" class="superdraft-view-message" data-message="%s">%s</a>',
			esc_attr( $item['message'] ),
			esc_html__( 'View Message', 'superdraft' )
		);
	}

	/**
	 * Display the filter dropdown.
	 *
	 * @param string $which The location of the extra table nav markup: 'top' or 'bottom'.
	 */
	public function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification -- Nonce verification is not needed here, as this is a read-only action.
		$current_tool = '';
		if ( isset( $_GET['tool_filter'] ) ) {
			$current_tool = sanitize_text_field( wp_unslash( $_GET['tool_filter'] ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification

		$tools = [
			'autocomplete',
			'taxonomy-auto-select',
			'taxonomy-auto-select-bulk',
			'taxonomy-suggest',
			'writing-tips',
		];
		?>
		<div class="alignleft actions">
			<select name="tool_filter">
				<option value=""><?php esc_html_e( 'All tools', 'superdraft' ); ?></option>
				<?php foreach ( $tools as $tool ) : ?>
					<option value="<?php echo esc_attr( $tool ); ?>" <?php selected( $current_tool, $tool ); ?>>
						<?php echo esc_html( $tool ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<?php submit_button( __( 'Filter', 'superdraft' ), '', 'filter_action', false ); ?>
		</div>
		<?php
	}

	/**
	 * Prepare the table items
	 */
	public function prepare_items() {

		// Handle pagination, search, ordering, etc.
		$per_page     = 20;
		$current_page = $this->get_pagenum();

		// phpcs:disable WordPress.Security.NonceVerification -- Nonce verification is not needed here, as this is a read-only action.
		$search      = ( ! empty( $_REQUEST['s'] ) ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
		$orderby     = ( ! empty( $_REQUEST['orderby'] ) ) ? sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ) ) : 'id';
		$order       = ( ! empty( $_REQUEST['order'] ) ) ? sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) : 'DESC';
		$tool_filter = ( ! empty( $_REQUEST['tool_filter'] ) ) ? sanitize_text_field( wp_unslash( $_REQUEST['tool_filter'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification

		$this->_column_headers = [
			$this->get_columns(),
			[], // hidden.
			$this->get_sortable_columns(),
		];

		$logger = new Logger();
		$result = $logger->get_logs(
			[
				'per_page' => $per_page,
				'paged'    => $current_page,
				'search'   => $search,
				'orderby'  => $orderby,
				'order'    => $order,
				'tool'     => $tool_filter, // Add tool filter to query.
			]
		);

		$this->items = $result['items'];

		$total_items = $result['total_items'];

		// Set pagination.
		$this->set_pagination_args(
			[
				'total_items' => $total_items,
				'per_page'    => $per_page,
			]
		);
	}
}
