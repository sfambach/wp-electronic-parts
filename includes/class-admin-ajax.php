<?php
/**
 * Admin AJAX API for the split-view catalog UI.
 *
 * @package WP_Electronic_Parts
 */

namespace WPEP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles get/save for categories and parts in the catalog admin page.
 */
final class Admin_Ajax {

	public const NONCE_ACTION = 'wpep_admin_ui';

	public static function register(): void {
		$actions = [
			'wpep_get_category'    => 'get_category',
			'wpep_save_category'   => 'save_category',
			'wpep_create_category' => 'create_category',
			'wpep_list_parts'      => 'list_parts',
			'wpep_get_part'        => 'get_part',
			'wpep_save_part'       => 'save_part',
			'wpep_resolve_schema'  => 'resolve_schema',
		];

		foreach ( $actions as $action => $method ) {
			add_action( 'wp_ajax_' . $action, [ self::class, $method ] );
		}
	}

	public static function get_category(): void {
		self::verify_request();
		$term_id = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
		$term    = get_term( $term_id, Taxonomy::SLUG );
		if ( ! $term instanceof \WP_Term ) {
			wp_send_json_error( [ 'message' => __( 'Category not found.', 'wp-electronic-parts' ) ], 404 );
		}

		wp_send_json_success( self::category_payload( $term ) );
	}

