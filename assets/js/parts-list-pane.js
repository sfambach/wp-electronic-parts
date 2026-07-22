(function (window, document) {
	'use strict';

	var cfg = window.wpepCatalog || {};
	var i18n = cfg.i18n || {};
	var events = (window.WPEP && window.WPEP.events) || null;

	function el(tag, attrs, children) {
		var node = document.createElement(tag);
		attrs = attrs || {};
		Object.keys(attrs).forEach(function (key) {
			if (key === 'className') {
				node.className = attrs[key];
			} else if (key === 'text') {
				node.textContent = attrs[key];
			} else if (key === 'html') {
				node.innerHTML = attrs[key];
			} else if (key.slice(0, 2) === 'on' && typeof attrs[key] === 'function') {
				node.addEventListener(key.slice(2).toLowerCase(), attrs[key]);
			} else if (attrs[key] === false || attrs[key] === null || attrs[key] === undefined) {
				// skip
			} else if (attrs[key] === true) {
				node.setAttribute(key, '');
			} else {
				node.setAttribute(key, String(attrs[key]));
			}
		});
		(children || []).forEach(function (child) {
			if (child === null || child === undefined || child === false) {
				return;
			}
			if (typeof child === 'string') {
				node.appendChild(document.createTextNode(child));
			} else {
				node.appendChild(child);
			}
		});
		return node;
	}

	function post(action, data) {
		var body = new window.FormData();
		body.append('action', action);
		body.append('nonce', cfg.nonce || '');
		Object.keys(data || {}).forEach(function (key) {
			var value = data[key];
			if (value === undefined || value === null) {
				return;
			}
			if (typeof value === 'object') {
				body.append(key, JSON.stringify(value));
			} else {
				body.append(key, String(value));
			}
		});
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
						'Request failed';
					var err = new Error(message);
					err.payload = payload;
					throw err;
				}
				return payload.data;
			});
		});
	}

	/**
	 * @param {Object} options
	 * @param {HTMLElement} options.root
	 * @param {'embedded'|'full'} options.variant
	 * @param {number} options.categoryId
	 * @param {string} [options.categoryName]
	 * @param {string} options.from - part:open from value
	 */
	function PartsListPane(options) {
		this.root = options.root;
		this.variant = options.variant || 'full';
		this.categoryId = options.categoryId || 0;
		this.categoryName = options.categoryName || '';
		this.from = options.from || (this.variant === 'embedded' ? 'category-embedded' : 'parts-list');
		this.parts = [];
		this.loading = false;
	}

	PartsListPane.prototype.mount = function () {
		this.render();
		this.load();
		return this;
	};

	PartsListPane.prototype.setCategory = function (categoryId, categoryName) {
		this.categoryId = categoryId;
		this.categoryName = categoryName || this.categoryName;
		this.load();
	};

	PartsListPane.prototype.load = function () {
		var self = this;
		if (!this.categoryId) {
			this.parts = [];
			this.render();
			return;
		}
		this.loading = true;
		this.render();
		if (events) {
			events.emit('parts-list:loading', { categoryId: this.categoryId, variant: this.variant });
		}
		post('wpep_list_parts', { category_id: this.categoryId })
			.then(function (data) {
				self.loading = false;
				self.parts = (data && data.parts) || [];
				self.render();
				if (events) {
					events.emit('parts-list:loaded', {
						categoryId: self.categoryId,
						parts: self.parts,
						variant: self.variant
					});
				}
			})
			.catch(function (err) {
				self.loading = false;
				self.parts = [];
				self.render(err.message);
				if (events) {
					events.emit('parts-list:failed', {
						categoryId: self.categoryId,
						message: err.message,
						variant: self.variant
					});
				}
			});
	};

	PartsListPane.prototype.render = function (errorMessage) {
		var self = this;
		var root = this.root;
		root.innerHTML = '';
		root.className =
			'wpep-parts-list-pane' +
			(this.variant === 'embedded' ? ' wpep-parts-list-pane--embedded' : ' wpep-parts-list-pane--full');

		var header = el('div', { className: 'wpep-parts-list-pane__header' }, [
			el('h2', {
				text:
					this.variant === 'full'
						? (i18n.partsIn || 'Parts in') +
						  (this.categoryName ? ' “' + this.categoryName + '”' : '')
						: i18n.parts || 'Parts'
			}),
			el(
				'button',
				{
					type: 'button',
					className: 'button button-secondary',
					onClick: function () {
						if (!events) {
							return;
						}
						events.emit('part:create', {
							categoryIds: self.categoryId ? [self.categoryId] : [],
							from: self.from
						});
					}
				},
				[i18n.addPart || 'Add part']
			)
		]);
		root.appendChild(header);

		if (errorMessage) {
			root.appendChild(el('p', { className: 'notice notice-error', text: errorMessage }));
			return;
		}

		if (this.loading) {
			root.appendChild(el('p', { className: 'description', text: i18n.loading || 'Loading…' }));
			return;
		}

		if (!this.parts.length) {
			root.appendChild(el('p', { className: 'description', text: i18n.noParts || 'No parts yet.' }));
			return;
		}

		var list = el('ul', { className: 'wpep-parts-list' });
		this.parts.forEach(function (part) {
			var item = el(
				'li',
				{
					className: 'wpep-parts-list__item',
					onClick: function () {
						if (!events) {
							return;
						}
						events.emit('part:open', {
							partId: part.id,
							categoryId: self.categoryId,
							from: self.from
						});
					}
				},
				[
					el('span', { className: 'wpep-parts-list__name', text: part.name || part.title || '#' + part.id }),
					el('span', { className: 'wpep-parts-list__title', text: part.title || '' })
				]
			);
			list.appendChild(item);
		});
		root.appendChild(list);
	};

	window.WPEP = window.WPEP || {};
	window.WPEP.PartsListPane = PartsListPane;
	window.WPEP.ajax = { post: post };
	window.WPEP.dom = { el: el };
})(window, document);
