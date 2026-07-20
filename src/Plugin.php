<?php
/**
 * Main plugin coordinator.
 *
 * @package MySliderPro
 */

namespace MySliderPro;

defined( 'ABSPATH' ) || exit;

/**
 * Coordinates plugin services after WordPress has loaded all plugins.
 */
final class Plugin {

	/**
	 * Boot the plugin.
	 *
	 * Feature services should register their hooks from this method. Keeping the
	 * bootstrap centralized makes load order explicit and straightforward to test.
	 *
	 * @return void
	 */
	public static function boot(): void {
		add_action( 'init', array( SliderPostType::class, 'register' ) );
		Settings::boot();
		AdminPage::boot();
		SliderShortcode::boot();

		/**
		 * Fires after MY Slider PRO has loaded.
		 *
		 * @since 0.1.0
		 */
		do_action( 'my_slider_pro_loaded' );
	}
}
