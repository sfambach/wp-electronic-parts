<?php
/**
 * Property type helpers: labels, options resolution, sanitize, validate.
 *
 * @package WP_Electronic_Parts
 */

namespace WPEP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Type-safe handling for category/part property definitions and values.
 */
final class Property_Types {

	public const TYPE_TEXT               = 'text';
	public const TYPE_TEXTAREA           = 'textarea';
	public const TYPE_INTEGER            = 'integer';
	public const TYPE_NUMBER             = 'number';
	public const TYPE_URL                = 'url';
	public const TYPE_BOOL               = 'bool';
	public const TYPE_ENUM               = 'enum';
	public const TYPE_ENUM_MULTI         = 'enum_multi';
	public const TYPE_TERM_CHILDREN      = 'term_children';
	public const TYPE_TERM_CHILDREN_MULTI = 'term_children_multi';
	public const TYPE_MEASURE            = 'measure';
	public const TYPE_ATTACHMENT         = 'attachment';

	/**
	 * @return array<string, string> type => label
	 */
	public static function type_labels(): array {
		return [
			self::TYPE_TEXT                => __( 'Text', 'wp-electronic-parts' ),
			self::TYPE_TEXTAREA            => __( 'Textarea', 'wp-electronic-parts' ),
			self::TYPE_INTEGER             => __( 'Integer', 'wp-electronic-parts' ),
			self::TYPE_NUMBER              => __( 'Number', 'wp-electronic-parts' ),
			self::TYPE_URL                 => __( 'URL', 'wp-electronic-parts' ),
			self::TYPE_BOOL                => __( 'Boolean', 'wp-electronic-parts' ),
			self::TYPE_ENUM                => __( 'Enum (single)', 'wp-electronic-parts' ),
			self::TYPE_ENUM_MULTI          => __( 'Enum (multiple)', 'wp-electronic-parts' ),
			self::TYPE_TERM_CHILDREN       => __( 'Subcategories (single)', 'wp-electronic-parts' ),
			self::TYPE_TERM_CHILDREN_MULTI => __( 'Subcategories (multiple)', 'wp-electronic-parts' ),
			self::TYPE_MEASURE             => __( 'Measure (value + unit)', 'wp-electronic-parts' ),
			self::TYPE_ATTACHMENT          => __( 'Attachment', 'wp-electronic-parts' ),
		];
	}

	/**
	 * @return list<string>
	 */
	public static function all_types(): array {
		return array_keys( self::type_labels() );
	}

	public static function is_valid_type( string $type ): bool {
		return in_array( $type, self::all_types(), true );
	}

	/**
	 * Descendants of a term as id => name options (flat, sorted by name).
	 *
	 * @return array<int, string>
	 */
	public static function resolve_term_options( int $parent_term_id ): array {
		if ( $parent_term_id <= 0 ) {
			return [];
		}

		$terms = get_terms(
			[
				'taxonomy'   => Taxonomy::SLUG,
				'hide_empty' => false,
				'child_of'   => $parent_term_id,
				'orderby'    => 'name',
				'order'      => 'ASC',
			]
		);

		if ( ! is_array( $terms ) ) {
			return [];
		}

		$options = [];
		foreach ( $terms as $term ) {
			$options[ (int) $term->term_id ] = $term->name;
		}

		return $options;
	}

	/**
	 * Resolve selectable options for a property definition.
	 *
	 * @param array<string, mixed> $definition Property definition.
	 * @return array<int|string, string> value => label
	 */
	public static function resolve_options( array $definition ): array {
		$type = (string) ( $definition['type'] ?? '' );

		if ( self::TYPE_ENUM === $type || self::TYPE_ENUM_MULTI === $type ) {
			$options = [];
			foreach ( (array) ( $definition['options'] ?? [] ) as $option ) {
				$option = sanitize_text_field( (string) $option );
				if ( '' !== $option ) {
					$options[ $option ] = $option;
				}
			}
			return $options;
		}

		if ( self::TYPE_TERM_CHILDREN === $type || self::TYPE_TERM_CHILDREN_MULTI === $type ) {
			$source = (int) ( $definition['source_term_id'] ?? 0 );
			return self::resolve_term_options( $source );
		}

		if ( self::TYPE_MEASURE === $type ) {
			$source = (int) ( $definition['units_source_term_id'] ?? 0 );
			return self::resolve_term_options( $source );
		}

		return [];
	}

