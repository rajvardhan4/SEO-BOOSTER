/* SEO Boster: Area page layout editor. Builds the editor from data, saves with AJAX, live preview in an iframe. */
(function ($) {
	'use strict';

	var cfg = window.seobLayout;
	if (!cfg || !$('#seob-le').length) { return; }

	var state = JSON.parse(JSON.stringify(cfg.layout));
	var dirty = false;
	var openId = null;
	var previewTimer = null;
	var currentPage = cfg.pages.length ? cfg.pages[0] : null;

	/* ---------------- Field definitions ---------------- */
	var YES = { t: 'check' };
	var imageOpts = cfg.imageFields;

	var common = [
		{ k: 'bg', l: 'Background', t: 'select', o: { none: 'None', soft: 'Light tint', accent: 'Brand color', dark: 'Dark', image: 'Image from the page' } },
		{ k: 'bg_field', l: 'Background image', t: 'select', o: imageOpts, when: function (s) { return s.bg === 'image'; } },
		{ k: 'overlay', l: 'Image darkness', t: 'range', min: 0, max: 95, unit: '%', when: function (s) { return s.bg === 'image'; } },
		{ k: 'padding', l: 'Spacing', t: 'select', o: { s: 'Small', m: 'Medium', l: 'Large' } },
		{ k: 'align', l: 'Text alignment', t: 'select', o: { left: 'Left', center: 'Center' } },
		{ k: 'hide_d', l: 'Hide on desktop', t: 'check', group: 'Visibility' },
		{ k: 'hide_t', l: 'Hide on tablet', t: 'check', group: 'Visibility' },
		{ k: 'hide_m', l: 'Hide on phone', t: 'check', group: 'Visibility' },
		{ k: 'anchor', l: 'Anchor ID (for links like #faq)', t: 'text', adv: true },
		{ k: 'class', l: 'Extra CSS class', t: 'text', adv: true }
	];
	var callFields = [
		{ k: 'show_call', l: 'Show call button', t: 'check' },
		{ k: 'call_label', l: 'Call button text', t: 'text', when: function (s) { return !!s.show_call; } },
		{ k: 'show_contact', l: 'Show contact button', t: 'check' },
		{ k: 'contact_label', l: 'Contact button text', t: 'text', when: function (s) { return !!s.show_contact; } }
	];
	var typeFields = {
		hero: [
			{ k: 'size', l: 'Banner height', t: 'select', o: { s: 'Short', m: 'Medium', l: 'Tall' } },
			{ k: 'line', l: 'Brand color line under the heading', t: 'check' },
			{ k: 'subtitle', l: 'Subtitle (optional)', t: 'text', hint: 'You can use {service}, {city}, {location}, {business}, {phone}' }
		],
		split: [
			{ k: 'n', l: 'Content from', t: 'select', o: { 2: 'Section 2 fields', 3: 'Section 3 fields', 4: 'Section 4 fields' } },
			{ k: 'side', l: 'Image side', t: 'select', o: { left: 'Image left', right: 'Image right' } },
			{ k: 'show_image', l: 'Show image', t: 'check' },
			{ k: 'show_heading', l: 'Show heading', t: 'check' },
			{ k: 'show_text', l: 'Show text', t: 'check' }
		].concat(callFields),
		boxes: [
			{ k: 'show_heading', l: 'Show heading', t: 'check' },
			{ k: 'columns', l: 'Columns', t: 'select', o: { 1: '1', 2: '2', 3: '3', 4: '4' } },
			{ k: 'style', l: 'Box style', t: 'select', o: { card: 'Cards', plain: 'Plain', line: 'Side line' } },
			{ k: 'show_icon', l: 'Show icon image', t: 'check' },
			{ k: 'count', l: 'Number of boxes', t: 'range', min: 1, max: 6 }
		],
		faq: [
			{ k: 'show_heading', l: 'Show heading', t: 'check' },
			{ k: 'open_first', l: 'First question open', t: 'check' },
			{ k: 'count', l: 'Number of questions', t: 'range', min: 1, max: 10 }
		],
		cta: [
			{ k: 'show_heading', l: 'Show heading', t: 'check' },
			{ k: 'show_text', l: 'Show text', t: 'check' }
		].concat(callFields),
		text: [
			{ k: 'heading', l: 'Heading', t: 'text', hint: 'You can use {service}, {city}, {location}, {business}, {phone}' },
			{ k: 'content', l: 'Text (basic HTML allowed)', t: 'textarea' }
		],
		buttons: callFields
	};

	var globalFields = [
		{ k: 'accent', l: 'Brand color', t: 'color', auto: true },
		{ k: 'accent2', l: 'Second brand color (Call button, open FAQ)', t: 'color', auto: true },
		{ k: 'dark', l: 'Dark color (button hover)', t: 'color' },
		{ k: 'soft', l: 'Light tint color', t: 'color', auto: true },
		{ k: 'button_style', l: 'Buttons', t: 'select', o: { solid: 'Filled', outline: 'Outline' } },
		{ k: 'button_shape', l: 'Button shape', t: 'select', o: { square: 'Square', rounded: 'Rounded', pill: 'Pill' } },
		{ k: 'radius', l: 'Corner roundness', t: 'range', min: 0, max: 40, unit: 'px' },
		{ k: 'heading_size', l: 'Heading size', t: 'select', o: { s: 'Small', m: 'Medium', l: 'Large' } },
		{ k: 'image_ratio', l: 'Image shape', t: 'select', o: { '4/3': '4:3', '1/1': 'Square', '16/9': 'Wide 16:9', auto: 'Original' } },
		{ k: 'max_width', l: 'Content width', t: 'range', min: 800, max: 1600, step: 20, unit: 'px' },
		{ k: 'faq_schema', l: 'FAQ rich results data for Google', t: 'check' }
	];

	/* ---------------- Helpers ---------------- */
	function uid(type) { return type + '_' + Math.random().toString(36).slice(2, 7); }

	function markDirty() {
		dirty = true;
		$('#seob-le-status').text('Unsaved changes').addClass('is-dirty');
		schedulePreview();
	}

	function fieldRow(def, obj, onChange) {
		var id = 'seob-f-' + Math.random().toString(36).slice(2, 8);
		var row = $('<div class="seob-le-field"/>').addClass('is-' + def.t);
		var val = obj[def.k];
		var input;

		if (def.t === 'check') {
			input = $('<input type="checkbox"/>').attr('id', id).prop('checked', !!val);
			row.append($('<label class="seob-check"/>').attr('for', id).append(input).append($('<span/>').text(def.l)));
			input.on('change', function () { obj[def.k] = this.checked ? 1 : 0; onChange(); });
			return row;
		}

		row.append($('<label class="seob-label"/>').attr('for', id).text(def.l));
		if (def.t === 'select') {
			input = $('<select class="seob-select"/>').attr('id', id);
			$.each(def.o, function (v, label) { input.append($('<option/>').val(v).text(label)); });
			input.val(String(val));
			input.on('change', function () { obj[def.k] = isNaN(+this.value) || this.value === '' || /\//.test(this.value) ? this.value : +this.value; onChange(true); });
		} else if (def.t === 'range') {
			var out = $('<span class="seob-le-range-val"/>').text(val + (def.unit || ''));
			input = $('<input type="range"/>').attr({ id: id, min: def.min, max: def.max, step: def.step || 1 }).val(val);
			input.on('input', function () { obj[def.k] = +this.value; out.text(this.value + (def.unit || '')); onChange(); });
			row.append($('<div class="seob-le-range"/>').append(input).append(out));
			return row;
		} else if (def.t === 'textarea') {
			input = $('<textarea class="seob-textarea" rows="5"/>').attr('id', id).val(val);
			input.on('input', function () { obj[def.k] = this.value; onChange(); });
		} else if (def.t === 'color') {
			var wrap = $('<div class="seob-le-color"/>');
			input = $('<input type="color"/>').attr('id', id).val(val || (def.k === 'accent2' ? cfg.autoAccent2 : (def.k === 'soft' ? '#f8f8f8' : cfg.autoAccent)) || '#1e73be');
			input.on('input', function () { obj[def.k] = this.value; autoBtn.toggle(true); onChange(); });
			wrap.append(input);
			var autoBtn = $('<button type="button" class="seob-link-btn"/>').text('Use site color').toggle(!!val && !!def.auto);
			if (def.auto) {
				autoBtn.on('click', function () { obj[def.k] = ''; input.val(def.k === 'accent' ? cfg.autoAccent : '#ffffff'); autoBtn.hide(); onChange(); });
				wrap.append(autoBtn);
				if (!val) { wrap.append($('<span class="seob-help"/>').text('Automatic')); }
			}
			if ((def.k === 'accent' || def.k === 'dark' || def.k === 'soft') && cfg.siteColors.length) {
				var sw = $('<span class="seob-swatches"/>');
				cfg.siteColors.slice(0, 10).forEach(function (c) {
					$('<button type="button" class="seob-swatch"/>').css('background', c.color).attr('title', c.label + ' ' + c.color).on('click', function () {
						obj[def.k] = c.color; input.val(c.color); autoBtn.show(); onChange();
					}).appendTo(sw);
				});
				wrap.append(sw);
			}
			row.append(wrap);
			return row;
		} else {
			input = $('<input type="text" class="seob-input"/>').attr('id', id).val(val);
			input.on('input', function () { obj[def.k] = this.value; onChange(); });
		}
		row.append(input);
		if (def.hint) { row.append($('<p class="seob-help"/>').text(def.hint)); }
		return row;
	}

	/* ---------------- Global style ---------------- */
	function renderGlobal() {
		var box = $('#seob-le-global').empty();
		box.append($('<h3/>').text('Style for all Area pages'));
		if (cfg.presets) {
			var pr = $('<div class="seob-le-preset"/>').append($('<span class="seob-label"/>').text('Start from a design'));
			$.each(cfg.presets, function (key, p) {
				$('<button type="button" class="seob-btn seob-btn-ghost seob-btn-sm"/>').text(p.label).toggleClass('is-current', state.global.preset === key).on('click', function () {
					if (!window.confirm('Replace the current sections and style with "' + p.label + '"? Nothing is saved until you press Save layout.')) { return; }
					var keepAccent = state.global.accent, keepAccent2 = state.global.accent2;
					state = JSON.parse(JSON.stringify(p.layout));
					state.global.accent = keepAccent; state.global.accent2 = keepAccent2;
					openId = null; renderGlobal(); renderList(); markDirty();
				}).appendTo(pr);
			});
			box.append(pr);
		}
		var grid = $('<div class="seob-le-grid"/>');
		globalFields.forEach(function (def) { grid.append(fieldRow(def, state.global, markDirty)); });
		box.append(grid);
	}

	/* ---------------- Sections ---------------- */
	function sectionTitle(sec) {
		var t = cfg.types[sec.type] || sec.type;
		if (sec.type === 'split') { t += ' (Section ' + sec.s.n + ')'; }
		if (sec.type === 'text' && sec.s.heading) { t += ': ' + sec.s.heading; }
		return t;
	}

	function renderBody(sec, body) {
		body.empty();
		var redraw = function (structural) {
			markDirty();
			if (structural) { renderBody(sec, body); body.closest('li').find('.seob-le-title').text(sectionTitle(sec)); }
		};
		var main = $('<div class="seob-le-grid"/>');
		(typeFields[sec.type] || []).forEach(function (def) {
			if (!def.when || def.when(sec.s)) { main.append(fieldRow(def, sec.s, redraw)); }
		});
		body.append(main);

		var design = $('<div class="seob-le-grid"/>');
		var adv = $('<div class="seob-le-grid"/>');
		common.forEach(function (def) {
			if (def.when && !def.when(sec.s)) { return; }
			(def.adv ? adv : design).append(fieldRow(def, sec.s, redraw));
		});
		body.append($('<p class="seob-le-sub"/>').text('Look'));
		body.append(design);
		body.append($('<details class="seob-le-adv"/>').append($('<summary/>').text('Advanced')).append(adv));
	}

	function renderList() {
		var list = $('#seob-le-list').empty();
		state.sections.forEach(function (sec, i) {
			var li = $('<li class="seob-le-item"/>').attr('data-id', sec.id).toggleClass('is-off', !sec.on).toggleClass('is-open', sec.id === openId);
			var head = $('<div class="seob-le-head"/>');
			head.append('<span class="seob-le-handle dashicons dashicons-menu" title="Drag to move"></span>');
			head.append($('<button type="button" class="seob-le-title"/>').text(sectionTitle(sec)));
			var eye = $('<button type="button" class="seob-le-icon"/>').attr('title', sec.on ? 'Hide section' : 'Show section')
				.append($('<span class="dashicons"/>').addClass(sec.on ? 'dashicons-visibility' : 'dashicons-hidden'));
			var dup = $('<button type="button" class="seob-le-icon" title="Duplicate"><span class="dashicons dashicons-admin-page"></span></button>');
			var del = $('<button type="button" class="seob-le-icon is-danger" title="Delete"><span class="dashicons dashicons-trash"></span></button>');
			head.append(eye, dup, del);
			li.append(head);
			var body = $('<div class="seob-le-body"/>');
			li.append(body);
			if (sec.id === openId) { renderBody(sec, body); }

			head.find('.seob-le-title').on('click', function () {
				openId = openId === sec.id ? null : sec.id;
				renderList();
			});
			eye.on('click', function () { sec.on = sec.on ? 0 : 1; renderList(); markDirty(); });
			dup.on('click', function () {
				var copy = JSON.parse(JSON.stringify(sec));
				copy.id = uid(sec.type);
				state.sections.splice(i + 1, 0, copy);
				openId = copy.id;
				renderList(); markDirty();
			});
			del.on('click', function () {
				if (!window.confirm('Delete this section from the layout?')) { return; }
				state.sections.splice(i, 1);
				renderList(); markDirty();
			});
			list.append(li);
		});

		list.sortable({
			handle: '.seob-le-handle',
			axis: 'y',
			placeholder: 'seob-le-placeholder',
			update: function () {
				var order = list.children().map(function () { return $(this).attr('data-id'); }).get();
				state.sections.sort(function (a, b) { return order.indexOf(a.id) - order.indexOf(b.id); });
				markDirty();
			}
		});
	}

	function renderAdd() {
		var sel = $('#seob-le-add-type').empty();
		$.each(cfg.types, function (k, label) { sel.append($('<option/>').val(k).text(label)); });
		$('#seob-le-add').on('click', function () {
			var type = sel.val();
			var sec = { id: uid(type), type: type, on: 1, s: JSON.parse(JSON.stringify(cfg.defaults[type])) };
			state.sections.push(sec);
			openId = sec.id;
			renderList(); markDirty();
			var el = $('#seob-le-list li[data-id="' + sec.id + '"]')[0];
			if (el) { el.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
		});
	}

	/* ---------------- Click a section in the preview to edit it ---------------- */
	function openSection(id) {
		openId = id;
		renderList();
		var el = $('#seob-le-list li[data-id="' + id + '"]')[0];
		if (el) { el.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
	}

	function wireFrame() {
		var doc;
		try { doc = this.contentDocument; } catch (e) { return; }
		if (!doc || !doc.head) { return; }
		var st = doc.createElement('style');
		st.textContent = '[data-seob-sec]{cursor:pointer;transition:outline-color .15s}' +
			'[data-seob-sec]:hover{outline:3px dashed #3b5bdb;outline-offset:-3px}' +
			'[data-seob-sec].seob-le-active{outline:3px solid #3b5bdb;outline-offset:-3px}' +
			'[data-seob-sec]:hover::after{content:attr(data-seob-label);position:absolute;top:8px;left:8px;z-index:9;background:#3b5bdb;color:#fff;font:600 12px/1 sans-serif;padding:6px 9px;border-radius:6px}';
		doc.head.appendChild(st);
		$(doc).find('[data-seob-sec]').each(function () {
			var id = this.getAttribute('data-seob-sec');
			var sec = state.sections.filter(function (x) { return x.id === id; })[0];
			this.setAttribute('data-seob-label', 'Edit: ' + (sec ? sectionTitle(sec) : 'section'));
			if (id === openId) { this.classList.add('seob-le-active'); }
		}).on('click', function (e) {
			e.preventDefault();
			e.stopPropagation();
			openSection(this.getAttribute('data-seob-sec'));
			$(doc).find('.seob-le-active').removeClass('seob-le-active');
			this.classList.add('seob-le-active');
		});
		$(doc).find('a').on('click', function (e) { e.preventDefault(); });
	}

	/* ---------------- Preview and save ---------------- */
	function frameUrl() {
		if (!currentPage) { return ''; }
		var u = currentPage.url;
		return u + (u.indexOf('?') === -1 ? '?' : '&') + 'seob_preview=' + encodeURIComponent(cfg.previewNonce) + '&seob_t=' + Date.now();
	}

	function post(doWhat) {
		return $.post(cfg.ajax, { action: 'seob_ap_layout', nonce: cfg.nonce, do: doWhat, layout: JSON.stringify(state) });
	}

	function refreshPreview() {
		if (!currentPage) { return; }
		$('#seob-le-status').text(dirty ? 'Updating preview...' : '');
		post('preview').done(function () {
			$('#seob-le-frame').attr('src', frameUrl());
			$('#seob-le-status').text(dirty ? 'Unsaved changes' : '');
		}).fail(function () { $('#seob-le-status').text('Preview failed, try again'); });
	}

	function schedulePreview() {
		clearTimeout(previewTimer);
		previewTimer = setTimeout(refreshPreview, 700);
	}

	function setupPreview() {
		var sel = $('#seob-le-page');
		if (!cfg.pages.length) {
			sel.hide(); $('#seob-le-frame').hide(); $('#seob-le-empty').prop('hidden', false); $('#seob-le-open').hide();
			return;
		}
		cfg.pages.forEach(function (p, i) { sel.append($('<option/>').val(i).text(p.title)); });
		$('#seob-le-frame').on('load', wireFrame);
		sel.on('change', function () { currentPage = cfg.pages[+this.value]; $('#seob-le-open').attr('href', currentPage.url); refreshPreview(); });
		$('#seob-le-open').attr('href', currentPage.url);
		$('.seob-le-devices button').on('click', function () {
			$('.seob-le-devices button').removeClass('is-active');
			$(this).addClass('is-active');
			$('#seob-le-frame').css('width', $(this).data('w'));
		});
		refreshPreview();
	}

	$('#seob-le-save').on('click', function () {
		var btn = $(this).prop('disabled', true);
		$('#seob-le-status').text('Saving...');
		post('save').done(function (res) {
			if (res && res.success) {
				state = res.data.layout;
				dirty = false;
				$('#seob-le-status').text('Saved. All Area pages use this layout now.').removeClass('is-dirty');
				$('#seob-le-frame').attr('src', frameUrl());
				renderGlobal(); renderList();
			} else {
				$('#seob-le-status').text('Save failed');
			}
		}).fail(function () { $('#seob-le-status').text('Save failed'); }).always(function () { btn.prop('disabled', false); });
	});

	$('#seob-le-import-toggle').on('click', function () {
		var f = $('#seob-le-import');
		f.prop('hidden', !f.prop('hidden'));
	});

	window.addEventListener('beforeunload', function (e) {
		if (dirty) { e.preventDefault(); e.returnValue = ''; }
	});

	renderGlobal();
	renderList();
	renderAdd();
	setupPreview();
})(jQuery);
