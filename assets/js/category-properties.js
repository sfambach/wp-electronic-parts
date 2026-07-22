(function () {
	'use strict';

	function syncRowExtras(row) {
		var select = row.querySelector('.wpep-prop-type');
		if (!select) {
			return;
		}
		var type = select.value;
		row.setAttribute('data-type', type);

		var showEnum = type === 'enum' || type === 'enum_multi';
		var showMeasure = type === 'measure';
		var showTermChildren = type === 'term_children' || type === 'term_children_multi';

		row.querySelectorAll('.wpep-prop-extra--enum').forEach(function (el) {
			el.hidden = !showEnum;
		});
		row.querySelectorAll('.wpep-prop-extra--measure').forEach(function (el) {
			el.hidden = !showMeasure;
		});
		row.querySelectorAll('.wpep-prop-extra--term-children').forEach(function (el) {
			el.hidden = !showTermChildren;
		});
	}

	function updateTitle(row) {
		var labelInput = row.querySelector('.wpep-prop-label');
		var title = row.querySelector('.wpep-prop-item__title');
		if (!labelInput || !title) {
			return;
		}
		var value = labelInput.value.trim();
		title.textContent = value || title.getAttribute('data-empty') || 'New property';
	}

	function reindexRows() {
		var rows = document.querySelectorAll('#wpep-prop-list .wpep-prop-row');
		rows.forEach(function (row, index) {
			row.querySelectorAll('input, select, textarea').forEach(function (field) {
				if (!field.name) {
					return;
				}
				field.name = field.name.replace(/wpep_properties\[(?:__INDEX__|\d+)\]/, 'wpep_properties[' + index + ']');
			});
		});
	}

	function bindRow(row) {
		var typeSelect = row.querySelector('.wpep-prop-type');
		if (typeSelect) {
			typeSelect.addEventListener('change', function () {
				syncRowExtras(row);
			});
		}
		var labelInput = row.querySelector('.wpep-prop-label');
		if (labelInput) {
			labelInput.addEventListener('input', function () {
				updateTitle(row);
			});
		}
		var title = row.querySelector('.wpep-prop-item__title');
		if (title) {
			title.setAttribute('data-empty', title.textContent);
		}
		syncRowExtras(row);
		updateTitle(row);
	}

	function init() {
		var list = document.getElementById('wpep-prop-list');
		var addBtn = document.getElementById('wpep-prop-add-row');
		var template = document.getElementById('wpep-prop-row-template');

		if (!list) {
			return;
		}

		list.querySelectorAll('.wpep-prop-row').forEach(bindRow);

		list.addEventListener('click', function (event) {
			var remove = event.target.closest('.wpep-prop-remove');
			if (!remove) {
				return;
			}
			var row = remove.closest('.wpep-prop-row');
			if (!row) {
				return;
			}
			if (list.querySelectorAll('.wpep-prop-row').length <= 1) {
				row.querySelectorAll('input[type="text"], textarea').forEach(function (field) {
					field.value = '';
				});
				row.querySelectorAll('input[type="checkbox"]').forEach(function (field) {
					field.checked = false;
				});
				var type = row.querySelector('.wpep-prop-type');
				if (type) {
					type.value = 'text';
				}
				var inheritance = row.querySelector('select[name*="[inheritance]"]');
				if (inheritance) {
					inheritance.value = 'none';
				}
				syncRowExtras(row);
				updateTitle(row);
				return;
			}
			row.remove();
			reindexRows();
		});

		if (addBtn && template) {
			addBtn.addEventListener('click', function () {
				var html = template.innerHTML.replace(/__INDEX__/g, String(list.querySelectorAll('.wpep-prop-row').length));
				var wrapper = document.createElement('div');
				wrapper.innerHTML = html.trim();
				var row = wrapper.firstElementChild;
				if (!row) {
					return;
				}
				list.appendChild(row);
				bindRow(row);
				reindexRows();
				row.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
			});
		}

		if (window.location.hash === '#wpep-category-properties') {
			var panel = document.getElementById('wpep-category-properties');
			if (panel) {
				panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
			}
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
