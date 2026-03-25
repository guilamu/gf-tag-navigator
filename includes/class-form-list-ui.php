<?php
/**
 * Forms list enhancements — column, filter bar, inline popover.
 *
 * @package GFTagNavigator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GFTagNavigatorFormListUI {

	/**
	 * Register all hooks for the forms list page.
	 */
	public function register_hooks(): void {
		add_filter( 'gform_form_list_columns', array( $this, 'add_tags_column' ) );
		add_action( 'gform_form_list_column_tags', array( $this, 'render_tags_column' ) );
		add_filter( 'gform_form_list_forms', array( $this, 'filter_forms_by_tags' ), 10, 6 );
		add_action( 'admin_footer', array( $this, 'render_filter_bar_and_popover' ) );
	}

	/**
	 * Add a "Tags" column to the form list.
	 */
	public function add_tags_column( array $columns ): array {
		$columns['tags'] = esc_html__( 'Tags', 'gf-tag-navigator' );
		return $columns;
	}

	/**
	 * Render pills and inline edit trigger for a single form row.
	 */
	public function render_tags_column( $form ): void {
		$form     = (array) $form;
		$form_id  = (int) $form['id'];

		// The list table passes a lightweight object without display_meta.
		// Load the full form to read gftn_tags.
		$full_form = GFAPI::get_form( $form_id );
		$catalog   = GFTagNavigatorCatalog::get_all();
		$assigned  = $full_form ? GFTagNavigatorCatalog::get_form_tags( $full_form ) : array();

		echo '<span class="gftn-pills-wrapper" data-form-id="' . esc_attr( $form_id ) . '" data-tags="' . esc_attr( implode( ',', $assigned ) ) . '">';
		foreach ( $catalog as $tag ) {
			if ( in_array( $tag['slug'], $assigned, true ) ) {
				echo GFTagNavigatorCatalog::render_pill( $tag ); // Already escaped inside render_pill.
			}
		}
		echo '</span> ';

		printf(
			'<button type="button" class="gftn-edit-tags gform-st-icon gform-st-icon--circle-plus" data-form-id="%d" aria-label="%s" title="%s"></button>',
			$form_id,
			esc_attr__( 'Edit tags', 'gf-tag-navigator' ),
			esc_attr__( 'Edit tags', 'gf-tag-navigator' )
		);
	}

	/**
	 * Filter the forms list using AND logic on selected tag slugs.
	 */
	public function filter_forms_by_tags( $forms, $sort_column, $sort_dir, $is_trash, $search, $is_active ) {
		if ( empty( $_GET['gftn'] ) ) {
			return $forms;
		}

		$active_slug = sanitize_title( wp_unslash( is_array( $_GET['gftn'] ) ? reset( $_GET['gftn'] ) : $_GET['gftn'] ) );

		if ( empty( $active_slug ) ) {
			return $forms;
		}

		$filtered = array();
		foreach ( $forms as $form ) {
			$form_arr  = (array) $form;
			$full_form = GFAPI::get_form( (int) $form_arr['id'] );
			$tags      = $full_form ? GFTagNavigatorCatalog::get_form_tags( $full_form ) : array();
			if ( in_array( $active_slug, $tags, true ) ) {
				$filtered[] = $form;
			}
		}

		return apply_filters( 'gftn_filter_forms', $filtered, array( $active_slug ) );
	}

	/**
	 * Render the filter bar and popover template in the footer.
	 * JS will relocate the filter bar above the form table.
	 */
	public function render_filter_bar_and_popover(): void {
		if ( 'form_list' !== GFForms::get_page() ) {
			return;
		}

		$catalog     = GFTagNavigatorCatalog::get_all();
		$active_slug = '';
		if ( ! empty( $_GET['gftn'] ) ) {
			$raw         = is_array( $_GET['gftn'] ) ? reset( $_GET['gftn'] ) : $_GET['gftn'];
			$active_slug = sanitize_title( wp_unslash( $raw ) );
		}

		// Build the base URL without gftn params.
		$base_url = remove_query_arg( 'gftn' );

		?>
		<!-- Filter bar -->
		<div id="gftn-filter-bar" class="gftn-filter-bar" style="display:none;">
			<span class="gftn-filter-label"><?php esc_html_e( 'Filter by tag:', 'gf-tag-navigator' ); ?></span>
			<a href="#" data-slug=""
			   class="gftn-pill gftn-pill--all <?php echo empty( $active_slug ) ? 'gftn-pill--active' : ''; ?>">
				<?php esc_html_e( 'All', 'gf-tag-navigator' ); ?>
			</a>
			<?php foreach ( $catalog as $tag ) :
				$is_active = ( $tag['slug'] === $active_slug );
				$extra = $is_active ? array( 'gftn-pill--active' ) : array();
			?>
				<a href="#" data-slug="<?php echo esc_attr( $tag['slug'] ); ?>"
				   class="<?php echo esc_attr( implode( ' ', array_merge( array( 'gftn-pill' ), $extra ) ) ); ?>"
				   style="background:<?php echo esc_attr( $tag['color'] . '1A' ); ?>;color:<?php echo esc_attr( $tag['color'] ); ?>;">
					<?php echo esc_html( $tag['name'] ); ?>
				</a>
			<?php endforeach; ?>
		</div>

		<!-- Inline popover template (cloned per row by JS) -->
		<div id="gftn-popover-template" class="gftn-popover" style="display:none;">
			<div class="gftn-popover-inner">
				<strong><?php esc_html_e( 'Tags', 'gf-tag-navigator' ); ?></strong>
				<?php foreach ( $catalog as $tag ) :
					$text_color = $tag['color'];
					$bg_color   = $tag['color'] . '1A';
				?>
					<label class="gftn-pill gftn-pill--checkbox" style="background:<?php echo esc_attr( $bg_color ); ?>;color:<?php echo esc_attr( $text_color ); ?>;">
						<input type="checkbox" value="<?php echo esc_attr( $tag['slug'] ); ?>" />
						<?php echo esc_html( $tag['name'] ); ?>
					</label>
				<?php endforeach; ?>
				<?php if ( empty( $catalog ) ) : ?>
					<p class="gftn-empty"><?php esc_html_e( 'No tags created yet.', 'gf-tag-navigator' ); ?></p>
				<?php endif; ?>
				<div class="gftn-quick-create">
					<input type="text" class="gftn-quick-name" placeholder="<?php esc_attr_e( 'New tag name…', 'gf-tag-navigator' ); ?>" maxlength="60" />
					<button type="button" class="button button-small gftn-quick-add"><?php esc_html_e( 'Add', 'gf-tag-navigator' ); ?></button>
				</div>
			</div>
		</div>
		<?php
	}
}
