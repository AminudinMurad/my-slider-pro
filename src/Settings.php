<?php
/**
 * Plugin-wide settings: storage, admin modal, and behaviour wiring.
 *
 * @package MySliderPro
 */

namespace MySliderPro;

defined( 'ABSPATH' ) || exit;

/**
 * Stores and applies the General settings shown on the sliders overview.
 */
final class Settings {

	/**
	 * Option key holding the settings array.
	 */
	public const OPTION_KEY = 'my_slider_pro_settings';

	/**
	 * Capability required to view or change settings.
	 *
	 * Settings affect site-wide asset loading, upload security, and data
	 * removal, so they are restricted to administrators rather than the
	 * lower upload_files capability used for slider editing.
	 */
	private const CAPABILITY = 'manage_options';

	private const SAVE_ACTION = 'my_slider_pro_save_settings';

	private const FLUSH_ACTION = 'my_slider_pro_flush_cache';

	/**
	 * Shared nonce action for both settings forms.
	 *
	 * Save and flush post through the same form, so they verify one nonce; the
	 * admin-post `action` field routes to the correct handler.
	 */
	private const NONCE_ACTION = 'my_slider_pro_settings';

	private const NONCE_FIELD = 'my_slider_pro_settings_nonce';

	/**
	 * Google Fonts families the plugin offers, requested from Google's CSS API.
	 */
	private const FONT_URL = 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Montserrat:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap';

	/**
	 * Uploads sub-directory that holds locally hosted font files.
	 */
	private const FONT_DIR = 'my-slider-pro-fonts';

	/**
	 * Register settings hooks.
	 *
	 * @return void
	 */
	public static function boot(): void {
		add_action( 'admin_post_' . self::SAVE_ACTION, array( self::class, 'handle_save' ) );
		add_action( 'admin_post_' . self::FLUSH_ACTION, array( self::class, 'handle_flush' ) );
		add_action( 'admin_notices', array( self::class, 'render_admin_notice' ) );

		if ( self::get( 'allow_svg' ) ) {
			add_filter( 'upload_mimes', array( self::class, 'allow_svg_mimes' ) );
			add_filter( 'wp_check_filetype_and_ext', array( self::class, 'fix_svg_filetype' ), 10, 4 );
			add_filter( 'wp_handle_upload_prefilter', array( self::class, 'sanitize_svg_upload' ) );
		}

		if ( self::get( 'resource_preloading' ) ) {
			add_action( 'wp_head', array( self::class, 'print_preload_links' ), 1 );
		}
	}

	/**
	 * Default values for every setting.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'google_fonts'          => 'enabled',
			'resource_preloading'   => false,
			'allow_svg'             => false,
			'load_assets_everywhere' => false,
			'delete_on_uninstall'   => false,
			'fonts_local_version'   => '',
		);
	}

	/**
	 * Return all settings merged over defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		$stored = get_option( self::OPTION_KEY, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return array_merge( self::defaults(), $stored );
	}

	/**
	 * Return one setting value.
	 *
	 * @param string $key Setting key.
	 * @return mixed
	 */
	public static function get( string $key ) {
		$all = self::all();

		return $all[ $key ] ?? null;
	}

	/**
	 * Sanitize a raw settings payload from the modal form.
	 *
	 * @param array<string, mixed> $raw Raw POST values.
	 * @return array<string, mixed>
	 */
	public static function sanitize( array $raw ): array {
		$fonts = isset( $raw['google_fonts'] ) ? sanitize_key( (string) $raw['google_fonts'] ) : 'enabled';

		if ( ! in_array( $fonts, array( 'enabled', 'disabled', 'local' ), true ) ) {
			$fonts = 'enabled';
		}

		$existing = self::all();

		return array(
			'google_fonts'          => $fonts,
			'resource_preloading'   => ! empty( $raw['resource_preloading'] ),
			'allow_svg'             => ! empty( $raw['allow_svg'] ),
			'load_assets_everywhere' => ! empty( $raw['load_assets_everywhere'] ),
			'delete_on_uninstall'   => ! empty( $raw['delete_on_uninstall'] ),
			// Preserved, not user-editable.
			'fonts_local_version'   => (string) $existing['fonts_local_version'],
		);
	}

