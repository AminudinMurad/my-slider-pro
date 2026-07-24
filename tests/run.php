<?php
/**
 * Dependency-free MY Slider PRO behavior tests.
 *
 * @package MySliderPro
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

function mySlider_test_fail( string $message ): void {
	fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
	exit( 1 );
}

function mySlider_test_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		mySlider_test_fail( $message );
	}
}

function mySlider_test_contains( string $needle, string $haystack, string $message ): void {
	mySlider_test_assert( false !== strpos( $haystack, $needle ), $message );
}

function mySlider_test_hook( string $type, string $hook ): callable {
	global $mySlider_test_state;

	if ( empty( $mySlider_test_state[ $type ][ $hook ][0]['callback'] ) ) {
		mySlider_test_fail( 'Missing callback for ' . $hook . '.' );
	}

	return $mySlider_test_state[ $type ][ $hook ][0]['callback'];
}

function mySlider_test_redirect( callable $callback, string $message ): string {
	try {
		call_user_func( $callback );
	} catch ( MySliderTestRedirect $exception ) {
		return $exception->getMessage();
	}

	mySlider_test_fail( $message );
	return '';
}

require dirname( __DIR__ ) . '/my-slider-pro.php';

global $mySlider_test_state;

mySlider_test_assert( defined( 'MY_SLIDER_PRO_VERSION' ), 'The version constant is missing.' );
mySlider_test_assert( '1.0.5' === MY_SLIDER_PRO_VERSION, 'The version constant is not 1.0.5.' );
mySlider_test_assert( 'MY Slider PRO' === MY_SLIDER_PRO_NAME, 'The plugin name constant is incorrect.' );

$plugin_source  = (string) file_get_contents( dirname( __DIR__ ) . '/my-slider-pro.php' );
$readme_source  = (string) file_get_contents( dirname( __DIR__ ) . '/readme.txt' );
$license_source = (string) file_get_contents( dirname( __DIR__ ) . '/LICENSE' );

mySlider_test_contains( 'Version:           1.0.5', $plugin_source, 'The plugin header version is incorrect.' );
mySlider_test_contains( 'Stable tag: 1.0.5', $readme_source, 'The readme stable tag is incorrect.' );
mySlider_test_contains( 'License:           GPLv3', $plugin_source, 'The plugin header must declare the GPLv3 license.' );
mySlider_test_contains( 'License: GPLv3', $readme_source, 'The readme must declare the GPLv3 license.' );
mySlider_test_assert( 0 === strpos( ltrim( $license_source ), 'GNU GENERAL PUBLIC LICENSE' ), 'The GPLv3 license file is incorrect.' );
mySlider_test_assert( MY_SLIDER_PRO_VERSION !== my_slider_pro_asset_version( 'assets/frontend.css' ), 'Bundled assets need a file-specific cache-busting version.' );

call_user_func( mySlider_test_hook( 'actions', 'plugins_loaded' ) );
call_user_func( mySlider_test_hook( 'actions', 'init' ) );

mySlider_test_assert( isset( $mySlider_test_state['shortcodes']['myslider'] ), 'The slider shortcode is not registered.' );
mySlider_test_assert( isset( $mySlider_test_state['actions']['admin_post_my_slider_pro_save_slider'] ), 'The secure save handler is missing.' );
mySlider_test_assert( isset( $mySlider_test_state['actions']['admin_post_my_slider_pro_rename_slider'] ), 'The secure quick rename handler is missing.' );
mySlider_test_assert( isset( $mySlider_test_state['actions']['admin_post_my_slider_pro_duplicate_slider'] ), 'The secure duplicate handler is missing.' );
mySlider_test_assert( false === ( $mySlider_test_state['post_types']['psp_slider']['public'] ?? null ), 'Slider posts must remain private.' );
mySlider_test_assert( false === ( $mySlider_test_state['post_types']['psp_slider']['show_in_rest'] ?? null ), 'Slider posts must not be exposed through REST.' );

$mySlider_test_state['images'] = array(
	101 => array(
		'url'   => 'https://example.test/uploads/one.jpg',
		'title' => 'First image',
		'alt'   => 'Calm ocean',
	),
	102 => array(
		'url'   => 'https://example.test/uploads/two.jpg',
		'title' => 'Second image',
		'alt'   => 'Mountain horizon',
	),
);

mySlider_test_assert(
	array( 102, 101 ) === MySliderPro\SliderPostType::sanitize_image_ids( array( 102, '101', 102, 0, -99, array( 101 ) ) ),
	'Image IDs must be positive, unique, valid, and order-preserving.'
);
mySlider_test_assert( 5 === MySliderPro\SliderPostType::MAX_IMAGES, 'Sliders are capped at 5 slides.' );
mySlider_test_assert( 2 === MySliderPro\SliderPostType::MAX_LAYERS_PER_TYPE, 'Each slide allows at most 2 layers of a type.' );
foreach ( range( 201, 207 ) as $mySlider_cap_id ) {
	$mySlider_test_state['images'][ $mySlider_cap_id ] = array( 'url' => 'https://example.test/' . $mySlider_cap_id . '.jpg' );
}
$capped_ids = MySliderPro\SliderPostType::sanitize_image_ids( range( 201, 207 ) );
mySlider_test_assert( 5 === count( $capped_ids ), 'The slide count must be capped at 5.' );

$settings = MySliderPro\SliderPostType::sanitize_settings(
	array(
		'height'           => 'invalid',
		'tablet_height'    => 'invalid',
		'mobile_height'    => 'invalid',
		'content_position' => 'invalid',
		'tablet_content_position' => 'invalid',
		'mobile_content_position' => 'invalid',
		'tablet_text_width' => 'invalid',
		'mobile_text_width' => 'invalid',
		'tablet_button_size' => 'invalid',
		'mobile_button_size' => 'invalid',
		'overlay'          => 'invalid',
		'interval'         => 99,
		'arrows'           => true,
	)
);
mySlider_test_assert( 'standard' === $settings['height'] && 'standard' === $settings['tablet_height'] && 'left' === $settings['content_position'] && 'left' === $settings['tablet_content_position'] && 'left' === $settings['mobile_content_position'] && 'comfortable' === $settings['tablet_text_width'] && 'comfortable' === $settings['mobile_text_width'] && 'large' === $settings['tablet_button_size'] && 'large' === $settings['mobile_button_size'] && 5000 === $settings['interval'], 'Invalid slider settings must fall back safely.' );

$sanitized_content = MySliderPro\SliderPostType::sanitize_slide_content(
	array(
		101 => array(
			'title'              => 'Welcome <script>alert(1)</script>',
			'heading_link_url'   => 'javascript:alert(1)',
			'description_link_url' => 'javascript:alert(1)',
			'button_url'         => 'javascript:alert(1)',
			'background_position'        => 'top_left',
			'tablet_background_position' => 'center',
			'mobile_background_position' => 'bottom_right',
			'button_target'      => '1',
			'text_x'             => '-500',
			'text_y'             => '61',
			'image_layer_url'    => 'javascript:alert(1)',
			'image_layer_alt'    => '<b>Decor</b>',
			'image_link_url'     => 'javascript:alert(1)',
			'image_width'        => '2000',
			'image_opacity'      => '2',
			'mobile_button_x'    => '500',
			'mobile_button_y'    => array( 'unsafe' ),
			'text_color'         => 'not-a-color',
			'heading_size'       => '999',
			'heading_opacity'    => '0',
			'font_family'        => '',
			'description_font_family' => '',
			'button_font_family' => '',
			'description_opacity' => '999',
			'button_font_size'   => '999',
			'button_opacity'     => '7',
			'button_radius'      => '-20',
			'heading_animation'  => 'unsafe',
			'image_animation'    => 'zoom',
			'image_animation_delay' => '9999',
			'image_animation_duration' => '10',
			'image_animation_easing' => 'bad',
			'layer_order'       => 'description,extra-0,unsafe,button,description',
			'extra_layers'      => array(
				array(
					'type'     => 'heading',
					'text'     => '<b>Second heading</b>',
					'link_url' => 'https://example.test/extra-heading',
					'desktop_x' => '500',
					'opacity'  => '2',
				),
				array(
					'type' => 'unsafe',
					'text' => 'Discard me',
				),
			),
		),
		999 => array( 'title' => 'Discarded' ),
	),
	array( 101 )
);
mySlider_test_assert( 'Welcome alert(1)' === $sanitized_content[101]['title'], 'Slide headings must be sanitized.' );
mySlider_test_assert( '' === $sanitized_content[101]['heading_link_url'] && '' === $sanitized_content[101]['description_link_url'], 'Unsafe text-layer links must be rejected.' );
mySlider_test_assert( '' === $sanitized_content[101]['button_url'], 'Unsafe CTA protocols must be rejected.' );
mySlider_test_assert( 'top_left' === $sanitized_content[101]['background_position'], 'Allowed background positions must be retained.' );
mySlider_test_assert( 'center' === $sanitized_content[101]['tablet_background_position'] && 'bottom_right' === $sanitized_content[101]['mobile_background_position'], 'Per-device background positions must be retained independently.' );
mySlider_test_assert( 5 === $sanitized_content[101]['text_x'] && 61 === $sanitized_content[101]['text_y'], 'Text-layer coordinates must be numeric and bounded.' );
mySlider_test_assert( 5 === $sanitized_content[101]['description_x'] && 73 === $sanitized_content[101]['description_y'], 'Existing text coordinates must safely seed a separate description layer.' );
mySlider_test_assert( 95 === $sanitized_content[101]['mobile_button_x'] && 82 === $sanitized_content[101]['mobile_button_y'], 'Mobile button coordinates must be bounded and use safe defaults.' );
mySlider_test_assert( 95 === $sanitized_content[101]['tablet_button_x'] && 82 === $sanitized_content[101]['tablet_button_y'], 'Existing mobile coordinates must safely seed missing tablet positions.' );
mySlider_test_assert( '#ffffff' === $sanitized_content[101]['text_color'] && 96 === $sanitized_content[101]['heading_size'], 'Text-layer styles must use safe colors and bounded sizes.' );
mySlider_test_assert( 10 === $sanitized_content[101]['heading_opacity'] && 100 === $sanitized_content[101]['description_opacity'], 'Text-layer opacity values must be bounded.' );
mySlider_test_assert( 36 === $sanitized_content[101]['button_font_size'] && 10 === $sanitized_content[101]['button_opacity'] && 0 === $sanitized_content[101]['button_radius'], 'Button styling values must be bounded.' );
mySlider_test_assert( 'montserrat' === $sanitized_content[101]['font_family'] && 'montserrat' === $sanitized_content[101]['description_font_family'] && 'montserrat' === $sanitized_content[101]['button_font_family'], 'Layer font defaults must use Montserrat.' );
mySlider_test_assert( '' === $sanitized_content[101]['image_layer_url'] && 'Decor' === $sanitized_content[101]['image_layer_alt'] && '' === $sanitized_content[101]['image_link_url'], 'Image layer content must be sanitized.' );
mySlider_test_assert( 800 === $sanitized_content[101]['image_width'] && 10 === $sanitized_content[101]['image_opacity'], 'Image layer styles must be bounded.' );
mySlider_test_assert( 'fade' === $sanitized_content[101]['heading_animation'] && 'zoom' === $sanitized_content[101]['image_animation'], 'Layer animations must be sanitized.' );
mySlider_test_assert( 5000 === $sanitized_content[101]['image_animation_delay'] && 100 === $sanitized_content[101]['image_animation_duration'] && 'ease-out' === $sanitized_content[101]['image_animation_easing'], 'Layer animation timing must be bounded.' );
mySlider_test_assert( 'description,extra-0,button,heading,image' === $sanitized_content[101]['layer_order'], 'Layer order must keep overlay (extra-N) tokens, discard unsupported and duplicate names, and restore missing base layers.' );
mySlider_test_assert( 1 === count( $sanitized_content[101]['extra_layers'] ) && 'heading' === $sanitized_content[101]['extra_layers'][0]['type'], 'Additional layers must keep supported layer types only.' );
mySlider_test_assert( 'Second heading' === $sanitized_content[101]['extra_layers'][0]['text'] && 95 === $sanitized_content[101]['extra_layers'][0]['desktop_x'] && 10 === $sanitized_content[101]['extra_layers'][0]['opacity'], 'Additional layer content and styles must be sanitized.' );
mySlider_test_assert( '1' === $sanitized_content[101]['heading_size_linked'] && '1' === $sanitized_content[101]['description_size_linked'] && '1' === $sanitized_content[101]['button_size_linked'] && '1' === $sanitized_content[101]['image_size_linked'], 'Missing size-link flags must default to linked so legacy sliders keep one size across devices.' );
mySlider_test_assert( '' === $sanitized_content[101]['heading_pos_linked'] && '' === $sanitized_content[101]['description_pos_linked'] && '' === $sanitized_content[101]['button_pos_linked'] && '' === $sanitized_content[101]['image_pos_linked'], 'Missing position-link flags must default to unlinked so legacy per-device placements are preserved.' );
mySlider_test_assert( 96 === $sanitized_content[101]['tablet_heading_size'] && 96 === $sanitized_content[101]['mobile_heading_size'], 'Missing per-device heading sizes must cascade from the desktop value.' );
mySlider_test_assert( '1' === $sanitized_content[101]['extra_layers'][0]['size_linked'] && '' === $sanitized_content[101]['extra_layers'][0]['pos_linked'], 'Overlay layers must inherit the same link-flag defaults as base layers.' );
mySlider_test_assert( ! isset( $sanitized_content[999] ), 'Content for unselected attachments must be discarded.' );

$capped_layers = MySliderPro\SliderPostType::sanitize_slide_content(
	array(
		101 => array(
			'title'        => 'Base heading',
			'extra_layers' => array(
				array( 'type' => 'heading', 'text' => 'Extra heading 1' ),
				array( 'type' => 'heading', 'text' => 'Extra heading 2' ),
				array( 'type' => 'button', 'text' => 'Extra button 1' ),
				array( 'type' => 'button', 'text' => 'Extra button 2' ),
			),
		),
	),
	array( 101 )
)[101]['extra_layers'];
$capped_headings = array_values( array_filter( $capped_layers, function ( $layer ) { return 'heading' === $layer['type']; } ) );
$capped_buttons  = array_values( array_filter( $capped_layers, function ( $layer ) { return 'button' === $layer['type']; } ) );
mySlider_test_assert( 1 === count( $capped_headings ), 'A base heading plus one extra reaches the 2-per-type cap; further heading layers are dropped.' );
mySlider_test_assert( 2 === count( $capped_buttons ), 'With no base button content, up to 2 button layers are allowed.' );

$shape_layers = MySliderPro\SliderPostType::sanitize_slide_content(
	array(
		101 => array(
			'title'        => 'Base heading',
			'extra_layers' => array(
				array( 'type' => 'shape', 'background' => '#3858e9', 'radius' => '900', 'height' => '5', 'overlay_type' => 'solid', 'overlay_opacity' => '70' ),
				array( 'type' => 'shape' ),
				array( 'type' => 'shape' ),
			),
		),
	),
	array( 101 )
)[101]['extra_layers'];
$shape_only = array_values( array_filter( $shape_layers, function ( $layer ) { return 'shape' === $layer['type']; } ) );
mySlider_test_assert( 2 === count( $shape_only ), 'Shape layers must be capped at 2 per slide like other types.' );
mySlider_test_assert( 400 === $shape_only[0]['radius'] && 20 === $shape_only[0]['height'], 'Shape radius and height must be clamped to their bounds.' );
mySlider_test_assert( 'solid' === $shape_only[0]['overlay_type'] && 70 === $shape_only[0]['overlay_opacity'] && '#3858e9' === $shape_only[0]['background'], 'Shape overlay and fill fields must be sanitized.' );
mySlider_test_assert( '' === $shape_only[0]['ratio_locked'], 'A shape must default to free (unlocked) proportions so height can be dragged independently.' );

$mySlider_test_state['posts'][42] = (object) array(
	'ID'           => 42,
	'post_type'    => 'psp_slider',
	'post_status'  => 'publish',
	'post_title'   => 'Summer slider',
	'post_content' => '',
	'post_author'  => 7,
);

MySliderPro\SliderPostType::save_meta(
	42,
	array( 102, 101, 102, 999 ),
	array(
		'height'           => 'tall',
		'tablet_height'    => 'standard',
		'mobile_height'    => 'compact',
		'content_position' => 'center',
		'tablet_content_position' => 'left',
		'mobile_content_position' => 'right',
		'tablet_text_width' => 'comfortable',
		'mobile_text_width' => 'narrow',
		'tablet_button_size' => 'standard',
		'mobile_button_size' => 'full',
		'overlay'          => 'strong',
		'arrows'           => true,
		'hide_arrows_on_phone' => true,
		'dots'             => true,
		'autoplay'         => true,
		'interval'         => 7000,
		'loop'             => true,
		'pause_on_hover'   => true,
	),
	array(
		102 => array(
			'title'        => 'Find your next view',
			'description'  => 'A responsive slider that stays clear on every screen.',
			'heading_link_url' => 'https://example.test/heading',
			'description_link_url' => 'https://example.test/description',
			'button_label' => 'View collection',
			'button_url'   => '/collection',
			'image_layer_url' => 'https://example.test/badge.png',
			'image_layer_alt' => 'Sale badge',
			'image_link_url' => 'https://example.test/badge',
			'background_position' => 'center_right',
			'text_x'             => '18',
			'text_y'             => '37',
			'button_x'           => '22',
			'button_y'           => '76',
			'image_x'            => '72',
			'image_y'            => '30',
			'tablet_text_x'      => '44',
			'tablet_text_y'      => '46',
			'tablet_description_x' => '45',
			'tablet_description_y' => '58',
			'tablet_button_x'    => '48',
			'tablet_button_y'    => '78',
			'tablet_image_x'     => '70',
			'tablet_image_y'     => '32',
			'mobile_text_x'      => '50',
			'mobile_text_y'      => '42',
			'mobile_description_x' => '51',
			'mobile_description_y' => '56',
			'mobile_button_x'    => '50',
			'mobile_button_y'    => '84',
			'mobile_image_x'     => '50',
			'mobile_image_y'     => '18',
			'text_color'         => '#f2e9d8',
			'heading_size'       => '70',
			'description_color'  => '#d8e9f2',
			'description_size'   => '19',
			'description_align'  => 'right',
			'description_font_family' => 'montserrat',
			'text_align'         => 'center',
			'font_family'        => 'poppins',
			'button_text_color'  => '#ffffff',
			'button_background'  => '#2255aa',
			'button_font_family' => 'inter',
			'button_font_size'   => '18',
			'button_radius'      => '18',
			'button_padding_x'   => '24',
			'button_padding_y'   => '14',
			'image_width'        => '180',
			'image_opacity'      => '85',
			'heading_animation'  => 'slide-up',
			'description_animation' => 'fade',
			'button_animation'   => 'zoom',
			'image_animation'    => 'slide-left',
			'heading_animation_delay' => '50',
			'description_animation_delay' => '150',
			'button_animation_delay' => '250',
			'image_animation_delay' => '75',
			'heading_animation_duration' => '650',
			'description_animation_duration' => '700',
			'button_animation_duration' => '750',
			'image_animation_duration' => '800',
			'heading_animation_easing' => 'ease-in',
			'description_animation_easing' => 'ease-out',
			'button_animation_easing' => 'ease-in-out',
			'image_animation_easing' => 'linear',
			'tablet_heading_size' => '48',
			'mobile_heading_size' => '36',
			'heading_size_linked' => '',
			'button_size_linked' => '1',
			'tablet_button_font_size' => '30',
			'button_pos_linked'  => '1',
			'layer_order'       => 'description,button,heading,image',
		),
	)
);
mySlider_test_assert( array( 102, 101 ) === MySliderPro\SliderPostType::get_image_ids( 42 ), 'Saved image IDs must be validated and deduplicated.' );
mySlider_test_assert( 'tall' === MySliderPro\SliderPostType::get_settings( 42 )['height'], 'Slider settings did not persist.' );
mySlider_test_assert( 'left' === MySliderPro\SliderPostType::get_settings( 42 )['tablet_content_position'], 'Tablet alignment did not persist.' );
mySlider_test_assert( 'right' === MySliderPro\SliderPostType::get_settings( 42 )['mobile_content_position'], 'Mobile alignment did not persist.' );
mySlider_test_assert( 'comfortable' === MySliderPro\SliderPostType::get_settings( 42 )['tablet_text_width'], 'Tablet text width did not persist.' );
mySlider_test_assert( 'narrow' === MySliderPro\SliderPostType::get_settings( 42 )['mobile_text_width'], 'Mobile text width did not persist.' );
mySlider_test_assert( 'standard' === MySliderPro\SliderPostType::get_settings( 42 )['tablet_button_size'], 'Tablet button size did not persist.' );
mySlider_test_assert( 'full' === MySliderPro\SliderPostType::get_settings( 42 )['mobile_button_size'], 'Mobile button size did not persist.' );
mySlider_test_assert( true === MySliderPro\SliderPostType::get_settings( 42 )['hide_arrows_on_phone'], 'The phone-arrow setting did not persist.' );
mySlider_test_assert( 'Find your next view' === MySliderPro\SliderPostType::get_slide_content( 42 )[102]['title'], 'Slide content did not persist.' );
mySlider_test_assert( 'center_right' === MySliderPro\SliderPostType::get_slide_content( 42 )[102]['background_position'] && 'center_right' === MySliderPro\SliderPostType::get_slide_content( 42 )[102]['tablet_background_position'], 'Background position must persist and cascade to tablet.' );
mySlider_test_assert( 18 === MySliderPro\SliderPostType::get_slide_content( 42 )[102]['text_x'], 'Desktop text-layer position did not persist.' );
mySlider_test_assert( 44 === MySliderPro\SliderPostType::get_slide_content( 42 )[102]['tablet_text_x'], 'Tablet text-layer position did not persist.' );
mySlider_test_assert( 45 === MySliderPro\SliderPostType::get_slide_content( 42 )[102]['tablet_description_x'] && 58 === MySliderPro\SliderPostType::get_slide_content( 42 )[102]['tablet_description_y'], 'The independent description-layer position did not persist.' );
mySlider_test_assert( 84 === MySliderPro\SliderPostType::get_slide_content( 42 )[102]['mobile_button_y'], 'Mobile button-layer position did not persist.' );
mySlider_test_assert( 'https://example.test/heading' === MySliderPro\SliderPostType::get_slide_content( 42 )[102]['heading_link_url'], 'Heading layer link did not persist.' );
mySlider_test_assert( 'https://example.test/description' === MySliderPro\SliderPostType::get_slide_content( 42 )[102]['description_link_url'], 'Description layer link did not persist.' );
mySlider_test_assert( 'https://example.test/badge.png' === MySliderPro\SliderPostType::get_slide_content( 42 )[102]['image_layer_url'], 'Image layer content did not persist.' );
mySlider_test_assert( 'https://example.test/badge' === MySliderPro\SliderPostType::get_slide_content( 42 )[102]['image_link_url'], 'Image layer link did not persist.' );
mySlider_test_assert( 70 === MySliderPro\SliderPostType::get_slide_content( 42 )[102]['tablet_image_x'] && 18 === MySliderPro\SliderPostType::get_slide_content( 42 )[102]['mobile_image_y'], 'Responsive image-layer positions did not persist.' );
mySlider_test_assert( '#2255aa' === MySliderPro\SliderPostType::get_slide_content( 42 )[102]['button_background'], 'Button styling did not persist.' );
mySlider_test_assert( 18 === MySliderPro\SliderPostType::get_slide_content( 42 )[102]['button_font_size'], 'Button text size did not persist.' );
mySlider_test_assert( 'slide-left' === MySliderPro\SliderPostType::get_slide_content( 42 )[102]['image_animation'], 'Image layer animation did not persist.' );
mySlider_test_assert( 48 === MySliderPro\SliderPostType::get_slide_content( 42 )[102]['tablet_heading_size'] && 36 === MySliderPro\SliderPostType::get_slide_content( 42 )[102]['mobile_heading_size'], 'Per-device heading sizes did not persist.' );
mySlider_test_assert( '' === MySliderPro\SliderPostType::get_slide_content( 42 )[102]['heading_size_linked'] && '1' === MySliderPro\SliderPostType::get_slide_content( 42 )[102]['button_size_linked'] && '1' === MySliderPro\SliderPostType::get_slide_content( 42 )[102]['button_pos_linked'], 'Responsive link flags did not persist.' );
mySlider_test_assert( 'description,button,heading,image' === MySliderPro\SliderPostType::get_slide_content( 42 )[102]['layer_order'], 'Layer stack order did not persist.' );

call_user_func( mySlider_test_hook( 'actions', 'admin_menu' ) );
$menu_page = $mySlider_test_state['menu_pages']['my-slider-pro'] ?? array();
mySlider_test_assert( 'upload_files' === ( $menu_page['capability'] ?? '' ), 'The slider menu capability is incorrect.' );
$menu_icon = (string) ( $menu_page['icon_url'] ?? '' );
mySlider_test_assert( 0 === strpos( $menu_icon, 'data:image/svg+xml;base64,' ), 'The slider menu must use a base64-encoded SVG icon.' );
$menu_icon_svg = (string) base64_decode( substr( $menu_icon, strlen( 'data:image/svg+xml;base64,' ) ), true );
mySlider_test_assert( false !== strpos( $menu_icon_svg, '<svg' ) && false !== strpos( $menu_icon_svg, '#a7aaad' ), 'The slider menu icon must be a monochrome SVG glyph.' );

ob_start();
call_user_func( $menu_page['callback'] );
$overview_output = (string) ob_get_clean();
mySlider_test_contains( 'psp-page-header psp-hero', $overview_output, 'The overview must lead with the branded gradient hero.' );
mySlider_test_contains( 'psp-hero-glyph', $overview_output, 'The overview hero must include the brand glyph.' );
mySlider_test_contains( 'psp-slider-cards', $overview_output, 'The overview must render sliders as a card grid.' );
mySlider_test_contains( 'psp-slider-card', $overview_output, 'Each slider must render as a card.' );
mySlider_test_assert( false === strpos( $overview_output, 'psp-slider-table' ), 'The overview must no longer render the old table layout.' );
mySlider_test_contains( 'psp-slider-card-title" href=', $overview_output, 'Clicking the slider title must open the editor.' );
mySlider_test_contains( 'psp-slider-card-slides', $overview_output, 'The card must show the slide count under the title.' );
mySlider_test_contains( 'psp-set-thumbnail', $overview_output, 'Each card must offer a Set thumbnail control.' );
mySlider_test_contains( 'my_slider_pro_set_thumbnail', $overview_output, 'The Set thumbnail form must submit to the thumbnail handler.' );
mySlider_test_contains( 'psp-slider-card-badge', $overview_output, 'Each card must indicate whether the thumbnail is custom or the first slide.' );
mySlider_test_assert( isset( $mySlider_test_state['actions']['admin_post_my_slider_pro_set_thumbnail'] ), 'The secure set-thumbnail handler must be registered.' );
mySlider_test_contains( 'psp-rename-toggle', $overview_output, 'The overview needs a compact Rename action.' );
mySlider_test_contains( 'psp-quick-rename-form" method="post"', $overview_output, 'The overview needs a quick rename form.' );
mySlider_test_contains( 'hidden>', $overview_output, 'The quick rename form must stay hidden until Rename is clicked.' );
mySlider_test_contains( 'my_slider_pro_rename_slider', $overview_output, 'The quick rename form must submit to the rename handler.' );
mySlider_test_contains( 'my_slider_pro_duplicate_slider', $overview_output, 'The overview needs a duplicate action.' );
mySlider_test_contains( '2 slides', $overview_output, 'The overview slide count label must use slides wording.' );

// General settings button and modal render on the overview.
mySlider_test_contains( 'psp-open-settings', $overview_output, 'The overview needs a Settings button.' );
mySlider_test_contains( 'id="psp-settings-modal"', $overview_output, 'The overview needs the general settings modal.' );
mySlider_test_contains( 'my_slider_pro_settings[google_fonts]', $overview_output, 'The settings modal needs the Google fonts control.' );
mySlider_test_contains( 'my_slider_pro_settings[load_assets_everywhere]', $overview_output, 'The settings modal needs the load-everywhere toggle.' );
mySlider_test_contains( 'my_slider_pro_settings[delete_on_uninstall]', $overview_output, 'The settings modal needs the delete-on-uninstall toggle.' );
mySlider_test_contains( 'my_slider_pro_save_settings', $overview_output, 'The settings modal must submit to the save handler.' );
mySlider_test_contains( 'my_slider_pro_flush_cache', $overview_output, 'The settings modal needs a flush-cache action.' );

// Settings sanitize whitelists the font mode and coerces toggles to booleans.
$mySlider_settings_clean = MySliderPro\Settings::sanitize( array( 'google_fonts' => 'local', 'resource_preloading' => '1' ) );
mySlider_test_assert( 'local' === $mySlider_settings_clean['google_fonts'], 'Sanitize should accept the local font mode.' );
mySlider_test_assert( true === $mySlider_settings_clean['resource_preloading'], 'Sanitize should enable a checked toggle.' );
mySlider_test_assert( false === $mySlider_settings_clean['allow_svg'], 'Sanitize should default an unchecked toggle to false.' );
$mySlider_settings_bad = MySliderPro\Settings::sanitize( array( 'google_fonts' => 'evil' ) );
mySlider_test_assert( 'enabled' === $mySlider_settings_bad['google_fonts'], 'Sanitize should reject an unknown font mode.' );

// Saving settings persists values and redirects with a status flag.
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = array(
	'action'                        => 'my_slider_pro_save_settings',
	'my_slider_pro_settings_nonce'  => 'test-nonce',
	'my_slider_pro_settings'        => array(
		'load_assets_everywhere' => '1',
		'google_fonts'           => 'disabled',
	),
);
$mySlider_test_state['nonce_valid']     = true;
$mySlider_test_state['redirect_throws'] = true;
$mySlider_settings_redirect = mySlider_test_redirect(
	static function (): void {
		MySliderPro\Settings::handle_save();
	},
	'The settings handler did not redirect after saving.'
);
mySlider_test_contains( 'my_slider_pro_settings=saved', $mySlider_settings_redirect, 'Saving settings should redirect with a saved flag.' );
mySlider_test_assert( true === MySliderPro\Settings::get( 'load_assets_everywhere' ), 'Saved settings should persist the load-everywhere toggle.' );
mySlider_test_assert( 'disabled' === MySliderPro\Settings::get( 'google_fonts' ), 'Saved settings should persist the font mode.' );

// The flush-cache action shares the settings nonce and redirects with a flag.
$_POST = array(
	'action'                       => 'my_slider_pro_flush_cache',
	'my_slider_pro_settings_nonce' => 'test-nonce',
);
$mySlider_flush_redirect = mySlider_test_redirect(
	static function (): void {
		MySliderPro\Settings::handle_flush();
	},
	'The flush-cache handler did not redirect.'
);
mySlider_test_contains( 'my_slider_pro_settings=flushed', $mySlider_flush_redirect, 'Flushing cache should redirect with a flushed flag.' );

// Reset options so later assertions observe defaults.
delete_option( 'my_slider_pro_settings' );

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = array(
	'action'              => 'my_slider_pro_rename_slider',
	'slider_id'           => '42',
	'slider_title'        => 'Quick renamed slider',
	'my_slider_pro_nonce' => 'test-nonce',
);
$mySlider_test_state['nonce_valid'] = true;
$mySlider_test_state['redirect_throws'] = true;
$rename_redirect = mySlider_test_redirect(
	static function (): void {
		MySliderPro\AdminPage::handle_rename();
	},
	'The quick rename handler did not redirect after success.'
);
mySlider_test_assert( 'Quick renamed slider' === $mySlider_test_state['posts'][42]->post_title, 'The quick rename handler did not update the slider title.' );
mySlider_test_contains( 'updated=1', $rename_redirect, 'The quick rename redirect must report success.' );

$_POST = array(
	'action'              => 'my_slider_pro_duplicate_slider',
	'slider_id'           => '42',
	'my_slider_pro_nonce' => 'test-nonce',
);
$duplicate_redirect = mySlider_test_redirect(
	static function (): void {
		MySliderPro\AdminPage::handle_duplicate();
	},
	'The duplicate handler did not redirect after success.'
);
$duplicated_id = $mySlider_test_state['next_post_id'];
mySlider_test_assert( 'Quick renamed slider (2)' === $mySlider_test_state['posts'][ $duplicated_id ]->post_title, 'The duplicate handler did not append a numeric suffix.' );
mySlider_test_assert( 'draft' === $mySlider_test_state['posts'][ $duplicated_id ]->post_status, 'Duplicated sliders should be created as drafts.' );
mySlider_test_assert( MySliderPro\SliderPostType::get_image_ids( 42 ) === MySliderPro\SliderPostType::get_image_ids( $duplicated_id ), 'The duplicate handler did not copy slide images.' );
mySlider_test_assert( MySliderPro\SliderPostType::get_settings( 42 ) === MySliderPro\SliderPostType::get_settings( $duplicated_id ), 'The duplicate handler did not copy slider settings.' );
mySlider_test_contains( 'duplicated=1', $duplicate_redirect, 'The duplicate redirect must report success.' );
$mySlider_test_state['redirect_throws'] = false;

$_GET['slider_id'] = '42';
$mySlider_test_state['menu_pages'] = array();
$mySlider_test_state['submenu_pages'] = array();
MySliderPro\AdminPage::register();
$editor_page = $mySlider_test_state['submenu_pages']['my-slider-pro-new'];
ob_start();
call_user_func( $editor_page['callback'] );
$editor_output = (string) ob_get_clean();
mySlider_test_contains( '>Slides<', $editor_output, 'The slides section heading is missing.' );
mySlider_test_contains( 'Add New Slide', $editor_output, 'The slide add button label is incorrect.' );
mySlider_test_contains( 'my_slider_pro_slide_content[102][title]', $editor_output, 'Each slide needs a heading field.' );
mySlider_test_contains( 'my_slider_pro_slide_content[102][heading_link_url]', $editor_output, 'Each slide needs a heading link field.' );
mySlider_test_contains( 'my_slider_pro_slide_content[102][description_link_url]', $editor_output, 'Each slide needs a description link field.' );
mySlider_test_contains( 'my_slider_pro_slide_content[102][button_url]', $editor_output, 'Each slide needs a CTA link field.' );
mySlider_test_contains( 'my_slider_pro_slide_content[102][image_layer_url]', $editor_output, 'Each slide needs an image-layer URL field.' );
mySlider_test_contains( 'psp-select-image-layer', $editor_output, 'Each slide needs a Media Library image-layer picker.' );
mySlider_test_contains( 'psp-add-extra-layer', $editor_output, 'Each slide needs controls to add repeatable overlay layers.' );
mySlider_test_contains( 'psp-delete-layer', $editor_output, 'The layer inspector needs a delete-layer control.' );
mySlider_test_contains( 'psp-replace-image', $editor_output, 'Each slide needs a control to replace its background image.' );
mySlider_test_contains( 'psp-canvas-layer-tools', $editor_output, 'The visual editor needs canvas-level add layer tools.' );
mySlider_test_contains( 'psp-set-slide-background', $editor_output, 'The Add Layer toolbar needs a Slide Background control.' );
mySlider_test_contains( 'data-psp-extra-layer-type="shape"', $editor_output, 'The Add Layer toolbar needs a Shape control.' );
mySlider_test_contains( 'data-psp-style-section="shape"', $editor_output, 'The layer inspector needs a Shape style section.' );
mySlider_test_contains( 'data-psp-style-key="shape_fill"', $editor_output, 'The Shape inspector needs a fill-color control.' );
mySlider_test_contains( 'data-psp-style-key="shape_radius"', $editor_output, 'The Shape inspector needs a corner-radius control.' );
mySlider_test_contains( 'data-psp-style-key="shape_overlay_type"', $editor_output, 'The Shape inspector needs an overlay-type control.' );
mySlider_test_contains( 'psp-canvas-panel', $editor_output, 'The editor workspace should present a visual preview canvas.' );
mySlider_test_contains( 'Selected slide properties', $editor_output, 'The selected slide drawer should replace the old form-like slide content label.' );
mySlider_test_contains( 'data-psp-extra-layers', $editor_output, 'Each slide needs a repeatable layer container.' );
mySlider_test_contains( 'my_slider_pro_slide_content[102][image_link_url]', $editor_output, 'Each slide needs an image-layer link field.' );
mySlider_test_contains( 'my_slider_pro_slide_content[102][tablet_background_position]', $editor_output, 'Each slide needs a tablet background-position field.' );
mySlider_test_contains( 'my_slider_pro_slide_content[102][mobile_background_position]', $editor_output, 'Each slide needs a phone background-position field.' );
mySlider_test_contains( 'my_slider_pro_slide_content[102][text_x]', $editor_output, 'Each slide needs a desktop text-layer coordinate.' );
mySlider_test_contains( 'my_slider_pro_slide_content[102][tablet_text_x]', $editor_output, 'Each slide needs a tablet text-layer coordinate.' );
mySlider_test_contains( 'my_slider_pro_slide_content[102][description_x]', $editor_output, 'Each slide needs an independent desktop description-layer coordinate.' );
mySlider_test_contains( 'my_slider_pro_slide_content[102][image_x]', $editor_output, 'Each slide needs an independent desktop image-layer coordinate.' );
mySlider_test_contains( 'my_slider_pro_slide_content[102][mobile_description_y]', $editor_output, 'Each slide needs an independent phone description-layer coordinate.' );
mySlider_test_contains( 'my_slider_pro_slide_content[102][tablet_image_y]', $editor_output, 'Each slide needs an independent tablet image-layer coordinate.' );
mySlider_test_contains( 'my_slider_pro_slide_content[102][mobile_button_y]', $editor_output, 'Each slide needs a mobile button-layer coordinate.' );
mySlider_test_contains( 'my_slider_pro_slide_content[102][layer_order]', $editor_output, 'Each slide needs a saved layer stack order.' );
mySlider_test_contains( 'my_slider_pro_slide_content[102][heading_size_linked]', $editor_output, 'The slide store must persist the size-link flag, or unlinking silently no-ops.' );
mySlider_test_contains( 'my_slider_pro_slide_content[102][heading_pos_linked]', $editor_output, 'The slide store must persist the position-link flag.' );
mySlider_test_contains( 'my_slider_pro_slide_content[102][tablet_heading_size]', $editor_output, 'The slide store must persist per-device sizes so unlinked sizes survive a save.' );
mySlider_test_contains( 'data-psp-link-key="size"', $editor_output, 'The Layer Inspector needs a link-size-across-devices toggle.' );
mySlider_test_contains( 'data-psp-link-key="pos"', $editor_output, 'The Layer Inspector needs a link-position-across-devices toggle.' );
mySlider_test_assert( false === strpos( $editor_output, '[overlay_strength]' ), 'The removed overlay-strength option must not render.' );
mySlider_test_assert( false === strpos( $editor_output, '[background_filter]' ), 'The removed grayscale option must not render.' );
mySlider_test_contains( 'my_slider_pro_slide_content[102][background_fill]', $editor_output, 'The slide store must persist the background fill mode.' );
mySlider_test_contains( 'my_slider_pro_slide_content[102][overlay_type]', $editor_output, 'The slide store must persist the overlay type.' );
mySlider_test_contains( 'my_slider_pro_slide_content[102][overlay_opacity]', $editor_output, 'The slide store must persist the overlay opacity.' );
mySlider_test_contains( 'my_slider_pro_slide_content[102][overlay_color2]', $editor_output, 'The slide store must persist the gradient second color.' );
mySlider_test_contains( 'data-psp-slide-key="overlay_type"', $editor_output, 'The overlay type control must render.' );
mySlider_test_contains( 'psp-replace-background', $editor_output, 'The Background panel needs a Replace background control.' );
mySlider_test_contains( 'name="slider_width"', $editor_output, 'Slider Settings needs a width control.' );
mySlider_test_contains( 'name="slider_max_width"', $editor_output, 'Slider Settings needs a max-width control.' );
mySlider_test_contains( 'data-psp-layer-device="tablet"', $editor_output, 'Each slide needs tablet layer-position presets.' );
mySlider_test_contains( 'data-psp-layer-device="mobile"', $editor_output, 'Each slide needs mobile layer-position presets.' );
mySlider_test_contains( 'id="my-slider-pro-preview-viewport"', $editor_output, 'The framed responsive preview is missing.' );
mySlider_test_contains( 'id="psp-layer-inspector-x"', $editor_output, 'The layer editor needs a precise X-coordinate inspector.' );
mySlider_test_assert( false === strpos( $editor_output, 'id="my-slider-pro-active-slide"' ), 'The selected-slide dropdown must be removed; slides are chosen from the thumbnail list.' );
mySlider_test_contains( 'id="psp-active-slide-fields"', $editor_output, 'The active slide content must render in the Slide inspector panel.' );
mySlider_test_contains( 'data-psp-slide-layers', $editor_output, 'The Layers list must render in the bottom layers panel.' );
mySlider_test_contains( 'psp-layer-strip-items', $editor_output, 'The Layers list needs a sortable items container.' );
mySlider_test_contains( 'psp-title-field', $editor_output, 'The slider name field must render in the Slides header.' );
mySlider_test_contains( 'psp-editor-shortcode', $editor_output, 'The shortcode copy control must sit in the Slides header near the slider name.' );
mySlider_test_contains( 'psp-panel-heading-actions', $editor_output, 'Save, cancel, and add-slide controls must sit in the Slides header.' );
mySlider_test_contains( 'psp-editor-sidebar', $editor_output, 'The editor must render the layer inspector sidebar.' );
mySlider_test_contains( 'data-psp-inspector-panel="layer"', $editor_output, 'The sidebar must render the Layer inspector.' );
mySlider_test_assert( false === strpos( $editor_output, 'psp-inspector-tabs' ), 'The inspector tab strip must be removed; only the Layer inspector remains.' );
mySlider_test_assert( false === strpos( $editor_output, 'data-psp-inspector-tab' ), 'No inspector tabs should remain.' );
mySlider_test_contains( 'psp-slide-fields-store', $editor_output, 'The per-slide fields must persist as a hidden data store for saving.' );
mySlider_test_contains( 'data-psp-inspector-panel="slide"', $editor_output, 'The hidden slide field store keeps its panel hook.' );
mySlider_test_contains( 'psp-slider-settings-panel', $editor_output, 'Slider settings must render in a full-width bottom panel.' );
mySlider_test_contains( 'psp-slide-layers-panel', $editor_output, 'The Layers list must render in a full-width bottom panel.' );
mySlider_test_assert( strpos( $editor_output, 'psp-slide-layers-panel' ) < strpos( $editor_output, 'psp-editor-sidebar' ), 'The Layers and Slider Settings panels must render in the main column, before the inspector sidebar, so they do not overlap it.' );
mySlider_test_assert( strpos( $editor_output, 'psp-slide-layers-panel' ) < strpos( $editor_output, 'psp-slider-settings-panel' ), 'The Layers panel must sit above the Slider Settings panel.' );
mySlider_test_assert( strpos( $editor_output, 'psp-slider-settings-panel' ) < strpos( $editor_output, 'my-slider-pro-height' ), 'Slider settings fields must render inside the Slider Settings bottom panel.' );
mySlider_test_contains( 'data-psp-style-key="font_family"', $editor_output, 'The layer inspector needs typography controls.' );
mySlider_test_contains( 'data-psp-style-key="heading_link_url"', $editor_output, 'The layer inspector must expose the heading link field.' );
mySlider_test_contains( 'data-psp-style-key="button_url"', $editor_output, 'The layer inspector must expose the button link field.' );
mySlider_test_contains( 'data-psp-content-toggle="button_target"', $editor_output, 'The layer inspector must expose the open-in-new-tab toggle.' );
mySlider_test_contains( 'data-psp-content-toggle="heading_target"', $editor_output, 'The heading section needs its own open-in-new-tab toggle.' );
mySlider_test_contains( 'data-psp-content-toggle="description_target"', $editor_output, 'The description section needs its own open-in-new-tab toggle.' );
mySlider_test_contains( 'data-psp-content-toggle="image_target"', $editor_output, 'The image section needs its own open-in-new-tab toggle.' );
mySlider_test_contains( 'psp-link-pick', $editor_output, 'Inspector link fields must offer the internal page/post picker.' );
mySlider_test_contains( '[heading_target]', $editor_output, 'The hidden slide store must persist the heading new-tab flag.' );
mySlider_test_contains( '[description_target]', $editor_output, 'The hidden slide store must persist the description new-tab flag.' );
mySlider_test_contains( '[image_target]', $editor_output, 'The hidden slide store must persist the image new-tab flag.' );
mySlider_test_contains( 'psp-accordion-static', $editor_output, 'The Animation section must render as a static, always-visible section.' );
mySlider_test_contains( 'psp-about-card', $editor_output, 'The editor must render the About / support card at the bottom, like the overview.' );
mySlider_test_contains( 'psp-editor-header psp-hero', $editor_output, 'The editor must lead with the same branded gradient hero as the overview.' );
mySlider_test_contains( 'psp-hero-glyph', $editor_output, 'The editor hero must include the brand glyph.' );
mySlider_test_assert( strpos( $editor_output, 'psp-back-link' ) < strpos( $editor_output, 'psp-title-row' ), 'The back link must sit above the editor hero title.' );
mySlider_test_assert( false === strpos( $editor_output, 'data-psp-collapsible' ), 'No collapsed accordions may remain in the layer inspector.' );
mySlider_test_contains( 'data-psp-style-key="image_layer_url"', $editor_output, 'The layer inspector must expose the image layer URL field.' );
mySlider_test_contains( 'data-psp-slide-key="background_position"', $editor_output, 'The Background settings panel must expose the position control.' );
mySlider_test_contains( 'value="poppins"', $editor_output, 'The layer inspector needs Poppins as a font choice.' );
mySlider_test_contains( 'data-psp-style-key="heading_font_style"', $editor_output, 'The layer inspector needs a heading font-style control.' );
mySlider_test_contains( 'data-psp-style-key="button_font_style"', $editor_output, 'The layer inspector needs a button font-style control.' );
mySlider_test_contains( 'value="montserrat"', $editor_output, 'The layer inspector needs Montserrat as a font choice.' );
mySlider_test_contains( 'value="inter"', $editor_output, 'The layer inspector needs Inter as a font choice.' );
mySlider_test_contains( 'data-psp-style-section="heading"', $editor_output, 'The inspector needs a dedicated Heading style panel.' );
mySlider_test_contains( 'data-psp-style-section="description"', $editor_output, 'The inspector needs a dedicated Description style panel.' );
mySlider_test_contains( 'data-psp-style-section="image"', $editor_output, 'The inspector needs a dedicated Image style panel.' );
mySlider_test_contains( 'data-psp-animation-key="animation"', $editor_output, 'The layer inspector needs animation controls.' );
mySlider_test_contains( 'min="0" max="5000" step="1" value="0" data-psp-animation-key="animation_delay"', $editor_output, 'Animation delay must accept any saved millisecond value; a coarser step blocks saving legal data.' );
mySlider_test_contains( 'min="100" max="5000" step="1" value="600" data-psp-animation-key="animation_duration"', $editor_output, 'Animation duration must accept any saved millisecond value; a coarser step blocks saving legal data.' );
mySlider_test_contains( 'data-psp-style-key="button_background"', $editor_output, 'The layer inspector needs button styling controls.' );
mySlider_test_contains( 'data-psp-style-key="button_font_family"', $editor_output, 'The layer inspector needs button typography controls.' );
mySlider_test_contains( 'data-psp-style-key="button_font_size"', $editor_output, 'The layer inspector needs a button text-size control.' );
mySlider_test_contains( 'data-psp-style-key="heading_opacity"', $editor_output, 'The layer inspector needs heading opacity control.' );
mySlider_test_contains( 'data-psp-style-key="description_opacity"', $editor_output, 'The layer inspector needs description opacity control.' );
mySlider_test_contains( 'data-psp-style-key="button_opacity"', $editor_output, 'The layer inspector needs button opacity control.' );
mySlider_test_contains( 'name="editor_view" value="preview"', $editor_output, 'The editor must retain the active workspace after saving.' );
mySlider_test_assert( false === strpos( $editor_output, 'psp-editor-tab' ), 'The editor must not render separate slide/layer tabs.' );
mySlider_test_assert( false === strpos( $editor_output, 'data-psp-tab' ), 'The editor must not expose tab switching controls.' );
mySlider_test_contains( 'psp-slide-order-panel', $editor_output, 'The combined editor needs a visible slide order panel.' );
mySlider_test_contains( 'psp-canvas-panel', $editor_output, 'The combined editor needs a visible canvas panel.' );
mySlider_test_contains( 'data-psp-device="desktop"', $editor_output, 'The desktop preview control is missing.' );
mySlider_test_contains( 'data-psp-device="tablet"', $editor_output, 'The tablet preview control is missing.' );
mySlider_test_contains( 'data-psp-device="phone"', $editor_output, 'The phone preview control is missing.' );
mySlider_test_contains( 'name="slider_tablet_height"', $editor_output, 'A dedicated tablet-height control is required.' );
mySlider_test_contains( 'name="slider_mobile_height"', $editor_output, 'A dedicated mobile-height control is required.' );
mySlider_test_contains( 'name="slider_tablet_content_position"', $editor_output, 'A dedicated tablet-alignment control is required.' );
mySlider_test_contains( 'name="slider_mobile_content_position"', $editor_output, 'A dedicated mobile-alignment control is required.' );
mySlider_test_contains( 'name="slider_tablet_text_width"', $editor_output, 'A dedicated tablet text-width control is required.' );
mySlider_test_contains( 'name="slider_mobile_text_width"', $editor_output, 'A dedicated mobile text-width control is required.' );
mySlider_test_contains( 'name="slider_tablet_button_size"', $editor_output, 'A dedicated tablet button-size control is required.' );
mySlider_test_contains( 'name="slider_mobile_button_size"', $editor_output, 'A dedicated mobile button-size control is required.' );
mySlider_test_contains( 'name="slider_hide_arrows_on_phone"', $editor_output, 'A phone-arrow visibility control is required.' );
mySlider_test_contains( 'name="slider_autoplay"', $editor_output, 'Autoplay must be configurable in the editor.' );

$mySlider_test_state['enqueued_styles'] = array();
$mySlider_test_state['enqueued_scripts'] = array();
$mySlider_test_state['media_calls'] = array();
MySliderPro\AdminPage::enqueue_assets( $editor_page['hook'] );
mySlider_test_assert( isset( $mySlider_test_state['enqueued_styles']['my-slider-pro-admin'] ), 'Editor CSS was not loaded.' );
mySlider_test_assert( isset( $mySlider_test_state['enqueued_styles']['my-slider-pro-fonts'] ), 'Editor fonts were not loaded.' );
mySlider_test_contains( 'fonts.googleapis.com/css2', $mySlider_test_state['registered_styles']['my-slider-pro-fonts']['src'] ?? '', 'Editor fonts must load from Google Fonts.' );
mySlider_test_contains( 'Poppins', $mySlider_test_state['registered_styles']['my-slider-pro-fonts']['src'] ?? '', 'Editor fonts must include Poppins.' );
mySlider_test_contains( 'Montserrat', $mySlider_test_state['registered_styles']['my-slider-pro-fonts']['src'] ?? '', 'Editor fonts must include Montserrat.' );
mySlider_test_contains( 'Inter', $mySlider_test_state['registered_styles']['my-slider-pro-fonts']['src'] ?? '', 'Editor fonts must include Inter.' );
mySlider_test_assert( isset( $mySlider_test_state['enqueued_scripts']['my-slider-pro-admin'] ), 'Editor JavaScript was not loaded.' );
mySlider_test_assert( array( 'post' => 42 ) === $mySlider_test_state['media_calls'][0], 'The Media Library was not scoped to the edited slider.' );

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = array(
	'action'                      => 'my_slider_pro_save_slider',
	'slider_id'                   => '42',
	'editor_view'                 => 'preview',
	'slider_title'                => 'Updated slider',
	'my_slider_pro_image_ids'     => array( '101', '102' ),
	'slider_height'               => 'standard',
	'slider_width'                => 'boxed',
	'slider_max_width'            => '1000',
	'slider_tablet_height'        => 'compact',
	'slider_mobile_height'        => 'tall',
	'slider_content_position'     => 'right',
	'slider_tablet_content_position' => 'left',
	'slider_mobile_content_position' => 'center',
	'slider_tablet_text_width'    => 'narrow',
	'slider_mobile_text_width'    => 'wide',
	'slider_tablet_button_size'   => 'full',
	'slider_mobile_button_size'   => 'large',
	'slider_overlay'              => 'light',
	'slider_arrows'               => '1',
	'slider_hide_arrows_on_phone' => '1',
	'slider_dots'                 => '1',
	'slider_autoplay'             => '1',
	'slider_interval'             => '3000',
	'slider_loop'                 => '1',
	'slider_pause_on_hover'       => '1',
	'my_slider_pro_slide_content' => array(
		101 => array(
			'title'         => 'Saved heading',
			'description'   => 'Saved description',
			'heading_link_url' => 'https://example.test/saved-heading',
			'heading_target' => '1',
			'description_link_url' => 'https://example.test/saved-description',
			'button_label'  => 'Visit',
			'button_url'    => 'https://example.test/visit',
			'button_target' => '1',
			'image_layer_url' => 'https://example.test/logo.png',
			'image_layer_alt' => 'Brand logo',
			'image_link_url' => 'https://example.test/logo-link',
			'image_target'   => '1',
			'background_position'        => 'center_left',
			'tablet_background_position' => 'top_right',
			'mobile_background_position' => 'bottom_center',
			'overlay_type'       => 'gradient',
			'overlay_color'      => '#112233',
			'overlay_color2'     => '#445566',
			'overlay_opacity'    => '40',
			'overlay_direction'  => 'to right',
			'text_x'             => '27',
			'text_y'             => '34',
			'button_x'           => '22',
			'button_y'           => '79',
			'image_x'            => '72',
			'image_y'            => '24',
			'tablet_text_x'      => '43',
			'tablet_text_y'      => '41',
			'tablet_description_x' => '45',
			'tablet_description_y' => '55',
			'tablet_button_x'    => '48',
			'tablet_button_y'    => '75',
			'tablet_image_x'     => '70',
			'tablet_image_y'     => '26',
			'mobile_text_x'      => '52',
			'mobile_text_y'      => '39',
			'mobile_description_x' => '53',
			'mobile_description_y' => '54',
			'mobile_button_x'    => '54',
			'mobile_button_y'    => '79',
			'mobile_image_x'     => '50',
			'mobile_image_y'     => '20',
			'text_color'         => '#ffeecc',
			'heading_size'       => '68',
			'tablet_heading_size' => '48',
			'mobile_heading_size' => '36',
			'heading_size_linked' => '',
			'heading_opacity'    => '74',
			'description_color'  => '#ccffee',
			'description_size'   => '18',
			'description_opacity' => '86',
			'description_align'  => 'center',
			'description_font_family' => 'inter',
			'text_align'         => 'right',
			'font_family'        => 'montserrat',
			'button_text_color'  => '#111111',
			'button_background'  => '#ffcc00',
			'button_font_family' => 'inter',
			'button_font_size'   => '18',
			'tablet_button_font_size' => '30',
			'button_size_linked' => '1',
			'button_pos_linked'  => '1',
			'button_opacity'     => '92',
			'button_radius'      => '22',
			'button_padding_x'   => '26',
			'button_padding_y'   => '15',
			'image_width'        => '240',
			'image_opacity'      => '80',
			'heading_animation'  => 'slide-up',
			'description_animation' => 'fade',
			'button_animation'   => 'zoom',
			'image_animation'    => 'slide-right',
			'heading_animation_delay' => '100',
			'description_animation_delay' => '200',
			'button_animation_delay' => '300',
			'image_animation_delay' => '400',
			'heading_animation_duration' => '650',
			'description_animation_duration' => '700',
			'button_animation_duration' => '750',
			'image_animation_duration' => '800',
			'heading_animation_easing' => 'ease-in',
			'description_animation_easing' => 'ease-out',
			'button_animation_easing' => 'ease-in-out',
			'image_animation_easing' => 'linear',
			'layer_order'       => 'heading,button,description,image',
			'extra_layers'      => array(
				array(
					'type'      => 'heading',
					'text'      => 'Second heading',
					'link_url'  => 'https://example.test/second-heading',
					'desktop_x' => '35',
					'desktop_y' => '20',
					'tablet_x'  => '40',
					'tablet_y'  => '25',
					'mobile_x'  => '50',
					'mobile_y'  => '28',
					'color'     => '#ffffff',
					'font_family' => 'montserrat',
					'size'      => '44',
					'opacity'   => '90',
				),
				array(
					'type'      => 'button',
					'text'      => 'Second CTA',
					'link_url'  => 'https://example.test/second-cta',
					'desktop_x' => '65',
					'desktop_y' => '80',
				),
				array(
					'type'              => 'shape',
					'desktop_x'         => '30',
					'desktop_y'         => '60',
					'background'        => '#0f6e56',
					'width'             => '420',
					'height'            => '160',
					'radius'            => '18',
					'ratio_locked'      => '1',
					'opacity'           => '80',
					'overlay_type'      => 'gradient',
					'overlay_color'     => '#101820',
					'overlay_color2'    => '#204060',
					'overlay_opacity'   => '60',
					'overlay_direction' => 'to right',
				),
			),
		),
	),
	'my_slider_pro_nonce'         => 'test-nonce',
);
$mySlider_test_state['nonce_valid'] = false;
try {
	MySliderPro\AdminPage::handle_save();
	mySlider_test_fail( 'The save handler accepted an invalid nonce.' );
} catch ( RuntimeException $exception ) {
	mySlider_test_contains( 'nonce', strtolower( $exception->getMessage() ), 'The nonce rejection is not actionable.' );
}
$mySlider_test_state['nonce_valid'] = true;
$mySlider_test_state['redirect_throws'] = true;
$save_redirect = mySlider_test_redirect( array( MySliderPro\AdminPage::class, 'handle_save' ), 'The save handler did not redirect.' );
mySlider_test_contains( 'saved=1', $save_redirect, 'The save redirect is missing its success flag.' );
mySlider_test_contains( 'page=my-slider-pro-new', $save_redirect, 'Saving must return to the slider editor.' );
mySlider_test_contains( 'slider_id=42', $save_redirect, 'The save redirect must retain the edited slider.' );
mySlider_test_contains( 'editor_view=preview', $save_redirect, 'The save redirect must retain the active editor workspace.' );
mySlider_test_assert( 'Updated slider' === $mySlider_test_state['posts'][42]->post_title, 'The save handler did not update the slider title.' );
mySlider_test_assert( 'right' === MySliderPro\SliderPostType::get_settings( 42 )['content_position'], 'The save handler did not persist slider settings.' );
mySlider_test_assert( 'left' === MySliderPro\SliderPostType::get_settings( 42 )['tablet_content_position'], 'The save handler did not persist tablet alignment.' );
mySlider_test_assert( 'center' === MySliderPro\SliderPostType::get_settings( 42 )['mobile_content_position'], 'The save handler did not persist mobile alignment.' );
mySlider_test_assert( 'narrow' === MySliderPro\SliderPostType::get_settings( 42 )['tablet_text_width'], 'The save handler did not persist tablet text width.' );
mySlider_test_assert( 'wide' === MySliderPro\SliderPostType::get_settings( 42 )['mobile_text_width'], 'The save handler did not persist mobile text width.' );
mySlider_test_assert( 'full' === MySliderPro\SliderPostType::get_settings( 42 )['tablet_button_size'], 'The save handler did not persist tablet button size.' );
mySlider_test_assert( 'large' === MySliderPro\SliderPostType::get_settings( 42 )['mobile_button_size'], 'The save handler did not persist mobile button size.' );
mySlider_test_assert( true === MySliderPro\SliderPostType::get_settings( 42 )['hide_arrows_on_phone'], 'The save handler did not persist phone-arrow visibility.' );
mySlider_test_assert( 'Saved heading' === MySliderPro\SliderPostType::get_slide_content( 42 )[101]['title'], 'The save handler did not persist slide content.' );
mySlider_test_assert( 'center_left' === MySliderPro\SliderPostType::get_slide_content( 42 )[101]['background_position'] && 'top_right' === MySliderPro\SliderPostType::get_slide_content( 42 )[101]['tablet_background_position'] && 'bottom_center' === MySliderPro\SliderPostType::get_slide_content( 42 )[101]['mobile_background_position'], 'The save handler did not persist per-device background positions.' );
mySlider_test_assert( 27 === MySliderPro\SliderPostType::get_slide_content( 42 )[101]['text_x'], 'The save handler did not persist desktop text-layer position.' );
mySlider_test_assert( 43 === MySliderPro\SliderPostType::get_slide_content( 42 )[101]['tablet_text_x'], 'The save handler did not persist tablet text-layer position.' );
mySlider_test_assert( 55 === MySliderPro\SliderPostType::get_slide_content( 42 )[101]['tablet_description_y'], 'The save handler did not persist the separate description layer.' );
mySlider_test_assert( 79 === MySliderPro\SliderPostType::get_slide_content( 42 )[101]['mobile_button_y'], 'The save handler did not persist mobile button-layer position.' );
mySlider_test_assert( 'https://example.test/saved-heading' === MySliderPro\SliderPostType::get_slide_content( 42 )[101]['heading_link_url'], 'The save handler did not persist heading layer links.' );
mySlider_test_assert( 'https://example.test/saved-description' === MySliderPro\SliderPostType::get_slide_content( 42 )[101]['description_link_url'], 'The save handler did not persist description layer links.' );
mySlider_test_assert( 'https://example.test/logo.png' === MySliderPro\SliderPostType::get_slide_content( 42 )[101]['image_layer_url'], 'The save handler did not persist image layer content.' );
mySlider_test_assert( 'https://example.test/logo-link' === MySliderPro\SliderPostType::get_slide_content( 42 )[101]['image_link_url'], 'The save handler did not persist image layer links.' );
mySlider_test_assert( 70 === MySliderPro\SliderPostType::get_slide_content( 42 )[101]['tablet_image_x'] && 20 === MySliderPro\SliderPostType::get_slide_content( 42 )[101]['mobile_image_y'], 'The save handler did not persist image-layer positions.' );
mySlider_test_assert( 'montserrat' === MySliderPro\SliderPostType::get_slide_content( 42 )[101]['font_family'], 'The save handler did not persist typography styling.' );
mySlider_test_assert( 'inter' === MySliderPro\SliderPostType::get_slide_content( 42 )[101]['description_font_family'] && 'inter' === MySliderPro\SliderPostType::get_slide_content( 42 )[101]['button_font_family'], 'The save handler did not persist description and button typography styling.' );
mySlider_test_assert( 'slide-right' === MySliderPro\SliderPostType::get_slide_content( 42 )[101]['image_animation'], 'The save handler did not persist image-layer animation.' );
mySlider_test_assert( 'heading,button,description,image' === MySliderPro\SliderPostType::get_slide_content( 42 )[101]['layer_order'], 'The save handler did not persist draggable layer sorting.' );
$mySlider_test_state['redirect_throws'] = false;

$shortcode = $mySlider_test_state['shortcodes']['myslider'];
$mySlider_test_state['enqueued_styles'] = array();
$mySlider_test_state['enqueued_scripts'] = array();
mySlider_test_assert( '' === call_user_func( $shortcode, array() ), 'A shortcode without a slider ID must render nothing.' );
$slider_output = call_user_func( $shortcode, array( 'id' => '42' ) );
mySlider_test_contains( 'my-slider-pro-slider is-standard-height has-compact-tablet-height has-tall-mobile-height is-right-content has-left-tablet-content has-center-mobile-content has-narrow-tablet-text has-wide-mobile-text has-full-tablet-button has-large-mobile-button hides-phone-arrows', $slider_output, 'The public slider classes are incorrect.' );
mySlider_test_contains( 'has-boxed-width', $slider_output, 'A boxed slider must add the boxed-width shell class.' );
mySlider_test_contains( '--my-slider-pro-max-width:1000px', $slider_output, 'A boxed slider must emit its max width.' );
mySlider_test_assert( 'boxed' === MySliderPro\SliderPostType::get_settings( 42 )['width'], 'The slider width setting must persist.' );
mySlider_test_contains( 'my-slider-pro-shade', $slider_output, 'A slide with an overlay must render the shade element.' );
mySlider_test_contains( 'linear-gradient(to right,rgba(17,34,51,0.40),rgba(68,85,102,0.40))', $slider_output, 'The gradient overlay must emit both colors at the chosen opacity and direction.' );
mySlider_test_assert( 'gradient' === MySliderPro\SliderPostType::get_slide_content( 42 )[101]['overlay_type'], 'The overlay type must persist.' );
mySlider_test_contains( 'data-psp-slider-viewport', $slider_output, 'The native swipe viewport is missing.' );
mySlider_test_contains( 'data-psp-slider-previous', $slider_output, 'Previous navigation is missing.' );
mySlider_test_contains( 'data-psp-slider-dot="0"', $slider_output, 'Slide dots are missing.' );
mySlider_test_contains( 'data-psp-slider-toggle', $slider_output, 'The autoplay pause control is missing.' );
mySlider_test_contains( 'has-compact-tablet-height', $slider_output, 'Tablet slider height must reach public markup.' );
mySlider_test_contains( 'has-left-tablet-content', $slider_output, 'Tablet slider alignment must reach public markup.' );
mySlider_test_contains( 'has-narrow-tablet-text', $slider_output, 'Tablet text width must reach public markup.' );
mySlider_test_contains( 'has-full-tablet-button', $slider_output, 'Tablet button size must reach public markup.' );
mySlider_test_contains( 'Saved heading', $slider_output, 'Slide content is missing from public output.' );
mySlider_test_contains( 'my-slider-pro-content my-slider-pro-layer my-slider-pro-heading-layer', $slider_output, 'The heading must render as an independent positioned layer.' );
mySlider_test_contains( 'my-slider-pro-content my-slider-pro-layer my-slider-pro-description-layer', $slider_output, 'The description must render as an independent positioned layer.' );
mySlider_test_contains( 'my-slider-pro-button-layer my-slider-pro-layer', $slider_output, 'The CTA must render as an independent positioned layer.' );
mySlider_test_contains( 'my-slider-pro-image-layer my-slider-pro-layer', $slider_output, 'The image layer must render as an independent positioned layer.' );
mySlider_test_contains( 'https://example.test/logo.png', $slider_output, 'Image layer URL must reach public output.' );
mySlider_test_contains( 'href="https://example.test/saved-heading"', $slider_output, 'Heading layer links must reach public output.' );
mySlider_test_contains( 'href="https://example.test/saved-description"', $slider_output, 'Description layer links must reach public output.' );
mySlider_test_contains( 'href="https://example.test/logo-link"', $slider_output, 'Image layer links must reach public output.' );
mySlider_test_contains( 'Second heading', MySliderPro\SliderShortcode::render( array( 'id' => 42 ) ), 'Additional heading layers must reach public output.' );
mySlider_test_contains( 'my-slider-pro-shape-layer my-slider-pro-layer', $slider_output, 'A shape layer must render as an independent positioned layer.' );
mySlider_test_contains( '--my-slider-pro-shape-fill:#0f6e56', $slider_output, 'A shape layer must emit its fill color.' );
mySlider_test_contains( '--my-slider-pro-shape-width:420px', $slider_output, 'A shape layer must emit its width.' );
mySlider_test_contains( '--my-slider-pro-shape-height:160px', $slider_output, 'A shape layer must emit its height.' );
mySlider_test_contains( '--my-slider-pro-shape-radius:18px', $slider_output, 'A shape layer must emit its corner radius.' );
mySlider_test_contains( 'my-slider-pro-shape-shade', $slider_output, 'A shape with an overlay must render its shade element.' );
mySlider_test_contains( 'linear-gradient(to right,rgba(16,24,32,0.60),rgba(32,64,96,0.60))', $slider_output, 'A shape gradient overlay must emit both colors at the chosen opacity and direction.' );
mySlider_test_assert( 'shape' === MySliderPro\SliderPostType::get_slide_content( 42 )[101]['extra_layers'][2]['type'], 'The shape layer type must persist.' );
mySlider_test_assert( 18 === MySliderPro\SliderPostType::get_slide_content( 42 )[101]['extra_layers'][2]['radius'] && 160 === MySliderPro\SliderPostType::get_slide_content( 42 )[101]['extra_layers'][2]['height'], 'The shape corner radius and height must persist.' );
mySlider_test_assert( 'gradient' === MySliderPro\SliderPostType::get_slide_content( 42 )[101]['extra_layers'][2]['overlay_type'] && '#0f6e56' === MySliderPro\SliderPostType::get_slide_content( 42 )[101]['extra_layers'][2]['background'], 'The shape overlay and fill must persist.' );
mySlider_test_assert( '1' === MySliderPro\SliderPostType::get_slide_content( 42 )[101]['extra_layers'][2]['ratio_locked'], 'The shape proportion lock must persist.' );
mySlider_test_contains( 'my-slider-pro-layer-link', $slider_output, 'Linked layers need a stable public link class.' );
mySlider_test_contains( '--my-slider-pro-desktop-x:27%', $slider_output, 'Desktop text-layer coordinates must reach public markup.' );
mySlider_test_contains( '--my-slider-pro-tablet-x:43%', $slider_output, 'Tablet text-layer coordinates must reach public markup.' );
mySlider_test_contains( '--my-slider-pro-tablet-x:45%', $slider_output, 'Tablet description-layer coordinates must reach public markup.' );
mySlider_test_contains( '--my-slider-pro-tablet-x:70%', $slider_output, 'Tablet image-layer coordinates must reach public markup.' );
mySlider_test_contains( '--my-slider-pro-tablet-heading-size:48px', $slider_output, 'An unlinked heading must emit its own tablet size.' );
mySlider_test_contains( '--my-slider-pro-mobile-heading-size:36px', $slider_output, 'An unlinked heading must emit its own phone size.' );
mySlider_test_contains( '--my-slider-pro-tablet-button-font-size:18px', $slider_output, 'A linked button size must reuse the desktop value on tablet.' );
mySlider_test_assert( false === strpos( $slider_output, '--my-slider-pro-tablet-button-font-size:30px' ), 'A linked button size must ignore the stored per-device value.' );
mySlider_test_contains( '--my-slider-pro-tablet-x:22%', $slider_output, 'A linked button position must reuse the desktop coordinate on tablet.' );
mySlider_test_assert( false === strpos( $slider_output, '--my-slider-pro-tablet-x:48%' ), 'A linked button position must not emit its stored tablet coordinate.' );
mySlider_test_contains( '--my-slider-pro-text-color:#ccffee', $slider_output, 'Independent description styling must reach public markup.' );
mySlider_test_contains( '--my-slider-pro-mobile-y:79%', $slider_output, 'Mobile button-layer coordinates must reach public markup.' );
mySlider_test_contains( '--my-slider-pro-mobile-y:20%', $slider_output, 'Mobile image-layer coordinates must reach public markup.' );
mySlider_test_contains( '--my-slider-pro-text-color:#ffeecc', $slider_output, 'Text-layer styling must reach public markup.' );
mySlider_test_contains( '--my-slider-pro-font-family:&quot;Montserrat&quot;,sans-serif', $slider_output, 'Exact heading font family must reach public markup.' );
mySlider_test_contains( '--my-slider-pro-font-family:&quot;Inter&quot;,sans-serif', $slider_output, 'Exact description font family must reach public markup.' );
mySlider_test_contains( '--my-slider-pro-button-background:#ffcc00', $slider_output, 'Button styling must reach public markup.' );
mySlider_test_contains( '--my-slider-pro-button-font-family:&quot;Inter&quot;,sans-serif', $slider_output, 'Button font family must reach public markup.' );
mySlider_test_contains( '--my-slider-pro-button-font-size:18px', $slider_output, 'Button text size must reach public markup.' );
mySlider_test_contains( '--my-slider-pro-image-layer-width:240px', $slider_output, 'Image layer width must reach public markup.' );
mySlider_test_contains( '--my-slider-pro-layer-animation:my-slider-pro-slide-right', $slider_output, 'Image layer animation must reach public markup.' );
mySlider_test_contains( '--my-slider-pro-layer-animation-delay:400ms', $slider_output, 'Image layer animation delay must reach public markup.' );
mySlider_test_contains( '--my-slider-pro-layer-z:4', $slider_output, 'The frontmost layer needs the highest public stacking index.' );
mySlider_test_contains( '--my-slider-pro-layer-z:2', $slider_output, 'The middle layer needs its public stacking index.' );
mySlider_test_contains( '--my-slider-pro-layer-z:1', $slider_output, 'The backmost layer needs the lowest public stacking index.' );
mySlider_test_contains( 'target="_blank" rel="noopener noreferrer"', $slider_output, 'New-tab CTA links need safe rel attributes.' );
mySlider_test_contains( 'href="https://example.test/saved-heading" target="_blank" rel="noopener noreferrer"', $slider_output, 'A heading link with the new-tab flag must open in a new tab.' );
mySlider_test_contains( 'href="https://example.test/logo-link" target="_blank" rel="noopener noreferrer"', $slider_output, 'An image link with the new-tab flag must open in a new tab.' );
mySlider_test_assert( false === strpos( $slider_output, 'href="https://example.test/saved-description" target="_blank"' ), 'A link without the new-tab flag must stay in the same tab.' );
mySlider_test_contains( '--my-slider-pro-bg-position:left center', $slider_output, 'The desktop background position must reach public markup.' );
mySlider_test_contains( '--my-slider-pro-bg-position-tablet:right top', $slider_output, 'The tablet background position must reach public markup.' );
mySlider_test_contains( '--my-slider-pro-bg-position-mobile:center bottom', $slider_output, 'The phone background position must reach public markup.' );
mySlider_test_contains( 'srcset=', $slider_output, 'Responsive WordPress image markup is missing.' );
mySlider_test_contains( 'loading="eager"', $slider_output, 'The first slider image should load eagerly.' );
mySlider_test_contains( 'loading="lazy"', $slider_output, 'Later slider images should lazy load.' );
mySlider_test_assert( isset( $mySlider_test_state['enqueued_scripts']['my-slider-pro-frontend'] ), 'Public slider output did not enqueue its controller.' );

$mySlider_test_state['images'][102]['status'] = 'private';
$public_only_output = call_user_func( $shortcode, array( 'id' => '42' ) );
mySlider_test_assert( false === strpos( $public_only_output, 'data-image-id="102"' ), 'A non-public attachment must not render publicly.' );
mySlider_test_contains( 'data-image-id="101"', $public_only_output, 'Public attachments should remain renderable.' );
$mySlider_test_state['images'][102]['status'] = 'publish';

$mySlider_test_state['posts'][42]->post_status = 'draft';
$mySlider_test_state['enqueued_styles'] = array();
$mySlider_test_state['enqueued_scripts'] = array();
mySlider_test_assert( '' === call_user_func( $shortcode, array( 'id' => '42' ) ), 'Unpublished sliders must not render publicly.' );
mySlider_test_assert( empty( $mySlider_test_state['enqueued_scripts'] ), 'Unpublished sliders must not enqueue assets.' );
$mySlider_test_state['posts'][42]->post_status = 'publish';

$mySlider_test_state['is_singular'] = true;
$mySlider_test_state['current_post'] = (object) array( 'post_content' => 'Before [myslider id="42"] after' );
$mySlider_test_state['enqueued_styles'] = array();
$mySlider_test_state['enqueued_scripts'] = array();
MySliderPro\SliderShortcode::maybe_enqueue_for_current_post();
mySlider_test_assert( isset( $mySlider_test_state['enqueued_styles']['my-slider-pro-frontend'] ), 'Shortcode content should enqueue CSS early.' );
mySlider_test_assert( isset( $mySlider_test_state['enqueued_styles']['my-slider-pro-fonts'] ), 'Shortcode content should enqueue slider fonts early.' );
mySlider_test_contains( 'fonts.googleapis.com/css2', $mySlider_test_state['registered_styles']['my-slider-pro-fonts']['src'] ?? '', 'Frontend fonts must load from Google Fonts.' );
mySlider_test_assert( isset( $mySlider_test_state['enqueued_scripts']['my-slider-pro-frontend'] ), 'Shortcode content should enqueue the slider controller early.' );

$admin_js = (string) file_get_contents( dirname( __DIR__ ) . '/assets/admin.js' );
$admin_css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/admin.css' );
$frontend_js = (string) file_get_contents( dirname( __DIR__ ) . '/assets/frontend.js' );
$frontend_css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/frontend.css' );

mySlider_test_assert( false === strpos( $admin_js, 'innerHTML' ), 'The admin editor must not build untrusted innerHTML.' );
mySlider_test_contains( 'openAddImageLayer', $admin_js, 'Adding an image layer must open the media picker before creating the layer so it renders.' );
mySlider_test_contains( "'.psp-delete-layer'", $admin_js, 'The editor must handle deleting the selected layer.' );
mySlider_test_contains( 'psp-layer-strip-delete', $admin_js, 'Each Layers-list row needs its own delete control.' );
mySlider_test_contains( 'function replaceSlideImage', $admin_js, 'Replacing a slide image must re-key its content so nothing is lost.' );
mySlider_test_contains( "prop( 'disabled', isInactive )", $admin_js, 'Hidden inspector style sections must be disabled so stale values cannot silently block saving.' );
mySlider_test_contains( 'data-psp-base-min', $admin_js, 'Inspector size controls must swap native bounds when a repeatable layer is selected.' );
mySlider_test_assert( false === strpos( $frontend_js, 'innerHTML' ), 'The public controller must not build untrusted innerHTML.' );
mySlider_test_contains( "multiple: 'add'", $admin_js, 'The Media Library must support additive selection.' );
mySlider_test_contains( 'psp-device-button', $admin_js, 'The editor must react to responsive device controls.' );
mySlider_test_contains( "'psp-preview-viewport is-' + device", $admin_js, 'Device controls must change the real preview viewport class.' );
mySlider_test_contains( 'refreshPreview', $admin_js, 'Slide edits must refresh the live preview.' );
mySlider_test_contains( "'pointerdown', '.psp-draggable-layer'", $admin_js, 'Preview layers must support direct pointer dragging.' );
mySlider_test_contains( "'pointerdown', '.psp-layer-resize-handle'", $admin_js, 'Preview layers must support direct hold-to-resize handles.' );
mySlider_test_contains( 'contenteditable', $admin_js, 'Text layers must support direct inline editing on the canvas.' );
mySlider_test_contains( '.psp-inline-editable', $admin_js, 'Canvas text edits must be scoped away from layer dragging.' );
mySlider_test_contains( "'keydown', '.psp-draggable-layer'", $admin_js, 'Preview layers must support keyboard position changes.' );
mySlider_test_contains( 'setLayerSize', $admin_js, 'Layer resizing must persist into saved layer size fields.' );
mySlider_test_contains( 'layerSizeConfig', $admin_js, 'Layer resizing must support each base layer type.' );
mySlider_test_contains( 'extraLayerIndex', $admin_js, 'Layer resizing must support repeatable extra layers.' );
mySlider_test_contains( 'psp-layer-editor-overlay', $admin_js, 'The preview must render a visual layer-editing overlay.' );
mySlider_test_contains( 'psp-layer-picker', $admin_js, 'The overlay editor must let users select text and button layers.' );
mySlider_test_contains( "'data-psp-layer': 'heading'", $admin_js, 'The overlay editor must render a standalone Heading layer.' );
mySlider_test_contains( "'data-psp-layer': 'description'", $admin_js, 'The overlay editor must render a standalone Description layer.' );
mySlider_test_contains( "'data-psp-layer': 'image'", $admin_js, 'The overlay editor must render a standalone Image layer.' );
mySlider_test_contains( 'imageLayerUrl', $admin_js, 'The live preview must read image-layer content.' );
mySlider_test_contains( "'input change', '[data-psp-animation-key]'", $admin_js, 'Animation controls must refresh the live preview while their values are edited.' );
mySlider_test_contains( 'syncLayerEditorUI', $admin_js, 'The canvas, inspector, and layer list must stay synchronized.' );
mySlider_test_contains( "'click', '.psp-slide-summary'", $admin_js, 'Clicking a slide filmstrip item should select that slide.' );
mySlider_test_contains( 'ensureLayerSortable', $admin_js, 'Slide layer lists must support drag sorting.' );
mySlider_test_contains( 'activeLayerStripItems', $admin_js, 'Layer rows must be scoped to the selected slide.' );
mySlider_test_contains( 'data-psp-layer-order-key', $admin_js, 'Layer rows must expose stable keys for sortable persistence.' );
mySlider_test_contains( '[name$="[layer_order]"]', $admin_js, 'Layer sorting must update the saved order field.' );
mySlider_test_contains( 'button_background', $admin_js, 'The live preview must apply saved button styling.' );
mySlider_test_contains( 'button_font_size', $admin_js, 'The live preview must apply saved button text size.' );
mySlider_test_contains( 'button_font_family', $admin_js, 'The live preview must apply saved button font family.' );
mySlider_test_contains( 'imageLayerFrame', $admin_js, 'The editor must provide a Media Library picker for image layers.' );
mySlider_test_contains( 'Add image layer', $admin_js, 'The editor must label the image-layer picker clearly.' );
mySlider_test_contains( 'makeExtraLayerRow', $admin_js, 'The editor must be able to add repeatable overlay layers.' );
mySlider_test_contains( 'extraLayers:', $admin_js, 'The live preview must read repeatable overlay layers.' );
mySlider_test_assert( false === strpos( $admin_js, 'data-psp-tab' ), 'The admin script must not keep tab-switching behavior.' );
mySlider_test_contains( '#my-slider-pro-tablet-height', $admin_js, 'The live preview must read tablet slider settings.' );
mySlider_test_contains( 'has-\' + settings.tabletButtonSize + \'-tablet-button', $admin_js, 'The live preview must apply tablet button sizing.' );
mySlider_test_contains( "'input change', '[data-psp-style-key]'", $admin_js, 'Style controls must refresh the live preview while their values are edited.' );
mySlider_test_contains( 'tablet_text_x', $admin_js, 'Tablet previews must edit independent tablet layer coordinates.' );
mySlider_test_contains( 'mobile_text_x', $admin_js, 'Phone previews must edit independent phone layer coordinates.' );
mySlider_test_contains( 'snapLayerCoordinate', $admin_js, 'Layer dragging must provide magnetic anchor snapping.' );
mySlider_test_contains( 'psp-preview-shape-layer', $admin_js, 'The live preview must render a standalone Shape layer.' );
mySlider_test_contains( 'shape_overlay_type', $admin_js, 'The editor must map the Shape overlay type to its layer field.' );
mySlider_test_contains( 'setShapeSize', $admin_js, 'A shape must resize in two dimensions (width and height) from a drag.' );
mySlider_test_contains( 'data-psp-shape-lock', $admin_js, 'The editor must handle the shape proportion-lock control.' );
mySlider_test_contains( 'data-psp-shape-lock', $editor_output, 'The Shape inspector needs a proportion-lock toggle.' );
mySlider_test_contains( '.my-slider-pro-shape-layer', $frontend_css, 'The public stylesheet must style shape layers.' );
mySlider_test_contains( '.psp-preview-shape', $admin_css, 'The editor stylesheet must style the shape preview.' );
mySlider_test_contains( 'var(--my-slider-pro-font-weight', $frontend_css, 'Public text layers must honor a font weight override.' );
mySlider_test_contains( 'var(--my-slider-pro-font-style', $frontend_css, 'Public text layers must honor an italic/normal override.' );
mySlider_test_contains( 'scroll-snap-type: x mandatory', $frontend_css, 'Slides must use native scroll-snap swipe behavior.' );
mySlider_test_contains( 'container-type: inline-size', $frontend_css, 'The public slider must respond to its container.' );
mySlider_test_contains( '.psp-preview-viewport.is-phone', $admin_css, 'The editor needs a framed phone preview.' );
mySlider_test_contains( '.my-slider-pro-admin.psp-editor', $admin_css, 'The slider editor needs a full-width wrapper override.' );
mySlider_test_assert( 1 === preg_match( '/\.my-slider-pro-admin\.psp-editor\s*\{[^}]*max-width:\s*none;/s', $admin_css ), 'The slider editor must use the full available WordPress content width.' );
mySlider_test_assert( 1 === preg_match( '/\.psp-slider-cards\s*\{[^}]*grid-template-columns:/s', $admin_css ), 'The overview must lay sliders out as a responsive card grid.' );
mySlider_test_contains( '.psp-slider-card-media', $admin_css, 'Cards need a media/thumbnail region.' );
mySlider_test_contains( '.psp-slider-card-actions', $admin_css, 'Cards need an actions row.' );
mySlider_test_contains( '.psp-active-slide-fields', $admin_css, 'Active slide fields need a full-width editor row.' );
mySlider_test_assert( 1 === preg_match( '/\.psp-canvas-panel\s*\{[^}]*order:\s*2;[^}]*grid-column:\s*1\s*\/\s*-1;/s', $admin_css ), 'The canvas preview must take the full-width row before the slide order panel.' );
mySlider_test_assert( 1 === preg_match( '/\.psp-slide-order-panel\s*\{[^}]*order:\s*3;[^}]*grid-column:\s*1\s*\/\s*-1;/s', $admin_css ), 'The slide order panel must move below the preview instead of taking preview column space.' );
mySlider_test_contains( '.psp-canvas-layer-tools', $admin_css, 'Canvas add-layer tools need visual editor styling.' );
mySlider_test_assert( 1 === preg_match( '/\.psp-image-picker\s*\{[^}]*display:\s*flex;[^}]*overflow-x:\s*auto;/s', $admin_css ), 'Slide sequence controls should render as a horizontal draggable filmstrip.' );
mySlider_test_contains( '.psp-slide-order-panel .psp-slide-details', $admin_css, 'Slide cards should not show the properties drawer inside each filmstrip item.' );
mySlider_test_assert( 1 === preg_match( '/\.psp-layer-controls\s*\{[^}]*display:\s*none;/s', $admin_css ), 'Old position preset controls should not dominate the visual editor surface.' );
mySlider_test_contains( 'syncActiveSlideDetails', $admin_js, 'The selected slide fields must move into the full-width editor row.' );
mySlider_test_contains( '.psp-draggable-layer.is-dragging', $admin_css, 'Dragged preview layers need visible editing feedback.' );
mySlider_test_contains( '.psp-inline-editable', $admin_css, 'Inline editable canvas text needs text-editing affordance.' );
mySlider_test_contains( '.psp-layer-resize-handle', $admin_css, 'Resizable preview layers need visible resize handles.' );
mySlider_test_contains( '.psp-draggable-layer.is-resizing', $admin_css, 'Resized preview layers need visible editing feedback.' );
mySlider_test_contains( '.psp-layer-safe-area', $admin_css, 'The layer editor needs a visible safe-area overlay.' );
mySlider_test_contains( '.psp-layer-snap-guide', $admin_css, 'The layer editor needs visible magnetic snap guides.' );
mySlider_test_contains( '.psp-layer-animation-section', $admin_css, 'The layer inspector needs dedicated animation control layout.' );
mySlider_test_contains( '.psp-layer-strip-placeholder', $admin_css, 'Layer sorting needs a visible drop placeholder.' );
mySlider_test_contains( '.psp-slider-preview.has-full-tablet-button .psp-slider-preview-button', $admin_css, 'The editor preview needs a full-width tablet CTA option.' );
mySlider_test_contains( '.my-slider-pro-layer', $frontend_css, 'Public text and CTA layers need responsive positioning CSS.' );
mySlider_test_contains( '.my-slider-pro-image-layer img', $frontend_css, 'Public image layers need sizing CSS.' );
mySlider_test_contains( '.my-slider-pro-layer-link', $frontend_css, 'Public linked layers need link styling.' );
mySlider_test_contains( '@keyframes my-slider-pro-slide-right', $frontend_css, 'Public layers need animation keyframes.' );
mySlider_test_contains( '@keyframes psp-layer-slide-right', $admin_css, 'Editor preview layers need animation keyframes.' );
mySlider_test_contains( 'var(--my-slider-pro-tablet-x)', $frontend_css, 'Public tablet layouts must use their independent layer coordinates.' );
mySlider_test_contains( '.my-slider-pro-slider.has-full-tablet-button .my-slider-pro-button', $frontend_css, 'The public slider needs a full-width tablet CTA option.' );
mySlider_test_contains( 'height: 100% !important', $frontend_css, 'Slider images must retain cover height against aggressive theme image rules.' );
mySlider_test_contains( 'var(--my-slider-pro-button-font-size, 16px)', $frontend_css, 'Public buttons must apply their saved text size.' );
mySlider_test_contains( 'var(--my-slider-pro-button-font-family, inherit)', $frontend_css, 'Public buttons must apply their saved font family.' );
mySlider_test_contains( 'var(--my-slider-pro-layer-z, 1)', $frontend_css, 'Public layers must apply their saved stack order.' );
mySlider_test_contains( '.psp-slider-preview.hides-phone-arrows .psp-preview-arrow', $admin_css, 'The editor preview must hide arrows at phone width when requested.' );
mySlider_test_contains( '.my-slider-pro-slider.has-full-mobile-button .my-slider-pro-button', $frontend_css, 'The public slider needs a full-width mobile CTA option.' );
mySlider_test_contains( '.my-slider-pro-slider.hides-phone-arrows .my-slider-pro-arrow', $frontend_css, 'The public slider must hide arrows at phone width when requested.' );
mySlider_test_assert( false === strpos( $admin_css, 'is-grid-layout' ) && false === strpos( $admin_css, 'is-masonry-layout' ), 'Legacy grid and masonry editor modes must be removed.' );
mySlider_test_assert( false === strpos( (string) file_get_contents( dirname( __DIR__ ) . '/README.md' ), 'Fluid, Desktop' ), 'The documented preview controls must match the three device buttons.' );
mySlider_test_contains( 'ResizeObserver', $frontend_js, 'The public slider must retain its active slide on resize.' );
mySlider_test_contains( 'prefers-reduced-motion', $frontend_js, 'Autoplay must respect reduced-motion preferences.' );
mySlider_test_contains( 'focusin', $frontend_js, 'Autoplay must pause while a user interacts with controls.' );

echo 'MY Slider PRO v1.0.5 slider behavior tests passed' . PHP_EOL;
