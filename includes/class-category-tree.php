<?php
/**
 * Split-view catalog admin: category tree + right-hand editors.
 *
 * @package WP_Electronic_Parts
 */

namespace WPEP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the catalog admin page and tree markup.
 */
final class Category_Tree {

	public const PAGE_SLUG = 'wpep-category-tree';

	public static function register(): void {
		add_action( 'admin_menu', [ self::class, 'register_menu' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_assets' ] );
		add_action( 'load-edit-tags.php', [ self::class, 'redirect_default_list_to_tree' ] );
		add_filter( 'redirect_term_location', [ self::class, 'redirect_after_term_save' ], 10, 2 );
		add_action( 'wp_ajax_wpep_delete_category', [ self::class, 'ajax_delete_category' ] );
	}

	public static function tree_url(): string {
		return (string) add_query_arg(
			[
				'post_type' => Post_Type::SLUG,
				'page'      => self::PAGE_SLUG,
			],
			admin_url( 'edit.php' )
		);
	}

	public static function redirect_default_list_to_tree(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( $_GET['taxonomy'] ) ) : '';
		if ( Taxonomy::SLUG !== $taxonomy ) {
			return;
		}
		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			return;
		}
		wp_safe_redirect( self::tree_url() );
		exit;
	}

	public static function redirect_after_term_save( string $location, \WP_Taxonomy $taxonomy ): string {
		if ( Taxonomy::SLUG !== $taxonomy->name ) {
			return $location;
		}
		return self::tree_url();
	}

	public static function register_menu(): void {
		$taxonomy = get_taxonomy( Taxonomy::SLUG );
		$cap      = $taxonomy instanceof \WP_Taxonomy ? $taxonomy->cap->manage_terms : 'manage_categories';

		add_submenu_page(
			'edit.php?post_type=' . Post_Type::SLUG,
			__( 'Catalog', 'wp-electronic-parts' ),
			__( 'Catalog', 'wp-electronic-parts' ),
			$cap,
			self::PAGE_SLUG,
			[ self::class, 'render_page' ]
		);
	}

