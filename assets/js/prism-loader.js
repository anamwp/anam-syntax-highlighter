/**
 * Anam Syntax Highlighter — front-end loader.
 *
 * Runs after Prism has initialised. Adds copy-to-clipboard button fallback
 * positioning and optional filename/language header via data attributes.
 */
(function () {
	'use strict';

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
