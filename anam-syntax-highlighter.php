<?php
/**
 * Plugin Name: Anam Syntax Highlighter
 * Plugin URI:  https://github.com/anamwp/anam-syntax-highlighter
 * Description: Prism.js-based syntax highlighting for code blocks in posts and pages.
 * Version:     1.0.0
 * Author:      Anam
 * Author URI:  https://github.com/anamwp
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: anam-syntax-highlighter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ANAM_SH_VERSION', '1.0.0' );
define( 'ANAM_SH_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ANAM_SH_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once ANAM_SH_PLUGIN_DIR . 'includes/class-asset-loader.php';
require_once ANAM_SH_PLUGIN_DIR . 'includes/class-settings-page.php';

/**
 * Return saved plugin options merged with defaults.
 *
 * @return array
 */
function anam_sh_get_options() {
	$defaults = array(
		'theme'            => 'okaidia',
		'line_numbers'     => true,
		'copy_button'      => true,
		'show_header'      => true,
		'default_language' => 'php',
		'custom_css'       => '',
	);

	$saved = get_option( 'anam_sh_options', array() );

	return wp_parse_args( $saved, $defaults );
}

// Initialize components.
add_action( 'plugins_loaded', function () {
	new Anam_SH_Asset_Loader();
	if ( is_admin() ) {
		new Anam_SH_Settings_Page();
	}
} );
