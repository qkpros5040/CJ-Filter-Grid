(function () {
	function normalize(text) {
		return String(text || '').toLowerCase().trim();
	}

	function renderMultiSelect(select, mount) {
		if (!select || !mount) return;
		if (mount.dataset.dpgMsInit) return;
		mount.dataset.dpgMsInit = '1';

		const placeholder = select.getAttribute('data-placeholder') || 'Select…';

		const root = document.createElement('div');
		root.className = 'dpg-ms-root';

		const chips = document.createElement('div');
		chips.className = 'dpg-ms-chips';

		const btn = document.createElement('button');
		btn.type = 'button';
		btn.className = 'button dpg-ms-btn';
		btn.setAttribute('aria-label', placeholder);
		btn.title = placeholder;
		btn.innerHTML = '<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span><span class="screen-reader-text"></span>';
		btn.querySelector('.screen-reader-text').textContent = placeholder;

		const pop = document.createElement('div');
		pop.className = 'dpg-ms-pop';
		pop.hidden = true;

		const search = document.createElement('input');
		search.type = 'search';
		search.className = 'dpg-ms-search';
		search.placeholder = 'Search…';

		const list = document.createElement('div');
		list.className = 'dpg-ms-list';

		pop.appendChild(search);
		pop.appendChild(list);

		root.appendChild(chips);
		root.appendChild(btn);
		root.appendChild(pop);
		mount.appendChild(root);

		let repositionHandler = null;

		function selectedOptions() {
			return Array.from(select.options).filter((o) => o.selected);
		}

		function syncChips() {
			chips.innerHTML = '';
			const selected = selectedOptions();
			if (!selected.length) {
				const empty = document.createElement('div');
				empty.className = 'dpg-ms-empty';
				empty.textContent = '—';
				chips.appendChild(empty);
				return;
			}
			for (const opt of selected) {
				const chip = document.createElement('span');
				chip.className = 'dpg-chip';
				const label = document.createElement('span');
				label.textContent = opt.text;
				const rm = document.createElement('button');
				rm.type = 'button';
				rm.className = 'dpg-chip__rm';
				rm.textContent = '×';
				rm.addEventListener('click', () => {
					opt.selected = false;
					select.dispatchEvent(new Event('change', { bubbles: true }));
					syncUI();
				});
				chip.appendChild(rm);
				chip.appendChild(label);
				chips.appendChild(chip);
			}
		}

		function renderList() {
			list.innerHTML = '';
			const q = normalize(search.value);
			for (const opt of Array.from(select.options)) {
				const text = opt.text || '';
				if (q && !normalize(text).includes(q)) continue;
				const row = document.createElement('label');
				row.className = 'dpg-ms-row';
				const cb = document.createElement('input');
				cb.type = 'checkbox';
				cb.checked = !!opt.selected;
				cb.addEventListener('change', () => {
					opt.selected = cb.checked;
					select.dispatchEvent(new Event('change', { bubbles: true }));
					syncChips();
				});
				const span = document.createElement('span');
				span.textContent = text;
				row.appendChild(cb);
				row.appendChild(span);
				list.appendChild(row);
			}
		}

		function open() {
			pop.hidden = false;
			root.classList.add('is-open');
			search.value = '';
			renderList();

			const positionPop = () => {
				const rect = btn.getBoundingClientRect();
				const vw = window.innerWidth || document.documentElement.clientWidth || 0;
				const vh = window.innerHeight || document.documentElement.clientHeight || 0;
				const padding = 12;
				const desiredWidth = Math.max(rect.width, 260);
				const maxWidth = Math.max(240, vw - padding * 2);
				const width = Math.min(desiredWidth, maxWidth);
				const left = Math.min(Math.max(padding, rect.left), Math.max(padding, vw - width - padding));
				const top = Math.min(rect.bottom + 6, Math.max(padding, vh - padding));

				pop.style.position = 'fixed';
				pop.style.left = `${left}px`;
				pop.style.top = `${top}px`;
				pop.style.width = `${width}px`;
				pop.style.maxHeight = `${Math.max(180, vh - top - padding)}px`;
			};

			positionPop();
			repositionHandler = () => {
				if (!pop.hidden) positionPop();
			};
			window.addEventListener('scroll', repositionHandler, true);
			window.addEventListener('resize', repositionHandler);

			search.focus();
		}

		function close() {
			pop.hidden = true;
			root.classList.remove('is-open');
			pop.style.position = '';
			pop.style.left = '';
			pop.style.top = '';
			pop.style.width = '';
			pop.style.maxHeight = '';
			if (repositionHandler) {
				window.removeEventListener('scroll', repositionHandler, true);
				window.removeEventListener('resize', repositionHandler);
				repositionHandler = null;
			}
		}

		function syncUI() {
			syncChips();
			renderList();
		}

		btn.addEventListener('click', () => {
			if (pop.hidden) open();
			else close();
		});

		search.addEventListener('input', renderList);

		document.addEventListener('click', (e) => {
			if (!root.contains(e.target)) close();
		});

		document.addEventListener('keydown', (e) => {
			if (e.key === 'Escape') close();
		});

		select.addEventListener('change', () => syncUI());
		syncUI();
	}

	function ready(fn) {
		if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn);
		else fn();
	}

	ready(() => {
		const table = document.getElementById('dpg-grids');
		const addBtn = document.getElementById('dpg-add-grid');
		if (!table || !addBtn) return;

		function selectedTaxonomies(row) {
			const sel = row.querySelector('select[data-role="taxonomies"]');
			if (!sel) return [];
			return Array.from(sel.selectedOptions).map((o) => o.value);
		}

		function setRowIndex(row, nextIndex) {
			const oldIndex = row.getAttribute('data-grid-index');
			row.setAttribute('data-grid-index', String(nextIndex));
			row.querySelectorAll('[name]').forEach((el) => {
				const name = el.getAttribute('name');
				if (!name || oldIndex === null) return;
				const updated = name.replace(`dpg_grids[${oldIndex}]`, `dpg_grids[${nextIndex}]`);
				el.setAttribute('name', updated);
			});
		}

		function syncTermSelectors(row) {
			const selected = new Set(selectedTaxonomies(row));
			row.querySelectorAll('.dpg-tax-terms__group').forEach((g) => {
				const tax = g.getAttribute('data-taxonomy');
				const show = selected.has(tax);
				g.style.display = show ? '' : 'none';
				const titleWrap = g.querySelector('.dpg-tax-title');
				if (titleWrap) titleWrap.style.display = show ? '' : 'none';
				if (show) {
					g.querySelectorAll('select.dpg-ms').forEach((sel) => {
						const ui = g.querySelector('.dpg-ms-ui[data-for="terms"]');
						if (ui) renderMultiSelect(sel, ui);
					});
				}
			});
		}

		function bindRemove(root) {
			root.querySelectorAll('.dpg-remove-row').forEach((btn) => {
				btn.addEventListener('click', () => {
					const tr = btn.closest('tr');
					if (tr) tr.remove();
					const rows = Array.from(table.querySelectorAll('tbody tr.dpg-grid-row'));
					rows.forEach((row, idx) => setRowIndex(row, idx));
				});
			});
		}

		function bindTaxonomyChange(root) {
			const sel = root.querySelector('select[data-role="taxonomies"]');
			if (!sel) return;
			sel.addEventListener('change', () => syncTermSelectors(root));
			syncTermSelectors(root);
		}

		function bindKeyPreview(root) {
			const input = root.querySelector('input.dpg-grid-key');
			const code = root.querySelector('code.dpg-shortcode');
			if (!input || !code) return;
			function update() {
				const v = (input.value || '').trim() || 'your_key';
				code.textContent = `[dpg_grid grid="${v}"]`;
			}
			input.addEventListener('input', update);
			update();
		}

		addBtn.addEventListener('click', () => {
			const tbody = table.querySelector('tbody');
			const first = tbody && tbody.querySelector('tr.dpg-grid-row');
			if (!tbody || !first) return;

			const clone = first.cloneNode(true);
			clone.querySelectorAll('.dpg-ms-ui').forEach((n) => {
				n.innerHTML = '';
				delete n.dataset.dpgMsInit;
			});
			const nextIndex = tbody.querySelectorAll('tr.dpg-grid-row').length;
			setRowIndex(clone, nextIndex);

			clone.querySelectorAll('input').forEach((i) => {
				// Keep checkbox/radio default behavior; reset text/number only.
				if (i.type === 'text' || i.type === 'number' || i.type === 'search') i.value = '';
			});
			clone.querySelectorAll('select').forEach((s) => {
				Array.from(s.options).forEach((o) => {
					o.selected = false;
				});
			});

			tbody.appendChild(clone);
			bindRemove(clone);
			bindTaxonomyChange(clone);
			bindKeyPreview(clone);
			clone.querySelectorAll('select.dpg-ms').forEach((sel) => {
				const mount = sel.parentElement && sel.parentElement.querySelector('.dpg-ms-ui');
				if (mount) renderMultiSelect(sel, mount);
			});
		});

		bindRemove(table);
		table.querySelectorAll('tr.dpg-grid-row').forEach((row) => {
			bindTaxonomyChange(row);
			bindKeyPreview(row);
		});

		table.querySelectorAll('select.dpg-ms').forEach((sel) => {
			const mount = sel.parentElement && sel.parentElement.querySelector('.dpg-ms-ui');
			if (mount) renderMultiSelect(sel, mount);
		});
	});
})();
