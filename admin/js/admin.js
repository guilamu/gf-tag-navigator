/**
 * Gravity Forms Tag Navigator — Admin JS
 *
 * Handles: filter bar relocation, inline popover, AJAX saves,
 *          catalog management on plugin settings.
 *
 * Globals: jQuery, gftnData
 */
(function ($) {
	'use strict';

	/* =================================================================
	   Helpers
	   ================================================================= */

	/**
	 * Compute contrast color (#ffffff or #1a1a1a) for a given hex background.
	 * Mirrors the PHP implementation in GFTagNavigatorCatalog::contrast_color().
	 */
	function contrastColor(hex) {
		hex = hex.replace(/^#/, '');
		if (hex.length === 3) {
			hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
		}
		var r = parseInt(hex.substring(0, 2), 16) / 255;
		var g = parseInt(hex.substring(2, 4), 16) / 255;
		var b = parseInt(hex.substring(4, 6), 16) / 255;

		r = r <= 0.03928 ? r / 12.92 : Math.pow((r + 0.055) / 1.055, 2.4);
		g = g <= 0.03928 ? g / 12.92 : Math.pow((g + 0.055) / 1.055, 2.4);
		b = b <= 0.03928 ? b / 12.92 : Math.pow((b + 0.055) / 1.055, 2.4);

		var luminance = 0.2126 * r + 0.7152 * g + 0.0722 * b;
		return luminance > 0.179 ? '#1a1a1a' : '#ffffff';
	}

	/**
	 * Build a pill HTML string from a tag object.
	 */
	function pillHtml(tag, extraClasses) {
		var classes = 'gftn-pill' + (extraClasses ? ' ' + extraClasses : '');
		var bg = tag.color + '1A'; // ~10% opacity for pastel tint
		return '<span class="' + classes + '" style="background:' + bg + ';color:' + tag.color + ';" data-slug="' + tag.slug + '">' +
			$('<span>').text(tag.name).html() +
			'</span>';
	}

	/**
	 * Find a tag object by slug in the local catalog cache.
	 */
	function tagBySlug(slug) {
		for (var i = 0; i < gftnData.tags.length; i++) {
			if (gftnData.tags[i].slug === slug) {
				return gftnData.tags[i];
			}
		}
		return null;
	}

	/* =================================================================
	   Filter bar — relocate from footer to above the form table
	   ================================================================= */

	function initFilterBar() {
		var $bar = $('#gftn-filter-bar');
		if (!$bar.length) {
			return;
		}
		// Place the filter bar after the .form-list-nav div (which contains status links + search).
		var $nav = $('.form-list-nav').first();
		if ($nav.length) {
			$bar.insertAfter($nav);
		} else {
			var $wrap = $('div.wrap, .gform-settings-panel__content').first();
			$wrap.find('table').first().before($bar);
		}
		$bar.show();

		// Live filter: click a pill to show/hide table rows instantly.
		$bar.on('click', 'a.gftn-pill', function (e) {
			e.preventDefault();
			var slug = $(this).data('slug') || '';

			// Update active pill.
			$bar.find('a.gftn-pill').removeClass('gftn-pill--active');
			if (slug) {
				$(this).addClass('gftn-pill--active');
			} else {
				$bar.find('a.gftn-pill--all').addClass('gftn-pill--active');
			}

			// Show/hide rows.
			$('.gftn-pills-wrapper').each(function () {
				var $row = $(this).closest('tr');
				if (!slug) {
					$row.show();
					return;
				}
				var tags = ($(this).data('tags') || '').toString().split(',');
				$row.toggle(tags.indexOf(slug) !== -1);
			});

			// Update URL without reload.
			var url = new URL(window.location);
			if (slug) {
				url.searchParams.set('gftn', slug);
			} else {
				url.searchParams.delete('gftn');
			}
			window.history.replaceState(null, '', url.toString());
		});

		// Apply initial filter if URL has gftn param on page load.
		var params = new URLSearchParams(window.location.search);
		var initialSlug = params.get('gftn');
		if (initialSlug) {
			$bar.find('a.gftn-pill[data-slug="' + initialSlug + '"]').trigger('click');
		}
	}

	/* =================================================================
	   Inline popover — forms list page
	   ================================================================= */

	var activePopover = null;
	var saveTimer = null;

	function closePopover() {
		if (activePopover) {
			activePopover.remove();
			activePopover = null;
		}
		if (saveTimer) {
			clearTimeout(saveTimer);
			saveTimer = null;
		}
	}

	function openPopover($button) {
		closePopover();

		var formId = $button.data('form-id');
		var $template = $('#gftn-popover-template');
		if (!$template.length) {
			return;
		}

		var $pop = $template.clone().removeAttr('id').addClass('gftn-popover--live');
		activePopover = $pop;

		// Pre-check assigned tags.
		var assigned = (gftnData.formTags && gftnData.formTags[String(formId)]) || [];
		$pop.find('input[type="checkbox"]').each(function () {
			if (assigned.indexOf($(this).val()) !== -1) {
				$(this).prop('checked', true);
			}
		});

		// Position near the button.
		var offset = $button.offset();
		$pop.css({
			position: 'absolute',
			top: offset.top + $button.outerHeight() + 4,
			left: offset.left
		}).appendTo('body').show();

		// Flip above if overflowing viewport.
		var popBottom = $pop.offset().top + $pop.outerHeight();
		if (popBottom > $(window).scrollTop() + $(window).height()) {
			$pop.css('top', offset.top - $pop.outerHeight() - 4);
		}

		// On checkbox change → debounce AJAX save.
		$pop.on('change', 'input[type="checkbox"]', function () {
			if (saveTimer) {
				clearTimeout(saveTimer);
			}
			saveTimer = setTimeout(function () {
				saveInlineTags(formId, $pop, $button);
			}, 400);
		});

		// Quick-create a new tag from the popover.
		$pop.on('click', '.gftn-quick-add', function () {
			var $input = $pop.find('.gftn-quick-name');
			var name = $input.val().trim();
			if (!name) { $input.focus(); return; }

			var $btn = $(this);
			$btn.prop('disabled', true);

			$.post(gftnData.ajaxUrl, {
				action: 'gftn_create_tag',
				nonce: gftnData.nonces.createTag,
				name: name
				// color omitted → server picks a random preset
			}, function (response) {
				$btn.prop('disabled', false);
				if (response.success) {
					// Update global catalog.
					gftnData.tags = response.data.catalog;
					var newTag = response.data.tag;

					// Add a checkbox for the new tag (pre-checked).
					var fg = contrastColor(newTag.color);
					var $label = $('<label class="gftn-pill gftn-pill--checkbox" style="background:' + newTag.color + ';color:' + fg + ';">' +
						'<input type="checkbox" value="' + newTag.slug + '" checked /> ' +
						$('<span>').text(newTag.name).html() +
						'</label>');
					$pop.find('.gftn-quick-create').before($label);
					$pop.find('.gftn-empty').remove();
					$input.val('');

					// Save immediately so the new tag is assigned.
					if (saveTimer) { clearTimeout(saveTimer); }
					saveTimer = setTimeout(function () {
						saveInlineTags(formId, $pop, $button);
					}, 100);
				} else {
					alert(response.data && response.data.message ? response.data.message : 'Error creating tag.');
				}
			}).fail(function () {
				$btn.prop('disabled', false);
				alert('Request failed.');
			});
		});

		// Allow Enter key in the quick-create input.
		$pop.on('keydown', '.gftn-quick-name', function (e) {
			if (e.which === 13) {
				e.preventDefault();
				$pop.find('.gftn-quick-add').trigger('click');
			}
		});
	}

	function saveInlineTags(formId, $pop, $button) {
		var selected = [];
		$pop.find('input[type="checkbox"]:checked').each(function () {
			selected.push($(this).val());
		});

		var $wrapper = $button.siblings('.gftn-pills-wrapper');
		$wrapper.addClass('gftn-saving');

		$.post(gftnData.ajaxUrl, {
			action: 'gftn_save_form_tags',
			nonce: gftnData.nonces.saveFormTags,
			form_id: formId,
			tags: selected
		}, function (response) {
			$wrapper.removeClass('gftn-saving');
			if (response.success) {
				$wrapper.html(response.data.pillsHtml);
				// Update local cache.
				if (gftnData.formTags) {
					gftnData.formTags[String(formId)] = response.data.tags;
				}
			}
		}).fail(function () {
			$wrapper.removeClass('gftn-saving');
		});
	}

	/* =================================================================
	   Plugin settings — catalog management
	   ================================================================= */

	function initCatalogManager() {
		var $manager = $('#gftn-catalog-manager');
		if (!$manager.length) {
			return;
		}

		var $table    = $manager.find('#gftn-tag-table tbody');
		var $form     = $manager.find('#gftn-tag-form');
		var $editId   = $form.find('#gftn-edit-id');
		var $nameInput = $form.find('#gftn-tag-name-input');
		var $colorInput = $form.find('#gftn-tag-color-input');
		var $saveBtn  = $form.find('#gftn-save-tag');

		var $title    = $form.find('#gftn-form-title');

		// Swatch selection.
		$form.on('click', '.gftn-color-swatch', function () {
			$form.find('.gftn-color-swatch').removeClass('gftn-color-swatch--selected');
			$(this).addClass('gftn-color-swatch--selected');
			$colorInput.val($(this).data('color'));
		});

		// Reset form to "add" state.
		function resetForm() {
			$editId.val('');
			$nameInput.val('');
			$colorInput.val('');
			$form.find('.gftn-color-swatch').removeClass('gftn-color-swatch--selected');
			$saveBtn.text($saveBtn.data('add-label') || 'Add Tag');
			$('#gftn-delete-tag-btn').hide();

			$title.text($title.data('add-title') || 'Add New Tag');
		}

		// Store labels.
		$saveBtn.data('add-label', $saveBtn.text());
		$title.data('add-title', $title.text());

		// Rebuild the catalog table after an AJAX response.
		function rebuildTable(catalog) {
			gftnData.tags = catalog;
			$table.empty();

			if (!catalog.length) {
				$table.append('<tr class="gftn-empty-state"><td colspan="4"><em>' +
					'No tags yet. Create your first tag below.' +
					'</em></td></tr>');
				return;
			}

			$.each(catalog, function (_, tag) {
				var $row = $(
					'<tr data-tag-id="' + tag.id + '" data-tag-slug="' + tag.slug + '">' +
						'<td><span class="gftn-color-swatch" style="background:' + tag.color + ';"></span></td>' +
						'<td class="gftn-tag-name">' + $('<span>').text(tag.name).html() + '</td>' +
						'<td class="gftn-tag-slug"><code>' + $('<span>').text(tag.slug).html() + '</code></td>' +
						'<td class="gftn-tag-usage">—</td>' +

					'</tr>'
				);
				$table.append($row);
			});
		}

		// Save (create or update).
		$saveBtn.on('click', function () {
			var id   = $editId.val();
			var name = $nameInput.val().trim();
			var color = $colorInput.val();

			if (!name) {
				alert('Please enter a name.');
				return;
			}

			// Color is required for edits, optional for new tags (server auto-assigns).
			if (id && !color) {
				alert('Please select a color.');
				return;
			}

			var isEdit = !!id;
			var action = isEdit ? 'gftn_update_tag' : 'gftn_create_tag';
			var nonce  = isEdit ? gftnData.nonces.updateTag : gftnData.nonces.createTag;

			var postData = {
				action: action,
				nonce: nonce,
				name: name
			};
			if (color) {
				postData.color = color;
			}
			if (isEdit) {
				postData.id = id;
			}

			$saveBtn.prop('disabled', true);

			$.post(gftnData.ajaxUrl, postData, function (response) {
				$saveBtn.prop('disabled', false);
				if (response.success) {
					rebuildTable(response.data.catalog);
					resetForm();
				} else {
					alert(response.data && response.data.message ? response.data.message : 'Error saving tag.');
				}
			}).fail(function () {
				$saveBtn.prop('disabled', false);
				alert('Request failed.');
			});
		});

		// Row click → enter edit mode.
		$table.on('click', 'tr[data-tag-id]', function () {
			var tagId = $(this).data('tag-id');
			var tag = null;
			for (var i = 0; i < gftnData.tags.length; i++) {
				if (gftnData.tags[i].id === tagId) {
					tag = gftnData.tags[i];
					break;
				}
			}
			if (!tag) return;

			$editId.val(tag.id);
			$nameInput.val(tag.name);
			$colorInput.val(tag.color);

			// Select the matching swatch.
			$form.find('.gftn-color-swatch').removeClass('gftn-color-swatch--selected');
			$form.find('.gftn-color-swatch[data-color="' + tag.color + '"]').addClass('gftn-color-swatch--selected');

			$saveBtn.text('Update Tag');
			$title.text('Edit Tag');
			$('#gftn-delete-tag-btn').show();
			$nameInput.focus();
		});

		// Delete button in edit form.
		$('#gftn-delete-tag-btn').on('click', function () {
			var tagId = $editId.val();
			if (!tagId) return;
			var tag = null;
			for (var i = 0; i < gftnData.tags.length; i++) {
				if (gftnData.tags[i].id === tagId) {
					tag = gftnData.tags[i];
					break;
				}
			}
			if (!tag) return;

			if (!confirm('Delete tag "' + tag.name + '"? It will be removed from all forms.')) {
				return;
			}

			$.post(gftnData.ajaxUrl, {
				action: 'gftn_delete_tag',
				nonce: gftnData.nonces.deleteTag,
				id: tagId
			}, function (response) {
				if (response.success) {
					rebuildTable(response.data.catalog);
					resetForm();
				} else {
					alert(response.data && response.data.message ? response.data.message : 'Error deleting tag.');
				}
			}).fail(function () {
				alert('Request failed.');
			});
		});
	}

	/* =================================================================
	   Init on document ready
	   ================================================================= */

	$(function () {
		// Forms list page.
		initFilterBar();

		// Inline popover toggle.
		$(document).on('click', '.gftn-edit-tags', function (e) {
			e.stopPropagation();
			var $btn = $(this);
			// Toggle: if popover is already open for this form, close it.
			if (activePopover && activePopover.data('formId') === $btn.data('form-id')) {
				closePopover();
			} else {
				openPopover($btn);
				if (activePopover) {
					activePopover.data('formId', $btn.data('form-id'));
				}
			}
		});

		// Close popover when clicking outside.
		$(document).on('click', function (e) {
			if (activePopover && !$(e.target).closest('.gftn-popover--live, .gftn-edit-tags').length) {
				closePopover();
			}
		});

		// Plugin settings page — catalog manager.
		initCatalogManager();
	});

})(jQuery);
