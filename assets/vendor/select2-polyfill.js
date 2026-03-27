(function ($) {
	// Lightweight Select2-compatible polyfill (searchable multi-select).
	// Used only when real Select2/selectWoo isn't registered by the site.
	if (!$ || $.fn.select2) return;

	function normalize(text) {
		return String(text || '')
			.toLowerCase()
			.trim();
	}

	$.fn.select2 = function (options) {
		const opts = options || {};
		return this.each(function () {
			const select = this;
			if (!select || select.dataset.dpgSelect2Init) return;
			select.dataset.dpgSelect2Init = '1';

			const wrapper = document.createElement('div');
			wrapper.className = 'dpg-select2-poly';

			const search = document.createElement('input');
			search.type = 'search';
			search.className = 'dpg-select2-poly__search';
			search.placeholder = opts.placeholder || select.getAttribute('data-placeholder') || '';

			const parent = select.parentNode;
			parent.insertBefore(wrapper, select);
			wrapper.appendChild(search);
			wrapper.appendChild(select);

			// Increase visible area to mimic Select2 multi-select UX.
			if (select.multiple && !select.size) {
				select.size = 8;
			}

			search.addEventListener('input', function () {
				const q = normalize(search.value);
				for (const opt of select.options) {
					const text = normalize(opt.text);
					const show = !q || text.includes(q);
					// Keep selected options visible so users can unselect.
					opt.hidden = !show && !opt.selected;
				}
			});
		});
	};
})(window.jQuery);

