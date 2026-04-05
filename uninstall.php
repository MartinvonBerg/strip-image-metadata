<?php

/**
 *
 * Description:       uninstall script - Strip Image Metadata - automatically run by WP
 * Author:            Martin von Berg
 * Author URI:        https://www.berg-reise-foto.de/software-wordpress-lightroom-plugins/wordpress-plugins-fotos-und-gpx/
 * License:           GPL-2.0
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */
// delete options in wp_options in the database at uninstall of the plugin

// if uninstall.php is not called by WordPress, die
if ( ! defined('WP_UNINSTALL_PLUGIN') ) {
    die;
}
 
$option_name = 'wp_strip_image_metadata_settings';
delete_option($option_name);