( function () {
	if ( ! window.wp || ! window.wp.element ) {
		return;
	}

	const el = window.wp.element;
	const { createElement, useEffect, useMemo, useState } = el;
	const __ = window.wp.i18n && window.wp.i18n.__ ? window.wp.i18n.__ : (s) => s;

	function useDebounced(value, delayMs) {
		const [debounced, setDebounced] = useState(value);
		useEffect(() => {
			const t = setTimeout(() => setDebounced(value), delayMs);
			return () => clearTimeout(t);
		}, [value, delayMs]);
		return debounced;
	}

	function graphqlFetch(query, variables) {
		const url = (window.DPG_CONFIG && window.DPG_CONFIG.graphqlUrl) || '/graphql';
		return fetch(url, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			credentials: 'same-origin',
			body: JSON.stringify({ query, variables }),
		})
			.then((r) => r.json())
			.then((json) => {
				if (json.errors && json.errors.length) {
					const message = json.errors.map((e) => e.message).join('\n');
					throw new Error(message);
				}
				return json.data;
			});
	}

	function FiltersPanel({ enabledTaxonomies, selected, termsByTax, onToggleTerm, taxonomyTitles }) {
		if (!enabledTaxonomies.length) return null;
		return createElement(
			'div',
			{ className: 'dpg-filters' },
			enabledTaxonomies.map((tax) => {
				const terms = termsByTax[tax] || [];
				if (!terms.length) return null;
				const title =
					(taxonomyTitles && taxonomyTitles[tax]) ||
					(termsByTax._labels && termsByTax._labels[tax]) ||
					tax;
				return createElement(
					'fieldset',
					{ key: tax, className: 'dpg-filter' },
					createElement('legend', { className: 'dpg-filter__title' }, title),
					createElement(
						'div',
						{ className: 'dpg-filter__terms' },
						terms.map((t) => {
							const checked = !!(selected[tax] && selected[tax].includes(t.id));
							return createElement(
								'label',
								{ key: t.id, className: 'dpg-term' },
								createElement('input', {
									type: 'checkbox',
									checked,
									onChange: () => onToggleTerm(tax, t.id),
								}),
								createElement('span', null, t.name)
							);
						})
					)
				);
			})
		);
	}

	function GridItem({ node }) {
		const title = node && node.title ? node.title : '(No title)';
		const uri = node && node.uri ? node.uri : null;
		const excerpt = node && node.excerpt ? node.excerpt : '';
		const imgUrl = node && node.featuredImageUrl ? node.featuredImageUrl : '';
		const imgAlt = node && node.featuredImageAlt ? node.featuredImageAlt : '';
		return createElement(
			'article',
			{ className: 'dpg-item' },
			imgUrl
				? createElement('img', {
						className: 'dpg-item__image',
						src: imgUrl,
						alt: imgAlt,
						loading: 'lazy',
				  })
				: null,
			createElement(
				'h3',
				{ className: 'dpg-item__title' },
				uri ? createElement('a', { href: uri }, title) : title
			),
			excerpt ? createElement('p', { className: 'dpg-item__excerpt' }, excerpt) : null
		);
	}

	function GridItemLogo({ node }) {
		const title = node && node.title ? node.title : '';
		const uri = node && node.uri ? node.uri : null;
		const imgUrl = node && node.featuredImageUrl ? node.featuredImageUrl : '';
		const imgAlt = node && node.featuredImageAlt ? node.featuredImageAlt : '';

		return createElement(
			'article',
			{ className: 'dpg-item dpg-item--logo' },
			createElement(
				'div',
				{ className: 'dpg-logo' },
				imgUrl
					? createElement('img', { className: 'dpg-logo__img', src: imgUrl, alt: imgAlt, loading: 'lazy' })
					: createElement('div', { className: 'dpg-logo__placeholder' })
			),
			title
				? createElement(
						'div',
						{ className: 'dpg-logo__title' },
						uri ? createElement('a', { href: uri }, title) : title
				  )
				: null
		);
	}

	function GridItemOverlay({ node }) {
		const title = node && node.title ? node.title : '(No title)';
		const uri = node && node.uri ? node.uri : null;
		const imgUrl = node && node.featuredImageUrl ? node.featuredImageUrl : '';

		return createElement(
			'article',
			{ className: 'dpg-item dpg-item--overlay' },
			createElement(
				'a',
				{
					className: 'dpg-overlay',
					href: uri || '#',
					style: imgUrl ? { backgroundImage: `url(${imgUrl})` } : undefined,
				},
				createElement('div', { className: 'dpg-overlay__shade' }),
				createElement('div', { className: 'dpg-overlay__title' }, title),
				createElement('div', { className: 'dpg-overlay__cta', 'aria-hidden': 'true' }, '→')
			)
		);
	}

	function PaginationButtons({ page, pages, onPage, __ }) {
		if (!pages || pages <= 1) return null;
		const prevDisabled = page <= 1;
		const nextDisabled = page >= pages;
		return createElement(
			'div',
			{ className: 'dpg-pagination dpg-pagination--buttons' },
			createElement(
				'button',
				{ type: 'button', disabled: prevDisabled, onClick: () => onPage(page - 1) },
				__('Précédent', 'dynamic-post-grid-pro')
			),
			createElement(
				'span',
				{ className: 'dpg-pagination__meta' },
				`${__('Page', 'dynamic-post-grid-pro')} ${page} / ${pages}`
			),
			createElement(
				'button',
				{ type: 'button', disabled: nextDisabled, onClick: () => onPage(page + 1) },
				__('Suivant', 'dynamic-post-grid-pro')
			)
		);
	}

	function buildPageModel(page, pages) {
		const items = [];
		const add = (v) => items.push(v);
		const windowSize = 5;
		const half = Math.floor(windowSize / 2);
		let start = Math.max(1, page - half);
		let end = Math.min(pages, start + windowSize - 1);
		start = Math.max(1, end - windowSize + 1);

		add(1);
		if (start > 2) add('…');
		for (let p = start; p <= end; p++) {
			if (p === 1 || p === pages) continue;
			add(p);
		}
		if (end < pages - 1) add('…');
		if (pages > 1) add(pages);
		return items;
	}

	function PaginationNumeric({ page, pages, onPage, __ }) {
		if (!pages || pages <= 1) return null;
		const items = buildPageModel(page, pages);
		const nextDisabled = page >= pages;
		return createElement(
			'div',
			{ className: 'dpg-pagination dpg-pagination--numeric' },
			createElement(
				'div',
				{ className: 'dpg-pagination__nums' },
				items.map((it, idx) => {
					if (it === '…') return createElement('span', { key: `e-${idx}`, className: 'dpg-page dpg-page--ellipsis' }, '…');
					const isActive = it === page;
					return createElement(
						'button',
						{
							key: `p-${it}`,
							type: 'button',
							className: `dpg-page${isActive ? ' is-active' : ''}`,
							onClick: () => onPage(it),
							'aria-current': isActive ? 'page' : undefined,
							'aria-label': `${__('Page', 'dynamic-post-grid-pro')} ${it}`,
						},
						String(it)
					);
				})
			),
			createElement(
				'button',
				{
					type: 'button',
					className: 'dpg-next',
					disabled: nextDisabled,
					onClick: () => onPage(page + 1),
					'aria-label': __('Page suivante', 'dynamic-post-grid-pro'),
				},
				'→'
			)
		);
	}

	function GridContainer({ config }) {
		const [search, setSearch] = useState('');
		const debouncedSearch = useDebounced(search, 350);
		const [page, setPage] = useState(1);
		const [loading, setLoading] = useState(false);
		const [error, setError] = useState('');
		const [nodes, setNodes] = useState([]);
		const [total, setTotal] = useState(0);
		const [pages, setPages] = useState(0);

		const layout = (config && config.layout) || 'card';
		const paginationType = (config && config.pagination) || 'buttons';

		const enabledTaxonomies = useMemo(() => {
			const fromConfig = (config && config.taxonomies) || [];
			if (!Array.isArray(fromConfig)) return [];
			return Array.from(new Set(fromConfig.filter(Boolean)));
		}, [config && config.taxonomies]);

		const allowedTermIdsByTax = useMemo(() => {
			const raw = (config && config.termIds) || {};
			if (!raw || typeof raw !== 'object') return {};
			const next = {};
			for (const [tax, ids] of Object.entries(raw)) {
				if (!Array.isArray(ids)) continue;
				next[tax] = Array.from(new Set(ids.map((n) => Number(n)).filter((n) => Number.isFinite(n))));
			}
			return next;
		}, [config && config.termIds]);

		const taxonomyTitles = useMemo(() => {
			const raw = (config && config.taxonomyTitles) || {};
			return raw && typeof raw === 'object' ? raw : {};
		}, [config && config.taxonomyTitles]);
		const [termsByTax, setTermsByTax] = useState({});
		const [selected, setSelected] = useState({});

		function toggleTerm(taxonomy, termId) {
			setSelected((prev) => {
				const prevList = (prev[taxonomy] || []).slice();
				const idx = prevList.indexOf(termId);
				if (idx >= 0) prevList.splice(idx, 1);
				else prevList.push(termId);
				return { ...prev, [taxonomy]: prevList };
			});
		}

		useEffect(() => {
			if (!enabledTaxonomies.length) return;

			const query = `
				query DpgTaxTerms($requests: [DPGTaxTermsRequestInput]) {
					dpgTaxTerms(requests: $requests) {
						taxonomy
						label
						terms { id name }
					}
				}
			`;
			const requests = enabledTaxonomies.map((taxonomy) => ({
				taxonomy,
				termIds: Array.isArray(allowedTermIdsByTax[taxonomy]) ? allowedTermIdsByTax[taxonomy] : [],
			}));
			graphqlFetch(query, { requests })
				.then((data) => {
					const list = (data && data.dpgTaxTerms) || [];
					const next = { _labels: {} };
					for (const row of list) {
						if (!row || !row.taxonomy) continue;
						next[row.taxonomy] = (row.terms || []).map((t) => ({ id: t.id, name: t.name }));
						if (row.label) next._labels[row.taxonomy] = row.label;
					}
					setTermsByTax(next);
				})
				.catch(() => {
					// If term query fails, keep filters hidden (grid still works).
					setTermsByTax({});
				});
		}, [enabledTaxonomies, allowedTermIdsByTax]);

		const filters = useMemo(() => {
			const out = [];
			for (const [taxonomy, termIds] of Object.entries(selected)) {
				if (!termIds || !termIds.length) continue;
				out.push({ taxonomy, termIds });
			}
			return out;
		}, [selected]);

		const variables = useMemo(
			() => ({
				postTypes: config.postTypes || [],
				search: debouncedSearch || null,
				page,
				postsPerPage: config.postsPerPage || null,
				filters,
			}),
			[config.postTypes, config.postsPerPage, debouncedSearch, page, filters]
		);

		useEffect(() => {
			let alive = true;
			setLoading(true);
			setError('');

			const query = `
				query DpgPosts($postTypes: [String], $search: String, $filters: [DPGTaxFilterInput], $page: Int, $postsPerPage: Int) {
					dpgPosts(postTypes: $postTypes, search: $search, filters: $filters, page: $page, postsPerPage: $postsPerPage) {
						total
						pages
						nodes {
							databaseId
							uri
							title
							postType
							excerpt
							featuredImageUrl
							featuredImageAlt
						}
					}
				}
			`;

			graphqlFetch(query, variables)
				.then((data) => {
					if (!alive) return;
					const res = data && data.dpgPosts ? data.dpgPosts : null;
					setNodes((res && res.nodes) || []);
					setTotal((res && res.total) || 0);
					setPages((res && res.pages) || 0);
				})
				.catch((e) => {
					if (!alive) return;
					setError(e && e.message ? e.message : 'Request failed');
				})
				.finally(() => {
					if (!alive) return;
					setLoading(false);
				});

			return () => {
				alive = false;
			};
		}, [variables]);

		useEffect(() => {
			setPage(1);
		}, [debouncedSearch, filters]);

		return createElement(
			'div',
			{ className: `dpg dpg--layout-${layout}` },
			createElement(
				'div',
				{ className: 'dpg-toolbar' },
				createElement('input', {
					className: 'dpg-search',
					type: 'search',
					placeholder: __('Search…', 'dynamic-post-grid-pro'),
					value: search,
					onChange: (e) => setSearch(e.target.value),
				}),
				createElement(
					'div',
					{ className: 'dpg-meta' },
					loading ? __('Loading…', 'dynamic-post-grid-pro') : `${total} ${__('results', 'dynamic-post-grid-pro')}`
				)
			),
			createElement(FiltersPanel, {
				enabledTaxonomies,
				selected,
				termsByTax,
				onToggleTerm: toggleTerm,
				taxonomyTitles,
			}),
			error ? createElement('div', { className: 'dpg-error' }, error) : null,
			createElement(
				'div',
				{ className: 'dpg-grid' },
				nodes.map((node) => {
					const key = node.databaseId || node.uri || Math.random();
					if (layout === 'logo') return createElement(GridItemLogo, { key, node });
					if (layout === 'overlay') return createElement(GridItemOverlay, { key, node });
					return createElement(GridItem, { key, node });
				})
			),
			paginationType === 'numeric'
				? createElement(PaginationNumeric, { page, pages, onPage: setPage, __ })
				: createElement(PaginationButtons, { page, pages, onPage: setPage, __ })
		);
	}

	function boot() {
		const roots = document.querySelectorAll('.dpg-root[data-dpg]');
		for (const root of roots) {
			try {
				const config = JSON.parse(root.getAttribute('data-dpg') || '{}');
				const element = createElement(GridContainer, { config });
				const rootApi = window.wp.element.createRoot ? window.wp.element.createRoot(root) : null;
				if (rootApi && rootApi.render) {
					rootApi.render(element);
				} else if (window.wp.element.render) {
					window.wp.element.render(element, root);
				}
			} catch (e) {
				// noop
			}
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
} )();
