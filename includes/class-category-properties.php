<?php
/**
 * Category property definitions (term meta + edit UI).
 *
 * @package WP_Electronic_Parts
 */

namespace WPEP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manage typed property schemas on part categories.
 */
final class Category_Properties {

	public const META_KEY = 'wpep_properties';

	public static function register(): void {
		add_action( 'init', [ self::class, 'register_term_meta' ] );
		// Full-width panel below the main term fields (clear property list).
		add_action( Taxonomy::SLUG . '_edit_form', [ self::class, 'render_properties_panel' ], 5, 1 );
		add_action( 'edited_' . Taxonomy::SLUG, [ self::class, 'save_term' ], 10, 1 );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_assets' ] );
		add_action( 'admin_notices', [ self::class, 'maybe_render_saved_notice' ] );
	}

	public static function register_term_meta(): void {
		register_term_meta(
			Taxonomy::SLUG,
			self::META_KEY,
			[
				'type'          => 'array',
				'single'        => true,
				'default'       => [],
				'show_in_rest'  => false,
				'auth_callback' => static function (): bool {
					$taxonomy = get_taxonomy( Taxonomy::SLUG );
					$cap      = $taxonomy instanceof \WP_Taxonomy ? $taxonomy->cap->edit_terms : 'manage_categories';
					return current_user_can( $cap );
				},
			]
		);
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public static function get_definitions( int $term_id ): array {
		$raw = get_term_meta( $term_id, self::META_KEY, true );
		if ( ! is_array( $raw ) ) {
			return [];
		}

		$defs = [];
		foreach ( $raw as $def ) {
			if ( ! is_array( $def ) ) {
				continue;
			}
			$def['source_term_id'] = (int) ( $def['source_term_id'] ?? $term_id );
			$defs[]                = $def;
		}

		return $defs;
	}

	/**
	 * Prominent properties list on the category edit screen.
	 *
	 * @param \WP_Term $term Term being edited.
	 */
	public static function render_properties_panel( \WP_Term $term ): void {
		$definitions = self::get_definitions( (int) $term->term_id );
		$types       = Property_Types::type_labels();
		$all_terms   = self::get_term_choices();

		wp_nonce_field( 'wpep_save_category_properties', 'wpep_category_properties_nonce' );
		?>
		<div class="wpep-category-properties-panel" id="wpep-category-properties">
			<h2><?php esc_html_e( 'Properties', 'wp-electronic-parts' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Add fields that parts in this category should fill in. Use “Inherit to children” if subcategories should get the same fields.', 'wp-electronic-parts' ); ?>
			</p>

			<div id="wpep-prop-list" class="wpep-prop-list">
				<?php
				if ( empty( $definitions ) ) {
					self::render_item( 0, self::empty_definition(), $types, $all_terms, false );
				} else {
					foreach ( $definitions as $index => $definition ) {
						self::render_item( (int) $index, $definition, $types, $all_terms, false );
					}
				}
				?>
			</div>

			<p>
				<button type="button" class="button button-secondary" id="wpep-prop-add-row">
					<?php esc_html_e( 'Add property', 'wp-electronic-parts' ); ?>
				</button>
			</p>

			<template id="wpep-prop-row-template">
				<?php self::render_item( '__INDEX__', self::empty_definition(), $types, $all_terms, true ); ?>
			</template>
		</div>
		<?php
	}

	/**
	 * @param array<string, mixed>  $definition Definition.
	 * @param array<string, string> $types      Type labels.
	 * @param array<int, string>    $all_terms  term_id => name path.
	 */
	private static function render_item( int|string $index, array $definition, array $types, array $all_terms, bool $is_template ): void {
		$label        = (string) ( $definition['label'] ?? '' );
		$key          = (string) ( $definition['key'] ?? '' );
		$type         = (string) ( $definition['type'] ?? Property_Types::TYPE_TEXT );
		$required     = ! empty( $definition['required'] );
		$inheritance  = (string) ( $definition['inheritance'] ?? 'none' );
		$options      = isset( $definition['options'] ) && is_array( $definition['options'] )
			? implode( "\n", array_map( 'strval', $definition['options'] ) )
			: '';
		$units_source = (int) ( $definition['units_source_term_id'] ?? 0 );
		$name_prefix  = $is_template && '__INDEX__' === $index
			? 'wpep_properties[__INDEX__]'
			: 'wpep_properties[' . (int) $index . ']';
		?>
		<div class="wpep-prop-item wpep-prop-row" data-type="<?php echo esc_attr( $type ); ?>">
			<div class="wpep-prop-item__header">
				<strong class="wpep-prop-item__title">
					<?php echo $label !== '' ? esc_html( $label ) : esc_html__( 'New property', 'wp-electronic-parts' ); ?>
				</strong>
				<button type="button" class="button-link-delete wpep-prop-remove">
					<?php esc_html_e( 'Remove', 'wp-electronic-parts' ); ?>
				</button>
			</div>
			<div class="wpep-prop-item__grid">
				<p>
					<label>
						<span><?php esc_html_e( 'Label', 'wp-electronic-parts' ); ?></span>
						<input type="text" class="widefat wpep-prop-label" name="<?php echo esc_attr( $name_prefix ); ?>[label]" value="<?php echo esc_attr( $label ); ?>" />
					</label>
				</p>
				<p>
					<label>
						<span><?php esc_html_e( 'Key', 'wp-electronic-parts' ); ?></span>
						<input type="text" class="widefat wpep-prop-key" name="<?php echo esc_attr( $name_prefix ); ?>[key]" value="<?php echo esc_attr( $key ); ?>" pattern="[a-z0-9_\-]*" placeholder="<?php esc_attr_e( 'auto from label', 'wp-electronic-parts' ); ?>" title="<?php esc_attr_e( 'Lowercase letters, numbers, underscore, hyphen', 'wp-electronic-parts' ); ?>" />
					</label>
				</p>
				<p>
					<label>
						<span><?php esc_html_e( 'Type', 'wp-electronic-parts' ); ?></span>
						<select class="widefat wpep-prop-type" name="<?php echo esc_attr( $name_prefix ); ?>[type]">
							<?php foreach ( $types as $type_key => $type_label ) : ?>
								<option value="<?php echo esc_attr( $type_key ); ?>" <?php selected( $type, $type_key ); ?>>
									<?php echo esc_html( $type_label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</label>
				</p>
				<p>
					<label>
						<span><?php esc_html_e( 'Inherit to children', 'wp-electronic-parts' ); ?></span>
						<select class="widefat" name="<?php echo esc_attr( $name_prefix ); ?>[inheritance]">
							<option value="none" <?php selected( $inheritance, 'none' ); ?>><?php esc_html_e( 'No', 'wp-electronic-parts' ); ?></option>
							<option value="children" <?php selected( $inheritance, 'children' ); ?>><?php esc_html_e( 'Yes', 'wp-electronic-parts' ); ?></option>
						</select>
					</label>
				</p>
				<p class="wpep-prop-item__required">
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $name_prefix ); ?>[required]" value="1" <?php checked( $required ); ?> />
						<?php esc_html_e( 'Required', 'wp-electronic-parts' ); ?>
					</label>
				</p>
			</div>
			<div class="wpep-prop-extra wpep-prop-extra--enum">
				<label>
					<span><?php esc_html_e( 'Options (one per line)', 'wp-electronic-parts' ); ?></span>
					<textarea class="widefat" name="<?php echo esc_attr( $name_prefix ); ?>[options]" rows="3" placeholder="<?php esc_attr_e( 'One option per line', 'wp-electronic-parts' ); ?>"><?php echo esc_textarea( $options ); ?></textarea>
				</label>
			</div>
			<div class="wpep-prop-extra wpep-prop-extra--measure">
				<label>
					<span><?php esc_html_e( 'Units from category', 'wp-electronic-parts' ); ?></span>
					<select class="widefat" name="<?php echo esc_attr( $name_prefix ); ?>[units_source_term_id]">
						<option value="0"><?php esc_html_e( '— Select unit category —', 'wp-electronic-parts' ); ?></option>
						<?php foreach ( $all_terms as $term_id => $term_label ) : ?>
							<option value="<?php echo esc_attr( (string) $term_id ); ?>" <?php selected( $units_source, $term_id ); ?>>
								<?php echo esc_html( $term_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</label>
				<p class="description"><?php esc_html_e( 'Descendants of this category become unit choices.', 'wp-electronic-parts' ); ?></p>
			</div>
			<div class="wpep-prop-extra wpep-prop-extra--term-children">
				<p class="description"><?php esc_html_e( 'Choices = all subcategories of this category.', 'wp-electronic-parts' ); ?></p>
			</div>
		</div>
		<?php
	}

	public static function save_term( int $term_id ): void {
		if ( ! isset( $_POST['wpep_category_properties_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpep_category_properties_nonce'] ) ), 'wpep_save_category_properties' )
		) {
			return;
		}

		$taxonomy = get_taxonomy( Taxonomy::SLUG );
		$cap      = $taxonomy instanceof \WP_Taxonomy ? $taxonomy->cap->edit_terms : 'manage_categories';
		if ( ! current_user_can( $cap ) ) {
			return;
		}

		$raw = isset( $_POST['wpep_properties'] ) && is_array( $_POST['wpep_properties'] )
			? wp_unslash( $_POST['wpep_properties'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			: [];

		$definitions = self::sanitize_definitions( is_array( $raw ) ? $raw : [], $term_id );
		update_term_meta( $term_id, self::META_KEY, $definitions );

		set_transient( 'wpep_category_props_saved_' . get_current_user_id(), $term_id, 30 );
	}

	/**
	 * @param array<int|string, mixed> $raw Raw rows.
	 * @return list<array<string, mixed>>
	 */
	public static function sanitize_definitions( array $raw, int $term_id ): array {
		$definitions = [];
		$used_keys   = [];

		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$label = sanitize_text_field( (string) ( $row['label'] ?? '' ) );
			$key   = sanitize_key( (string) ( $row['key'] ?? '' ) );
			$type  = sanitize_key( (string) ( $row['type'] ?? '' ) );

			if ( '' === $label && '' === $key ) {
				continue;
			}

			if ( '' === $key ) {
				$key = sanitize_key( sanitize_title( $label ) );
			}

			if ( '' === $key || ! Property_Types::is_valid_type( $type ) ) {
				continue;
			}

			if ( isset( $used_keys[ $key ] ) ) {
				continue;
			}
			$used_keys[ $key ] = true;

			$inheritance = sanitize_key( (string) ( $row['inheritance'] ?? 'none' ) );
			if ( ! in_array( $inheritance, [ 'none', 'children' ], true ) ) {
				$inheritance = 'none';
			}

			$definition = [
				'key'            => $key,
				'label'          => '' !== $label ? $label : $key,
				'type'           => $type,
				'required'       => ! empty( $row['required'] ),
				'inheritance'    => $inheritance,
				'source_term_id' => $term_id > 0 ? $term_id : (int) ( $row['source_term_id'] ?? 0 ),
			];

			if ( Property_Types::TYPE_ENUM === $type || Property_Types::TYPE_ENUM_MULTI === $type ) {
				$options_raw = $row['options'] ?? [];
				if ( is_string( $options_raw ) ) {
					$options_raw = preg_split( '/\r\n|\r|\n/', $options_raw ) ?: [];
				}
				$clean = [];
				foreach ( (array) $options_raw as $option ) {
					$option = sanitize_text_field( trim( (string) $option ) );
					if ( '' !== $option ) {
						$clean[] = $option;
					}
				}
				$definition['options'] = array_values( array_unique( $clean ) );
			}

			if ( Property_Types::TYPE_MEASURE === $type ) {
				$definition['units_source_term_id'] = absint( $row['units_source_term_id'] ?? 0 );
			}

			$definitions[] = $definition;
		}

		return $definitions;
	}

	public static function enqueue_assets( string $hook_suffix ): void {
		if ( 'term.php' !== $hook_suffix ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || Taxonomy::SLUG !== $screen->taxonomy ) {
			return;
		}

		wp_enqueue_style(
			'wpep-properties-admin',
			WPEP_PLUGIN_URL . 'assets/css/properties-admin.css',
			[],
			WPEP_VERSION
		);

		wp_enqueue_script(
			'wpep-category-properties',
			WPEP_PLUGIN_URL . 'assets/js/category-properties.js',
			[],
			WPEP_VERSION,
			true
		);
	}

	public static function maybe_render_saved_notice(): void {
		$screen = get_current_screen();
		if ( ! $screen || 'term' !== $screen->base || Taxonomy::SLUG !== $screen->taxonomy ) {
			return;
		}

		$saved_term = get_transient( 'wpep_category_props_saved_' . get_current_user_id() );
		if ( ! $saved_term ) {
			return;
		}

		delete_transient( 'wpep_category_props_saved_' . get_current_user_id() );

		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Category properties saved.', 'wp-electronic-parts' ) . '</p></div>';
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function empty_definition(): array {
		return [
			'key'                  => '',
			'label'                => '',
			'type'                 => Property_Types::TYPE_TEXT,
			'required'             => false,
			'inheritance'          => 'none',
			'options'              => [],
			'units_source_term_id' => 0,
			'source_term_id'       => 0,
		];
	}

	/**
	 * @return array<int, string>
	 */
	public static function get_term_choices(): array {
		$terms = get_terms(
			[
				'taxonomy'   => Taxonomy::SLUG,
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			]
		);

		if ( ! is_array( $terms ) ) {
			return [];
		}

		$by_id = [];
		foreach ( $terms as $term ) {
			$by_id[ (int) $term->term_id ] = $term;
		}

		$labels = [];
		foreach ( $terms as $term ) {
			$parts  = [ $term->name ];
			$parent = (int) $term->parent;
			$guard  = 0;
			while ( $parent > 0 && isset( $by_id[ $parent ] ) && $guard < 50 ) {
				array_unshift( $parts, $by_id[ $parent ]->name );
				$parent = (int) $by_id[ $parent ]->parent;
				++$guard;
			}
			$labels[ (int) $term->term_id ] = implode( ' › ', $parts );
		}

		asort( $labels, SORT_NATURAL | SORT_FLAG_CASE );

		return $labels;
	}
}
