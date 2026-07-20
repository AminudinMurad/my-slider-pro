<?php
/**
 * Slider data model backed by a private WordPress post type.
 *
 * @package MySliderPro
 */

namespace MySliderPro;

defined( 'ABSPATH' ) || exit;

/**
 * Registers sliders and provides validated access to slider content.
 */
final class SliderPostType {

	public const POST_TYPE = 'psp_slider';

	public const META_IMAGE_IDS = '_my_slider_pro_image_ids';

	public const META_SLIDE_CONTENT = '_my_slider_pro_slide_content';

	public const META_THUMBNAIL_ID = '_my_slider_pro_thumbnail_id';

	public const META_HEIGHT = '_my_slider_pro_height';

	public const META_WIDTH = '_my_slider_pro_width';

	public const META_MAX_WIDTH = '_my_slider_pro_max_width';

	public const META_TABLET_HEIGHT = '_my_slider_pro_tablet_height';

	public const META_MOBILE_HEIGHT = '_my_slider_pro_mobile_height';

	public const META_CONTENT_POSITION = '_my_slider_pro_content_position';

	public const META_TABLET_CONTENT_POSITION = '_my_slider_pro_tablet_content_position';

	public const META_MOBILE_CONTENT_POSITION = '_my_slider_pro_mobile_content_position';

	public const META_TABLET_TEXT_WIDTH = '_my_slider_pro_tablet_text_width';

	public const META_MOBILE_TEXT_WIDTH = '_my_slider_pro_mobile_text_width';

	public const META_TABLET_BUTTON_SIZE = '_my_slider_pro_tablet_button_size';

	public const META_MOBILE_BUTTON_SIZE = '_my_slider_pro_mobile_button_size';

	public const META_ARROWS = '_my_slider_pro_arrows';

	public const META_HIDE_ARROWS_ON_PHONE = '_my_slider_pro_hide_arrows_on_phone';

	public const META_DOTS = '_my_slider_pro_dots';

	public const META_AUTOPLAY = '_my_slider_pro_autoplay';

	public const META_INTERVAL = '_my_slider_pro_interval';

	public const META_LOOP = '_my_slider_pro_loop';

	public const META_PAUSE_ON_HOVER = '_my_slider_pro_pause_on_hover';

	public const MAX_IMAGES = 5;

	public const MAX_LAYERS_PER_TYPE = 2;

