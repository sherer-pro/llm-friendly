/**
 * Admin script for the LLM Friendly settings page.
 *
 * Handles copy helpers, dirty-state save bar, read-only llms.txt preview,
 * and accessible exclusion search/list management.
 */
(function () {
	'use strict';

	const cfg = window.LLMF_ADMIN || {};
	const ajaxUrl = cfg.ajaxUrl || '';
	const nonce = cfg.nonce || '';
	const previewNonce = cfg.previewNonce || '';
	const minChars = Number(cfg.minChars || 2);
	const i18n = cfg.i18n || {};

	const qs = (selector, ctx) => (ctx || document).querySelector(selector);
	const qsa = (selector, ctx) => Array.from((ctx || document).querySelectorAll(selector));

	const t = (key, fallback) => {
		return typeof i18n[key] === 'string' && i18n[key] ? i18n[key] : fallback;
	};

	const format = (template, value) => {
		return String(template).replace('%s', value);
	};

	const idPart = (value) => {
		return String(value).replace(/[^a-zA-Z0-9_-]/g, '-');
	};

	const announce = (message, selector) => {
		const target = qs(selector || '#llmf-copy-status');
		if (target) {
			target.textContent = message;
		}
	};

	const copyText = (text) => {
		if (!text) {
			return Promise.reject(new Error('empty'));
		}

		if (navigator.clipboard && navigator.clipboard.writeText) {
			return navigator.clipboard.writeText(text);
		}

		const textarea = document.createElement('textarea');
		textarea.value = text;
		textarea.setAttribute('readonly', 'readonly');
		textarea.style.position = 'fixed';
		textarea.style.left = '-9999px';
		document.body.appendChild(textarea);
		textarea.select();
		let ok = false;
		try {
			ok = document.execCommand('copy');
		} finally {
			textarea.remove();
		}

		return ok ? Promise.resolve() : Promise.reject(new Error('copy'));
	};

	qsa('[data-llmf-copy]').forEach((button) => {
		button.addEventListener('click', () => {
			copyText(button.getAttribute('data-llmf-copy') || '')
				.then(() => announce(t('copySuccess', 'Copied to clipboard.')))
				.catch(() => announce(t('copyError', 'Copy failed. Select and copy manually.')));
		});
	});

	const form = qs('#llmf-settings-form');
	const saveBar = qs('[data-llmf-save-bar]');
	const saveBarMessage = saveBar ? qs('.llmf-save-bar__message', saveBar) : null;
	const previewDirty = qs('[data-llmf-preview-dirty]');
	const exclusionsDirty = qs('[data-llmf-exclusions-dirty]');
	let initialSnapshot = '';
	let isDirty = false;
	let isSubmitting = false;
	let saveFeedbackTimer = null;
	const saveButtons = form ? qsa('#llmf-save-settings, #llmf-save-settings-sticky', form) : [];
	const saveButtonLabels = new Map();

	saveButtons.forEach((button) => {
		saveButtonLabels.set(button, button.textContent);
	});

	const serializeForm = (targetForm) => {
		if (!targetForm) {
			return '';
		}

		return qsa('input, select, textarea', targetForm)
			.filter((field) => field.name && !field.disabled && !['submit', 'button', 'reset'].includes((field.type || '').toLowerCase()))
			.map((field) => {
				const type = (field.type || '').toLowerCase();
				const checked = type === 'checkbox' || type === 'radio' ? (field.checked ? '1' : '0') : '';
				return [
					field.name,
					field.id || '',
					type,
					checked,
					field.value || '',
				].join('=');
			})
			.sort()
			.join('&');
	};

	const resetSaveButtons = () => {
		saveButtons.forEach((button) => {
			button.disabled = false;
			button.textContent = saveButtonLabels.get(button) || button.textContent;
		});
	};

	const setSaveButtonsSaving = () => {
		saveButtons.forEach((button) => {
			button.disabled = true;
			button.textContent = t('savingSettings', 'Saving settings...');
		});
	};

	const setSaveBarState = (state, message) => {
		if (!saveBar) {
			return;
		}

		saveBar.classList.remove('is-saving', 'is-success', 'is-error');
		if (state) {
			saveBar.classList.add(`is-${state}`);
		}
		if (saveBarMessage) {
			saveBarMessage.textContent = message;
		}
	};

	const clearSaveFeedbackTimer = () => {
		if (saveFeedbackTimer) {
			window.clearTimeout(saveFeedbackTimer);
			saveFeedbackTimer = null;
		}
	};

	const hideDirtyMessages = () => {
		if (previewDirty) {
			previewDirty.hidden = true;
		}
		if (exclusionsDirty) {
			exclusionsDirty.hidden = true;
		}
	};

	const updateDirtyState = () => {
		if (!form || !initialSnapshot) {
			return;
		}

		isDirty = serializeForm(form) !== initialSnapshot;

		if (saveBar) {
			saveBar.hidden = !isDirty;
			if (isDirty) {
				clearSaveFeedbackTimer();
				setSaveBarState('', t('unsavedChanges', 'Unsaved changes'));
			}
		}
		if (previewDirty) {
			previewDirty.hidden = !isDirty;
		}
	};

	const markFormDirty = () => {
		if (!form) {
			return;
		}
		window.setTimeout(updateDirtyState, 0);
	};

	if (form) {
		initialSnapshot = serializeForm(form);
		form.addEventListener('input', updateDirtyState);
		form.addEventListener('change', updateDirtyState);
		form.addEventListener('submit', (event) => {
			if (!ajaxUrl || !window.fetch || !window.FormData) {
				isSubmitting = true;
				return;
			}

			event.preventDefault();

			if (isSubmitting) {
				return;
			}

			isSubmitting = true;
			clearSaveFeedbackTimer();
			setSaveButtonsSaving();
			if (saveBar) {
				saveBar.hidden = false;
				setSaveBarState('saving', t('savingSettings', 'Saving settings...'));
			}

			const payload = new FormData(form);
			payload.set('action', 'llmf_save_settings');

			fetch(ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: payload,
			})
				.then((response) => {
					return response.json()
						.catch(() => null)
						.then((data) => {
							if (!response.ok || !data || !data.success) {
								const message = data && data.data && data.data.message ? data.data.message : t('settingsSaveError', 'Settings could not be saved. Please try again.');
								throw new Error(message);
							}
							return data;
						});
				})
				.then((data) => {
					const message = data && data.data && data.data.message ? data.data.message : t('settingsSaved', 'Settings saved.');
					initialSnapshot = serializeForm(form);
					isDirty = false;
					hideDirtyMessages();
					if (saveBar) {
						saveBar.hidden = false;
						setSaveBarState('success', message);
						saveFeedbackTimer = window.setTimeout(() => {
							if (!isDirty && !isSubmitting && saveBar) {
								saveBar.hidden = true;
								setSaveBarState('', t('unsavedChanges', 'Unsaved changes'));
							}
						}, 1600);
					}
					announce(message);
				})
				.catch((error) => {
					const message = error && error.message ? error.message : t('settingsSaveError', 'Settings could not be saved. Please try again.');
					if (saveBar) {
						saveBar.hidden = false;
						setSaveBarState('error', message);
					}
					announce(message);
				})
				.finally(() => {
					isSubmitting = false;
					resetSaveButtons();
				});
		});
		window.addEventListener('beforeunload', (event) => {
			if (!isDirty || isSubmitting) {
				return;
			}
			event.preventDefault();
			event.returnValue = '';
		});

		const discard = qs('[data-llmf-discard]', form);
		if (discard) {
			discard.addEventListener('click', () => {
				isSubmitting = true;
				window.location.reload();
			});
		}
	}

	const updateMarkdownPattern = () => {
		const input = qs('#llmf-base-path');
		const preview = qs('[data-llmf-markdown-pattern]');
		if (!input || !preview) {
			return;
		}

		const baseUrl = preview.getAttribute('data-llmf-markdown-pattern') || '';
		const basePath = String(input.value || 'llm')
			.trim()
			.replace(/^\/+|\/+$/g, '')
			.replace(/[^a-zA-Z0-9/_-]/g, '-')
			.replace(/\/+/g, '/');
		const pattern = `${baseUrl}${basePath || 'llm'}/{post_type}/{path}.md`;
		preview.textContent = pattern;

		const copy = qs('#llmf-markdown-pattern-preview [data-llmf-copy]');
		if (copy) {
			copy.setAttribute('data-llmf-copy', pattern);
		}
	};

	const basePathInput = qs('#llmf-base-path');
	if (basePathInput) {
		basePathInput.addEventListener('input', updateMarkdownPattern);
		updateMarkdownPattern();
	}

	const previewRoot = qs('#llmf-preview');
	const previewContent = qs('[data-llmf-preview-content]', previewRoot);
	const previewMeta = qs('[data-llmf-preview-meta]', previewRoot);
	const previewLoad = qs('[data-llmf-preview-load]', previewRoot);
	const previewCopy = qs('[data-llmf-preview-copy]', previewRoot);
	let previewText = '';

	const cacheLabel = (status) => {
		if (status === 'cached') {
			return t('cacheCached', 'Cached');
		}
		if (status === 'needs_regeneration') {
			return t('cacheNeedsRegen', 'Needs regeneration');
		}
		if (status === 'disabled') {
			return t('cacheDisabled', 'Disabled');
		}
		return t('cacheNotCached', 'Not cached');
	};

	const setPreviewMeta = (items) => {
		if (!previewMeta) {
			return;
		}
		previewMeta.replaceChildren();
		items.forEach((item) => {
			const span = document.createElement('span');
			span.textContent = item;
			previewMeta.appendChild(span);
		});
	};

	const setPreviewState = (message, canCopy) => {
		if (previewContent) {
			previewContent.textContent = message;
		}
		if (previewCopy) {
			previewCopy.disabled = !canCopy;
		}
		announce(message, '[data-llmf-preview-status]');
	};

	const loadPreview = () => {
		if (!ajaxUrl || !previewNonce || !previewContent) {
			return;
		}

		previewText = '';
		setPreviewMeta([]);
		setPreviewState(t('previewLoading', 'Generating preview...'), false);

		const params = new URLSearchParams({
			action: 'llmf_preview_llms',
			nonce: previewNonce,
		});

		fetch(`${ajaxUrl}?${params.toString()}`, {
			credentials: 'same-origin',
		})
			.then((response) => response.json())
			.then((data) => {
				if (!data || !data.success || !data.data) {
					setPreviewState(t('previewError', 'Preview failed, please try again.'), false);
					return;
				}

				const result = data.data;
				previewText = result.content || '';

				if (!result.enabled) {
					setPreviewState(t('previewDisabled', 'llms.txt is disabled in saved settings.'), false);
				} else {
					setPreviewState(previewText || t('previewError', 'Preview failed, please try again.'), !!previewText);
				}

				const meta = [
					cacheLabel(result.cacheStatus || ''),
				];
				if (result.generatedAt) {
					meta.push(result.generatedAt);
				}
				if (result.contentHash) {
					meta.push(result.contentHash.slice(0, 12));
				}
				if (result.truncated) {
					meta.push(t('previewTruncated', 'Preview was truncated for display.'));
				}
				setPreviewMeta(meta);
				announce(t('previewReady', 'Preview generated.'), '[data-llmf-preview-status]');
			})
			.catch(() => {
				setPreviewState(t('previewError', 'Preview failed, please try again.'), false);
			});
	};

	if (previewLoad) {
		previewLoad.addEventListener('click', loadPreview);
	}
	if (previewCopy) {
		previewCopy.addEventListener('click', () => {
			copyText(previewText)
				.then(() => announce(t('copySuccess', 'Copied to clipboard.'), '[data-llmf-preview-status]'))
				.catch(() => announce(t('copyError', 'Copy failed. Select and copy manually.'), '[data-llmf-preview-status]'));
		});
	}

	const root = document.getElementById('llmf-excluded-posts');
	if (!root || !ajaxUrl || !nonce) {
		return;
	}

	const byPostType = (selector, postType) => {
		return qsa(selector, root).find((el) => el instanceof HTMLElement && el.dataset.postType === postType) || null;
	};

	const updateStatus = (postType, message) => {
		const block = byPostType('.llmf-excluded-posts__type', postType);
		const status = block ? qs('.llmf-excluded-posts__status', block) : null;
		if (status) {
			status.textContent = message;
		}
	};

	const setExclusionsDirty = () => {
		const dirty = qs('[data-llmf-exclusions-dirty]', root);
		if (dirty) {
			dirty.hidden = false;
		}
		markFormDirty();
	};

	const updateExcludedCount = (postType) => {
		const block = byPostType('.llmf-excluded-posts__type', postType);
		if (!block) {
			return;
		}
		const count = qsa('.llmf-excluded-posts__selected-item', block).length;
		const countNode = qs('[data-llmf-excluded-count]', block);
		if (countNode) {
			countNode.textContent = String(count);
		}
		const clear = qs('.llmf-excluded-posts__clear', block);
		if (clear) {
			clear.disabled = count === 0;
		}
	};

	const clearDropdown = (dropdown) => {
		if (dropdown) {
			dropdown.replaceChildren();
		}
	};

	const inputForDropdown = (dropdown) => {
		const postType = dropdown instanceof HTMLElement ? dropdown.dataset.postType || '' : '';
		const input = postType ? byPostType('.llmf-excluded-posts__search-input', postType) : null;
		return input instanceof HTMLInputElement ? input : null;
	};

	const showDropdown = (dropdown) => {
		if (!dropdown) return;

		dropdown.hidden = false;
		dropdown.classList.add('is-open');

		const input = inputForDropdown(dropdown);
		if (input) {
			input.setAttribute('aria-expanded', 'true');
		}
	};

	const debounceMap = new Map();

	const hideDropdown = (dropdown) => {
		if (dropdown) {
			dropdown.classList.remove('is-open');
			dropdown.hidden = true;
			clearDropdown(dropdown);

			const input = inputForDropdown(dropdown);
			if (input) {
				input.setAttribute('aria-expanded', 'false');
			}
		}
	};

	const showDropdownMessage = (dropdown, message) => {
		if (!dropdown) return;

		const row = document.createElement('div');
		row.className = 'llmf-excluded-posts__dropdown-item';
		row.setAttribute('role', 'listitem');
		row.textContent = message;

		clearDropdown(dropdown);
		dropdown.appendChild(row);
		showDropdown(dropdown);

		if (dropdown instanceof HTMLElement) {
			updateStatus(dropdown.dataset.postType || '', message);
		}
	};

	const selectedIds = (postType) => {
		const container = byPostType('.llmf-excluded-posts__selected', postType);
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

	const removeEmptyNotice = (container) => {
		const empty = qs('.llmf-excluded-posts__empty', container);
		if (empty) {
			empty.remove();
		}
	};

	const appendEmptyNotice = (container) => {
		if (qs('.llmf-excluded-posts__empty', container)) {
			return;
		}
		const p = document.createElement('p');
		p.className = 'description llmf-excluded-posts__empty';
		p.textContent = t('selectedEmpty', 'No items are excluded yet.');
		container.appendChild(p);
	};

	const appendSelectedItem = (postType, id, title) => {
		const container = byPostType('.llmf-excluded-posts__selected', postType);
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
		wrapper.dataset.title = title;

		const label = document.createElement('label');
		label.className = 'llmf-inline-checkbox llmf-excluded-posts__selected-label';
		label.setAttribute('for', `llmf-excluded-${idPart(postType)}-${idStr}`);

		const checkbox = document.createElement('input');
		checkbox.type = 'checkbox';
		checkbox.id = `llmf-excluded-${idPart(postType)}-${idStr}`;
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
		removeBtn.setAttribute('aria-label', format(t('removeItemAction', 'Remove "%s" from exclusions'), title));

		const removeIcon = document.createElement('span');
		removeIcon.className = 'dashicons dashicons-trash';
		removeIcon.setAttribute('aria-hidden', 'true');
		removeBtn.appendChild(removeIcon);

		wrapper.appendChild(label);
		wrapper.appendChild(removeBtn);

		container.appendChild(wrapper);
		updateExcludedCount(postType);
		updateStatus(postType, format(t('itemAdded', 'Added "%s" to exclusions.'), title));
		setExclusionsDirty();
	};

	const renderDropdown = (dropdown, postType, items) => {
		if (!dropdown) return;

		const existing = selectedIds(postType);
		const fragment = document.createDocumentFragment();

		items
			.filter((item) => item && typeof item.id === 'number' && item.id > 0 && item.title)
			.forEach((item) => {
				if (existing.has(String(item.id))) {
					return;
				}

				const row = document.createElement('div');
				row.className = 'llmf-excluded-posts__dropdown-item';
				row.dataset.postId = String(item.id);
				row.setAttribute('role', 'listitem');

				const text = document.createElement('span');
				text.textContent = item.title;

				const btn = document.createElement('button');
				btn.type = 'button';
				btn.className = 'button button-secondary';
				btn.textContent = t('addAction', 'Add to exclusions');
				btn.setAttribute('aria-label', format(t('addItemAction', 'Add "%s" to exclusions'), item.title));
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

		clearDropdown(dropdown);
		dropdown.appendChild(fragment);
		showDropdown(dropdown);
		updateStatus(postType, t('resultsUpdated', 'Search results updated.'));
	};

	const performSearch = (input) => {
		if (!(input instanceof HTMLInputElement)) return;

		const postType = input.dataset.postType || '';
		const term = input.value.trim();
		const dropdown = byPostType('.llmf-excluded-posts__dropdown', postType);

		if (term.length < minChars) {
			hideDropdown(dropdown);
			return;
		}

		showDropdownMessage(dropdown, t('searching', 'Searching...'));

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

	const handleSearchInput = (event) => {
		const input = event.currentTarget;
		if (!(input instanceof HTMLInputElement)) return;

		const postType = input.dataset.postType || '';
		const dropdown = byPostType('.llmf-excluded-posts__dropdown', postType);

		if (!postType) {
			return;
		}

		const existingTimer = debounceMap.get(postType);
		if (existingTimer) {
			clearTimeout(existingTimer);
		}

		const timer = setTimeout(() => performSearch(input), 250);
		debounceMap.set(postType, timer);

		if (input.value.trim().length < minChars) {
			hideDropdown(dropdown);
		}
	};

	const removeSelectedItem = (item) => {
		if (!item || !item.parentElement) return;
		const container = item.parentElement;
		const postType = container instanceof HTMLElement ? container.dataset.postType || '' : '';
		const title = item instanceof HTMLElement && item.dataset.title ? item.dataset.title : '';
		item.remove();

		if (!container.querySelector('.llmf-excluded-posts__selected-item')) {
			appendEmptyNotice(container);
		}

		if (postType) {
			updateExcludedCount(postType);
			setExclusionsDirty();
		}
		if (postType && title) {
			updateStatus(postType, format(t('itemRemoved', 'Removed "%s" from exclusions.'), title));
		}
	};

	const clearSelectedItems = (postType) => {
		const container = byPostType('.llmf-excluded-posts__selected', postType);
		if (!container) return;
		qsa('.llmf-excluded-posts__selected-item', container).forEach((item) => item.remove());
		appendEmptyNotice(container);
		updateExcludedCount(postType);
		updateStatus(postType, t('exclusionsChanged', 'Exclusions changed. Save settings to apply.'));
		setExclusionsDirty();
	};

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
				qsa('input.llmf-excluded-posts__checkbox', block).forEach((input) => {
					if (input instanceof HTMLInputElement) {
						input.disabled = false;
					}
				});
			} else {
				block.classList.add('llmf-excluded-posts__type--hidden');
				qsa('input.llmf-excluded-posts__checkbox', block).forEach((input) => {
					if (input instanceof HTMLInputElement) {
						input.disabled = true;
					}
				});
				hideDropdown(qs('.llmf-excluded-posts__dropdown', block));
			}
		});
	};

	const focusDropdownButton = (dropdown, direction) => {
		if (!dropdown || dropdown.hidden) return;

		const buttons = qsa('button', dropdown).filter((button) => button instanceof HTMLButtonElement);
		if (!buttons.length) return;

		const current = document.activeElement;
		const index = buttons.indexOf(current);
		const nextIndex = index === -1 ? 0 : (index + direction + buttons.length) % buttons.length;
		buttons[nextIndex].focus();
	};

	const handleSearchKeydown = (event) => {
		const input = event.currentTarget;
		if (!(input instanceof HTMLInputElement)) return;

		const dropdown = byPostType('.llmf-excluded-posts__dropdown', input.dataset.postType || '');
		if (!dropdown) return;

		if (event.key === 'Escape') {
			hideDropdown(dropdown);
			return;
		}

		if (event.key === 'ArrowDown' && !dropdown.hidden) {
			event.preventDefault();
			focusDropdownButton(dropdown, 1);
		}
	};

	const handleDropdownKeydown = (event) => {
		const dropdown = event.currentTarget;
		if (!(dropdown instanceof HTMLElement)) return;

		if (event.key === 'Escape') {
			const input = inputForDropdown(dropdown);
			hideDropdown(dropdown);
			if (input) {
				input.focus();
			}
			return;
		}

		if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
			event.preventDefault();
			focusDropdownButton(dropdown, event.key === 'ArrowDown' ? 1 : -1);
		}
	};

	qsa('.llmf-excluded-posts__search-input', root).forEach((input) => {
		input.addEventListener('input', handleSearchInput);
		input.addEventListener('focus', handleSearchInput);
		input.addEventListener('keydown', handleSearchKeydown);
	});

	qsa('.llmf-excluded-posts__dropdown', root).forEach((dropdown) => {
		dropdown.addEventListener('keydown', handleDropdownKeydown);
	});

	root.addEventListener('click', (event) => {
		const target = event.target;
		const remove = target instanceof HTMLElement ? target.closest('.llmf-excluded-posts__remove') : null;
		if (remove) {
			removeSelectedItem(remove.closest('.llmf-excluded-posts__selected-item'));
			return;
		}

		const clear = target instanceof HTMLElement ? target.closest('.llmf-excluded-posts__clear') : null;
		if (clear instanceof HTMLElement && clear.dataset.postType) {
			clearSelectedItems(clear.dataset.postType);
		}
	});

	document.addEventListener('click', (event) => {
		const target = event.target;
		if (!(target instanceof Element)) return;

		if (target.closest('.llmf-excluded-posts__search')) {
			return;
		}

		qsa('.llmf-excluded-posts__dropdown', root).forEach((dropdown) => hideDropdown(dropdown));
	});

	qsa('.llmf-post-type-toggle').forEach((cb) => {
		cb.addEventListener('change', () => {
			syncTypesVisibility();
			markFormDirty();
		});
	});

	qsa('.llmf-excluded-posts__type', root).forEach((block) => {
		if (block instanceof HTMLElement && block.dataset.postType) {
			updateExcludedCount(block.dataset.postType);
		}
	});
	syncTypesVisibility();
})();
