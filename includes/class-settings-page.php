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

		$clean['line_numbers'] = ! empty( $input['line_numbers'] );
		$clean['copy_button']  = ! empty( $input['copy_button'] );
		$clean['show_header']  = ! empty( $input['show_header'] );

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

		wp_enqueue_style(
			'anam-sh-admin',
			$url . 'assets/css/admin.css',
			array(),
			$version
		);

		// Prism core for live preview.
		wp_enqueue_script(
			'anam-sh-prism',
			$url . 'vendor/prism/prism.js',
			array(),
			$version,
			true
		);

		// Enqueue all theme CSS files so JS can toggle them.
		foreach ( Anam_SH_Asset_Loader::get_theme_files() as $slug => $file ) {
			$handle = 'anam-sh-theme-' . $slug;
			wp_enqueue_style( $handle, $url . 'vendor/prism/' . $file, array(), $version );
			// Disable all except the current one.
			$opts = anam_sh_get_options();
			if ( $slug !== $opts['theme'] ) {
				wp_style_add_data( $handle, 'disabled', true );
			}
		}

		// Inline JS for live theme switching.
		wp_add_inline_script( 'anam-sh-prism', $this->get_preview_js(), 'after' );
	}

	/**
	 * Inline JS that swaps theme stylesheets for live preview.
	 *
	 * @return string
	 */
	private function get_preview_js() {
		$themes = array_keys( Anam_SH_Asset_Loader::get_theme_files() );
		$json   = wp_json_encode( $themes );

		return <<<JS
(function(){
	var themes = {$json};
	var select = document.getElementById('anam-sh-theme');
	if (!select) return;
	select.addEventListener('change', function(){
		var chosen = this.value;
		themes.forEach(function(slug){
			var link = document.getElementById('anam-sh-theme-' + slug + '-css');
			if (link) {
				link.disabled = (slug !== chosen);
			}
		});
		if (typeof Prism !== 'undefined') {
			Prism.highlightAll();
		}
	});
})();
JS;
	}

	/**
	 * Render the settings page.
	 */
	public function render_page() {
		$opts      = anam_sh_get_options();
		$themes    = Anam_SH_Asset_Loader::get_theme_files();
		$languages = Anam_SH_Asset_Loader::get_languages();
		?>
		<div class="wrap anam-sh-settings">
			<h1><?php esc_html_e( 'Anam Syntax Highlighter Settings', 'anam-syntax-highlighter' ); ?></h1>

			<form method="post" action="options.php">
				<?php settings_fields( self::OPTION ); ?>

				<table class="form-table" role="presentation">
					<!-- Theme -->
					<tr>
						<th scope="row">
							<label for="anam-sh-theme"><?php esc_html_e( 'Theme', 'anam-syntax-highlighter' ); ?></label>
						</th>
						<td>
							<select id="anam-sh-theme" name="<?php echo esc_attr( self::OPTION ); ?>[theme]">
								<?php foreach ( $themes as $slug => $file ) : ?>
									<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $opts['theme'], $slug ); ?>>
										<?php echo esc_html( ucfirst( $slug ) ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>

					<!-- Line Numbers -->
					<tr>
						<th scope="row"><?php esc_html_e( 'Show Line Numbers', 'anam-syntax-highlighter' ); ?></th>
						<td>
							<label>
								<input type="checkbox"
									name="<?php echo esc_attr( self::OPTION ); ?>[line_numbers]"
									value="1"
									<?php checked( $opts['line_numbers'] ); ?> />
								<?php esc_html_e( 'Display line numbers on code blocks', 'anam-syntax-highlighter' ); ?>
							</label>
						</td>
					</tr>

					<!-- Copy Button -->
					<tr>
						<th scope="row"><?php esc_html_e( 'Show Copy Button', 'anam-syntax-highlighter' ); ?></th>
						<td>
							<label>
								<input type="checkbox"
									name="<?php echo esc_attr( self::OPTION ); ?>[copy_button]"
									value="1"
									<?php checked( $opts['copy_button'] ); ?> />
								<?php esc_html_e( 'Display a copy-to-clipboard button on code blocks', 'anam-syntax-highlighter' ); ?>
							</label>
						</td>
					</tr>

					<!-- Show Header -->
					<tr>
						<th scope="row"><?php esc_html_e( 'Show Filename / Language Header', 'anam-syntax-highlighter' ); ?></th>
						<td>
							<label>
								<input type="checkbox"
									name="<?php echo esc_attr( self::OPTION ); ?>[show_header]"
									value="1"
									<?php checked( $opts['show_header'] ); ?> />
								<?php esc_html_e( 'Display a header label above each code block', 'anam-syntax-highlighter' ); ?>
							</label>
						</td>
					</tr>

					<!-- Default Language -->
					<tr>
						<th scope="row">
							<label for="anam-sh-lang"><?php esc_html_e( 'Default Fallback Language', 'anam-syntax-highlighter' ); ?></label>
						</th>
						<td>
							<select id="anam-sh-lang" name="<?php echo esc_attr( self::OPTION ); ?>[default_language]">
								<?php foreach ( $languages as $slug => $name ) : ?>
									<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $opts['default_language'], $slug ); ?>>
										<?php echo esc_html( $name ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>

					<!-- Custom CSS -->
					<tr>
						<th scope="row">
							<label for="anam-sh-css"><?php esc_html_e( 'Custom CSS Override', 'anam-syntax-highlighter' ); ?></label>
						</th>
						<td>
							<textarea id="anam-sh-css"
								name="<?php echo esc_attr( self::OPTION ); ?>[custom_css]"
								rows="6"
								class="large-text code"
								placeholder="/* Your custom styles */"
							><?php echo esc_textarea( $opts['custom_css'] ); ?></textarea>
							<p class="description">
								<?php esc_html_e( 'Optional CSS that will be output in a scoped style block on the front end.', 'anam-syntax-highlighter' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<!-- Live Preview -->
			<h2><?php esc_html_e( 'Theme Preview', 'anam-syntax-highlighter' ); ?></h2>
			<div class="anam-sh-preview">
				<pre class="line-numbers"><code class="language-php">&lt;?php
/**
 * Sample PHP code for theme preview.
 */
function greet( string $name ): string {
    return 'Hello, ' . $name . '!';
}

$items = array_map( function ( $n ) {
    return $n * 2;
}, range( 1, 5 ) );

echo greet( 'World' );
print_r( $items );</code></pre>
			</div>
		</div>
		<?php
	}
}