	/**
	 * Persist settings submitted from the modal.
	 *
	 * @return void
	 */
	public static function handle_save(): void {
		self::authorize();
		check_admin_referer( self::NONCE_ACTION, self::NONCE_FIELD );

		$raw = isset( $_POST['my_slider_pro_settings'] ) && is_array( $_POST['my_slider_pro_settings'] )
			? wp_unslash( $_POST['my_slider_pro_settings'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			: array();

		$clean  = self::sanitize( $raw );
		$status = 'saved';

		// When switching to locally hosted fonts, fetch and cache them now.
		if ( 'local' === $clean['google_fonts'] ) {
			if ( self::generate_local_fonts() ) {
				$clean['fonts_local_version'] = (string) time();
			} else {
				$clean['google_fonts'] = 'enabled';
				$status                = 'fontfail';
			}
		}

		update_option( self::OPTION_KEY, $clean );

		wp_safe_redirect( self::overview_url( $status ) );
		exit;
	}

	/**
	 * Regenerate cached assets and refresh locally hosted fonts.
	 *
	 * @return void
	 */
	public static function handle_flush(): void {
		self::authorize();
		check_admin_referer( self::NONCE_ACTION, self::NONCE_FIELD );

		if ( 'local' === self::get( 'google_fonts' ) ) {
			self::generate_local_fonts();
			$settings                        = self::all();
			$settings['fonts_local_version'] = (string) time();
			update_option( self::OPTION_KEY, $settings );
		}

		/**
		 * Allow other components to clear their own caches on flush.
		 *
		 * @since 1.0.0
		 */
		do_action( 'my_slider_pro_flush_cache' );

		wp_safe_redirect( self::overview_url( 'flushed' ) );
		exit;
	}

	/**
	 * Render a success/failure notice on the overview after a settings action.
	 *
	 * @return void
	 */
	public static function render_admin_notice(): void {
		if ( ! self::can_manage() ) {
			return;
		}

		// Display-only status flag; no state change occurs from reading it.
		$flag = isset( $_GET['my_slider_pro_settings'] ) ? sanitize_key( wp_unslash( $_GET['my_slider_pro_settings'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( '' === $flag ) {
			return;
		}

		$messages = array(
			'saved'    => array( 'success', __( 'Settings saved.', 'my-slider-pro' ) ),
			'flushed'  => array( 'success', __( 'Cache cleared and assets regenerated.', 'my-slider-pro' ) ),
			'fontfail' => array( 'error', __( 'Settings saved, but Google Fonts could not be downloaded for local hosting, so the remote version stays enabled. Try again or check outbound requests.', 'my-slider-pro' ) ),
		);

		if ( ! isset( $messages[ $flag ] ) ) {
			return;
		}

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $messages[ $flag ][0] ),
			esc_html( $messages[ $flag ][1] )
		);
	}

	/**
	 * Render the Settings button that opens the modal.
	 *
	 * @return void
	 */
	public static function render_button(): void {
		if ( ! self::can_manage() ) {
			return;
		}
		?>
		<button type="button" class="button psp-open-settings" aria-haspopup="dialog" aria-controls="psp-settings-modal">
			<span class="dashicons dashicons-admin-settings" aria-hidden="true"></span><?php echo esc_html__( 'Settings', 'my-slider-pro' ); ?>
		</button>
		<?php
	}

	/**
	 * Render the General settings modal on the overview page.
	 *
	 * @return void
	 */
	public static function render_modal(): void {
		if ( ! self::can_manage() ) {
			return;
		}

		$fonts       = (string) self::get( 'google_fonts' );
		$preloading  = (bool) self::get( 'resource_preloading' );
		$allow_svg   = (bool) self::get( 'allow_svg' );
		$everywhere  = (bool) self::get( 'load_assets_everywhere' );
		$delete_data = (bool) self::get( 'delete_on_uninstall' );
		$action_url  = admin_url( 'admin-post.php' );
		?>
		<div id="psp-settings-modal" class="psp-modal" hidden>
			<div class="psp-modal-backdrop" data-psp-settings-close></div>
			<div class="psp-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="psp-settings-title">
				<form class="psp-modal-form" method="post" action="<?php echo esc_url( $action_url ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::SAVE_ACTION ); ?>" />
					<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD ); ?>
					<header class="psp-modal-head">
						<h2 id="psp-settings-title"><span class="dashicons dashicons-admin-settings" aria-hidden="true"></span><?php echo esc_html__( 'General settings', 'my-slider-pro' ); ?></h2>
						<button type="button" class="psp-modal-x" data-psp-settings-close aria-label="<?php echo esc_attr__( 'Close settings', 'my-slider-pro' ); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
					</header>
					<div class="psp-modal-body">
						<div class="psp-setting-row">
							<div class="psp-setting-main">
								<label class="psp-setting-label" for="psp-setting-google-fonts"><?php echo esc_html__( 'Google fonts', 'my-slider-pro' ); ?></label>
								<select id="psp-setting-google-fonts" name="my_slider_pro_settings[google_fonts]">
									<option value="enabled" <?php selected( $fonts, 'enabled' ); ?>><?php echo esc_html__( 'Load from Google (default)', 'my-slider-pro' ); ?></option>
									<option value="local" <?php selected( $fonts, 'local' ); ?>><?php echo esc_html__( 'Save locally on my host', 'my-slider-pro' ); ?></option>
									<option value="disabled" <?php selected( $fonts, 'disabled' ); ?>><?php echo esc_html__( 'Disable (use theme fonts)', 'my-slider-pro' ); ?></option>
								</select>
							</div>
							<p class="psp-setting-help"><?php echo esc_html__( 'Slider headings use Inter, Montserrat, and Poppins. Save locally to avoid calling Google on every visit (better for privacy and speed).', 'my-slider-pro' ); ?></p>
						</div>

						<div class="psp-setting-row">
							<div class="psp-setting-main">
								<label class="psp-toggle">
									<input type="checkbox" name="my_slider_pro_settings[resource_preloading]" value="1" <?php checked( $preloading ); ?> />
									<span class="psp-toggle-track" aria-hidden="true"></span>
									<span class="psp-setting-label"><?php echo esc_html__( 'Resource preloading', 'my-slider-pro' ); ?></span>
								</label>
							</div>
							<p class="psp-setting-help"><?php echo esc_html__( 'Preload each slider\'s first slide image so it appears sooner (improves Largest Contentful Paint).', 'my-slider-pro' ); ?></p>
						</div>

						<div class="psp-setting-row">
							<div class="psp-setting-main">
								<label class="psp-toggle">
									<input type="checkbox" name="my_slider_pro_settings[allow_svg]" value="1" <?php checked( $allow_svg ); ?> />
									<span class="psp-toggle-track" aria-hidden="true"></span>
									<span class="psp-setting-label"><?php echo esc_html__( 'Allow SVG and JSON upload', 'my-slider-pro' ); ?></span>
								</label>
							</div>
							<p class="psp-setting-help psp-setting-warning"><?php echo esc_html__( 'Attention: allowing SVG uploads is a security risk because SVG files can contain scripts. MY Slider PRO strips scripts on upload, but only enable this if you understand the risk.', 'my-slider-pro' ); ?></p>
						</div>

						<div class="psp-setting-row">
							<div class="psp-setting-main">
								<label class="psp-toggle">
									<input type="checkbox" name="my_slider_pro_settings[load_assets_everywhere]" value="1" <?php checked( $everywhere ); ?> />
									<span class="psp-toggle-track" aria-hidden="true"></span>
									<span class="psp-setting-label"><?php echo esc_html__( 'Load assets on all pages', 'my-slider-pro' ); ?></span>
								</label>
							</div>
							<p class="psp-setting-help"><?php echo esc_html__( 'By default slider CSS and JavaScript load only on pages that use the shortcode. Enable this if you inject sliders via Ajax or a page builder.', 'my-slider-pro' ); ?></p>
						</div>

						<div class="psp-setting-row">
							<div class="psp-setting-main">
								<label class="psp-toggle">
									<input type="checkbox" name="my_slider_pro_settings[delete_on_uninstall]" value="1" <?php checked( $delete_data ); ?> />
									<span class="psp-toggle-track" aria-hidden="true"></span>
									<span class="psp-setting-label"><?php echo esc_html__( 'Delete all data on uninstall', 'my-slider-pro' ); ?></span>
								</label>
							</div>
							<p class="psp-setting-help"><?php echo esc_html__( 'When the plugin is deleted, permanently remove every slider and these settings. Off by default so your sliders survive a reinstall. Media Library images are always kept.', 'my-slider-pro' ); ?></p>
						</div>
					</div>
					<footer class="psp-modal-foot">
						<span class="psp-modal-foot-left">
							<button type="submit" class="button psp-flush-cache" name="action" value="<?php echo esc_attr( self::FLUSH_ACTION ); ?>" formnovalidate>
								<span class="dashicons dashicons-update" aria-hidden="true"></span><?php echo esc_html__( 'Regenerate CSS &amp; flush cache', 'my-slider-pro' ); ?>
							</button>
						</span>
						<span class="psp-modal-foot-right">
							<button type="button" class="button" data-psp-settings-close><?php echo esc_html__( 'Close', 'my-slider-pro' ); ?></button>
							<button type="submit" class="button button-primary"><span class="dashicons dashicons-saved" aria-hidden="true"></span><?php echo esc_html__( 'Save', 'my-slider-pro' ); ?></button>
						</span>
					</footer>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Add SVG and JSON to the permitted upload MIME types.
	 *
	 * @param array<string, string> $mimes Allowed MIME types.
	 * @return array<string, string>
	 */
	public static function allow_svg_mimes( array $mimes ): array {
		$mimes['svg']  = 'image/svg+xml';
		$mimes['svgz'] = 'image/svg+xml';
		$mimes['json'] = 'application/json';

		return $mimes;
	}

	/**
	 * Correct the detected type for SVG uploads so WordPress accepts them.
	 *
	 * @param array<string, mixed> $data     File data (ext, type, proper_filename).
	 * @param string               $file     Full path to the file.
	 * @param string               $filename The name of the file.
	 * @param array<string, string> $mimes   Allowed MIME types.
	 * @return array<string, mixed>
	 */
	public static function fix_svg_filetype( array $data, string $file, string $filename, $mimes ): array {
		unset( $file, $mimes );

		if ( '' === (string) ( $data['type'] ?? '' ) ) {
			$check = wp_check_filetype( $filename, array( 'svg' => 'image/svg+xml', 'svgz' => 'image/svg+xml', 'json' => 'application/json' ) );

			if ( 'svg' === $check['ext'] || 'svgz' === $check['ext'] ) {
				$data['ext']  = $check['ext'];
				$data['type'] = 'image/svg+xml';
			} elseif ( 'json' === $check['ext'] ) {
				$data['ext']  = 'json';
				$data['type'] = 'application/json';
			}
		}

		return $data;
	}

	/**
	 * Strip scripting from SVG uploads before they are stored.
	 *
	 * This is a conservative sanitizer, not a full SVG parser: it removes
	 * script elements, event-handler attributes, and javascript: URLs. It is a
	 * safety net, not a licence to accept SVGs from untrusted authors.
	 *
	 * @param array<string, mixed> $file Upload data with a tmp_name path.
	 * @return array<string, mixed>
	 */
	public static function sanitize_svg_upload( array $file ): array {
		$name = isset( $file['name'] ) ? (string) $file['name'] : '';
		$path = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';

		if ( '' === $path || ! preg_match( '/\.svgz?$/i', $name ) || ! is_file( $path ) ) {
			return $file;
		}

		$contents = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		if ( '' === $contents ) {
			return $file;
		}

		$clean = preg_replace( '#<script[^>]*>.*?</script>#is', '', $contents );
		$clean = preg_replace( '#\son\w+\s*=\s*"[^"]*"#i', '', (string) $clean );
		$clean = preg_replace( "#\son\w+\s*=\s*'[^']*'#i", '', (string) $clean );
		$clean = preg_replace( '#(href|xlink:href)\s*=\s*("|\')\s*javascript:[^"\']*("|\')#i', '', (string) $clean );

		if ( null !== $clean && $clean !== $contents ) {
			file_put_contents( $path, $clean ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents
		}

		return $file;
	}

	/**
	 * Print preload hints for the first image of each slider on the page.
	 *
	 * @return void
	 */
	public static function print_preload_links(): void {
		if ( ! is_singular() ) {
			return;
		}

		$post = get_queried_object();

		if ( ! $post instanceof \WP_Post || '' === (string) $post->post_content ) {
			return;
		}

		if ( ! preg_match_all( '/\[' . SliderShortcode::TAG . '[^\]]*\bid=["\']?(\d+)/', (string) $post->post_content, $matches ) ) {
			return;
		}

		$seen = array();

		foreach ( array_map( 'absint', $matches[1] ) as $slider_id ) {
			if ( 0 === $slider_id || isset( $seen[ $slider_id ] ) ) {
				continue;
			}
			$seen[ $slider_id ] = true;

			$image_url = SliderShortcode::first_image_url( $slider_id );

			if ( '' !== $image_url ) {
				printf(
					'<link rel="preload" as="image" href="%s" fetchpriority="high" />' . "\n",
					esc_url( $image_url )
				);
			}
		}
	}

	/**
	 * Return the Google Fonts mode: enabled, disabled, or local.
	 *
	 * @return string
	 */
	public static function font_mode(): string {
		return (string) self::get( 'google_fonts' );
	}

	/**
	 * Return the remote Google Fonts CSS URL.
	 *
	 * @return string
	 */
	public static function remote_font_url(): string {
		return self::FONT_URL;
	}

	/**
	 * Return the URL of the locally hosted font stylesheet, or '' if missing.
	 *
	 * @return string
	 */
	public static function local_font_url(): string {
		$upload = wp_upload_dir();

		if ( ! empty( $upload['error'] ) ) {
			return '';
		}

		$file = trailingslashit( $upload['basedir'] ) . self::FONT_DIR . '/fonts.css';

		if ( ! is_file( $file ) ) {
			return '';
		}

		$version = (string) self::get( 'fonts_local_version' );
		$url     = trailingslashit( $upload['baseurl'] ) . self::FONT_DIR . '/fonts.css';

		return '' !== $version ? add_query_arg( 'ver', $version, $url ) : $url;
	}

	/**
	 * Download the Google Fonts CSS and font files into the uploads directory.
	 *
	 * @return bool True when the local stylesheet was written.
	 */
	public static function generate_local_fonts(): bool {
		$upload = wp_upload_dir();

		if ( ! empty( $upload['error'] ) ) {
			return false;
		}

		$dir = trailingslashit( $upload['basedir'] ) . self::FONT_DIR;
		$url = trailingslashit( $upload['baseurl'] ) . self::FONT_DIR;

		if ( ! wp_mkdir_p( $dir ) ) {
			return false;
		}

		// A modern browser user-agent makes Google return woff2 sources.
		$response = wp_remote_get(
			self::FONT_URL,
			array(
				'timeout'    => 15,
				'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$css = (string) wp_remote_retrieve_body( $response );

		if ( '' === $css ) {
			return false;
		}

		if ( preg_match_all( '#https://fonts\.gstatic\.com/[^)\'" ]+#', $css, $font_matches ) ) {
			foreach ( array_unique( $font_matches[0] ) as $font_url ) {
				$file_name = md5( $font_url ) . '.woff2';
				$dest      = $dir . '/' . $file_name;

				if ( ! is_file( $dest ) ) {
					$font_response = wp_remote_get( $font_url, array( 'timeout' => 15 ) );

					if ( is_wp_error( $font_response ) || 200 !== (int) wp_remote_retrieve_response_code( $font_response ) ) {
						continue;
					}

					$body = (string) wp_remote_retrieve_body( $font_response );

					if ( '' === $body ) {
						continue;
					}

					file_put_contents( $dest, $body ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents
				}

				$css = str_replace( $font_url, $url . '/' . $file_name, $css );
			}
		}

		return false !== file_put_contents( $dir . '/fonts.css', $css ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents
	}

	/**
	 * Build the overview URL carrying a status flag.
	 *
	 * @param string $status Status flag for the admin notice.
	 * @return string
	 */
	private static function overview_url( string $status ): string {
		return add_query_arg(
			array(
				'page'                   => 'my-slider-pro',
				'my_slider_pro_settings' => $status,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Require an authenticated, capable POST request.
	 *
	 * @return void
	 */
	private static function authorize(): void {
		if ( ! self::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to change these settings.', 'my-slider-pro' ) );
		}
	}

	/**
	 * Whether the current user may manage settings.
	 *
	 * @return bool
	 */
	private static function can_manage(): bool {
		return current_user_can( self::CAPABILITY );
	}
}