	/**
	 * Sanitize a raw value for storage according to type.
	 *
	 * @param array<string, mixed> $definition Property definition.
	 * @return mixed Sanitized value or null if empty/invalid enough to clear.
	 */
	public static function sanitize( string $type, mixed $value, array $definition ): mixed {
		switch ( $type ) {
			case self::TYPE_TEXT:
				$text = sanitize_text_field( (string) $value );
				$text = substr( $text, 0, 500 );
				return '' === $text ? null : $text;

			case self::TYPE_TEXTAREA:
				$text = sanitize_textarea_field( (string) $value );
				return '' === $text ? null : $text;

			case self::TYPE_INTEGER:
				if ( '' === $value || null === $value ) {
					return null;
				}
				$filtered = filter_var( $value, FILTER_VALIDATE_INT );
				return false === $filtered ? null : $filtered;

			case self::TYPE_NUMBER:
				if ( '' === $value || null === $value ) {
					return null;
				}
				$filtered = filter_var( $value, FILTER_VALIDATE_FLOAT );
				return false === $filtered ? null : $filtered;

			case self::TYPE_URL:
				$url = esc_url_raw( trim( (string) $value ) );
				return '' === $url ? null : $url;

			case self::TYPE_BOOL:
				return ( ! empty( $value ) && '0' !== (string) $value ) ? 1 : 0;

			case self::TYPE_ENUM:
				$option = sanitize_text_field( (string) $value );
				return '' === $option ? null : $option;

			case self::TYPE_ENUM_MULTI:
				$raw = is_array( $value ) ? $value : [];
				$out = [];
				foreach ( $raw as $item ) {
					$item = sanitize_text_field( (string) $item );
					if ( '' !== $item ) {
						$out[] = $item;
					}
				}
				return array_values( array_unique( $out ) );

			case self::TYPE_TERM_CHILDREN:
				$id = absint( $value );
				return $id > 0 ? $id : null;

			case self::TYPE_TERM_CHILDREN_MULTI:
				$raw = is_array( $value ) ? $value : [];
				$out = [];
				foreach ( $raw as $item ) {
					$id = absint( $item );
					if ( $id > 0 ) {
						$out[] = $id;
					}
				}
				return array_values( array_unique( $out ) );

			case self::TYPE_MEASURE:
				if ( ! is_array( $value ) ) {
					return null;
				}
				$num  = $value['value'] ?? '';
				$unit = isset( $value['unit'] ) ? absint( $value['unit'] ) : 0;
				if ( '' === $num || null === $num ) {
					$num_val = null;
				} else {
					$filtered = filter_var( $num, FILTER_VALIDATE_FLOAT );
					$num_val  = false === $filtered ? null : $filtered;
				}
				if ( null === $num_val && $unit <= 0 ) {
					return null;
				}
				return [
					'value' => $num_val,
					'unit'  => $unit > 0 ? $unit : 0,
				];

			case self::TYPE_ATTACHMENT:
				$id = absint( $value );
				return $id > 0 ? $id : null;

			default:
				return null;
		}
	}

