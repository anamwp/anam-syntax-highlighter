<?php
/**
 * Admin settings page: Settings > Syntax Highlighter.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Anam_SH_Settings_Page {

	/** Option group / option name. */
	const OPTION = 'anam_sh_options';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Add submenu under Settings.
	 */
	public function add_menu() {
		add_options_page(
			__( 'Syntax Highlighter', 'anam-syntax-highlighter' ),
			__( 'Syntax Highlighter', 'anam-syntax-highlighter' ),
			'manage_options',
			'anam-syntax-highlighter',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register the single option with a sanitize callback.
	 */
	public function register_settings() {
		register_setting( self::OPTION, self::OPTION, array(
			'type'              => 'array',
			'sanitize_callback' => array( $this, 'sanitize' ),
		) );
	}

	/**
	 * Sanitize options on save.
	 *
	 * @param array $input Raw input.
	 * @return array Sanitized options.
	 */
	public function sanitize( $input ) {
		$clean = array();

		$themes = array_keys( Anam_SH_Asset_Loader::get_theme_files() );
		$clean['theme'] = in_array( $input['theme'] ?? '', $themes, true )
			? $input['theme']
			: 'okaidia';

		$clean['line_numbers']      = ! empty( $input['line_numbers'] );
		$clean['copy_button']       = ! empty( $input['copy_button'] );
		$clean['show_header']       = ! empty( $input['show_header'] );
		$clean['inline_code_style'] = ! empty( $input['inline_code_style'] );

		$languages = array_keys( Anam_SH_Asset_Loader::get_languages() );
		$clean['default_language'] = in_array( $input['default_language'] ?? '', $languages, true )
			? $input['default_language']
			: 'php';

		$clean['custom_css'] = wp_strip_all_tags( $input['custom_css'] ?? '' );

		return $clean;
	}

	/**
	 * Enqueue admin-only assets on the settings page.
	 *
	 * @param string $hook Admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'settings_page_anam-syntax-highlighter' !== $hook ) {
			return;
		}

		$url     = ANAM_SH_PLUGIN_URL;
		$version = ANAM_SH_VERSION;
		$opts    = anam_sh_get_options();

		wp_enqueue_style(
			'anam-sh-admin',
			$url . 'assets/css/admin.css',
			array(),
			$version
		);

		// Inline-code CSS is always available in the preview (regardless of
		// the saved setting) — the live-preview JS below toggles it on/off
		// with a wrapper class so the checkbox can be previewed instantly.
		wp_add_inline_style( 'anam-sh-admin', Anam_SH_Asset_Loader::get_inline_code_css() );

		// Prism core for live preview.
		wp_enqueue_script(
			'anam-sh-prism',
			$url . 'vendor/prism/prism.js',
			array(),
			$version,
			true
		);

		// Enqueue all theme CSS files so JS can toggle them live. `disabled`
		// isn't a real wp_style_add_data key — core has no built-in support
		// for it — so without the style_loader_tag filter below, every theme
		// stylesheet loads active at once and the *last* one in the DOM wins
		// conflicting token colors, regardless of which theme is selected.
		add_filter( 'style_loader_tag', array( $this, 'filter_disabled_style_tag' ), 10, 2 );

		foreach ( Anam_SH_Asset_Loader::get_theme_files() as $slug => $file ) {
			$handle = 'anam-sh-theme-' . $slug;
			wp_enqueue_style( $handle, $url . 'vendor/prism/' . $file, array(), $version );
			// Disable all except the current one.
			if ( $slug !== $opts['theme'] ) {
				wp_style_add_data( $handle, 'disabled', true );
			}
		}

		// Line-numbers, toolbar and copy-to-clipboard are loaded unconditionally
		// here (unlike the front end, where they're gated by the saved option)
		// so every feature actually exists in the preview DOM and the live
		// toggles below can simply show/hide it — no re-highlighting needed.
		wp_enqueue_style(
			'anam-sh-line-numbers',
			$url . 'vendor/prism/plugins/line-numbers/prism-line-numbers.min.css',
			array( 'anam-sh-theme-' . $opts['theme'] ),
			$version
		);

		wp_enqueue_style(
			'anam-sh-toolbar',
			$url . 'vendor/prism/plugins/toolbar/prism-toolbar.min.css',
			array( 'anam-sh-theme-' . $opts['theme'] ),
			$version
		);

		wp_enqueue_script(
			'anam-sh-line-numbers',
			$url . 'vendor/prism/plugins/line-numbers/prism-line-numbers.min.js',
			array( 'anam-sh-prism' ),
			$version,
			true
		);

		wp_enqueue_script(
			'anam-sh-toolbar-js',
			$url . 'vendor/prism/plugins/toolbar/prism-toolbar.min.js',
			array( 'anam-sh-prism' ),
			$version,
			true
		);

		wp_enqueue_script(
			'anam-sh-copy',
			$url . 'vendor/prism/plugins/copy-to-clipboard/prism-copy-to-clipboard.min.js',
			array( 'anam-sh-toolbar-js' ),
			$version,
			true
		);

		// Explicitly load PHP and TypeScript so custom grammars can extend them.
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

		wp_enqueue_script(
			'anam-sh-lang-typescript',
			$url . 'vendor/prism/components/prism-typescript.min.js',
			array( 'anam-sh-prism' ),
			$version,
			true
		);

		// WordPress custom grammar for admin preview.
		wp_enqueue_script(
			'anam-sh-wordpress',
			$url . 'assets/js/prism-wordpress.js',
			array( 'anam-sh-lang-php' ),
			$version,
			true
		);

		// MCP custom grammar for admin preview.
		wp_enqueue_script(
			'anam-sh-mcp',
			$url . 'assets/js/prism-mcp.js',
			array( 'anam-sh-lang-typescript' ),
			$version,
			true
		);

		// SQL grammar for the admin preview (also covers MySQL syntax).
		wp_enqueue_script(
			'anam-sh-lang-sql',
			$url . 'vendor/prism/components/prism-sql.min.js',
			array( 'anam-sh-prism' ),
			$version,
			true
		);

		// Same loader used on the front end — gives the preview's copy
		// buttons the real icon treatment instead of default Prism text.
		wp_enqueue_script(
			'anam-sh-loader',
			$url . 'assets/js/prism-loader.js',
			array( 'anam-sh-prism', 'anam-sh-copy' ),
			$version,
			true
		);

		wp_localize_script( 'anam-sh-loader', 'anamSH', array(
			'copyButton' => true,
			'showHeader' => true,
		) );

		// Live-preview JS: reflects every control's current value instantly,
		// with no page reload or save required.
		wp_add_inline_script( 'anam-sh-prism', $this->get_preview_js(), 'after' );
	}

	/**
	 * Adds the actual HTML `disabled` attribute to <link> tags whose
	 * wp_style_add_data() 'disabled' flag was set — core enqueues the data
	 * but never reads it back into markup, so this is the missing half.
	 * Only touches our own theme-preview handles.
	 *
	 * @param string $tag    The <link> tag markup.
	 * @param string $handle Style handle.
	 * @return string
	 */
	public function filter_disabled_style_tag( $tag, $handle ) {
		if ( 0 !== strpos( $handle, 'anam-sh-theme-' ) ) {
			return $tag;
		}

		if ( wp_styles()->get_data( $handle, 'disabled' ) ) {
			$tag = str_replace( ' rel=', ' disabled rel=', $tag );
		}

		return $tag;
	}

	/**
	 * Inline JS that keeps the live preview panel in sync with every control
	 * on the page — the theme dropdown swaps stylesheets, and the feature
	 * checkboxes toggle CSS classes that show/hide the relevant preview
	 * chrome, all without a page reload.
	 *
	 * @return string
	 */
	private function get_preview_js() {
		$themes = array_keys( Anam_SH_Asset_Loader::get_theme_files() );
		$json   = wp_json_encode( $themes );

		return <<<JS
(function () {
	var themes = {$json};
	var panel = document.getElementById('anam-sh-preview-panel');
	if (!panel) {
		return;
	}

	var themeSelect  = document.getElementById('anam-sh-theme');
	var lineNumbers  = document.getElementById('anam-sh-field-line-numbers');
	var copyButton   = document.getElementById('anam-sh-field-copy-button');
	var showHeader   = document.getElementById('anam-sh-field-show-header');
	var inlineCode   = document.getElementById('anam-sh-field-inline-code-style');

	function setThemeClass(slug) {
		var blocks = panel.querySelectorAll('.anam-sh-block');
		blocks.forEach(function (block) {
			block.className = block.className
				.split(' ')
				.filter(function (cls) { return cls.indexOf('anam-sh-theme-') !== 0; })
				.concat('anam-sh-theme-' + slug)
				.join(' ');
		});
	}

	if (themeSelect) {
		themeSelect.addEventListener('change', function () {
			var chosen = this.value;
			themes.forEach(function (slug) {
				var link = document.getElementById('anam-sh-theme-' + slug + '-css');
				if (link) {
					link.disabled = (slug !== chosen);
				}
			});
			setThemeClass(chosen);
		});
	}

	function bindToggle(input, panelClass) {
		if (!input) {
			return;
		}
		function sync() {
			panel.classList.toggle(panelClass, !input.checked);
		}
		input.addEventListener('change', sync);
		sync();
	}

	bindToggle(lineNumbers, 'anam-sh-preview-hide-line-numbers');
	bindToggle(copyButton, 'anam-sh-preview-hide-copy');
	bindToggle(showHeader, 'anam-sh-preview-hide-header');
	bindToggle(inlineCode, 'anam-sh-preview-hide-inline-code');
})();
JS;
	}

	/**
	 * Render one code-block preview using the exact markup
	 * Anam_SH_Asset_Loader::filter_content() produces on the front end, so
	 * the preview looks pixel-identical to a real post — including the
	 * per-theme chrome (e.g. Kent Light's corner badge) that's keyed off the
	 * `anam-sh-theme-{slug}` wrapper class.
	 *
	 * @param string $theme_slug Active theme slug.
	 * @param string $language   Prism language slug.
	 * @param string $label      Header label (filename or language name).
	 * @param string $code_html  Pre-escaped, highlighted-ready code markup.
	 * @return string
	 */
	private function render_preview_block( $theme_slug, $language, $label, $code_html ) {
		return sprintf(
			'<div class="anam-sh-block anam-sh-theme-%1$s">'
				. '<div class="anam-sh-header"><span class="anam-sh-label">%2$s</span></div>'
				. '<pre class="line-numbers"><code class="language-%3$s">%4$s</code></pre>'
				. '</div>',
			esc_attr( $theme_slug ),
			esc_html( $label ),
			esc_attr( $language ),
			$code_html
		);
	}

	/**
	 * Render the settings page.
	 */
	public function render_page() {
		$opts        = anam_sh_get_options();
		$themes      = Anam_SH_Asset_Loader::get_theme_files();
		$theme_names = Anam_SH_Asset_Loader::get_theme_names();
		$languages   = Anam_SH_Asset_Loader::get_languages();

		$php_sample = "&lt;?php\n/**\n * Sample PHP code for theme preview.\n */\nfunction greet( string \$name ): string {\n    return 'Hello, ' . \$name . '!';\n}\n\necho greet( 'World' );";
		$sql_sample = 'SELECT * FROM wp_bookings WHERE id = 42 OR 1=1;';
		?>
		<div class="wrap anam-sh-settings">
			<h1><?php esc_html_e( 'Anam Syntax Highlighter', 'anam-syntax-highlighter' ); ?></h1>
			<p class="anam-sh-tagline"><?php esc_html_e( 'Configure how code blocks and inline code are highlighted across your site. Changes below preview instantly — nothing is applied until you save.', 'anam-syntax-highlighter' ); ?></p>

			<div class="anam-sh-admin-grid">
				<div class="anam-sh-admin-main">
					<form method="post" action="options.php">
						<?php settings_fields( self::OPTION ); ?>

						<div class="anam-sh-card">
							<h2 class="anam-sh-card-title"><?php esc_html_e( 'Appearance', 'anam-syntax-highlighter' ); ?></h2>

							<div class="anam-sh-field">
								<label for="anam-sh-theme"><?php esc_html_e( 'Theme', 'anam-syntax-highlighter' ); ?></label>
								<div class="anam-sh-field-control">
									<select id="anam-sh-theme" name="<?php echo esc_attr( self::OPTION ); ?>[theme]">
										<?php foreach ( $themes as $slug => $file ) : ?>
											<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $opts['theme'], $slug ); ?>>
												<?php echo esc_html( isset( $theme_names[ $slug ] ) ? $theme_names[ $slug ] : ucfirst( $slug ) ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</div>
							</div>

							<div class="anam-sh-field">
								<label for="anam-sh-lang"><?php esc_html_e( 'Default Fallback Language', 'anam-syntax-highlighter' ); ?></label>
								<div class="anam-sh-field-control">
									<select id="anam-sh-lang" name="<?php echo esc_attr( self::OPTION ); ?>[default_language]">
										<?php foreach ( $languages as $slug => $name ) : ?>
											<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $opts['default_language'], $slug ); ?>>
												<?php echo esc_html( $name ); ?>
											</option>
										<?php endforeach; ?>
									</select>
									<p class="anam-sh-field-description"><?php esc_html_e( 'Used only when a code block has no detectable language.', 'anam-syntax-highlighter' ); ?></p>
								</div>
							</div>
						</div>

						<div class="anam-sh-card">
							<h2 class="anam-sh-card-title"><?php esc_html_e( 'Code Block Features', 'anam-syntax-highlighter' ); ?></h2>

							<div class="anam-sh-toggle-row">
								<div class="anam-sh-toggle-text">
									<strong><?php esc_html_e( 'Show Line Numbers', 'anam-syntax-highlighter' ); ?></strong>
									<p><?php esc_html_e( 'Display line numbers on code blocks.', 'anam-syntax-highlighter' ); ?></p>
								</div>
								<label class="anam-sh-switch">
									<input type="checkbox" id="anam-sh-field-line-numbers"
										name="<?php echo esc_attr( self::OPTION ); ?>[line_numbers]"
										value="1" <?php checked( $opts['line_numbers'] ); ?> />
									<span class="anam-sh-switch-track"><span class="anam-sh-switch-thumb"></span></span>
								</label>
							</div>

							<div class="anam-sh-toggle-row">
								<div class="anam-sh-toggle-text">
									<strong><?php esc_html_e( 'Show Copy Button', 'anam-syntax-highlighter' ); ?></strong>
									<p><?php esc_html_e( 'Display a copy-to-clipboard button on code blocks.', 'anam-syntax-highlighter' ); ?></p>
								</div>
								<label class="anam-sh-switch">
									<input type="checkbox" id="anam-sh-field-copy-button"
										name="<?php echo esc_attr( self::OPTION ); ?>[copy_button]"
										value="1" <?php checked( $opts['copy_button'] ); ?> />
									<span class="anam-sh-switch-track"><span class="anam-sh-switch-thumb"></span></span>
								</label>
							</div>

							<div class="anam-sh-toggle-row">
								<div class="anam-sh-toggle-text">
									<strong><?php esc_html_e( 'Show Filename / Language Header', 'anam-syntax-highlighter' ); ?></strong>
									<p><?php esc_html_e( 'Display a header label above each code block.', 'anam-syntax-highlighter' ); ?></p>
								</div>
								<label class="anam-sh-switch">
									<input type="checkbox" id="anam-sh-field-show-header"
										name="<?php echo esc_attr( self::OPTION ); ?>[show_header]"
										value="1" <?php checked( $opts['show_header'] ); ?> />
									<span class="anam-sh-switch-track"><span class="anam-sh-switch-thumb"></span></span>
								</label>
							</div>

							<div class="anam-sh-toggle-row">
								<div class="anam-sh-toggle-text">
									<strong><?php esc_html_e( 'Style Inline Code', 'anam-syntax-highlighter' ); ?></strong>
									<p><?php esc_html_e( 'Style inline `code` in paragraphs. When off, it falls back to your theme\'s default styling.', 'anam-syntax-highlighter' ); ?></p>
								</div>
								<label class="anam-sh-switch">
									<input type="checkbox" id="anam-sh-field-inline-code-style"
										name="<?php echo esc_attr( self::OPTION ); ?>[inline_code_style]"
										value="1" <?php checked( $opts['inline_code_style'] ); ?> />
									<span class="anam-sh-switch-track"><span class="anam-sh-switch-thumb"></span></span>
								</label>
							</div>
						</div>

						<div class="anam-sh-card">
							<h2 class="anam-sh-card-title"><?php esc_html_e( 'Custom CSS', 'anam-syntax-highlighter' ); ?></h2>
							<div class="anam-sh-field">
								<label for="anam-sh-css"><?php esc_html_e( 'Custom CSS Override', 'anam-syntax-highlighter' ); ?></label>
								<div class="anam-sh-field-control">
									<textarea id="anam-sh-css"
										name="<?php echo esc_attr( self::OPTION ); ?>[custom_css]"
										rows="6"
										class="large-text code"
										placeholder="/* Your custom styles */"
									><?php echo esc_textarea( $opts['custom_css'] ); ?></textarea>
									<p class="anam-sh-field-description">
										<?php esc_html_e( 'Optional CSS that will be output in a scoped style block on the front end.', 'anam-syntax-highlighter' ); ?>
									</p>
								</div>
							</div>
						</div>

						<?php submit_button(); ?>
					</form>
				</div>

				<div class="anam-sh-admin-sidebar">
					<div class="anam-sh-preview-panel" id="anam-sh-preview-panel">
						<div class="anam-sh-preview-panel-title"><?php esc_html_e( 'Live Preview', 'anam-syntax-highlighter' ); ?></div>

						<?php
						echo $this->render_preview_block( $opts['theme'], 'php', 'PHP', $php_sample ); // phpcs:ignore WordPress.Security.EscapeOutput
						echo $this->render_preview_block( $opts['theme'], 'sql', 'SQL', esc_html( $sql_sample ) ); // phpcs:ignore WordPress.Security.EscapeOutput
						?>

						<p class="anam-sh-preview-inline">
							<?php
							printf(
								/* translators: %s: inline code example */
								esc_html__( 'Call %s to create a new post programmatically.', 'anam-syntax-highlighter' ),
								'<code>wp_insert_post( $args )</code>' // phpcs:ignore WordPress.Security.EscapeOutput
							);
							?>
						</p>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}
