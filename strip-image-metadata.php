<?php

/**
 * Plugin Name: Strip Image Metadata
 * Plugin URI: https://github.com/MartinvonBerg/wp-strip-image-metadata
 * Description: Strip image metadata from JPGs and WEBPs on upload or via bulk action, and view image EXIF data.
 * Version: 1.6.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: Martin von Berg
 * Author URI: https://www.berg-reise-foto.de/software-wordpress-lightroom-plugins/wordpress-plugins-fotos-und-gpx/
 * License: GPL-2.0
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: wp-strip-image-metadata
 * Domain Path: /languages
 *
 * @package StripImageMetadata
 */

namespace mvbplugins\stripmetadata;

if ( ! \defined( 'ABSPATH' ) ) { exit; }

// load textdomain
add_action( 'init', '\mvbplugins\stripmetadata\wp_strip_meta_load_textdomain');

/**
 * Load the translation for the locale
 *
 * @return void
 */
function wp_strip_meta_load_textdomain() {
	$dir = dirname(plugin_basename(__FILE__)) . \DIRECTORY_SEPARATOR . 'languages';
	load_plugin_textdomain( 'wp-strip-image-metadata', false, $dir ); 
}

require_once __DIR__ . '/includes/shared/autoload.php';
require_once __DIR__ . '/includes/implode-all.php';
require_once __DIR__ . '/classes/StripImageMetadata.php';

(new StripImageMetadata())->init();
