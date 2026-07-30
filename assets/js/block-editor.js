/**
 * Anam Syntax Highlighter — block editor sidebar control.
 *
 * Adds a "Syntax Highlighting" panel to the Code block's Inspector sidebar
 * so authors can choose the language used for highlighting (CSS/SCSS,
 * JavaScript, PHP, WordPress, etc.) on a per-block basis. The choice is
 * stored as a `data-lang` attribute on the saved <pre> element, which the
 * front-end renderer (Anam_SH_Asset_Loader::filter_content) already reads.
 */
(function (wp) {
	'use strict';

	if (!wp || !wp.hooks || !wp.element || !wp.compose || !wp.components) {
		return;
	}

	var addFilter = wp.hooks.addFilter;
	var createElement = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var createHigherOrderComponent = wp.compose.createHigherOrderComponent;
	var InspectorControls = (wp.blockEditor || wp.editor).InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var __ = wp.i18n.__;

	var TARGET_BLOCK = 'core/code';
	var languages = (window.anamSHBlockEditor && window.anamSHBlockEditor.languages) || {};

	/**
	 * Register an `anamLanguage` attribute on the Code block, sourced from
	 * the `data-lang` attribute on the saved <pre> element.
	 */
	function addLanguageAttribute(settings, name) {
		if (name !== TARGET_BLOCK) {
			return settings;
		}

		settings.attributes = Object.assign({}, settings.attributes, {
			anamLanguage: {
				type: 'string',
				source: 'attribute',
				selector: 'pre',
				attribute: 'data-lang',
				default: '',
			},
		});

		return settings;
	}
	addFilter('blocks.registerBlockType', 'anam-sh/add-language-attribute', addLanguageAttribute);

	/**
	 * Write the chosen language back onto the saved <pre> element without
	 * touching the Code block's own save() output.
	 */
	function addLanguageSaveProp(extraProps, blockType, attributes) {
		if (blockType.name !== TARGET_BLOCK || !attributes.anamLanguage) {
			return extraProps;
		}

		extraProps['data-lang'] = attributes.anamLanguage;

		return extraProps;
	}
	addFilter('blocks.getSaveContent.extraProps', 'anam-sh/add-language-save-prop', addLanguageSaveProp);

	/**
	 * Inject the language dropdown into the Code block's Inspector sidebar.
	 */
	var withLanguageControl = createHigherOrderComponent(function (BlockEdit) {
		return function (props) {
			if (props.name !== TARGET_BLOCK) {
				return createElement(BlockEdit, props);
			}

			var options = [{ label: __('Default (from settings)', 'anam-syntax-highlighter'), value: '' }].concat(
				Object.keys(languages).map(function (slug) {
					return { label: languages[slug], value: slug };
				})
			);

			return createElement(
				Fragment,
				null,
				createElement(BlockEdit, props),
				createElement(
					InspectorControls,
					null,
					createElement(
						PanelBody,
						{ title: __('Syntax Highlighting', 'anam-syntax-highlighter') },
						createElement(SelectControl, {
							label: __('Language', 'anam-syntax-highlighter'),
							value: props.attributes.anamLanguage || '',
							options: options,
							onChange: function (value) {
								props.setAttributes({ anamLanguage: value });
							},
						})
					)
				)
			);
		};
	}, 'withLanguageControl');

	addFilter('editor.BlockEdit', 'anam-sh/with-language-control', withLanguageControl);
})(window.wp);
