(function (window, document) {
	'use strict';

	var cfg = window.wpepCatalog || {};
	var i18n = cfg.i18n || {};
	var events = (window.WPEP && window.WPEP.events) || null;
	var el = window.WPEP.dom.el;
	var post = window.WPEP.ajax.post;

	function PartEditorPane(root) {
		this.root = root;
		this.data = null;
		this.from = null;
		this.creating = false;
		this.draftCategoryIds = [];
	}

	PartEditorPane.prototype.showLoading = function () {
		this.root.innerHTML = '';
		this.root.appendChild(el('p', { className: 'description', text: i18n.loading || 'Loading…' }));
	};

	PartEditorPane.prototype.openExisting = function (partId, from) {
		var self = this;
		this.from = from || null;
		this.creating = false;
		this.showLoading();
		return post('wpep_get_part', { part_id: partId })
			.then(function (data) {
				self.data = data;
				self.render();
				if (events) {
					events.emit('part:loaded', data);
				}
				return data;
			})
			.catch(function (err) {
				self.root.innerHTML = '';
				self.root.appendChild(el('p', { className: 'notice notice-error', text: err.message }));
				throw err;
			});
	};

	PartEditorPane.prototype.openCreate = function (categoryIds, from) {
		this.from = from || null;
		this.creating = true;
		this.draftCategoryIds = categoryIds || [];
		this.data = {
			part: {
				id: 0,
				name: '',
				title: '',
				categoryIds: this.draftCategoryIds.slice()
			},
			values: {},
			schema: [],
			terms: {}
		};
		var self = this;
		this.showLoading();
		return post('wpep_resolve_schema', { categoryIds: this.draftCategoryIds })
			.then(function (res) {
				self.data.schema = (res && res.schema) || [];
				self.data.terms = (res && res.terms) || {};
				self.render();
			})
			.catch(function (err) {
				self.root.innerHTML = '';
				self.root.appendChild(el('p', { className: 'notice notice-error', text: err.message }));
			});
	};

	PartEditorPane.prototype.render = function () {
		var self = this;
		var data = this.data;
		var part = data.part;
		var values = Object.assign({}, data.values || {});
		var schema = (data.schema || []).slice();
		var terms = data.terms || {};

		this.root.innerHTML = '';
		var wrap = el('div', { className: 'wpep-part-editor' });

		if (this.from) {
			wrap.appendChild(
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
									if (events) {
										events.emit('part:back', { from: self.from });
									}
								}
							},
							['← ' + (i18n.back || 'Back')]
						)
					]
				)
			);
		}

		wrap.appendChild(el('h2', { text: i18n.partSettings || 'Part settings' }));

		var nameInput = el('input', {
			type: 'text',
			className: 'regular-text',
			value: part.name || '',
			id: 'wpep-part-name'
		});
		nameInput.addEventListener('input', markDirty);

		var catBox = el('div', { className: 'wpep-part-categories' });
		var selected = (part.categoryIds || []).map(Number);
		Object.keys(terms).forEach(function (id) {
			var checked = selected.indexOf(Number(id)) >= 0;
			var cb = el('input', {
				type: 'checkbox',
				value: String(id),
				checked: checked
			});
			cb.addEventListener('change', function () {
				markDirty();
				refreshSchema();
			});
			catBox.appendChild(
				el('label', { className: 'wpep-part-categories__item' }, [cb, ' ', terms[id]])
			);
		});

		var fieldsMount = el('div', { className: 'wpep-part-fields' });
		var status = el('p', { className: 'wpep-editor-status', hidden: true });

		wrap.appendChild(
			el('table', { className: 'form-table' }, [
				el('tbody', {}, [
					el('tr', {}, [
						el('th', { scope: 'row' }, [el('label', { text: i18n.name || 'Name' })]),
						el('td', {}, [nameInput])
					]),
					el('tr', {}, [
						el('th', { scope: 'row' }, [el('label', { text: i18n.categories || 'Categories' })]),
						el('td', {}, [catBox])
					])
				])
			])
		);
		wrap.appendChild(fieldsMount);
		wrap.appendChild(
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
		wrap.appendChild(status);
		this.root.appendChild(wrap);

		renderFields();

		function markDirty() {
			if (events) {
				events.emit('part:dirty', { dirty: true });
			}
		}

		function selectedCategoryIds() {
			return Array.prototype.slice
				.call(catBox.querySelectorAll('input[type="checkbox"]:checked'))
				.map(function (cb) {
					return Number(cb.value);
				});
		}

		function refreshSchema() {
			var ids = selectedCategoryIds();
			post('wpep_resolve_schema', { categoryIds: ids }).then(function (res) {
				schema = (res && res.schema) || [];
				if (res && res.terms) {
					terms = res.terms;
				}
				renderFields();
			});
		}

		function renderFields() {
			fieldsMount.innerHTML = '';
			if (!schema.length) {
				return;
			}
			schema.forEach(function (def) {
				fieldsMount.appendChild(fieldRow(def));
			});
		}

		function eachOption(opts, cb) {
			if (!opts) {
				return;
			}
			if (Array.isArray(opts)) {
				opts.forEach(function (opt) {
					if (opt && typeof opt === 'object') {
						cb(String(opt.value), opt.label != null ? String(opt.label) : String(opt.value));
					} else {
						cb(String(opt), String(opt));
					}
				});
				return;
			}
			if (typeof opts === 'object') {
				Object.keys(opts).forEach(function (key) {
					cb(String(key), String(opts[key]));
				});
			}
		}

		function fieldRow(def) {
			var key = def.key;
			var type = def.type;
			var label = def.label || key;
			var current = values[key];
			var control;

			if (type === 'textarea') {
				control = el('textarea', {
					className: 'large-text',
					rows: '3',
					text: current == null ? '' : String(current)
				});
				control.addEventListener('input', function () {
					values[key] = control.value;
					markDirty();
				});
			} else if (type === 'bool') {
				control = el('select');
				control.appendChild(el('option', { value: '', text: '—' }));
				control.appendChild(
					el('option', {
						value: '1',
						text: i18n.yes || 'Yes',
						selected: current === true || current === 1 || current === '1'
					})
				);
				control.appendChild(
					el('option', {
						value: '0',
						text: i18n.no || 'No',
						selected: current === false || current === 0 || current === '0'
					})
				);
				control.addEventListener('change', function () {
					if (control.value === '') {
						delete values[key];
					} else {
						values[key] = control.value === '1';
					}
					markDirty();
				});
			} else if (type === 'enum' || type === 'term_children') {
				control = el('select');
				control.appendChild(el('option', { value: '', text: '—' }));
				eachOption(def.resolvedOptions, function (val, lab) {
					control.appendChild(
						el('option', {
							value: val,
							text: lab,
							selected: String(current) === val
						})
					);
				});
				control.addEventListener('change', function () {
					values[key] = control.value;
					markDirty();
				});
			} else if (type === 'enum_multi' || type === 'term_children_multi') {
				control = el('select', { multiple: true, size: '5' });
				var curArr = Array.isArray(current) ? current.map(String) : [];
				eachOption(def.resolvedOptions, function (val, lab) {
					control.appendChild(
						el('option', {
							value: val,
							text: lab,
							selected: curArr.indexOf(val) >= 0
						})
					);
				});
				control.addEventListener('change', function () {
					values[key] = Array.prototype.slice
						.call(control.selectedOptions)
						.map(function (o) {
							return o.value;
						});
					markDirty();
				});
			} else if (type === 'measure') {
				var measure = current && typeof current === 'object' ? current : { value: '', unit: 0 };
				var valInput = el('input', {
					type: 'number',
					step: 'any',
					className: 'small-text',
					value: measure.value != null ? String(measure.value) : ''
				});
				var unitSelect = el('select');
				unitSelect.appendChild(el('option', { value: '0', text: '—' }));
				eachOption(def.resolvedOptions, function (val, lab) {
					unitSelect.appendChild(
						el('option', {
							value: val,
							text: lab,
							selected: String(measure.unit || 0) === val
						})
					);
				});
				function syncMeasure() {
					values[key] = {
						value: valInput.value === '' ? '' : Number(valInput.value),
						unit: Number(unitSelect.value) || 0
					};
					markDirty();
				}
				valInput.addEventListener('input', syncMeasure);
				unitSelect.addEventListener('change', syncMeasure);
				control = el('span', { className: 'wpep-measure-fields' }, [valInput, ' ', unitSelect]);
			} else if (type === 'attachment') {
				control = el('input', {
					type: 'number',
					className: 'small-text',
					value: current != null ? String(current) : '',
					placeholder: 'Attachment ID'
				});
				control.addEventListener('input', function () {
					values[key] = control.value === '' ? '' : Number(control.value);
					markDirty();
				});
			} else {
				control = el('input', {
					type: type === 'integer' || type === 'number' ? 'number' : type === 'url' ? 'url' : 'text',
					className: 'regular-text',
					step: type === 'number' ? 'any' : type === 'integer' ? '1' : undefined,
					value: current == null ? '' : String(current)
				});
				control.addEventListener('input', function () {
					if (type === 'integer' || type === 'number') {
						values[key] = control.value === '' ? '' : Number(control.value);
					} else {
						values[key] = control.value;
					}
					markDirty();
				});
			}

			return el('div', { className: 'wpep-part-field' }, [
				el('label', {}, [
					el('strong', { text: label }),
					def.required ? el('span', { className: 'required', text: ' *' }) : null,
					el('div', {}, [control])
				])
			]);
		}

		function save() {
			status.hidden = true;
			if (events) {
				events.emit('part:save-requested', { partId: part.id });
			}
			post('wpep_save_part', {
				part_id: part.id || 0,
				name: nameInput.value,
				categoryIds: selectedCategoryIds(),
				values: values
			})
				.then(function (fresh) {
					self.creating = false;
					self.data = fresh;
					status.hidden = false;
					status.className = 'wpep-editor-status notice notice-success';
					status.textContent = i18n.saved || 'Saved.';
					if (events) {
						events.emit('part:saved', fresh);
						events.emit('part:dirty', { dirty: false });
						(fresh.part.categoryIds || []).forEach(function (cid) {
							events.emit('tree:bump-count', { termId: cid });
						});
					}
					self.render();
				})
				.catch(function (err) {
					status.hidden = false;
					status.className = 'wpep-editor-status notice notice-error';
					status.textContent = err.message;
					if (events) {
						events.emit('part:save-failed', { message: err.message });
					}
				});
		}
	};

	window.WPEP = window.WPEP || {};
	window.WPEP.PartEditorPane = PartEditorPane;
})(window, document);
