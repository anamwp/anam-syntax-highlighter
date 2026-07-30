<?php
/**
 * Adds a "Syntax Highlighting" language dropdown to the block editor sidebar
 * for the core Code block, so authors can pick a language (CSS/SCSS, JS,
 * PHP, WordPress, etc.) per-block instead of relying on the global default.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Anam_SH_Block_Editor {

	public function __construct() {
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue the block editor sidebar script and pass it the list of
	 * supported languages (kept in sync with the settings page).
	 */
	public function enqueue_assets() {
		$url     = ANAM_SH_PLUGIN_URL;
		$version = ANAM_SH_VERSION;

		wp_enqueue_script(
			'anam-sh-block-editor',
			$url . 'assets/js/block-editor.js',
			array(
				'wp-blocks',
				'wp-element',
				'wp-block-editor',
				'wp-components',
				'wp-compose',
				'wp-hooks',
				'wp-i18n',
			),
			$version,
			true
		);

		wp_localize_script( 'anam-sh-block-editor', 'anamSHBlockEditor', array(
			'languages' => Anam_SH_Asset_Loader::get_languages(),
		) );
	}
}
