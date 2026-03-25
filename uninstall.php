<?php
/**
 * Gravity Forms Tag Navigator — Uninstall
 *
 * Fired when the plugin is deleted through the WordPress admin.
 *
 * @package GFTagNavigator
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Remove the tag catalog.
delete_option( 'gftn_tag_catalog' );

// Remove the GitHub release cache transient.
delete_transient( 'gftn_github_release' );

// Remove per-form tag assignments from Gravity Forms form meta.
if ( class_exists( 'GFAPI' ) && class_exists( 'GFFormsModel' ) ) {
	$forms = GFAPI::get_forms();
	foreach ( $forms as $form ) {
		if ( isset( $form['gftn_tags'] ) ) {
			unset( $form['gftn_tags'] );
			GFFormsModel::update_form_meta( (int) $form['id'], $form );
		}
	}
}
