/**
 * Anam Syntax Highlighter — front-end loader.
 *
 * Runs after Prism has initialised. Adds copy-to-clipboard button fallback
 * positioning and optional filename/language header via data attributes.
 */
(function () {
	'use strict';

	// Lucide "copy" and "check" icons (24x24, stroke-based). Inserted as real
	// SVG elements — not a background/mask image — so `stroke="currentColor"`
	// inherits the button's color and follows whichever Prism theme is active.
	var COPY_ICON =
		'<svg class="anam-copy-icon anam-copy-icon-copy" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' +
		'<rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>' +
		'<path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>' +
		'</svg>';
	var SUCCESS_ICON =
		'<svg class="anam-copy-icon anam-copy-icon-success" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' +
		'<polyline points="20 6 9 17 4 12"></polyline>' +
		'</svg>';

	/**
	 * Swap the default "Copy" text button for icon + visually-hidden label.
	 * The label span is left in place (and still updated by
	 * prism-copy-to-clipboard.js on click) so screen readers keep announcing
	 * "Copy" / "Copied!" / "Press Ctrl+C to copy".
	 */
	function iconifyCopyButtons(block) {
		var buttons = block.querySelectorAll('.copy-to-clipboard-button');
		buttons.forEach(function (button) {
			if (button.querySelector('.anam-copy-icon')) {
				return;
			}
			var label = button.querySelector('span');
			if (label) {
				label.classList.add('anam-copy-label');
			}
			button.insertAdjacentHTML('afterbegin', COPY_ICON + SUCCESS_ICON);
		});
	}

	// The copy-to-clipboard button is created by the toolbar plugin inside
	// Prism's 'complete' hook, which can fire well after DOMContentLoaded
	// (the autoloader plugin fetches missing language grammars asynchronously
	// before highlighting, so a block's toolbar may not exist yet at
	// DOMContentLoaded time). Hooking 'complete' ourselves — registered after
	// prism-toolbar.js's own 'complete' hook, since that script runs first —
	// guarantees the button already exists whenever ours runs, for every
	// block regardless of load timing.
	if (typeof Prism !== 'undefined' && Prism.hooks) {
		Prism.hooks.add('complete', function (env) {
			var block = env.element.closest('.anam-sh-block');
			if (block) {
				iconifyCopyButtons(block);
			}
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		var blocks = document.querySelectorAll('.anam-sh-block');

		blocks.forEach(function (block) {
			var codeEl = block.querySelector('code[class*="language-"]');
			if (!codeEl) {
				return;
			}

			// If the header was rendered server-side we don't need to do anything
			// extra here, but if for some reason it was missed we can add one.
			if (typeof anamSH !== 'undefined' && anamSH.showHeader && !block.querySelector('.anam-sh-header')) {
				var lang = '';
				var match = (codeEl.className || '').match(/language-(\w+)/);
				if (match) {
					lang = match[1].toUpperCase();
				}

				var filename = codeEl.getAttribute('data-filename');
				var label = filename || lang;

				if (label) {
					var header = document.createElement('div');
					header.className = 'anam-sh-header';
					var span = document.createElement('span');
					span.className = 'anam-sh-label';
					span.textContent = label;
					header.appendChild(span);
					block.insertBefore(header, block.firstChild);
				}
			}
		});
	});
})();