	/**
	 * Validate a sanitized value against the definition.
	 *
	 * @param array<string, mixed> $definition Property definition.
	 * @return true|\WP_Error
	 */
	public static function validate( string $type, mixed $value, array $definition, bool $required ): true|\WP_Error {
		$label = (string) ( $definition['label'] ?? $definition['key'] ?? __( 'Field', 'wp-electronic-parts' ) );

		$is_empty = self::is_empty_value( $type, $value );
		if ( $required && $is_empty ) {
			return new \WP_Error(
				'wpep_required',
				sprintf(
					/* translators: %s: property label */
					__( '“%s” is required.', 'wp-electronic-parts' ),
					$label
				)
			);
		}

		if ( $is_empty ) {
			return true;
		}

		$options = self::resolve_options( $definition );

		switch ( $type ) {
			case self::TYPE_URL:
				if ( ! wp_http_validate_url( (string) $value ) ) {
					return new \WP_Error(
						'wpep_invalid_url',
						sprintf(
							/* translators: %s: property label */
							__( '“%s” must be a valid URL.', 'wp-electronic-parts' ),
							$label
						)
					);
				}
				break;

			case self::TYPE_ENUM:
				if ( ! array_key_exists( (string) $value, $options ) ) {
					return new \WP_Error(
						'wpep_invalid_enum',
						sprintf(
							/* translators: %s: property label */
							__( '“%s” has an invalid option.', 'wp-electronic-parts' ),
							$label
						)
					);
				}
				break;

			case self::TYPE_ENUM_MULTI:
				foreach ( (array) $value as $item ) {
					if ( ! array_key_exists( (string) $item, $options ) ) {
						return new \WP_Error(
							'wpep_invalid_enum',
							sprintf(
								/* translators: %s: property label */
								__( '“%s” has an invalid option.', 'wp-electronic-parts' ),
								$label
							)
						);
					}
				}
				break;

			case self::TYPE_TERM_CHILDREN:
				if ( ! array_key_exists( (int) $value, $options ) ) {
					return new \WP_Error(
						'wpep_invalid_term',
						sprintf(
							/* translators: %s: property label */
							__( '“%s” must be a valid subcategory.', 'wp-electronic-parts' ),
							$label
						)
					);
				}
				break;

			case self::TYPE_TERM_CHILDREN_MULTI:
				foreach ( (array) $value as $item ) {
					if ( ! array_key_exists( (int) $item, $options ) ) {
						return new \WP_Error(
							'wpep_invalid_term',
							sprintf(
								/* translators: %s: property label */
								__( '“%s” must be a valid subcategory.', 'wp-electronic-parts' ),
								$label
							)
						);
					}
				}
				break;

			case self::TYPE_MEASURE:
				if ( ! is_array( $value ) ) {
					return new \WP_Error( 'wpep_invalid_measure', __( 'Invalid measure value.', 'wp-electronic-parts' ) );
				}
				$num  = $value['value'] ?? null;
				$unit = (int) ( $value['unit'] ?? 0 );
				if ( $required && ( null === $num || $unit <= 0 ) ) {
					return new \WP_Error(
						'wpep_required',
						sprintf(
							/* translators: %s: property label */
							__( '“%s” requires both a value and a unit.', 'wp-electronic-parts' ),
							$label
						)
					);
				}
				if ( null !== $num && false === filter_var( $num, FILTER_VALIDATE_FLOAT ) && ! is_float( $num ) && ! is_int( $num ) ) {
					return new \WP_Error(
						'wpep_invalid_number',
						sprintf(
							/* translators: %s: property label */
							__( '“%s” value must be a number.', 'wp-electronic-parts' ),
							$label
						)
					);
				}
				if ( $unit > 0 && ! array_key_exists( $unit, $options ) ) {
					return new \WP_Error(
						'wpep_invalid_unit',
						sprintf(
							/* translators: %s: property label */
							__( '“%s” unit is invalid.', 'wp-electronic-parts' ),
							$label
						)
					);
				}
				if ( ( null !== $num && $unit <= 0 ) || ( null === $num && $unit > 0 ) ) {
					return new \WP_Error(
						'wpep_incomplete_measure',
						sprintf(
							/* translators: %s: property label */
							__( '“%s” needs both value and unit, or neither.', 'wp-electronic-parts' ),
							$label
						)
					);
				}
				break;

			case self::TYPE_ATTACHMENT:
				$post = get_post( (int) $value );
				if ( ! $post instanceof \WP_Post || 'attachment' !== $post->post_type ) {
					return new \WP_Error(
						'wpep_invalid_attachment',
						sprintf(
							/* translators: %s: property label */
							__( '“%s” must be a media attachment.', 'wp-electronic-parts' ),
							$label
						)
					);
				}
				break;
		}

		return true;
	}

	/**
	 * @param mixed $value Sanitized value.
	 */
	public static function is_empty_value( string $type, mixed $value ): bool {
		if ( null === $value ) {
			return true;
		}

		return match ( $type ) {
			self::TYPE_BOOL => false,
			self::TYPE_ENUM_MULTI, self::TYPE_TERM_CHILDREN_MULTI => [] === $value,
			self::TYPE_MEASURE => ! is_array( $value )
				|| ( null === ( $value['value'] ?? null ) && empty( $value['unit'] ) ),
			default => '' === $value || [] === $value,
		};
	}
}
