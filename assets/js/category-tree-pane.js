(function (window, document) {
	'use strict';

	var cfg = window.wpepCatalog || {};
	var i18n = cfg.i18n || {};
	var events = (window.WPEP && window.WPEP.events) || null;
	var post = window.WPEP.ajax.post;

	function directChildByClass(parent, className) {
		for (var i = 0; i < parent.children.length; i++) {
			if (parent.children[i].classList.contains(className)) {
				return parent.children[i];
			}
		}
		return null;
	}

	function setExpanded(node, expanded) {
		var row = directChildByClass(node, 'wpep-tree__row');
		var children = directChildByClass(node, 'wpep-tree__children');
		if (!row || !children) {
			return;
		}
		var toggle = row.querySelector('.wpep-tree__toggle');
		if (!toggle || toggle.classList.contains('wpep-tree__toggle--spacer')) {
			return;
		}
		toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
		children.classList.toggle('is-collapsed', !expanded);
		children.hidden = !expanded;
	}

	function setAll(expanded) {
		document.querySelectorAll('[data-wpep-tree] .wpep-tree__node.has-children').forEach(function (node) {
			setExpanded(node, expanded);
		});
	}

	function setActive(termId) {
		document.querySelectorAll('[data-wpep-tree] .wpep-tree__row.is-active').forEach(function (row) {
			row.classList.remove('is-active');
		});
		if (!termId) {
			return;
		}
		var node = document.querySelector('[data-wpep-tree] .wpep-tree__node[data-term-id="' + termId + '"]');
		if (node) {
			var row = directChildByClass(node, 'wpep-tree__row');
			if (row) {
				row.classList.add('is-active');
			}
		}
	}

	function updateTermLabel(termId, name, count) {
		var node = document.querySelector('[data-wpep-tree] .wpep-tree__node[data-term-id="' + termId + '"]');
		if (!node) {
			return;
		}
		node.setAttribute('data-term-name', name || node.getAttribute('data-term-name') || '');
		var nameBtn = node.querySelector('.wpep-tree__name');
		if (nameBtn && name != null) {
			nameBtn.textContent = name;
		}
		if (count != null) {
			var countBtn = node.querySelector('.wpep-tree__count');
			if (countBtn) {
				countBtn.textContent = String(count);
			}
		}
	}

	function deleteCategory(termId, mode) {
		var body = new window.FormData();
		body.append('action', 'wpep_delete_category');
		body.append('nonce', cfg.deleteNonce || '');
		body.append('term_id', String(termId));
		body.append('mode', mode);
		return window.fetch(cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body
		}).then(function (response) {
			return response.json().then(function (payload) {
				if (!response.ok || !payload || !payload.success) {
					var message =
						(payload && payload.data && payload.data.message) ||
						i18n.deleteFailed ||
						'Error';
					throw new Error(message);
				}
			});
		});
	}

	function setupDialog() {
		var dialog = document.getElementById('wpep-delete-dialog');
		if (!dialog || typeof dialog.showModal !== 'function') {
			return null;
		}
		var title = document.getElementById('wpep-delete-dialog-title');
		var text = document.getElementById('wpep-delete-dialog-text');
		var promote = document.getElementById('wpep-delete-promote');
		var cascade = document.getElementById('wpep-delete-cascade');
		var cancel = document.getElementById('wpep-delete-cancel');
		if (title) {
			title.textContent = i18n.dialogTitle || '';
		}
		if (text) {
			text.textContent = i18n.dialogText || '';
		}
		if (promote) {
			promote.textContent = i18n.promoteChildren || '';
		}
		if (cascade) {
			cascade.textContent = i18n.deleteChildren || '';
		}
		if (cancel) {
			cancel.textContent = i18n.cancel || '';
		}
		dialog.hidden = false;
		return dialog;
	}

	function askDeleteMode(hasChildren) {
		if (!hasChildren) {
			return window.confirm(i18n.confirmLeaf || 'Delete?') ? Promise.resolve('leaf') : Promise.resolve(null);
		}
		var dialog = setupDialog();
		if (!dialog) {
			return Promise.resolve(null);
		}
		return new Promise(function (resolve) {
			function onClose() {
				dialog.removeEventListener('close', onClose);
				var value = dialog.returnValue;
				if (value === 'promote' || value === 'delete_children') {
					resolve(value);
				} else {
					resolve(null);
				}
			}
			dialog.addEventListener('close', onClose);
			dialog.showModal();
		});
	}

	function insertNode(term, parentId) {
		var li = document.createElement('li');
		li.className = 'wpep-tree__node';
		li.setAttribute('data-term-id', String(term.id));
		li.setAttribute('data-has-children', '0');
		li.setAttribute('data-term-name', term.name);
		li.innerHTML =
			'<div class="wpep-tree__row">' +
			'<span class="wpep-tree__toggle wpep-tree__toggle--spacer" aria-hidden="true"></span>' +
			'<button type="button" class="wpep-tree__name" data-action="select-category"></button>' +
			'<span class="wpep-tree__row-end">' +
			'<button type="button" class="wpep-tree__count" data-action="open-parts">0</button>' +
			'<button type="button" class="wpep-tree__icon-btn" data-action="add-child" title="' +
			(i18n.addChild || 'Add child') +
			'"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span></button>' +
			'<button type="button" class="wpep-tree__icon-btn wpep-tree__icon-btn--danger wpep-tree__delete" data-action="delete" title="' +
			(i18n.delete || 'Delete') +
			'"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button>' +
			'</span></div>';
		li.querySelector('.wpep-tree__name').textContent = term.name;

		if (parentId) {
			var parent = document.querySelector(
				'[data-wpep-tree] .wpep-tree__node[data-term-id="' + parentId + '"]'
			);
			if (!parent) {
				return li;
			}
			var children = directChildByClass(parent, 'wpep-tree__children');
			if (!children) {
				parent.classList.add('has-children');
				parent.setAttribute('data-has-children', '1');
				var row = directChildByClass(parent, 'wpep-tree__row');
				var spacer = row && row.querySelector('.wpep-tree__toggle--spacer');
				if (spacer) {
					var btn = document.createElement('button');
					btn.type = 'button';
					btn.className = 'wpep-tree__toggle';
					btn.setAttribute('aria-expanded', 'true');
					btn.setAttribute('aria-label', 'Toggle children');
					btn.innerHTML = '<span class="dashicons dashicons-arrow-down" aria-hidden="true"></span>';
					spacer.replaceWith(btn);
				}
				children = document.createElement('ul');
				children.className = 'wpep-tree__children';
				parent.appendChild(children);
			}
			children.appendChild(li);
			setExpanded(parent, true);
		} else {
			var tree = document.querySelector('[data-wpep-tree]');
			if (tree) {
				tree.appendChild(li);
			}
		}
		return li;
	}

	function initTreePane() {
		var tree = document.querySelector('[data-wpep-tree]');
		if (!tree || !events) {
			return;
		}

		var expand = document.getElementById('wpep-tree-expand-all');
		var collapse = document.getElementById('wpep-tree-collapse-all');
		if (expand) {
			expand.addEventListener('click', function () {
				setAll(true);
			});
		}
		if (collapse) {
			collapse.addEventListener('click', function () {
				setAll(false);
			});
		}

		tree.addEventListener('click', function (event) {
			var toggle = event.target.closest('.wpep-tree__toggle');
			if (toggle && !toggle.classList.contains('wpep-tree__toggle--spacer')) {
				event.preventDefault();
				var node = toggle.closest('.wpep-tree__node');
				var expanded = toggle.getAttribute('aria-expanded') === 'true';
				setExpanded(node, !expanded);
				return;
			}

			var actionEl = event.target.closest('[data-action]');
			if (!actionEl) {
				return;
			}
			var nodeEl = actionEl.closest('.wpep-tree__node');
			if (!nodeEl) {
				return;
			}
			var termId = Number(nodeEl.getAttribute('data-term-id'));
			var termName = nodeEl.getAttribute('data-term-name') || '';
			var action = actionEl.getAttribute('data-action');

			if (action === 'select-category') {
				event.preventDefault();
				events.emit('category:selected', { categoryId: termId, name: termName });
				return;
			}
			if (action === 'open-parts') {
				event.preventDefault();
				events.emit('parts-list:open', { categoryId: termId, name: termName });
				return;
			}
			if (action === 'add-child') {
				event.preventDefault();
				events.emit('category:create', { parentId: termId });
				return;
			}
			if (action === 'delete') {
				event.preventDefault();
				var hasChildren = nodeEl.getAttribute('data-has-children') === '1';
				askDeleteMode(hasChildren).then(function (mode) {
					if (!mode) {
						return;
					}
					return deleteCategory(termId, mode).then(function () {
						nodeEl.remove();
						events.emit('category:deleted', { categoryId: termId });
					});
				}).catch(function (err) {
					window.alert(err.message || i18n.deleteFailed);
				});
			}
		});

		var addRoot = document.getElementById('wpep-add-root');
		if (addRoot) {
			addRoot.addEventListener('click', function () {
				events.emit('category:create', { parentId: 0 });
			});
		}

		var newPart = document.getElementById('wpep-new-part');
		if (newPart) {
			newPart.addEventListener('click', function () {
				events.emit('part:create', { categoryIds: [], from: 'toolbar' });
			});
		}

		events.on('tree:set-active', function (payload) {
			setActive(payload && payload.termId);
		});
		events.on('tree:refresh-term', function (payload) {
			if (!payload) {
				return;
			}
			updateTermLabel(payload.termId, payload.name, payload.count);
		});
		events.on('tree:bump-count', function (payload) {
			if (!payload || !payload.termId) {
				return;
			}
			post('wpep_list_parts', { category_id: payload.termId })
				.then(function (data) {
					var count = (data && data.parts && data.parts.length) || 0;
					updateTermLabel(payload.termId, null, count);
				})
				.catch(function () {
					/* ignore */
				});
		});
		events.on('category:created', function (payload) {
			if (!payload || !payload.term) {
				return;
			}
			insertNode(payload.term, payload.term.parent || 0);
			setActive(payload.term.id);
		});
	}

	window.WPEP = window.WPEP || {};
	window.WPEP.initTreePane = initTreePane;
	window.WPEP.createCategory = function (parentId) {
		return post('wpep_create_category', {
			parent: parentId || 0,
			name: i18n.newCategoryName || 'New category'
		});
	};
})(window, document);
