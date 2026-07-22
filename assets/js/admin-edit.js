(function () {
	'use strict';

	function slugify(value) {
		return String(value || '')
			.normalize('NFD')
			.replace(/[\u0300-\u036f]/g, '')
			.toLowerCase()
			.replace(/[^a-z0-9\s-]/g, '')
			.trim()
			.replace(/[\s_]+/g, '-')
			.replace(/-+/g, '-');
	}

	function bindPreview() {
		var nameInput = document.getElementById('wpep_part_name');
		var titleInput = document.getElementById('wpep_generated_title');

		if (!nameInput || !titleInput) {
			return;
		}

		var update = function () {
			titleInput.value = slugify(nameInput.value);
		};

		nameInput.addEventListener('input', update);
		nameInput.addEventListener('change', update);
		update();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', bindPreview);
	} else {
		bindPreview();
	}
})();
