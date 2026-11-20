/* SEO Boster admin helpers. No dependencies. */
(function () {
	'use strict';

	var i18n = window.seobAdmin || { copied: 'Copied', confirm: 'Are you sure?' };

	// Live keyword counter.
	document.querySelectorAll('[data-seob-count]').forEach(function (area) {
		var target = document.querySelector(area.getAttribute('data-seob-count'));
		if (!target) { return; }
		var update = function () {
			var seen = {};
			var n = 0;
			area.value.split(/[\r\n,;]+/).forEach(function (line) {
				var k = line.replace(/^\s*(\d+\s*[\.\):-]|[-*•])\s*/, '').trim().toLowerCase();
				if (k && !seen[k]) { seen[k] = true; n++; }
			});
			target.textContent = n;
			target.parentNode.classList.toggle('is-over', n > 200);
		};
		area.addEventListener('input', update);
		update();
	});

	// Confirm before destructive forms.
	document.querySelectorAll('form[data-seob-confirm]').forEach(function (form) {
		form.addEventListener('submit', function (e) {
			if (!window.confirm(form.getAttribute('data-seob-confirm') || i18n.confirm)) {
				e.preventDefault();
			}
		});
	});

	// Copy link buttons.
	document.querySelectorAll('[data-seob-copy]').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var text = btn.getAttribute('data-seob-copy');
			var done = function () {
				var old = btn.textContent;
				btn.textContent = i18n.copied;
				setTimeout(function () { btn.textContent = old; }, 1400);
			};
			if (navigator.clipboard && window.isSecureContext) {
				navigator.clipboard.writeText(text).then(done);
			} else {
				var t = document.createElement('textarea');
				t.value = text;
				document.body.appendChild(t);
				t.select();
				try { document.execCommand('copy'); done(); } catch (err) {}
				document.body.removeChild(t);
			}
		});
	});

	// Filter review rows.
	document.querySelectorAll('[data-seob-filter]').forEach(function (input) {
		var table = document.querySelector(input.getAttribute('data-seob-filter'));
		if (!table) { return; }
		input.addEventListener('input', function () {
			var q = input.value.trim().toLowerCase();
			table.querySelectorAll('tbody tr').forEach(function (row) {
				row.style.display = !q || (row.getAttribute('data-seob-row') || '').indexOf(q) !== -1 ? '' : 'none';
			});
		});
	});

	// Warn if a line no longer contains its keyword.
	document.querySelectorAll('#seob-review-table tbody tr').forEach(function (row) {
		var kw = (row.getAttribute('data-seob-row') || '').trim();
		var box = row.querySelector('.seob-sentence');
		if (!kw || !box) { return; }
		var check = function () {
			box.classList.toggle('is-missing', box.value.toLowerCase().indexOf(kw) === -1);
		};
		box.addEventListener('input', check);
		check();
	});
})();
