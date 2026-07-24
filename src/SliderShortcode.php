<?php
/**
 * Responsive slider shortcode and frontend assets.
 *
 * @package MySliderPro
 */

namespace MySliderPro;

defined( 'ABSPATH' ) || exit;

/**
 * Renders published sliders with progressive enhancement.
 */
final class SliderShortcode {

	public const TAG = 'myslider';

	private const STYLE_HANDLE = 'my-slider-pro-frontend';

	private const SCRIPT_HANDLE = 'my-slider-pro-frontend';

	private const FONT_HANDLE = 'my-slider-pro-fonts';

	/**
	 * Register shortcode and frontend asset hooks.
	 *
	 * @return void
	 */
	public static function boot(): void {
		add_shortcode( self::TAG, array( self::class, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'register_assets' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'maybe_enqueue_for_current_post' ), 20 );
	}

	/**
	 * Return the first renderable slide image URL for a slider, or ''.
	 *
	 * Used by the resource-preloading setting to hint the LCP image.
	 *
	 * @param int $slider_id Slider post ID.
	 * @return string
	 */
	public static function first_image_url( int $slider_id ): string {
		$slider = get_post( $slider_id );

		if (
			! $slider ||
			SliderPostType::POST_TYPE !== $slider->post_type ||
			'publish' !== $slider->post_status
		) {
			return '';
		}

		$items = self::get_renderable_items( $slider_id );

		if ( empty( $items ) ) {
			return '';
		}

		return (string) wp_get_attachment_image_url( $items[0]['attachment_id'], 'large' );
	}

	/**
	 * Register first-party frontend assets without loading them globally.
	 *
	 * @return void
	 */
	public static function register_assets(): void {
		$font_mode  = Settings::font_mode();
		$style_deps = array();

		if ( 'disabled' !== $font_mode ) {
			$font_src = 'local' === $font_mode ? Settings::local_font_url() : '';

			// Fall back to the remote stylesheet if local hosting is not ready.
			if ( '' === $font_src ) {
				$font_src = Settings::remote_font_url();
			}

			wp_register_style(
				self::FONT_HANDLE,
				$font_src,
				array(),
				null
			);
			$style_deps[] = self::FONT_HANDLE;
		}

		wp_register_style(
			self::STYLE_HANDLE,
			MY_SLIDER_PRO_URL . 'assets/frontend.css',
			$style_deps,
			\my_slider_pro_asset_version( 'assets/frontend.css' )
		);
		wp_register_script(
			self::SCRIPT_HANDLE,
			MY_SLIDER_PRO_URL . 'assets/frontend.js',
			array(),
			\my_slider_pro_asset_version( 'assets/frontend.js' ),
			true
		);
		wp_localize_script(
			self::SCRIPT_HANDLE,
			'mySliderProFrontend',
			array(
				'previousLabel' => __( 'Previous slide', 'my-slider-pro' ),
				'nextLabel'     => __( 'Next slide', 'my-slider-pro' ),
				'pauseLabel'    => __( 'Pause slide rotation', 'my-slider-pro' ),
				'playLabel'     => __( 'Resume slide rotation', 'my-slider-pro' ),
				'slideText'     => __( 'Slide %1$d of %2$d', 'my-slider-pro' ),
			)
		);
	}

	/**
	 * Enqueue early when the queried post contains the shortcode.
	 *
	 * @return void
	 */
	public static function maybe_enqueue_for_current_post(): void {
		// Load everywhere when the setting is on (e.g. Ajax or page-builder use).
		if ( Settings::get( 'load_assets_everywhere' ) ) {
			self::enqueue_assets();
			return;
		}

		if ( ! is_singular() ) {
			return;
		}

		$post = get_queried_object();

		if ( ! $post || empty( $post->post_content ) || ! has_shortcode( $post->post_content, self::TAG ) ) {
			return;
		}

		self::enqueue_assets();
	}

