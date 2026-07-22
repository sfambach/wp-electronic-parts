<?php
/**
 * Electronic part post type.
 *
 * @package WP_Electronic_Parts
 */

namespace WPEP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the electronic_part custom post type.
 */
final class Post_Type {

	public const SLUG = 'electronic_part';

	public static function register(): void {
		register_post_type(
			self::SLUG,
			[
				'labels'              => self::labels(),
				'public'              => true,
				'show_in_rest'        => true,
				'has_archive'         => true,
				'rewrite'             => [ 'slug' => 'parts' ],
				'menu_icon'           => 'dashicons-admin-generic',
				'supports'            => [ 'title', 'editor', 'thumbnail', 'excerpt', 'revisions' ],
				'taxonomies'          => [ Taxonomy::SLUG ],
				'show_in_nav_menus'   => true,
				'exclude_from_search' => false,
			]
		);
	}

	/**
	 * @return array<string, string>
	 */
	private static function labels(): array {
		return [
			'name'                  => __( 'Electronic Parts', 'wp-electronic-parts' ),
			'singular_name'         => __( 'Electronic Part', 'wp-electronic-parts' ),
			'menu_name'             => __( 'Electronic Parts', 'wp-electronic-parts' ),
			'name_admin_bar'        => __( 'Electronic Part', 'wp-electronic-parts' ),
			'add_new'               => __( 'Add New', 'wp-electronic-parts' ),
			'add_new_item'          => __( 'Add New Part', 'wp-electronic-parts' ),
			'new_item'              => __( 'New Part', 'wp-electronic-parts' ),
			'edit_item'             => __( 'Edit Part', 'wp-electronic-parts' ),
			'view_item'             => __( 'View Part', 'wp-electronic-parts' ),
			'all_items'             => __( 'All Parts', 'wp-electronic-parts' ),
			'search_items'          => __( 'Search Parts', 'wp-electronic-parts' ),
			'not_found'             => __( 'No parts found.', 'wp-electronic-parts' ),
			'not_found_in_trash'    => __( 'No parts found in Trash.', 'wp-electronic-parts' ),
			'archives'              => __( 'Part Archives', 'wp-electronic-parts' ),
			'insert_into_item'      => __( 'Insert into part', 'wp-electronic-parts' ),
			'uploaded_to_this_item' => __( 'Uploaded to this part', 'wp-electronic-parts' ),
			'filter_items_list'     => __( 'Filter parts list', 'wp-electronic-parts' ),
			'items_list_navigation' => __( 'Parts list navigation', 'wp-electronic-parts' ),
			'items_list'            => __( 'Parts list', 'wp-electronic-parts' ),
		];
	}
}
