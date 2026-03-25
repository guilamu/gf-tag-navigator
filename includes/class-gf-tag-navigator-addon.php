<?php
/**
 * Main GFAddOn class for Gravity Forms Tag Navigator.
 *
 * @package GFTagNavigator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

GFForms::include_addon_framework();

class GFTagNavigatorAddOn extends GFAddOn {

	protected $_version                  = GFTN_VERSION;
	protected $_min_gravityforms_version = '2.5';
	protected $_slug                     = 'gf-tag-navigator';
	protected $_path                     = 'gf-tag-navigator/gf-tag-navigator.php';
	protected $_full_path                = __FILE__;
	protected $_title                    = 'Gravity Forms Tag Navigator';
	protected $_short_title              = 'Tag Navigator';

	protected $_capabilities              = array( 'gravityforms_edit_forms' );
	protected $_capabilities_settings_page = array( 'gravityforms_edit_forms' );
	protected $_capabilities_form_settings = array( 'gravityforms_edit_forms' );
	protected $_capabilities_uninstall     = array( 'gravityforms_uninstall' );

	private static $_instance = null;

	public static function get_instance() {
		if ( null === self::$_instance ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	public function get_menu_icon() {
		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="currentColor" d="M11 2h7v7L8 19l-7-7zm3 3.5c0-.83.67-1.5 1.5-1.5s1.5.67 1.5 1.5S16.33 7 15.5 7 14 6.33 14 5.5z"/></svg>';
	}

	// ------------------------------------------------------------------
	// Initialization
	// ------------------------------------------------------------------

	public function init() {
		parent::init();
	}

	public function init_admin() {
		parent::init_admin();

		$form_list_ui = new GFTagNavigatorFormListUI();
		$form_list_ui->register_hooks();
	}

	public function init_ajax() {
		parent::init_ajax();

		add_action( 'wp_ajax_gftn_create_tag', array( $this, 'ajax_create_tag' ) );
		add_action( 'wp_ajax_gftn_update_tag', array( $this, 'ajax_update_tag' ) );
		add_action( 'wp_ajax_gftn_delete_tag', array( $this, 'ajax_delete_tag' ) );
		add_action( 'wp_ajax_gftn_save_form_tags', array( $this, 'ajax_save_form_tags' ) );
	}

	// ------------------------------------------------------------------
	// Admin bar — tag shortcuts
	// ------------------------------------------------------------------

	public function enqueue_admin_bar_css(): void {
		if ( is_admin_bar_showing() ) {
			wp_enqueue_style( 'gftn_admin_bar_css', GFTN_URL . 'admin/css/admin.css', array(), $this->_version );
		}
	}

	public function add_admin_bar_tags( $wp_admin_bar ): void {
		if ( ! current_user_can( 'gravityforms_edit_forms' ) ) {
			return;
		}

		$catalog = GFTagNavigatorCatalog::get_all();
		if ( empty( $catalog ) ) {
			return;
		}

		$forms_url = admin_url( 'admin.php?page=gf_edit_forms' );

		$wp_admin_bar->add_node( array(
			'id'    => 'gftn-tags',
			'title' => '<span class="ab-icon dashicons dashicons-tag"></span>',
			'href'  => $forms_url,
			'meta'  => array(
				'title' => esc_attr__( 'Filter forms by tag', 'gf-tag-navigator' ),
			),
		) );

		foreach ( $catalog as $tag ) {
			$wp_admin_bar->add_node( array(
				'parent' => 'gftn-tags',
				'id'     => 'gftn-tag-' . $tag['slug'],
				'title'  => '<span class="gftn-bar-dot" style="background:' . esc_attr( $tag['color'] ) . ';"></span>' . esc_html( $tag['name'] ),
				'href'   => esc_url( add_query_arg( 'gftn', $tag['slug'], $forms_url ) ),
			) );
		}
	}

	// ------------------------------------------------------------------
	// Scripts & Styles
	// ------------------------------------------------------------------

	public function scripts() {
		$scripts = array(
			array(
				'handle'  => 'gftn_admin_js',
				'src'     => GFTN_URL . 'admin/js/admin.js',
				'version' => $this->_version,
				'deps'    => array( 'jquery' ),
				'enqueue' => array(
					array(
						'admin_page' => array( 'form_list', 'form_settings', 'plugin_settings' ),
					),
				),
			),
		);

		return array_merge( parent::scripts(), $scripts );
	}

	public function styles() {
		$styles = array(
			array(
				'handle'  => 'gftn_admin_css',
				'src'     => GFTN_URL . 'admin/css/admin.css',
				'version' => $this->_version,
				'enqueue' => array(
					array(
						'admin_page' => array( 'form_list', 'form_settings', 'plugin_settings' ),
					),
				),
			),
		);

		return array_merge( parent::styles(), $styles );
	}

	/**
	 * Localize JS with catalog, nonces, and (on form list) assignments.
	 */
	public function get_script_data( $form = null, $is_ajax = false ) {
		$catalog = GFTagNavigatorCatalog::get_all();

		$data = array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonces'  => array(
				'createTag'    => wp_create_nonce( 'gftn_create_tag' ),
				'updateTag'    => wp_create_nonce( 'gftn_update_tag' ),
				'deleteTag'    => wp_create_nonce( 'gftn_delete_tag' ),
				'saveFormTags' => wp_create_nonce( 'gftn_save_form_tags' ),
			),
			'tags'    => $catalog,
		);

		// On the forms list page, include per-form tag assignments.
		if ( $this->is_form_list() ) {
			$forms    = GFAPI::get_forms();
			$form_tags = array();
			foreach ( $forms as $f ) {
				$form_tags[ (string) $f['id'] ] = GFTagNavigatorCatalog::get_form_tags( $f );
			}
			$data['formTags'] = $form_tags;
		}

		return $data;
	}

	/**
	 * Enqueue localized data for the admin JS handle.
	 */
	public function localize_scripts() {
		wp_localize_script( 'gftn_admin_js', 'gftnData', $this->get_script_data() );
	}

	/**
	 * Override to inject localized data after scripts are enqueued.
	 */
	public function enqueue_scripts( $form = null, $is_ajax = false ) {
		parent::enqueue_scripts( $form, $is_ajax );

		if ( wp_script_is( 'gftn_admin_js', 'enqueued' ) ) {
			wp_localize_script( 'gftn_admin_js', 'gftnData', $this->get_script_data( $form, $is_ajax ) );
		}
	}

	// ------------------------------------------------------------------
	// Plugin Settings — Tag Catalog Management
	// ------------------------------------------------------------------

	public function plugin_settings_fields() {
		return array(
			array(
				'title'       => esc_html__( 'Manage Tags', 'gf-tag-navigator' ),
				'description' => esc_html__( 'Create, edit, and delete tags to organize your Gravity Forms.', 'gf-tag-navigator' ),
				'fields'      => array(
					array(
						'name' => 'gftn_catalog_ui',
						'type' => 'html',
						'html' => $this->render_catalog_settings_html(),
					),
				),
			),
		);
	}

	private function render_catalog_settings_html(): string {
		$catalog = GFTagNavigatorCatalog::get_all();
		$presets = GFTagNavigatorCatalog::get_color_presets();

		ob_start();
		?>
		<div id="gftn-catalog-manager">
			<table class="gftn-tag-table widefat" id="gftn-tag-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Color', 'gf-tag-navigator' ); ?></th>
						<th><?php esc_html_e( 'Name', 'gf-tag-navigator' ); ?></th>
						<th><?php esc_html_e( 'Slug', 'gf-tag-navigator' ); ?></th>
						<th><?php esc_html_e( 'Used by', 'gf-tag-navigator' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $catalog ) ) : ?>
					<tr class="gftn-empty-state">
						<td colspan="4"><?php esc_html_e( 'No tags yet. Create your first tag below.', 'gf-tag-navigator' ); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( $catalog as $tag ) :
						$usage = GFTagNavigatorCatalog::get_usage_count( $tag['slug'] );
					?>
					<tr data-tag-id="<?php echo esc_attr( $tag['id'] ); ?>" data-tag-slug="<?php echo esc_attr( $tag['slug'] ); ?>">
						<td>
							<span class="gftn-color-swatch" style="background:<?php echo esc_attr( $tag['color'] ); ?>;"></span>
						</td>
						<td class="gftn-tag-name"><?php echo esc_html( $tag['name'] ); ?></td>
						<td class="gftn-tag-slug"><code><?php echo esc_html( $tag['slug'] ); ?></code></td>
						<td class="gftn-tag-usage"><?php
							echo esc_html( sprintf(
								/* translators: %d: number of forms */
								_n( '%d form', '%d forms', $usage, 'gf-tag-navigator' ),
								$usage
							) );
						?></td>
					</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>

			<!-- Add / Edit row -->
			<div id="gftn-tag-form" class="gftn-tag-form">
				<h4 id="gftn-form-title"><?php esc_html_e( 'Add New Tag', 'gf-tag-navigator' ); ?></h4>
				<input type="hidden" id="gftn-edit-id" value="" />
				<label for="gftn-tag-name-input"><?php esc_html_e( 'Name', 'gf-tag-navigator' ); ?></label>
				<input type="text" id="gftn-tag-name-input" class="regular-text" maxlength="60" />

				<label><?php esc_html_e( 'Color', 'gf-tag-navigator' ); ?></label>
				<div class="gftn-swatch-picker" id="gftn-swatch-picker">
					<?php foreach ( $presets as $hex ) : ?>
						<button type="button"
							class="gftn-color-swatch"
							data-color="<?php echo esc_attr( $hex ); ?>"
							style="background:<?php echo esc_attr( $hex ); ?>;"
							aria-label="<?php echo esc_attr( $hex ); ?>">
						</button>
					<?php endforeach; ?>
				</div>
				<input type="hidden" id="gftn-tag-color-input" value="" />

				<div class="gftn-tag-form-actions">
					<button type="button" class="button" id="gftn-save-tag"><?php esc_html_e( 'Add Tag', 'gf-tag-navigator' ); ?></button>
					<button type="button" class="button" id="gftn-delete-tag-btn" style="display:none;"><?php esc_html_e( 'Delete Tag', 'gf-tag-navigator' ); ?></button>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	// ------------------------------------------------------------------
	// Form Settings — Tag Assignment
	// ------------------------------------------------------------------

	public function form_settings_fields( $form ) {
		return array(
			array(
				'title'       => esc_html__( 'Assign Tags', 'gf-tag-navigator' ),
				'description' => esc_html__( 'Select the tags for this form.', 'gf-tag-navigator' ),
				'fields'      => array(
					array(
						'name' => 'gftn_form_tags_ui',
						'type' => 'html',
						'html' => $this->render_form_tags_html( $form ),
					),
				),
			),
		);
	}

	private function render_form_tags_html( array $form ): string {
		$catalog  = GFTagNavigatorCatalog::get_all();
		$assigned = GFTagNavigatorCatalog::get_form_tags( $form );

		ob_start();

		if ( empty( $catalog ) ) {
			printf(
				'<p>%s <a href="%s">%s</a></p>',
				esc_html__( 'No tags have been created yet.', 'gf-tag-navigator' ),
				esc_url( admin_url( 'admin.php?page=gf_settings&subview=gf-tag-navigator' ) ),
				esc_html__( 'Create tags in plugin settings.', 'gf-tag-navigator' )
			);
		} else {
			echo '<div class="gftn-form-tag-grid">';
			foreach ( $catalog as $tag ) {
				$checked = in_array( $tag['slug'], $assigned, true ) ? 'checked' : '';
				printf(
					'<label class="gftn-pill gftn-pill--checkbox" style="background:%s;color:%s;">
						<input type="checkbox" name="gftn_tags[]" value="%s" %s />
						%s
					</label>',
					esc_attr( $tag['color'] . '1A' ),
					esc_attr( $tag['color'] ),
					esc_attr( $tag['slug'] ),
					esc_attr( $checked ),
					esc_html( $tag['name'] )
				);
			}
			echo '</div>';
		}

		return ob_get_clean();
	}

	/**
	 * Save form tags when form settings are saved.
	 */
	public function save_form_settings( $form, $settings ) {
		$slugs = isset( $_POST['gftn_tags'] ) && is_array( $_POST['gftn_tags'] )
			? array_map( 'sanitize_title', $_POST['gftn_tags'] )
			: array();

		GFTagNavigatorCatalog::save_form_tags( (int) $form['id'], $slugs );

		return parent::save_form_settings( $form, $settings );
	}

	// ------------------------------------------------------------------
	// AJAX Handlers
	// ------------------------------------------------------------------

	public function ajax_create_tag() {
		check_ajax_referer( 'gftn_create_tag', 'nonce' );

		if ( ! GFCommon::current_user_can_any( 'gravityforms_edit_forms' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'gf-tag-navigator' ) ), 403 );
		}

		$name  = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$color = isset( $_POST['color'] ) ? sanitize_text_field( wp_unslash( $_POST['color'] ) ) : '';

		$result = GFTagNavigatorCatalog::create( $name, $color );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'tag' => $result, 'catalog' => GFTagNavigatorCatalog::get_all() ) );
	}

	public function ajax_update_tag() {
		check_ajax_referer( 'gftn_update_tag', 'nonce' );

		if ( ! GFCommon::current_user_can_any( 'gravityforms_edit_forms' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'gf-tag-navigator' ) ), 403 );
		}

		$id    = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
		$name  = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$color = isset( $_POST['color'] ) ? sanitize_text_field( wp_unslash( $_POST['color'] ) ) : '';

		$result = GFTagNavigatorCatalog::update( $id, $name, $color );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'catalog' => GFTagNavigatorCatalog::get_all() ) );
	}

	public function ajax_delete_tag() {
		check_ajax_referer( 'gftn_delete_tag', 'nonce' );

		if ( ! GFCommon::current_user_can_any( 'gravityforms_edit_forms' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'gf-tag-navigator' ) ), 403 );
		}

		$id = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';

		$result = GFTagNavigatorCatalog::delete( $id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'catalog' => GFTagNavigatorCatalog::get_all() ) );
	}

	public function ajax_save_form_tags() {
		check_ajax_referer( 'gftn_save_form_tags', 'nonce' );

		if ( ! GFCommon::current_user_can_any( 'gravityforms_edit_forms' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'gf-tag-navigator' ) ), 403 );
		}

		$form_id = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
		$slugs   = isset( $_POST['tags'] ) && is_array( $_POST['tags'] )
			? array_map( 'sanitize_title', wp_unslash( $_POST['tags'] ) )
			: array();

		if ( ! $form_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid form ID.', 'gf-tag-navigator' ) ) );
		}

		GFTagNavigatorCatalog::save_form_tags( $form_id, $slugs );

		// Return updated pills HTML for the inline column.
		$catalog  = GFTagNavigatorCatalog::get_all();
		$assigned = array();
		foreach ( $catalog as $tag ) {
			if ( in_array( $tag['slug'], $slugs, true ) ) {
				$assigned[] = GFTagNavigatorCatalog::render_pill( $tag );
			}
		}

		wp_send_json_success( array(
			'pillsHtml' => implode( ' ', $assigned ),
			'tags'      => $slugs,
		) );
	}

	// ------------------------------------------------------------------
	// Helpers
	// ------------------------------------------------------------------

	/**
	 * Detect whether we are on the GF form list page.
	 */
	public function is_form_list(): bool {
		return 'form_list' === GFForms::get_page();
	}
}
