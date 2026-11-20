/* SEO Boster: Area Pages admin (image grid with media picker, live counts, progress runner). */
(function ($) {
	'use strict';

	var cfg = window.seobArea || {};
	var t = cfg.i18n || {};

	function lines(text) {
		var seen = {};
		return String(text || '').split(/\r\n|\r|\n|\|\|/).map(function (l) {
			return l.replace(/^\s*(\d+\s*[\.\)]|[-*•])\s*/, '').replace(/\s+/g, ' ').trim();
		}).filter(function (l) {
			var k = l.toLowerCase();
			if (!l || seen[k]) { return false; }
			seen[k] = true;
			return true;
		});
	}

	/* ---------- Counts and total ---------- */
	function updateCounts() {
		var s = lines($('#seob-services').val()).length;
		var l = lines($('#seob-locations').val()).length;
		$('#seob-count-services').text(s);
		$('#seob-count-locations').text(l);
		var total = s * l;
		$('#seob-total').html(total ? '<strong>' + total + '</strong> pages will be created (' + s + ' services x ' + l + ' locations)' : '')
			.toggleClass('is-over', total > 1000);
	}

	/* ---------- Image grid ---------- */
	var grid = $('#seob-image-grid');
	var state = {};

	function saveState() {
		grid.find('.seob-img-cell').each(function () {
			var c = $(this);
			var key = c.data('field') + '|' + c.data('service');
			state[key] = { id: c.find('input.seob-img-id').val(), url: c.find('img').attr('src') || '', auto: c.find('input.seob-img-auto').val() };
		});
	}

	function cell(field, idx, serviceName) {
		var key = field + '|' + serviceName.toLowerCase();
		var st = state[key] || { id: '', url: '', auto: '' };
		var c = $('<div class="seob-img-cell"/>').attr('data-field', field).attr('data-service', serviceName.toLowerCase());
		c.append($('<input type="hidden" class="seob-img-id"/>').attr('name', 'images[' + field + '][' + idx + ']').val(st.id));
		c.append($('<input type="hidden" class="seob-img-auto"/>').attr('name', 'images_auto[' + field + '][' + idx + ']').val(st.auto || ''));
		var thumb = $('<div class="seob-img-thumb"/>');
		if (st.url) { thumb.append($('<img alt=""/>').attr('src', st.url)); }
		c.append(thumb);
		var actions = $('<div class="seob-img-actions"/>');
		actions.append($('<button type="button" class="seob-link-btn seob-img-pick"/>').text(st.id ? t.change : t.choose));
		actions.append($('<button type="button" class="seob-link-btn seob-danger seob-img-clear"/>').text(t.remove).toggle(!!st.id));
		if (idx === 0) {
			actions.append($('<button type="button" class="seob-link-btn seob-img-all"/>').text(t.useAll).toggle(!!st.id));
		}
		c.append(actions);
		return c;
	}

	function renderGrid() {
		if (!grid.length) { return; }
		saveState();
		var services = lines($('#seob-services').val());
		grid.empty();
		if (!services.length) {
			grid.append($('<p class="seob-help"/>').text(t.noService));
			return;
		}
		var table = $('<table class="seob-table seob-img-table"/>');
		var head = $('<tr/>').append('<th>Service</th>');
		$.each(cfg.imageNames || {}, function (field, label) { head.append($('<th/>').text(label)); });
		table.append($('<thead/>').append(head));
		var body = $('<tbody/>');
		services.forEach(function (name, idx) {
			var row = $('<tr/>').append($('<td/>').append($('<strong/>').text(name)));
			$.each(cfg.imageNames || {}, function (field) { row.append($('<td/>').append(cell(field, idx, name))); });
			body.append(row);
		});
		table.append(body);
		grid.append(table);
	}

	function setCell(c, id, url, auto) {
		c.find('input.seob-img-id').val(id || '');
		c.find('input.seob-img-auto').val(id && auto ? '1' : '');
		var thumb = c.find('.seob-img-thumb').empty();
		if (url) { thumb.append($('<img alt=""/>').attr('src', url)); }
		c.find('.seob-img-pick').text(id ? t.change : t.choose);
		c.find('.seob-img-clear, .seob-img-all').toggle(!!id);
	}

	grid.on('click', '.seob-img-pick', function () {
		var c = $(this).closest('.seob-img-cell');
		if (!window.wp || !wp.media) { return; }
		var frame = wp.media({ title: t.pickTitle, library: { type: 'image' }, multiple: false });
		frame.on('select', function () {
			var a = frame.state().get('selection').first().toJSON();
			var url = (a.sizes && (a.sizes.thumbnail || a.sizes.medium) ? (a.sizes.thumbnail || a.sizes.medium).url : a.url);
			setCell(c, a.id, url);
		});
		frame.open();
	});

	grid.on('click', '.seob-img-clear', function () {
		setCell($(this).closest('.seob-img-cell'), '', '');
	});

	grid.on('click', '.seob-img-all', function () {
		var src = $(this).closest('.seob-img-cell');
		var field = src.data('field');
		var id = src.find('input.seob-img-id').val();
		var url = src.find('img').attr('src');
		grid.find('.seob-img-cell[data-field="' + field + '"]').each(function () { setCell($(this), id, url); });
	});

	/* ---------- Auto-fill images ---------- */
	$('#seob-auto-images').on('click', function () {
		var btn = $(this).prop('disabled', true);
		$('#seob-img-status').text(t.finding);
		$.post(cfg.ajax, {
			action: 'seob_ap_images',
			nonce: cfg.imgNonce,
			services: $('#seob-services').val(),
			keywords: $('#seob-keywords').val(),
			business: $('[name="business"]').val() || '',
			pexels: $('#seob-use-pexels').is(':checked') ? 1 : 0
		}).done(function (res) {
			if (!res || !res.success) { $('#seob-img-status').text(t.noneFound); return; }
			var filled = 0;
			var services = lines($('#seob-services').val());
			$.each(res.data.grid, function (field, cells) {
				$.each(cells, function (idx, img) {
					if (!img || !img.id || !services[idx]) { return; }
					var c = grid.find('.seob-img-cell[data-field="' + field + '"][data-service="' + services[idx].toLowerCase().replace(/"/g, '') + '"]');
					// Cells picked by hand stay; empty and earlier auto-filled cells get the new pick.
					if (c.length && (!c.find('input.seob-img-id').val() || c.find('input.seob-img-auto').val() === '1')) { setCell(c, img.id, img.url, true); filled++; }
				});
			});
			var empty = grid.find('.seob-img-cell').filter(function () { return !$(this).find('input.seob-img-id').val(); }).length;
			$('#seob-img-status').text(!filled && empty ? t.noneFound : (empty ? (t.someEmpty || t.found).replace('%d', empty) : t.found));
		}).fail(function () { $('#seob-img-status').text(t.noneFound); })
		  .always(function () { btn.prop('disabled', false); });
	});

	$('#seob-services').on('input', function () { updateCounts(); renderGrid(); });
	$('#seob-locations').on('input', updateCounts);
	updateCounts();
	renderGrid();

	/* ---------- Menu order (drag and drop) ---------- */
	if ($.fn.sortable) {
		$('[data-seob-sort]').sortable({ axis: 'y', placeholder: 'seob-sort-ph' });
	}

	/* ---------- Design screen ---------- */
	$(document).on('click', '[data-seob-color]', function () {
		$($(this).data('seob-target')).val($(this).data('seob-color'));
	});
	function showMaps() {
		var mode = $('#seob-design-form input[name="mode"]:checked').val();
		$('.seob-color-map').each(function () { $(this).toggle($(this).data('seob-mode') === mode); });
	}
	$('#seob-design-form').on('change', 'input[name="mode"]', showMaps);
	showMaps();

	/* ---------- Progress runner ---------- */
	var box = $('#seob-progress');
	if (box.length) {
		var job = box.data('job');
		var running = box.data('status') === 'running';
		var errors = 0;

		var paint = function (r) {
			var c = r.counts || {};
			var pct = c.total ? Math.floor(c.finished / c.total * 100) : 0;
			$('#seob-bar-fill').css('width', pct + '%');
			$('#seob-pct').text(pct + '%');
			$('#seob-done').text(c.finished || 0);
			['done', 'warning', 'skipped', 'failed'].forEach(function (k) { $('#seob-c-' + k).text(c[k] || 0); });
		};

		var stopped = false;
		var lastText = '';
		var tick = function () {
			if (stopped) { return; }
			$.post(cfg.ajax, { action: 'seob_ap_step', nonce: cfg.nonce, job: job }).done(function (res) {
				errors = 0;
				if (!res || !res.success) { $('#seob-state').text('Error'); return; }
				var r = res.data;
				paint(r);
				if (r.status === 'finished' || r.status === 'paused') {
					if (!stopped) {
						stopped = true;
						$('#seob-state').text(r.status === 'finished' ? t.done : t.paused);
						setTimeout(function () { window.location.reload(); }, 1500);
					}
					return;
				}
				if (r.last && r.last.title) {
					lastText = r.last.title + ' - ' + (r.last.status === 'checking' ? 'improving the text' : r.last.status);
					$('#seob-state').text(lastText);
				} else if (r.status === 'busy' && !lastText) {
					$('#seob-state').text(t.busy);
				}
				// No free page for this worker right now: check again shortly.
				setTimeout(tick, r.status === 'busy' ? 5000 : 200);
			}).fail(function () {
				errors++;
				setTimeout(tick, Math.min(30000, 3000 * errors));
			});
		};
		if (running) {
			var workers = Math.max(1, Math.min(8, parseInt(cfg.parallel, 10) || 4));
			for (var w = 0; w < workers; w++) { setTimeout(tick, w * 700); }
		}
	}

	/* ---------- SEMrush Bulk Drop-In & Query Copier ---------- */
	$('#seob-copy-semrush-btn').on('click', function () {
		var services = lines($('#seob-services').val());
		var locations = lines($('#seob-locations').val());
		if (!services.length || !locations.length) {
			alert('Please enter at least one service and one location first.');
			return;
		}
		var queries = [];
		services.forEach(function (s) {
			locations.forEach(function (loc) {
				var city = loc.split(',')[0].trim();
				queries.push(s + ' ' + city);
			});
		});
		var text = queries.join("\n");
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).then(function () {
				alert(queries.length + ' query strings copied to clipboard! Paste directly into SEMrush Bulk Analysis.');
			});
		} else {
			window.prompt('Copy query list (Ctrl+C, Enter):', text);
		}
	});

	$('#seob-semrush-csv-input').on('change', function (e) {
		var file = e.target.files[0];
		if (!file) { return; }
		var services = $('#seob-services').val();
		var locations = $('#seob-locations').val();
		if (!lines(services).length || !lines(locations).length) {
			alert('Please enter your services and locations first so SEMrush keywords can be clustered.');
			return;
		}

		var reader = new FileReader();
		reader.onload = function (evt) {
			var content = evt.target.result;
			var btn = $('#seob-semrush-csv-input').closest('label');
			var origText = btn.html();
			btn.css('opacity', '0.6').text('Parsing & Clustering...');

			$.post(cfg.ajax, {
				action: 'seob_ap_semrush_csv',
				nonce: cfg.semrushNonce,
				csv_content: content,
				services: services,
				locations: locations
			}).done(function (res) {
				btn.css('opacity', '1').html(origText);
				if (res && res.success && res.data.keywords_text) {
					$('#seob-keywords').val(res.data.keywords_text);
					alert('Success! ' + res.data.total_rows + ' SEMrush keywords parsed and clustered across ' + res.data.clusters + ' service-location targets (Primary by Volume & lowest KD%).');
				} else {
					alert('Could not parse SEMrush CSV: ' + (res && res.data && res.data.message ? res.data.message : 'Unknown error'));
				}
			}).fail(function () {
				btn.css('opacity', '1').html(origText);
				alert('AJAX error while parsing SEMrush CSV.');
			});
		};
		reader.readAsText(file);
	});
})(jQuery);
