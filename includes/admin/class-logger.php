<?php
/**
 * Logger class for Superdraft.
 *
 * @package Superdraft
 * @since 1.0.0
 */

namespace Superdraft;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Logger class.
 */
class Logger {

	/**
	 * The name of our custom DB table (without the WP prefix).
	 *
	 * @var string
	 */
	private static $table_name = 'superdraft_api_logs'; // Adjust as needed.

	/**
	 * Create the custom table upon plugin activation or upgrade.
	 */
	public static function activate() {
		global $wpdb;

		$table_name      = $wpdb->prefix . self::$table_name;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE `{$table_name}` (
			`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			`tool` VARCHAR(200) NOT NULL,
			`input_tokens` BIGINT UNSIGNED NOT NULL DEFAULT 0,
			`output_tokens` BIGINT UNSIGNED NOT NULL DEFAULT 0,
			`model` VARCHAR(200) NOT NULL,
			`timestamp` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			`response_time` BIGINT UNSIGNED NOT NULL DEFAULT 0,
			`user_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
			`message` LONGTEXT NOT NULL,
			PRIMARY KEY (`id`)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Insert a log entry into the table.
	 *
	 * @param array $data {
	 *     @type string $tool          The module/tool name (e.g., 'autocomplete', 'tags_categories', etc.)
	 *     @type int    $input_tokens  Number of input tokens used.
	 *     @type int    $output_tokens Number of output tokens returned.
	 *     @type string $model         Which model was used.
	 *     @type int    $user_id       Which user triggered this usage.
	 *     @type string $message       Any message or extra data (e.g. the prompt).
	 * }
	 * @return int|false The inserted row ID on success, or false on failure.
	 */
	public function insert_log( $data ) {
		global $wpdb;

		$table_name = $wpdb->prefix . self::$table_name;

		$defaults = [
			'tool'          => '',
			'input_tokens'  => 0,
			'output_tokens' => 0,
			'model'         => '',
			'timestamp'     => current_time( 'mysql' ),
			'response_time' => 0,
			'user_id'       => get_current_user_id(),
			'message'       => '',
		];
		$data     = wp_parse_args( $data, $defaults );

		$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- The direct query is needed here.
			$table_name,
			[
				'tool'          => sanitize_text_field( $data['tool'] ),
				'input_tokens'  => intval( $data['input_tokens'] ),
				'output_tokens' => intval( $data['output_tokens'] ),
				'model'         => sanitize_text_field( $data['model'] ),
				'timestamp'     => $data['timestamp'],
				'response_time' => intval( $data['response_time'] ),
				'user_id'       => intval( $data['user_id'] ),
				'message'       => $data['message'],
			],
			[ '%s', '%d', '%d', '%s', '%s', '%d', '%s' ]
		);

		if ( false === $inserted ) {
			return false;
		}

		return $wpdb->insert_id;
	}

	/**
	 * Retrieve log entries from the database for listing.
	 *
	 * @param array $args {
	 *     @type int    $per_page  Number of items per page.
	 *     @type int    $paged     Current page number.
	 *     @type string $search    Optional search term.
	 *     @type string $orderby   Column to sort by (id, tool, model, timestamp, etc.).
	 *     @type string $order     Sort order (ASC or DESC).
	 *     @type string $tool      Optional tool filter.
	 * }
	 * @return array {
	 *     @type array $items      The queried log entries.
	 *     @type int   $total_items The total number of log entries (for pagination).
	 * }
	 */
	public function get_logs( $args = [] ) {
		global $wpdb;

		$table_name = $wpdb->prefix . self::$table_name;

		$defaults = [
			'per_page' => 20,
			'paged'    => 1,
			'search'   => '',
			'orderby'  => 'id',
			'order'    => 'DESC',
			'tool'     => '', // Add tool parameter.
		];
		$args     = wp_parse_args( $args, $defaults );

		$offset  = ( $args['paged'] - 1 ) * $args['per_page'];
		$search  = $args['search'];
		$orderby = in_array( $args['orderby'], [ 'id', 'tool', 'model', 'timestamp', 'user_id' ], true ) ? $args['orderby'] : 'id';
		$order   = ( 'ASC' === strtoupper( $args['order'] ) ) ? 'ASC' : 'DESC';

		$where = 'WHERE 1=1';
		if ( ! empty( $search ) ) {
			// Simple search by tool, model, or message.
			$search_like = '%' . $wpdb->esc_like( $search ) . '%';
			$where      .= $wpdb->prepare( ' AND (`tool` LIKE %s OR `model` LIKE %s OR `message` LIKE %s)', $search_like, $search_like, $search_like );
		}

		// Add tool filter.
		if ( ! empty( $args['tool'] ) ) {
			$where .= $wpdb->prepare( ' AND tool = %s', $args['tool'] );
		}

		// Query items.
		// phpcs:disable -- We prepared those interpolated values above, and we do need the direct database calls here.
		$sql   = $wpdb->prepare(
			"SELECT * FROM `{$table_name}`
			{$where}
			ORDER BY `{$orderby}` {$order}
			LIMIT %d OFFSET %d",
			$args['per_page'],
			$offset
		);
		$items = $wpdb->get_results( $sql, ARRAY_A );

		// Query total count.
		$sql_count   = "SELECT COUNT(*) FROM `{$table_name}` {$where}";
		$total_items = $wpdb->get_var( $sql_count );
		// phpcs:enable

		return [
			'items'       => $items,
			'total_items' => $total_items,
		];
	}
}