	public static function save_category(): void {
		self::verify_request();
		self::require_manage_terms();

		$term_id = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
		$term    = get_term( $term_id, Taxonomy::SLUG );
		if ( ! $term instanceof \WP_Term ) {
			wp_send_json_error( [ 'message' => __( 'Category not found.', 'wp-electronic-parts' ) ], 404 );
		}

		$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		if ( '' === $name ) {
			wp_send_json_error( [ 'message' => __( 'Name is required.', 'wp-electronic-parts' ) ], 400 );
		}

		$slug        = isset( $_POST['slug'] ) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : '';
		$parent      = isset( $_POST['parent'] ) ? absint( $_POST['parent'] ) : 0;
		$description = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';

		if ( $parent === $term_id ) {
			$parent = 0;
		}

		$result = wp_update_term(
			$term_id,
			Taxonomy::SLUG,
			[
				'name'        => $name,
				'slug'        => $slug,
				'parent'      => $parent,
				'description' => $description,
			]
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ], 400 );
		}

		$properties_raw = self::json_post( 'properties' );
		$definitions    = Category_Properties::sanitize_definitions( is_array( $properties_raw ) ? $properties_raw : [], $term_id );
		update_term_meta( $term_id, Category_Properties::META_KEY, $definitions );

		$term = get_term( $term_id, Taxonomy::SLUG );
		if ( ! $term instanceof \WP_Term ) {
			wp_send_json_error( [ 'message' => __( 'Category not found.', 'wp-electronic-parts' ) ], 404 );
		}

		wp_send_json_success( self::category_payload( $term ) );
	}

	public static function create_category(): void {
		self::verify_request();
		self::require_manage_terms();

		$name   = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$parent = isset( $_POST['parent'] ) ? absint( $_POST['parent'] ) : 0;

		if ( '' === $name ) {
			$name = __( 'New category', 'wp-electronic-parts' );
		}

		$result = wp_insert_term(
			$name,
			Taxonomy::SLUG,
			[ 'parent' => $parent ]
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ], 400 );
		}

		$term = get_term( (int) $result['term_id'], Taxonomy::SLUG );
		if ( ! $term instanceof \WP_Term ) {
			wp_send_json_error( [ 'message' => __( 'Category not found.', 'wp-electronic-parts' ) ], 404 );
		}

		wp_send_json_success( self::category_payload( $term ) );
	}

	public static function list_parts(): void {
		self::verify_request();

		$category_id = isset( $_POST['category_id'] ) ? absint( $_POST['category_id'] ) : 0;
		if ( $category_id <= 0 ) {
			wp_send_json_error( [ 'message' => __( 'Invalid category.', 'wp-electronic-parts' ) ], 400 );
		}

		$query = new \WP_Query(
			[
				'post_type'      => Post_Type::SLUG,
				'post_status'    => [ 'publish', 'draft', 'pending', 'private' ],
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'tax_query'      => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					[
						'taxonomy'         => Taxonomy::SLUG,
						'field'            => 'term_id',
						'terms'            => [ $category_id ],
						'include_children' => false,
					],
				],
			]
		);

		$parts = [];
		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			$name = (string) get_post_meta( $post->ID, Part_Name::META_KEY, true );
			$parts[] = [
				'id'    => (int) $post->ID,
				'name'  => '' !== $name ? $name : $post->post_title,
				'title' => $post->post_title,
			];
		}

		wp_send_json_success(
			[
				'categoryId' => $category_id,
				'parts'      => $parts,
			]
		);
	}

	public static function get_part(): void {
		self::verify_request();

		$part_id = isset( $_POST['part_id'] ) ? absint( $_POST['part_id'] ) : 0;
		$post    = get_post( $part_id );
		if ( ! $post instanceof \WP_Post || Post_Type::SLUG !== $post->post_type ) {
			wp_send_json_error( [ 'message' => __( 'Part not found.', 'wp-electronic-parts' ) ], 404 );
		}

		wp_send_json_success( self::part_payload( $post ) );
	}

	public static function save_part(): void {
		self::verify_request();

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'wp-electronic-parts' ) ], 403 );
		}

		$part_id      = isset( $_POST['part_id'] ) ? absint( $_POST['part_id'] ) : 0;
		$name         = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$category_ids = self::json_post( 'categoryIds' );
		$category_ids = is_array( $category_ids ) ? array_values( array_filter( array_map( 'absint', $category_ids ) ) ) : [];
		$values_raw   = self::json_post( 'values' );
		$values_raw   = is_array( $values_raw ) ? $values_raw : [];

		if ( '' === $name ) {
			wp_send_json_error( [ 'message' => __( 'Part name is required.', 'wp-electronic-parts' ) ], 400 );
		}

		$title = Part_Name::title_from_name( $name );
		if ( '' === $title ) {
			$title = $name;
		}

		if ( $part_id > 0 ) {
			$post = get_post( $part_id );
			if ( ! $post instanceof \WP_Post || Post_Type::SLUG !== $post->post_type ) {
				wp_send_json_error( [ 'message' => __( 'Part not found.', 'wp-electronic-parts' ) ], 404 );
			}
			if ( ! current_user_can( 'edit_post', $part_id ) ) {
				wp_send_json_error( [ 'message' => __( 'Permission denied.', 'wp-electronic-parts' ) ], 403 );
			}
			wp_update_post(
				[
					'ID'         => $part_id,
					'post_title' => $title,
				]
			);
		} else {
			$part_id = wp_insert_post(
				[
					'post_type'   => Post_Type::SLUG,
					'post_status' => 'publish',
					'post_title'  => $title,
				],
				true
			);
			if ( is_wp_error( $part_id ) ) {
				wp_send_json_error( [ 'message' => $part_id->get_error_message() ], 400 );
			}
			$part_id = (int) $part_id;
		}

		update_post_meta( $part_id, Part_Name::META_KEY, $name );
		wp_set_object_terms( $part_id, $category_ids, Taxonomy::SLUG, false );

		$schema = Part_Properties::resolve_schema_for_post( $part_id );
		$stored = [];
		$errors = [];

		foreach ( $schema as $definition ) {
			$key  = (string) ( $definition['key'] ?? '' );
			$type = (string) ( $definition['type'] ?? '' );
			if ( '' === $key ) {
				continue;
			}
			$input     = $values_raw[ $key ] ?? null;
			$sanitized = Property_Types::sanitize( $type, $input, $definition );
			$valid     = Property_Types::validate( $type, $sanitized, $definition, ! empty( $definition['required'] ) );
			if ( is_wp_error( $valid ) ) {
				$errors[] = $valid->get_error_message();
				continue;
			}
			if ( null !== $sanitized && ! ( is_array( $sanitized ) && Property_Types::TYPE_MEASURE !== $type && [] === $sanitized ) ) {
				$stored[ $key ] = $sanitized;
			}
		}

		update_post_meta( $part_id, Part_Properties::META_KEY, $stored );

		$post = get_post( $part_id );
		if ( ! $post instanceof \WP_Post ) {
			wp_send_json_error( [ 'message' => __( 'Part not found.', 'wp-electronic-parts' ) ], 404 );
		}

		$payload           = self::part_payload( $post );
		$payload['errors'] = $errors;

		wp_send_json_success( $payload );
	}

	public static function resolve_schema(): void {
		self::verify_request();
		$category_ids = self::json_post( 'categoryIds' );
		$category_ids = is_array( $category_ids ) ? array_values( array_filter( array_map( 'absint', $category_ids ) ) ) : [];
		$schema       = Part_Properties::resolve_schema_for_term_ids( $category_ids );
		$enriched     = [];
		foreach ( $schema as $definition ) {
			$definition['resolvedOptions'] = Property_Types::resolve_options( $definition );
			$enriched[]                    = $definition;
		}
		wp_send_json_success(
			[
				'schema' => $enriched,
				'terms'  => Category_Properties::get_term_choices(),
			]
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function category_payload( \WP_Term $term ): array {
		$properties = Category_Properties::get_definitions( (int) $term->term_id );
		foreach ( $properties as &$property ) {
			$property['resolvedOptions'] = Property_Types::resolve_options( $property );
		}
		unset( $property );

		return [
			'term'       => [
				'id'          => (int) $term->term_id,
				'name'        => $term->name,
				'slug'        => $term->slug,
				'parent'      => (int) $term->parent,
				'description' => $term->description,
				'count'       => (int) $term->count,
			],
			'properties' => $properties,
			'parents'    => Category_Properties::get_term_choices(),
			'typeLabels' => Property_Types::type_labels(),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function part_payload( \WP_Post $post ): array {
		$name = (string) get_post_meta( $post->ID, Part_Name::META_KEY, true );
		$vals = get_post_meta( $post->ID, Part_Properties::META_KEY, true );
		$vals = is_array( $vals ) ? $vals : [];
		$ids  = wp_get_post_terms( $post->ID, Taxonomy::SLUG, [ 'fields' => 'ids' ] );
		$ids  = is_array( $ids ) ? array_map( 'intval', $ids ) : [];

		$schema = Part_Properties::resolve_schema_for_post( (int) $post->ID );
		foreach ( $schema as &$definition ) {
			$definition['resolvedOptions'] = Property_Types::resolve_options( $definition );
		}
		unset( $definition );

		return [
			'part'   => [
				'id'          => (int) $post->ID,
				'name'        => $name,
				'title'       => $post->post_title,
				'categoryIds' => $ids,
			],
			'values' => $vals,
			'schema' => $schema,
			'terms'  => Category_Properties::get_term_choices(),
		];
	}

	private static function verify_request(): void {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid security token.', 'wp-electronic-parts' ) ], 403 );
		}
	}

	private static function require_manage_terms(): void {
		$taxonomy = get_taxonomy( Taxonomy::SLUG );
		$cap      = $taxonomy instanceof \WP_Taxonomy ? $taxonomy->cap->manage_terms : 'manage_categories';
		if ( ! current_user_can( $cap ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'wp-electronic-parts' ) ], 403 );
		}
	}

	/**
	 * @return mixed
	 */
	private static function json_post( string $key ): mixed {
		if ( ! isset( $_POST[ $key ] ) ) {
			return null;
		}
		$raw = wp_unslash( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( is_array( $raw ) ) {
			return $raw;
		}
		if ( ! is_string( $raw ) || '' === $raw ) {
			return null;
		}
		$decoded = json_decode( $raw, true );
		return null === $decoded ? null : $decoded;
	}
}