	/**
	 * Render a published slider.
	 *
	 * @param mixed       $atts    Shortcode attributes.
	 * @param string|null $content Enclosed content, unused.
	 * @param string      $tag     Shortcode tag, unused.
	 * @return string
	 */
	public static function render( $atts, $content = null, string $tag = '' ): string {
		unset( $content, $tag );

		$atts = shortcode_atts(
			array( 'id' => 0 ),
			(array) $atts,
			self::TAG
		);
		$slider_id = absint( $atts['id'] );

		if ( 0 === $slider_id ) {
			return '';
		}

		$slider = get_post( $slider_id );

		if (
			! $slider ||
			SliderPostType::POST_TYPE !== $slider->post_type ||
			'publish' !== $slider->post_status
		) {
			return '';
		}

		$items = self::get_renderable_items( $slider_id );

		if ( empty( $items ) ) {
			return '';
		}

		$settings   = SliderPostType::get_settings( $slider_id );
		$instance   = wp_unique_id( 'my-slider-pro-' );
		$title      = get_the_title( $slider_id );
		$title      = '' !== $title ? $title : esc_html__( 'Slider', 'my-slider-pro' );
		$item_count = count( $items );
		$classes    = sprintf(
			'my-slider-pro-slider is-%1$s-height has-%2$s-tablet-height has-%3$s-mobile-height is-%4$s-content has-%5$s-tablet-content has-%6$s-mobile-content has-%7$s-tablet-text has-%8$s-mobile-text has-%9$s-tablet-button has-%10$s-mobile-button%11$s',
			$settings['height'],
			$settings['tablet_height'],
			$settings['mobile_height'],
			$settings['content_position'],
			$settings['tablet_content_position'],
			$settings['mobile_content_position'],
			$settings['tablet_text_width'],
			$settings['mobile_text_width'],
			$settings['tablet_button_size'],
			$settings['mobile_button_size'],
			$settings['hide_arrows_on_phone'] ? ' hides-phone-arrows' : ''
		);
		$is_boxed     = 'boxed' === ( $settings['width'] ?? 'full' );
		$shell_class  = 'my-slider-pro-shell' . ( $is_boxed ? ' has-boxed-width' : '' );
		$width_style  = $is_boxed ? sprintf( '--my-slider-pro-max-width:%dpx;', (int) $settings['max_width'] ) : '';

		self::enqueue_assets();

		ob_start();
		?>
		<div class="<?php echo esc_attr( $shell_class ); ?>"<?php echo '' !== $width_style ? ' style="' . esc_attr( $width_style ) . '"' : ''; ?>>
			<section
				id="<?php echo esc_attr( $instance ); ?>"
				class="<?php echo esc_attr( $classes ); ?>"
				aria-roledescription="<?php echo esc_attr__( 'carousel', 'my-slider-pro' ); ?>"
				aria-label="<?php echo esc_attr( sprintf( esc_html__( 'Slider: %s', 'my-slider-pro' ), $title ) ); ?>"
				data-psp-slider
				data-psp-autoplay="<?php echo $settings['autoplay'] ? '1' : '0'; ?>"
				data-psp-interval="<?php echo esc_attr( (string) $settings['interval'] ); ?>"
				data-psp-loop="<?php echo $settings['loop'] ? '1' : '0'; ?>"
				data-psp-pause-on-hover="<?php echo $settings['pause_on_hover'] ? '1' : '0'; ?>"
			>
				<div class="my-slider-pro-viewport" data-psp-slider-viewport tabindex="0">
					<ol class="my-slider-pro-track">
						<?php foreach ( $items as $index => $item ) : ?>
							<?php
							$position = $index + 1;
							$heading_style = self::layer_style( $item['content'], 'heading' );
							$description_style = self::layer_style( $item['content'], 'description' );
							$button_style = self::layer_style( $item['content'], 'button' );
							$image_layer_style = self::layer_style( $item['content'], 'image' );
							$background_styles = self::slide_background_styles( $item['content'] );
							$image_attributes = array(
								'class'    => 'my-slider-pro-image',
								'loading'  => 0 === $index ? 'eager' : 'lazy',
								'decoding' => 'async',
							);

							if ( 0 === $index ) {
								$image_attributes['fetchpriority'] = 'high';
							}
							if ( '' !== $background_styles['image'] ) {
								$image_attributes['style'] = $background_styles['image'];
							}
							?>
							<li
								class="my-slider-pro-slide"
								role="group"
								aria-roledescription="<?php echo esc_attr__( 'slide', 'my-slider-pro' ); ?>"
								aria-label="<?php echo esc_attr( sprintf( esc_html__( 'Slide %1$d of %2$d', 'my-slider-pro' ), $position, $item_count ) ); ?>"
								data-psp-slider-slide
							>
								<div class="my-slider-pro-visual">
									<?php
									// Core generates escaped alt, srcset, and sizes attributes for attachment images.
									echo wp_get_attachment_image( $item['attachment_id'], 'large', false, $image_attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									?>
								</div>
								<?php if ( '' !== $background_styles['shade'] ) : ?>
									<div class="my-slider-pro-shade" aria-hidden="true" style="<?php echo esc_attr( $background_styles['shade'] ); ?>"></div>
								<?php endif; ?>
								<?php if ( '' !== $item['content']['image_layer_url'] ) : ?>
									<div class="my-slider-pro-image-layer my-slider-pro-layer" style="<?php echo esc_attr( $image_layer_style ); ?>">
										<?php if ( '' !== $item['content']['image_link_url'] ) : ?>
											<a class="my-slider-pro-layer-link" href="<?php echo esc_url( $item['content']['image_link_url'] ); ?>"<?php echo ! empty( $item['content']['image_target'] ) ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>>
												<img src="<?php echo esc_url( $item['content']['image_layer_url'] ); ?>" alt="<?php echo esc_attr( $item['content']['image_layer_alt'] ); ?>" loading="lazy" decoding="async" />
											</a>
										<?php else : ?>
											<img src="<?php echo esc_url( $item['content']['image_layer_url'] ); ?>" alt="<?php echo esc_attr( $item['content']['image_layer_alt'] ); ?>" loading="lazy" decoding="async" />
										<?php endif; ?>
									</div>
								<?php endif; ?>
								<?php if ( '' !== $item['content']['title'] ) : ?>
									<div class="my-slider-pro-content my-slider-pro-layer my-slider-pro-heading-layer" style="<?php echo esc_attr( $heading_style ); ?>">
										<?php if ( '' !== $item['content']['heading_link_url'] ) : ?>
											<a class="my-slider-pro-layer-link" href="<?php echo esc_url( $item['content']['heading_link_url'] ); ?>"<?php echo ! empty( $item['content']['heading_target'] ) ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>>
												<h2><?php echo esc_html( $item['content']['title'] ); ?></h2>
											</a>
										<?php else : ?>
											<h2><?php echo esc_html( $item['content']['title'] ); ?></h2>
										<?php endif; ?>
									</div>
								<?php endif; ?>
								<?php if ( '' !== $item['content']['description'] ) : ?>
									<div class="my-slider-pro-content my-slider-pro-layer my-slider-pro-description-layer" style="<?php echo esc_attr( $description_style ); ?>">
										<?php if ( '' !== $item['content']['description_link_url'] ) : ?>
											<a class="my-slider-pro-layer-link" href="<?php echo esc_url( $item['content']['description_link_url'] ); ?>"<?php echo ! empty( $item['content']['description_target'] ) ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>>
												<p><?php echo nl2br( esc_html( $item['content']['description'] ) ); ?></p>
											</a>
										<?php else : ?>
											<p><?php echo nl2br( esc_html( $item['content']['description'] ) ); ?></p>
										<?php endif; ?>
									</div>
								<?php endif; ?>
								<?php if ( '' !== $item['content']['button_label'] && '' !== $item['content']['button_url'] ) : ?>
									<div class="my-slider-pro-button-layer my-slider-pro-layer" style="<?php echo esc_attr( $button_style ); ?>">
										<a class="my-slider-pro-button" href="<?php echo esc_url( $item['content']['button_url'] ); ?>"<?php echo $item['content']['button_target'] ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>><?php echo esc_html( $item['content']['button_label'] ); ?></a>
									</div>
								<?php endif; ?>
								<?php foreach ( $item['content']['extra_layers'] as $extra_index => $extra_layer ) : ?>
									<?php self::render_extra_layer( $extra_layer, $extra_index, (string) $item['content']['layer_order'] ); ?>
								<?php endforeach; ?>
							</li>
						<?php endforeach; ?>
					</ol>
				</div>

				<?php if ( $item_count > 1 && $settings['arrows'] ) : ?>
					<button type="button" class="my-slider-pro-arrow my-slider-pro-previous" data-psp-slider-previous aria-label="<?php echo esc_attr__( 'Previous slide', 'my-slider-pro' ); ?>">&#8249;</button>
					<button type="button" class="my-slider-pro-arrow my-slider-pro-next" data-psp-slider-next aria-label="<?php echo esc_attr__( 'Next slide', 'my-slider-pro' ); ?>">&#8250;</button>
				<?php endif; ?>

				<?php if ( $item_count > 1 && ( $settings['dots'] || $settings['autoplay'] ) ) : ?>
					<div class="my-slider-pro-footer">
						<?php if ( $settings['dots'] ) : ?>
							<div class="my-slider-pro-dots" role="tablist" aria-label="<?php echo esc_attr__( 'Choose slide', 'my-slider-pro' ); ?>">
								<?php foreach ( $items as $index => $item ) : ?>
									<button type="button" class="my-slider-pro-dot" data-psp-slider-dot="<?php echo esc_attr( (string) $index ); ?>" aria-label="<?php echo esc_attr( sprintf( esc_html__( 'Show slide %1$d of %2$d', 'my-slider-pro' ), $index + 1, $item_count ) ); ?>" aria-current="<?php echo 0 === $index ? 'true' : 'false'; ?>"></button>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
						<?php if ( $settings['autoplay'] ) : ?>
							<button type="button" class="my-slider-pro-toggle" data-psp-slider-toggle aria-label="<?php echo esc_attr__( 'Pause slide rotation', 'my-slider-pro' ); ?>"><span aria-hidden="true">II</span></button>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</section>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Ensure registered frontend assets are enqueued once.
	 *
	 * @return void
	 */
	private static function enqueue_assets(): void {
		if ( ! wp_style_is( self::STYLE_HANDLE, 'registered' ) || ! wp_script_is( self::SCRIPT_HANDLE, 'registered' ) ) {
			self::register_assets();
		}

		if ( wp_style_is( self::FONT_HANDLE, 'registered' ) ) {
			wp_enqueue_style( self::FONT_HANDLE );
		}
		wp_enqueue_style( self::STYLE_HANDLE );
		wp_enqueue_script( self::SCRIPT_HANDLE );
	}

	/**
	 * Build renderable slide data while preserving saved order.
	 *
	 * @param int $slider_id Slider post ID.
	 * @return array<int, array{attachment_id:int,content:array<string,mixed>}>
	 */
	private static function get_renderable_items( int $slider_id ): array {
		$items       = array();
		$image_ids   = SliderPostType::get_image_ids( $slider_id );
		$slide_content = SliderPostType::get_slide_content( $slider_id, $image_ids );

		foreach ( $image_ids as $attachment_id ) {
			// Core resolves an attachment's inherited status to its parent status.
			if ( 'publish' !== get_post_status( $attachment_id ) || ! wp_get_attachment_image_url( $attachment_id, 'large' ) ) {
				continue;
			}

			$items[] = array(
				'attachment_id' => $attachment_id,
				'content'       => $slide_content[ $attachment_id ],
			);
		}

		return $items;
	}

	/**
	 * Build safe responsive custom properties for one independently positioned layer.
	 *
	 * @param array<string, mixed> $content Sanitized slide content.
	 * @param string               $layer Layer name: heading, description, button, or image.
	 * @return string
	 */
	private static function layer_style( array $content, string $layer ): string {
		$coordinate_layer = 'heading' === $layer ? 'text' : $layer;
		$desktop_x = (int) $content[ $coordinate_layer . '_x' ];
		$desktop_y = (int) $content[ $coordinate_layer . '_y' ];
		$tablet_x  = (int) $content[ 'tablet_' . $coordinate_layer . '_x' ];
		$tablet_y  = (int) $content[ 'tablet_' . $coordinate_layer . '_y' ];
		$mobile_x  = (int) $content[ 'mobile_' . $coordinate_layer . '_x' ];
		$mobile_y  = (int) $content[ 'mobile_' . $coordinate_layer . '_y' ];

		// When position is linked, every device uses the desktop coordinates.
		if ( ! empty( $content[ $layer . '_pos_linked' ] ) ) {
			$tablet_x = $desktop_x;
			$tablet_y = $desktop_y;
			$mobile_x = $desktop_x;
			$mobile_y = $desktop_y;
		}

		$style = sprintf(
			'--my-slider-pro-desktop-x:%1$d%%;--my-slider-pro-desktop-y:%2$d%%;--my-slider-pro-desktop-translate-x:%3$s;--my-slider-pro-desktop-translate-y:%4$s;--my-slider-pro-tablet-x:%5$d%%;--my-slider-pro-tablet-y:%6$d%%;--my-slider-pro-tablet-translate-x:%7$s;--my-slider-pro-tablet-translate-y:%8$s;--my-slider-pro-mobile-x:%9$d%%;--my-slider-pro-mobile-y:%10$d%%;--my-slider-pro-mobile-translate-x:%11$s;--my-slider-pro-mobile-translate-y:%12$s',
			$desktop_x,
			$desktop_y,
			self::layer_anchor_offset( $desktop_x ),
			self::layer_anchor_offset( $desktop_y ),
			$tablet_x,
			$tablet_y,
			self::layer_anchor_offset( $tablet_x ),
			self::layer_anchor_offset( $tablet_y ),
			$mobile_x,
			$mobile_y,
			self::layer_anchor_offset( $mobile_x ),
			self::layer_anchor_offset( $mobile_y )
		);
		$order = explode( ',', (string) $content['layer_order'] );
		$index = array_search( $layer, $order, true );
		$style .= sprintf( ';--my-slider-pro-layer-z:%d', false === $index ? 1 : count( $order ) - $index );
		$animation_names = array(
			'none'        => 'none',
			'fade'        => 'my-slider-pro-fade',
			'slide-up'    => 'my-slider-pro-slide-up',
			'slide-down'  => 'my-slider-pro-slide-down',
			'slide-left'  => 'my-slider-pro-slide-left',
			'slide-right' => 'my-slider-pro-slide-right',
			'zoom'        => 'my-slider-pro-zoom',
		);
		$style .= sprintf(
			';--my-slider-pro-layer-animation:%1$s;--my-slider-pro-layer-animation-duration:%2$dms;--my-slider-pro-layer-animation-delay:%3$dms;--my-slider-pro-layer-animation-easing:%4$s',
			$animation_names[ $content[ $layer . '_animation' ] ],
			(int) $content[ $layer . '_animation_duration' ],
			(int) $content[ $layer . '_animation_delay' ],
			$content[ $layer . '_animation_easing' ]
		);

		if ( 'heading' === $layer || 'description' === $layer ) {
			$font_families = array(
				'theme'       => 'inherit',
				'poppins'     => '"Poppins",sans-serif',
				'montserrat'  => '"Montserrat",sans-serif',
				'inter'       => '"Inter",sans-serif',
			);
			if ( 'heading' === $layer ) {
				$sizes = self::responsive_sizes( $content, 'heading_size', 'tablet_heading_size', 'mobile_heading_size', 'heading_size_linked' );
				$style .= sprintf(
					';--my-slider-pro-text-color:%1$s;--my-slider-pro-heading-size:%2$dpx;--my-slider-pro-tablet-heading-size:%6$dpx;--my-slider-pro-mobile-heading-size:%7$dpx;--my-slider-pro-text-align:%3$s;--my-slider-pro-font-family:%4$s;--my-slider-pro-heading-opacity:%5$.2f',
					$content['text_color'],
					$sizes['desktop'],
					$content['text_align'],
					$font_families[ $content['font_family'] ],
					(int) $content['heading_opacity'] / 100,
					$sizes['tablet'],
					$sizes['mobile']
				);
				$style .= self::font_style_css( $content['heading_font_style'] ?? 'default', '' );
			} else {
				$sizes = self::responsive_sizes( $content, 'description_size', 'tablet_description_size', 'mobile_description_size', 'description_size_linked' );
				$style .= sprintf(
					';--my-slider-pro-text-color:%1$s;--my-slider-pro-description-size:%2$dpx;--my-slider-pro-tablet-description-size:%6$dpx;--my-slider-pro-mobile-description-size:%7$dpx;--my-slider-pro-text-align:%3$s;--my-slider-pro-font-family:%4$s;--my-slider-pro-description-opacity:%5$.2f',
					$content['description_color'],
					$sizes['desktop'],
					$content['description_align'],
					$font_families[ $content['description_font_family'] ],
					(int) $content['description_opacity'] / 100,
					$sizes['tablet'],
					$sizes['mobile']
				);
				$style .= self::font_style_css( $content['description_font_style'] ?? 'default', '' );
			}
		} elseif ( 'button' === $layer ) {
			$font_families = array(
				'theme'       => 'inherit',
				'poppins'     => '"Poppins",sans-serif',
				'montserrat'  => '"Montserrat",sans-serif',
				'inter'       => '"Inter",sans-serif',
			);
			$sizes = self::responsive_sizes( $content, 'button_font_size', 'tablet_button_font_size', 'mobile_button_font_size', 'button_size_linked' );
			$style .= sprintf(
				';--my-slider-pro-button-color:%1$s;--my-slider-pro-button-background:%2$s;--my-slider-pro-button-font-family:%3$s;--my-slider-pro-button-font-size:%4$dpx;--my-slider-pro-tablet-button-font-size:%9$dpx;--my-slider-pro-mobile-button-font-size:%10$dpx;--my-slider-pro-button-opacity:%5$.2f;--my-slider-pro-button-radius:%6$dpx;--my-slider-pro-button-padding-x:%7$dpx;--my-slider-pro-button-padding-y:%8$dpx',
				$content['button_text_color'],
				$content['button_background'],
				$font_families[ $content['button_font_family'] ],
				$sizes['desktop'],
				(int) $content['button_opacity'] / 100,
				(int) $content['button_radius'],
				(int) $content['button_padding_x'],
				(int) $content['button_padding_y'],
				$sizes['tablet'],
				$sizes['mobile']
			);
			$style .= self::font_style_css( $content['button_font_style'] ?? 'default', 'button-' );
		} else {
			$sizes = self::responsive_sizes( $content, 'image_width', 'tablet_image_width', 'mobile_image_width', 'image_size_linked' );
			$style .= sprintf(
				';--my-slider-pro-image-layer-width:%1$dpx;--my-slider-pro-tablet-image-layer-width:%3$dpx;--my-slider-pro-mobile-image-layer-width:%4$dpx;--my-slider-pro-image-layer-opacity:%2$.2f',
				$sizes['desktop'],
				(int) $content['image_opacity'] / 100,
				$sizes['tablet'],
				$sizes['mobile']
			);
		}

		return $style;
	}

	/**
	 * Render one additional repeatable overlay layer.
	 *
	 * @param array<string, mixed> $layer Layer data.
	 * @param int                  $index Zero-based layer index.
	 * @param string               $order Combined front-to-back layer order.
	 * @return void
	 */
	private static function render_extra_layer( array $layer, int $index, string $order = '' ): void {
		$type = (string) $layer['type'];
		if ( 'image' === $type && '' === $layer['url'] ) {
			return;
		}
		if ( in_array( $type, array( 'heading', 'description', 'button' ), true ) && '' === $layer['text'] ) {
			return;
		}

		$style = self::extra_layer_style( $layer, $index, $order );
		?>
		<?php if ( 'image' === $type ) : ?>
			<div class="my-slider-pro-image-layer my-slider-pro-layer" style="<?php echo esc_attr( $style ); ?>">
				<?php if ( '' !== $layer['link_url'] ) : ?>
					<a class="my-slider-pro-layer-link" href="<?php echo esc_url( $layer['link_url'] ); ?>"<?php echo ! empty( $layer['target'] ) ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>>
						<img src="<?php echo esc_url( $layer['url'] ); ?>" alt="<?php echo esc_attr( $layer['alt'] ); ?>" loading="lazy" decoding="async" />
					</a>
				<?php else : ?>
					<img src="<?php echo esc_url( $layer['url'] ); ?>" alt="<?php echo esc_attr( $layer['alt'] ); ?>" loading="lazy" decoding="async" />
				<?php endif; ?>
			</div>
		<?php elseif ( 'button' === $type && '' !== $layer['link_url'] ) : ?>
			<div class="my-slider-pro-button-layer my-slider-pro-layer" style="<?php echo esc_attr( $style ); ?>">
				<a class="my-slider-pro-button" href="<?php echo esc_url( $layer['link_url'] ); ?>"<?php echo ! empty( $layer['target'] ) ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>><?php echo esc_html( $layer['text'] ); ?></a>
			</div>
		<?php elseif ( 'heading' === $type ) : ?>
			<div class="my-slider-pro-content my-slider-pro-layer my-slider-pro-heading-layer" style="<?php echo esc_attr( $style ); ?>">
				<?php if ( '' !== $layer['link_url'] ) : ?>
					<a class="my-slider-pro-layer-link" href="<?php echo esc_url( $layer['link_url'] ); ?>"<?php echo ! empty( $layer['target'] ) ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>><h2><?php echo esc_html( $layer['text'] ); ?></h2></a>
				<?php else : ?>
					<h2><?php echo esc_html( $layer['text'] ); ?></h2>
				<?php endif; ?>
			</div>
		<?php elseif ( 'description' === $type ) : ?>
			<div class="my-slider-pro-content my-slider-pro-layer my-slider-pro-description-layer" style="<?php echo esc_attr( $style ); ?>">
				<?php if ( '' !== $layer['link_url'] ) : ?>
					<a class="my-slider-pro-layer-link" href="<?php echo esc_url( $layer['link_url'] ); ?>"<?php echo ! empty( $layer['target'] ) ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>><p><?php echo nl2br( esc_html( $layer['text'] ) ); ?></p></a>
				<?php else : ?>
					<p><?php echo nl2br( esc_html( $layer['text'] ) ); ?></p>
				<?php endif; ?>
			</div>
		<?php elseif ( 'shape' === $type ) : ?>
			<?php $shape_overlay = self::shape_overlay_style( $layer ); ?>
			<div class="my-slider-pro-shape-layer my-slider-pro-layer" style="<?php echo esc_attr( $style ); ?>" aria-hidden="true">
				<?php if ( '' !== $shape_overlay ) : ?>
					<span class="my-slider-pro-shape-shade" style="<?php echo esc_attr( $shape_overlay ); ?>"></span>
				<?php endif; ?>
			</div>
		<?php endif; ?>
		<?php
	}

	/**
	 * Build the fill/gradient overlay drawn on top of a shape layer's fill.
	 *
	 * Mirrors the slide-background overlay model (solid or gradient at a chosen
	 * opacity and direction) but sources the values from the shape's own fields.
	 *
	 * @param array<string, mixed> $layer Layer data.
	 * @return string CSS background declaration, or '' when there is no overlay.
	 */
	private static function shape_overlay_style( array $layer ): string {
		$overlay_type = (string) ( $layer['overlay_type'] ?? 'none' );
		$opacity      = max( 0, min( 100, (int) ( $layer['overlay_opacity'] ?? 50 ) ) ) / 100;

		if ( 'solid' === $overlay_type && $opacity > 0 ) {
			return 'background:' . self::hex_to_rgba( (string) ( $layer['overlay_color'] ?? '#08101f' ), $opacity ) . ';';
		}

		if ( 'gradient' === $overlay_type && $opacity > 0 ) {
			$directions = array( 'to bottom', 'to top', 'to right', 'to left', 'to bottom right', 'to bottom left' );
			$direction  = (string) ( $layer['overlay_direction'] ?? 'to bottom' );
			$direction  = in_array( $direction, $directions, true ) ? $direction : 'to bottom';

			return sprintf(
				'background:linear-gradient(%1$s,%2$s,%3$s);',
				$direction,
				self::hex_to_rgba( (string) ( $layer['overlay_color'] ?? '#08101f' ), $opacity ),
				self::hex_to_rgba( (string) ( $layer['overlay_color2'] ?? '#000000' ), $opacity )
			);
		}

		return '';
	}

	/**
	 * Build inline CSS variables for an additional repeatable layer.
	 *
	 * @param array<string, mixed> $layer Layer data.
	 * @param int                  $index Zero-based layer index.
	 * @param string               $order Combined front-to-back layer order.
	 * @return string
	 */
	private static function extra_layer_style( array $layer, int $index, string $order = '' ): string {
		$font_families = array(
			'theme'      => 'inherit',
			'poppins'    => '"Poppins",sans-serif',
			'montserrat' => '"Montserrat",sans-serif',
			'inter'      => '"Inter",sans-serif',
		);
		$animation_names = array(
			'none'        => 'none',
			'fade'        => 'my-slider-pro-fade',
			'slide-up'    => 'my-slider-pro-slide-up',
			'slide-down'  => 'my-slider-pro-slide-down',
			'slide-left'  => 'my-slider-pro-slide-left',
			'slide-right' => 'my-slider-pro-slide-right',
			'zoom'        => 'my-slider-pro-zoom',
		);
		$type = (string) $layer['type'];
		// z from the unified layer order; overlay layers not yet placed in the
		// order fall back above the base layers (a freshly added overlay).
		$order_list = '' !== $order ? explode( ',', $order ) : array();
		$order_pos  = array_search( 'extra-' . $index, $order_list, true );
		$layer_z    = false === $order_pos ? count( $order_list ) + 1 + $index : count( $order_list ) - $order_pos;
		$dx   = (int) $layer['desktop_x'];
		$dy   = (int) $layer['desktop_y'];
		$tx   = (int) $layer['tablet_x'];
		$ty   = (int) $layer['tablet_y'];
		$mx   = (int) $layer['mobile_x'];
		$my   = (int) $layer['mobile_y'];

		// When position is linked, every device uses the desktop coordinates.
		if ( ! empty( $layer['pos_linked'] ) ) {
			$tx = $dx;
			$ty = $dy;
			$mx = $dx;
			$my = $dy;
		}

		$style = sprintf(
			'--my-slider-pro-desktop-x:%1$d%%;--my-slider-pro-desktop-y:%2$d%%;--my-slider-pro-desktop-translate-x:%3$s;--my-slider-pro-desktop-translate-y:%4$s;--my-slider-pro-tablet-x:%5$d%%;--my-slider-pro-tablet-y:%6$d%%;--my-slider-pro-tablet-translate-x:%7$s;--my-slider-pro-tablet-translate-y:%8$s;--my-slider-pro-mobile-x:%9$d%%;--my-slider-pro-mobile-y:%10$d%%;--my-slider-pro-mobile-translate-x:%11$s;--my-slider-pro-mobile-translate-y:%12$s;--my-slider-pro-layer-z:%13$d;--my-slider-pro-layer-animation:%14$s;--my-slider-pro-layer-animation-duration:%15$dms;--my-slider-pro-layer-animation-delay:%16$dms;--my-slider-pro-layer-animation-easing:%17$s',
			$dx,
			$dy,
			self::layer_anchor_offset( $dx ),
			self::layer_anchor_offset( $dy ),
			$tx,
			$ty,
			self::layer_anchor_offset( $tx ),
			self::layer_anchor_offset( $ty ),
			$mx,
			$my,
			self::layer_anchor_offset( $mx ),
			self::layer_anchor_offset( $my ),
			$layer_z,
			$animation_names[ $layer['animation'] ],
			(int) $layer['animation_duration'],
			(int) $layer['animation_delay'],
			$layer['animation_easing']
		);

		if ( 'image' === $type ) {
			$sizes = self::responsive_sizes( $layer, 'width', 'tablet_width', 'mobile_width', 'size_linked' );
			return $style . sprintf( ';--my-slider-pro-image-layer-width:%1$dpx;--my-slider-pro-tablet-image-layer-width:%3$dpx;--my-slider-pro-mobile-image-layer-width:%4$dpx;--my-slider-pro-image-layer-opacity:%2$.2f', $sizes['desktop'], (int) $layer['opacity'] / 100, $sizes['tablet'], $sizes['mobile'] );
		}
		if ( 'shape' === $type ) {
			$sizes = self::responsive_sizes( $layer, 'width', 'tablet_width', 'mobile_width', 'size_linked' );
			return $style . sprintf(
				';--my-slider-pro-shape-width:%1$dpx;--my-slider-pro-tablet-shape-width:%2$dpx;--my-slider-pro-mobile-shape-width:%3$dpx;--my-slider-pro-shape-height:%4$dpx;--my-slider-pro-shape-radius:%5$dpx;--my-slider-pro-shape-fill:%6$s;--my-slider-pro-shape-opacity:%7$.2f',
				$sizes['desktop'],
				$sizes['tablet'],
				$sizes['mobile'],
				(int) $layer['height'],
				(int) $layer['radius'],
				(string) $layer['background'],
				(int) $layer['opacity'] / 100
			);
		}
		$sizes = self::responsive_sizes( $layer, 'size', 'tablet_size', 'mobile_size', 'size_linked' );
		if ( 'button' === $type ) {
			return $style . sprintf( ';--my-slider-pro-button-color:%1$s;--my-slider-pro-button-background:%2$s;--my-slider-pro-button-font-family:%3$s;--my-slider-pro-button-font-size:%4$dpx;--my-slider-pro-tablet-button-font-size:%6$dpx;--my-slider-pro-mobile-button-font-size:%7$dpx;--my-slider-pro-button-opacity:%5$.2f;--my-slider-pro-button-radius:4px;--my-slider-pro-button-padding-x:20px;--my-slider-pro-button-padding-y:12px', $layer['color'], $layer['background'], $font_families[ $layer['font_family'] ], $sizes['desktop'], (int) $layer['opacity'] / 100, $sizes['tablet'], $sizes['mobile'] ) . self::font_style_css( $layer['font_style'] ?? 'default', 'button-' );
		}

		$size_var      = 'heading' === $type ? '--my-slider-pro-heading-size' : '--my-slider-pro-description-size';
		$tablet_var    = 'heading' === $type ? '--my-slider-pro-tablet-heading-size' : '--my-slider-pro-tablet-description-size';
		$mobile_var    = 'heading' === $type ? '--my-slider-pro-mobile-heading-size' : '--my-slider-pro-mobile-description-size';
		$opacity_var = 'heading' === $type ? '--my-slider-pro-heading-opacity' : '--my-slider-pro-description-opacity';
		return $style . sprintf( ';--my-slider-pro-text-color:%1$s;%2$s:%3$dpx;%8$s:%9$dpx;%10$s:%11$dpx;--my-slider-pro-text-align:%4$s;--my-slider-pro-font-family:%5$s;%6$s:%7$.2f', $layer['color'], $size_var, $sizes['desktop'], $layer['align'], $font_families[ $layer['font_family'] ], $opacity_var, (int) $layer['opacity'] / 100, $tablet_var, $sizes['tablet'], $mobile_var, $sizes['mobile'] ) . self::font_style_css( $layer['font_style'] ?? 'default', '' );
	}

	/**
	 * Resolve a layer's desktop/tablet/mobile size, honoring the link flag.
	 *
	 * @param array<string, mixed> $content   Sanitized slide content.
	 * @param string               $desktop   Desktop size key.
	 * @param string               $tablet    Tablet size key.
	 * @param string               $mobile    Mobile size key.
	 * @param string               $flag_key  Link flag key ('1' when linked).
	 * @return array{desktop:int,tablet:int,mobile:int}
	 */
	private static function responsive_sizes( array $content, string $desktop, string $tablet, string $mobile, string $flag_key ): array {
		$desktop_value = (int) ( $content[ $desktop ] ?? 0 );

		if ( ! empty( $content[ $flag_key ] ) ) {
			return array(
				'desktop' => $desktop_value,
				'tablet'  => $desktop_value,
				'mobile'  => $desktop_value,
			);
		}

		return array(
			'desktop' => $desktop_value,
			'tablet'  => (int) ( $content[ $tablet ] ?? $desktop_value ),
			'mobile'  => (int) ( $content[ $mobile ] ?? $desktop_value ),
		);
	}

	/**
	 * Build the font weight/style custom properties for a layer style preset.
	 *
	 * @param string $value  Sanitized font style (default|normal|bold|italic|bold-italic).
	 * @param string $prefix Variable prefix ('' for text layers, 'button-' for buttons).
	 * @return string
	 */
	private static function font_style_css( string $value, string $prefix ): string {
		$map = array(
			'normal'      => array( 400, 'normal' ),
			'bold'        => array( 700, 'normal' ),
			'italic'      => array( 400, 'italic' ),
			'bold-italic' => array( 700, 'italic' ),
		);

		if ( ! isset( $map[ $value ] ) ) {
			return '';
		}

		return sprintf( ';--my-slider-pro-%1$sfont-weight:%2$d;--my-slider-pro-%1$sfont-style:%3$s', $prefix, $map[ $value ][0], $map[ $value ][1] );
	}

	/**
	 * Convert a bounded anchor coordinate into an edge-safe translate offset.
	 *
	 * @param int $coordinate Sanitized coordinate.
	 * @return string
	 */
	private static function layer_anchor_offset( int $coordinate ): string {
		if ( $coordinate <= 33 ) {
			return '0';
		}

		return $coordinate >= 67 ? '-100%' : '-50%';
	}

	/**
	 * Build inline styles for the per-slide background layer.
	 *
	 * A non-default fill mode, position, or filter overrides the slide's
	 * focal-point classes because it is an explicit, deliberate choice.
	 *
	 * @param array<string, mixed> $content Sanitized slide content.
	 * @return array{image:string,shade:string}
	 */
	private static function slide_background_styles( array $content ): array {
		$image = '';
		$shade = '';

		$fill_map = array(
			'cover'  => 'cover',
			'fill'   => 'fill',
			'fit'    => 'contain',
			'center' => 'none',
		);
		$fill = (string) ( $content['background_fill'] ?? 'cover' );
		if ( 'cover' !== $fill && isset( $fill_map[ $fill ] ) ) {
			$image .= 'object-fit:' . $fill_map[ $fill ] . ';';
		}

		$position_map = array(
			'top_left'      => 'left top',
			'top_center'    => 'center top',
			'top_right'     => 'right top',
			'center_left'   => 'left center',
			'center'        => 'center',
			'center_right'  => 'right center',
			'bottom_left'   => 'left bottom',
			'bottom_center' => 'center bottom',
			'bottom_right'  => 'right bottom',
		);
		$desktop_position = (string) ( $content['background_position'] ?? 'center' );
		$tablet_position  = (string) ( $content['tablet_background_position'] ?? 'center' );
		$mobile_position  = (string) ( $content['mobile_background_position'] ?? 'center' );
		if ( 'center' !== $desktop_position || 'center' !== $tablet_position || 'center' !== $mobile_position ) {
			$image .= sprintf(
				'--my-slider-pro-bg-position:%1$s;--my-slider-pro-bg-position-tablet:%2$s;--my-slider-pro-bg-position-mobile:%3$s;',
				isset( $position_map[ $desktop_position ] ) ? $position_map[ $desktop_position ] : 'center',
				isset( $position_map[ $tablet_position ] ) ? $position_map[ $tablet_position ] : 'center',
				isset( $position_map[ $mobile_position ] ) ? $position_map[ $mobile_position ] : 'center'
			);
		}

		$overlay_type = (string) ( $content['overlay_type'] ?? 'none' );
		$opacity      = max( 0, min( 100, (int) ( $content['overlay_opacity'] ?? 50 ) ) ) / 100;
		if ( 'solid' === $overlay_type && $opacity > 0 ) {
			$shade = 'background:' . self::hex_to_rgba( (string) ( $content['overlay_color'] ?? '#08101f' ), $opacity ) . ';';
		} elseif ( 'gradient' === $overlay_type && $opacity > 0 ) {
			$directions = array( 'to bottom', 'to top', 'to right', 'to left', 'to bottom right', 'to bottom left' );
			$direction  = (string) ( $content['overlay_direction'] ?? 'to bottom' );
			$direction  = in_array( $direction, $directions, true ) ? $direction : 'to bottom';
			$shade      = sprintf(
				'background:linear-gradient(%1$s,%2$s,%3$s);',
				$direction,
				self::hex_to_rgba( (string) ( $content['overlay_color'] ?? '#08101f' ), $opacity ),
				self::hex_to_rgba( (string) ( $content['overlay_color2'] ?? '#000000' ), $opacity )
			);
		}

		return array(
			'image' => rtrim( $image, ';' ),
			'shade' => rtrim( $shade, ';' ),
		);
	}

	/**
	 * Convert a #rrggbb hex color into an rgba() string at a fixed alpha.
	 *
	 * @param string $hex   Hex color, e.g. #08101f.
	 * @param float  $alpha Alpha channel 0-1.
	 * @return string
	 */
	private static function hex_to_rgba( string $hex, float $alpha ): string {
		$hex = ltrim( $hex, '#' );

		if ( 6 !== strlen( $hex ) ) {
			return sprintf( 'rgba(8,16,31,%.2f)', $alpha );
		}

		return sprintf(
			'rgba(%d,%d,%d,%.2f)',
			hexdec( substr( $hex, 0, 2 ) ),
			hexdec( substr( $hex, 2, 2 ) ),
			hexdec( substr( $hex, 4, 2 ) ),
			$alpha
		);
	}
}
