<?php
/**
 * Plugin Name:       MY Slider PRO
 * Plugin URI:        https://github.com/AminudinMurad/my-slider-pro
 * Description:       Build fast, responsive, and accessible photo sliders in WordPress.
 * Version:           1.0.4
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Aminudin Murad
 * Author URI:        https://github.com/AminudinMurad
 * Copyright:         2026 Aminudin Murad
 * License:           GPLv3
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       my-slider-pro
 * Update URI:        https://github.com/AminudinMurad/my-slider-pro
 *
 * @package MySliderPro
 */

/*
 * MY Slider PRO — responsive WordPress slider
 * Copyright (C) 2026 Aminudin Murad
 *
 * This program is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License, version 3, as published
 * by the Free Software Foundation. This program is distributed WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the bundled LICENSE file or
 * https://www.gnu.org/licenses/gpl-3.0.html for the full license text.
 */

defined( 'ABSPATH' ) || exit;

define( 'MY_SLIDER_PRO_VERSION', '1.0.4' );
define( 'MY_SLIDER_PRO_NAME', 'MY Slider PRO' );
define( 'MY_SLIDER_PRO_FILE', __FILE__ );
define( 'MY_SLIDER_PRO_PATH', plugin_dir_path( __FILE__ ) );
define( 'MY_SLIDER_PRO_URL', plugin_dir_url( __FILE__ ) );
define( 'MY_SLIDER_PRO_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Return a cache-safe version for a bundled asset.
 *
 * Test builds may replace files without changing the public plugin version, so
 * include the file modification time to prevent browsers and CDNs from mixing
 * new markup with an older stylesheet or controller.
 *
 * @param string $relative_path Asset path relative to the plugin directory.
 * @return string
 */
function my_slider_pro_asset_version( string $relative_path ): string {
	$asset_path = MY_SLIDER_PRO_PATH . ltrim( $relative_path, '/' );
	$modified   = is_file( $asset_path ) ? filemtime( $asset_path ) : false;

	return false === $modified ? MY_SLIDER_PRO_VERSION : MY_SLIDER_PRO_VERSION . '.' . (string) $modified;
}

if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
	add_action(
		'admin_notices',
		static function () {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__( 'MY Slider PRO requires PHP 7.4 or newer.', 'my-slider-pro' )
			);
		}
	);

	return;
}

require_once MY_SLIDER_PRO_PATH . 'src/Autoloader.php';

MySliderPro\Autoloader::register();

add_action( 'plugins_loaded', array( MySliderPro\Plugin::class, 'boot' ) );
