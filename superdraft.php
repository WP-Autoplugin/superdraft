<?php
/**
 * Plugin Name: Superdraft
 * Description: A powerful AI-driven toolset that enhances your WordPress experience with smart automation, intelligent recommendations, and predictive features.
 * Version: 1.0.1
 * Author: Balázs Piller
 * Author URI: https://wp-autoplugin.com
 * Text Domain: superdraft
 * Domain Path: /languages
 * License: GPL-3.0
 *
 * @package Superdraft
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define constants.
define( 'SUPERDRAFT_VERSION', '1.0.1' );
define( 'SUPERDRAFT_DIR', plugin_dir_path( __FILE__ ) );
define( 'SUPERDRAFT_URL', plugin_dir_url( __FILE__ ) );

// Include the autoloader.
require_once SUPERDRAFT_DIR . 'vendor/autoload.php';

// Load the Action Scheduler.
require_once SUPERDRAFT_DIR . 'vendor/woocommerce/action-scheduler/action-scheduler.php';

// Create the logs table.
register_activation_hook( __FILE__, [ 'Superdraft\Logger', 'activate' ] );

// Set default options.
register_activation_hook( __FILE__, [ 'Superdraft\Admin', 'set_default_options' ] );

/**
 * Initialize the plugin.
 */
function superdraft_init() {
	$admin = new Superdraft\Admin();
}
add_action( 'plugins_loaded', 'superdraft_init' );
