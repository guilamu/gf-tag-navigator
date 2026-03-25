<?php
/**
 * Tag catalog — CRUD and validation for the central tag catalog.
 *
 * @package GFTagNavigator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GFTagNavigatorCatalog {

	const OPTION_KEY = 'gftn_tag_catalog';

	/**
	 * 12 preset colors.
	 */
	private static function default_presets(): array {
		return array(
			'#e74c3c', // Red
			'#e67e22', // Orange
			'#f1c40f', // Yellow
			'#27ae60', // Green
			'#1abc9c', // Teal
			'#2980b9', // Blue
			'#4a47a3', // Indigo
			'#8e44ad', // Purple
			'#e91e8c', // Pink
			'#795548', // Brown
			'#7f8c8d', // Grey
			'#2c3e50', // Dark
		);
	}

	/**
	 * Return the filterable list of allowed color hex values.
	 */
	public static function get_color_presets(): array {
		return apply_filters( 'gftn_color_presets', self::default_presets() );
	}

	/**
	 * Return the full catalog sorted alphabetically by name.
	 */
	public static function get_all(): array {
		$catalog = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $catalog ) ) {
			$catalog = array();
		}
		usort( $catalog, function ( $a, $b ) {
			return strcasecmp( $a['name'] ?? '', $b['name'] ?? '' );
		} );
		return $catalog;
	}

	public static function get_by_id( string $id ): ?array {
		foreach ( self::get_all() as $tag ) {
			if ( $tag['id'] === $id ) {
				return $tag;
			}
		}
		return null;
	}

	public static function get_by_slug( string $slug ): ?array {
		foreach ( self::get_all() as $tag ) {
			if ( $tag['slug'] === $slug ) {
				return $tag;
			}
		}
		return null;
	}

	/**
	 * Create a new tag. Returns the created tag array or WP_Error.
	 */
	public static function create( string $name, string $color = '' ) {
		$name = sanitize_text_field( trim( $name ) );
		if ( '' === $name ) {
			return new WP_Error( 'empty_name', __( 'Tag name cannot be empty.', 'gf-tag-navigator' ) );
		}

		// Auto-assign a random available color when none supplied.
		if ( '' === $color ) {
			$color = self::pick_random_color();
			if ( is_wp_error( $color ) ) {
				return $color;
			}
		} else {
			$color = self::validate_color( $color );
			if ( is_wp_error( $color ) ) {
				return $color;
			}
		}

		$slug = self::generate_slug( $name );
		if ( is_wp_error( $slug ) ) {
			return $slug;
		}

		$tag = array(
			'id'    => wp_generate_uuid4(),
			'name'  => $name,
			'slug'  => $slug,
			'color' => $color,
		);

		$catalog   = self::get_all();
		$catalog[] = $tag;
		self::save( $catalog );

		do_action( 'gftn_tag_created', $tag );

		return $tag;
	}

	/**
	 * Update an existing tag's name and/or color.
	 */
	public static function update( string $id, string $name, string $color ) {
		$name = sanitize_text_field( trim( $name ) );
		if ( '' === $name ) {
			return new WP_Error( 'empty_name', __( 'Tag name cannot be empty.', 'gf-tag-navigator' ) );
		}

		$color = self::validate_color( $color );
		if ( is_wp_error( $color ) ) {
			return $color;
		}

		$catalog = self::get_all();
		$found   = false;

		foreach ( $catalog as &$tag ) {
			if ( $tag['id'] === $id ) {
				$tag['name']  = $name;
				$tag['color'] = $color;
				$found        = true;
				break;
			}
		}
		unset( $tag );

		if ( ! $found ) {
			return new WP_Error( 'not_found', __( 'Tag not found.', 'gf-tag-navigator' ) );
		}

		self::save( $catalog );

		do_action( 'gftn_tag_updated', self::get_by_id( $id ) );

		return true;
	}

	/**
	 * Delete a tag and strip it from every form's meta.
	 */
	public static function delete( string $id ) {
		$catalog = self::get_all();
		$target  = null;

		foreach ( $catalog as $index => $tag ) {
			if ( $tag['id'] === $id ) {
				$target = $tag;
				array_splice( $catalog, $index, 1 );
				break;
			}
		}

		if ( null === $target ) {
			return new WP_Error( 'not_found', __( 'Tag not found.', 'gf-tag-navigator' ) );
		}

		self::save( $catalog );

		// Remove slug from every form that carries it.
		self::strip_slug_from_all_forms( $target['slug'] );

		do_action( 'gftn_tag_deleted', $target );

		return true;
	}

	/**
	 * Persist the full catalog array to wp_options.
	 */
	public static function save( array $catalog ): bool {
		return update_option( self::OPTION_KEY, $catalog, false );
	}

	/**
	 * Generate a unique slug from a name. Returns WP_Error if slug collides.
	 */
	public static function generate_slug( string $name ) {
		$slug = sanitize_title( $name );
		if ( '' === $slug ) {
			return new WP_Error( 'invalid_slug', __( 'Could not generate a valid slug from this name.', 'gf-tag-navigator' ) );
		}

		if ( null !== self::get_by_slug( $slug ) ) {
			return new WP_Error( 'duplicate_slug', __( 'A tag with this name already exists. Choose a different name.', 'gf-tag-navigator' ) );
		}

		return $slug;
	}

	/**
	 * Validate a color hex value against the presets.
	 */
	public static function validate_color( string $color ) {
		$color = sanitize_hex_color( $color );
		if ( ! $color || ! in_array( $color, self::get_color_presets(), true ) ) {
			return new WP_Error( 'invalid_color', __( 'Invalid color value.', 'gf-tag-navigator' ) );
		}
		return $color;
	}

	/**
	 * Pick a random color preset, preferring ones not yet used in the catalog.
	 */
	public static function pick_random_color() {
		$presets = self::get_color_presets();
		if ( empty( $presets ) ) {
			return new WP_Error( 'no_colors', __( 'No color presets available.', 'gf-tag-navigator' ) );
		}

		$used = array_column( self::get_all(), 'color' );
		$unused = array_values( array_diff( $presets, $used ) );

		if ( ! empty( $unused ) ) {
			return $unused[ array_rand( $unused ) ];
		}

		// All presets used — pick any preset at random.
		return $presets[ array_rand( $presets ) ];
	}

	/**
	 * Return all form IDs that carry a given tag slug.
	 */
	public static function get_forms_for_tag( string $slug ): array {
		$forms    = GFAPI::get_forms();
		$form_ids = array();

		foreach ( $forms as $form ) {
			$tags = self::get_form_tags( $form );
			if ( in_array( $slug, $tags, true ) ) {
				$form_ids[] = (int) $form['id'];
			}
		}

		return $form_ids;
	}

	/**
	 * Return the usage count for a tag slug.
	 */
	public static function get_usage_count( string $slug ): int {
		return count( self::get_forms_for_tag( $slug ) );
	}

	/**
	 * Read the gftn_tags meta for a single form.
	 */
	public static function get_form_tags( $form ): array {
		$form = (array) $form;
		$tags = $form['gftn_tags'] ?? array();
		if ( ! is_array( $tags ) ) {
			return array();
		}
		return array_values( array_unique( array_filter( array_map( 'sanitize_title', $tags ) ) ) );
	}

	/**
	 * Save tag slugs to a form's meta.
	 */
	public static function save_form_tags( int $form_id, array $slugs ): void {
		$catalog_slugs = array_column( self::get_all(), 'slug' );
		$clean         = array();

		foreach ( $slugs as $slug ) {
			$slug = sanitize_title( $slug );
			if ( in_array( $slug, $catalog_slugs, true ) ) {
				$clean[] = $slug;
			}
		}

		$clean = array_values( array_unique( $clean ) );

		// Store inside the form's display_meta so it survives get_forms() and export/import.
		$form = GFAPI::get_form( $form_id );
		if ( ! $form ) {
			return;
		}
		$form['gftn_tags'] = $clean;
		GFFormsModel::update_form_meta( $form_id, $form );

		do_action( 'gftn_form_tags_saved', $form_id, $clean );
	}

	/**
	 * Remove a deleted slug from all active forms.
	 */
	private static function strip_slug_from_all_forms( string $slug ): void {
		$forms = GFAPI::get_forms();

		foreach ( $forms as $form ) {
			$tags = self::get_form_tags( $form );
			if ( ! in_array( $slug, $tags, true ) ) {
				continue;
			}
			$tags = array_values( array_diff( $tags, array( $slug ) ) );
			$full_form = GFAPI::get_form( (int) $form['id'] );
			if ( $full_form ) {
				$full_form['gftn_tags'] = $tags;
				GFFormsModel::update_form_meta( (int) $form['id'], $full_form );
			}
		}
	}

	/**
	 * Compute contrasting text color for a given hex background.
	 * Returns '#ffffff' or '#1a1a1a'.
	 */
	public static function contrast_color( string $hex ): string {
		$hex = ltrim( $hex, '#' );
		if ( strlen( $hex ) === 3 ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		$r = hexdec( substr( $hex, 0, 2 ) ) / 255;
		$g = hexdec( substr( $hex, 2, 2 ) ) / 255;
		$b = hexdec( substr( $hex, 4, 2 ) ) / 255;

		// sRGB relative luminance.
		$r = $r <= 0.03928 ? $r / 12.92 : pow( ( $r + 0.055 ) / 1.055, 2.4 );
		$g = $g <= 0.03928 ? $g / 12.92 : pow( ( $g + 0.055 ) / 1.055, 2.4 );
		$b = $b <= 0.03928 ? $b / 12.92 : pow( ( $b + 0.055 ) / 1.055, 2.4 );

		$luminance = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;

		return $luminance > 0.179 ? '#1a1a1a' : '#ffffff';
	}

	/**
	 * Render a single pill span for a tag.
	 */
	public static function render_pill( array $tag, array $extra_classes = array() ): string {
		$classes = array_merge( array( 'gftn-pill' ), $extra_classes );
		$bg     = $tag['color'] . '1A'; // ~10% opacity hex-alpha for pastel tint.
		return sprintf(
			'<span class="%s" style="background:%s;color:%s;" data-slug="%s">%s</span>',
			esc_attr( implode( ' ', $classes ) ),
			esc_attr( $bg ),
			esc_attr( $tag['color'] ),
			esc_attr( $tag['slug'] ),
			esc_html( $tag['name'] )
		);
	}
}
