(function (window, document) {
	'use strict';

	var cfg = window.wpepCatalog || {};
	var i18n = cfg.i18n || {};
	var events = (window.WPEP && window.WPEP.events) || null;
	var el = window.WPEP.dom.el;
	var post = window.WPEP.ajax.post;

	function emptyProperty() {
		return {
			key: '',
			label: '',
			type: 'text',
			required: false,
			inheritance: 'none',
			options: [],
			units_source_term_id: 0,
			source_term_id: 0
		};
	}

	function CategoryEditorPane(root) {
		this.root = root;
		this.data = null;
		this.partsPane = null;
		this.partsMount = null;
	}

	CategoryEditorPane.prototype.showLoading = function () {
		this.root.innerHTML = '';
		this.root.appendChild(el('p', { className: 'description', text: i18n.loading || 'Loading…' }));
	};

	CategoryEditorPane.prototype.load = function (termId) {
		var self = this;
		this.showLoading();
		return post('wpep_get_category', { term_id: termId })
			.then(function (data) {
				self.data = data;
				self.render();
				if (events) {
					events.emit('category:loaded', data);
				}
				return data;
			})
			.catch(function (err) {
				self.root.innerHTML = '';
				self.root.appendChild(el('p', { className: 'notice notice-error', text: err.message }));
				if (events) {
					events.emit('category:failed', { message: err.message });
				}
				throw err;
			});
	};

	CategoryEditorPane.prototype.render = function () {
		var self = this;
		var data = this.data;
		if (!data || !data.term) {
			return;
		}
		var term = data.term;
		var properties = (data.properties || []).map(function (p) {
			return Object.assign({}, emptyProperty(), p, {
				options: Array.isArray(p.options) ? p.options.slice() : []
			});
		});
		var typeLabels = data.typeLabels || cfg.typeLabels || {};
		var parents = data.parents || {};

		this.root.innerHTML = '';

		var status = el('p', { className: 'wpep-editor-status', hidden: true });
		var form = el('div', { className: 'wpep-category-editor' });

		form.appendChild(el('h2', { text: i18n.categorySettings || 'Category settings' }));

		var nameInput = el('input', {
			type: 'text',
			className: 'regular-text',
			value: term.name,
			id: 'wpep-cat-name'
		});
		var slugInput = el('input', {
			type: 'text',
			className: 'regular-text',
			value: term.slug,
			id: 'wpep-cat-slug'
		});
		var parentSelect = el('select', { id: 'wpep-cat-parent' });
		parentSelect.appendChild(el('option', { value: '0', text: i18n.none || '— None —' }));
		Object.keys(parents).forEach(function (id) {
			if (String(id) === String(term.id)) {
				return;
			}
			parentSelect.appendChild(
				el('option', {
					value: String(id),
					text: parents[id],
					selected: Number(id) === Number(term.parent)
				})
			);
		});
		var descInput = el('textarea', {
			className: 'large-text',
			rows: '3',
			id: 'wpep-cat-description',
			text: term.description || ''
		});

		[nameInput, slugInput, parentSelect, descInput].forEach(function (input) {
			input.addEventListener('change', markDirty);
			input.addEventListener('input', markDirty);
		});

		function markDirty() {
			if (events) {
				events.emit('category:dirty', { dirty: true });
			}
		}

		form.appendChild(
			el('table', { className: 'form-table' }, [
				el('tbody', {}, [
					row(i18n.name || 'Name', nameInput),
					row(i18n.slug || 'Slug', slugInput),
					row(i18n.parent || 'Parent', parentSelect),
					row(i18n.description || 'Description', descInput)
				])
			])
		);

		form.appendChild(
			el(
				'p',
				{},
				[
					el(
						'button',
						{
							type: 'button',
							className: 'button button-primary',
							onClick: function () {
								save();
							}
						},
						[i18n.save || 'Save']
					)
				]
			)
		);
		form.appendChild(status);

		form.appendChild(el('h2', { text: i18n.parameters || 'Parameters' }));
		var propsWrap = el('div', { className: 'wpep-params-list' });
		form.appendChild(propsWrap);

		function renderProperties() {
			propsWrap.innerHTML = '';
			properties.forEach(function (prop, index) {
				propsWrap.appendChild(propertyCard(prop, index));
			});
		}

		function propertyCard(prop, index) {
			var typeSelect = el('select');
			Object.keys(typeLabels).forEach(function (type) {
				typeSelect.appendChild(
					el('option', {
						value: type,
						text: typeLabels[type],
						selected: prop.type === type
					})
				);
			});
			typeSelect.addEventListener('change', function () {
				prop.type = typeSelect.value;
				markDirty();
				renderProperties();
			});

			var keyInput = el('input', { type: 'text', className: 'regular-text', value: prop.key || '' });
			keyInput.addEventListener('input', function () {
				prop.key = keyInput.value;
				markDirty();
			});
			var labelInput = el('input', { type: 'text', className: 'regular-text', value: prop.label || '' });
			labelInput.addEventListener('input', function () {
				prop.label = labelInput.value;
				markDirty();
			});

			var required = el('input', { type: 'checkbox', checked: !!prop.required });
			required.addEventListener('change', function () {
				prop.required = required.checked;
				markDirty();
			});
			var inherit = el('input', {
				type: 'checkbox',
				checked: prop.inheritance === 'children'
			});
			inherit.addEventListener('change', function () {
				prop.inheritance = inherit.checked ? 'children' : 'none';
				markDirty();
			});

			var extras = el('div', { className: 'wpep-param-extras' });
			if (prop.type === 'enum' || prop.type === 'enum_multi') {
				var opts = el('textarea', {
					className: 'large-text',
					rows: '3',
					text: (prop.options || []).join('\n')
				});
				opts.addEventListener('input', function () {
					prop.options = opts.value
						.split('\n')
						.map(function (line) {
							return line.trim();
						})
						.filter(Boolean);
					markDirty();
				});
				extras.appendChild(el('label', {}, [i18n.options || 'Options', opts]));
			}
			if (prop.type === 'measure') {
				var units = el('select');
				units.appendChild(el('option', { value: '0', text: i18n.none || '— None —' }));
				Object.keys(parents).forEach(function (id) {
					units.appendChild(
						el('option', {
							value: String(id),
							text: parents[id],
							selected: Number(id) === Number(prop.units_source_term_id)
						})
					);
				});
				units.addEventListener('change', function () {
					prop.units_source_term_id = Number(units.value) || 0;
					markDirty();
				});
				extras.appendChild(el('label', {}, [i18n.unitsSource || 'Units from category', units]));
			}

			return el('div', { className: 'wpep-param-card' }, [
				el('div', { className: 'wpep-param-card__row' }, [
					el('label', {}, [i18n.label || 'Label', labelInput]),
					el('label', {}, [i18n.key || 'Key', keyInput]),
					el('label', {}, [i18n.type || 'Type', typeSelect])
				]),
				el('div', { className: 'wpep-param-card__row' }, [
					el('label', {}, [required, ' ', i18n.required || 'Required']),
					el('label', {}, [inherit, ' ', i18n.inherit || 'Inherit to children']),
					el(
						'button',
						{
							type: 'button',
							className: 'button-link-delete',
							onClick: function () {
								properties.splice(index, 1);
								markDirty();
								renderProperties();
							}
						},
						[i18n.remove || 'Remove']
					)
				]),
				extras
			]);
		}

		renderProperties();

		form.appendChild(
			el(
				'p',
				{},
				[
					el(
						'button',
						{
							type: 'button',
							className: 'button',
							onClick: function () {
								properties.push(emptyProperty());
								markDirty();
								renderProperties();
							}
						},
						[i18n.addParameter || 'Add parameter']
					)
				]
			)
		);

		this.partsMount = el('div', { className: 'wpep-category-parts-mount' });
		form.appendChild(this.partsMount);

		this.root.appendChild(form);

		if (window.WPEP.PartsListPane) {
			this.partsPane = new window.WPEP.PartsListPane({
				root: this.partsMount,
				variant: 'embedded',
				categoryId: term.id,
				categoryName: term.name,
				from: 'category-embedded'
			}).mount();
		}

		function row(label, control) {
			return el('tr', {}, [
				el('th', { scope: 'row' }, [el('label', { text: label })]),
				el('td', {}, [control])
			]);
		}

		function save() {
			status.hidden = true;
			post('wpep_save_category', {
				term_id: term.id,
				name: nameInput.value,
				slug: slugInput.value,
				parent: parentSelect.value,
				description: descInput.value,
				properties: properties
			})
				.then(function (fresh) {
					self.data = fresh;
					status.hidden = false;
					status.className = 'wpep-editor-status notice notice-success';
					status.textContent = i18n.saved || 'Saved.';
					if (events) {
						events.emit('category:saved', fresh);
						events.emit('category:dirty', { dirty: false });
						events.emit('tree:refresh-term', {
							termId: fresh.term.id,
							name: fresh.term.name,
							count: fresh.term.count
						});
					}
					self.render();
				})
				.catch(function (err) {
					status.hidden = false;
					status.className = 'wpep-editor-status notice notice-error';
					status.textContent = err.message;
				});
		}
	};

	window.WPEP = window.WPEP || {};
	window.WPEP.CategoryEditorPane = CategoryEditorPane;
})(window, document);
