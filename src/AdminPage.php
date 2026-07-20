<?php
/**
 * WordPress admin experience for MY Slider PRO.
 *
 * @package MySliderPro
 */

namespace MySliderPro;

defined( 'ABSPATH' ) || exit;

/**
 * Renders slider management and handles authorized slider mutations.
 */
final class AdminPage {

	private const PAGE_SLUG = 'my-slider-pro';

	private const EDITOR_SLUG = 'my-slider-pro-new';

	private const FONT_HANDLE = 'my-slider-pro-fonts';

	private const FONT_URL = 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Montserrat:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap';

	private const CAPABILITY = 'upload_files';

	private const SAVE_ACTION = 'my_slider_pro_save_slider';

	private const DELETE_ACTION = 'my_slider_pro_delete_slider';

	private const RENAME_ACTION = 'my_slider_pro_rename_slider';

	private const DUPLICATE_ACTION = 'my_slider_pro_duplicate_slider';

	private const EXPORT_ACTION = 'my_slider_pro_export_slider';

	private const IMPORT_ACTION = 'my_slider_pro_import_slider';

	private const THUMBNAIL_ACTION = 'my_slider_pro_set_thumbnail';

	private const SLIDERS_PER_PAGE = 24;

	/** @var string */
	private static $overview_hook = '';

	/** @var string */
	private static $editor_hook = '';

	/**
	 * Register WordPress hooks for the admin experience.
	 *
	 * @return void
	 */
	public static function boot(): void {
		add_action( 'admin_menu', array( self::class, 'register' ), 9 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_assets' ) );
		add_action( 'admin_post_' . self::SAVE_ACTION, array( self::class, 'handle_save' ) );
		add_action( 'admin_post_' . self::DELETE_ACTION, array( self::class, 'handle_delete' ) );
		add_action( 'admin_post_' . self::RENAME_ACTION, array( self::class, 'handle_rename' ) );
		add_action( 'admin_post_' . self::DUPLICATE_ACTION, array( self::class, 'handle_duplicate' ) );
		add_action( 'admin_post_' . self::EXPORT_ACTION, array( self::class, 'handle_export' ) );
		add_action( 'admin_post_' . self::IMPORT_ACTION, array( self::class, 'handle_import' ) );
		add_action( 'admin_post_' . self::THUMBNAIL_ACTION, array( self::class, 'handle_set_thumbnail' ) );
		add_filter(
			'plugin_action_links_' . MY_SLIDER_PRO_BASENAME,
			array( self::class, 'add_action_link' )
		);
		add_filter( 'plugin_row_meta', array( self::class, 'add_row_meta' ), 10, 2 );
	}

	/**
	 * Base64-encoded monochrome menu icon (WordPress recolors it to the admin scheme).
	 *
	 * @return string Data URI suitable for add_menu_page().
	 */
	private static function menu_icon(): string {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">'
			. '<path fill="#a7aaad" fill-rule="evenodd" d="M3.5 4.5h13a1 1 0 0 1 1 1v7a1 1 0 0 1-1 1h-13a1 1 0 0 1-1-1v-7a1 1 0 0 1 1-1Zm.5 1.5v6h12v-6H4Z"/>'
			. '<circle fill="#a7aaad" cx="13.3" cy="7.7" r="1.15"/>'
			. '<path fill="#a7aaad" d="M4 12 L7 8.2 L9 10.2 L11.2 7.7 L14 12 Z"/>'
			. '<circle fill="#a7aaad" cx="8.2" cy="16.2" r="0.95"/>'
			. '<circle fill="#a7aaad" cx="10" cy="16.2" r="0.95"/>'
			. '<circle fill="#a7aaad" cx="11.8" cy="16.2" r="0.95"/>'
			. '</svg>';

		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}

	/**
	 * Register the plugin menu and slider editor submenu.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( ! self::can_manage_sliders() ) {
			return;
		}

		$overview_page_title = sprintf(
			/* translators: 1: plugin name, 2: plugin version number. */
			esc_html__( '%1$s — Version v%2$s', 'my-slider-pro' ),
			MY_SLIDER_PRO_NAME,
			MY_SLIDER_PRO_VERSION
		);
		$editor_action = self::requested_slider_id() > 0
			? esc_html__( 'Edit Slider', 'my-slider-pro' )
			: esc_html__( 'Add Slider', 'my-slider-pro' );
		$editor_page_title = sprintf(
			/* translators: 1: editor action, 2: plugin name, 3: plugin version number. */
			esc_html__( '%1$s — %2$s v%3$s', 'my-slider-pro' ),
			$editor_action,
			MY_SLIDER_PRO_NAME,
			MY_SLIDER_PRO_VERSION
		);

		self::$overview_hook = add_menu_page(
			$overview_page_title,
			MY_SLIDER_PRO_NAME,
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( self::class, 'render_overview' ),
			self::menu_icon(),
			58
		);

		add_submenu_page(
			self::PAGE_SLUG,
			$overview_page_title,
			esc_html__( 'Sliders', 'my-slider-pro' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( self::class, 'render_overview' )
		);

		self::$editor_hook = add_submenu_page(
			self::PAGE_SLUG,
			$editor_page_title,
			esc_html__( 'Add Slider', 'my-slider-pro' ),
			self::CAPABILITY,
			self::EDITOR_SLUG,
			array( self::class, 'render_editor' )
		);
	}

	/**
	 * Add a Manage Sliders link to the plugin row.
	 *
	 * @param array<int, string> $links Existing plugin action links.
	 * @return array<int, string>
	 */
	public static function add_action_link( array $links ): array {
		if ( ! self::can_manage_sliders() ) {
			return $links;
		}

		array_unshift(
			$links,
			sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( self::get_overview_url() ),
				esc_html__( 'Manage Sliders', 'my-slider-pro' )
			)
		);