	public static function enqueue_assets( string $hook_suffix ): void {
		if ( 'electronic_part_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'wpep-catalog',
			WPEP_PLUGIN_URL . 'assets/css/category-tree.css',
			[ 'dashicons' ],
			WPEP_VERSION
		);

		$scripts = [
			'wpep-events'           => 'assets/js/wpep-events.js',
			'wpep-parts-list-pane'  => 'assets/js/parts-list-pane.js',
			'wpep-category-editor'  => 'assets/js/category-editor-pane.js',
			'wpep-part-editor'      => 'assets/js/part-editor-pane.js',
			'wpep-category-tree'    => 'assets/js/category-tree-pane.js',
			'wpep-catalog-app'      => 'assets/js/category-tree-app.js',
		];

		$prev = [];
		foreach ( $scripts as $handle => $path ) {
			wp_enqueue_script(
				$handle,
				WPEP_PLUGIN_URL . $path,
				$prev,
				WPEP_VERSION,
				true
			);
			$prev = [ $handle ];
		}

		wp_localize_script(
			'wpep-parts-list-pane',
			'wpepCatalog',
			[
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( Admin_Ajax::NONCE_ACTION ),
				'deleteNonce'=> wp_create_nonce( 'wpep_delete_category' ),
				'typeLabels' => Property_Types::type_labels(),
				'i18n'       => [
					'empty'            => __( 'Select a category, click a count for parts, or create a new part.', 'wp-electronic-parts' ),
					'loading'          => __( 'Loading…', 'wp-electronic-parts' ),
					'save'             => __( 'Save', 'wp-electronic-parts' ),
					'saved'            => __( 'Saved.', 'wp-electronic-parts' ),
					'addParameter'     => __( 'Add parameter', 'wp-electronic-parts' ),
					'addPart'          => __( 'Add part', 'wp-electronic-parts' ),
					'newPart'          => __( 'New part', 'wp-electronic-parts' ),
					'addRoot'          => __( 'Add root category', 'wp-electronic-parts' ),
					'addChild'         => __( 'Add child', 'wp-electronic-parts' ),
					'delete'           => __( 'Delete', 'wp-electronic-parts' ),
					'back'             => __( 'Back', 'wp-electronic-parts' ),
					'partsIn'          => __( 'Parts in', 'wp-electronic-parts' ),
					'noParts'          => __( 'No parts yet.', 'wp-electronic-parts' ),
					'parameters'      => __( 'Parameters', 'wp-electronic-parts' ),
					'parts'            => __( 'Parts', 'wp-electronic-parts' ),
					'categorySettings' => __( 'Category settings', 'wp-electronic-parts' ),
					'partSettings'     => __( 'Part settings', 'wp-electronic-parts' ),
					'name'             => __( 'Name', 'wp-electronic-parts' ),
					'slug'             => __( 'Slug', 'wp-electronic-parts' ),
					'parent'           => __( 'Parent', 'wp-electronic-parts' ),
					'description'      => __( 'Description', 'wp-electronic-parts' ),
					'none'             => __( '— None —', 'wp-electronic-parts' ),
					'required'         => __( 'Required', 'wp-electronic-parts' ),
					'inherit'          => __( 'Inherit to children', 'wp-electronic-parts' ),
					'type'             => __( 'Type', 'wp-electronic-parts' ),
					'key'              => __( 'Key', 'wp-electronic-parts' ),
					'label'            => __( 'Label', 'wp-electronic-parts' ),
					'options'          => __( 'Options (one per line)', 'wp-electronic-parts' ),
					'unitsSource'      => __( 'Units from category', 'wp-electronic-parts' ),
					'remove'           => __( 'Remove', 'wp-electronic-parts' ),
					'categories'       => __( 'Categories', 'wp-electronic-parts' ),
					'confirmLeaf'      => __( 'Delete this category?', 'wp-electronic-parts' ),
					'dialogTitle'      => __( 'Delete category with children', 'wp-electronic-parts' ),
					'dialogText'       => __( 'This category has child categories. What should happen to them?', 'wp-electronic-parts' ),
					'promoteChildren'  => __( 'Move children up one level', 'wp-electronic-parts' ),
					'deleteChildren'   => __( 'Delete children as well', 'wp-electronic-parts' ),
					'cancel'           => __( 'Cancel', 'wp-electronic-parts' ),
					'deleteFailed'     => __( 'Could not delete the category.', 'wp-electronic-parts' ),
					'unsaved'          => __( 'You have unsaved changes. Continue?', 'wp-electronic-parts' ),
					'newCategoryName'  => __( 'New category', 'wp-electronic-parts' ),
					'yes'              => __( 'Yes', 'wp-electronic-parts' ),
					'no'               => __( 'No', 'wp-electronic-parts' ),
				],
			]
		);
	}

