/**
 * Admin script for managing exclusions.
 *
 * Implements async search while typing (from 2 characters), add/remove
 * excluded posts, and syncs sections when post type checkboxes toggle.
 */
(function () {
	'use strict';

	/**
	 * Configuration passed from PHP via wp_localize_script.
	 *
	 * @type {{ajaxUrl?: string, nonce?: string, minChars?: number, i18n?: Record<string,string>}}
	 */
	const cfg = window.LLMF_ADMIN || {};
	const ajaxUrl = cfg.ajaxUrl || '';
	const nonce = cfg.nonce || '';
	const minChars = Number(cfg.minChars || 2);
	const i18n = cfg.i18n || {};

	const root = document.getElementById('llmf-excluded-posts');
	if (!root || !ajaxUrl || !nonce) {
		return;
	}

	/**
	 * Return a localized string with a fallback.
	 *
	 * @param {string} key Localization key.
	 * @param {string} fallback Fallback text.
	 *
	 * @returns {string}
	 */
	const t = (key, fallback) => {
		return typeof i18n[key] === 'string' && i18n[key] ? i18n[key] : fallback;
	};

	/**
	 * Debounce map by post type to avoid sending a request per keystroke.
	 */
	const debounceMap = new Map();

	/**
	 * Simple helper for querying a single element.
	 *
	 * @param {string} selector CSS selector.
	 * @param {Element|Document} [ctx] Search context.
	 *
	 * @returns {Element|null}
	 */
	const qs = (selector, ctx) => (ctx || document).querySelector(selector);

	/**
	 * Simple helper for querying a list of elements.
	 *
	 * @param {string} selector CSS selector.
	 * @param {Element|Document} [ctx] Search context.
	 *
	 * @returns {Element[]}
	 */
	const qsa = (selector, ctx) => Array.from((ctx || document).querySelectorAll(selector));

	/**
	 * Hide the suggestions dropdown.
	 *
	 * @param {Element} dropdown Dropdown element.
	 *
	 * @returns {void}
	 */
	const hideDropdown = (dropdown) => {
		if (dropdown) {
			dropdown.style.display = 'none';
			dropdown.innerHTML = '';
		}
	};

	/**
	 * Show an informational message in the dropdown.
	 *
	 * @param {Element} dropdown Dropdown element.
	 * @param {string} message Message text.
	 *
	 * @returns {void}
	 */
	const showDropdownMessage = (dropdown, message) => {
		if (!dropdown) return;
		dropdown.innerHTML = `<div class="llmf-excluded-posts__dropdown-item">${message}</div>`;
		dropdown.style.display = 'block';
	};

	/**
	 * Return a set of already selected IDs for a post type.
	 *
	 * @param {string} postType Current post type.
	 *
	 * @returns {Set<string>}
	 */
	const selectedIds = (postType) => {
		const container = root.querySelector(`.llmf-excluded-posts__selected[data-post-type="${postType}"]`);
		const ids = new Set();

		if (!container) {
			return ids;
		}

		qsa('.llmf-excluded-posts__checkbox', container).forEach((input) => {
			if (input instanceof HTMLInputElement && input.value) {
				ids.add(String(input.value));
			}
		});

		return ids;
	};

	/**
	 * Remove the "empty" notice within the list container.
	 *
	 * @param {Element} container Selected items container.
	 *
	 * @returns {void}
	 */
	const removeEmptyNotice = (container) => {
		const empty = qs('.llmf-excluded-posts__empty', container);
		if (empty) {
			empty.remove();
		}
	};

	/**
	 * Add a post to the exclusion list in the DOM.
	 *
	 * @param {string} postType Post type.
	 * @param {number} id Post ID.
	 * @param {string} title Post title.
	 *
	 * @returns {void}
	 */
	const appendSelectedItem = (postType, id, title) => {
		const container = root.querySelector(`.llmf-excluded-posts__selected[data-post-type="${postType}"]`);
		if (!container) return;

		const idStr = String(id);
		const ids = selectedIds(postType);
		if (ids.has(idStr)) {
			return;
		}

		removeEmptyNotice(container);

		const wrapper = document.createElement('div');
		wrapper.className = 'llmf-excluded-posts__selected-item';
		wrapper.dataset.postId = idStr;

		const label = document.createElement('label');
		label.className = 'llmf-excluded-posts__selected-label';
		label.style.flex = '1';

		const checkbox = document.createElement('input');
		checkbox.type = 'checkbox';
		checkbox.className = 'llmf-excluded-posts__checkbox';
		checkbox.name = `llmf_options[excluded_posts][${postType}][]`;
		checkbox.value = idStr;
		checkbox.checked = true;

		label.appendChild(checkbox);
		label.insertAdjacentText('beforeend', ` ${title} `);

		const meta = document.createElement('span');
		meta.className = 'description';
		meta.textContent = `(#${idStr})`;
		label.appendChild(meta);

		const removeBtn = document.createElement('button');
		removeBtn.type = 'button';
		removeBtn.className = 'button-link llmf-excluded-posts__remove';
		removeBtn.setAttribute('aria-label', t('removeAction', 'Remove from exclusions'));
		removeBtn.textContent = '×';

		wrapper.appendChild(label);
		wrapper.appendChild(removeBtn);

		container.appendChild(wrapper);
	};

	/**
	 * Render found posts in the dropdown.
	 *
	 * @param {Element} dropdown Dropdown element.
	 * @param {string} postType Current post type.
	 * @param {{id:number,title:string}[]} items Post list.
	 */
	const renderDropdown = (dropdown, postType, items) => {
		if (!dropdown) return;

		const existing = selectedIds(postType);
		const fragment = document.createDocumentFragment();

		items
			.filter((item) => item && typeof item.id === 'number' && item.id > 0 && item.title)
			.forEach((item) => {
				// Skip already selected posts to avoid duplicates.
				if (existing.has(String(item.id))) {
					return;
				}

				const row = document.createElement('div');
				row.className = 'llmf-excluded-posts__dropdown-item';
				row.dataset.postId = String(item.id);

				const text = document.createElement('span');
				text.textContent = item.title;

				const btn = document.createElement('button');
				btn.type = 'button';
				btn.className = 'button button-secondary';
				btn.textContent = t('addAction', 'Add to exclusions');
				btn.addEventListener('click', () => {
					appendSelectedItem(postType, item.id, item.title);
					hideDropdown(dropdown);
				});

				row.appendChild(text);
				row.appendChild(btn);
				fragment.appendChild(row);
			});

		if (!fragment.childNodes.length) {
			showDropdownMessage(dropdown, t('nothingFound', 'Nothing found for this query.'));
			return;
		}

		dropdown.innerHTML = '';
		dropdown.appendChild(fragment);
		dropdown.style.display = 'block';
	};

	/**
	 * Perform an AJAX search by post type.
	 *
	 * @param {HTMLInputElement} input Input that initiated the search.
	 *
	 * @returns {void}
	 */
	const performSearch = (input) => {
		if (!(input instanceof HTMLInputElement)) return;

		const postType = input.dataset.postType || '';
		const term = input.value.trim();
		const dropdown = root.querySelector(`.llmf-excluded-posts__dropdown[data-post-type="${postType}"]`);

		if (term.length < minChars) {
			showDropdownMessage(dropdown, t('typeMore', 'Enter at least 2 characters to search.'));
			return;
		}

		showDropdownMessage(dropdown, t('searching', 'Searching…'));

		const params = new URLSearchParams({
			action: 'llmf_search_posts',
			post_type: postType,
			q: term,
			nonce: nonce,
		});

		fetch(`${ajaxUrl}?${params.toString()}`, {
			credentials: 'same-origin',
		})
			.then((res) => res.json())
			.then((data) => {
				if (!data || !data.success || !data.data || !Array.isArray(data.data.items)) {
					showDropdownMessage(dropdown, t('searchError', 'Search failed, please try again.'));
					return;
				}
				renderDropdown(dropdown, postType, data.data.items);
			})
			.catch(() => {
				showDropdownMessage(dropdown, t('searchError', 'Search failed, please try again.'));
			});
	};

	/**
	 * Debounced input handler for the search field.
	 *
	 * @param {Event} event Input event.
	 *
	 * @returns {void}
	 */
	const handleSearchInput = (event) => {
		const input = event.currentTarget;
		if (!(input instanceof HTMLInputElement)) return;

		const postType = input.dataset.postType || '';
		const dropdown = root.querySelector(`.llmf-excluded-posts__dropdown[data-post-type="${postType}"]`);

		if (!postType) {
			return;
		}

		const existingTimer = debounceMap.get(postType);
		if (existingTimer) {
			clearTimeout(existingTimer);
		}

		// Start search 250ms after typing stops.
		const timer = setTimeout(() => performSearch(input), 250);
		debounceMap.set(postType, timer);

		if (input.value.trim().length < minChars) {
			showDropdownMessage(dropdown, t('typeMore', 'Enter at least 2 characters to search.'));
		}
	};

	/**
	 * Remove an exclusion item when clicking its button.
	 *
	 * @param {Element} item Selected item element.
	 *
	 * @returns {void}
	 */
	const removeSelectedItem = (item) => {
		if (!item || !item.parentElement) return;
		const container = item.parentElement;
		item.remove();

		// If the container is empty after removal, show the empty state text.
		if (!container.querySelector('.llmf-excluded-posts__selected-item')) {
			const p = document.createElement('p');
			p.className = 'description llmf-excluded-posts__empty';
			p.textContent = t('selectedEmpty', 'No items are excluded yet.');
			container.appendChild(p);
		}
	};

	/**
	 * Toggle search block visibility based on selected post types.
	 *
	 * @returns {void}
	 */
	const syncTypesVisibility = () => {
		const toggles = qsa('.llmf-post-type-toggle');
		const activeTypes = new Set(
			toggles
				.filter((cb) => cb instanceof HTMLInputElement && cb.checked && cb.dataset.postType)
				.map((cb) => cb.dataset.postType)
		);

		qsa('.llmf-excluded-posts__type', root).forEach((block) => {
			const type = block.dataset.postType;
			if (!type) return;

			if (activeTypes.has(type)) {
				block.classList.remove('llmf-excluded-posts__type--hidden');
				// Enable checkboxes so values submit on save.
				qsa('input.llmf-excluded-posts__checkbox', block).forEach((input) => {
					if (input instanceof HTMLInputElement) {
						input.disabled = false;
					}
				});
			} else {
				block.classList.add('llmf-excluded-posts__type--hidden');
				// Disable checkboxes for hidden types so exclusions are not submitted.
				qsa('input.llmf-excluded-posts__checkbox', block).forEach((input) => {
					if (input instanceof HTMLInputElement) {
						input.disabled = true;
					}
				});
				// Hide the dropdown if it was open.
				hideDropdown(qs('.llmf-excluded-posts__dropdown', block));
			}
		});
	};

	// Attach handlers to search fields.
	qsa('.llmf-excluded-posts__search-input', root).forEach((input) => {
		input.addEventListener('input', handleSearchInput);
		input.addEventListener('focus', handleSearchInput);
	});

	// Delegate clicks on remove buttons.
	root.addEventListener('click', (event) => {
		const target = event.target;
		if (target instanceof HTMLElement && target.classList.contains('llmf-excluded-posts__remove')) {
			const wrapper = target.closest('.llmf-excluded-posts__selected-item');
			removeSelectedItem(wrapper);
		}
	});

	// Close dropdowns when clicking outside.
	document.addEventListener('click', (event) => {
		const target = event.target;
		if (!(target instanceof Element)) return;

		// Ignore clicks inside the search block.
		if (target.closest('.llmf-excluded-posts__search')) {
			return;
		}

		qsa('.llmf-excluded-posts__dropdown', root).forEach((dropdown) => hideDropdown(dropdown));
	});

	// Toggle block visibility when post type checkboxes change.
	qsa('.llmf-post-type-toggle').forEach((cb) => {
		cb.addEventListener('change', syncTypesVisibility);
	});

	// Initial block visibility depends on selected types.
	syncTypesVisibility();
})();