	/**
	 * Register the slider post type.
	 *
	 * @return void
	 */
	public static function register(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => esc_html__( 'Sliders', 'my-slider-pro' ),
					'singular_name' => esc_html__( 'Slider', 'my-slider-pro' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_nav_menus'   => false,
				'show_in_admin_bar'   => false,
				'show_in_rest'        => false,
				'query_var'           => false,
				'rewrite'             => false,
				'has_archive'         => false,
				'hierarchical'        => false,
				'map_meta_cap'        => true,
				'capability_type'     => 'post',
				'supports'            => array( 'title', 'author' ),
				'can_export'          => true,
				'delete_with_user'    => false,
			)
		);
	}

	/**
	 * Normalize and validate an ordered image ID list.
	 *
	 * @param mixed $value Submitted or stored image IDs.
	 * @return array<int, int>
	 */
	public static function sanitize_image_ids( $value ): array {
		if ( is_string( $value ) ) {
			$value = preg_split( '/[\s,]+/', $value, -1, PREG_SPLIT_NO_EMPTY );
		}

		if ( ! is_array( $value ) ) {
			return array();
		}

		$image_ids = array();
		$seen      = array();

		foreach ( $value as $candidate ) {
			if ( is_array( $candidate ) || is_object( $candidate ) ) {
				continue;
			}
			if ( is_int( $candidate ) ) {
				if ( $candidate < 1 ) {
					continue;
				}
			} elseif ( ! is_string( $candidate ) || 1 !== preg_match( '/\A[0-9]+\z/D', trim( $candidate ) ) ) {
				continue;
			}

			$attachment_id = absint( $candidate );

			if (
				0 === $attachment_id ||
				isset( $seen[ $attachment_id ] ) ||
				! wp_attachment_is_image( $attachment_id )
			) {
				continue;
			}

			$seen[ $attachment_id ] = true;
			$image_ids[]             = $attachment_id;

			if ( self::MAX_IMAGES === count( $image_ids ) ) {
				break;
			}
		}

		return $image_ids;
	}

	/**
	 * Return the current valid ordered image IDs for a slider.
	 *
	 * @param int $slider_id Slider post ID.
	 * @return array<int, int>
	 */
	public static function get_image_ids( int $slider_id ): array {
		return self::sanitize_image_ids( get_post_meta( $slider_id, self::META_IMAGE_IDS, true ) );
	}

	/**
	 * Read the slider's assigned card thumbnail attachment ID (0 when unset).
	 *
	 * @param int $slider_id Slider post ID.
	 * @return int
	 */
	public static function get_thumbnail_id( int $slider_id ): int {
		return absint( get_post_meta( $slider_id, self::META_THUMBNAIL_ID, true ) );
	}

	/**
	 * Store (or clear) the slider's card thumbnail attachment ID.
	 *
	 * @param int $slider_id     Slider post ID.
	 * @param int $attachment_id Attachment ID, or 0 to clear.
	 * @return void
	 */
	public static function save_thumbnail_id( int $slider_id, int $attachment_id ): void {
		$attachment_id = absint( $attachment_id );

		if ( $attachment_id > 0 && 'attachment' === get_post_type( $attachment_id ) ) {
			update_post_meta( $slider_id, self::META_THUMBNAIL_ID, $attachment_id );
			return;
		}

		delete_post_meta( $slider_id, self::META_THUMBNAIL_ID );
	}

	/**
	 * Normalize display settings.
	 *
	 * @param array<string, mixed> $settings Submitted or stored settings.
	 * @return array{height:string,tablet_height:string,mobile_height:string,content_position:string,tablet_content_position:string,mobile_content_position:string,tablet_text_width:string,mobile_text_width:string,tablet_button_size:string,mobile_button_size:string,arrows:bool,hide_arrows_on_phone:bool,dots:bool,autoplay:bool,interval:int,loop:bool,pause_on_hover:bool}
	 */
	public static function sanitize_settings( array $settings ): array {
		$height = isset( $settings['height'] ) ? (string) $settings['height'] : '';
		$tablet_height = isset( $settings['tablet_height'] ) ? (string) $settings['tablet_height'] : '';
		$mobile_height = isset( $settings['mobile_height'] ) ? (string) $settings['mobile_height'] : '';
		$content_position = isset( $settings['content_position'] ) ? (string) $settings['content_position'] : '';
		$tablet_content_position = isset( $settings['tablet_content_position'] ) ? (string) $settings['tablet_content_position'] : '';
		$mobile_content_position = isset( $settings['mobile_content_position'] ) ? (string) $settings['mobile_content_position'] : '';
		$tablet_text_width = isset( $settings['tablet_text_width'] ) ? (string) $settings['tablet_text_width'] : '';
		$mobile_text_width = isset( $settings['mobile_text_width'] ) ? (string) $settings['mobile_text_width'] : '';
		$tablet_button_size = isset( $settings['tablet_button_size'] ) ? (string) $settings['tablet_button_size'] : '';
		$mobile_button_size = isset( $settings['mobile_button_size'] ) ? (string) $settings['mobile_button_size'] : '';
		$interval = isset( $settings['interval'] ) ? absint( $settings['interval'] ) : 0;
		$width = isset( $settings['width'] ) ? (string) $settings['width'] : '';
		$max_width = isset( $settings['max_width'] ) ? absint( $settings['max_width'] ) : 0;
		$max_width = ( $max_width >= 600 && $max_width <= 1920 ) ? $max_width : 1200;
		$mobile_height = in_array( $mobile_height, array( 'compact', 'standard', 'tall' ), true ) ? $mobile_height : 'standard';
		$mobile_content_position = in_array( $mobile_content_position, array( 'left', 'center', 'right' ), true ) ? $mobile_content_position : 'left';
		$mobile_text_width = in_array( $mobile_text_width, array( 'narrow', 'comfortable', 'wide' ), true ) ? $mobile_text_width : 'comfortable';
		$mobile_button_size = in_array( $mobile_button_size, array( 'standard', 'large', 'full' ), true ) ? $mobile_button_size : 'large';

		return array(
			'height'                  => in_array( $height, array( 'compact', 'standard', 'tall', 'viewport' ), true ) ? $height : 'standard',
			'width'                   => in_array( $width, array( 'full', 'boxed' ), true ) ? $width : 'full',
			'max_width'               => $max_width,
			'tablet_height'           => in_array( $tablet_height, array( 'compact', 'standard', 'tall' ), true ) ? $tablet_height : $mobile_height,
			'mobile_height'           => $mobile_height,
			'content_position'        => in_array( $content_position, array( 'left', 'center', 'right' ), true ) ? $content_position : 'left',
			'tablet_content_position' => in_array( $tablet_content_position, array( 'left', 'center', 'right' ), true ) ? $tablet_content_position : $mobile_content_position,
			'mobile_content_position' => $mobile_content_position,
			'tablet_text_width'       => in_array( $tablet_text_width, array( 'narrow', 'comfortable', 'wide' ), true ) ? $tablet_text_width : $mobile_text_width,
			'mobile_text_width'       => $mobile_text_width,
			'tablet_button_size'      => in_array( $tablet_button_size, array( 'standard', 'large', 'full' ), true ) ? $tablet_button_size : $mobile_button_size,
			'mobile_button_size'      => $mobile_button_size,
			'arrows'                  => ! empty( $settings['arrows'] ),
			'hide_arrows_on_phone'    => ! empty( $settings['hide_arrows_on_phone'] ),
			'dots'                    => ! empty( $settings['dots'] ),
			'autoplay'                => ! empty( $settings['autoplay'] ),
			'interval'                => in_array( $interval, array( 3000, 5000, 7000 ), true ) ? $interval : 5000,
			'loop'                    => ! empty( $settings['loop'] ),
			'pause_on_hover'          => ! empty( $settings['pause_on_hover'] ),
		);
	}

	/**
	 * Get stored display settings with defaults for first-generation sliders.
	 *
	 * @param int $slider_id Slider post ID.
	 * @return array{height:string,tablet_height:string,mobile_height:string,content_position:string,tablet_content_position:string,mobile_content_position:string,tablet_text_width:string,mobile_text_width:string,tablet_button_size:string,mobile_button_size:string,arrows:bool,hide_arrows_on_phone:bool,dots:bool,autoplay:bool,interval:int,loop:bool,pause_on_hover:bool}
	 */
	public static function get_settings( int $slider_id ): array {
		return self::sanitize_settings(
			array(
				'height'                  => get_post_meta( $slider_id, self::META_HEIGHT, true ),
				'width'                   => get_post_meta( $slider_id, self::META_WIDTH, true ),
				'max_width'               => get_post_meta( $slider_id, self::META_MAX_WIDTH, true ),
				'tablet_height'           => get_post_meta( $slider_id, self::META_TABLET_HEIGHT, true ),
				'mobile_height'           => get_post_meta( $slider_id, self::META_MOBILE_HEIGHT, true ),
				'content_position'        => get_post_meta( $slider_id, self::META_CONTENT_POSITION, true ),
				'tablet_content_position' => get_post_meta( $slider_id, self::META_TABLET_CONTENT_POSITION, true ),
				'mobile_content_position' => get_post_meta( $slider_id, self::META_MOBILE_CONTENT_POSITION, true ),
				'tablet_text_width'       => get_post_meta( $slider_id, self::META_TABLET_TEXT_WIDTH, true ),
				'mobile_text_width'       => get_post_meta( $slider_id, self::META_MOBILE_TEXT_WIDTH, true ),
				'tablet_button_size'      => get_post_meta( $slider_id, self::META_TABLET_BUTTON_SIZE, true ),
				'mobile_button_size'      => get_post_meta( $slider_id, self::META_MOBILE_BUTTON_SIZE, true ),
				'arrows'                  => get_post_meta( $slider_id, self::META_ARROWS, true ),
				'hide_arrows_on_phone'    => get_post_meta( $slider_id, self::META_HIDE_ARROWS_ON_PHONE, true ),
				'dots'                    => get_post_meta( $slider_id, self::META_DOTS, true ),
				'autoplay'                => get_post_meta( $slider_id, self::META_AUTOPLAY, true ),
				'interval'                => get_post_meta( $slider_id, self::META_INTERVAL, true ),
				'loop'                    => get_post_meta( $slider_id, self::META_LOOP, true ),
				'pause_on_hover'          => get_post_meta( $slider_id, self::META_PAUSE_ON_HOVER, true ),
			)
		);
	}

	/**
	 * Return per-slide content in the same order as the current image IDs.
	 *
	 * @param int                $slider_id Slider post ID.
	 * @param array<int, int>|null $image_ids Allowed attachment IDs.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_slide_content( int $slider_id, ?array $image_ids = null ): array {
		if ( null === $image_ids ) {
			$image_ids = self::get_image_ids( $slider_id );
		}

		return self::sanitize_slide_content(
			get_post_meta( $slider_id, self::META_SLIDE_CONTENT, true ),
			$image_ids
		);
	}

	/**
	 * Normalize slide-specific content and discard data for unselected attachments.
	 *
	 * @param mixed            $content Raw slide data keyed by attachment ID.
	 * @param array<int, int> $image_ids Allowed attachment IDs.
	 * @return array<int, array<string, mixed>>
	 */
	public static function sanitize_slide_content( $content, array $image_ids ): array {
		$content = is_array( $content ) ? $content : array();
		$slides  = array();

		foreach ( self::sanitize_image_ids( $image_ids ) as $attachment_id ) {
			$raw = isset( $content[ $attachment_id ] ) && is_array( $content[ $attachment_id ] )
				? $content[ $attachment_id ]
				: array();
			// Background crop anchor, per device. Tablet inherits desktop and phone
			// inherits tablet so a single value cascades cleanly.
			$background_position        = self::sanitize_bg_position( $raw['background_position'] ?? null, 'center' );
			$tablet_background_position = self::sanitize_bg_position( $raw['tablet_background_position'] ?? null, $background_position );
			$mobile_background_position = self::sanitize_bg_position( $raw['mobile_background_position'] ?? null, $tablet_background_position );
			$desktop_text_x  = self::sanitize_layer_coordinate( $raw['text_x'] ?? null, 5 );
			$desktop_text_y  = self::sanitize_layer_coordinate( $raw['text_y'] ?? null, 50 );
			$mobile_text_x   = self::sanitize_layer_coordinate( $raw['mobile_text_x'] ?? null, 50 );
			$mobile_text_y   = self::sanitize_layer_coordinate( $raw['mobile_text_y'] ?? null, 50 );
			$tablet_text_x   = self::sanitize_layer_coordinate( $raw['tablet_text_x'] ?? null, $mobile_text_x );
			$tablet_text_y   = self::sanitize_layer_coordinate( $raw['tablet_text_y'] ?? null, $mobile_text_y );
			$desktop_description_x = self::sanitize_layer_coordinate( $raw['description_x'] ?? null, $desktop_text_x );
			$desktop_description_y = self::sanitize_layer_coordinate( $raw['description_y'] ?? null, min( 95, $desktop_text_y + 12 ) );
			$tablet_description_x = self::sanitize_layer_coordinate( $raw['tablet_description_x'] ?? null, $tablet_text_x );
			$tablet_description_y = self::sanitize_layer_coordinate( $raw['tablet_description_y'] ?? null, min( 95, $tablet_text_y + 12 ) );
			$mobile_description_x = self::sanitize_layer_coordinate( $raw['mobile_description_x'] ?? null, $mobile_text_x );
			$mobile_description_y = self::sanitize_layer_coordinate( $raw['mobile_description_y'] ?? null, min( 95, $mobile_text_y + 12 ) );
			$mobile_button_x = self::sanitize_layer_coordinate( $raw['mobile_button_x'] ?? null, 50 );
			$mobile_button_y = self::sanitize_layer_coordinate( $raw['mobile_button_y'] ?? null, 82 );
			$mobile_image_x = self::sanitize_layer_coordinate( $raw['mobile_image_x'] ?? null, 50 );
			$mobile_image_y = self::sanitize_layer_coordinate( $raw['mobile_image_y'] ?? null, 50 );
			$text_color      = self::sanitize_style_color( $raw['text_color'] ?? null, '#ffffff' );
			$text_align      = in_array( $raw['text_align'] ?? '', array( 'left', 'center', 'right' ), true ) ? $raw['text_align'] : 'left';
			$font_family     = self::sanitize_font_family( $raw['font_family'] ?? 'montserrat' );
			$layer_order     = self::sanitize_layer_order( $raw['layer_order'] ?? '' );

			// Per-device layer sizes. Tablet inherits desktop and phone inherits
			// tablet so a legacy single value cascades cleanly when unlinked.
			$heading_size            = self::sanitize_style_number( $raw['heading_size'] ?? null, 64, 24, 96 );
			$tablet_heading_size     = self::sanitize_style_number( $raw['tablet_heading_size'] ?? null, $heading_size, 24, 96 );
			$mobile_heading_size     = self::sanitize_style_number( $raw['mobile_heading_size'] ?? null, $tablet_heading_size, 24, 96 );
			$description_size        = self::sanitize_style_number( $raw['description_size'] ?? null, 20, 12, 36 );
			$tablet_description_size = self::sanitize_style_number( $raw['tablet_description_size'] ?? null, $description_size, 12, 36 );
			$mobile_description_size = self::sanitize_style_number( $raw['mobile_description_size'] ?? null, $tablet_description_size, 12, 36 );
			$button_font_size        = self::sanitize_style_number( $raw['button_font_size'] ?? null, 16, 12, 36 );
			$tablet_button_font_size = self::sanitize_style_number( $raw['tablet_button_font_size'] ?? null, $button_font_size, 12, 36 );
			$mobile_button_font_size = self::sanitize_style_number( $raw['mobile_button_font_size'] ?? null, $tablet_button_font_size, 12, 36 );
			$image_width             = self::sanitize_style_number( $raw['image_width'] ?? null, 220, 40, 800 );
			$tablet_image_width      = self::sanitize_style_number( $raw['tablet_image_width'] ?? null, $image_width, 40, 800 );
			$mobile_image_width      = self::sanitize_style_number( $raw['mobile_image_width'] ?? null, $tablet_image_width, 40, 800 );

			// The base Heading/Text/Button/Image count toward the 2-per-type cap,
			// so seed the extra-layer counter with any base layer that has content.
			$base_counts = array(
				'heading'     => '' !== self::limit_text( $raw['title'] ?? '', 120, false ) ? 1 : 0,
				'description' => '' !== self::limit_text( $raw['description'] ?? '', 280, true ) ? 1 : 0,
				'button'      => '' !== self::limit_text( $raw['button_label'] ?? '', 80, false ) ? 1 : 0,
				'image'       => '' !== self::sanitize_url( $raw['image_layer_url'] ?? '' ) ? 1 : 0,
			);

			$slides[ $attachment_id ] = array(
				'title'         => self::limit_text( $raw['title'] ?? '', 120, false ),
				'description'   => self::limit_text( $raw['description'] ?? '', 280, true ),
				'heading_link_url' => self::sanitize_url( $raw['heading_link_url'] ?? '' ),
				'heading_target' => ! empty( $raw['heading_target'] ),
				'description_link_url' => self::sanitize_url( $raw['description_link_url'] ?? '' ),
				'description_target' => ! empty( $raw['description_target'] ),
				'button_label'  => self::limit_text( $raw['button_label'] ?? '', 80, false ),
				'button_url'    => self::sanitize_url( $raw['button_url'] ?? '' ),
				'button_target' => ! empty( $raw['button_target'] ),
				'image_layer_url' => self::sanitize_url( $raw['image_layer_url'] ?? '' ),
				'image_layer_alt' => self::limit_text( $raw['image_layer_alt'] ?? '', 120, false ),
				'image_link_url' => self::sanitize_url( $raw['image_link_url'] ?? '' ),
				'image_target'   => ! empty( $raw['image_target'] ),
				'text_x'              => $desktop_text_x,
				'text_y'              => $desktop_text_y,
				'description_x'       => $desktop_description_x,
				'description_y'       => $desktop_description_y,
				'button_x'            => self::sanitize_layer_coordinate( $raw['button_x'] ?? null, 5 ),
				'button_y'            => self::sanitize_layer_coordinate( $raw['button_y'] ?? null, 82 ),
				'image_x'             => self::sanitize_layer_coordinate( $raw['image_x'] ?? null, 50 ),
				'image_y'             => self::sanitize_layer_coordinate( $raw['image_y'] ?? null, 50 ),
				'tablet_text_x'       => $tablet_text_x,
				'tablet_text_y'       => $tablet_text_y,
				'tablet_description_x' => $tablet_description_x,
				'tablet_description_y' => $tablet_description_y,
				'tablet_button_x'     => self::sanitize_layer_coordinate( $raw['tablet_button_x'] ?? null, $mobile_button_x ),
				'tablet_button_y'     => self::sanitize_layer_coordinate( $raw['tablet_button_y'] ?? null, $mobile_button_y ),
				'tablet_image_x'      => self::sanitize_layer_coordinate( $raw['tablet_image_x'] ?? null, $mobile_image_x ),
				'tablet_image_y'      => self::sanitize_layer_coordinate( $raw['tablet_image_y'] ?? null, $mobile_image_y ),
				'mobile_text_x'       => $mobile_text_x,
				'mobile_text_y'       => $mobile_text_y,
				'mobile_description_x' => $mobile_description_x,
				'mobile_description_y' => $mobile_description_y,
				'mobile_button_x'     => $mobile_button_x,
				'mobile_button_y'     => $mobile_button_y,
				'mobile_image_x'      => $mobile_image_x,
				'mobile_image_y'      => $mobile_image_y,
				'text_color'          => $text_color,
				'heading_size'        => $heading_size,
				'tablet_heading_size' => $tablet_heading_size,
				'mobile_heading_size' => $mobile_heading_size,
				'heading_opacity'     => self::sanitize_style_number( $raw['heading_opacity'] ?? null, 100, 10, 100 ),
				'description_color'   => self::sanitize_style_color( $raw['description_color'] ?? null, $text_color ),
				'description_size'    => $description_size,
				'tablet_description_size' => $tablet_description_size,
				'mobile_description_size' => $mobile_description_size,
				'description_opacity' => self::sanitize_style_number( $raw['description_opacity'] ?? null, 100, 10, 100 ),
				'description_align'   => in_array( $raw['description_align'] ?? '', array( 'left', 'center', 'right' ), true ) ? $raw['description_align'] : $text_align,
				'description_font_family' => self::sanitize_font_family( $raw['description_font_family'] ?? 'montserrat' ),
				'heading_font_style'  => self::sanitize_font_style( $raw['heading_font_style'] ?? 'default' ),
				'description_font_style' => self::sanitize_font_style( $raw['description_font_style'] ?? 'default' ),
				'text_align'          => $text_align,
				'font_family'         => $font_family,
				'button_text_color'   => self::sanitize_style_color( $raw['button_text_color'] ?? null, '#172033' ),
				'button_background'   => self::sanitize_style_color( $raw['button_background'] ?? null, '#ffffff' ),
				'button_font_family'  => self::sanitize_font_family( $raw['button_font_family'] ?? 'montserrat' ),
				'button_font_style'   => self::sanitize_font_style( $raw['button_font_style'] ?? 'default' ),
				'button_font_size'    => $button_font_size,
				'tablet_button_font_size' => $tablet_button_font_size,
				'mobile_button_font_size' => $mobile_button_font_size,
				'button_opacity'      => self::sanitize_style_number( $raw['button_opacity'] ?? null, 100, 10, 100 ),
				'button_radius'       => self::sanitize_style_number( $raw['button_radius'] ?? null, 4, 0, 50 ),
				'button_padding_x'    => self::sanitize_style_number( $raw['button_padding_x'] ?? null, 20, 8, 48 ),
				'button_padding_y'    => self::sanitize_style_number( $raw['button_padding_y'] ?? null, 12, 6, 30 ),
				'image_width'         => $image_width,
				'tablet_image_width'  => $tablet_image_width,
				'mobile_image_width'  => $mobile_image_width,
				'image_opacity'       => self::sanitize_style_number( $raw['image_opacity'] ?? null, 100, 10, 100 ),
				'heading_size_linked'     => self::sanitize_link_flag( $raw, 'heading_size_linked', true ),
				'description_size_linked' => self::sanitize_link_flag( $raw, 'description_size_linked', true ),
				'button_size_linked'      => self::sanitize_link_flag( $raw, 'button_size_linked', true ),
				'image_size_linked'       => self::sanitize_link_flag( $raw, 'image_size_linked', true ),
				'heading_pos_linked'      => self::sanitize_link_flag( $raw, 'heading_pos_linked', false ),
				'description_pos_linked'  => self::sanitize_link_flag( $raw, 'description_pos_linked', false ),
				'button_pos_linked'       => self::sanitize_link_flag( $raw, 'button_pos_linked', false ),
				'image_pos_linked'        => self::sanitize_link_flag( $raw, 'image_pos_linked', false ),
				'background_fill'     => in_array( $raw['background_fill'] ?? '', array( 'cover', 'fill', 'fit', 'center' ), true ) ? $raw['background_fill'] : 'cover',
				'background_position' => $background_position,
				'tablet_background_position' => $tablet_background_position,
				'mobile_background_position' => $mobile_background_position,
				'overlay_type'        => in_array( $raw['overlay_type'] ?? '', array( 'none', 'solid', 'gradient' ), true ) ? $raw['overlay_type'] : 'none',
				'overlay_color'       => self::sanitize_hex_color( $raw['overlay_color'] ?? '', '#08101f' ),
				'overlay_color2'      => self::sanitize_hex_color( $raw['overlay_color2'] ?? '', '#000000' ),
				'overlay_opacity'     => self::sanitize_style_number( $raw['overlay_opacity'] ?? null, 50, 0, 100 ),
				'overlay_direction'   => in_array( $raw['overlay_direction'] ?? '', array( 'to bottom', 'to top', 'to right', 'to left', 'to bottom right', 'to bottom left' ), true ) ? $raw['overlay_direction'] : 'to bottom',
				'heading_animation'   => self::sanitize_animation_type( $raw['heading_animation'] ?? 'fade' ),
				'description_animation' => self::sanitize_animation_type( $raw['description_animation'] ?? 'fade' ),
				'button_animation'    => self::sanitize_animation_type( $raw['button_animation'] ?? 'fade' ),
				'image_animation'     => self::sanitize_animation_type( $raw['image_animation'] ?? 'fade' ),
				'heading_animation_delay' => self::sanitize_style_number( $raw['heading_animation_delay'] ?? null, 0, 0, 5000 ),
				'description_animation_delay' => self::sanitize_style_number( $raw['description_animation_delay'] ?? null, 120, 0, 5000 ),
				'button_animation_delay' => self::sanitize_style_number( $raw['button_animation_delay'] ?? null, 240, 0, 5000 ),
				'image_animation_delay' => self::sanitize_style_number( $raw['image_animation_delay'] ?? null, 0, 0, 5000 ),
				'heading_animation_duration' => self::sanitize_style_number( $raw['heading_animation_duration'] ?? null, 600, 100, 5000 ),
				'description_animation_duration' => self::sanitize_style_number( $raw['description_animation_duration'] ?? null, 600, 100, 5000 ),
				'button_animation_duration' => self::sanitize_style_number( $raw['button_animation_duration'] ?? null, 600, 100, 5000 ),
				'image_animation_duration' => self::sanitize_style_number( $raw['image_animation_duration'] ?? null, 600, 100, 5000 ),
				'heading_animation_easing' => self::sanitize_animation_easing( $raw['heading_animation_easing'] ?? 'ease-out' ),
				'description_animation_easing' => self::sanitize_animation_easing( $raw['description_animation_easing'] ?? 'ease-out' ),
				'button_animation_easing' => self::sanitize_animation_easing( $raw['button_animation_easing'] ?? 'ease-out' ),
				'image_animation_easing' => self::sanitize_animation_easing( $raw['image_animation_easing'] ?? 'ease-out' ),
				'layer_order'         => $layer_order,
				'extra_layers'        => self::sanitize_extra_layers( $raw['extra_layers'] ?? array(), $base_counts ),
			);
		}

		return $slides;
	}

	/**
	 * Normalize additional repeatable overlay layers.
	 *
	 * @param mixed $value Raw extra layer rows.
	 * @return array<int, array<string, mixed>>
	 */
	private static function sanitize_extra_layers( $value, array $base_counts = array() ): array {
		$value  = is_array( $value ) ? $value : array();
		$layers = array();
		$type_counts = array(
			'heading'     => (int) ( $base_counts['heading'] ?? 0 ),
			'description' => (int) ( $base_counts['description'] ?? 0 ),
			'button'      => (int) ( $base_counts['button'] ?? 0 ),
			'image'       => (int) ( $base_counts['image'] ?? 0 ),
		);

		foreach ( $value as $raw ) {
			if ( ! is_array( $raw ) ) {
				continue;
			}

			$type = isset( $raw['type'] ) && is_scalar( $raw['type'] ) ? sanitize_key( (string) $raw['type'] ) : '';
			if ( ! in_array( $type, array( 'heading', 'description', 'button', 'image' ), true ) ) {
				continue;
			}

			// Cap each type (base + extras) at MAX_LAYERS_PER_TYPE.
			if ( $type_counts[ $type ] >= self::MAX_LAYERS_PER_TYPE ) {
				continue;
			}
			$type_counts[ $type ]++;

			$size_max     = 'heading' === $type ? 96 : 48;
			$size         = self::sanitize_style_number( $raw['size'] ?? null, 'heading' === $type ? 64 : 20, 12, $size_max );
			$tablet_size  = self::sanitize_style_number( $raw['tablet_size'] ?? null, $size, 12, $size_max );
			$mobile_size  = self::sanitize_style_number( $raw['mobile_size'] ?? null, $tablet_size, 12, $size_max );
			$width        = self::sanitize_style_number( $raw['width'] ?? null, 220, 40, 800 );
			$tablet_width = self::sanitize_style_number( $raw['tablet_width'] ?? null, $width, 40, 800 );
			$mobile_width = self::sanitize_style_number( $raw['mobile_width'] ?? null, $tablet_width, 40, 800 );

			$layers[] = array(
				'type'               => $type,
				'text'               => self::limit_text( $raw['text'] ?? '', 'description' === $type ? 280 : 120, true ),
				'url'                => self::sanitize_url( $raw['url'] ?? '' ),
				'link_url'           => self::sanitize_url( $raw['link_url'] ?? '' ),
				'target'             => ! empty( $raw['target'] ),
				'alt'                => self::limit_text( $raw['alt'] ?? '', 120, false ),
				'desktop_x'          => self::sanitize_layer_coordinate( $raw['desktop_x'] ?? null, 50 ),
				'desktop_y'          => self::sanitize_layer_coordinate( $raw['desktop_y'] ?? null, 50 ),
				'tablet_x'           => self::sanitize_layer_coordinate( $raw['tablet_x'] ?? null, 50 ),
				'tablet_y'           => self::sanitize_layer_coordinate( $raw['tablet_y'] ?? null, 50 ),
				'mobile_x'           => self::sanitize_layer_coordinate( $raw['mobile_x'] ?? null, 50 ),
				'mobile_y'           => self::sanitize_layer_coordinate( $raw['mobile_y'] ?? null, 50 ),
				'color'              => self::sanitize_style_color( $raw['color'] ?? null, 'button' === $type ? '#172033' : '#ffffff' ),
				'background'         => self::sanitize_style_color( $raw['background'] ?? null, '#ffffff' ),
				'font_family'        => self::sanitize_font_family( $raw['font_family'] ?? 'montserrat' ),
				'font_style'         => self::sanitize_font_style( $raw['font_style'] ?? 'default' ),
				'size'               => $size,
				'tablet_size'        => $tablet_size,
				'mobile_size'        => $mobile_size,
				'opacity'            => self::sanitize_style_number( $raw['opacity'] ?? null, 100, 10, 100 ),
				'align'              => in_array( $raw['align'] ?? '', array( 'left', 'center', 'right' ), true ) ? $raw['align'] : 'left',
				'width'              => $width,
				'tablet_width'       => $tablet_width,
				'mobile_width'       => $mobile_width,
				'size_linked'        => self::sanitize_link_flag( $raw, 'size_linked', true ),
				'pos_linked'         => self::sanitize_link_flag( $raw, 'pos_linked', false ),
				'animation'          => self::sanitize_animation_type( $raw['animation'] ?? 'fade' ),
				'animation_delay'    => self::sanitize_style_number( $raw['animation_delay'] ?? null, 0, 0, 5000 ),
				'animation_duration' => self::sanitize_style_number( $raw['animation_duration'] ?? null, 600, 100, 5000 ),
				'animation_easing'   => self::sanitize_animation_easing( $raw['animation_easing'] ?? 'ease-out' ),
			);
		}

		return $layers;
	}

	/**
	 * Save ordered images, display settings, and per-slide content.
	 *
	 * @param int                 $slider_id Slider post ID.
	 * @param mixed               $image_ids Submitted image IDs.
	 * @param array<string,mixed> $settings Display settings.
	 * @param mixed               $slide_content Per-slide content keyed by attachment ID.
	 * @return void
	 */
	public static function save_meta( int $slider_id, $image_ids, array $settings, $slide_content = array() ): void {
		$image_ids     = self::sanitize_image_ids( $image_ids );
		$settings      = self::sanitize_settings( $settings );
		$slide_content = self::sanitize_slide_content( $slide_content, $image_ids );

		update_post_meta( $slider_id, self::META_IMAGE_IDS, $image_ids );
		update_post_meta( $slider_id, self::META_SLIDE_CONTENT, $slide_content );
		update_post_meta( $slider_id, self::META_HEIGHT, $settings['height'] );
		update_post_meta( $slider_id, self::META_WIDTH, $settings['width'] );
		update_post_meta( $slider_id, self::META_MAX_WIDTH, $settings['max_width'] );
		update_post_meta( $slider_id, self::META_TABLET_HEIGHT, $settings['tablet_height'] );
		update_post_meta( $slider_id, self::META_MOBILE_HEIGHT, $settings['mobile_height'] );
		update_post_meta( $slider_id, self::META_CONTENT_POSITION, $settings['content_position'] );
		update_post_meta( $slider_id, self::META_TABLET_CONTENT_POSITION, $settings['tablet_content_position'] );
		update_post_meta( $slider_id, self::META_MOBILE_CONTENT_POSITION, $settings['mobile_content_position'] );
		update_post_meta( $slider_id, self::META_TABLET_TEXT_WIDTH, $settings['tablet_text_width'] );
		update_post_meta( $slider_id, self::META_MOBILE_TEXT_WIDTH, $settings['mobile_text_width'] );
		update_post_meta( $slider_id, self::META_TABLET_BUTTON_SIZE, $settings['tablet_button_size'] );
		update_post_meta( $slider_id, self::META_MOBILE_BUTTON_SIZE, $settings['mobile_button_size'] );
		update_post_meta( $slider_id, self::META_ARROWS, $settings['arrows'] ? '1' : '0' );
		update_post_meta( $slider_id, self::META_HIDE_ARROWS_ON_PHONE, $settings['hide_arrows_on_phone'] ? '1' : '0' );
		update_post_meta( $slider_id, self::META_DOTS, $settings['dots'] ? '1' : '0' );
		update_post_meta( $slider_id, self::META_AUTOPLAY, $settings['autoplay'] ? '1' : '0' );
		update_post_meta( $slider_id, self::META_INTERVAL, (string) $settings['interval'] );
		update_post_meta( $slider_id, self::META_LOOP, $settings['loop'] ? '1' : '0' );
		update_post_meta( $slider_id, self::META_PAUSE_ON_HOVER, $settings['pause_on_hover'] ? '1' : '0' );
	}

	/**
	 * Sanitize a bounded line or paragraph of slide copy.
	 *
	 * @param mixed $value Raw text.
	 * @param int   $limit Maximum character count.
	 * @param bool  $multiline Whether line breaks are allowed.
	 * @return string
	 */
	private static function limit_text( $value, int $limit, bool $multiline ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$text = $multiline ? sanitize_textarea_field( (string) $value ) : sanitize_text_field( (string) $value );

		if ( function_exists( 'mb_substr' ) ) {
			return (string) mb_substr( $text, 0, $limit );
		}

		return substr( $text, 0, $limit );
	}

	/**
	 * Sanitize a CTA URL using WordPress protocol allow-listing.
	 *
	 * @param mixed $value Raw URL.
	 * @return string
	 */
	private static function sanitize_url( $value ): string {
		return is_scalar( $value ) ? esc_url_raw( trim( (string) $value ) ) : '';
	}

	/**
	 * Sanitize a layer anchor coordinate to a safe percentage.
	 *
	 * @param mixed $value Raw coordinate.
	 * @param int   $fallback Default coordinate.
	 * @return int
	 */
	private static function sanitize_layer_coordinate( $value, int $fallback ): int {
		if ( ! is_scalar( $value ) || ! is_numeric( (string) $value ) ) {
			return $fallback;
		}

		return max( 5, min( 95, (int) round( (float) $value ) ) );
	}

	/**
	 * Normalize the front-to-back layer stack, preserving every supported layer once.
	 *
	 * @param mixed $value Comma-separated layer names or an array of names.
	 * @return string
	 */
	private static function sanitize_layer_order( $value ): string {
		$base    = array( 'button', 'heading', 'description', 'image' );
		$values  = is_array( $value ) ? $value : explode( ',', is_scalar( $value ) ? (string) $value : '' );
		$order   = array();

		foreach ( $values as $layer ) {
			$layer = is_scalar( $layer ) ? strtolower( trim( (string) $layer ) ) : '';
			// Base layers and repeatable overlay layers (extra-0, extra-1, …)
			// both participate in the single front-to-back order.
			$is_valid = in_array( $layer, $base, true ) || 1 === preg_match( '/\Aextra-[0-9]+\z/', $layer );
			if ( $is_valid && ! in_array( $layer, $order, true ) ) {
				$order[] = $layer;
			}
		}

		foreach ( $base as $layer ) {
			if ( ! in_array( $layer, $order, true ) ) {
				$order[] = $layer;
			}
		}

		return implode( ',', $order );
	}

	/**
	 * Sanitize a curated dependency-free font stack key.
	 *
	 * @param mixed $value Raw font family key.
	 * @return string
	 */
	private static function sanitize_font_family( $value ): string {
		$font = is_scalar( $value ) ? sanitize_key( (string) $value ) : '';

		return in_array( $font, array( 'theme', 'poppins', 'montserrat', 'inter' ), true ) ? $font : 'montserrat';
	}

	/**
	 * Sanitize a layer font style (weight/italic) preset.
	 *
	 * @param mixed $value Raw font style.
	 * @return string
	 */
	private static function sanitize_font_style( $value ): string {
		$style = is_scalar( $value ) ? sanitize_key( (string) $value ) : '';

		return in_array( $style, array( 'default', 'normal', 'bold', 'italic', 'bold-italic' ), true ) ? $style : 'default';
	}

	/**
	 * Sanitize a layer animation type.
	 *
	 * @param mixed $value Raw animation key.
	 * @return string
	 */
	private static function sanitize_animation_type( $value ): string {
		$animation = is_scalar( $value ) ? sanitize_key( (string) $value ) : '';

		return in_array( $animation, array( 'none', 'fade', 'slide-up', 'slide-down', 'slide-left', 'slide-right', 'zoom' ), true ) ? $animation : 'fade';
	}

	/**
	 * Sanitize a layer animation easing preset.
	 *
	 * @param mixed $value Raw easing key.
	 * @return string
	 */
	private static function sanitize_animation_easing( $value ): string {
		$easing = is_scalar( $value ) ? sanitize_key( (string) $value ) : '';

		return in_array( $easing, array( 'linear', 'ease', 'ease-in', 'ease-out', 'ease-in-out' ), true ) ? $easing : 'ease-out';
	}

	/**
	 * Sanitize a layer style color.
	 *
	 * @param mixed  $value Raw color.
	 * @param string $fallback Safe default color.
	 * @return string
	 */
	private static function sanitize_style_color( $value, string $fallback ): string {
		if ( ! is_scalar( $value ) ) {
			return $fallback;
		}

		$color = strtolower( trim( (string) $value ) );

		return preg_match( '/^#[0-9a-f]{6}$/', $color ) ? $color : $fallback;
	}

	/**
	 * Sanitize a hex color, falling back to a supplied default when invalid.
	 *
	 * @param mixed  $value   Raw color.
	 * @param string $default Fallback hex when the value is not a valid #rrggbb.
	 * @return string
	 */
	private static function sanitize_hex_color( $value, string $default ): string {
		if ( ! is_scalar( $value ) ) {
			return $default;
		}

		$color = strtolower( trim( (string) $value ) );

		return preg_match( '/^#[0-9a-f]{6}$/', $color ) ? $color : $default;
	}

	/**
	 * Sanitize a nine-point background position value.
	 *
	 * @param mixed  $value   Raw position.
	 * @param string $default Fallback when invalid.
	 * @return string
	 */
	private static function sanitize_bg_position( $value, string $default ): string {
		$allowed = array( 'top_left', 'top_center', 'top_right', 'center_left', 'center', 'center_right', 'bottom_left', 'bottom_center', 'bottom_right' );

		return is_scalar( $value ) && in_array( (string) $value, $allowed, true ) ? (string) $value : $default;
	}

	/**
	 * Sanitize a responsive link flag ('1' linked, '' independent).
	 *
	 * A missing key means the value predates responsive linking, so the layer
	 * keeps its historical default: sizes were shared (linked), positions were
	 * independent (unlinked).
	 *
	 * @param array<string, mixed> $raw     Raw slide row.
	 * @param string               $key     Flag key.
	 * @param bool                 $default Default when the key is absent.
	 * @return string
	 */
	private static function sanitize_link_flag( array $raw, string $key, bool $default ): string {
		if ( ! array_key_exists( $key, $raw ) ) {
			return $default ? '1' : '';
		}

		return ! empty( $raw[ $key ] ) ? '1' : '';
	}

	/**
	 * Sanitize a bounded integer style value.
	 *
	 * @param mixed $value Raw numeric value.
	 * @param int   $fallback Safe default.
	 * @param int   $minimum Minimum accepted value.
	 * @param int   $maximum Maximum accepted value.
	 * @return int
	 */
	private static function sanitize_style_number( $value, int $fallback, int $minimum, int $maximum ): int {
		if ( ! is_scalar( $value ) || ! is_numeric( (string) $value ) ) {
			return $fallback;
		}

		return max( $minimum, min( $maximum, (int) round( (float) $value ) ) );
	}
}
