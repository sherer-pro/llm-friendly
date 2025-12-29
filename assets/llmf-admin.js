/**
 * Админский скрипт управления исключениями.
 *
 * Реализует асинхронный поиск по мере ввода (от 2 символов), добавление
 * и удаление исключенных записей, а также синхронизацию блоков при
 * переключении чекбоксов типов записей.
 */
(function () {
	'use strict';

	/**
	 * Конфигурация, приходящая из PHP через wp_localize_script.
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
	 * Возвращает текст с запасным значением.
	 *
	 * @param {string} key Ключ из объекта локализации.
	 * @param {string} fallback Текст по умолчанию.
	 *
	 * @returns {string}
	 */
	const t = (key, fallback) => {
		return typeof i18n[key] === 'string' && i18n[key] ? i18n[key] : fallback;
	};

	/**
	 * Дебаунс для ввода: храним идентификаторы таймеров по типу записей,
	 * чтобы не отправлять запрос на каждый символ.
	 */
	const debounceMap = new Map();

	/**
	 * Простой помощник для поиска элементов.
	 *
	 * @param {string} selector CSS-селектор.
	 * @param {Element|Document} [ctx] Контекст поиска.
	 *
	 * @returns {Element|null}
	 */
	const qs = (selector, ctx) => (ctx || document).querySelector(selector);

	/**
	 * Простой помощник для поиска коллекции элементов.
	 *
	 * @param {string} selector CSS-селектор.
	 * @param {Element|Document} [ctx] Контекст поиска.
	 *
	 * @returns {Element[]}
	 */
	const qsa = (selector, ctx) => Array.from((ctx || document).querySelectorAll(selector));

	/**
	 * Прячет выпадающий список с подсказками.
	 *
	 * @param {Element} dropdown Узел списка.
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
	 * Показывает информационное сообщение в выпадающем списке.
	 *
	 * @param {Element} dropdown Узел списка.
	 * @param {string} message Текст сообщения.
	 *
	 * @returns {void}
	 */
	const showDropdownMessage = (dropdown, message) => {
		if (!dropdown) return;
		dropdown.innerHTML = `<div class="llmf-excluded-posts__dropdown-item">${message}</div>`;
		dropdown.style.display = 'block';
	};

	/**
	 * Возвращает множество уже выбранных ID для типа записей.
	 *
	 * @param {string} postType Текущий тип записей.
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
	 * Удаляет сообщение "пусто" внутри контейнера списка.
	 *
	 * @param {Element} container Контейнер с выбранными элементами.
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
	 * Добавляет запись в список исключений в DOM.
	 *
	 * @param {string} postType Тип записей.
	 * @param {number} id ID записи.
	 * @param {string} title Заголовок записи.
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
	 * Рендерит найденные записи в выпадающем списке.
	 *
	 * @param {Element} dropdown Узел списка.
	 * @param {string} postType Текущий тип записей.
	 * @param {{id:number,title:string}[]} items Массив записей.
	 */
	const renderDropdown = (dropdown, postType, items) => {
		if (!dropdown) return;

		const existing = selectedIds(postType);
		const fragment = document.createDocumentFragment();

		items
			.filter((item) => item && typeof item.id === 'number' && item.id > 0 && item.title)
			.forEach((item) => {
				// Пропускаем уже выбранные записи, чтобы не дублировать.
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
	 * Выполняет AJAX-поиск по типу записей.
	 *
	 * @param {HTMLInputElement} input Поле ввода, которое инициировало поиск.
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
	 * Обработчик ввода в поле поиска с дебаунсом.
	 *
	 * @param {Event} event Событие input.
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

		// Стартуем поиск спустя 250 мс после остановки ввода.
		const timer = setTimeout(() => performSearch(input), 250);
		debounceMap.set(postType, timer);

		if (input.value.trim().length < minChars) {
			showDropdownMessage(dropdown, t('typeMore', 'Enter at least 2 characters to search.'));
		}
	};

	/**
	 * Удаляет элемент исключения по клику на кнопку.
	 *
	 * @param {Element} item Узел выбранного элемента.
	 *
	 * @returns {void}
	 */
	const removeSelectedItem = (item) => {
		if (!item || !item.parentElement) return;
		const container = item.parentElement;
		item.remove();

		// Если после удаления контейнер пуст, показываем информационный текст.
		if (!container.querySelector('.llmf-excluded-posts__selected-item')) {
			const p = document.createElement('p');
			p.className = 'description llmf-excluded-posts__empty';
			p.textContent = t('selectedEmpty', 'No items are excluded yet.');
			container.appendChild(p);
		}
	};

	/**
	 * Переключает видимость блоков поиска в зависимости от выбранных типов.
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
				// Включаем чекбоксы, чтобы значения отправились при сохранении.
				qsa('input.llmf-excluded-posts__checkbox', block).forEach((input) => {
					if (input instanceof HTMLInputElement) {
						input.disabled = false;
					}
				});
			} else {
				block.classList.add('llmf-excluded-posts__type--hidden');
				// Отключаем чекбоксы скрытых типов, чтобы исключения не отправлялись.
				qsa('input.llmf-excluded-posts__checkbox', block).forEach((input) => {
					if (input instanceof HTMLInputElement) {
						input.disabled = true;
					}
				});
				// Прячем выпадающий список, если он был открыт.
				hideDropdown(qs('.llmf-excluded-posts__dropdown', block));
			}
		});
	};

	// Навешиваем обработчики на поля поиска.
	qsa('.llmf-excluded-posts__search-input', root).forEach((input) => {
		input.addEventListener('input', handleSearchInput);
		input.addEventListener('focus', handleSearchInput);
	});

	// Делегирование кликов по кнопкам удаления.
	root.addEventListener('click', (event) => {
		const target = event.target;
		if (target instanceof HTMLElement && target.classList.contains('llmf-excluded-posts__remove')) {
			const wrapper = target.closest('.llmf-excluded-posts__selected-item');
			removeSelectedItem(wrapper);
		}
	});

	// Закрытие выпадающих списков при клике вне их области.
	document.addEventListener('click', (event) => {
		const target = event.target;
		if (!(target instanceof Element)) return;

		// Игнорируем клики внутри блока поиска.
		if (target.closest('.llmf-excluded-posts__search')) {
			return;
		}

		qsa('.llmf-excluded-posts__dropdown', root).forEach((dropdown) => hideDropdown(dropdown));
	});

	// Переключение видимости блоков при смене чекбоксов типов записей.
	qsa('.llmf-post-type-toggle').forEach((cb) => {
		cb.addEventListener('change', syncTypesVisibility);
	});

	// Начальное состояние блоков зависит от выбранных типов.
	syncTypesVisibility();
})();
