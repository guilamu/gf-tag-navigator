<?php
/**
 * Plugin Name:  Gravity Forms Tag Navigator
 * Plugin URI:   https://github.com/guilamu/gf-tag-navigator
 * Description:  Add colored tags to Gravity Forms and filter your form list by tag.
 * Version:      1.0.0
 * Author:       Guilamu
 * Author URI:   https://github.com/guilamu
 * Text Domain:  gf-tag-navigator
 * Domain Path:  /languages
 * Update URI:   https://github.com/guilamu/gf-tag-navigator/
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License:      AGPL-3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GFTN_VERSION', '1.0.0' );
define( 'GFTN_PATH', plugin_dir_path( __FILE__ ) );
define( 'GFTN_URL', plugin_dir_url( __FILE__ ) );
define( 'GFTN_FILE', __FILE__ );

// GitHub auto-updater — load immediately (no GF dependency).
require_once GFTN_PATH . 'includes/class-github-updater.php';

// Bug Reporter integration.
add_action( 'plugins_loaded', function () {
	if ( class_exists( 'Guilamu_Bug_Reporter' ) ) {
		Guilamu_Bug_Reporter::register( array(
			'slug'        => 'gf-tag-navigator',
			'name'        => 'Gravity Forms Tag Navigator',
			'version'     => GFTN_VERSION,
			'github_repo' => 'guilamu/gf-tag-navigator',
		) );
	}
}, 20 );

// GF Add-On bootstrap — only after Gravity Forms is ready.
add_action( 'gform_loaded', array( 'GF_Tag_Navigator_Bootstrap', 'load' ), 5 );

// Admin bar tag shortcuts — register early, outside GFAddOn (runs on ALL admin pages).
add_action( 'admin_bar_menu', 'gftn_admin_bar_tags', 999 );
add_action( 'admin_enqueue_scripts', 'gftn_admin_bar_css' );

function gftn_admin_bar_css() {
	if ( is_admin_bar_showing() ) {
		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style( 'gftn_admin_bar_css', GFTN_URL . 'admin/css/admin.css', array( 'dashicons' ), GFTN_VERSION );
	}
}

function gftn_admin_bar_tags( $wp_admin_bar ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Tag catalog is stored in wp_options — read it directly (no GFAddOn dependency).
	$catalog = get_option( 'gftn_tag_catalog', array() );
	if ( empty( $catalog ) || ! is_array( $catalog ) ) {
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
		if ( empty( $tag['slug'] ) || empty( $tag['name'] ) ) {
			continue;
		}
		$color = ! empty( $tag['color'] ) ? $tag['color'] : '#999';
		$wp_admin_bar->add_node( array(
			'parent' => 'gftn-tags',
			'id'     => 'gftn-tag-' . $tag['slug'],
			'title'  => '<span class="gftn-bar-dot" style="background:' . esc_attr( $color ) . ';"></span>' . esc_html( $tag['name'] ),
			'href'   => esc_url( add_query_arg( 'gftn', $tag['slug'], $forms_url ) ),
		) );
	}
}

class GF_Tag_Navigator_Bootstrap {

	public static function load() {
		if ( ! method_exists( 'GFForms', 'include_addon_framework' ) ) {
			return;
		}

		require_once GFTN_PATH . 'includes/class-tag-catalog.php';
		require_once GFTN_PATH . 'includes/class-form-list-ui.php';
		require_once GFTN_PATH . 'includes/class-gf-tag-navigator-addon.php';

		GFAddOn::register( 'GFTagNavigatorAddOn' );
	}
}

/**
 * Helper — return the singleton add-on instance.
 */
function gf_tag_navigator() {
	return GFTagNavigatorAddOn::get_instance();
}

// Plugin row meta — View Details + Report a Bug.
add_filter( 'plugin_row_meta', 'gftn_plugin_row_meta', 10, 2 );

function gftn_plugin_row_meta( $links, $file ) {
	if ( plugin_basename( GFTN_FILE ) !== $file ) {
		return $links;
	}

	// View Details (Thickbox).
	$links[] = sprintf(
		'<a href="%s" class="thickbox open-plugin-details-modal" aria-label="%s" data-title="%s">%s</a>',
		esc_url(
			admin_url(
				'plugin-install.php?tab=plugin-information&plugin=gf-tag-navigator'
				. '&TB_iframe=true&width=600&height=550'
			)
		),
		esc_attr__( 'More information about Gravity Forms Tag Navigator', 'gf-tag-navigator' ),
		esc_attr__( 'Gravity Forms Tag Navigator', 'gf-tag-navigator' ),
		esc_html__( 'View details', 'gf-tag-navigator' )
	);

	// Report a Bug.
	if ( class_exists( 'Guilamu_Bug_Reporter' ) ) {
		$links[] = sprintf(
			'<a href="#" class="guilamu-bug-report-btn" data-plugin-slug="gf-tag-navigator" data-plugin-name="%s">%s</a>',
			esc_attr__( 'Gravity Forms Tag Navigator', 'gf-tag-navigator' ),
			esc_html__( '🐛 Report a Bug', 'gf-tag-navigator' )
		);
	} else {
		$links[] = sprintf(
			'<a href="%s" target="_blank">%s</a>',
			'https://github.com/guilamu/guilamu-bug-reporter/releases',
			esc_html__( '🐛 Report a Bug (install Bug Reporter)', 'gf-tag-navigator' )
		);
	}

	return $links;
}
