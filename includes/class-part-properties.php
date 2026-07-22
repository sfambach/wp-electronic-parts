<?php
/**
 * Resolve category property schemas onto parts and store values.
 *
 * @package WP_Electronic_Parts
 */

namespace WPEP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Part-side property values derived from assigned categories.
 */
final class Part_Properties {

	public const META_KEY = 'wpep_property_values';

	public static function register(): void {
		add_action( 'init', [ self::class, 'register_post_meta' ] );
		add_action( 'add_meta_boxes', [ self::class, 'register_meta_box' ] );
		add_action( 'save_post_' . Post_Type::SLUG, [ self::class, 'save_meta_box' ], 20, 2 );
		add_action( 'admin_notices', [ self::class, 'render_admin_notices' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_assets' ] );
	}

	public static function register_post_meta(): void {
		register_post_meta(
			Post_Type::SLUG,
			self::META_KEY,
			[
				'type'              => 'object',
				'description'       => __( 'Values for category-defined properties.', 'wp-electronic-parts' ),
				'single'            => true,
				'default'           => [],
				'show_in_rest'      => [
					'schema' => [
						'type'                 => 'object',
						'additionalProperties' => true,
					],
				],
				'sanitize_callback' => [ self::class, 'sanitize_values_meta' ],
				'auth_callback'     => static function (): bool {
					return current_user_can( 'edit_posts' );
				},
			]
		);
	}

	/**
	 * @param mixed $value Raw meta.
	 * @return array<string, mixed>
	 */
	public static function sanitize_values_meta( mixed $value ): array {
		return is_array( $value ) ? $value : [];
	}

	/**
	 * Resolve merged property definitions for a part.
	 *
	 * @return list<array<string, mixed>>
	 */
	public static function resolve_schema_for_post( int $post_id ): array {
		$terms = wp_get_post_terms( $post_id, Taxonomy::SLUG );
		if ( ! is_array( $terms ) || [] === $terms ) {
			return [];
		}

		return self::merge_definitions_from_terms( $terms );
	}

	/**
	 * Resolve schema from category term IDs (e.g. unsaved part form).
	 *
	 * @param list<int> $term_ids Term IDs.
	 * @return list<array<string, mixed>>
	 */
	public static function resolve_schema_for_term_ids( array $term_ids ): array {
		$terms = [];
		foreach ( $term_ids as $term_id ) {
			$term = get_term( (int) $term_id, Taxonomy::SLUG );
			if ( $term instanceof \WP_Term ) {
				$terms[] = $term;
			}
		}

		if ( [] === $terms ) {
			return [];
		}

		return self::merge_definitions_from_terms( $terms );
	}

	/**
	 * @param array<int, \WP_Term> $terms Assigned terms.
	 * @return list<array<string, mixed>>
	 */
	public static function merge_definitions_from_terms( array $terms ): array {
		$assigned_ids = [];
		foreach ( $terms as $term ) {
			$assigned_ids[ (int) $term->term_id ] = true;
		}

		/** @var array<string, array{definition: array<string, mixed>, score: array{direct: int, depth: int, term_id: int}}> $best */
		$best = [];

		foreach ( $terms as $term ) {
			$self_id = (int) $term->term_id;
			$chain   = self::ancestor_chain( $term );

			foreach ( $chain as $depth => $chain_term ) {
				$defs = Category_Properties::get_definitions( (int) $chain_term->term_id );
				foreach ( $defs as $definition ) {
					$inheritance = (string) ( $definition['inheritance'] ?? 'none' );
					$is_own      = (int) $chain_term->term_id === $self_id;

					if ( ! $is_own && 'children' !== $inheritance ) {
						continue;
					}

					$key = (string) ( $definition['key'] ?? '' );
					if ( '' === $key ) {
						continue;
					}

					$definition['source_term_id'] = (int) ( $definition['source_term_id'] ?? $chain_term->term_id );
					$definition['source_term_name'] = $chain_term->name;

					$direct = isset( $assigned_ids[ (int) $chain_term->term_id ] ) ? 1 : 0;
					$score  = [
						'direct'  => $direct,
						'depth'   => (int) $depth,
						'term_id' => (int) $chain_term->term_id,
					];

					if ( ! isset( $best[ $key ] ) || self::score_beats( $score, $best[ $key ]['score'] ) ) {
						$required = ! empty( $definition['required'] );
						if ( isset( $best[ $key ] ) && ! empty( $best[ $key ]['definition']['required'] ) ) {
							$required = true;
						}
						$definition['required'] = $required;

						$best[ $key ] = [
							'definition' => $definition,
							'score'      => $score,
						];
					} elseif ( ! empty( $definition['required'] ) ) {
						$best[ $key ]['definition']['required'] = true;
					}
				}
			}
		}

		$merged = [];
		foreach ( $best as $item ) {
			$merged[] = $item['definition'];
		}

		usort(
			$merged,
			static function ( array $a, array $b ): int {
				$name_cmp = strcasecmp( (string) ( $a['source_term_name'] ?? '' ), (string) ( $b['source_term_name'] ?? '' ) );
				if ( 0 !== $name_cmp ) {
					return $name_cmp;
				}
				return strcasecmp( (string) ( $a['label'] ?? '' ), (string) ( $b['label'] ?? '' ) );
			}
		);

		return $merged;
	}

	/**
	 * Ancestor chain starting at the term itself (depth 0), then parent, …
	 *
	 * @return list<\WP_Term>
	 */
	private static function ancestor_chain( \WP_Term $term ): array {
		$chain = [ $term ];
		$parent_id = (int) $term->parent;
		$guard     = 0;

		while ( $parent_id > 0 && $guard < 50 ) {
			$parent = get_term( $parent_id, Taxonomy::SLUG );
			if ( ! $parent instanceof \WP_Term ) {
				break;
			}
			$chain[]   = $parent;
			$parent_id = (int) $parent->parent;
			++$guard;
		}

		return $chain;
	}

	/**
	 * @param array{direct: int, depth: int, term_id: int} $candidate Candidate score.
	 * @param array{direct: int, depth: int, term_id: int} $current   Current best.
	 */
	private static function score_beats( array $candidate, array $current ): bool {
		// Prefer definitions from directly assigned terms.
		if ( $candidate['direct'] !== $current['direct'] ) {
			return $candidate['direct'] > $current['direct'];
		}
		// Prefer deeper (more specific) terms — lower depth index = closer to assigned term.
		if ( $candidate['depth'] !== $current['depth'] ) {
			return $candidate['depth'] < $current['depth'];
		}
		return $candidate['term_id'] > $current['term_id'];
	}

	public static function register_meta_box(): void {
		add_meta_box(
			'wpep-part-properties',
			__( 'Category properties', 'wp-electronic-parts' ),
			[ self::class, 'render_meta_box' ],
			Post_Type::SLUG,
			'normal',
			'high'
		);
	}

	/**
	 * @param \WP_Post $post Post.
	 */
	public static function render_meta_box( \WP_Post $post ): void {
		$schema = self::resolve_schema_for_post( (int) $post->ID );
		$values = get_post_meta( (int) $post->ID, self::META_KEY, true );
		$values = is_array( $values ) ? $values : [];

		wp_nonce_field( 'wpep_save_part_properties', 'wpep_part_properties_nonce' );

		if ( [] === $schema ) {
			echo '<p>' . esc_html__( 'Assign part categories to see category properties here.', 'wp-electronic-parts' ) . '</p>';
			return;
		}

		$grouped = [];
		foreach ( $schema as $definition ) {
			$group = (string) ( $definition['source_term_name'] ?? __( 'Category', 'wp-electronic-parts' ) );
			$grouped[ $group ][] = $definition;
		}
		?>
		<div class="wpep-part-properties">
			<?php foreach ( $grouped as $group_label => $definitions ) : ?>
				<fieldset class="wpep-part-properties__group">
					<legend><?php echo esc_html( $group_label ); ?></legend>
					<?php foreach ( $definitions as $definition ) : ?>
						<?php
						$key   = (string) $definition['key'];
						$value = $values[ $key ] ?? null;
						self::render_field( $definition, $value );
						?>
					<?php endforeach; ?>
				</fieldset>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * @param array<string, mixed> $definition Definition.
	 * @param mixed                $value      Current value.
	 */
	private static function render_field( array $definition, mixed $value ): void {
		$key      = (string) $definition['key'];
		$label    = (string) ( $definition['label'] ?? $key );
		$type     = (string) ( $definition['type'] ?? Property_Types::TYPE_TEXT );
		$required = ! empty( $definition['required'] );
		$name     = 'wpep_property_values[' . $key . ']';
		$id       = 'wpep_prop_' . $key;
		$options  = Property_Types::resolve_options( $definition );
		$req_attr = $required ? 'required' : '';
		?>
		<div class="wpep-part-properties__field" data-type="<?php echo esc_attr( $type ); ?>">
			<label for="<?php echo esc_attr( $id ); ?>">
				<strong><?php echo esc_html( $label ); ?></strong>
				<?php if ( $required ) : ?>
					<span class="wpep-required">*</span>
				<?php endif; ?>
				<code class="wpep-prop-key-hint"><?php echo esc_html( $key ); ?></code>
			</label>
			<?php
			switch ( $type ) {
				case Property_Types::TYPE_TEXTAREA:
					printf(
						'<textarea class="widefat" id="%1$s" name="%2$s" rows="4" %3$s>%4$s</textarea>',
						esc_attr( $id ),
						esc_attr( $name ),
						esc_attr( $req_attr ),
						esc_textarea( (string) ( $value ?? '' ) )
					);
					break;

				case Property_Types::TYPE_INTEGER:
					printf(
						'<input type="number" step="1" class="widefat" id="%1$s" name="%2$s" value="%3$s" %4$s />',
						esc_attr( $id ),
						esc_attr( $name ),
						esc_attr( null === $value ? '' : (string) $value ),
						esc_attr( $req_attr )
					);
					break;

				case Property_Types::TYPE_NUMBER:
					printf(
						'<input type="number" step="any" class="widefat" id="%1$s" name="%2$s" value="%3$s" %4$s />',
						esc_attr( $id ),
						esc_attr( $name ),
						esc_attr( null === $value ? '' : (string) $value ),
						esc_attr( $req_attr )
					);
					break;

				case Property_Types::TYPE_URL:
					printf(
						'<input type="url" class="widefat" id="%1$s" name="%2$s" value="%3$s" %4$s />',
						esc_attr( $id ),
						esc_attr( $name ),
						esc_attr( (string) ( $value ?? '' ) ),
						esc_attr( $req_attr )
					);
					break;

				case Property_Types::TYPE_BOOL:
					printf(
						'<label><input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s /> %4$s</label>',
						esc_attr( $id ),
						esc_attr( $name ),
						checked( ! empty( $value ), true, false ),
						esc_html__( 'Yes', 'wp-electronic-parts' )
					);
					break;

				case Property_Types::TYPE_ENUM:
				case Property_Types::TYPE_TERM_CHILDREN:
					echo '<select class="widefat" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" ' . esc_attr( $req_attr ) . '>';
					echo '<option value="">' . esc_html__( '— Select —', 'wp-electronic-parts' ) . '</option>';
					foreach ( $options as $opt_value => $opt_label ) {
						printf(
							'<option value="%1$s" %2$s>%3$s</option>',
							esc_attr( (string) $opt_value ),
							selected( (string) ( $value ?? '' ), (string) $opt_value, false ),
							esc_html( (string) $opt_label )
						);
					}
					echo '</select>';
					break;

				case Property_Types::TYPE_ENUM_MULTI:
				case Property_Types::TYPE_TERM_CHILDREN_MULTI:
					$selected = is_array( $value ) ? array_map( 'strval', $value ) : [];
					echo '<select class="widefat" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '[]" multiple size="5">';
					foreach ( $options as $opt_value => $opt_label ) {
						printf(
							'<option value="%1$s" %2$s>%3$s</option>',
							esc_attr( (string) $opt_value ),
							selected( in_array( (string) $opt_value, $selected, true ), true, false ),
							esc_html( (string) $opt_label )
						);
					}
					echo '</select>';
					break;

				case Property_Types::TYPE_MEASURE:
					$measure_value = is_array( $value ) ? ( $value['value'] ?? '' ) : '';
					$measure_unit  = is_array( $value ) ? (int) ( $value['unit'] ?? 0 ) : 0;
					echo '<div class="wpep-measure-fields">';
					printf(
						'<input type="number" step="any" class="wpep-measure-value" id="%1$s" name="%2$s[value]" value="%3$s" %4$s />',
						esc_attr( $id ),
						esc_attr( $name ),
						esc_attr( null === $measure_value || '' === $measure_value ? '' : (string) $measure_value ),
						esc_attr( $req_attr )
					);
					echo '<select class="wpep-measure-unit" name="' . esc_attr( $name ) . '[unit]" ' . esc_attr( $req_attr ) . '>';
					echo '<option value="">' . esc_html__( '— Unit —', 'wp-electronic-parts' ) . '</option>';
					foreach ( $options as $opt_value => $opt_label ) {
						printf(
							'<option value="%1$s" %2$s>%3$s</option>',
							esc_attr( (string) $opt_value ),
							selected( $measure_unit, (int) $opt_value, false ),
							esc_html( (string) $opt_label )
						);
					}
					echo '</select></div>';
					break;

				case Property_Types::TYPE_ATTACHMENT:
					$attachment_id = absint( $value );
					$url           = $attachment_id ? wp_get_attachment_url( $attachment_id ) : '';
					echo '<div class="wpep-attachment-field">';
					printf(
						'<input type="hidden" class="wpep-attachment-id" id="%1$s" name="%2$s" value="%3$s" />',
						esc_attr( $id ),
						esc_attr( $name ),
						esc_attr( (string) $attachment_id )
					);
					echo '<button type="button" class="button wpep-attachment-select">' . esc_html__( 'Select media', 'wp-electronic-parts' ) . '</button> ';
					echo '<button type="button" class="button wpep-attachment-clear">' . esc_html__( 'Clear', 'wp-electronic-parts' ) . '</button>';
					echo '<p class="wpep-attachment-preview">';
					if ( $url ) {
						echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( basename( $url ) ) . '</a>';
					}
					echo '</p></div>';
					break;

				default:
					printf(
						'<input type="text" class="widefat" id="%1$s" name="%2$s" value="%3$s" maxlength="500" %4$s />',
						esc_attr( $id ),
						esc_attr( $name ),
						esc_attr( (string) ( $value ?? '' ) ),
						esc_attr( $req_attr )
					);
					break;
			}
			?>
		</div>
		<?php
	}

	/**
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post.
	 */
	public static function save_meta_box( int $post_id, \WP_Post $post ): void {
		unset( $post );

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! isset( $_POST['wpep_part_properties_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpep_part_properties_nonce'] ) ), 'wpep_save_part_properties' )
		) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$schema = self::resolve_schema_for_post( $post_id );
		$raw    = isset( $_POST['wpep_property_values'] ) && is_array( $_POST['wpep_property_values'] )
			? wp_unslash( $_POST['wpep_property_values'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			: [];

		$existing = get_post_meta( $post_id, self::META_KEY, true );
		$existing = is_array( $existing ) ? $existing : [];
		$stored   = $existing;
		$errors   = [];

		$schema_keys = [];
		foreach ( $schema as $definition ) {
			$key  = (string) $definition['key'];
			$type = (string) $definition['type'];
			$schema_keys[ $key ] = true;

			$input = $raw[ $key ] ?? null;
			if ( Property_Types::TYPE_BOOL === $type && ! isset( $raw[ $key ] ) ) {
				$input = 0;
			}

			$sanitized = Property_Types::sanitize( $type, $input, $definition );
			$valid     = Property_Types::validate( $type, $sanitized, $definition, ! empty( $definition['required'] ) );

			if ( is_wp_error( $valid ) ) {
				$errors[] = $valid->get_error_message();
				continue;
			}

			if ( null === $sanitized || ( is_array( $sanitized ) && Property_Types::TYPE_MEASURE !== $type && [] === $sanitized ) ) {
				unset( $stored[ $key ] );
			} else {
				$stored[ $key ] = $sanitized;
			}
		}

		// Drop values for keys no longer in schema.
		foreach ( array_keys( $stored ) as $key ) {
			if ( ! isset( $schema_keys[ $key ] ) ) {
				unset( $stored[ $key ] );
			}
		}

		update_post_meta( $post_id, self::META_KEY, $stored );

		if ( [] !== $errors ) {
			set_transient(
				self::notice_key( $post_id ),
				$errors,
				45
			);
		}
	}

	public static function render_admin_notices(): void {
		$screen = get_current_screen();
		if ( ! $screen || Post_Type::SLUG !== $screen->post_type ) {
			return;
		}

		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $post_id <= 0 ) {
			return;
		}

		$errors = get_transient( self::notice_key( $post_id ) );
		if ( ! is_array( $errors ) || [] === $errors ) {
			return;
		}

		delete_transient( self::notice_key( $post_id ) );

		echo '<div class="notice notice-warning is-dismissible"><p><strong>' . esc_html__( 'Category properties:', 'wp-electronic-parts' ) . '</strong></p><ul>';
		foreach ( $errors as $error ) {
			echo '<li>' . esc_html( (string) $error ) . '</li>';
		}
		echo '</ul></div>';
	}

	public static function enqueue_assets( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || Post_Type::SLUG !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style(
			'wpep-properties-admin',
			WPEP_PLUGIN_URL . 'assets/css/properties-admin.css',
			[],
			WPEP_VERSION
		);

		wp_enqueue_media();
		wp_enqueue_script(
			'wpep-part-properties',
			WPEP_PLUGIN_URL . 'assets/js/part-properties.js',
			[ 'jquery' ],
			WPEP_VERSION,
			true
		);
	}

	private static function notice_key( int $post_id ): string {
		return 'wpep_part_prop_notices_' . $post_id;
	}
}
