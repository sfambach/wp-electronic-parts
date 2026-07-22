<?php
/**
 * Hierarchical part category taxonomy.
 *
 * @package WP_Electronic_Parts
 */

namespace WPEP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the hierarchical part_category taxonomy.
 *
 * Term meta and custom node properties can be added here later.
 */
final class Taxonomy {

	public const SLUG = 'part_category';

	public static function register(): void {
		register_taxonomy(
			self::SLUG,
			[ Post_Type::SLUG ],
			[
				'labels'            => self::labels(),
				'hierarchical'      => true,
				'public'            => true,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rewrite'           => [ 'slug' => 'part-category' ],
				'show_tagcloud'     => false,
			]
		);
	}

	/**
	 * @return array<string, string>
	 */
	private static function labels(): array {
		return [
			'name'                       => __( 'Part Categories', 'wp-electronic-parts' ),
			'singular_name'              => __( 'Part Category', 'wp-electronic-parts' ),
			'menu_name'                  => __( 'Categories', 'wp-electronic-parts' ),
			'all_items'                  => __( 'All Categories', 'wp-electronic-parts' ),
			'parent_item'                => __( 'Parent Category', 'wp-electronic-parts' ),
			'parent_item_colon'          => __( 'Parent Category:', 'wp-electronic-parts' ),
			'new_item_name'              => __( 'New Category Name', 'wp-electronic-parts' ),
			'add_new_item'               => __( 'Add New Category', 'wp-electronic-parts' ),
			'edit_item'                  => __( 'Edit Category', 'wp-electronic-parts' ),
			'update_item'                => __( 'Update Category', 'wp-electronic-parts' ),
			'view_item'                  => __( 'View Category', 'wp-electronic-parts' ),
			'search_items'               => __( 'Search Categories', 'wp-electronic-parts' ),
			'popular_items'              => __( 'Popular Categories', 'wp-electronic-parts' ),
			'separate_items_with_commas' => __( 'Separate categories with commas', 'wp-electronic-parts' ),
			'add_or_remove_items'        => __( 'Add or remove categories', 'wp-electronic-parts' ),
			'choose_from_most_used'      => __( 'Choose from the most used categories', 'wp-electronic-parts' ),
			'not_found'                  => __( 'No categories found.', 'wp-electronic-parts' ),
			'no_terms'                   => __( 'No categories', 'wp-electronic-parts' ),
			'items_list_navigation'      => __( 'Category list navigation', 'wp-electronic-parts' ),
			'items_list'                 => __( 'Category list', 'wp-electronic-parts' ),
			'back_to_items'              => __( '&larr; Back to Categories', 'wp-electronic-parts' ),
		];
	}
}