		return $links;
	}

	/**
	 * Add a Sponsor link to the plugin's row meta on the Plugins screen.
	 *
	 * @param array<int, string> $meta Existing row meta links.
	 * @param string             $file Plugin file the meta belongs to.
	 * @return array<int, string>
	 */
	public static function add_row_meta( array $meta, string $file ): array {
		if ( MY_SLIDER_PRO_BASENAME === $file ) {
			$meta[] = sprintf(
				'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
				esc_url( 'https://github.com/sponsors/aminudinmurad' ),
				esc_html__( 'Sponsor', 'my-slider-pro' )
			);
		}

		return $meta;
	}

	/**
	 * Enqueue assets only on MY Slider PRO screens.
	 *
	 * @param string $hook_suffix Current admin hook suffix.
	 * @return void
	 */
	public static function enqueue_assets( string $hook_suffix ): void {
		if ( self::$overview_hook !== $hook_suffix && self::$editor_hook !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			self::FONT_HANDLE,
			self::FONT_URL,
			array(),
			null
		);
		wp_enqueue_style(
			'my-slider-pro-admin',
			MY_SLIDER_PRO_URL . 'assets/admin.css',
			array( self::FONT_HANDLE ),
			\my_slider_pro_asset_version( 'assets/admin.css' )
		);

		$dependencies = array( 'jquery' );

		if ( self::$editor_hook === $hook_suffix ) {
			$dependencies[] = 'jquery-ui-sortable';
			wp_enqueue_media( self::media_library_args() );
		} else {
			// The overview's per-card "Set thumbnail" picker needs the media modal.
			wp_enqueue_media();
		}

		wp_enqueue_script(
			'my-slider-pro-admin',
			MY_SLIDER_PRO_URL . 'assets/admin.js',
			$dependencies,
			\my_slider_pro_asset_version( 'assets/admin.js' ),
			true
		);
		wp_localize_script(
			'my-slider-pro-admin',
			'mySliderProAdmin',
			array(
				'frameTitle'       => __( 'Choose slider images', 'my-slider-pro' ),
				'frameButton'      => __( 'Use selected images', 'my-slider-pro' ),
				'restSearchUrl'    => rest_url( 'wp/v2/search' ),
				'restNonce'        => wp_create_nonce( 'wp_rest' ),
				'linkPickerTitle'  => __( 'Link to existing content', 'my-slider-pro' ),
				'linkPickerPlaceholder' => __( 'Search pages and posts…', 'my-slider-pro' ),
				'linkPickerSearching' => __( 'Searching…', 'my-slider-pro' ),
				'linkPickerNoResults' => __( 'No matches found.', 'my-slider-pro' ),
				'linkPickerError'  => __( 'Search failed. Check your connection and try again.', 'my-slider-pro' ),
				'linkPickerHint'   => __( 'Or paste any external URL in the field.', 'my-slider-pro' ),
				'removeText'       => __( 'Remove', 'my-slider-pro' ),
				'replaceImageText' => __( 'Replace', 'my-slider-pro' ),
				'maxLayersPerType' => SliderPostType::MAX_LAYERS_PER_TYPE,
				'layerLimitText'   => sprintf(
					/* translators: %d: maximum number of layers of one type per slide. */
					__( 'You can add up to %d layers of each type per slide.', 'my-slider-pro' ),
					SliderPostType::MAX_LAYERS_PER_TYPE
				),
				'colorLabel'       => __( 'Color', 'my-slider-pro' ),
				'firstColorLabel'  => __( 'First color', 'my-slider-pro' ),
				'thumbnailFrameTitle' => __( 'Choose slider thumbnail', 'my-slider-pro' ),
				'thumbnailFrameButton' => __( 'Use as thumbnail', 'my-slider-pro' ),
				'extraLayersLegend' => __( 'Additional overlay layers', 'my-slider-pro' ),
				'addHeadingText'   => __( 'Add heading', 'my-slider-pro' ),
				'addDescriptionText' => __( 'Add description', 'my-slider-pro' ),
				'addButtonLayerText' => __( 'Add button', 'my-slider-pro' ),
				'addImageLayerText' => __( 'Add image', 'my-slider-pro' ),
				'imageFallback'    => __( 'Image', 'my-slider-pro' ),
				'removeLabel'      => __( 'Remove %1$s; position %2$d of %3$d', 'my-slider-pro' ),
				'moveEarlierLabel' => __( 'Move %1$s earlier; position %2$d of %3$d', 'my-slider-pro' ),
				'moveLaterLabel'   => __( 'Move %1$s later; position %2$d of %3$d', 'my-slider-pro' ),
				'emptyText'        => __( 'No slides yet.', 'my-slider-pro' ),
				'countSingular'    => __( '1 slide', 'my-slider-pro' ),
				'countPlural'      => __( '%d slides', 'my-slider-pro' ),
				'movedText'        => __( '%1$s moved to position %2$d of %3$d.', 'my-slider-pro' ),
				'removedText'      => __( '%1$s removed. %2$d images selected.', 'my-slider-pro' ),
				'limitText'        => __( 'Only the first %d images were selected.', 'my-slider-pro' ),
				'copyText'         => __( 'Copy', 'my-slider-pro' ),
				'copiedText'       => __( 'Copied!', 'my-slider-pro' ),
				'copyFailedText'   => __( 'Copy failed', 'my-slider-pro' ),
				'previewPreviousLabel' => __( 'Show previous slide', 'my-slider-pro' ),
				'previewNextLabel' => __( 'Show next slide', 'my-slider-pro' ),
				'slideContentLabel' => __( 'Slide content', 'my-slider-pro' ),
				'headingLabel'    => __( 'Heading', 'my-slider-pro' ),
				'headingLinkLabel' => __( 'Heading link', 'my-slider-pro' ),
				'descriptionLabel' => __( 'Description', 'my-slider-pro' ),
				'descriptionLinkLabel' => __( 'Description link', 'my-slider-pro' ),
				'buttonLabel'     => __( 'Button label', 'my-slider-pro' ),
				'buttonLinkLabel' => __( 'Button link', 'my-slider-pro' ),
				'imageLayerLabel' => __( 'Image', 'my-slider-pro' ),
				'imageLayerUrlLabel' => __( 'Image layer URL', 'my-slider-pro' ),
				'imageLayerAltLabel' => __( 'Image layer alt text', 'my-slider-pro' ),
				'imageLayerChooseLabel' => __( 'Add image layer', 'my-slider-pro' ),
				'imageLayerChangeLabel' => __( 'Change image layer', 'my-slider-pro' ),
				'imageLayerFrameTitle' => __( 'Choose image layer', 'my-slider-pro' ),
				'imageLayerFrameButton' => __( 'Use image layer', 'my-slider-pro' ),
				'imageLinkLabel'  => __( 'Image layer link', 'my-slider-pro' ),
				'imageFocusLabel' => __( 'Desktop image focus', 'my-slider-pro' ),
				'mobileImageFocusLabel' => __( 'Mobile image focus', 'my-slider-pro' ),
				'newTabLabel'     => __( 'Open button link in a new tab', 'my-slider-pro' ),
				'layerPositionsLabel' => __( 'Layer positions', 'my-slider-pro' ),
				'desktopTextPositionLabel' => __( 'Desktop text position', 'my-slider-pro' ),
				'desktopHeadingPositionLabel' => __( 'Desktop heading position', 'my-slider-pro' ),
				'desktopDescriptionPositionLabel' => __( 'Desktop description position', 'my-slider-pro' ),
				'desktopButtonPositionLabel' => __( 'Desktop button position', 'my-slider-pro' ),
				'desktopImagePositionLabel' => __( 'Desktop image position', 'my-slider-pro' ),
				'tabletTextPositionLabel' => __( 'Tablet text position', 'my-slider-pro' ),
				'tabletHeadingPositionLabel' => __( 'Tablet heading position', 'my-slider-pro' ),
				'tabletDescriptionPositionLabel' => __( 'Tablet description position', 'my-slider-pro' ),
				'tabletButtonPositionLabel' => __( 'Tablet button position', 'my-slider-pro' ),
				'tabletImagePositionLabel' => __( 'Tablet image position', 'my-slider-pro' ),
				'mobileTextPositionLabel' => __( 'Phone text position', 'my-slider-pro' ),
				'mobileHeadingPositionLabel' => __( 'Phone heading position', 'my-slider-pro' ),
				'mobileDescriptionPositionLabel' => __( 'Phone description position', 'my-slider-pro' ),
				'mobileButtonPositionLabel' => __( 'Phone button position', 'my-slider-pro' ),
				'mobileImagePositionLabel' => __( 'Phone image position', 'my-slider-pro' ),
				'layerPositionsHelp' => __( 'Choose a preset or drag each layer in Slider Preview. Desktop, Tablet, and Phone positions are saved independently.', 'my-slider-pro' ),
				'textLayerDragLabel' => __( 'Text layer. Drag to move, or use arrow keys to nudge.', 'my-slider-pro' ),
				'headingLayerDragLabel' => __( 'Heading layer. Drag to move, or use arrow keys to nudge.', 'my-slider-pro' ),
				'descriptionLayerDragLabel' => __( 'Description layer. Drag to move, or use arrow keys to nudge.', 'my-slider-pro' ),
				'buttonLayerDragLabel' => __( 'Button layer. Drag to move, or use arrow keys to nudge.', 'my-slider-pro' ),
				'imageLayerDragLabel' => __( 'Image layer. Drag to move, or use arrow keys to nudge.', 'my-slider-pro' ),
				'layerEditorLabel' => __( 'Layer editor', 'my-slider-pro' ),
				'layersLabel' => __( 'Layers', 'my-slider-pro' ),
				'slideLayersLabel' => __( 'Slide layers', 'my-slider-pro' ),
				'textLayerLabel' => __( 'Text', 'my-slider-pro' ),
				'headingLayerLabel' => __( 'Heading', 'my-slider-pro' ),
				'descriptionLayerLabel' => __( 'Text', 'my-slider-pro' ),
				'buttonLayerLabel' => __( 'Button', 'my-slider-pro' ),
				'backgroundLayerLabel' => __( 'Background', 'my-slider-pro' ),
				'backgroundLayerHint' => __( 'Slide background — locked and always at the back. Edit it in Background settings.', 'my-slider-pro' ),
				'lockedText'       => __( 'Locked', 'my-slider-pro' ),
				'showOverlayLabel' => __( 'Show guides', 'my-slider-pro' ),
				'hideOverlayLabel' => __( 'Hide guides', 'my-slider-pro' ),
				'layerMovedText' => __( '%1$s moved to %2$d%% horizontal and %3$d%% vertical.', 'my-slider-pro' ),
				'customPositionLabel' => __( 'Custom (dragged)', 'my-slider-pro' ),
				'layerPositionOptions' => array(
					array( '5,12', __( 'Top left', 'my-slider-pro' ) ),
					array( '50,12', __( 'Top center', 'my-slider-pro' ) ),
					array( '95,12', __( 'Top right', 'my-slider-pro' ) ),
					array( '5,50', __( 'Middle left', 'my-slider-pro' ) ),
					array( '50,50', __( 'Middle center', 'my-slider-pro' ) ),
					array( '95,50', __( 'Middle right', 'my-slider-pro' ) ),
					array( '5,82', __( 'Bottom left', 'my-slider-pro' ) ),
					array( '50,82', __( 'Bottom center', 'my-slider-pro' ) ),
					array( '95,82', __( 'Bottom right', 'my-slider-pro' ) ),
				),
				'maxImages'        => SliderPostType::MAX_IMAGES,
			)
		);
	}

	/**
	 * Render the slider overview and useful empty state.
	 *
	 * @return void
	 */
	public static function render_overview(): void {
		self::authorize();

		$current_page = self::requested_page_number();
		$query_args   = array(
			'post_type'      => SliderPostType::POST_TYPE,
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'posts_per_page' => self::SLIDERS_PER_PAGE + 1,
			'offset'         => ( $current_page - 1 ) * self::SLIDERS_PER_PAGE,
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'perm'           => 'readable',
			'no_found_rows'  => true,
		);

		if ( ! current_user_can( 'edit_others_posts' ) ) {
			$query_args['author'] = get_current_user_id();
		}

		$sliders = get_posts( $query_args );
		$sliders = array_values(
			array_filter(
				$sliders,
				static function ( $slider ): bool {
					return isset( $slider->ID ) && current_user_can( 'edit_post', (int) $slider->ID );
				}
			)
		);
		$has_next  = count( $sliders ) > self::SLIDERS_PER_PAGE;
		$sliders = array_slice( $sliders, 0, self::SLIDERS_PER_PAGE );
		?>
		<div class="wrap my-slider-pro-admin">
			<?php self::render_notice(); ?>
			<header class="psp-page-header psp-hero">
				<?php self::render_hero_glyph(); ?>
				<div class="psp-hero-copy">
					<div class="psp-title-row">
						<h1 id="psp-page-title"><?php echo esc_html__( 'Sliders', 'my-slider-pro' ); ?></h1>
						<span class="psp-version-inline"><?php echo esc_html( MY_SLIDER_PRO_NAME . ' v' . MY_SLIDER_PRO_VERSION ); ?></span>
					</div>
					<p><?php echo esc_html__( 'Create responsive image sliders from your WordPress Media Library. Export a slider as a portable ZIP, or import one to recreate it here.', 'my-slider-pro' ); ?></p>
				</div>
			</header>

			<section class="psp-section" aria-labelledby="psp-sliders-title">
				<div class="psp-list-summary">
					<h2 id="psp-sliders-title" class="screen-reader-text"><?php echo esc_html__( 'Slider list', 'my-slider-pro' ); ?></h2>
					<strong><?php echo esc_html__( 'All sliders', 'my-slider-pro' ); ?></strong>
					<span aria-label="<?php echo esc_attr__( 'Slider count', 'my-slider-pro' ); ?>"><?php echo esc_html( '(' . (string) count( $sliders ) . ')' ); ?></span>
					<div class="psp-list-actions">
						<a class="page-title-action" href="<?php echo esc_url( self::get_editor_url() ); ?>"><?php echo esc_html__( 'Add New Slider', 'my-slider-pro' ); ?></a>
						<form class="psp-import-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="<?php echo esc_attr( self::IMPORT_ACTION ); ?>" />
							<?php wp_nonce_field( self::IMPORT_ACTION, 'my_slider_pro_nonce' ); ?>
							<label class="screen-reader-text" for="psp-import-file"><?php echo esc_html__( 'Slider export file (.zip)', 'my-slider-pro' ); ?></label>
							<input id="psp-import-file" class="psp-import-file" type="file" name="my_slider_pro_import" accept=".zip,application/zip" required />
							<label class="button psp-import-choose" for="psp-import-file"><span class="dashicons dashicons-upload" aria-hidden="true"></span><?php echo esc_html__( 'Choose file', 'my-slider-pro' ); ?></label>
							<span class="psp-import-filename" data-empty="<?php echo esc_attr__( 'No file chosen', 'my-slider-pro' ); ?>"><?php echo esc_html__( 'No file chosen', 'my-slider-pro' ); ?></span>
							<button type="submit" class="button psp-import-submit" disabled><?php echo esc_html__( 'Import', 'my-slider-pro' ); ?></button>
						</form>
						<?php Settings::render_button(); ?>
					</div>
				</div>

				<?php if ( empty( $sliders ) ) : ?>
					<div class="psp-empty-state">
						<span class="dashicons dashicons-format-slider" aria-hidden="true"></span>
						<h3><?php echo esc_html__( 'Create your first slider', 'my-slider-pro' ); ?></h3>
						<p><?php echo esc_html__( 'Select images, choose a layout, then paste the generated shortcode into any page or post.', 'my-slider-pro' ); ?></p>
						<a class="button button-primary" href="<?php echo esc_url( self::get_editor_url() ); ?>">
							<?php echo esc_html__( 'Add Slider', 'my-slider-pro' ); ?>
						</a>
					</div>
				<?php else : ?>
					<div class="psp-slider-cards">
						<?php foreach ( $sliders as $slider ) : ?>
							<?php self::render_slider_card( $slider ); ?>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</section>

			<?php self::render_pagination( $current_page, $has_next ); ?>
			<?php self::render_about_card(); ?>
			<?php Settings::render_modal(); ?>
		</div>
		<?php
	}

	/**
	 * Render the About / Support open-source footer card on the overview.
	 *
	 * @return void
	 */
	private static function render_about_card(): void {
		$links = array(
			array( 'https://github.com/AminudinMurad/my-slider-pro', 'dashicons-editor-code', __( 'GitHub', 'my-slider-pro' ) ),
			array( 'https://github.com/sponsors/aminudinmurad', 'dashicons-heart', __( 'GitHub Sponsors', 'my-slider-pro' ) ),
			array( 'https://ko-fi.com/aminudinmurad', 'dashicons-coffee', __( 'Ko-fi', 'my-slider-pro' ) ),
			array( 'https://www.paypal.com/paypalme/aminudinmurad', 'dashicons-money-alt', __( 'PayPal', 'my-slider-pro' ) ),
		);
		?>
		<section class="psp-about-card" aria-labelledby="psp-about-title">
			<div class="psp-about-info">
				<h2 id="psp-about-title"><?php echo esc_html( MY_SLIDER_PRO_NAME ); ?></h2>
				<p><?php echo esc_html__( 'Fast, responsive, accessible sliders for WordPress.', 'my-slider-pro' ); ?></p>
				<span class="psp-about-meta"><?php echo esc_html( sprintf( 'v%s', MY_SLIDER_PRO_VERSION ) ); ?> &middot; <?php echo esc_html__( 'open source', 'my-slider-pro' ); ?> &middot; GPL-3.0 &middot; &copy; <?php echo esc_html__( '2026 Aminudin Murad', 'my-slider-pro' ); ?></span>
			</div>
			<div class="psp-about-support">
				<span class="psp-about-support-label"><?php echo esc_html__( 'Support open-source development', 'my-slider-pro' ); ?></span>
				<div class="psp-about-links">
					<?php foreach ( $links as $link ) : ?>
						<a class="psp-about-link" href="<?php echo esc_url( $link[0] ); ?>" target="_blank" rel="noopener noreferrer"><span class="dashicons <?php echo esc_attr( $link[1] ); ?>" aria-hidden="true"></span><?php echo esc_html( $link[2] ); ?></a>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
		<?php
	}

	/**
	 * Render the create/edit slider form.
	 *
	 * @return void
	 */
	public static function render_editor(): void {
		self::authorize();

		$slider_id = self::requested_slider_id();
		$slider    = null;

		if ( $slider_id > 0 ) {
			$slider = get_post( $slider_id );

			if (
				! $slider ||
				SliderPostType::POST_TYPE !== $slider->post_type ||
				! current_user_can( 'edit_post', $slider_id )
			) {
				wp_die( esc_html__( 'You do not have permission to edit this slider.', 'my-slider-pro' ) );
			}

			if ( ! self::is_editable_status( $slider ) ) {
				wp_die( esc_html__( 'This slider is in the trash and cannot be edited.', 'my-slider-pro' ) );
			}
		}

		$image_ids = $slider ? self::sanitize_submitted_image_ids( SliderPostType::get_image_ids( $slider_id ) ) : array();
		$settings  = $slider ? SliderPostType::get_settings( $slider_id ) : SliderPostType::sanitize_settings( array() );
		$slide_content = $slider ? SliderPostType::get_slide_content( $slider_id, $image_ids ) : array();
		$title     = $slider ? get_the_title( $slider_id ) : '';
		?>
		<div class="wrap my-slider-pro-admin psp-editor">
			<header class="psp-editor-header psp-hero">
				<?php self::render_hero_glyph(); ?>
				<div class="psp-hero-copy">
					<a class="psp-back-link" href="<?php echo esc_url( self::get_overview_url() ); ?>">&larr; <?php echo esc_html__( 'All sliders', 'my-slider-pro' ); ?></a>
					<div class="psp-title-row">
						<h1><?php echo $slider ? esc_html__( 'Edit Slider', 'my-slider-pro' ) : esc_html__( 'Add New Slider', 'my-slider-pro' ); ?></h1>
						<span class="psp-version-inline"><?php echo esc_html( MY_SLIDER_PRO_NAME . ' v' . MY_SLIDER_PRO_VERSION ); ?></span>
					</div>
				</div>
			</header>

			<form class="psp-editor-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::SAVE_ACTION ); ?>" />
				<input type="hidden" name="slider_id" value="<?php echo esc_attr( (string) $slider_id ); ?>" />
				<input type="hidden" id="my-slider-pro-editor-view" name="editor_view" value="preview" />
				<?php wp_nonce_field( self::SAVE_ACTION, 'my_slider_pro_nonce' ); ?>
				<div class="psp-editor-layout">
					<div class="psp-editor-main">
					<section class="psp-panel psp-slider-items-panel" aria-labelledby="psp-images-title">
						<div class="psp-panel-heading">
							<h2 id="psp-images-title" class="screen-reader-text"><?php echo esc_html__( 'Slides', 'my-slider-pro' ); ?></h2>
							<div class="psp-title-field">
								<label class="psp-title-label" for="my-slider-pro-title"><?php echo esc_html__( 'Slider Name', 'my-slider-pro' ); ?></label>
								<input id="my-slider-pro-title" class="psp-title-input" type="text" name="slider_title" value="<?php echo esc_attr( $title ); ?>" maxlength="160" placeholder="<?php echo esc_attr__( 'Slider Name', 'my-slider-pro' ); ?>" required />
							</div>
							<div class="psp-panel-heading-actions">
								<?php if ( $slider_id > 0 ) : ?>
									<div class="psp-editor-shortcode">
										<span><?php echo esc_html__( 'Shortcode', 'my-slider-pro' ); ?></span>
										<?php self::render_shortcode_copy( $slider_id ); ?>
									</div>
								<?php endif; ?>
								<button type="button" class="button button-primary" id="my-slider-pro-add-images">
									<?php echo esc_html__( 'Add New Slide', 'my-slider-pro' ); ?>
								</button>
								<button type="submit" class="button button-primary button-slider">
									<?php echo $slider ? esc_html__( 'Update Slider', 'my-slider-pro' ) : esc_html__( 'Publish Slider', 'my-slider-pro' ); ?>
								</button>
								<a class="button button-link" href="<?php echo esc_url( self::get_overview_url() ); ?>"><?php echo esc_html__( 'Cancel', 'my-slider-pro' ); ?></a>
							</div>
						</div>
						<div id="psp-manage-panel" class="psp-workspace-panel psp-slide-order-panel" data-psp-panel="manage">
							<p class="psp-media-policy">
								<?php
								printf(
									/* translators: %d: maximum number of images per slider. */
									esc_html__( 'Choose up to %d images. Images inherited from draft or private content remain hidden from the public slider until that content is published.', 'my-slider-pro' ),
									SliderPostType::MAX_IMAGES
								);
								?>
							</p>
							<p id="my-slider-pro-image-count" class="psp-selection-count"></p>
							<ul id="my-slider-pro-images" class="psp-image-picker" aria-labelledby="psp-images-title">
								<?php foreach ( $image_ids as $index => $attachment_id ) : ?>
									<?php self::render_media_item( $attachment_id, $index, count( $image_ids ), $slide_content[ $attachment_id ] ?? array() ); ?>
								<?php endforeach; ?>
							</ul>
							<p id="my-slider-pro-image-status" class="screen-reader-text" role="status" aria-live="polite" aria-atomic="true"></p>
							<p id="my-slider-pro-empty-images" class="psp-media-empty<?php echo empty( $image_ids ) ? '' : ' is-hidden'; ?>">
								<?php echo esc_html__( 'Your slider is empty. Add images from the Media Library to get started.', 'my-slider-pro' ); ?>
							</p>
						</div>

						<div id="psp-preview-panel" class="psp-workspace-panel psp-canvas-panel" data-psp-panel="preview">
							<div class="psp-preview-toolbar" role="toolbar" aria-label="<?php echo esc_attr__( 'Responsive preview size', 'my-slider-pro' ); ?>">
								<div class="psp-canvas-layer-tools" aria-label="<?php echo esc_attr__( 'Add layer', 'my-slider-pro' ); ?>">
									<span class="psp-preview-toolbar-label"><?php echo esc_html__( 'Add layer', 'my-slider-pro' ); ?></span>
									<button type="button" class="psp-layer-tool-button psp-add-extra-layer" data-psp-extra-layer-type="heading"><strong>H</strong><span><?php echo esc_html__( 'Heading', 'my-slider-pro' ); ?></span></button>
									<button type="button" class="psp-layer-tool-button psp-add-extra-layer" data-psp-extra-layer-type="description"><strong>T</strong><span><?php echo esc_html__( 'Text', 'my-slider-pro' ); ?></span></button>
									<button type="button" class="psp-layer-tool-button psp-add-extra-layer" data-psp-extra-layer-type="button"><strong>▭</strong><span><?php echo esc_html__( 'Button', 'my-slider-pro' ); ?></span></button>
									<button type="button" class="psp-layer-tool-button psp-add-extra-layer" data-psp-extra-layer-type="image"><strong>▧</strong><span><?php echo esc_html__( 'Image', 'my-slider-pro' ); ?></span></button>
									<button type="button" class="psp-layer-tool-button psp-set-slide-background"><strong>▦</strong><span><?php echo esc_html__( 'Slide Background', 'my-slider-pro' ); ?></span></button>
								</div>
								<div class="psp-device-tools">
									<span class="psp-preview-toolbar-label"><?php echo esc_html__( 'Preview', 'my-slider-pro' ); ?></span>
									<button type="button" class="button psp-device-button is-active" data-psp-device="desktop" aria-pressed="true"><span class="dashicons dashicons-desktop" aria-hidden="true"></span><?php echo esc_html__( 'Desktop', 'my-slider-pro' ); ?></button>
									<button type="button" class="button psp-device-button" data-psp-device="tablet" aria-pressed="false"><span class="dashicons dashicons-tablet" aria-hidden="true"></span><?php echo esc_html__( 'Tablet', 'my-slider-pro' ); ?></button>
									<button type="button" class="button psp-device-button" data-psp-device="phone" aria-pressed="false"><span class="dashicons dashicons-smartphone" aria-hidden="true"></span><?php echo esc_html__( 'Phone', 'my-slider-pro' ); ?></button>
								</div>
							</div>
							<div class="psp-layer-workbench">
								<div class="psp-layer-canvas-column">
									<p id="psp-layer-preview-help" class="psp-layer-preview-help"><?php echo esc_html__( 'Select a layer on the canvas, drag it into position, or use the inspector for precise placement. Each device saves independently.', 'my-slider-pro' ); ?></p>
									<div class="psp-preview-stage">
										<div id="my-slider-pro-preview-viewport" class="psp-preview-viewport is-desktop">
											<div id="my-slider-pro-preview" class="psp-slider-preview" aria-live="polite"></div>
										</div>
									</div>
								</div>
							</div>
							<p id="my-slider-pro-empty-preview" class="psp-media-empty<?php echo empty( $image_ids ) ? '' : ' is-hidden'; ?>"><?php echo esc_html__( 'Add images to see a preview.', 'my-slider-pro' ); ?></p>
						</div>
					</section>
					<section class="psp-panel psp-slide-layers-panel" aria-labelledby="psp-slide-layers-title">
						<div class="psp-layers-columns">
							<div class="psp-layers-col psp-layers-col-list">
								<h2 id="psp-slide-layers-title"><?php echo esc_html__( 'Layers', 'my-slider-pro' ); ?></h2>
								<p class="psp-panel-intro"><?php echo esc_html__( 'Drag to set the front-to-back order for the selected slide. The top layer shows in front.', 'my-slider-pro' ); ?></p>
								<div class="psp-layer-strip" data-psp-slide-layers aria-label="<?php echo esc_attr__( 'Slide layers', 'my-slider-pro' ); ?>">
									<div class="psp-layer-strip-items"></div>
								</div>
								<p id="psp-slide-layers-empty" class="psp-slide-layers-empty<?php echo empty( $image_ids ) ? '' : ' is-hidden'; ?>"><?php echo esc_html__( 'Add a slide to arrange its layers.', 'my-slider-pro' ); ?></p>
							</div>
							<div class="psp-layers-col psp-background-settings" id="psp-background-settings" aria-labelledby="psp-background-settings-title">
								<h2 id="psp-background-settings-title"><?php echo esc_html__( 'Background settings', 'my-slider-pro' ); ?></h2>
								<p class="psp-panel-intro"><?php echo esc_html__( 'Background image, sizing, and overlay for the selected slide.', 'my-slider-pro' ); ?></p>
								<div class="psp-bg-group">
									<p class="psp-group-label"><span class="dashicons dashicons-format-image" aria-hidden="true"></span><?php echo esc_html__( 'Image', 'my-slider-pro' ); ?></p>
									<div class="psp-bg-image-row">
										<span class="psp-bg-thumb"><img id="psp-bg-thumb-img" src="" alt="" /><span class="psp-bg-thumb-empty dashicons dashicons-format-image" aria-hidden="true"></span></span>
										<button type="button" class="button psp-replace-background"><span class="dashicons dashicons-update" aria-hidden="true"></span><?php echo esc_html__( 'Replace background', 'my-slider-pro' ); ?></button>
									</div>
									<p class="psp-bg-thumb-name" id="psp-bg-thumb-name"></p>
									<div class="psp-layer-slide-section">
										<div class="psp-bg-fill-row">
											<label class="psp-seg-field"><span><?php echo esc_html__( 'Fill mode', 'my-slider-pro' ); ?></span><?php self::render_fill_segment(); ?></label>
											<label class="psp-seg-field"><span><?php echo esc_html__( 'Position', 'my-slider-pro' ); ?></span><?php self::render_position_segment(); ?></label>
										</div>
									</div>
								</div>
								<div class="psp-bg-group">
									<p class="psp-group-label"><span class="dashicons dashicons-art" aria-hidden="true"></span><?php echo esc_html__( 'Overlay', 'my-slider-pro' ); ?></p>
									<div class="psp-layer-slide-section">
										<label class="psp-seg-field psp-inspector-wide"><span><?php echo esc_html__( 'Type', 'my-slider-pro' ); ?></span>
											<span class="psp-seg psp-seg-labelled" role="group" aria-label="<?php echo esc_attr__( 'Overlay type', 'my-slider-pro' ); ?>">
												<input type="hidden" data-psp-slide-key="overlay_type" value="none" />
												<button type="button" data-psp-seg-value="none"><span class="psp-seg-label"><?php echo esc_html__( 'None', 'my-slider-pro' ); ?></span></button>
												<button type="button" data-psp-seg-value="solid"><span class="psp-seg-label"><?php echo esc_html__( 'Solid', 'my-slider-pro' ); ?></span></button>
												<button type="button" data-psp-seg-value="gradient"><span class="psp-seg-label"><?php echo esc_html__( 'Gradient', 'my-slider-pro' ); ?></span></button>
											</span>
										</label>
										<div class="psp-overlay-fields psp-inspector-wide" data-psp-overlay-fields>
											<label class="psp-overlay-color-field"><span id="psp-overlay-color-label"><?php echo esc_html__( 'Color', 'my-slider-pro' ); ?></span><input type="color" value="#08101f" data-psp-slide-key="overlay_color" /></label>
											<label class="psp-overlay-gradient-only"><span><?php echo esc_html__( 'Second color', 'my-slider-pro' ); ?></span><input type="color" value="#000000" data-psp-slide-key="overlay_color2" /></label>
											<label class="psp-overlay-opacity-field psp-inspector-wide"><span><?php echo esc_html__( 'Opacity', 'my-slider-pro' ); ?></span><span class="psp-range-row"><input type="range" min="0" max="100" step="1" value="50" data-psp-slide-key="overlay_opacity" /><output class="psp-range-out">50%</output></span></label>
											<label class="psp-overlay-gradient-only psp-inspector-wide"><span><?php echo esc_html__( 'Direction', 'my-slider-pro' ); ?></span><select data-psp-slide-key="overlay_direction"><option value="to bottom"><?php echo esc_html__( 'Top to bottom', 'my-slider-pro' ); ?></option><option value="to top"><?php echo esc_html__( 'Bottom to top', 'my-slider-pro' ); ?></option><option value="to right"><?php echo esc_html__( 'Left to right', 'my-slider-pro' ); ?></option><option value="to left"><?php echo esc_html__( 'Right to left', 'my-slider-pro' ); ?></option><option value="to bottom right"><?php echo esc_html__( 'Diagonal down-right', 'my-slider-pro' ); ?></option><option value="to bottom left"><?php echo esc_html__( 'Diagonal down-left', 'my-slider-pro' ); ?></option></select></label>
										</div>
									</div>
								</div>
								<p class="psp-layer-inspector-note"><?php echo esc_html__( 'Position sets the crop anchor per device.', 'my-slider-pro' ); ?></p>
							</div>
						</div>
					</section>
					<section class="psp-panel psp-slider-settings-panel" aria-labelledby="psp-layout-title">
						<h2 id="psp-layout-title"><?php echo esc_html__( 'Slider Settings', 'my-slider-pro' ); ?></h2>
						<p class="psp-panel-intro"><?php echo esc_html__( 'Set the responsive frame, content placement, and slide behaviour.', 'my-slider-pro' ); ?></p>
						<?php self::render_settings_fields( $settings ); ?>
					</section>
					</div>
					<aside class="psp-editor-sidebar" aria-label="<?php echo esc_attr__( 'Layer inspector', 'my-slider-pro' ); ?>">
						<div class="psp-inspector-body">
							<div id="psp-inspector-panel-layer" class="psp-inspector-panel" data-psp-inspector-panel="layer">
								<div class="psp-layer-inspector" aria-labelledby="psp-layer-inspector-title">
									<div class="psp-layer-inspector-head">
										<h3 id="psp-layer-inspector-title" class="screen-reader-text"><?php echo esc_html__( 'Layer Inspector', 'my-slider-pro' ); ?></h3>
										<p class="psp-layer-name" id="psp-layer-inspector-name"><?php echo esc_html__( 'Heading layer', 'my-slider-pro' ); ?></p>
										<span class="psp-layer-device-pill" id="psp-layer-inspector-device"><?php echo esc_html__( 'Desktop', 'my-slider-pro' ); ?></span>
										<button type="button" class="psp-delete-layer-btn psp-delete-layer" aria-label="<?php echo esc_attr__( 'Delete layer', 'my-slider-pro' ); ?>"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button>
									</div>
									<div class="psp-layer-position-row">
										<div class="psp-layer-axis-fields">
											<label><span><?php echo esc_html__( 'X position', 'my-slider-pro' ); ?></span><input id="psp-layer-inspector-x" type="number" min="5" max="95" step="1" value="5" /><small>%</small></label>
											<label><span><?php echo esc_html__( 'Y position', 'my-slider-pro' ); ?></span><input id="psp-layer-inspector-y" type="number" min="5" max="95" step="1" value="50" /><small>%</small></label>
										</div>
										<div class="psp-layer-anchor-col">
											<span class="psp-layer-anchor-label"><?php echo esc_html__( 'Anchor position', 'my-slider-pro' ); ?></span>
											<div class="psp-layer-anchor-grid" role="group" aria-label="<?php echo esc_attr__( 'Layer anchor position', 'my-slider-pro' ); ?>">
												<?php foreach ( array( '5,12', '50,12', '95,12', '5,50', '50,50', '95,50', '5,82', '50,82', '95,82' ) as $anchor ) : ?>
													<button type="button" data-psp-layer-anchor="<?php echo esc_attr( $anchor ); ?>" aria-label="<?php echo esc_attr( str_replace( ',', ', ', $anchor ) ); ?>"><span></span></button>
												<?php endforeach; ?>
											</div>
										</div>
									</div>
									<div class="psp-layer-responsive" data-psp-layer-responsive>
										<span class="psp-layer-responsive-label"><?php echo esc_html__( 'Responsive', 'my-slider-pro' ); ?></span>
										<label class="psp-inspector-check"><input type="checkbox" data-psp-link-key="size" /> <span><?php echo esc_html__( 'Link size across devices', 'my-slider-pro' ); ?></span></label>
										<label class="psp-inspector-check"><input type="checkbox" data-psp-link-key="pos" /> <span><?php echo esc_html__( 'Link position across devices', 'my-slider-pro' ); ?></span></label>
										<p class="psp-layer-responsive-help"><?php echo esc_html__( 'When unlinked, size is set separately for Desktop, Tablet, and Phone. Link position to reuse the desktop placement on every device.', 'my-slider-pro' ); ?></p>
									</div>
									<div class="psp-layer-style-section" data-psp-style-section="heading">
										<h4><?php echo esc_html__( 'Heading style', 'my-slider-pro' ); ?></h4>
										<label><span><?php echo esc_html__( 'Text color', 'my-slider-pro' ); ?></span><input type="color" value="#ffffff" data-psp-style-key="text_color" /></label>
										<label><span><?php echo esc_html__( 'Font family', 'my-slider-pro' ); ?></span><?php self::render_font_select( 'font_family' ); ?></label>
										<label><span><?php echo esc_html__( 'Font style', 'my-slider-pro' ); ?></span><?php self::render_font_style_select( 'heading_font_style' ); ?></label>
										<label><span><?php echo esc_html__( 'Heading size', 'my-slider-pro' ); ?></span><span class="psp-style-number"><input type="number" min="24" max="96" value="64" data-psp-style-key="heading_size" /><small>px</small></span></label>
										<label><span><?php echo esc_html__( 'Opacity', 'my-slider-pro' ); ?></span><span class="psp-style-number"><input type="number" min="10" max="100" value="100" data-psp-style-key="heading_opacity" /><small>%</small></span></label>
										<label><span><?php echo esc_html__( 'Alignment', 'my-slider-pro' ); ?></span><?php self::render_align_segment( 'text_align' ); ?></label>
										<label class="psp-inspector-wide"><span><?php echo esc_html__( 'Link', 'my-slider-pro' ); ?></span><?php self::render_link_field( 'heading_link_url' ); ?></label>
										<label class="psp-inspector-wide psp-inspector-check"><input type="checkbox" data-psp-content-toggle="heading_target" /> <span><?php echo esc_html__( 'Open link in a new tab', 'my-slider-pro' ); ?></span></label>
									</div>
									<div class="psp-layer-style-section" data-psp-style-section="description" hidden>
										<h4><?php echo esc_html__( 'Description style', 'my-slider-pro' ); ?></h4>
										<label><span><?php echo esc_html__( 'Text color', 'my-slider-pro' ); ?></span><input type="color" value="#ffffff" data-psp-style-key="description_color" /></label>
										<label><span><?php echo esc_html__( 'Font family', 'my-slider-pro' ); ?></span><?php self::render_font_select( 'description_font_family' ); ?></label>
										<label><span><?php echo esc_html__( 'Font style', 'my-slider-pro' ); ?></span><?php self::render_font_style_select( 'description_font_style' ); ?></label>
										<label><span><?php echo esc_html__( 'Text size', 'my-slider-pro' ); ?></span><span class="psp-style-number"><input type="number" min="12" max="36" value="20" data-psp-style-key="description_size" /><small>px</small></span></label>
										<label><span><?php echo esc_html__( 'Opacity', 'my-slider-pro' ); ?></span><span class="psp-style-number"><input type="number" min="10" max="100" value="100" data-psp-style-key="description_opacity" /><small>%</small></span></label>
										<label><span><?php echo esc_html__( 'Alignment', 'my-slider-pro' ); ?></span><?php self::render_align_segment( 'description_align' ); ?></label>
										<label class="psp-inspector-wide"><span><?php echo esc_html__( 'Link', 'my-slider-pro' ); ?></span><?php self::render_link_field( 'description_link_url' ); ?></label>
										<label class="psp-inspector-wide psp-inspector-check"><input type="checkbox" data-psp-content-toggle="description_target" /> <span><?php echo esc_html__( 'Open link in a new tab', 'my-slider-pro' ); ?></span></label>
									</div>
									<div class="psp-layer-style-section" data-psp-style-section="button" hidden>
										<h4><?php echo esc_html__( 'Button style', 'my-slider-pro' ); ?></h4>
						<label><span><?php echo esc_html__( 'Text color', 'my-slider-pro' ); ?></span><input type="color" value="#172033" data-psp-style-key="button_text_color" /></label>
						<label><span><?php echo esc_html__( 'Background', 'my-slider-pro' ); ?></span><input type="color" value="#ffffff" data-psp-style-key="button_background" /></label>
						<label><span><?php echo esc_html__( 'Font family', 'my-slider-pro' ); ?></span><?php self::render_font_select( 'button_font_family' ); ?></label>
										<label><span><?php echo esc_html__( 'Font style', 'my-slider-pro' ); ?></span><?php self::render_font_style_select( 'button_font_style' ); ?></label>
						<label><span><?php echo esc_html__( 'Text size', 'my-slider-pro' ); ?></span><span class="psp-style-number"><input type="number" min="12" max="36" value="16" data-psp-style-key="button_font_size" /><small>px</small></span></label>
						<label><span><?php echo esc_html__( 'Opacity', 'my-slider-pro' ); ?></span><span class="psp-style-number"><input type="number" min="10" max="100" value="100" data-psp-style-key="button_opacity" /><small>%</small></span></label>
						<label><span><?php echo esc_html__( 'Corner radius', 'my-slider-pro' ); ?></span><span class="psp-style-number"><input type="number" min="0" max="50" value="4" data-psp-style-key="button_radius" /><small>px</small></span></label>
										<label><span><?php echo esc_html__( 'Horizontal padding', 'my-slider-pro' ); ?></span><span class="psp-style-number"><input type="number" min="8" max="48" value="20" data-psp-style-key="button_padding_x" /><small>px</small></span></label>
										<label><span><?php echo esc_html__( 'Vertical padding', 'my-slider-pro' ); ?></span><span class="psp-style-number"><input type="number" min="6" max="30" value="12" data-psp-style-key="button_padding_y" /><small>px</small></span></label>
										<label class="psp-inspector-wide"><span><?php echo esc_html__( 'Link', 'my-slider-pro' ); ?></span><?php self::render_link_field( 'button_url' ); ?></label>
										<label class="psp-inspector-wide psp-inspector-check"><input type="checkbox" data-psp-content-toggle="button_target" /> <span><?php echo esc_html__( 'Open link in a new tab', 'my-slider-pro' ); ?></span></label>
									</div>
									<div class="psp-layer-style-section" data-psp-style-section="image" hidden>
										<h4><?php echo esc_html__( 'Image style', 'my-slider-pro' ); ?></h4>
										<label><span><?php echo esc_html__( 'Width', 'my-slider-pro' ); ?></span><span class="psp-style-number"><input type="number" min="40" max="800" value="220" data-psp-style-key="image_width" /><small>px</small></span></label>
										<label><span><?php echo esc_html__( 'Opacity', 'my-slider-pro' ); ?></span><span class="psp-style-number"><input type="number" min="10" max="100" value="100" data-psp-style-key="image_opacity" /><small>%</small></span></label>
										<label class="psp-inspector-wide"><span><?php echo esc_html__( 'Image URL', 'my-slider-pro' ); ?></span><span class="psp-inspector-image-field"><input type="url" placeholder="https://" data-psp-style-key="image_layer_url" /><button type="button" class="button psp-inspector-image-pick"><?php echo esc_html__( 'Choose', 'my-slider-pro' ); ?></button></span></label>
										<label class="psp-inspector-wide"><span><?php echo esc_html__( 'Alt text', 'my-slider-pro' ); ?></span><input type="text" data-psp-style-key="image_layer_alt" /></label>
										<label class="psp-inspector-wide"><span><?php echo esc_html__( 'Link', 'my-slider-pro' ); ?></span><?php self::render_link_field( 'image_link_url' ); ?></label>
										<label class="psp-inspector-wide psp-inspector-check"><input type="checkbox" data-psp-content-toggle="image_target" /> <span><?php echo esc_html__( 'Open link in a new tab', 'my-slider-pro' ); ?></span></label>
									</div>
									<div class="psp-layer-collapsible">
										<div class="psp-accordion-toggle psp-accordion-static"><span class="dashicons dashicons-controls-play psp-acc-lead" aria-hidden="true"></span><?php echo esc_html__( 'Animation', 'my-slider-pro' ); ?></div>
										<div class="psp-accordion-body">
											<div class="psp-layer-animation-section">
												<label><span><?php echo esc_html__( 'Type', 'my-slider-pro' ); ?></span><select data-psp-animation-key="animation"><option value="none"><?php echo esc_html__( 'None', 'my-slider-pro' ); ?></option><option value="fade"><?php echo esc_html__( 'Fade', 'my-slider-pro' ); ?></option><option value="slide-up"><?php echo esc_html__( 'Slide up', 'my-slider-pro' ); ?></option><option value="slide-down"><?php echo esc_html__( 'Slide down', 'my-slider-pro' ); ?></option><option value="slide-left"><?php echo esc_html__( 'Slide left', 'my-slider-pro' ); ?></option><option value="slide-right"><?php echo esc_html__( 'Slide right', 'my-slider-pro' ); ?></option><option value="zoom"><?php echo esc_html__( 'Zoom', 'my-slider-pro' ); ?></option></select></label>
												<label><span><?php echo esc_html__( 'Delay', 'my-slider-pro' ); ?></span><span class="psp-style-number"><input type="number" min="0" max="5000" step="1" value="0" data-psp-animation-key="animation_delay" /><small>ms</small></span></label>
												<label><span><?php echo esc_html__( 'Duration', 'my-slider-pro' ); ?></span><span class="psp-style-number"><input type="number" min="100" max="5000" step="1" value="600" data-psp-animation-key="animation_duration" /><small>ms</small></span></label>
												<label><span><?php echo esc_html__( 'Easing', 'my-slider-pro' ); ?></span><select data-psp-animation-key="animation_easing"><option value="linear"><?php echo esc_html__( 'Linear', 'my-slider-pro' ); ?></option><option value="ease"><?php echo esc_html__( 'Ease', 'my-slider-pro' ); ?></option><option value="ease-in"><?php echo esc_html__( 'Ease in', 'my-slider-pro' ); ?></option><option value="ease-out"><?php echo esc_html__( 'Ease out', 'my-slider-pro' ); ?></option><option value="ease-in-out"><?php echo esc_html__( 'Ease in-out', 'my-slider-pro' ); ?></option></select></label>
											</div>
										</div>
									</div>
									<p class="psp-layer-inspector-note"><?php echo esc_html__( 'Coordinates are percentages of the current device canvas.', 'my-slider-pro' ); ?></p>
								</div>
							</div>
							<div id="psp-inspector-panel-slide" class="psp-inspector-panel psp-slide-fields-store" data-psp-inspector-panel="slide" hidden aria-hidden="true">
								<div id="psp-active-slide-fields" class="psp-active-slide-fields" aria-live="polite"></div>
							</div>
						</div>
					</aside>
				</div>
			</form>
			<?php self::render_about_card(); ?>
		</div>
		<?php
	}

	/**
	 * Handle an authenticated create or update request.
	 *
	 * @return void
	 */
	public static function handle_save(): void {
		self::require_post_request();
		self::authorize();
		check_admin_referer( self::SAVE_ACTION, 'my_slider_pro_nonce' );

		$slider_id = self::posted_integer( 'slider_id' );
		$title      = self::posted_text( 'slider_title' );

		if ( '' === $title ) {
			wp_die( esc_html__( 'Enter a slider name before saving.', 'my-slider-pro' ) );
		}
		if ( self::text_length( $title ) > 160 ) {
			wp_die( esc_html__( 'Slider names cannot be longer than 160 characters.', 'my-slider-pro' ) );
		}
		if ( ! current_user_can( 'publish_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to publish sliders.', 'my-slider-pro' ) );
		}

		$image_ids = isset( $_POST['my_slider_pro_image_ids'] ) ? wp_unslash( $_POST['my_slider_pro_image_ids'] ) : array();
		$image_ids = self::sanitize_submitted_image_ids( $image_ids );

		if ( empty( $image_ids ) ) {
			wp_die( esc_html__( 'Choose at least one valid Media Library image before saving.', 'my-slider-pro' ) );
		}

		if ( $slider_id > 0 ) {
			$slider = get_post( $slider_id );

			if (
				! $slider ||
				SliderPostType::POST_TYPE !== $slider->post_type ||
				! current_user_can( 'edit_post', $slider_id )
			) {
				wp_die( esc_html__( 'You do not have permission to update this slider.', 'my-slider-pro' ) );
			}

			if ( ! self::is_editable_status( $slider ) ) {
				wp_die( esc_html__( 'This slider is in the trash and cannot be edited.', 'my-slider-pro' ) );
			}

			$result = wp_update_post(
				array(
					'ID'         => $slider_id,
					'post_title' => $title,
					'post_status' => 'publish',
				),
				true
			);
		} else {
			$result = wp_insert_post(
				array(
					'post_type'   => SliderPostType::POST_TYPE,
					'post_title'  => $title,
					'post_status' => 'publish',
					'post_author' => get_current_user_id(),
				),
				true
			);
		}

		if ( is_wp_error( $result ) || 0 === $result ) {
			wp_die( esc_html__( 'WordPress could not save this slider. Please try again.', 'my-slider-pro' ) );
		}

		$slider_id = (int) $result;
		SliderPostType::save_meta(
			$slider_id,
			$image_ids,
			array(
				'height'           => self::posted_text( 'slider_height' ),
				'width'            => self::posted_text( 'slider_width' ),
				'max_width'        => self::posted_text( 'slider_max_width' ),
				'tablet_height'    => self::posted_text( 'slider_tablet_height' ),
				'mobile_height'    => self::posted_text( 'slider_mobile_height' ),
				'content_position' => self::posted_text( 'slider_content_position' ),
				'tablet_content_position' => self::posted_text( 'slider_tablet_content_position' ),
				'mobile_content_position' => self::posted_text( 'slider_mobile_content_position' ),
				'tablet_text_width' => self::posted_text( 'slider_tablet_text_width' ),
				'mobile_text_width' => self::posted_text( 'slider_mobile_text_width' ),
				'tablet_button_size' => self::posted_text( 'slider_tablet_button_size' ),
				'mobile_button_size' => self::posted_text( 'slider_mobile_button_size' ),
				'arrows'           => isset( $_POST['slider_arrows'] ),
				'hide_arrows_on_phone' => isset( $_POST['slider_hide_arrows_on_phone'] ),
				'dots'             => isset( $_POST['slider_dots'] ),
				'autoplay'         => isset( $_POST['slider_autoplay'] ),
				'interval'         => self::posted_text( 'slider_interval' ),
				'loop'             => isset( $_POST['slider_loop'] ),
				'pause_on_hover'   => isset( $_POST['slider_pause_on_hover'] ),
			),
			self::posted_slide_content( $image_ids )
		);

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => self::EDITOR_SLUG,
					'slider_id'   => $slider_id,
					'editor_view' => 'manage' === self::posted_text( 'editor_view' ) ? 'manage' : 'preview',
					'saved'       => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle an authenticated request to move a slider to the trash.
	 *
	 * @return void
	 */
	public static function handle_delete(): void {
		self::require_post_request();
		self::authorize();

		$slider_id = self::posted_integer( 'slider_id' );
		check_admin_referer( self::DELETE_ACTION . '_' . $slider_id, 'my_slider_pro_nonce' );

		$slider = get_post( $slider_id );

		if (
			! $slider ||
			SliderPostType::POST_TYPE !== $slider->post_type ||
			! current_user_can( 'delete_post', $slider_id )
		) {
			wp_die( esc_html__( 'You do not have permission to delete this slider.', 'my-slider-pro' ) );
		}

		if ( ! wp_trash_post( $slider_id ) ) {
			wp_die( esc_html__( 'WordPress could not move this slider to the trash. Please try again.', 'my-slider-pro' ) );
		}
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => self::PAGE_SLUG,
					'deleted' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Return the overview URL.
	 *
	 * @return string
	 */
	public static function get_overview_url(): string {
		return add_query_arg( 'page', self::PAGE_SLUG, admin_url( 'admin.php' ) );
	}

	/**
	 * Return the create/edit URL.
	 *
	 * @param int $slider_id Optional slider ID.
	 * @return string
	 */
	public static function get_editor_url( int $slider_id = 0 ): string {
		$args = array( 'page' => self::EDITOR_SLUG );

		if ( $slider_id > 0 ) {
			$args['slider_id'] = $slider_id;
		}

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Render an accessible one-click shortcode copy control.
	 *
	 * @param int $slider_id Slider ID.
	 * @return void
	 */
	private static function render_shortcode_copy( int $slider_id ): void {
		$shortcode = sprintf( '[myslider id="%d"]', $slider_id );
		$copy_label = sprintf(
			/* translators: %s: slider shortcode. */
			__( 'Copy shortcode %s', 'my-slider-pro' ),
			$shortcode
		);
		?>
		<span class="psp-shortcode-copy-wrap">
			<button type="button" class="psp-shortcode-copy" data-shortcode="<?php echo esc_attr( $shortcode ); ?>" aria-label="<?php echo esc_attr( $copy_label ); ?>">
				<code><?php echo esc_html( $shortcode ); ?></code>
				<span class="psp-shortcode-copy-label" aria-hidden="true"><?php echo esc_html__( 'Copy', 'my-slider-pro' ); ?></span>
			</button>
			<span class="screen-reader-text psp-shortcode-copy-status" aria-live="polite"></span>
		</span>
		<?php
	}

	/**
	 * Render one slider row in the overview.
	 *
	 * @param object $slider Slider post object.
	 * @return void
	 */
	private static function render_slider_card( $slider ): void {
		$slider_id = (int) $slider->ID;
		$image_ids  = self::sanitize_submitted_image_ids( SliderPostType::get_image_ids( $slider_id ) );
		$title      = get_the_title( $slider_id );

		if ( '' === $title ) {
			$title = esc_html__( 'Untitled slider', 'my-slider-pro' );
		}

		$thumbnail_id = SliderPostType::get_thumbnail_id( $slider_id );
		$is_custom    = $thumbnail_id > 0 && wp_attachment_is_image( $thumbnail_id );
		$preview_id   = $is_custom ? $thumbnail_id : ( ! empty( $image_ids ) ? (int) $image_ids[0] : 0 );
		$can_edit     = current_user_can( 'edit_post', $slider_id );
		?>
		<div class="psp-slider-card">
			<div class="psp-slider-card-media">
				<?php if ( $preview_id > 0 ) : ?>
					<?php echo wp_get_attachment_image( $preview_id, 'medium', false, array( 'loading' => 'lazy', 'alt' => '', 'class' => 'psp-slider-card-image' ) ); ?>
				<?php else : ?>
					<span class="psp-slider-card-placeholder"><span class="dashicons dashicons-format-image" aria-hidden="true"></span></span>
				<?php endif; ?>
				<span class="psp-slider-card-badge<?php echo $is_custom ? ' is-custom' : ''; ?>">
					<?php echo $is_custom ? esc_html__( 'Custom thumbnail', 'my-slider-pro' ) : esc_html__( 'First slide', 'my-slider-pro' ); ?>
				</span>
			</div>
			<div class="psp-slider-card-body">
				<a class="psp-slider-card-title" href="<?php echo esc_url( self::get_editor_url( $slider_id ) ); ?>"><?php echo esc_html( $title ); ?></a>
				<p class="psp-slider-card-slides">
					<?php
					printf(
						/* translators: %d: number of slides. */
						esc_html( _n( '%d slide', '%d slides', count( $image_ids ), 'my-slider-pro' ) ),
						(int) count( $image_ids )
					);
					?>
				</p>
				<div class="psp-slider-card-shortcode">
					<?php self::render_shortcode_copy( $slider_id ); ?>
				</div>
				<?php if ( $can_edit ) : ?>
					<div class="psp-slider-card-thumb-actions">
						<form class="psp-set-thumbnail-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="<?php echo esc_attr( self::THUMBNAIL_ACTION ); ?>" />
							<input type="hidden" name="slider_id" value="<?php echo esc_attr( (string) $slider_id ); ?>" />
							<input type="hidden" name="thumbnail_id" value="<?php echo esc_attr( (string) $thumbnail_id ); ?>" class="psp-thumbnail-id" />
							<?php wp_nonce_field( self::THUMBNAIL_ACTION . '_' . $slider_id, 'my_slider_pro_nonce' ); ?>
							<button type="button" class="button button-small psp-set-thumbnail"><span class="dashicons dashicons-format-image" aria-hidden="true"></span><?php echo esc_html__( 'Set thumbnail', 'my-slider-pro' ); ?></button>
							<?php if ( $is_custom ) : ?>
								<button type="submit" class="button button-small psp-clear-thumbnail" name="thumbnail_id" value="0"><?php echo esc_html__( 'Remove', 'my-slider-pro' ); ?></button>
							<?php endif; ?>
						</form>
					</div>
				<?php endif; ?>
				<?php if ( $can_edit ) : ?>
					<form id="psp-rename-slider-<?php echo esc_attr( (string) $slider_id ); ?>" class="psp-quick-rename-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" hidden>
						<input type="hidden" name="action" value="<?php echo esc_attr( self::RENAME_ACTION ); ?>" />
						<input type="hidden" name="slider_id" value="<?php echo esc_attr( (string) $slider_id ); ?>" />
						<label class="screen-reader-text" for="psp-rename-title-<?php echo esc_attr( (string) $slider_id ); ?>"><?php echo esc_html__( 'Rename slider title', 'my-slider-pro' ); ?></label>
						<input id="psp-rename-title-<?php echo esc_attr( (string) $slider_id ); ?>" type="text" name="slider_title" value="<?php echo esc_attr( $title ); ?>" maxlength="160" />
						<?php wp_nonce_field( self::RENAME_ACTION . '_' . $slider_id, 'my_slider_pro_nonce' ); ?>
						<button type="submit" class="button button-small"><?php echo esc_html__( 'Save', 'my-slider-pro' ); ?></button>
						<button type="button" class="button button-small psp-rename-cancel"><?php echo esc_html__( 'Cancel', 'my-slider-pro' ); ?></button>
					</form>
				<?php endif; ?>
				<div class="psp-slider-card-actions">
					<?php if ( $can_edit ) : ?>
						<a class="button button-small" href="<?php echo esc_url( self::get_editor_url( $slider_id ) ); ?>"><?php echo esc_html__( 'Edit', 'my-slider-pro' ); ?></a>
						<button type="button" class="button button-small psp-rename-toggle" data-psp-rename-target="psp-rename-slider-<?php echo esc_attr( (string) $slider_id ); ?>"><?php echo esc_html__( 'Rename', 'my-slider-pro' ); ?></button>
						<form class="psp-duplicate-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="<?php echo esc_attr( self::DUPLICATE_ACTION ); ?>" />
							<input type="hidden" name="slider_id" value="<?php echo esc_attr( (string) $slider_id ); ?>" />
							<?php wp_nonce_field( self::DUPLICATE_ACTION . '_' . $slider_id, 'my_slider_pro_nonce' ); ?>
							<button type="submit" class="button button-small"><?php echo esc_html__( 'Duplicate', 'my-slider-pro' ); ?></button>
						</form>
						<form class="psp-export-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="<?php echo esc_attr( self::EXPORT_ACTION ); ?>" />
							<input type="hidden" name="slider_id" value="<?php echo esc_attr( (string) $slider_id ); ?>" />
							<?php wp_nonce_field( self::EXPORT_ACTION . '_' . $slider_id, 'my_slider_pro_nonce' ); ?>
							<button type="submit" class="button button-small"><?php echo esc_html__( 'Export', 'my-slider-pro' ); ?></button>
						</form>
					<?php endif; ?>
					<?php if ( current_user_can( 'delete_post', $slider_id ) ) : ?>
						<form class="psp-delete-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-confirm="<?php echo esc_attr__( 'Move this slider to the trash?', 'my-slider-pro' ); ?>">
							<input type="hidden" name="action" value="<?php echo esc_attr( self::DELETE_ACTION ); ?>" />
							<input type="hidden" name="slider_id" value="<?php echo esc_attr( (string) $slider_id ); ?>" />
							<?php wp_nonce_field( self::DELETE_ACTION . '_' . $slider_id, 'my_slider_pro_nonce' ); ?>
							<button type="submit" class="button button-small button-link-delete"><?php echo esc_html__( 'Trash', 'my-slider-pro' ); ?></button>
						</form>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle quick title rename from the overview table.
	 *
	 * @return void
	 */
	public static function handle_rename(): void {
		self::require_post_request();
		self::authorize();

		$slider_id = self::posted_integer( 'slider_id' );
		$slider    = $slider_id > 0 ? get_post( $slider_id ) : null;

		if (
			! $slider ||
			SliderPostType::POST_TYPE !== $slider->post_type ||
			! self::is_editable_status( $slider ) ||
			! current_user_can( 'edit_post', $slider_id )
		) {
			wp_die( esc_html__( 'You do not have permission to rename this slider.', 'my-slider-pro' ) );
		}

		check_admin_referer( self::RENAME_ACTION . '_' . $slider_id, 'my_slider_pro_nonce' );

		$title = self::posted_text( 'slider_title' );
		$title = '' !== $title ? $title : esc_html__( 'Untitled slider', 'my-slider-pro' );

		wp_update_post(
			array(
				'ID'         => $slider_id,
				'post_title' => substr( $title, 0, 160 ),
			)
		);

		wp_safe_redirect( add_query_arg( 'updated', '1', self::get_overview_url() ) );
		exit;
	}

	/**
	 * Assign (or clear) a slider's card thumbnail from the overview.
	 *
	 * @return void
	 */
	public static function handle_set_thumbnail(): void {
		self::require_post_request();
		self::authorize();

		$slider_id = self::posted_integer( 'slider_id' );
		$slider    = $slider_id > 0 ? get_post( $slider_id ) : null;

		if (
			! $slider ||
			SliderPostType::POST_TYPE !== $slider->post_type ||
			! self::is_editable_status( $slider ) ||
			! current_user_can( 'edit_post', $slider_id )
		) {
			wp_die( esc_html__( 'You do not have permission to change this slider.', 'my-slider-pro' ) );
		}

		check_admin_referer( self::THUMBNAIL_ACTION . '_' . $slider_id, 'my_slider_pro_nonce' );

		SliderPostType::save_thumbnail_id( $slider_id, self::posted_integer( 'thumbnail_id' ) );

		wp_safe_redirect( add_query_arg( 'updated', '1', self::get_overview_url() ) );
		exit;
	}

	/**
	 * Duplicate a slider and append a numeric suffix to its title.
	 *
	 * @return void
	 */
	public static function handle_duplicate(): void {
		self::require_post_request();
		self::authorize();

		$slider_id = self::posted_integer( 'slider_id' );
		$slider    = $slider_id > 0 ? get_post( $slider_id ) : null;

		if (
			! $slider ||
			SliderPostType::POST_TYPE !== $slider->post_type ||
			! self::is_editable_status( $slider ) ||
			! current_user_can( 'edit_post', $slider_id )
		) {
			wp_die( esc_html__( 'You do not have permission to duplicate this slider.', 'my-slider-pro' ) );
		}

		check_admin_referer( self::DUPLICATE_ACTION . '_' . $slider_id, 'my_slider_pro_nonce' );

		$new_id = wp_insert_post(
			array(
				'post_type'   => SliderPostType::POST_TYPE,
				'post_status' => 'draft',
				'post_title'  => self::duplicate_title( get_the_title( $slider_id ) ),
				'post_author' => get_current_user_id(),
			)
		);

		if ( is_wp_error( $new_id ) || (int) $new_id < 1 ) {
			wp_die( esc_html__( 'The slider could not be duplicated.', 'my-slider-pro' ) );
		}

		$image_ids = SliderPostType::get_image_ids( $slider_id );
		SliderPostType::save_meta(
			(int) $new_id,
			$image_ids,
			SliderPostType::get_settings( $slider_id ),
			SliderPostType::get_slide_content( $slider_id, $image_ids )
		);

		wp_safe_redirect( add_query_arg( 'duplicated', '1', self::get_overview_url() ) );
		exit;
	}

	/**
	 * Stream a slider export archive as a download.
	 *
	 * @return void
	 */
	public static function handle_export(): void {
		self::require_post_request();
		self::authorize();

		$slider_id = self::posted_integer( 'slider_id' );
		$slider    = $slider_id > 0 ? get_post( $slider_id ) : null;

		if (
			! $slider ||
			SliderPostType::POST_TYPE !== $slider->post_type ||
			! self::is_editable_status( $slider ) ||
			! current_user_can( 'edit_post', $slider_id )
		) {
			wp_die( esc_html__( 'You do not have permission to export this slider.', 'my-slider-pro' ) );
		}

		check_admin_referer( self::EXPORT_ACTION . '_' . $slider_id, 'my_slider_pro_nonce' );

		$archive = SliderTransfer::export( $slider_id );

		if ( is_wp_error( $archive ) ) {
			wp_die( esc_html( $archive->get_error_message() ) );
		}

		$filename = SliderTransfer::export_filename( $slider_id );
		$size     = filesize( $archive );

		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		if ( false !== $size ) {
			header( 'Content-Length: ' . $size );
		}

		// The archive is first-party binary output, not HTML to be escaped.
		readfile( $archive ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile, WordPress.Security.EscapeOutput.OutputNotEscaped
		wp_delete_file( $archive );
		exit;
	}

	/**
	 * Recreate a slider from an uploaded export archive.
	 *
	 * @return void
	 */
	public static function handle_import(): void {
		self::require_post_request();
		self::authorize();

		check_admin_referer( self::IMPORT_ACTION, 'my_slider_pro_nonce' );

		$file = self::uploaded_import_file();

		if ( is_wp_error( $file ) ) {
			self::redirect_import_error( $file->get_error_code() );
		}

		$result = SliderTransfer::import( $file );

		if ( is_wp_error( $result ) ) {
			self::redirect_import_error( $result->get_error_code() );
		}

		wp_safe_redirect( add_query_arg( 'imported', '1', self::get_overview_url() ) );
		exit;
	}

	/**
	 * Validate the uploaded import file and return its temporary path.
	 *
	 * @return string|\WP_Error
	 */
	private static function uploaded_import_file() {
		if (
			! isset( $_FILES['my_slider_pro_import'] ) ||
			! is_array( $_FILES['my_slider_pro_import'] )
		) {
			return new \WP_Error( 'psp_no_file', __( 'Choose a ZIP file to import.', 'my-slider-pro' ) );
		}

		// Individual members are validated below before use.
		$upload = wp_unslash( $_FILES['my_slider_pro_import'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$error    = isset( $upload['error'] ) ? (int) $upload['error'] : UPLOAD_ERR_NO_FILE;
		$tmp_name = isset( $upload['tmp_name'] ) ? (string) $upload['tmp_name'] : '';
		$name     = isset( $upload['name'] ) ? sanitize_file_name( (string) $upload['name'] ) : '';

		if ( UPLOAD_ERR_OK !== $error || '' === $tmp_name || ! is_uploaded_file( $tmp_name ) ) {
			return new \WP_Error( 'psp_upload_failed', __( 'The file could not be uploaded.', 'my-slider-pro' ) );
		}

		$type = wp_check_filetype( $name, array( 'zip' => 'application/zip' ) );

		if ( 'zip' !== $type['ext'] ) {
			return new \WP_Error( 'psp_not_zip', __( 'Imports must be a .zip file.', 'my-slider-pro' ) );
		}

		return $tmp_name;
	}

	/**
	 * Redirect back to the overview with an import error code.
	 *
	 * @param string $code Error code.
	 * @return void
	 */
	private static function redirect_import_error( string $code ): void {
		wp_safe_redirect( add_query_arg( 'import_error', rawurlencode( $code ), self::get_overview_url() ) );
		exit;
	}

	/**
	 * Return a duplicate title with the next available numeric suffix.
	 *
	 * @param string $title Source slider title.
	 * @return string
	 */
	private static function duplicate_title( string $title ): string {
		$base = '' !== trim( $title ) ? trim( $title ) : esc_html__( 'Untitled slider', 'my-slider-pro' );
		$existing = array_map(
			static function ( $slider ): string {
				return isset( $slider->post_title ) ? (string) $slider->post_title : '';
			},
			get_posts(
				array(
					'post_type'      => SliderPostType::POST_TYPE,
					'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
					'posts_per_page' => -1,
					'fields'         => 'all',
					'no_found_rows'  => true,
				)
			)
		);

		for ( $number = 2; $number < 1000; $number++ ) {
			$candidate = sprintf( '%1$s (%2$d)', $base, $number );
			if ( ! in_array( $candidate, $existing, true ) ) {
				return $candidate;
			}
		}

		return $base . ' (copy)';
	}

	/**
	 * Render a selected Media Library image.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @param int $index         Zero-based image position.
	 * @param int $total         Total selected images.
	 * @param array<string, mixed> $content Slide-specific content.
	 * @return void
	 */
	private static function render_media_item( int $attachment_id, int $index, int $total, array $content = array() ): void {
		$title     = get_the_title( $attachment_id );
		$title     = '' !== $title ? $title : sprintf( esc_html__( 'Image %d', 'my-slider-pro' ), $attachment_id );
		$position  = $index + 1;
		$is_public = 'publish' === get_post_status( $attachment_id );
		$content   = SliderPostType::sanitize_slide_content( array( $attachment_id => $content ), array( $attachment_id ) )[ $attachment_id ];
		?>
		<li class="psp-media-item<?php echo $is_public ? '' : ' is-not-public'; ?>" data-attachment-id="<?php echo esc_attr( (string) $attachment_id ); ?>" data-preview-url="<?php echo esc_url( (string) wp_get_attachment_image_url( $attachment_id, 'large' ) ); ?>">
			<input type="hidden" name="my_slider_pro_image_ids[]" value="<?php echo esc_attr( (string) $attachment_id ); ?>" />
			<div class="psp-slide-summary">
				<div class="psp-media-thumbnail"><?php echo wp_get_attachment_image( $attachment_id, 'thumbnail', false, array( 'alt' => '' ) ); ?></div>
				<div class="psp-slide-identity">
					<strong class="psp-slide-position"><?php echo esc_html( sprintf( __( 'Slide %d', 'my-slider-pro' ), $position ) ); ?></strong>
					<span class="psp-media-title" title="<?php echo esc_attr( $title ); ?>"><?php echo esc_html( $title ); ?></span>
					<?php if ( ! $is_public ) : ?>
						<span class="psp-media-visibility"><?php echo esc_html__( 'Hidden until published', 'my-slider-pro' ); ?></span>
					<?php endif; ?>
				</div>
				<div class="psp-media-actions">
					<button type="button" class="button-link psp-move-earlier" aria-label="<?php echo esc_attr( sprintf( esc_html__( 'Move %1$s earlier; position %2$d of %3$d', 'my-slider-pro' ), $title, $position, $total ) ); ?>"<?php echo 1 === $position ? ' disabled' : ''; ?>>&larr;</button>
					<button type="button" class="button-link psp-move-later" aria-label="<?php echo esc_attr( sprintf( esc_html__( 'Move %1$s later; position %2$d of %3$d', 'my-slider-pro' ), $title, $position, $total ) ); ?>"<?php echo $position === $total ? ' disabled' : ''; ?>>&rarr;</button>
					<button type="button" class="button-link psp-replace-image" aria-label="<?php echo esc_attr( sprintf( esc_html__( 'Replace image for %s', 'my-slider-pro' ), $title ) ); ?>"><?php echo esc_html__( 'Replace', 'my-slider-pro' ); ?></button>
					<button type="button" class="button-link-delete psp-remove-image" aria-label="<?php echo esc_attr( sprintf( esc_html__( 'Remove %1$s; position %2$d of %3$d', 'my-slider-pro' ), $title, $position, $total ) ); ?>"><?php echo esc_html__( 'Remove', 'my-slider-pro' ); ?></button>
				</div>
			</div>
			<details class="psp-slide-details" data-attachment-id="<?php echo esc_attr( (string) $attachment_id ); ?>"<?php echo 0 === $index ? ' open' : ''; ?>>
				<summary><?php echo esc_html__( 'Selected slide properties', 'my-slider-pro' ); ?></summary>
				<div class="psp-slide-fields">
					<label class="psp-field">
						<span><?php echo esc_html__( 'Heading', 'my-slider-pro' ); ?></span>
						<input class="psp-slide-content-input" type="text" name="my_slider_pro_slide_content[<?php echo esc_attr( (string) $attachment_id ); ?>][title]" value="<?php echo esc_attr( $content['title'] ); ?>" maxlength="120" />
					</label>
					<label class="psp-field psp-field-wide">
						<span><?php echo esc_html__( 'Description', 'my-slider-pro' ); ?></span>
						<textarea class="psp-slide-content-input" name="my_slider_pro_slide_content[<?php echo esc_attr( (string) $attachment_id ); ?>][description]" maxlength="280" rows="3"><?php echo esc_textarea( $content['description'] ); ?></textarea>
					</label>
					<label class="psp-field">
						<span><?php echo esc_html__( 'Heading link', 'my-slider-pro' ); ?></span>
						<input class="psp-slide-content-input" type="url" name="my_slider_pro_slide_content[<?php echo esc_attr( (string) $attachment_id ); ?>][heading_link_url]" value="<?php echo esc_attr( $content['heading_link_url'] ); ?>" maxlength="2048" />
					</label>
					<label class="psp-field">
						<span><?php echo esc_html__( 'Description link', 'my-slider-pro' ); ?></span>
						<input class="psp-slide-content-input" type="url" name="my_slider_pro_slide_content[<?php echo esc_attr( (string) $attachment_id ); ?>][description_link_url]" value="<?php echo esc_attr( $content['description_link_url'] ); ?>" maxlength="2048" />
					</label>
					<label class="psp-field">
						<span><?php echo esc_html__( 'Button label', 'my-slider-pro' ); ?></span>
						<input class="psp-slide-content-input" type="text" name="my_slider_pro_slide_content[<?php echo esc_attr( (string) $attachment_id ); ?>][button_label]" value="<?php echo esc_attr( $content['button_label'] ); ?>" maxlength="80" />
					</label>
					<label class="psp-field">
						<span><?php echo esc_html__( 'Button link', 'my-slider-pro' ); ?></span>
						<input class="psp-slide-content-input" type="url" name="my_slider_pro_slide_content[<?php echo esc_attr( (string) $attachment_id ); ?>][button_url]" value="<?php echo esc_attr( $content['button_url'] ); ?>" maxlength="2048" />
					</label>
					<label class="psp-field">
						<span><?php echo esc_html__( 'Image layer URL', 'my-slider-pro' ); ?></span>
						<span class="psp-image-layer-picker">
							<input class="psp-slide-content-input" type="url" name="my_slider_pro_slide_content[<?php echo esc_attr( (string) $attachment_id ); ?>][image_layer_url]" value="<?php echo esc_attr( $content['image_layer_url'] ); ?>" maxlength="2048" />
							<button type="button" class="button psp-select-image-layer"><?php echo esc_html( '' === $content['image_layer_url'] ? __( 'Add image layer', 'my-slider-pro' ) : __( 'Change image layer', 'my-slider-pro' ) ); ?></button>
						</span>
					</label>
					<label class="psp-field">
						<span><?php echo esc_html__( 'Image layer alt text', 'my-slider-pro' ); ?></span>
						<input class="psp-slide-content-input" type="text" name="my_slider_pro_slide_content[<?php echo esc_attr( (string) $attachment_id ); ?>][image_layer_alt]" value="<?php echo esc_attr( $content['image_layer_alt'] ); ?>" maxlength="120" />
					</label>
					<label class="psp-field">
						<span><?php echo esc_html__( 'Image layer link', 'my-slider-pro' ); ?></span>
						<input class="psp-slide-content-input" type="url" name="my_slider_pro_slide_content[<?php echo esc_attr( (string) $attachment_id ); ?>][image_link_url]" value="<?php echo esc_attr( $content['image_link_url'] ); ?>" maxlength="2048" />
					</label>
					<label class="psp-check-field psp-slide-target-field">
						<input class="psp-slide-content-input" type="checkbox" name="my_slider_pro_slide_content[<?php echo esc_attr( (string) $attachment_id ); ?>][button_target]" value="1" <?php checked( $content['button_target'] ); ?> />
						<span><?php echo esc_html__( 'Open button link in a new tab', 'my-slider-pro' ); ?></span>
					</label>
					<label class="psp-check-field psp-slide-target-field">
						<input class="psp-slide-content-input" type="checkbox" name="my_slider_pro_slide_content[<?php echo esc_attr( (string) $attachment_id ); ?>][heading_target]" value="1" <?php checked( ! empty( $content['heading_target'] ) ); ?> />
						<span><?php echo esc_html__( 'Open heading link in a new tab', 'my-slider-pro' ); ?></span>
					</label>
					<label class="psp-check-field psp-slide-target-field">
						<input class="psp-slide-content-input" type="checkbox" name="my_slider_pro_slide_content[<?php echo esc_attr( (string) $attachment_id ); ?>][description_target]" value="1" <?php checked( ! empty( $content['description_target'] ) ); ?> />
						<span><?php echo esc_html__( 'Open text link in a new tab', 'my-slider-pro' ); ?></span>
					</label>
					<label class="psp-check-field psp-slide-target-field">
						<input class="psp-slide-content-input" type="checkbox" name="my_slider_pro_slide_content[<?php echo esc_attr( (string) $attachment_id ); ?>][image_target]" value="1" <?php checked( ! empty( $content['image_target'] ) ); ?> />
						<span><?php echo esc_html__( 'Open image link in a new tab', 'my-slider-pro' ); ?></span>
					</label>
					<?php self::render_extra_layer_controls( $attachment_id, $content['extra_layers'] ); ?>
					<?php self::render_layer_position_controls( $attachment_id, $content ); ?>
				</div>
			</details>
		</li>
		<?php
	}

	/**
	 * Render repeatable extra layer controls.
	 *
	 * @param int                       $attachment_id Attachment ID.
	 * @param array<int, array<string,mixed>> $layers Saved extra layers.
	 * @return void
	 */
	private static function render_extra_layer_controls( int $attachment_id, array $layers ): void {
		?>
		<fieldset class="psp-extra-layers psp-field-wide" data-psp-extra-layers="<?php echo esc_attr( (string) $attachment_id ); ?>" data-psp-next-extra-layer="<?php echo esc_attr( (string) count( $layers ) ); ?>">
			<legend><?php echo esc_html__( 'Additional overlay layers', 'my-slider-pro' ); ?></legend>
			<div class="psp-extra-layer-actions">
				<button type="button" class="button psp-add-extra-layer" data-psp-extra-layer-type="heading"><?php echo esc_html__( 'Add heading', 'my-slider-pro' ); ?></button>
				<button type="button" class="button psp-add-extra-layer" data-psp-extra-layer-type="description"><?php echo esc_html__( 'Add description', 'my-slider-pro' ); ?></button>
				<button type="button" class="button psp-add-extra-layer" data-psp-extra-layer-type="button"><?php echo esc_html__( 'Add button', 'my-slider-pro' ); ?></button>
				<button type="button" class="button psp-add-extra-layer" data-psp-extra-layer-type="image"><?php echo esc_html__( 'Add image', 'my-slider-pro' ); ?></button>
			</div>
			<div class="psp-extra-layer-list">
				<?php foreach ( $layers as $index => $layer ) : ?>
					<?php self::render_extra_layer_row( $attachment_id, $index, $layer ); ?>
				<?php endforeach; ?>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Render one extra layer row.
	 *
	 * @param int                  $attachment_id Attachment ID.
	 * @param int                  $index Row index.
	 * @param array<string,mixed>  $layer Layer values.
	 * @return void
	 */
	private static function render_extra_layer_row( int $attachment_id, int $index, array $layer ): void {
		$name = 'my_slider_pro_slide_content[' . $attachment_id . '][extra_layers][' . $index . ']';
		$type = (string) $layer['type'];
		?>
		<details class="psp-extra-layer-row" data-psp-extra-layer-row>
			<summary class="psp-extra-layer-summary">
				<span class="psp-extra-layer-type-label"><?php echo esc_html( sprintf( '%1$s %2$d', ucfirst( $type ), $index + 1 ) ); ?></span>
				<span class="psp-extra-layer-summary-note"><?php echo esc_html__( 'Overlay layer', 'my-slider-pro' ); ?></span>
				<button type="button" class="button-link-delete psp-remove-extra-layer"><?php echo esc_html__( 'Remove', 'my-slider-pro' ); ?></button>
			</summary>
			<div class="psp-extra-layer-fields">
			<label><span><?php echo esc_html__( 'Type', 'my-slider-pro' ); ?></span><select class="psp-slide-content-input" name="<?php echo esc_attr( $name . '[type]' ); ?>">
				<?php foreach ( array( 'heading' => __( 'Heading', 'my-slider-pro' ), 'description' => __( 'Description', 'my-slider-pro' ), 'button' => __( 'Button', 'my-slider-pro' ), 'image' => __( 'Image', 'my-slider-pro' ) ) as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $type, $value ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select></label>
			<label><span><?php echo esc_html__( 'Text', 'my-slider-pro' ); ?></span><input class="psp-slide-content-input" type="text" name="<?php echo esc_attr( $name . '[text]' ); ?>" value="<?php echo esc_attr( (string) $layer['text'] ); ?>" maxlength="280" /></label>
			<label><span><?php echo esc_html__( 'Image URL', 'my-slider-pro' ); ?></span><span class="psp-image-layer-picker"><input class="psp-slide-content-input" type="url" name="<?php echo esc_attr( $name . '[url]' ); ?>" value="<?php echo esc_attr( (string) $layer['url'] ); ?>" maxlength="2048" /><button type="button" class="button psp-select-extra-image-layer"><?php echo esc_html__( 'Choose image', 'my-slider-pro' ); ?></button></span></label>
			<label><span><?php echo esc_html__( 'Link URL', 'my-slider-pro' ); ?></span><input class="psp-slide-content-input" type="url" name="<?php echo esc_attr( $name . '[link_url]' ); ?>" value="<?php echo esc_attr( (string) $layer['link_url'] ); ?>" maxlength="2048" /></label>
			<label><span><?php echo esc_html__( 'Alt text', 'my-slider-pro' ); ?></span><input class="psp-slide-content-input" type="text" name="<?php echo esc_attr( $name . '[alt]' ); ?>" value="<?php echo esc_attr( (string) $layer['alt'] ); ?>" maxlength="120" /></label>
			<label><span><?php echo esc_html__( 'Color', 'my-slider-pro' ); ?></span><input class="psp-slide-content-input" type="color" name="<?php echo esc_attr( $name . '[color]' ); ?>" value="<?php echo esc_attr( (string) $layer['color'] ); ?>" /></label>
			<label><span><?php echo esc_html__( 'Background', 'my-slider-pro' ); ?></span><input class="psp-slide-content-input" type="color" name="<?php echo esc_attr( $name . '[background]' ); ?>" value="<?php echo esc_attr( (string) $layer['background'] ); ?>" /></label>
			<label><span><?php echo esc_html__( 'Font', 'my-slider-pro' ); ?></span><?php self::render_named_font_select( $name . '[font_family]', (string) $layer['font_family'] ); ?></label>
			<input type="hidden" name="<?php echo esc_attr( $name . '[font_style]' ); ?>" value="<?php echo esc_attr( (string) ( $layer['font_style'] ?? 'default' ) ); ?>" />
			<label><span><?php echo esc_html__( 'Size', 'my-slider-pro' ); ?></span><input class="psp-slide-content-input" type="number" min="12" max="96" name="<?php echo esc_attr( $name . '[size]' ); ?>" value="<?php echo esc_attr( (string) $layer['size'] ); ?>" /></label>
			<label><span><?php echo esc_html__( 'Opacity', 'my-slider-pro' ); ?></span><input class="psp-slide-content-input" type="number" min="10" max="100" name="<?php echo esc_attr( $name . '[opacity]' ); ?>" value="<?php echo esc_attr( (string) $layer['opacity'] ); ?>" /></label>
			<label><span><?php echo esc_html__( 'Desktop X/Y', 'my-slider-pro' ); ?></span><span class="psp-extra-layer-pair"><input class="psp-slide-content-input" type="number" min="5" max="95" name="<?php echo esc_attr( $name . '[desktop_x]' ); ?>" value="<?php echo esc_attr( (string) $layer['desktop_x'] ); ?>" /><input class="psp-slide-content-input" type="number" min="5" max="95" name="<?php echo esc_attr( $name . '[desktop_y]' ); ?>" value="<?php echo esc_attr( (string) $layer['desktop_y'] ); ?>" /></span></label>
			<label><span><?php echo esc_html__( 'Tablet X/Y', 'my-slider-pro' ); ?></span><span class="psp-extra-layer-pair"><input class="psp-slide-content-input" type="number" min="5" max="95" name="<?php echo esc_attr( $name . '[tablet_x]' ); ?>" value="<?php echo esc_attr( (string) $layer['tablet_x'] ); ?>" /><input class="psp-slide-content-input" type="number" min="5" max="95" name="<?php echo esc_attr( $name . '[tablet_y]' ); ?>" value="<?php echo esc_attr( (string) $layer['tablet_y'] ); ?>" /></span></label>
			<label><span><?php echo esc_html__( 'Phone X/Y', 'my-slider-pro' ); ?></span><span class="psp-extra-layer-pair"><input class="psp-slide-content-input" type="number" min="5" max="95" name="<?php echo esc_attr( $name . '[mobile_x]' ); ?>" value="<?php echo esc_attr( (string) $layer['mobile_x'] ); ?>" /><input class="psp-slide-content-input" type="number" min="5" max="95" name="<?php echo esc_attr( $name . '[mobile_y]' ); ?>" value="<?php echo esc_attr( (string) $layer['mobile_y'] ); ?>" /></span></label>
			<input type="hidden" name="<?php echo esc_attr( $name . '[width]' ); ?>" value="<?php echo esc_attr( (string) $layer['width'] ); ?>" />
			<input type="hidden" name="<?php echo esc_attr( $name . '[animation]' ); ?>" value="<?php echo esc_attr( (string) $layer['animation'] ); ?>" />
			<input type="hidden" name="<?php echo esc_attr( $name . '[animation_delay]' ); ?>" value="<?php echo esc_attr( (string) $layer['animation_delay'] ); ?>" />
			<input type="hidden" name="<?php echo esc_attr( $name . '[animation_duration]' ); ?>" value="<?php echo esc_attr( (string) $layer['animation_duration'] ); ?>" />
			<input type="hidden" name="<?php echo esc_attr( $name . '[animation_easing]' ); ?>" value="<?php echo esc_attr( (string) $layer['animation_easing'] ); ?>" />
			<label class="psp-check-field"><input class="psp-slide-content-input" type="checkbox" name="<?php echo esc_attr( $name . '[target]' ); ?>" value="1" <?php checked( ! empty( $layer['target'] ) ); ?> /><span><?php echo esc_html__( 'Open in new tab', 'my-slider-pro' ); ?></span></label>
			</div>
		</details>
		<?php
	}

	/**
	 * Render desktop and mobile position controls for the text and CTA layers.
	 *
	 * @param int                  $attachment_id Attachment ID.
	 * @param array<string, mixed> $content Sanitized slide content.
	 * @return void
	 */
	private static function render_layer_position_controls( int $attachment_id, array $content ): void {
		$position_labels = array(
			'5,12'  => esc_html__( 'Top left', 'my-slider-pro' ),
			'50,12' => esc_html__( 'Top center', 'my-slider-pro' ),
			'95,12' => esc_html__( 'Top right', 'my-slider-pro' ),
			'5,50'  => esc_html__( 'Middle left', 'my-slider-pro' ),
			'50,50' => esc_html__( 'Middle center', 'my-slider-pro' ),
			'95,50' => esc_html__( 'Middle right', 'my-slider-pro' ),
			'5,82'  => esc_html__( 'Bottom left', 'my-slider-pro' ),
			'50,82' => esc_html__( 'Bottom center', 'my-slider-pro' ),
			'95,82' => esc_html__( 'Bottom right', 'my-slider-pro' ),
		);
		$controls = array(
			array( 'desktop', 'heading', 'text_x', 'text_y', esc_html__( 'Desktop heading position', 'my-slider-pro' ) ),
			array( 'desktop', 'description', 'description_x', 'description_y', esc_html__( 'Desktop description position', 'my-slider-pro' ) ),
			array( 'desktop', 'button', 'button_x', 'button_y', esc_html__( 'Desktop button position', 'my-slider-pro' ) ),
			array( 'desktop', 'image', 'image_x', 'image_y', esc_html__( 'Desktop image position', 'my-slider-pro' ) ),
			array( 'tablet', 'heading', 'tablet_text_x', 'tablet_text_y', esc_html__( 'Tablet heading position', 'my-slider-pro' ) ),
			array( 'tablet', 'description', 'tablet_description_x', 'tablet_description_y', esc_html__( 'Tablet description position', 'my-slider-pro' ) ),
			array( 'tablet', 'button', 'tablet_button_x', 'tablet_button_y', esc_html__( 'Tablet button position', 'my-slider-pro' ) ),
			array( 'tablet', 'image', 'tablet_image_x', 'tablet_image_y', esc_html__( 'Tablet image position', 'my-slider-pro' ) ),
			array( 'mobile', 'heading', 'mobile_text_x', 'mobile_text_y', esc_html__( 'Phone heading position', 'my-slider-pro' ) ),
			array( 'mobile', 'description', 'mobile_description_x', 'mobile_description_y', esc_html__( 'Phone description position', 'my-slider-pro' ) ),
			array( 'mobile', 'button', 'mobile_button_x', 'mobile_button_y', esc_html__( 'Phone button position', 'my-slider-pro' ) ),
			array( 'mobile', 'image', 'mobile_image_x', 'mobile_image_y', esc_html__( 'Phone image position', 'my-slider-pro' ) ),
		);
		?>
		<fieldset class="psp-layer-controls psp-field-wide">
			<legend><?php echo esc_html__( 'Layer positions', 'my-slider-pro' ); ?></legend>
			<p><?php echo esc_html__( 'Choose a preset or drag each layer in Slider Preview. Desktop, Tablet, and Phone positions are saved independently.', 'my-slider-pro' ); ?></p>
			<div class="psp-layer-controls-grid">
				<?php foreach ( $controls as $control ) : ?>
					<?php
					$selected_position = (string) $content[ $control[2] ] . ',' . (string) $content[ $control[3] ];
					$selected_position = isset( $position_labels[ $selected_position ] ) ? $selected_position : 'custom';
					?>
					<label class="psp-field">
						<span><?php echo esc_html( $control[4] ); ?></span>
						<select class="psp-layer-position-select" data-psp-layer-device="<?php echo esc_attr( $control[0] ); ?>" data-psp-layer-type="<?php echo esc_attr( $control[1] ); ?>">
							<option value="custom" <?php selected( $selected_position, 'custom' ); ?>><?php echo esc_html__( 'Custom (dragged)', 'my-slider-pro' ); ?></option>
							<?php foreach ( $position_labels as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $selected_position, $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
				<?php endforeach; ?>
			</div>
			<?php foreach ( array( 'text_x', 'text_y', 'description_x', 'description_y', 'button_x', 'button_y', 'image_x', 'image_y', 'tablet_text_x', 'tablet_text_y', 'tablet_description_x', 'tablet_description_y', 'tablet_button_x', 'tablet_button_y', 'tablet_image_x', 'tablet_image_y', 'mobile_text_x', 'mobile_text_y', 'mobile_description_x', 'mobile_description_y', 'mobile_button_x', 'mobile_button_y', 'mobile_image_x', 'mobile_image_y' ) as $coordinate_key ) : ?>
				<input class="psp-layer-coordinate" type="hidden" name="my_slider_pro_slide_content[<?php echo esc_attr( (string) $attachment_id ); ?>][<?php echo esc_attr( $coordinate_key ); ?>]" value="<?php echo esc_attr( (string) $content[ $coordinate_key ] ); ?>" />
			<?php endforeach; ?>
			<?php foreach ( array( 'text_color', 'heading_size', 'heading_opacity', 'text_align', 'font_family', 'description_color', 'description_size', 'description_opacity', 'description_align', 'description_font_family', 'heading_font_style', 'description_font_style', 'button_font_style', 'button_text_color', 'button_background', 'button_font_family', 'button_font_size', 'button_opacity', 'button_radius', 'button_padding_x', 'button_padding_y', 'image_width', 'image_opacity', 'background_fill', 'background_position', 'tablet_background_position', 'mobile_background_position', 'overlay_type', 'overlay_color', 'overlay_color2', 'overlay_opacity', 'overlay_direction', 'heading_animation', 'description_animation', 'button_animation', 'image_animation', 'heading_animation_delay', 'description_animation_delay', 'button_animation_delay', 'image_animation_delay', 'heading_animation_duration', 'description_animation_duration', 'button_animation_duration', 'image_animation_duration', 'heading_animation_easing', 'description_animation_easing', 'button_animation_easing', 'image_animation_easing', 'tablet_heading_size', 'mobile_heading_size', 'tablet_description_size', 'mobile_description_size', 'tablet_button_font_size', 'mobile_button_font_size', 'tablet_image_width', 'mobile_image_width', 'heading_size_linked', 'description_size_linked', 'button_size_linked', 'image_size_linked', 'heading_pos_linked', 'description_pos_linked', 'button_pos_linked', 'image_pos_linked' ) as $style_key ) : ?>
				<input class="psp-layer-style-value" type="hidden" name="my_slider_pro_slide_content[<?php echo esc_attr( (string) $attachment_id ); ?>][<?php echo esc_attr( $style_key ); ?>]" value="<?php echo esc_attr( (string) $content[ $style_key ] ); ?>" />
			<?php endforeach; ?>
			<input class="psp-layer-order-value" type="hidden" name="my_slider_pro_slide_content[<?php echo esc_attr( (string) $attachment_id ); ?>][layer_order]" value="<?php echo esc_attr( (string) $content['layer_order'] ); ?>" />
		</fieldset>
		<?php
	}

	/**
	 * Render a dependency-free font stack selector for layer typography.
	 *
	 * @param string $style_key Hidden style key managed by the layer inspector.
	 * @return void
	 */
	/**
	 * Render the brand glyph used by the gradient hero headers.
	 *
	 * @return void
	 */
	private static function render_hero_glyph(): void {
		?>
		<span class="psp-hero-glyph" aria-hidden="true">
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" focusable="false">
				<defs>
					<linearGradient id="psp-hbg" x1="0" y1="0" x2="1" y2="1">
						<stop offset="0" stop-color="#2b86cf"/>
						<stop offset="1" stop-color="#135e96"/>
					</linearGradient>
					<linearGradient id="psp-hsky" x1="0" y1="0" x2="0" y2="1">
						<stop offset="0" stop-color="#eaf3fb"/>
						<stop offset="1" stop-color="#cfe4f6"/>
					</linearGradient>
					<clipPath id="psp-hfront"><rect x="70" y="70" width="116" height="116" rx="16"/></clipPath>
				</defs>
				<rect width="256" height="256" rx="56" fill="url(#psp-hbg)"/>
				<rect x="26" y="88" width="34" height="80" rx="10" fill="#ffffff" opacity="0.22"/>
				<rect x="196" y="88" width="34" height="80" rx="10" fill="#ffffff" opacity="0.22"/>
				<rect x="70" y="70" width="116" height="116" rx="16" fill="#ffffff"/>
				<g clip-path="url(#psp-hfront)">
					<rect x="70" y="70" width="116" height="116" fill="url(#psp-hsky)"/>
					<circle cx="156" cy="104" r="14" fill="#ffcf6b"/>
					<path d="M70 186 L110 132 L138 160 L162 128 L186 158 L186 186 Z" fill="#2271b1"/>
					<path d="M70 186 L98 154 L120 172 L146 150 L186 186 Z" fill="#17567f"/>
				</g>
				<path d="M52 128 l-12 10 l12 10" fill="none" stroke="#ffffff" stroke-width="7" stroke-linecap="round" stroke-linejoin="round" opacity="0.9"/>
				<path d="M204 128 l12 10 l-12 10" fill="none" stroke="#ffffff" stroke-width="7" stroke-linecap="round" stroke-linejoin="round" opacity="0.9"/>
				<circle cx="108" cy="210" r="6" fill="#ffffff" opacity="0.55"/>
				<circle cx="128" cy="210" r="7" fill="#ffffff"/>
				<circle cx="148" cy="210" r="6" fill="#ffffff" opacity="0.55"/>
			</svg>
		</span>
		<?php
	}

	/**
	 * Render an inspector link field: a free URL input plus a button that
	 * opens the internal page/post search picker.
	 *
	 * @param string $style_key Inspector style key the input writes to.
	 * @return void
	 */
	private static function render_link_field( string $style_key ): void {
		?>
		<span class="psp-link-field">
			<input type="url" placeholder="https://" data-psp-style-key="<?php echo esc_attr( $style_key ); ?>" />
			<button type="button" class="button psp-link-pick" title="<?php echo esc_attr__( 'Link to a page or post on this site', 'my-slider-pro' ); ?>" aria-label="<?php echo esc_attr__( 'Search pages and posts to link', 'my-slider-pro' ); ?>"><span class="dashicons dashicons-admin-links" aria-hidden="true"></span></button>
		</span>
		<?php
	}

	private static function render_font_select( string $style_key ): void {
		$options = array(
			'theme'       => esc_html__( 'Theme default', 'my-slider-pro' ),
			'poppins'     => esc_html__( 'Poppins', 'my-slider-pro' ),
			'montserrat'  => esc_html__( 'Montserrat', 'my-slider-pro' ),
			'inter'       => esc_html__( 'Inter', 'my-slider-pro' ),
		);
		?>
		<select data-psp-style-key="<?php echo esc_attr( $style_key ); ?>">
			<?php foreach ( $options as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Render a font weight/style selector bound to a layer style key.
	 *
	 * @param string $style_key Inspector style key.
	 * @return void
	 */
	private static function render_font_style_select( string $style_key ): void {
		$options = array(
			'default'     => esc_html__( 'Theme default', 'my-slider-pro' ),
			'normal'      => esc_html__( 'Normal', 'my-slider-pro' ),
			'bold'        => esc_html__( 'Bold', 'my-slider-pro' ),
			'italic'      => esc_html__( 'Italic', 'my-slider-pro' ),
			'bold-italic' => esc_html__( 'Bold italic', 'my-slider-pro' ),
		);
		?>
		<select data-psp-style-key="<?php echo esc_attr( $style_key ); ?>">
			<?php foreach ( $options as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Render an icon segmented control for a text-alignment style key.
	 *
	 * The buttons drive a hidden input carrying the style key, so the existing
	 * two-way binding is unchanged.
	 *
	 * @param string $style_key Alignment style key.
	 * @return void
	 */
	private static function render_align_segment( string $style_key ): void {
		$options = array(
			'left'   => array( 'dashicons-editor-alignleft', __( 'Left', 'my-slider-pro' ) ),
			'center' => array( 'dashicons-editor-aligncenter', __( 'Center', 'my-slider-pro' ) ),
			'right'  => array( 'dashicons-editor-alignright', __( 'Right', 'my-slider-pro' ) ),
		);
		?>
		<span class="psp-seg" role="group" aria-label="<?php echo esc_attr__( 'Alignment', 'my-slider-pro' ); ?>">
			<input type="hidden" data-psp-style-key="<?php echo esc_attr( $style_key ); ?>" value="left" />
			<?php foreach ( $options as $value => $opt ) : ?>
				<button type="button" data-psp-seg-value="<?php echo esc_attr( $value ); ?>" aria-label="<?php echo esc_attr( $opt[1] ); ?>"><span class="dashicons <?php echo esc_attr( $opt[0] ); ?>" aria-hidden="true"></span></button>
			<?php endforeach; ?>
		</span>
		<?php
	}

	/**
	 * Render a text segmented control for the slide background fill mode.
	 *
	 * @return void
	 */
	private static function render_fill_segment(): void {
		// value => [ dashicon, full label (title/aria), short visible label ].
		$options = array(
			'cover'  => array( 'dashicons-image-crop', __( 'Cover', 'my-slider-pro' ), __( 'Cover', 'my-slider-pro' ) ),
			'fill'   => array( 'dashicons-editor-expand', __( 'Fill (stretch)', 'my-slider-pro' ), __( 'Fill', 'my-slider-pro' ) ),
			'fit'    => array( 'dashicons-editor-contract', __( 'Fit', 'my-slider-pro' ), __( 'Fit', 'my-slider-pro' ) ),
			'center' => array( 'dashicons-align-center', __( 'Actual size', 'my-slider-pro' ), __( 'Actual', 'my-slider-pro' ) ),
		);
		?>
		<span class="psp-seg psp-seg-icons psp-seg-labelled" role="group" aria-label="<?php echo esc_attr__( 'Fill mode', 'my-slider-pro' ); ?>">
			<input type="hidden" data-psp-slide-key="background_fill" value="cover" />
			<?php foreach ( $options as $value => $opt ) : ?>
				<button type="button" data-psp-seg-value="<?php echo esc_attr( $value ); ?>" title="<?php echo esc_attr( $opt[1] ); ?>" aria-label="<?php echo esc_attr( $opt[1] ); ?>"><span class="dashicons <?php echo esc_attr( $opt[0] ); ?>" aria-hidden="true"></span><span class="psp-seg-label"><?php echo esc_html( $opt[2] ); ?></span></button>
			<?php endforeach; ?>
		</span>
		<?php
	}

	/**
	 * Render a 3x3 pad for choosing the slide background position.
	 *
	 * Reuses the .psp-seg component so the existing click and slide-sync logic
	 * drives it; only the layout differs.
	 *
	 * @return void
	 */
	private static function render_position_segment(): void {
		$positions = array(
			'top_left'      => __( 'Top left', 'my-slider-pro' ),
			'top_center'    => __( 'Top center', 'my-slider-pro' ),
			'top_right'     => __( 'Top right', 'my-slider-pro' ),
			'center_left'   => __( 'Center left', 'my-slider-pro' ),
			'center'        => __( 'Center', 'my-slider-pro' ),
			'center_right'  => __( 'Center right', 'my-slider-pro' ),
			'bottom_left'   => __( 'Bottom left', 'my-slider-pro' ),
			'bottom_center' => __( 'Bottom center', 'my-slider-pro' ),
			'bottom_right'  => __( 'Bottom right', 'my-slider-pro' ),
		);
		?>
		<span class="psp-seg psp-seg-grid" role="group" aria-label="<?php echo esc_attr__( 'Background position', 'my-slider-pro' ); ?>">
			<input type="hidden" data-psp-slide-key="background_position" value="center" />
			<?php foreach ( $positions as $value => $label ) : ?>
				<button type="button" data-psp-seg-value="<?php echo esc_attr( $value ); ?>" title="<?php echo esc_attr( $label ); ?>" aria-label="<?php echo esc_attr( $label ); ?>"><span></span></button>
			<?php endforeach; ?>
		</span>
		<?php
	}

	/**
	 * Render a named font selector for submitted repeatable layer fields.
	 *
	 * @param string $name Submitted field name.
	 * @param string $selected Selected font key.
	 * @return void
	 */
	private static function render_named_font_select( string $name, string $selected ): void {
		$options = array(
			'theme'      => esc_html__( 'Theme default', 'my-slider-pro' ),
			'poppins'    => esc_html__( 'Poppins', 'my-slider-pro' ),
			'montserrat' => esc_html__( 'Montserrat', 'my-slider-pro' ),
			'inter'      => esc_html__( 'Inter', 'my-slider-pro' ),
		);
		?>
		<select class="psp-slide-content-input" name="<?php echo esc_attr( $name ); ?>">
			<?php foreach ( $options as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $selected, $value ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Render slider settings.
	 *
	 * @param array<string, mixed> $settings Slider settings.
	 * @return void
	 */
	private static function render_settings_fields( array $settings ): void {
		?>
		<div class="psp-settings-layout">
		<div class="psp-settings-frame">
		<div class="psp-settings-width" data-psp-width-group>
			<label class="psp-width-field"><span><?php echo esc_html__( 'Slider width', 'my-slider-pro' ); ?></span>
				<select id="my-slider-pro-width" name="slider_width">
					<option value="full" <?php selected( $settings['width'], 'full' ); ?>><?php echo esc_html__( 'Full width', 'my-slider-pro' ); ?></option>
					<option value="boxed" <?php selected( $settings['width'], 'boxed' ); ?>><?php echo esc_html__( 'Boxed (max-width)', 'my-slider-pro' ); ?></option>
				</select>
			</label>
			<label class="psp-width-field psp-width-max<?php echo 'boxed' === $settings['width'] ? '' : ' is-hidden'; ?>"><span><?php echo esc_html__( 'Max width (px)', 'my-slider-pro' ); ?></span>
				<input type="number" id="my-slider-pro-max-width" name="slider_max_width" min="600" max="1920" step="10" value="<?php echo esc_attr( (string) $settings['max_width'] ); ?>" />
			</label>
			<p class="psp-width-help"><?php echo esc_html__( 'Full width spans the content area; Boxed centers the slider at the max width and stays responsive on small screens.', 'my-slider-pro' ); ?></p>
		</div>
		<div class="psp-responsive-matrix" role="group" aria-label="<?php echo esc_attr__( 'Responsive layout by device', 'my-slider-pro' ); ?>">
			<span class="psp-matrix-corner" aria-hidden="true"></span>
			<span class="psp-matrix-device"><span class="dashicons dashicons-desktop" aria-hidden="true"></span><?php echo esc_html__( 'Desktop', 'my-slider-pro' ); ?></span>
			<span class="psp-matrix-device"><span class="dashicons dashicons-tablet" aria-hidden="true"></span><?php echo esc_html__( 'Tablet', 'my-slider-pro' ); ?></span>
			<span class="psp-matrix-device"><span class="dashicons dashicons-smartphone" aria-hidden="true"></span><?php echo esc_html__( 'Phone', 'my-slider-pro' ); ?></span>

			<span class="psp-matrix-label"><?php echo esc_html__( 'Height', 'my-slider-pro' ); ?></span>
			<select id="my-slider-pro-height" name="slider_height" aria-label="<?php echo esc_attr__( 'Desktop height', 'my-slider-pro' ); ?>">
				<option value="compact" <?php selected( $settings['height'], 'compact' ); ?>><?php echo esc_html__( 'Compact', 'my-slider-pro' ); ?></option>
				<option value="standard" <?php selected( $settings['height'], 'standard' ); ?>><?php echo esc_html__( 'Standard', 'my-slider-pro' ); ?></option>
				<option value="tall" <?php selected( $settings['height'], 'tall' ); ?>><?php echo esc_html__( 'Tall', 'my-slider-pro' ); ?></option>
				<option value="viewport" <?php selected( $settings['height'], 'viewport' ); ?>><?php echo esc_html__( 'Full screen', 'my-slider-pro' ); ?></option>
			</select>
			<select id="my-slider-pro-tablet-height" name="slider_tablet_height" aria-label="<?php echo esc_attr__( 'Tablet height', 'my-slider-pro' ); ?>">
				<option value="compact" <?php selected( $settings['tablet_height'], 'compact' ); ?>><?php echo esc_html__( 'Compact', 'my-slider-pro' ); ?></option>
				<option value="standard" <?php selected( $settings['tablet_height'], 'standard' ); ?>><?php echo esc_html__( 'Standard', 'my-slider-pro' ); ?></option>
				<option value="tall" <?php selected( $settings['tablet_height'], 'tall' ); ?>><?php echo esc_html__( 'Tall', 'my-slider-pro' ); ?></option>
			</select>
			<select id="my-slider-pro-mobile-height" name="slider_mobile_height" aria-label="<?php echo esc_attr__( 'Phone height', 'my-slider-pro' ); ?>">
				<option value="compact" <?php selected( $settings['mobile_height'], 'compact' ); ?>><?php echo esc_html__( 'Compact', 'my-slider-pro' ); ?></option>
				<option value="standard" <?php selected( $settings['mobile_height'], 'standard' ); ?>><?php echo esc_html__( 'Standard', 'my-slider-pro' ); ?></option>
				<option value="tall" <?php selected( $settings['mobile_height'], 'tall' ); ?>><?php echo esc_html__( 'Tall', 'my-slider-pro' ); ?></option>
			</select>

			<span class="psp-matrix-label"><?php echo esc_html__( 'Content align', 'my-slider-pro' ); ?></span>
			<select id="my-slider-pro-content-position" name="slider_content_position" aria-label="<?php echo esc_attr__( 'Desktop content alignment', 'my-slider-pro' ); ?>">
				<option value="left" <?php selected( $settings['content_position'], 'left' ); ?>><?php echo esc_html__( 'Left', 'my-slider-pro' ); ?></option>
				<option value="center" <?php selected( $settings['content_position'], 'center' ); ?>><?php echo esc_html__( 'Center', 'my-slider-pro' ); ?></option>
				<option value="right" <?php selected( $settings['content_position'], 'right' ); ?>><?php echo esc_html__( 'Right', 'my-slider-pro' ); ?></option>
			</select>
			<select id="my-slider-pro-tablet-content-position" name="slider_tablet_content_position" aria-label="<?php echo esc_attr__( 'Tablet content alignment', 'my-slider-pro' ); ?>">
				<option value="left" <?php selected( $settings['tablet_content_position'], 'left' ); ?>><?php echo esc_html__( 'Left', 'my-slider-pro' ); ?></option>
				<option value="center" <?php selected( $settings['tablet_content_position'], 'center' ); ?>><?php echo esc_html__( 'Center', 'my-slider-pro' ); ?></option>
				<option value="right" <?php selected( $settings['tablet_content_position'], 'right' ); ?>><?php echo esc_html__( 'Right', 'my-slider-pro' ); ?></option>
			</select>
			<select id="my-slider-pro-mobile-content-position" name="slider_mobile_content_position" aria-label="<?php echo esc_attr__( 'Phone content alignment', 'my-slider-pro' ); ?>">
				<option value="left" <?php selected( $settings['mobile_content_position'], 'left' ); ?>><?php echo esc_html__( 'Left', 'my-slider-pro' ); ?></option>
				<option value="center" <?php selected( $settings['mobile_content_position'], 'center' ); ?>><?php echo esc_html__( 'Center', 'my-slider-pro' ); ?></option>
				<option value="right" <?php selected( $settings['mobile_content_position'], 'right' ); ?>><?php echo esc_html__( 'Right', 'my-slider-pro' ); ?></option>
			</select>

			<span class="psp-matrix-label"><?php echo esc_html__( 'Text width', 'my-slider-pro' ); ?></span>
			<span class="psp-matrix-default" title="<?php echo esc_attr__( 'Uses the theme default on desktop', 'my-slider-pro' ); ?>"><?php echo esc_html__( 'Default', 'my-slider-pro' ); ?></span>
			<select id="my-slider-pro-tablet-text-width" name="slider_tablet_text_width" aria-label="<?php echo esc_attr__( 'Tablet text width', 'my-slider-pro' ); ?>">
				<option value="narrow" <?php selected( $settings['tablet_text_width'], 'narrow' ); ?>><?php echo esc_html__( 'Narrow', 'my-slider-pro' ); ?></option>
				<option value="comfortable" <?php selected( $settings['tablet_text_width'], 'comfortable' ); ?>><?php echo esc_html__( 'Comfortable', 'my-slider-pro' ); ?></option>
				<option value="wide" <?php selected( $settings['tablet_text_width'], 'wide' ); ?>><?php echo esc_html__( 'Wide', 'my-slider-pro' ); ?></option>
			</select>
			<select id="my-slider-pro-mobile-text-width" name="slider_mobile_text_width" aria-label="<?php echo esc_attr__( 'Phone text width', 'my-slider-pro' ); ?>">
				<option value="narrow" <?php selected( $settings['mobile_text_width'], 'narrow' ); ?>><?php echo esc_html__( 'Narrow', 'my-slider-pro' ); ?></option>
				<option value="comfortable" <?php selected( $settings['mobile_text_width'], 'comfortable' ); ?>><?php echo esc_html__( 'Comfortable', 'my-slider-pro' ); ?></option>
				<option value="wide" <?php selected( $settings['mobile_text_width'], 'wide' ); ?>><?php echo esc_html__( 'Wide', 'my-slider-pro' ); ?></option>
			</select>

			<span class="psp-matrix-label"><?php echo esc_html__( 'Button size', 'my-slider-pro' ); ?></span>
			<span class="psp-matrix-default" title="<?php echo esc_attr__( 'Uses the theme default on desktop', 'my-slider-pro' ); ?>"><?php echo esc_html__( 'Default', 'my-slider-pro' ); ?></span>
			<select id="my-slider-pro-tablet-button-size" name="slider_tablet_button_size" aria-label="<?php echo esc_attr__( 'Tablet button size', 'my-slider-pro' ); ?>">
				<option value="standard" <?php selected( $settings['tablet_button_size'], 'standard' ); ?>><?php echo esc_html__( 'Standard', 'my-slider-pro' ); ?></option>
				<option value="large" <?php selected( $settings['tablet_button_size'], 'large' ); ?>><?php echo esc_html__( 'Large tap target', 'my-slider-pro' ); ?></option>
				<option value="full" <?php selected( $settings['tablet_button_size'], 'full' ); ?>><?php echo esc_html__( 'Full width', 'my-slider-pro' ); ?></option>
			</select>
			<select id="my-slider-pro-mobile-button-size" name="slider_mobile_button_size" aria-label="<?php echo esc_attr__( 'Phone button size', 'my-slider-pro' ); ?>">
				<option value="standard" <?php selected( $settings['mobile_button_size'], 'standard' ); ?>><?php echo esc_html__( 'Standard', 'my-slider-pro' ); ?></option>
				<option value="large" <?php selected( $settings['mobile_button_size'], 'large' ); ?>><?php echo esc_html__( 'Large tap target', 'my-slider-pro' ); ?></option>
				<option value="full" <?php selected( $settings['mobile_button_size'], 'full' ); ?>><?php echo esc_html__( 'Full width', 'my-slider-pro' ); ?></option>
			</select>
		</div>
		</div>

		<div class="psp-settings-groups">
			<div class="psp-check-group">
				<p class="psp-group-label"><span class="dashicons dashicons-controls-forward" aria-hidden="true"></span><?php echo esc_html__( 'Navigation', 'my-slider-pro' ); ?></p>
				<div class="psp-check-list">
					<label class="psp-check-field">
						<input type="checkbox" name="slider_arrows" value="1" <?php checked( $settings['arrows'] ); ?> />
						<span><?php echo esc_html__( 'Show arrows', 'my-slider-pro' ); ?></span>
					</label>
					<label class="psp-check-field">
						<input type="checkbox" name="slider_hide_arrows_on_phone" value="1" <?php checked( $settings['hide_arrows_on_phone'] ); ?> />
						<span><?php echo esc_html__( 'Hide arrows on phones', 'my-slider-pro' ); ?></span>
					</label>
					<label class="psp-check-field">
						<input type="checkbox" name="slider_dots" value="1" <?php checked( $settings['dots'] ); ?> />
						<span><?php echo esc_html__( 'Show slide dots', 'my-slider-pro' ); ?></span>
					</label>
				</div>
			</div>
			<div class="psp-check-group">
				<p class="psp-group-label"><span class="dashicons dashicons-update" aria-hidden="true"></span><?php echo esc_html__( 'Playback', 'my-slider-pro' ); ?></p>
				<div class="psp-check-list">
					<label class="psp-check-field">
						<input type="checkbox" id="my-slider-pro-autoplay" name="slider_autoplay" value="1" <?php checked( $settings['autoplay'] ); ?> />
						<span><?php echo esc_html__( 'Autoplay slides', 'my-slider-pro' ); ?></span>
					</label>
					<label class="psp-field psp-check-subfield" for="my-slider-pro-interval">
						<span class="psp-field-label"><?php echo esc_html__( 'Delay between slides', 'my-slider-pro' ); ?></span>
						<select id="my-slider-pro-interval" name="slider_interval">
							<option value="3000" <?php selected( $settings['interval'], 3000 ); ?>><?php echo esc_html__( '3 seconds', 'my-slider-pro' ); ?></option>
							<option value="5000" <?php selected( $settings['interval'], 5000 ); ?>><?php echo esc_html__( '5 seconds', 'my-slider-pro' ); ?></option>
							<option value="7000" <?php selected( $settings['interval'], 7000 ); ?>><?php echo esc_html__( '7 seconds', 'my-slider-pro' ); ?></option>
						</select>
					</label>
					<label class="psp-check-field">
						<input type="checkbox" name="slider_loop" value="1" <?php checked( $settings['loop'] ); ?> />
						<span><?php echo esc_html__( 'Loop to first slide', 'my-slider-pro' ); ?></span>
					</label>
					<label class="psp-check-field">
						<input type="checkbox" name="slider_pause_on_hover" value="1" <?php checked( $settings['pause_on_hover'] ); ?> />
						<span><?php echo esc_html__( 'Pause on hover', 'my-slider-pro' ); ?></span>
					</label>
				</div>
			</div>
		</div>
		</div>
		<?php
	}

	/**
	 * Render save/delete notices from an allow-listed query flag.
	 *
	 * @return void
	 */
	private static function render_notice(): void {
		$saved   = self::requested_flag( 'saved' );
		$deleted = self::requested_flag( 'deleted' );

		if ( '1' === $saved ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Slider saved.', 'my-slider-pro' ) . '</p></div>';
		}

		if ( '1' === $deleted ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Slider moved to the trash. Media Library images were not deleted.', 'my-slider-pro' ) . '</p></div>';
		}

		if ( '1' === self::requested_flag( 'duplicated' ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Slider duplicated as a new draft.', 'my-slider-pro' ) . '</p></div>';
		}

		if ( '1' === self::requested_flag( 'imported' ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Slider imported as a new draft. Bundled images were added to the Media Library.', 'my-slider-pro' ) . '</p></div>';
		}

		$import_error = self::requested_flag( 'import_error' );

		if ( '' !== $import_error ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( self::import_error_message( $import_error ) ) . '</p></div>';
		}
	}

	/**
	 * Map an import error code to a human-readable message.
	 *
	 * @param string $code Error code from the transfer engine.
	 * @return string
	 */
	private static function import_error_message( string $code ): string {
		$messages = array(
			'psp_no_zip'              => __( 'ZIP support (the PHP zip extension) is required to import sliders.', 'my-slider-pro' ),
			'psp_no_file'            => __( 'Choose a ZIP file to import.', 'my-slider-pro' ),
			'psp_upload_failed'      => __( 'The file could not be uploaded.', 'my-slider-pro' ),
			'psp_not_zip'            => __( 'Imports must be a .zip file.', 'my-slider-pro' ),
			'psp_zip_size'           => __( 'The uploaded file is missing or too large.', 'my-slider-pro' ),
			'psp_zip_read'           => __( 'The uploaded file is not a valid ZIP archive.', 'my-slider-pro' ),
			'psp_zip_entries'        => __( 'The archive contains too many files.', 'my-slider-pro' ),
			'psp_zip_bomb'           => __( 'The archive contents are too large to import.', 'my-slider-pro' ),
			'psp_no_manifest'        => __( 'The archive does not contain a slider definition.', 'my-slider-pro' ),
			'psp_bad_manifest'       => __( 'The slider definition could not be read.', 'my-slider-pro' ),
			'psp_wrong_format'       => __( 'This file is not a MY Slider PRO export.', 'my-slider-pro' ),
			'psp_unsupported_version' => __( 'This export was made with a newer version of MY Slider PRO.', 'my-slider-pro' ),
			'psp_no_slides'          => __( 'The slider definition contains no slides.', 'my-slider-pro' ),
			'psp_insert'             => __( 'The imported slider could not be created.', 'my-slider-pro' ),
		);

		return isset( $messages[ $code ] ) ? $messages[ $code ] : __( 'The slider could not be imported.', 'my-slider-pro' );
	}

	/**
	 * Render compact previous/next navigation for the bounded overview query.
	 *
	 * @param int  $current_page Current one-based page number.
	 * @param bool $has_next     Whether another page of manageable sliders exists.
	 * @return void
	 */
	private static function render_pagination( int $current_page, bool $has_next ): void {
		if ( 1 === $current_page && ! $has_next ) {
			return;
		}
		?>
		<nav class="psp-pagination" aria-label="<?php echo esc_attr__( 'Slider pages', 'my-slider-pro' ); ?>">
			<?php if ( $current_page > 1 ) : ?>
				<a class="button" href="<?php echo esc_url( self::get_overview_page_url( $current_page - 1 ) ); ?>">&larr; <?php echo esc_html__( 'Previous', 'my-slider-pro' ); ?></a>
			<?php endif; ?>
			<span>
				<?php
				printf(
					/* translators: %d: current slider overview page number. */
					esc_html__( 'Page %d', 'my-slider-pro' ),
					$current_page
				);
				?>
			</span>
			<?php if ( $has_next ) : ?>
				<a class="button" href="<?php echo esc_url( self::get_overview_page_url( $current_page + 1 ) ); ?>"><?php echo esc_html__( 'Next', 'my-slider-pro' ); ?> &rarr;</a>
			<?php endif; ?>
		</nav>
		<?php
	}

	/**
	 * Return a paginated overview URL.
	 *
	 * @param int $page_number One-based page number.
	 * @return string
	 */
	private static function get_overview_page_url( int $page_number ): string {
		return add_query_arg(
			array(
				'page'         => self::PAGE_SLUG,
				'slider_page' => max( 1, $page_number ),
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Build safe wp_enqueue_media arguments for the requested editor object.
	 *
	 * WordPress expects a real post when the post argument is supplied, so an
	 * invalid or unauthorized query value must never be forwarded.
	 *
	 * @return array<string,int>
	 */
	private static function media_library_args(): array {
		$slider_id = self::requested_slider_id();

		if ( $slider_id < 1 ) {
			return array();
		}

		$slider = get_post( $slider_id );

		if (
			! $slider ||
			SliderPostType::POST_TYPE !== $slider->post_type ||
			! self::is_editable_status( $slider ) ||
			! current_user_can( 'edit_post', $slider_id )
		) {
			return array();
		}

		return array( 'post' => $slider_id );
	}

	/**
	 * Validate Media Library selections against the current user's object access.
	 *
	 * @param mixed $value Submitted attachment IDs.
	 * @return array<int,int>
	 */
	private static function sanitize_submitted_image_ids( $value ): array {
		return array_values(
			array_filter(
				SliderPostType::sanitize_image_ids( $value ),
				static function ( int $attachment_id ): bool {
					return current_user_can( 'edit_post', $attachment_id );
				}
			)
		);
	}

	/**
	 * Return a safe character count with a conservative extension fallback.
	 *
	 * @param string $text Text to measure.
	 * @return int
	 */
	private static function text_length( string $text ): int {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
	}

	/**
	 * Determine whether a slider is in a state the editor may modify.
	 *
	 * Mirrors core's Trash behavior: a trashed slider must not be edited or
	 * silently republished by the save handler, which forces publish status.
	 * The allow list matches the statuses the overview query lists.
	 *
	 * @param object $slider Slider post object.
	 * @return bool
	 */
	private static function is_editable_status( $slider ): bool {
		$status = isset( $slider->post_status ) ? (string) $slider->post_status : '';

		return in_array( $status, array( 'publish', 'draft', 'pending', 'private' ), true );
	}

	/**
	 * Require slider-management capability.
	 *
	 * @return void
	 */
	private static function authorize(): void {
		if ( ! self::can_manage_sliders() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'my-slider-pro' ) );
		}
	}

	/**
	 * Determine whether the current user can access all slider-management flows.
	 *
	 * @return bool
	 */
	private static function can_manage_sliders(): bool {
		return current_user_can( self::CAPABILITY ) && current_user_can( 'edit_posts' ) && current_user_can( 'publish_posts' );
	}

	/**
	 * Require POST for mutating handlers.
	 *
	 * @return void
	 */
	private static function require_post_request(): void {
		$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : '';

		if ( 'POST' !== $request_method ) {
			wp_die( esc_html__( 'This action requires a POST request.', 'my-slider-pro' ) );
		}
	}

	/**
	 * Read an integer from POST without accepting arrays or objects.
	 *
	 * @param string $key POST key.
	 * @return int
	 */
	private static function posted_integer( string $key ): int {
		if ( ! isset( $_POST[ $key ] ) || is_array( $_POST[ $key ] ) || is_object( $_POST[ $key ] ) ) {
			return 0;
		}

		return absint( wp_unslash( $_POST[ $key ] ) );
	}

	/**
	 * Read sanitized text from POST without accepting arrays or objects.
	 *
	 * @param string $key POST key.
	 * @return string
	 */
	private static function posted_text( string $key ): string {
		if ( ! isset( $_POST[ $key ] ) || is_array( $_POST[ $key ] ) || is_object( $_POST[ $key ] ) ) {
			return '';
		}

		return sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
	}

	/**
	 * Return only submitted slide content belonging to selected attachments.
	 *
	 * @param array<int, int> $image_ids Authorized selected image IDs.
	 * @return array<int, array<string, mixed>>
	 */
	private static function posted_slide_content( array $image_ids ): array {
		$content = $_POST['my_slider_pro_slide_content'] ?? array();
		$content = is_array( $content ) ? wp_unslash( $content ) : array();

		return SliderPostType::sanitize_slide_content( $content, $image_ids );
	}

	/**
	 * Read a slider ID from the current admin request.
	 *
	 * @return int
	 */
	private static function requested_slider_id(): int {
		if ( ! isset( $_GET['slider_id'] ) || is_array( $_GET['slider_id'] ) || is_object( $_GET['slider_id'] ) ) {
			return 0;
		}

		return absint( wp_unslash( $_GET['slider_id'] ) );
	}

	/**
	 * Read the requested editor workspace from an allow list.
	 *
	 * @return string
	 */
	private static function requested_editor_view(): string {
		if ( ! isset( $_GET['editor_view'] ) || is_array( $_GET['editor_view'] ) || is_object( $_GET['editor_view'] ) ) {
			return '';
		}

		$view = sanitize_text_field( wp_unslash( $_GET['editor_view'] ) );

		return in_array( $view, array( 'manage', 'preview' ), true ) ? $view : '';
	}

	/**
	 * Read the bounded one-based overview page number.
	 *
	 * @return int
	 */
	private static function requested_page_number(): int {
		if ( ! isset( $_GET['slider_page'] ) || is_array( $_GET['slider_page'] ) || is_object( $_GET['slider_page'] ) ) {
			return 1;
		}

		return max( 1, absint( wp_unslash( $_GET['slider_page'] ) ) );
	}

	/**
	 * Read an allow-listed scalar query flag.
	 *
	 * @param string $key Query key.
	 * @return string
	 */
	private static function requested_flag( string $key ): string {
		if ( ! isset( $_GET[ $key ] ) || is_array( $_GET[ $key ] ) || is_object( $_GET[ $key ] ) ) {
			return '';
		}

		return sanitize_key( wp_unslash( $_GET[ $key ] ) );
	}
}
