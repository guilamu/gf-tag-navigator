# Gravity Forms Tag Navigator

Add colored tags to Gravity Forms and filter your form list by tag — right inside the WordPress admin.

![Plugin Screenshot](https://github.com/guilamu/gf-tag-navigator/blob/main/screenshot.png)

## Tag Management
- Create, edit, and delete reusable tags from a central catalog
- Choose from 12 built-in color presets (Red, Orange, Yellow, Green, Teal, Blue, Indigo, Purple, Pink, Brown, Grey, Dark)
- Extend the color palette programmatically via the `gftn_color_presets` filter
- See at a glance how many forms use each tag

## Form Tagging
- Assign tags to any form from the **Form Settings → Tag Navigator** tab
- Edit tags inline directly from the forms list with the **⊕** button
- Quick-create new tags on the fly from the inline popover
- Tags are stored in Gravity Forms form meta and survive export/import

## Forms List Filtering
- Filter your form list by clicking a tag in the filter bar
- Live filtering: rows show/hide instantly without page reload
- One-click "All" button to reset the filter
- Pastel-colored pills and a filter bar integrated into the native Gravity Forms list

## Admin Bar Shortcuts
- Quick-access tag menu in the WordPress admin bar
- Jump to the forms list pre-filtered by any tag from anywhere in the admin

## Key Features
- **Multilingual:** Works with content in any language
- **Translation-Ready:** All strings are internationalized
- **Secure:** Nonce verification and capability checks on every AJAX request; all output escaped
- **GitHub Updates:** Automatic updates from GitHub releases via the built-in updater
- **Bug Reporting:** Integrated with Guilamu Bug Reporter for easy issue reporting

## Requirements
- WordPress 5.8 or higher
- PHP 7.4 or higher
- Gravity Forms 2.5 or higher

## Installation
1. Upload the `gf-tag-navigator` folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** menu in WordPress
3. Go to **Forms → Settings → Tag Navigator** and create your first tags
4. Assign tags to forms via **Form Settings → Tag Navigator** or inline from the forms list

## FAQ

### Do tags survive form export and import?
Tag assignments travel with the form because they are stored in Gravity Forms form meta. However, the tag catalog itself (names, colors) is site-level data and does not export. After importing, you can recreate matching tags and the imported slugs will reconnect automatically.

### What is the minimum Gravity Forms version?
Version 2.5 or higher is required. The plugin uses the GFAddOn framework which is available in all modern Gravity Forms releases.

### Can I edit tags inline from the forms list?
Yes. Click the **⊕** button next to any form's tags to open a popover where you can check or uncheck tags. You can also create new tags on the fly from the same popover. Changes are saved automatically via AJAX.

### Can I customize the available colors?
Yes, use the `gftn_color_presets` filter:
```php
add_filter( 'gftn_color_presets', function( $presets ) {
    $presets[] = '#ff6600'; // Add a custom orange.
    return $presets;
} );
```

### How does the filter bar work?
Click a tag pill in the filter bar to show only forms with that tag. The list updates instantly without reloading the page. Click the same tag again or "All" to reset.

### What happens when I delete a tag?
The tag is removed from the catalog and automatically stripped from every form that was using it.

## Project Structure
```
.
├── gf-tag-navigator.php             # Main bootstrap file + admin bar shortcuts
├── uninstall.php                    # Cleanup on uninstall
├── README.md
├── admin
│   ├── css
│   │   └── admin.css                # Pills, filter bar, popover, settings UI
│   └── js
│       └── admin.js                 # Live filtering, inline popover, catalog AJAX
├── includes
│   ├── class-gf-tag-navigator-addon.php  # Main GFAddOn class
│   ├── class-tag-catalog.php        # Tag catalog CRUD and validation
│   ├── class-form-list-ui.php       # Forms list column, filter, inline editor
│   └── class-github-updater.php     # GitHub auto-update support
└── languages
    ├── gf-tag-navigator-fr_FR.po    # French translation (source)
    └── gf-tag-navigator.pot         # Translation template
```

## Changelog

### 1.0.2
- Rewrite GitHub updater: README.md parsing via Parsedown, "View details" modal with description/installation/FAQ/changelog tabs, CSS injection for wp_kses-safe tables, Gravity Forms sidebar line
- Fix: Use self_admin_url() and correct modal dimensions (772×926) for the "View details" thickbox link

### 1.0.1
- Fix: Properly vertically align the inline "Edit tags" button next to form tags.

### 1.0.0
- Initial release
- Central tag catalog with 12 color presets
- Tag assignments via form settings and inline popover
- Quick-create tags from the inline popover
- Forms list tag column with pastel-colored pills
- Live filter bar with instant show/hide (no page reload)
- Admin bar tag menu for quick navigation
- GitHub auto-updater
- Guilamu Bug Reporter integration
- Uninstall cleanup

## License
This project is licensed under the GNU Affero General Public License v3.0 (AGPL-3.0) - see the [LICENSE](LICENSE) file for details.

---

<p align="center">
  Made with love for the WordPress community
</p>