	public static function render_page(): void {
		if ( ! self::user_can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to manage categories.', 'wp-electronic-parts' ) );
		}

		$terms = get_terms(
			[
				'taxonomy'   => Taxonomy::SLUG,
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			]
		);
		if ( is_wp_error( $terms ) ) {
			$terms = [];
		}
		$tree = self::build_tree( $terms );
		?>
		<div class="wrap wpep-catalog-wrap">
			<h1><?php esc_html_e( 'Catalog', 'wp-electronic-parts' ); ?></h1>
			<div class="wpep-catalog" id="wpep-catalog">
				<aside class="wpep-catalog__tree-pane">
					<div class="wpep-tree-toolbar">
						<button type="button" class="button" id="wpep-tree-expand-all"><?php esc_html_e( 'Expand all', 'wp-electronic-parts' ); ?></button>
						<button type="button" class="button" id="wpep-tree-collapse-all"><?php esc_html_e( 'Collapse all', 'wp-electronic-parts' ); ?></button>
					</div>
					<ul class="wpep-tree" data-wpep-tree>
						<?php self::render_nodes( $tree ); ?>
					</ul>
					<p class="wpep-tree-actions">
						<button type="button" class="button" id="wpep-add-root"><?php esc_html_e( 'Add root category', 'wp-electronic-parts' ); ?></button>
						<button type="button" class="button button-primary" id="wpep-new-part"><?php esc_html_e( 'New part', 'wp-electronic-parts' ); ?></button>
					</p>
				</aside>
				<main class="wpep-catalog__editor-pane" id="wpep-editor-pane">
					<div class="wpep-editor-empty" data-mode-panel="empty">
						<p><?php esc_html_e( 'Select a category, click a count for parts, or create a new part.', 'wp-electronic-parts' ); ?></p>
					</div>
					<div class="wpep-editor-category" data-mode-panel="category" hidden></div>
					<div class="wpep-editor-parts-list" data-mode-panel="parts-list" hidden></div>
					<div class="wpep-editor-part" data-mode-panel="part" hidden></div>
				</main>
			</div>

			<dialog id="wpep-delete-dialog" class="wpep-delete-dialog" hidden>
				<form method="dialog" class="wpep-delete-dialog__form">
					<h2 id="wpep-delete-dialog-title"></h2>
					<p id="wpep-delete-dialog-text"></p>
					<div class="wpep-delete-dialog__actions">
						<button type="submit" value="promote" class="button button-primary" id="wpep-delete-promote"></button>
						<button type="submit" value="delete_children" class="button button-link-delete" id="wpep-delete-cascade"></button>
						<button type="submit" value="cancel" class="button" id="wpep-delete-cancel"></button>
					</div>
				</form>
			</dialog>
		</div>
		<?php
	}

	/**
	 * @param array<int, \WP_Term> $terms Flat terms.
	 * @return array<int, array{term: \WP_Term, children: array<int, mixed>}>
	 */
	private static function build_tree( array $terms ): array {
		$by_parent = [];
		foreach ( $terms as $term ) {
			$by_parent[ (int) $term->parent ][] = $term;
		}
		$build = static function ( int $parent_id ) use ( &$build, $by_parent ): array {
			$nodes = [];
			foreach ( $by_parent[ $parent_id ] ?? [] as $term ) {
				$nodes[] = [
					'term'     => $term,
					'children' => $build( (int) $term->term_id ),
				];
			}
			return $nodes;
		};
		return $build( 0 );
	}

	/**
	 * @param array<int, array{term: \WP_Term, children: array<int, mixed>}> $nodes Nodes.
	 */
	private static function render_nodes( array $nodes ): void {
		foreach ( $nodes as $node ) {
			$term     = $node['term'];
			$children = $node['children'];
			$has_kids = ! empty( $children );
			?>
			<li
				class="wpep-tree__node<?php echo $has_kids ? ' has-children' : ''; ?>"
				data-term-id="<?php echo esc_attr( (string) $term->term_id ); ?>"
				data-has-children="<?php echo $has_kids ? '1' : '0'; ?>"
				data-term-name="<?php echo esc_attr( $term->name ); ?>"
			>
				<div class="wpep-tree__row">
					<?php if ( $has_kids ) : ?>
						<button type="button" class="wpep-tree__toggle" aria-expanded="true" aria-label="<?php esc_attr_e( 'Toggle children', 'wp-electronic-parts' ); ?>">
							<span class="dashicons dashicons-arrow-down" aria-hidden="true"></span>
						</button>
					<?php else : ?>
						<span class="wpep-tree__toggle wpep-tree__toggle--spacer" aria-hidden="true"></span>
					<?php endif; ?>

					<button type="button" class="wpep-tree__name" data-action="select-category">
						<?php echo esc_html( $term->name ); ?>
					</button>

					<span class="wpep-tree__row-end">
						<button type="button" class="wpep-tree__count" data-action="open-parts" title="<?php esc_attr_e( 'Show parts', 'wp-electronic-parts' ); ?>">
							<?php echo esc_html( (string) (int) $term->count ); ?>
						</button>
						<button type="button" class="wpep-tree__icon-btn" data-action="add-child" title="<?php esc_attr_e( 'Add child', 'wp-electronic-parts' ); ?>" aria-label="<?php esc_attr_e( 'Add child', 'wp-electronic-parts' ); ?>">
							<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
						</button>
						<button type="button" class="wpep-tree__icon-btn wpep-tree__icon-btn--danger wpep-tree__delete" data-action="delete" title="<?php esc_attr_e( 'Delete', 'wp-electronic-parts' ); ?>" aria-label="<?php esc_attr_e( 'Delete', 'wp-electronic-parts' ); ?>">
							<span class="dashicons dashicons-trash" aria-hidden="true"></span>
						</button>
					</span>
				</div>
				<?php if ( $has_kids ) : ?>
					<ul class="wpep-tree__children">
						<?php self::render_nodes( $children ); ?>
					</ul>
				<?php endif; ?>
			</li>
			<?php
		}
	}

