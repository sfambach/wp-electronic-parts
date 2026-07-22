(function (window, document) {
	'use strict';

	var events = (window.WPEP && window.WPEP.events) || null;
	var cfg = window.wpepCatalog || {};
	var i18n = cfg.i18n || {};

	if (!events || !document.getElementById('wpep-catalog')) {
		return;
	}

	var state = {
		mode: 'empty',
		categoryId: null,
		partId: null,
		partOpenedFrom: null,
		dirty: false,
		categoryName: ''
	};

	var panels = {
		empty: document.querySelector('[data-mode-panel="empty"]'),
		category: document.querySelector('[data-mode-panel="category"]'),
		'parts-list': document.querySelector('[data-mode-panel="parts-list"]'),
		part: document.querySelector('[data-mode-panel="part"]')
	};

	var categoryEditor = new window.WPEP.CategoryEditorPane(panels.category);
	var partEditor = new window.WPEP.PartEditorPane(panels.part);
	var partsListPane = null;

	window.WPEP.initTreePane();

	function confirmLeave() {
		if (!state.dirty) {
			return true;
		}
		return window.confirm(i18n.unsaved || 'You have unsaved changes. Continue?');
	}

	function showMode(mode) {
		Object.keys(panels).forEach(function (key) {
			if (!panels[key]) {
				return;
			}
			var active = key === mode;
			panels[key].hidden = !active;
		});
		state.mode = mode;
	}

	function setDirty(dirty) {
		state.dirty = !!dirty;
	}

	function openCategory(categoryId, name) {
		if (!confirmLeave()) {
			return;
		}
		state.categoryId = categoryId;
		state.categoryName = name || state.categoryName;
		state.partId = null;
		state.partOpenedFrom = null;
		setDirty(false);
		showMode('category');
		events.emit('tree:set-active', { termId: categoryId });
		events.emit('category:loading', { categoryId: categoryId });
		categoryEditor.load(categoryId).then(function (data) {
			if (data && data.term) {
				state.categoryName = data.term.name;
			}
		});
	}

	function openPartsList(categoryId, name) {
		if (!confirmLeave()) {
			return;
		}
		state.categoryId = categoryId;
		state.categoryName = name || state.categoryName;
		state.partId = null;
		state.partOpenedFrom = null;
		setDirty(false);
		showMode('parts-list');
		events.emit('tree:set-active', { termId: categoryId });
		panels['parts-list'].innerHTML = '';
		partsListPane = new window.WPEP.PartsListPane({
			root: panels['parts-list'],
			variant: 'full',
			categoryId: categoryId,
			categoryName: state.categoryName,
			from: 'parts-list'
		}).mount();
	}

	function openPart(partId, from, categoryId) {
		if (!confirmLeave()) {
			return;
		}
		if (categoryId) {
			state.categoryId = categoryId;
		}
		state.partId = partId;
		state.partOpenedFrom = from || null;
		setDirty(false);
		showMode('part');
		partEditor.openExisting(partId, state.partOpenedFrom);
	}

	function createPart(categoryIds, from) {
		if (!confirmLeave()) {
			return;
		}
		var ids = categoryIds || [];
		if ((!ids || !ids.length) && state.categoryId) {
			ids = [state.categoryId];
		}
		state.partId = null;
		state.partOpenedFrom = from || 'toolbar';
		setDirty(false);
		showMode('part');
		partEditor.openCreate(ids, state.partOpenedFrom);
	}

	function backFromPart() {
		if (!confirmLeave()) {
			return;
		}
		setDirty(false);
		var from = state.partOpenedFrom;
		state.partId = null;
		state.partOpenedFrom = null;
		if (from === 'parts-list' && state.categoryId) {
			openPartsList(state.categoryId, state.categoryName);
			return;
		}
		if (from === 'category-embedded' && state.categoryId) {
			openCategory(state.categoryId, state.categoryName);
			return;
		}
		if (state.categoryId) {
			openCategory(state.categoryId, state.categoryName);
			return;
		}
		showMode('empty');
		events.emit('tree:set-active', { termId: null });
	}

	events.on('category:selected', function (payload) {
		openCategory(payload.categoryId, payload.name);
	});

	events.on('parts-list:open', function (payload) {
		openPartsList(payload.categoryId, payload.name);
	});

	events.on('part:open', function (payload) {
		openPart(payload.partId, payload.from, payload.categoryId);
	});

	events.on('part:create', function (payload) {
		createPart(payload.categoryIds, payload.from);
	});

	events.on('part:back', function () {
		backFromPart();
	});

	events.on('category:dirty', function (payload) {
		setDirty(!!(payload && payload.dirty));
	});

	events.on('part:dirty', function (payload) {
		setDirty(!!(payload && payload.dirty));
	});

	events.on('category:create', function (payload) {
		if (!confirmLeave()) {
			return;
		}
		window.WPEP.createCategory(payload.parentId || 0)
			.then(function (data) {
				events.emit('category:created', data);
				if (data && data.term) {
					openCategory(data.term.id, data.term.name);
				}
			})
			.catch(function (err) {
				window.alert(err.message);
			});
	});

	events.on('category:deleted', function (payload) {
		if (state.categoryId && payload && payload.categoryId === state.categoryId) {
			setDirty(false);
			state.categoryId = null;
			state.partId = null;
			showMode('empty');
			events.emit('tree:set-active', { termId: null });
		}
	});

	events.on('part:saved', function (payload) {
		if (payload && payload.part) {
			state.partId = payload.part.id;
		}
		// Refresh full parts list counts precisely when possible.
		if (state.categoryId && partsListPane && state.mode === 'part') {
			// no-op here; tree bump handled elsewhere
		}
	});

	showMode('empty');
})(window, document);
