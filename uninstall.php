<?php
/**
 * MY Slider PRO uninstall handler.
 *
 * User content is preserved unless the administrator explicitly opted in via the
 * "Delete all data on uninstall" setting. Media Library images are always kept.
 *
 * @package MySliderPro
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$my_slider_pro_settings = get_option( 'my_slider_pro_settings', array() );

if ( ! is_array( $my_slider_pro_settings ) || empty( $my_slider_pro_settings['delete_on_uninstall'] ) ) {
	return;
}

// Delete every slider post (post meta is removed with each post).
$my_slider_pro_sliders = get_posts(
	array(
		'post_type'        => 'psp_slider',
		'post_status'      => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ),
		'numberposts'      => -1,
		'fields'           => 'ids',
		'suppress_filters' => false,
	)
);

foreach ( $my_slider_pro_sliders as $my_slider_pro_slider_id ) {
	wp_delete_post( (int) $my_slider_pro_slider_id, true );
}

// Remove the settings option.
delete_option( 'my_slider_pro_settings' );

// Remove locally hosted font files, if any.
$my_slider_pro_upload = wp_upload_dir();

if ( empty( $my_slider_pro_upload['error'] ) ) {
	$my_slider_pro_font_dir = trailingslashit( $my_slider_pro_upload['basedir'] ) . 'my-slider-pro-fonts';

	if ( is_dir( $my_slider_pro_font_dir ) ) {
		$my_slider_pro_font_files = glob( $my_slider_pro_font_dir . '/*' );

		if ( is_array( $my_slider_pro_font_files ) ) {
			foreach ( $my_slider_pro_font_files as $my_slider_pro_font_file ) {
				if ( is_file( $my_slider_pro_font_file ) ) {
					wp_delete_file( $my_slider_pro_font_file );
				}
			}
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		@rmdir( $my_slider_pro_font_dir );
	}
}
