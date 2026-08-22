<?php
/**
 * Handles front-end asset loading and content filtering for syntax highlighting.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Anam_SH_Asset_Loader {

	/** @var array Plugin options. */
	private $options;

	public function __construct() {
		$this->options = anam_sh_get_options();

		add_filter( 'the_content', array( $this, 'filter_content' ), 20 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Detect <pre><code> blocks and ensure they have the proper language class
	 * and optional line-numbers / header markup.
	 *
	 * @param string $content Post content.
	 * @return string Modified content.
	 */
	public function filter_content( $content ) {
		if ( ! is_singular() ) {
			return $content;
		}

		if ( strpos( $content, '<code' ) === false ) {
			return $content;
		}

		$default_lang = sanitize_text_field( $this->options['default_language'] );
		$show_header  = (bool) $this->options['show_header'];
		$line_numbers = (bool) $this->options['line_numbers'];
		$theme_slug   = sanitize_html_class( $this->options['theme'] );

		// Match <pre…><code…>…</code></pre> blocks.
		$content = preg_replace_callback(
			'#(<pre(?P<pre_attrs>[^>]*)>)\s*(<code(?P<code_attrs>[^>]*)>)(?P<code_body>.*?)</code>\s*</pre>#si',
			function ( $m ) use ( $default_lang, $show_header, $line_numbers, $theme_slug ) {
				$pre_attrs  = $m['pre_attrs'];
				$code_attrs = $m['code_attrs'];
				$code_body  = $m['code_body'];

				// Detect language using priority order:
				// 1. data-lang attribute (highest priority)
				// 2. language-xxx class (medium priority)
				// 3. data-filename extension inference
				// 4. Raw-SQL content sniffing (e.g. a block starting with
				//    `SELECT …` that was typed with no language hint at
				//    all — without this it would silently inherit the site
				//    default, usually PHP/WordPress, which doesn't
				//    recognize SQL keywords and leaves the whole block
				//    barely tokenized)
				// 5. Global default fallback (lowest priority)
				$language = $default_lang;

				if ( preg_match( '/data-lang="([a-zA-Z0-9_-]+)"/', $code_attrs, $lang_m ) ) {
					$language = $lang_m[1];
				} elseif ( preg_match( '/data-lang="([a-zA-Z0-9_-]+)"/', $pre_attrs, $lang_m ) ) {
					$language = $lang_m[1];
				} elseif ( preg_match( '/class="[^"]*language-(\w+)/', $code_attrs, $lang_m ) ) {
					$language = $lang_m[1];
				} elseif ( preg_match( '/class="[^"]*language-(\w+)/', $pre_attrs, $lang_m ) ) {
					$language = $lang_m[1];
				} else {
					// Infer language from data-filename extension.
					$fn_attrs = $code_attrs . ' ' . $pre_attrs;
					if ( preg_match( '/data-filename="[^"]*\.([a-zA-Z][a-zA-Z0-9]*)\"/', $fn_attrs, $fn_m ) ) {
						$ext_map = self::get_extension_language_map();
						$ext     = strtolower( $fn_m[1] );
						if ( isset( $ext_map[ $ext ] ) ) {
							$language = $ext_map[ $ext ];
						}
					} elseif ( self::looks_like_sql( $code_body ) ) {
						$language = 'sql';
					}
				}

				// Ensure <code> has the language class.
				if ( strpos( $code_attrs, 'language-' ) === false ) {
					if ( preg_match( '/class="([^"]*)"/', $code_attrs, $cls_m ) ) {
						$code_attrs = str_replace(
							'class="' . $cls_m[1] . '"',
							'class="' . $cls_m[1] . ' language-' . esc_attr( $language ) . '"',
							$code_attrs
						);
					} else {
						$code_attrs .= ' class="language-' . esc_attr( $language ) . '"';
					}
				}

				// Add line-numbers class to <pre> if enabled.
				if ( $line_numbers ) {
					if ( preg_match( '/class="([^"]*)"/', $pre_attrs, $pre_cls ) ) {
						if ( strpos( $pre_cls[1], 'line-numbers' ) === false ) {
							$pre_attrs = str_replace(
								'class="' . $pre_cls[1] . '"',
								'class="' . $pre_cls[1] . ' line-numbers"',
								$pre_attrs
							);
						}
					} else {
						$pre_attrs .= ' class="line-numbers"';
					}
				}

				// Build header label.
				$header = '';
				if ( $show_header ) {
					$filename = '';
					if ( preg_match( '/data-filename="([^"]*)"/', $code_attrs, $fn_m ) ) {
						$filename = esc_html( $fn_m[1] );
					}
					$label  = $filename ? $filename : strtoupper( esc_html( $language ) );
					$header = '<div class="anam-sh-header"><span class="anam-sh-label">' . $label . '</span></div>';
				}

				// The block's alignwide/alignfull/etc. class lives on the <pre> (that's
				// what core/code saves). Since we wrap the <pre> in our own div, that
				// div becomes the new direct child of the content container, so themes
				// that key their layout CSS off the *outermost* element's align class
				// (e.g. `.entry-content > *:not(.alignwide)`) never see it. Mirror the
				// align class onto our wrapper so width/alignment keeps working.
				$wrapper_classes = 'anam-sh-block anam-sh-theme-' . $theme_slug;
				if ( preg_match( '/class="([^"]*)"/', $pre_attrs, $pre_cls_m )
					&& preg_match_all( '/\balign(?:wide|full|left|right|center)\b/', $pre_cls_m[1], $align_m )
				) {
					$wrapper_classes .= ' ' . implode( ' ', $align_m[0] );

					// Strip the align class from the <pre> itself so theme CSS
					// targeting `.alignwide`/`.alignfull` etc. only applies once,
					// on the outer wrapper that's now the actual aligned element.
					$new_pre_class = trim( preg_replace( '/\balign(?:wide|full|left|right|center)\b/', '', $pre_cls_m[1] ) );
					$new_pre_class = preg_replace( '/\s+/', ' ', $new_pre_class );
					$pre_attrs     = str_replace(
						'class="' . $pre_cls_m[1] . '"',
						'class="' . $new_pre_class . '"',
						$pre_attrs
					);
				}

				return '<div class="' . esc_attr( $wrapper_classes ) . '">'
					. $header
					. '<pre' . $pre_attrs . '><code' . $code_attrs . '>' . $code_body . '</code></pre>'
					. '</div>';
			},
			$content
		);

		return $content;
	}

	/**
	 * Enqueue Prism assets on singular posts/pages that contain code blocks.
	 */
	public function enqueue_assets() {
		if ( ! is_singular() ) {
			return;
		}

		$post = get_post();
		if ( ! $post || strpos( $post->post_content, '<code' ) === false ) {
			return;
		}

		$url     = ANAM_SH_PLUGIN_URL;
		$version = ANAM_SH_VERSION;

		// Theme CSS.
		$theme     = sanitize_file_name( $this->options['theme'] );
		$theme_map = self::get_theme_files();

		if ( isset( $theme_map[ $theme ] ) ) {
			$theme_file = $theme_map[ $theme ];
		} else {
			$theme_file = 'themes/prism-okaidia.min.css';
		}

		wp_enqueue_style(
			'anam-sh-prism-theme',
			$url . 'vendor/prism/' . $theme_file,
			array(),
			$version
		);

		// Line-numbers CSS (if enabled).
		if ( $this->options['line_numbers'] ) {
			wp_enqueue_style(
				'anam-sh-line-numbers',
				$url . 'vendor/prism/plugins/line-numbers/prism-line-numbers.min.css',
				array( 'anam-sh-prism-theme' ),
				$version
			);
		}

		// Toolbar CSS (needed for copy button).
		if ( $this->options['copy_button'] ) {
			wp_enqueue_style(
				'anam-sh-toolbar',
				$url . 'vendor/prism/plugins/toolbar/prism-toolbar.min.css',
				array( 'anam-sh-prism-theme' ),
				$version
			);
		}

		// Front-end custom styles.
		wp_enqueue_style(
			'anam-sh-front',
			$url . 'assets/css/admin.css',
			array( 'anam-sh-prism-theme' ),
			$version
		);

		// Inline code styling (e.g. `code` in a paragraph) — opt-in. When off,
		// inline code is left alone so the active theme's own default styling
		// applies. Code blocks are unaffected either way; that CSS lives in
		// admin.css and always loads.
		if ( $this->options['inline_code_style'] ) {
			wp_add_inline_style( 'anam-sh-front', self::get_inline_code_css() );
		}

		// Custom CSS override.
		$custom_css = wp_strip_all_tags( $this->options['custom_css'] );
		if ( $custom_css ) {
			wp_add_inline_style( 'anam-sh-front', $custom_css );
		}

		// Prism core JS.
		wp_enqueue_script(
			'anam-sh-prism',
			$url . 'vendor/prism/prism.js',
			array(),
			$version,
			true
		);

		// Autoloader plugin — tells Prism where to find language components.
		wp_enqueue_script(
			'anam-sh-autoloader',
			$url . 'vendor/prism/plugins/autoloader/prism-autoloader.min.js',
			array( 'anam-sh-prism' ),
			$version,
			true
		);

		// Pass the languages path to autoloader.
		wp_add_inline_script(
			'anam-sh-autoloader',
			'Prism.plugins.autoloader.languages_path = ' . wp_json_encode( $url . 'vendor/prism/components/' ) . ';',
			'before'
		);

		// Line-numbers JS.
		if ( $this->options['line_numbers'] ) {
			wp_enqueue_script(
				'anam-sh-line-numbers',
				$url . 'vendor/prism/plugins/line-numbers/prism-line-numbers.min.js',
				array( 'anam-sh-prism' ),
				$version,
				true
			);
		}

		// Copy-to-clipboard.
		if ( $this->options['copy_button'] ) {
			wp_enqueue_script(
				'anam-sh-toolbar',
				$url . 'vendor/prism/plugins/toolbar/prism-toolbar.min.js',
				array( 'anam-sh-prism' ),
				$version,
				true
			);
			wp_enqueue_script(
				'anam-sh-copy',
				$url . 'vendor/prism/plugins/copy-to-clipboard/prism-copy-to-clipboard.min.js',
				array( 'anam-sh-toolbar' ),
				$version,
				true
			);
		}

		// Custom front-end loader JS.
		wp_enqueue_script(
			'anam-sh-loader',
			$url . 'assets/js/prism-loader.js',
			array( 'anam-sh-prism' ),
			$version,
			true
		);

		// Explicitly load the PHP component so the WordPress grammar can extend it.
		// PHP requires markup-templating.
		wp_enqueue_script(
			'anam-sh-markup-templating',
			$url . 'vendor/prism/components/prism-markup-templating.min.js',
			array( 'anam-sh-prism' ),
			$version,
			true
		);

		wp_enqueue_script(
			'anam-sh-lang-php',
			$url . 'vendor/prism/components/prism-php.min.js',
			array( 'anam-sh-markup-templating' ),
			$version,
			true
		);

		// Load TypeScript component so the MCP grammar can extend it.
		wp_enqueue_script(
			'anam-sh-lang-typescript',
			$url . 'vendor/prism/components/prism-typescript.min.js',
			array( 'anam-sh-prism' ),
			$version,
			true
		);

		// WordPress custom grammar — extends PHP with WP-specific tokens.
		wp_enqueue_script(
			'anam-sh-wordpress',
			$url . 'assets/js/prism-wordpress.js',
			array( 'anam-sh-lang-php' ),
			$version,
			true
		);

		// MCP custom grammar — extends TypeScript/JS with MCP SDK tokens.
		wp_enqueue_script(
			'anam-sh-mcp',
			$url . 'assets/js/prism-mcp.js',
			array( 'anam-sh-lang-typescript' ),
			$version,
			true
		);

		wp_localize_script( 'anam-sh-loader', 'anamSH', array(
			'copyButton' => (bool) $this->options['copy_button'],
			'showHeader' => (bool) $this->options['show_header'],
		) );
	}

	/**
	 * CSS for WordPress's "Inline code" text format (a bare <code> inside a
	 * paragraph/list/heading), gated behind the "Style Inline Code" setting.
	 * Excludes `[class*="language-"]` so it never touches Prism-highlighted
	 * code blocks — those always carry a language-* class, added by
	 * filter_content() above. A translucent background is used instead of a
	 * flat color so it adapts to whatever background it's placed on.
	 *
	 * @return string
	 */
	public static function get_inline_code_css() {
		return 'code:not([class*="language-"]) {'
			. 'background: rgba(135, 131, 120, 0.15);'
			. 'color: #8f3417;'
			. 'padding: 0.2em 0.45em;'
			. 'border-radius: 4px;'
			. 'font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;'
			. 'font-size: 0.875em;'
			. 'word-break: break-word;'
			. '}';
	}

	/**
	 * Best-effort detection of raw SQL in an unlabeled code block. Only
	 * consulted when no data-lang/language class/filename hint exists, so
	 * a block like `SELECT * FROM wp_bookings WHERE id = 42` gets tagged
	 * `language-sql` instead of silently inheriting the site's default
	 * fallback language (commonly PHP/WordPress, whose grammar doesn't
	 * recognize SQL keywords and leaves the block almost entirely
	 * untokenized).
	 *
	 * @param string $code_body Raw (still HTML-escaped) code block content.
	 * @return bool
	 */
	private static function looks_like_sql( $code_body ) {
		$text = trim( html_entity_decode( wp_strip_all_tags( $code_body ), ENT_QUOTES ) );

		return (bool) preg_match(
			'/^(SELECT|INSERT\s+INTO|UPDATE|DELETE\s+FROM|CREATE\s+(?:TABLE|DATABASE|INDEX|VIEW)|ALTER\s+TABLE|DROP\s+(?:TABLE|DATABASE|INDEX|VIEW)|TRUNCATE\s+TABLE|REPLACE\s+INTO|EXPLAIN)\b/i',
			$text
		);
	}

	/**
	 * Map of file extensions to Prism language identifiers.
	 *
	 * Used for inferring the language from a data-filename attribute.
	 *
	 * @return array Extension (lowercase) => language slug.
	 */
	public static function get_extension_language_map() {
		return array(
			'php'  => 'php',
			'sql'  => 'sql',
			'css'  => 'css',
			'scss' => 'scss',
			'sass' => 'sass',
			'js'   => 'javascript',
			'ts'   => 'typescript',
			'json' => 'json',
			'yaml' => 'yaml',
			'yml'  => 'yaml',
			'html' => 'markup',
			'htm'  => 'markup',
			'xml'  => 'markup',
			'sh'   => 'bash',
			'bash' => 'bash',
			'py'   => 'python',
			'rb'   => 'ruby',
			'go'   => 'go',
			'rs'   => 'rust',
			'java' => 'java',
			'c'    => 'c',
			'cpp'  => 'cpp',
			'cs'   => 'csharp',
		);
	}

	/**
	 * Map of theme slug → file path relative to vendor/prism/.
	 *
	 * @return array
	 */
	public static function get_theme_files() {
		return array(
			// — built-in Prism themes —
			'default'        => 'themes/prism.min.css',
			'coy'            => 'themes/prism-coy.min.css',
			'dark'           => 'themes/prism-dark.min.css',
			'funky'          => 'themes/prism-funky.min.css',
			'okaidia'        => 'themes/prism-okaidia.min.css',
			'solarizedlight' => 'themes/prism-solarizedlight.min.css',
			'tomorrow'       => 'themes/prism-tomorrow.min.css',
			'twilight'       => 'themes/prism-twilight.min.css',
			// — community themes (prism-themes) —
			'anam-light'     => 'themes/prism-anam-light.min.css',
			'kent-light'     => 'themes/prism-kent-light.min.css',
			'atom-dark'      => 'themes/prism-atom-dark.min.css',
			'dracula'        => 'themes/prism-dracula.min.css',
			'ghcolors'       => 'themes/prism-ghcolors.min.css',
			'material-dark'  => 'themes/prism-material-dark.min.css',
			'nord'           => 'themes/prism-nord.min.css',
			'one-dark'       => 'themes/prism-one-dark.min.css',
			'one-light'      => 'themes/prism-one-light.min.css',
			'synthwave84'    => 'themes/prism-synthwave84.min.css',
			'vsc-dark-plus'  => 'themes/prism-vsc-dark-plus.min.css',
			'xonokai'        => 'themes/prism-xonokai.min.css',
		);
	}

	/**
	 * Human-readable display names for each theme slug.
	 *
	 * @return array slug => display name
	 */
	public static function get_theme_names() {
		return array(
			'default'        => 'Default',
			'coy'            => 'Coy',
			'dark'           => 'Dark',
			'funky'          => 'Funky',
			'okaidia'        => 'Okaidia',
			'solarizedlight' => 'Solarized Light',
			'tomorrow'       => 'Tomorrow',
			'twilight'       => 'Twilight',
			'anam-light'     => 'Anam Light (High Contrast)',
			'kent-light'     => 'Kent Light (kentcdodds.com style)',
			'atom-dark'      => 'Atom Dark',
			'dracula'        => 'Dracula',
			'ghcolors'       => 'GitHub Colors',
			'material-dark'  => 'Material Dark',
			'nord'           => 'Nord',
			'one-dark'       => 'One Dark',
			'one-light'      => 'One Light',
			'synthwave84'    => 'Synthwave \'84',
			'vsc-dark-plus'  => 'VS Code Dark+',
			'xonokai'        => 'Xonokai (Monokai)',
		);
	}

	/**
	 * Available fallback languages.
	 *
	 * @return array
	 */
	public static function get_languages() {
		return array(
			'php'        => 'PHP',
			'wordpress'  => 'WordPress (PHP)',
			'javascript' => 'JavaScript',
			'typescript' => 'TypeScript',
			'mcp'        => 'MCP Server (TypeScript/JS)',
			'python'     => 'Python',
			'css'        => 'CSS',
			'less'       => 'LESS',
			'sass'       => 'SASS',
			'scss'       => 'SCSS',
			'markup'     => 'HTML / Markup',
			'bash'       => 'Bash / Shell',
			'java'       => 'Java',
			'ruby'       => 'Ruby',
			'go'         => 'Go',
			'rust'       => 'Rust',
			'sql'        => 'SQL',
			'json'       => 'JSON',
			'yaml'       => 'YAML',
			'c'          => 'C',
			'cpp'        => 'C++',
			'csharp'     => 'C#',
		);
	}
}