	public static function ajax_delete_category(): void {
		if ( ! check_ajax_referer( 'wpep_delete_category', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid security token.', 'wp-electronic-parts' ) ], 403 );
		}
		if ( ! self::user_can_manage() ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'wp-electronic-parts' ) ], 403 );
		}

		$term_id = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
		$mode    = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : '';
		if ( $term_id <= 0 || ! in_array( $mode, [ 'leaf', 'promote', 'delete_children' ], true ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid request.', 'wp-electronic-parts' ) ], 400 );
		}

		$term = get_term( $term_id, Taxonomy::SLUG );
		if ( ! $term instanceof \WP_Term ) {
			wp_send_json_error( [ 'message' => __( 'Category not found.', 'wp-electronic-parts' ) ], 404 );
		}

		$children = self::direct_children( $term_id );
		$has_kids = ! empty( $children );
		if ( $has_kids && 'leaf' === $mode ) {
			wp_send_json_error( [ 'message' => __( 'This category has children.', 'wp-electronic-parts' ) ], 400 );
		}

		if ( ! $has_kids ) {
			$result = wp_delete_term( $term_id, Taxonomy::SLUG );
		} elseif ( 'promote' === $mode ) {
			$result = self::delete_and_promote_children( $term );
		} else {
			$result = self::delete_with_children( $term_id );
		}

		if ( is_wp_error( $result ) || false === $result || null === $result ) {
			$message = is_wp_error( $result ) ? $result->get_error_message() : __( 'Could not delete the category.', 'wp-electronic-parts' );
			wp_send_json_error( [ 'message' => $message ], 500 );
		}

		wp_send_json_success( [ 'deletedId' => $term_id ] );
	}

	private static function user_can_manage(): bool {
		$taxonomy = get_taxonomy( Taxonomy::SLUG );
		$cap      = $taxonomy instanceof \WP_Taxonomy ? $taxonomy->cap->manage_terms : 'manage_categories';
		return current_user_can( $cap );
	}

	/**
	 * @return array<int, \WP_Term>
	 */
	private static function direct_children( int $term_id ): array {
		$children = get_terms(
			[
				'taxonomy'   => Taxonomy::SLUG,
				'parent'     => $term_id,
				'hide_empty' => false,
			]
		);
		return is_array( $children ) ? $children : [];
	}

	/**
	 * @return true|\WP_Error|false|null
	 */
	private static function delete_and_promote_children( \WP_Term $term ): mixed {
		$new_parent = (int) $term->parent;
		foreach ( self::direct_children( (int) $term->term_id ) as $child ) {
			$result = wp_update_term( (int) $child->term_id, Taxonomy::SLUG, [ 'parent' => $new_parent ] );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}
		return wp_delete_term( (int) $term->term_id, Taxonomy::SLUG );
	}

	/**
	 * @return true|\WP_Error|false|null
	 */
	private static function delete_with_children( int $term_id ): mixed {
		foreach ( self::direct_children( $term_id ) as $child ) {
			$result = self::delete_with_children( (int) $child->term_id );
			if ( is_wp_error( $result ) || false === $result || null === $result ) {
				return $result;
			}
		}
		return wp_delete_term( $term_id, Taxonomy::SLUG );
	}
}
