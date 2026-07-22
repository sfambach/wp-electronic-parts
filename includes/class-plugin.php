<?php
/**
 * Main plugin bootstrap.
 *
 * @package WP_Electronic_Parts
 */

namespace WPEP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers post type, taxonomy, and shared hooks.
 */
final class Plugin {

	private static ?Plugin $instance = null;

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );
		add_action( 'init', [ Post_Type::class, 'register' ] );
		add_action( 'init', [ Taxonomy::class, 'register' ] );
	}

	public function load_textdomain(): void {
		load_plugin_textdomain(
			'wp-electronic-parts',
			false,
			dirname( plugin_basename( WPEP_PLUGIN_FILE ) ) . '/languages'
		);
	}
}
