<?php
/**
 * Plugin Name: WP Electronic Parts
 * Description: Hierarchical taxonomy and post type for electronic parts. Playground for part trees and future term properties.
 * Version: 0.3.0
 * Author: Stefan Fambach
 * Text Domain: wp-electronic-parts
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP: 8.0
 *
 * @package WP_Electronic_Parts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPEP_VERSION', '0.3.0' );
define( 'WPEP_PLUGIN_FILE', __FILE__ );
define( 'WPEP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPEP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once WPEP_PLUGIN_DIR . 'includes/class-post-type.php';
require_once WPEP_PLUGIN_DIR . 'includes/class-taxonomy.php';
require_once WPEP_PLUGIN_DIR . 'includes/class-part-name.php';
require_once WPEP_PLUGIN_DIR . 'includes/class-category-tree.php';
require_once WPEP_PLUGIN_DIR . 'includes/class-property-types.php';
require_once WPEP_PLUGIN_DIR . 'includes/class-category-properties.php';
require_once WPEP_PLUGIN_DIR . 'includes/class-part-properties.php';
require_once WPEP_PLUGIN_DIR . 'includes/class-admin-ajax.php';
require_once WPEP_PLUGIN_DIR . 'includes/class-plugin.php';

WPEP\Plugin::instance();
