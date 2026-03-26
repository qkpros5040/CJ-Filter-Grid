/* global DPG_CONFIG */
import './style.css';
(function () {
	if (!window.wp || !window.wp.element) return;

	const el = window.wp.element;
	const { createElement, useEffect, useMemo, useState } = el;

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
					throw new Error(json.errors.map((e) => e.message).join('\n'));
				}
				return json.data;
			});
	}

	function FiltersPanel({ enabledTaxonomies, selected, termsByTax, onToggleTerm }) {
		if (!enabledTaxonomies.length) return null;
		return createElement(
			'div',
			{ className: 'dpg-filters' },
			enabledTaxonomies.map((tax) => {
				const terms = termsByTax[tax] || [];
				if (!terms.length) return null;
				return createElement(
					'fieldset',
					{ key: tax, className: 'dpg-filter' },
					createElement('legend', { className: 'dpg-filter__title' }, tax),
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
		return createElement(
			'article',
			{ className: 'dpg-item' },
			createElement(
				'h3',
				{ className: 'dpg-item__title' },
				uri ? createElement('a', { href: uri }, title) : title
			)
		);
	}

	function Pagination({ page, pages, onPage }) {
		if (!pages || pages <= 1) return null;
		return createElement(
			'div',
			{ className: 'dpg-pagination' },
			createElement(
				'button',
				{ type: 'button', disabled: page <= 1, onClick: () => onPage(page - 1) },
				'Prev'
			),
			createElement('span', { className: 'dpg-pagination__meta' }, `Page ${page} / ${pages}`),
			createElement(
				'button',
				{ type: 'button', disabled: page >= pages, onClick: () => onPage(page + 1) },
				'Next'
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

		const taxonomyConfig = (config && config.taxonomyConfig) || {};
		const enabledTaxonomies = useMemo(() => {
			const enabled = Object.keys(taxonomyConfig).filter((k) => !!taxonomyConfig[k]);
			return enabled.filter((k) => k === 'category' || k === 'post_tag');
		}, [taxonomyConfig]);

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
				query DpgTerms {
					categories(first: 100) { nodes { databaseId name } }
					tags(first: 100) { nodes { databaseId name } }
				}
			`;
			graphqlFetch(query, null)
				.then((data) => {
					const next = {};
					if (enabledTaxonomies.includes('category') && data && data.categories && data.categories.nodes) {
						next.category = data.categories.nodes.map((n) => ({ id: n.databaseId, name: n.name }));
					}
					if (enabledTaxonomies.includes('post_tag') && data && data.tags && data.tags.nodes) {
						next.post_tag = data.tags.nodes.map((n) => ({ id: n.databaseId, name: n.name }));
					}
					setTermsByTax(next);
				})
				.catch(() => setTermsByTax({}));
		}, [enabledTaxonomies]);

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
							__typename
							... on ContentNode {
								id
								uri
								title
							}
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

		useEffect(() => setPage(1), [debouncedSearch, filters]);

		return createElement(
			'div',
			{ className: 'dpg' },
			createElement(
				'div',
				{ className: 'dpg-toolbar' },
				createElement('input', {
					className: 'dpg-search',
					type: 'search',
					placeholder: 'Search…',
					value: search,
					onChange: (e) => setSearch(e.target.value),
				}),
				createElement('div', { className: 'dpg-meta' }, loading ? 'Loading…' : `${total} results`)
			),
			createElement(FiltersPanel, {
				enabledTaxonomies,
				selected,
				termsByTax,
				onToggleTerm: toggleTerm,
			}),
			error ? createElement('div', { className: 'dpg-error' }, error) : null,
			createElement(
				'div',
				{ className: 'dpg-grid' },
				nodes.map((node) => createElement(GridItem, { key: node.id || node.uri, node }))
			),
			createElement(Pagination, { page, pages, onPage: setPage })
		);
	}

	function boot() {
		const roots = document.querySelectorAll('.dpg-root[data-dpg]');
		for (const root of roots) {
			try {
				const config = JSON.parse(root.getAttribute('data-dpg') || '{}');
				const element = createElement(GridContainer, { config });
				const rootApi = window.wp.element.createRoot ? window.wp.element.createRoot(root) : null;
				if (rootApi && rootApi.render) rootApi.render(element);
				else if (window.wp.element.render) window.wp.element.render(element, root);
			} catch (e) {
				// noop
			}
		}
	}

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
	else boot();
})();
