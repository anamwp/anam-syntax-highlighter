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

		// Match <pre…><code…>…</code></pre> blocks.
		$content = preg_replace_callback(
			'#(<pre(?P<pre_attrs>[^>]*)>)\s*(<code(?P<code_attrs>[^>]*)>)(?P<code_body>.*?)</code>\s*</pre>#si',
			function ( $m ) use ( $default_lang, $show_header, $line_numbers ) {
				$pre_attrs  = $m['pre_attrs'];
				$code_attrs = $m['code_attrs'];
				$code_body  = $m['code_body'];

				// Detect language from class="language-xxx".
				$language = $default_lang;
				if ( preg_match( '/class="[^"]*language-(\w+)/', $code_attrs, $lang_m ) ) {
					$language = $lang_m[1];
				} elseif ( preg_match( '/class="[^"]*language-(\w+)/', $pre_attrs, $lang_m ) ) {
					$language = $lang_m[1];
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

				return '<div class="anam-sh-block">'
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
