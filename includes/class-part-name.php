<?php
/**
 * Part name meta and automatic post title.
 *
 * @package WP_Electronic_Parts
 */

namespace WPEP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Editable part name; derived post title is locked in the UI.
 */
final class Part_Name {

	public const META_KEY = 'wpep_part_name';

	private static bool $syncing_title = false;

	public static function register(): void {
		add_action( 'init', [ self::class, 'register_meta' ] );
		add_action( 'add_meta_boxes', [ self::class, 'register_meta_box' ] );
		add_action( 'save_post_' . Post_Type::SLUG, [ self::class, 'save_meta_box' ], 10, 2 );
		add_filter( 'wp_insert_post_data', [ self::class, 'filter_post_data' ], 10, 2 );
		add_filter( 'rest_pre_insert_' . Post_Type::SLUG, [ self::class, 'filter_rest_pre_insert' ], 10, 2 );
		add_action( 'added_post_meta', [ self::class, 'sync_title_from_meta' ], 10, 4 );
		add_action( 'updated_post_meta', [ self::class, 'sync_title_from_meta' ], 10, 4 );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_admin_assets' ] );
	}

	public static function register_meta(): void {
		register_post_meta(
			Post_Type::SLUG,
			self::META_KEY,
			[
				'type'              => 'string',
				'description'       => __( 'Human-readable part name used to generate the post title.', 'wp-electronic-parts' ),
				'single'            => true,
				'default'           => '',
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => static function (): bool {
					return current_user_can( 'edit_posts' );
				},
			]
		);
	}

	public static function register_meta_box(): void {
		add_meta_box(
			'wpep-part-name',
			__( 'Part Name', 'wp-electronic-parts' ),
			[ self::class, 'render_meta_box' ],
			Post_Type::SLUG,
			'side',
			'high'
		);
	}

	/**
	 * @param \WP_Post $post Post being edited.
	 */
	public static function render_meta_box( \WP_Post $post ): void {
		$name  = (string) get_post_meta( $post->ID, self::META_KEY, true );
		$title = self::title_from_name( $name );

		wp_nonce_field( 'wpep_save_part_name', 'wpep_part_name_nonce' );
		?>
		<p>
			<label for="wpep_part_name">
				<strong><?php esc_html_e( 'Name', 'wp-electronic-parts' ); ?></strong>
			</label>
			<input
				type="text"
				class="widefat"
				id="wpep_part_name"
				name="wpep_part_name"
				value="<?php echo esc_attr( $name ); ?>"
				autocomplete="off"
			/>
		</p>
		<p class="description">
			<?php esc_html_e( 'The title is generated automatically from this name (special characters removed, spaces become hyphens).', 'wp-electronic-parts' ); ?>
		</p>
		<p>
			<label for="wpep_generated_title">
				<strong><?php esc_html_e( 'Generated title', 'wp-electronic-parts' ); ?></strong>
			</label>
			<input
				type="text"
				class="widefat"
				id="wpep_generated_title"
				value="<?php echo esc_attr( $title ); ?>"
				readonly
			/>
		</p>
		<?php
	}

	/**
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public static function save_meta_box( int $post_id, \WP_Post $post ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! isset( $_POST['wpep_part_name_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpep_part_name_nonce'] ) ), 'wpep_save_part_name' )
		) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST['wpep_part_name'] ) ) {
			return;
		}

		$name = sanitize_text_field( wp_unslash( $_POST['wpep_part_name'] ) );
		update_post_meta( $post_id, self::META_KEY, $name );
	}

	/**
	 * Force post_title from the part name when available.
	 *
	 * @param array<string, mixed> $data    Sanitized post data.
	 * @param array<string, mixed> $postarr Raw post array.
	 * @return array<string, mixed>
	 */
	public static function filter_post_data( array $data, array $postarr ): array {
		if ( ( $data['post_type'] ?? '' ) !== Post_Type::SLUG ) {
			return $data;
		}

		$name = self::resolve_name_from_request( $postarr );
		if ( '' === $name ) {
			return $data;
		}

		$data['post_title'] = self::title_from_name( $name );

		return $data;
	}

	/**
	 * @param \stdClass        $prepared Prepared post data.
	 * @param \WP_REST_Request $request  REST request.
	 * @return \stdClass
	 */
	public static function filter_rest_pre_insert( \stdClass $prepared, \WP_REST_Request $request ): \stdClass {
		$meta = $request->get_param( 'meta' );
		if ( ! is_array( $meta ) || ! isset( $meta[ self::META_KEY ] ) ) {
			return $prepared;
		}

		$name = sanitize_text_field( (string) $meta[ self::META_KEY ] );
		if ( '' === $name ) {
			return $prepared;
		}

		$prepared->post_title = self::title_from_name( $name );

		return $prepared;
	}

	/**
	 * Keep post_title in sync when the name meta changes (e.g. via REST).
	 *
	 * @param int    $meta_id    Meta ID.
	 * @param int    $post_id    Post ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 */
	public static function sync_title_from_meta( int $meta_id, int $post_id, string $meta_key, mixed $meta_value ): void {
		unset( $meta_id );

		if ( self::META_KEY !== $meta_key || self::$syncing_title ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || Post_Type::SLUG !== $post->post_type ) {
			return;
		}

		$name  = sanitize_text_field( (string) $meta_value );
		$title = self::title_from_name( $name );
		if ( '' === $title || $title === $post->post_title ) {
			return;
		}

		self::$syncing_title = true;
		wp_update_post(
			[
				'ID'         => $post_id,
				'post_title' => $title,
			]
		);
		self::$syncing_title = false;
	}

	public static function enqueue_admin_assets( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || Post_Type::SLUG !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style(
			'wpep-admin-edit',
			WPEP_PLUGIN_URL . 'assets/css/admin-edit.css',
			[],
			WPEP_VERSION
		);

		wp_enqueue_script(
			'wpep-admin-edit',
			WPEP_PLUGIN_URL . 'assets/js/admin-edit.js',
			[],
			WPEP_VERSION,
			true
		);
	}

	/**
	 * Build a title from the part name: strip specials, spaces → hyphens.
	 */
	public static function title_from_name( string $name ): string {
		$name = wp_strip_all_tags( $name );
		$name = html_entity_decode( $name, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$name = remove_accents( $name );

		return sanitize_title( $name );
	}

	/**
	 * @param array<string, mixed> $postarr Raw post array.
	 */
	private static function resolve_name_from_request( array $postarr ): string {
		if ( isset( $_POST['wpep_part_name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return sanitize_text_field( wp_unslash( $_POST['wpep_part_name'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}

		$post_id = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;
		if ( $post_id > 0 ) {
			return (string) get_post_meta( $post_id, self::META_KEY, true );
		}

		return '';
	}
}
