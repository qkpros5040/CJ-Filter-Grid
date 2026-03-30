<?php
/**
 * Plugin Name: Dynamic Post Grid Pro (DPG)
 * Description: Dynamic grid with search, taxonomy filters, pagination, and optional WPGraphQL integration.
 * Version: 2026.03.27.155040
 * Author: CJ
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: dynamic-post-grid-pro
 * Domain Path: /languages
 *
 * @package DynamicPostGridPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DPG_VERSION', '2026.03.27.155040' );
define( 'DPG_FILE', __FILE__ );
define( 'DPG_PATH', plugin_dir_path( __FILE__ ) );
define( 'DPG_URL', plugin_dir_url( __FILE__ ) );

require_once DPG_PATH . 'includes/class-dpg-plugin.php';
require_once DPG_PATH . 'includes/class-dpg-admin.php';
require_once DPG_PATH . 'includes/class-dpg-graphql.php';
require_once DPG_PATH . 'includes/class-dpg-query-builder.php';

register_activation_hook( __FILE__, array( 'DPG_Plugin', 'activate' ) );

add_action(
	'plugins_loaded',
	static function () {
		DPG_Plugin::instance()->init();
	}
);
