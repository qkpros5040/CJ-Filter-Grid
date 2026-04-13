/* global DPG_CONFIG */
import './style.css';
(function () {
	if (!window.wp || !window.wp.element) return;

	const el = window.wp.element;
	const { createElement, useEffect, useMemo, useRef, useState } = el;
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
					throw new Error(json.errors.map((e) => e.message).join('\n'));
				}
				return json.data;
			});
	}

	function Chevron() {
		return createElement(
			'svg',
			{ className: 'dpg-chevron', width: 14, height: 14, viewBox: '0 0 20 20', 'aria-hidden': true },
			createElement('path', { d: 'M5.5 7.5 10 12l4.5-4.5', fill: 'none', stroke: 'currentColor', strokeWidth: 2, strokeLinecap: 'round', strokeLinejoin: 'round' })
		);
	}

	function SearchIcon() {
		return createElement(
			'svg',
			{ className: 'dpg-searchicon', width: 18, height: 18, viewBox: '0 0 24 24', 'aria-hidden': true },
			createElement('path', {
				d: 'M21 21l-4.35-4.35m1.6-5.05a7.45 7.45 0 11-14.9 0 7.45 7.45 0 0114.9 0z',
				fill: 'none',
				stroke: 'currentColor',
				strokeWidth: 2,
				strokeLinecap: 'round',
			})
		);
	}

	function ResetButton({ href, onClick, mode }) {
		const label = __('Réinitialiser les filtres', 'dynamic-post-grid-pro');
		if (mode === 'ajax') {
			return createElement(
				'button',
				{ type: 'button', className: 'dpg-reset', onClick, title: __('Réinitialiser les filtres', 'dynamic-post-grid-pro') },
				label
			);
		}
		if (!href) return null;
		return createElement(
			'a',
			{ className: 'dpg-reset', href, title: __('Réinitialiser les filtres', 'dynamic-post-grid-pro') },
			label
		);
	}

	function FiltersPanel({ enabledTaxonomies, selected, termsByTax, onToggleTerm, taxonomyTitles, mode, contextTerm }) {
		if (!enabledTaxonomies.length) return null;
		const ref = useRef(null);
		const [openTax, setOpenTax] = useState(null);

		useEffect(() => {
			if (!openTax) return;
			function onDocPointerDown(e) {
				if (!ref.current) return;
				const target =
					e.target instanceof Element
						? e.target
						: e.target && e.target.parentElement instanceof Element
							? e.target.parentElement
							: null;
				const withinDd = target && target.closest ? target.closest('.dpg-dd') : null;
				if (!withinDd) setOpenTax(null);
			}
			function onKeyDown(e) {
				if (e.key === 'Escape') setOpenTax(null);
			}
			document.addEventListener('pointerdown', onDocPointerDown, true);
			document.addEventListener('keydown', onKeyDown);
			return () => {
				document.removeEventListener('pointerdown', onDocPointerDown, true);
				document.removeEventListener('keydown', onKeyDown);
			};
		}, [openTax]);

		return createElement(
			'div',
			{ className: 'dpg-filterbar', ref },
			enabledTaxonomies.map((tax) => {
				const terms = termsByTax[tax] || [];
				if (!terms.length) return null;
				const title =
					(taxonomyTitles && taxonomyTitles[tax]) ||
					(termsByTax._labels && termsByTax._labels[tax]) ||
					tax;
				const open = openTax === tax;
				const selectedIds = (selected && selected[tax]) || [];
				const activeTermId =
					contextTerm && contextTerm.taxonomy === tax && contextTerm.termId ? Number(contextTerm.termId) : null;

				return createElement(
					'div',
					{ key: tax, className: `dpg-dd ${open ? 'is-open' : ''}` },
					createElement(
						'button',
						{
							type: 'button',
							className: 'dpg-dd__btn',
							onClick: () => setOpenTax(open ? null : tax),
							'aria-expanded': open ? 'true' : 'false',
						},
						createElement('span', { className: 'dpg-dd__label' }, title),
						selectedIds.length ? createElement('span', { className: 'dpg-dd__count' }, String(selectedIds.length)) : null,
						createElement(Chevron, null)
					),
					createElement(
						'div',
						{ className: 'dpg-dd__menu', hidden: !open },
						createElement(
							'div',
							{ className: 'dpg-dd__items' },
								mode === 'links'
									? terms.map((t) =>
											createElement(
												'a',
												{
													key: t.id,
													className: `dpg-dd__link ${
														activeTermId && Number(t.id) === activeTermId ? 'is-active' : ''
													}`,
													href: t.uri || '#',
													rel: 'nofollow',
													onClick: () => setOpenTax(null),
												},
												t.name
											)
									  )
								: terms.map((t) => {
										const checked = !!(selected[tax] && selected[tax].includes(t.id));
										return createElement(
											'label',
											{ key: t.id, className: 'dpg-dd__item' },
											createElement('input', {
												type: 'checkbox',
												checked,
												onChange: () => onToggleTerm(tax, t.id),
											}),
											createElement('span', null, t.name)
										);
								  })
						)
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
		const inner = createElement(
			'div',
			{ className: 'dpg-item__inner' },
			imgUrl
				? createElement(
						'div',
						{ className: 'dpg-item__media' },
						createElement('img', {
							className: 'dpg-item__image',
							src: imgUrl,
							alt: imgAlt,
							loading: 'lazy',
						})
				  )
				: null,
			createElement('h3', { className: 'dpg-item__title' }, title),
			excerpt ? createElement('p', { className: 'dpg-item__excerpt' }, excerpt) : null
		);
		return createElement(
			'article',
			{ className: 'dpg-item' },
			uri ? createElement('a', { className: 'dpg-item__link', href: uri }, inner) : inner
		);
	}

	function GridItemLogo({ node }) {
		const title = node && node.title ? node.title : '';
		const uri = node && node.uri ? node.uri : null;
		const imgUrl = node && node.featuredImageUrl ? node.featuredImageUrl : '';
		const imgAlt = node && node.featuredImageAlt ? node.featuredImageAlt : '';

		const inner = createElement(
			'div',
			{ className: 'dpg-item__inner' },
			createElement(
				'div',
				{ className: 'dpg-logo' },
				imgUrl
					? createElement('img', { className: 'dpg-logo__img', src: imgUrl, alt: imgAlt, loading: 'lazy' })
					: createElement('div', { className: 'dpg-logo__placeholder' })
			),
			title ? createElement('div', { className: 'dpg-logo__title' }, title) : null
		);

		return createElement(
			'article',
			{ className: 'dpg-item dpg-item--logo' },
			uri ? createElement('a', { className: 'dpg-item__link', href: uri }, inner) : inner
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
				},
				imgUrl ? createElement('div', { className: 'dpg-overlay__bg', style: { backgroundImage: `url(${imgUrl})` } }) : null,
				createElement('div', { className: 'dpg-overlay__shade' }),
				createElement('h2', { className: 'dpg-overlay__title' }, title),
				createElement('div', { className: 'dpg-overlay__cta', 'aria-hidden': 'true' }, '→')
			)
		);
	}

	function BlogCard({ node }) {
		const title = node && node.title ? node.title : '(No title)';
		const uri = node && node.uri ? node.uri : null;
		const excerpt = node && node.excerpt ? node.excerpt : '';
		const imgUrl = node && node.featuredImageUrl ? node.featuredImageUrl : '';
		const imgAlt = node && node.featuredImageAlt ? node.featuredImageAlt : '';

		const inner = createElement(
			'div',
			{ className: 'dpg-bcard__inner' },
			imgUrl
				? createElement(
						'div',
						{ className: 'dpg-bcard__media' },
						createElement('img', {
							className: 'dpg-bcard__img',
							src: imgUrl,
							alt: imgAlt,
							loading: 'lazy',
						})
				  )
				: null,
			createElement(
				'div',
				{ className: 'dpg-bcard__body' },
				createElement('h3', { className: 'dpg-bcard__title' }, title),
				excerpt ? createElement('p', { className: 'dpg-bcard__excerpt' }, excerpt) : null
			)
		);

		return createElement(
			'article',
			{ className: 'dpg-bcard' },
			uri ? createElement('a', { className: 'dpg-bcard__link', href: uri }, inner) : inner
		);
	}

	function AdSlot({ config }) {
		return (config && config.adImageUrl)
			? createElement(
					'div',
					{ className: 'dpg-ad' },
					createElement(
						'a',
						{
							className: 'dpg-ad__link',
							href: (config && config.adLink) || '#',
							target: '_blank',
							rel: 'noopener noreferrer',
						},
						createElement('img', { className: 'dpg-ad__img', src: config.adImageUrl, alt: '' })
					)
			  )
			: config && config.adHtml
				? createElement('div', {
						className: 'dpg-ad',
						dangerouslySetInnerHTML: { __html: config.adHtml },
				  })
				: createElement('div', { className: 'dpg-ad dpg-ad--empty' }, __('Publicité', 'dynamic-post-grid-pro'));
	}

	function CategorySection({ section, config }) {
		const term = section && section.term ? section.term : null;
		const posts = section && section.posts ? section.posts : null;
		const nodes = (posts && posts.nodes) || [];
		const isSingle = !!(config && config._dpgIsSingleTermView);
		const showAd = !!(config && config._dpgShowSectionAd);
		if (!term) return null;

		if (!nodes.length) {
			return createElement(
				'section',
				{ className: `dpg-cat${isSingle ? ' dpg-cat--single' : ''}` },
				isSingle
					? null
					: createElement(
							'header',
							{ className: 'dpg-cat__header' },
							createElement(
								'h2',
								{ className: 'dpg-cat__title' },
								term.uri ? createElement('a', { href: term.uri }, term.name) : term.name
							),
							term.uri
								? createElement(
										'a',
										{ className: 'dpg-cat__more', href: term.uri },
										__('Voir plus', 'dynamic-post-grid-pro'),
										createElement('img', {
											src: 'https://cj.williamfiliatrault.com/wp-content/themes/communcation-jeunesse/resources/images/arrow-right.svg',
											className: 'dpg-cat__more-icon',
											alt: '',
										})
								  )
								: null
					  ),
				createElement('div', { className: 'dpg-empty dpg-empty--section' }, __('Aucun article trouvé.', 'dynamic-post-grid-pro'))
			);
		}

		const hasGlobalAd = !!(config && (config.adImageUrl || config.adHtml));
		const withAd = showAd && hasGlobalAd;
		const visibleNodes = withAd ? nodes.slice(0, 3) : nodes.slice(0, 4);

		return createElement(
			'section',
			{ className: `dpg-cat${isSingle ? ' dpg-cat--single' : ''}${withAd ? ' dpg-cat--hasad' : ''}` },
			isSingle
				? null
				: createElement(
						'header',
						{ className: 'dpg-cat__header' },
						createElement(
							'h2',
							{ className: 'dpg-cat__title' },
							term.uri ? createElement('a', { href: term.uri }, term.name) : term.name
						),
						term.uri
							? createElement(
									'a',
									{ className: 'dpg-cat__more', href: term.uri },
									__('Voir plus', 'dynamic-post-grid-pro'),
									createElement('img', {
										src: 'https://cj.williamfiliatrault.com/wp-content/themes/communcation-jeunesse/resources/images/arrow-right.svg',
										className: 'dpg-cat__more-icon',
										alt: '',
									})
							  )
							: null
				  ),
			createElement(
				'div',
				{ className: 'dpg-cat__body' },
				createElement(
					'div',
					{ className: 'dpg-cat__main' },
					createElement(
						'div',
						{ className: 'dpg-cat__list' },
						visibleNodes.map((node) => {
							const key = node.databaseId || node.uri;
							return createElement(BlogCard, { key, node });
						}),
						withAd ? createElement('div', { className: 'dpg-cat__adcard' }, createElement(AdSlot, { config })) : null
					)
				),
				null
			)
		);
	}

	function CategoriesTop({ nodes }) {
		const list = Array.isArray(nodes) ? nodes : [];
		if (!list.length) return null;
		return createElement(
			'div',
			{ className: 'dpg-cat__top' },
			createElement(
				'div',
				{ className: 'dpg-cat__top-left' },
				list[0]
					? createElement(GridItemOverlay, {
							key: list[0].databaseId || list[0].uri,
							node: list[0],
					  })
					: null
			),
			createElement(
				'div',
				{ className: 'dpg-cat__top-right' },
				list[1]
					? createElement(GridItemOverlay, {
							key: list[1].databaseId || list[1].uri,
							node: list[1],
					  })
					: null,
				list[2]
					? createElement(GridItemOverlay, {
							key: list[2].databaseId || list[2].uri,
							node: list[2],
					  })
					: null
			)
		);
	}

	function PaginationButtons({ page, pages, onPage, __ }) {
		if (!pages || pages <= 1) return null;
		return createElement(
			'div',
			{ className: 'dpg-pagination dpg-pagination--buttons' },
			createElement(
				'button',
				{ type: 'button', disabled: page <= 1, onClick: () => onPage(page - 1) },
				__('Précédent', 'dynamic-post-grid-pro')
			),
			createElement(
				'span',
				{ className: 'dpg-pagination__meta' },
				`${__('Page', 'dynamic-post-grid-pro')} ${page} / ${pages}`
			),
			createElement(
				'button',
				{ type: 'button', disabled: page >= pages, onClick: () => onPage(page + 1) },
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
		const didScrollInit = useRef(false);
		const [search, setSearch] = useState('');
		const [isDesktop, setIsDesktop] = useState(() => {
			try {
				return !!(window.matchMedia && window.matchMedia('(min-width: 721px)').matches);
			} catch (e) {
				return false;
			}
		});
		const [searchOpen, setSearchOpen] = useState(() => {
			try {
				return !!(window.matchMedia && window.matchMedia('(min-width: 721px)').matches);
			} catch (e) {
				return false;
			}
		});
		const debouncedSearch = useDebounced(search, 350);
		const searchRef = useRef(null);

		useEffect(() => {
			if (!window.matchMedia) return;
			const mql = window.matchMedia('(min-width: 721px)');
			const onChange = (e) => setIsDesktop(!!(e && e.matches));
			setIsDesktop(!!mql.matches);
			if (mql.addEventListener) mql.addEventListener('change', onChange);
			else if (mql.addListener) mql.addListener(onChange);
			return () => {
				if (mql.removeEventListener) mql.removeEventListener('change', onChange);
				else if (mql.removeListener) mql.removeListener(onChange);
			};
		}, []);

		useEffect(() => {
			setSearchOpen(!!isDesktop);
		}, [isDesktop]);

		useEffect(() => {
			if (searchOpen && searchRef.current) {
				searchRef.current.focus();
			}
		}, [searchOpen]);
		const [page, setPage] = useState(1);
		const [loading, setLoading] = useState(false);
		const [error, setError] = useState('');
		const [nodes, setNodes] = useState([]);
		const [sections, setSections] = useState([]);
		const [topNodes, setTopNodes] = useState([]);
		const [total, setTotal] = useState(0);
		const [pages, setPages] = useState(0);

		const layout = (config && config.layout) || 'card';
		const paginationType = (config && config.pagination) || 'buttons';

		const groupByTaxonomy = useMemo(() => {
			const explicit = config && config.groupByTaxonomy ? String(config.groupByTaxonomy) : '';
			if (explicit) return explicit;
			const list = (config && config.taxonomies) || [];
			if (Array.isArray(list) && list.includes('category')) return 'category';
			return Array.isArray(list) && list[0] ? String(list[0]) : 'category';
		}, [config && config.groupByTaxonomy, config && config.taxonomies]);

		const isSingleTermView = useMemo(() => {
			const ct = config && config.contextTerm ? config.contextTerm : null;
			return !!(ct && ct.taxonomy && String(ct.taxonomy) === String(groupByTaxonomy) && ct.termId);
		}, [config && config.contextTerm, groupByTaxonomy]);

		const isArchiveTermView = useMemo(() => {
			const ct = config && config.contextTerm ? config.contextTerm : null;
			return !!(ct && ct.taxonomy && ct.termId);
		}, [config && config.contextTerm]);

		// On any taxonomy archive (term) page, show a 4-per-row BlogCard grid (no top section/ad).
		const effectiveLayout = layout === 'categories' && isArchiveTermView ? 'categories-archive' : layout;

		const enabledTaxonomies = useMemo(() => {
			const fromConfig = (config && config.taxonomies) || [];
			if (!Array.isArray(fromConfig)) return [];
			const base = fromConfig.filter(Boolean);
			if (effectiveLayout === 'categories' && groupByTaxonomy && !base.includes(groupByTaxonomy)) base.push(groupByTaxonomy);
			return Array.from(new Set(base));
		}, [config && config.taxonomies, effectiveLayout, groupByTaxonomy]);

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
		const baseSelected = useMemo(() => {
			const ct = config && config.contextTerm ? config.contextTerm : null;
			if (ct && ct.taxonomy && ct.termId) {
				return { [ct.taxonomy]: [Number(ct.termId)] };
			}
			return {};
		}, [config && config.contextTerm]);
		const [selected, setSelected] = useState(() => baseSelected);
		const filterMode = (config && config.filterMode) || 'ajax';

		function resetAjaxFilters() {
			setSelected(baseSelected);
			setSearch('');
			setPage(1);
			setSearchOpen(!!isDesktop);
		}

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
						terms { id name uri }
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
						next[row.taxonomy] = (row.terms || []).map((t) => ({ id: t.id, name: t.name, uri: t.uri }));
						if (row.label) next._labels[row.taxonomy] = row.label;
					}
					setTermsByTax(next);
				})
				.catch(() => setTermsByTax({}));
		}, [enabledTaxonomies, allowedTermIdsByTax]);

		const filters = useMemo(() => {
			const out = [];
			for (const [taxonomy, termIds] of Object.entries(selected)) {
				if (!termIds || !termIds.length) continue;
				out.push({ taxonomy, termIds });
			}
			return out;
		}, [selected]);

		useEffect(() => {
			if (effectiveLayout !== 'categories') return;
			if (isSingleTermView) {
				setTopNodes([]);
				return;
			}
			let alive = true;
			const query = `
				query DpgPosts($postTypes: [String], $search: String, $filters: [DPGTaxFilterInput], $page: Int, $postsPerPage: Int) {
					dpgPosts(postTypes: $postTypes, search: $search, filters: $filters, page: $page, postsPerPage: $postsPerPage) {
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

			// Top area should show latest posts across ALL terms of the grouped taxonomy.
			const topFilters = [];
			for (const [taxonomy, termIds] of Object.entries(selected || {})) {
				if (taxonomy === groupByTaxonomy) continue;
				if (!termIds || !termIds.length) continue;
				topFilters.push({ taxonomy, termIds });
			}

			const vars = {
				postTypes: config.postTypes || [],
				search: debouncedSearch || null,
				filters: topFilters.length ? topFilters : null,
				page: 1,
				postsPerPage: 3,
			};

			graphqlFetch(query, vars)
				.then((data) => {
					if (!alive) return;
					const res = data && data.dpgPosts ? data.dpgPosts : null;
					setTopNodes((res && res.nodes) || []);
				})
				.catch(() => {
					if (!alive) return;
					setTopNodes([]);
				});

			return () => {
				alive = false;
			};
		}, [effectiveLayout, layout, isSingleTermView, config.postTypes, debouncedSearch, selected, groupByTaxonomy]);

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
			if (effectiveLayout === 'categories') return;
			let alive = true;
			setLoading(true);
			setError('');
			setSections([]);

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
					setError(e && e.message ? e.message : 'Échec de la requête');
				})
				.finally(() => {
					if (!alive) return;
					setLoading(false);
				});

			return () => {
				alive = false;
			};
		}, [effectiveLayout, layout, variables]);

		useEffect(() => {
			if (effectiveLayout !== 'categories') return;
			let alive = true;
			setLoading(true);
			setError('');
			setNodes([]);
			setPages(0);

			const selectedTermIds = (selected && selected[groupByTaxonomy]) || [];
			const allowed = (allowedTermIdsByTax && allowedTermIdsByTax[groupByTaxonomy]) || [];
			const termIds = selectedTermIds.length ? selectedTermIds : allowed;

			const extraFilters = [];
			for (const [taxonomy, termIds2] of Object.entries(selected || {})) {
				if (taxonomy === groupByTaxonomy) continue;
				if (!termIds2 || !termIds2.length) continue;
				extraFilters.push({ taxonomy, termIds: termIds2 });
			}

			const query = `
				query DpgTermSections($taxonomy: String, $termIds: [Int], $postTypes: [String], $search: String, $filters: [DPGTaxFilterInput], $postsPerPage: Int) {
					dpgTermSections(taxonomy: $taxonomy, termIds: $termIds, postTypes: $postTypes, search: $search, filters: $filters, postsPerPage: $postsPerPage) {
						term { taxonomy id name uri }
						posts {
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
				}
			`;

			const vars = {
				taxonomy: groupByTaxonomy,
				termIds: termIds && termIds.length ? termIds : null,
				postTypes: config.postTypes || [],
				search: debouncedSearch || null,
				filters: extraFilters.length ? extraFilters : null,
				postsPerPage: 4,
			};

			graphqlFetch(query, vars)
				.then((data) => {
					if (!alive) return;
					const list = (data && data.dpgTermSections) || [];
					setSections(list);
					let sum = 0;
					for (const row of list) sum += (row && row.posts && Number(row.posts.total)) || 0;
					setTotal(sum);
				})
				.catch((e) => {
					if (!alive) return;
					setError(e && e.message ? e.message : 'Échec de la requête');
					setSections([]);
					setTotal(0);
				})
				.finally(() => {
					if (!alive) return;
					setLoading(false);
				});

			return () => {
				alive = false;
			};
		}, [
			effectiveLayout,
			layout,
			groupByTaxonomy,
			config.postTypes,
			config.postsPerPage,
			debouncedSearch,
			selected,
			allowedTermIdsByTax,
		]);

		useEffect(() => setPage(1), [debouncedSearch, filters]);

		useEffect(() => {
			if (!didScrollInit.current) {
				didScrollInit.current = true;
				return;
			}
			try {
				window.scrollTo({ top: 0, behavior: 'smooth' });
			} catch (e) {
				// Fallback for older browsers.
				window.scrollTo(0, 0);
			}
		}, [page]);

		return createElement(
			'div',
			{ className: `dpg dpg--layout-${effectiveLayout}` },
			createElement(
				'div',
				{ className: 'dpg-toolbar' },
				createElement(
					'div',
					{ className: 'dpg-toolbar__top' },
					createElement(
						'div',
						{ className: 'dpg-toolbar__menu' },
						createElement(
							'div',
							{ className: 'dpg-filters-desktop' },
							createElement(FiltersPanel, {
								enabledTaxonomies,
								selected,
								termsByTax,
								onToggleTerm: toggleTerm,
								taxonomyTitles,
								mode: filterMode,
								contextTerm: (config && config.contextTerm) || null,
							}),
						),
						createElement(
							'details',
							{ className: 'dpg-filters-mobile' },
							createElement('summary', { className: 'dpg-filters-mobile__summary' }, __('Filtres', 'dynamic-post-grid-pro')),
							createElement(
								'div',
								{ className: 'dpg-filters-mobile__body' },
								createElement(FiltersPanel, {
									enabledTaxonomies,
									selected,
									termsByTax,
									onToggleTerm: toggleTerm,
									taxonomyTitles,
									mode: filterMode,
									contextTerm: (config && config.contextTerm) || null,
								}),
							)
						)
					),
				),
				createElement(
					'div',
					{ className: 'dpg-toolbar__bottom' },
					createElement(
						'button',
						{
							type: 'button',
							className: `dpg-search-toggle ${searchOpen ? 'is-open' : ''}`,
							'aria-label': __('Recherche', 'dynamic-post-grid-pro'),
							onClick: () => (isDesktop ? null : setSearchOpen((v) => !v)),
						},
						createElement(SearchIcon, null)
					),
					searchOpen
						? createElement('input', {
								ref: searchRef,
								className: 'dpg-search',
								type: 'search',
								placeholder: __('Rechercher…', 'dynamic-post-grid-pro'),
								value: search,
								onChange: (e) => setSearch(e.target.value),
						  })
						: null,
					createElement(ResetButton, {
						mode: filterMode,
						href: (config && config.archiveUrl) || '',
						onClick: resetAjaxFilters,
					})
				)
			),
			error ? createElement('div', { className: 'dpg-error' }, error) : null,
			!error &&
			!loading &&
			(effectiveLayout === 'categories' ? !sections || !sections.length : !nodes || !nodes.length)
				? createElement('div', { className: 'dpg-empty' }, __('Aucun résultat trouvé.', 'dynamic-post-grid-pro'))
				: null,
			effectiveLayout === 'blog'
				? createElement(
						'div',
						{ className: 'dpg-blog' },
						createElement(
							'div',
							{ className: 'dpg-blog__top' },
							createElement(
								'div',
								{ className: 'dpg-blog__top-left' },
								nodes[0]
									? createElement(GridItemOverlay, {
											key: nodes[0].databaseId || nodes[0].uri,
											node: nodes[0],
									  })
									: null
							),
							createElement(
								'div',
								{ className: 'dpg-blog__top-right' },
								nodes[1]
									? createElement(GridItemOverlay, {
											key: nodes[1].databaseId || nodes[1].uri,
											node: nodes[1],
									  })
									: null,
								nodes[2]
									? createElement(GridItemOverlay, {
											key: nodes[2].databaseId || nodes[2].uri,
											node: nodes[2],
									  })
									: null
							)
						),
						createElement(
							'div',
							{ className: 'dpg-blog__body' },
							createElement(
								'div',
								{ className: 'dpg-blog__main' },
								createElement(
									'div',
									{ className: 'dpg-blog__list' },
									nodes.slice(3).map((node) => {
										const key = node.databaseId || node.uri;
										return createElement(BlogCard, { key, node });
									})
								)
							),
							createElement('aside', { className: 'dpg-blog__ad' }, createElement(AdSlot, { config }))
						)
				  )
				: effectiveLayout === 'categories'
					? createElement(
							'div',
							{ className: 'dpg-cats' },
							isSingleTermView ? null : createElement(CategoriesTop, { nodes: topNodes }),
							sections.map((section, idx) =>
								createElement(CategorySection, {
									key: (section && section.term && section.term.id) || idx,
									section,
									config: {
										...config,
										_dpgIsSingleTermView: isSingleTermView,
										_dpgShowSectionAd:
											!!(config && config.adTermId)
												? Number(section && section.term && section.term.id) === Number(config.adTermId)
												: idx === 0,
									},
								})
							)
					  )
				: effectiveLayout === 'categories-archive'
					? createElement(
							'div',
							{ className: 'dpg-archive-grid' },
							nodes.map((node) => {
								const key = node.databaseId || node.uri;
								return createElement(BlogCard, { key, node });
							})
					  )
				: createElement(
						'div',
						{ className: 'dpg-grid' },
						nodes.map((node) => {
							const key = node.databaseId || node.uri;
							if (effectiveLayout === 'logo') return createElement(GridItemLogo, { key, node });
							if (effectiveLayout === 'overlay') return createElement(GridItemOverlay, { key, node });
							return createElement(GridItem, { key, node });
						})
				  ),
			effectiveLayout === 'categories'
				? null
				: paginationType === 'numeric'
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
