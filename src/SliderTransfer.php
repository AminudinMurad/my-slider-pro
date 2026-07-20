<?php
/**
 * Slider import and export as a self-contained ZIP (slider.json + assets/).
 *
 * @package MySliderPro
 */

namespace MySliderPro;

defined( 'ABSPATH' ) || exit;

/**
 * Packages a slider (settings, slides, and referenced images) into a portable
 * ZIP and rebuilds it on another site by sideloading the bundled images.
 */
final class SliderTransfer {

	/**
	 * Format identifier embedded in every export.
	 */
	private const FORMAT = 'my-slider-pro/slider';

	/**
	 * Highest export schema version this build can read and write.
	 */
	private const FORMAT_VERSION = 1;

	/**
	 * ZIP entry that holds the slider definition.
	 */
	private const JSON_ENTRY = 'slider.json';

	/**
	 * Prefix (a folder) every bundled asset lives under.
	 */
	private const ASSET_PREFIX = 'assets/';

	/**
	 * Reject uploads larger than this to bound memory and disk use.
	 */
	private const MAX_ZIP_BYTES = 64 * 1024 * 1024;

	/**
	 * Reject a single bundled asset larger than this.
	 */
	private const MAX_ASSET_BYTES = 32 * 1024 * 1024;

	/**
	 * Reject archives whose contents would inflate beyond this (zip-bomb guard).
	 */
	private const MAX_TOTAL_BYTES = 192 * 1024 * 1024;

	/**
	 * Reject archives with more entries than this.
	 */
	private const MAX_ENTRIES = 600;

	/**
	 * Build a downloadable export archive for a slider.
	 *
	 * @param int $slider_id Slider post ID.
	 * @return string|\WP_Error Absolute path to a temporary ZIP file, or an error.
	 */
	public static function export( int $slider_id ) {
		if ( ! class_exists( '\ZipArchive' ) ) {
			return new \WP_Error( 'psp_no_zip', __( 'ZIP support (the PHP zip extension) is required to export sliders.', 'my-slider-pro' ) );
		}

		$image_ids     = SliderPostType::get_image_ids( $slider_id );
		$slide_content = SliderPostType::get_slide_content( $slider_id, $image_ids );

		$assets    = array();
		$counter   = 0;
		$slides    = array();

		foreach ( $image_ids as $attachment_id ) {
			$content = isset( $slide_content[ $attachment_id ] ) ? $slide_content[ $attachment_id ] : array();

			$slides[] = array(
				'asset'   => self::collect_asset( (int) $attachment_id, $assets, $counter ),
				'content' => self::export_content( $content, $assets, $counter ),
			);
		}

		$document = array(
			'format'         => self::FORMAT,
			'version'        => self::FORMAT_VERSION,
			'plugin_version' => MY_SLIDER_PRO_VERSION,
			'title'          => (string) get_the_title( $slider_id ),
			'settings'       => SliderPostType::get_settings( $slider_id ),
			'slides'         => $slides,
		);

		$json = wp_json_encode( $document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		if ( false === $json ) {
			return new \WP_Error( 'psp_json_encode', __( 'The slider could not be encoded for export.', 'my-slider-pro' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$zip_path = wp_tempnam( 'my-slider-pro-export.zip' );

		if ( ! $zip_path ) {
			return new \WP_Error( 'psp_tempfile', __( 'A temporary export file could not be created.', 'my-slider-pro' ) );
		}

		$zip = new \ZipArchive();

		if ( true !== $zip->open( $zip_path, \ZipArchive::OVERWRITE ) ) {
			self::delete_temp( $zip_path );

			return new \WP_Error( 'psp_zip_open', __( 'The export archive could not be opened for writing.', 'my-slider-pro' ) );
		}

		$zip->addFromString( self::JSON_ENTRY, $json );

		foreach ( $assets as $asset ) {
			if ( is_readable( $asset['file'] ) ) {
				$zip->addFile( $asset['file'], $asset['path'] );
			}
		}

		$zip->close();

		return $zip_path;
	}

	/**
	 * Build the download filename for a slider export.
	 *
	 * @param int $slider_id Slider post ID.
	 * @return string
	 */
	public static function export_filename( int $slider_id ): string {
		$title = sanitize_file_name( (string) get_the_title( $slider_id ) );

		if ( '' === $title ) {
			$title = 'slider';
		}

		return $title . '.zip';
	}

	/**
	 * Recreate a slider from an uploaded export archive.
	 *
	 * @param string $zip_path Absolute path to the uploaded ZIP file.
	 * @return int|\WP_Error New slider post ID, or an error.
	 */
	public static function import( string $zip_path ) {
		if ( ! class_exists( '\ZipArchive' ) ) {
			return new \WP_Error( 'psp_no_zip', __( 'ZIP support (the PHP zip extension) is required to import sliders.', 'my-slider-pro' ) );
		}

		if ( ! is_readable( $zip_path ) || filesize( $zip_path ) > self::MAX_ZIP_BYTES ) {
			return new \WP_Error( 'psp_zip_size', __( 'The uploaded file is missing or too large.', 'my-slider-pro' ) );
		}

		$zip = new \ZipArchive();

		if ( true !== $zip->open( $zip_path ) ) {
			return new \WP_Error( 'psp_zip_read', __( 'The uploaded file is not a valid ZIP archive.', 'my-slider-pro' ) );
		}

		$bounds = self::check_archive_bounds( $zip );

		if ( is_wp_error( $bounds ) ) {
			$zip->close();

			return $bounds;
		}

		$raw = $zip->getFromName( self::JSON_ENTRY );

		if ( false === $raw ) {
			$zip->close();

			return new \WP_Error( 'psp_no_manifest', __( 'The archive does not contain a slider definition.', 'my-slider-pro' ) );
		}

		$document = json_decode( $raw, true );
		$document = self::validate_document( $document );

		if ( is_wp_error( $document ) ) {
			$zip->close();

			return $document;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$new_id = wp_insert_post(
			array(
				'post_type'   => SliderPostType::POST_TYPE,
				'post_status' => 'draft',
				'post_title'  => self::unique_title( (string) $document['title'] ),
				'post_author' => get_current_user_id(),
			),
			true
		);

		if ( is_wp_error( $new_id ) || (int) $new_id < 1 ) {
			$zip->close();

			return new \WP_Error( 'psp_insert', __( 'The imported slider could not be created.', 'my-slider-pro' ) );
		}

		$new_id     = (int) $new_id;
		$sideloaded = array();
		$image_ids  = array();
		$content    = array();

		foreach ( $document['slides'] as $slide ) {
			if ( ! is_array( $slide ) ) {
				continue;
			}

			$asset          = isset( $slide['asset'] ) ? (string) $slide['asset'] : '';
			$attachment_id  = self::import_asset( $zip, $asset, $sideloaded, $new_id );

			if ( $attachment_id < 1 ) {
				// A slide without a resolvable background image cannot be rebuilt.
				continue;
			}

			$slide_content = isset( $slide['content'] ) && is_array( $slide['content'] ) ? $slide['content'] : array();

			$image_ids[]              = $attachment_id;
			$content[ $attachment_id ] = self::import_content( $zip, $slide_content, $sideloaded, $new_id );
		}

		$zip->close();

		SliderPostType::save_meta(
			$new_id,
			$image_ids,
			is_array( $document['settings'] ) ? $document['settings'] : array(),
			$content
		);

		return $new_id;
	}

	/**
	 * Add an attachment's file to the export asset map and return its ZIP path.
	 *
	 * @param int                             $attachment_id Attachment ID.
	 * @param array<int, array<string,string>> $assets       Asset map, keyed by attachment ID.
	 * @param int                             $counter       Incrementing uniqueness counter.
	 * @return string ZIP-relative asset path, or an empty string when unavailable.
	 */
	private static function collect_asset( int $attachment_id, array &$assets, int &$counter ): string {
		if ( $attachment_id < 1 ) {
			return '';
		}

		if ( isset( $assets[ $attachment_id ] ) ) {
			return $assets[ $attachment_id ]['path'];
		}

		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			return '';
		}

		$file = get_attached_file( $attachment_id );

		if ( ! $file || ! is_readable( $file ) ) {
			return '';
		}

		$name = sanitize_file_name( wp_basename( $file ) );

		if ( '' === $name ) {
			$name = 'image';
		}

		$path = self::ASSET_PREFIX . sprintf( '%03d-%s', $counter, $name );
		$counter++;

		$assets[ $attachment_id ] = array(
			'file' => $file,
			'path' => $path,
		);

		return $path;
	}

	/**
	 * Rewrite a slide's local image URLs to bundled asset paths for export.
	 *
	 * @param array<string, mixed>             $content Sanitized slide content.
	 * @param array<int, array<string,string>> $assets  Asset map, keyed by attachment ID.
	 * @param int                             $counter Incrementing uniqueness counter.
	 * @return array<string, mixed>
	 */
	private static function export_content( array $content, array &$assets, int &$counter ): array {
		if ( isset( $content['image_layer_url'] ) && '' !== (string) $content['image_layer_url'] ) {
			$content['image_layer_url'] = self::url_to_asset( (string) $content['image_layer_url'], $assets, $counter );
		}

		if ( isset( $content['extra_layers'] ) && is_array( $content['extra_layers'] ) ) {
			foreach ( $content['extra_layers'] as $index => $layer ) {
				if ( is_array( $layer ) && isset( $layer['url'] ) && '' !== (string) $layer['url'] ) {
					$content['extra_layers'][ $index ]['url'] = self::url_to_asset( (string) $layer['url'], $assets, $counter );
				}
			}
		}

		return $content;
	}

	/**
	 * Convert a local attachment URL to a bundled asset path, or keep it as-is.
	 *
	 * @param string                          $url     Stored image URL.
	 * @param array<int, array<string,string>> $assets Asset map, keyed by attachment ID.
	 * @param int                             $counter Incrementing uniqueness counter.
	 * @return string
	 */
	private static function url_to_asset( string $url, array &$assets, int &$counter ): string {
		$attachment_id = attachment_url_to_postid( $url );

		if ( $attachment_id < 1 ) {
			return $url;
		}

		$path = self::collect_asset( (int) $attachment_id, $assets, $counter );

		return '' !== $path ? $path : $url;
	}

	/**
	 * Sideload one bundled asset and return the new attachment ID.
	 *
	 * Entries are read by their declared name only; archive paths are never used
	 * as filesystem destinations, so traversal in entry names cannot escape.
	 *
	 * @param \ZipArchive                                  $zip        Open archive.
	 * @param string                                       $asset      Declared asset path.
	 * @param array<string, array{id:int,url:string}>      $sideloaded Cache of already-imported assets.
	 * @param int                                          $parent_id  Slider post ID to attach media to.
	 * @return int New attachment ID, or 0 on failure.
	 */
	private static function import_asset( \ZipArchive $zip, string $asset, array &$sideloaded, int $parent_id ): int {
		if ( ! self::is_asset_path( $asset ) ) {
			return 0;
		}

		if ( isset( $sideloaded[ $asset ] ) ) {
			return $sideloaded[ $asset ]['id'];
		}

		$bytes = $zip->getFromName( $asset );

		if ( false === $bytes || '' === $bytes || strlen( $bytes ) > self::MAX_ASSET_BYTES ) {
			return 0;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$name = sanitize_file_name( wp_basename( $asset ) );

		if ( '' === $name ) {
			$name = 'image';
		}

		$tmp = wp_tempnam( $name );

		if ( ! $tmp ) {
			return 0;
		}

		if ( false === file_put_contents( $tmp, $bytes ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			self::delete_temp( $tmp );

			return 0;
		}

		// Only accept entries that decode as real raster images.
		$dimensions = @getimagesize( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		if ( false === $dimensions || empty( $dimensions[0] ) ) {
			self::delete_temp( $tmp );

			return 0;
		}

		$file_array = array(
			'name'     => $name,
			'tmp_name' => $tmp,
		);

		// media_handle_sideload re-validates the MIME against the user's allowed
		// upload types and removes the temporary file for us.
		$attachment_id = media_handle_sideload( $file_array, $parent_id );

		if ( is_wp_error( $attachment_id ) ) {
			self::delete_temp( $tmp );

			return 0;
		}

		$attachment_id            = (int) $attachment_id;
		$sideloaded[ $asset ]     = array(
			'id'  => $attachment_id,
			'url' => (string) wp_get_attachment_url( $attachment_id ),
		);

		return $attachment_id;
	}

	/**
	 * Rewrite bundled asset references in slide content back to live media URLs.
	 *
	 * @param \ZipArchive                             $zip        Open archive.
	 * @param array<string, mixed>                    $content    Slide content from the manifest.
	 * @param array<string, array{id:int,url:string}> $sideloaded Cache of already-imported assets.
	 * @param int                                     $parent_id  Slider post ID to attach media to.
	 * @return array<string, mixed>
	 */
	private static function import_content( \ZipArchive $zip, array $content, array &$sideloaded, int $parent_id ): array {
		if ( isset( $content['image_layer_url'] ) ) {
			$content['image_layer_url'] = self::asset_to_url( $zip, (string) $content['image_layer_url'], $sideloaded, $parent_id );
		}

		if ( isset( $content['extra_layers'] ) && is_array( $content['extra_layers'] ) ) {
			foreach ( $content['extra_layers'] as $index => $layer ) {
				if ( is_array( $layer ) && isset( $layer['url'] ) ) {
					$content['extra_layers'][ $index ]['url'] = self::asset_to_url( $zip, (string) $layer['url'], $sideloaded, $parent_id );
				}
			}
		}

		return $content;
	}

	/**
	 * Resolve a stored value that may be a bundled asset path into a live URL.
	 *
	 * @param \ZipArchive                             $zip        Open archive.
	 * @param string                                  $value      Stored URL or asset path.
	 * @param array<string, array{id:int,url:string}> $sideloaded Cache of already-imported assets.
	 * @param int                                     $parent_id  Slider post ID to attach media to.
	 * @return string
	 */
	private static function asset_to_url( \ZipArchive $zip, string $value, array &$sideloaded, int $parent_id ): string {
		if ( ! self::is_asset_path( $value ) ) {
			// A real, non-bundled URL is preserved untouched.
			return $value;
		}

		if ( ! isset( $sideloaded[ $value ] ) ) {
			self::import_asset( $zip, $value, $sideloaded, $parent_id );
		}

		return isset( $sideloaded[ $value ] ) ? $sideloaded[ $value ]['url'] : '';
	}

	/**
	 * Determine whether a value is a safe in-archive asset reference.
	 *
	 * @param string $value Candidate path.
	 * @return bool
	 */
	private static function is_asset_path( string $value ): bool {
		if ( 0 !== strpos( $value, self::ASSET_PREFIX ) ) {
			return false;
		}

		if ( false !== strpos( $value, '..' ) || false !== strpos( $value, "\0" ) ) {
			return false;
		}

		return 0 === validate_file( $value );
	}

	/**
	 * Reject archives that are oversized or hold too many entries.
	 *
	 * @param \ZipArchive $zip Open archive.
	 * @return true|\WP_Error
	 */
	private static function check_archive_bounds( \ZipArchive $zip ) {
		if ( $zip->numFiles > self::MAX_ENTRIES ) {
			return new \WP_Error( 'psp_zip_entries', __( 'The archive contains too many files.', 'my-slider-pro' ) );
		}

		$total = 0;

		for ( $index = 0; $index < $zip->numFiles; $index++ ) {
			$stat = $zip->statIndex( $index );

			if ( false === $stat ) {
				continue;
			}

			$total += (int) $stat['size'];

			if ( $total > self::MAX_TOTAL_BYTES ) {
				return new \WP_Error( 'psp_zip_bomb', __( 'The archive contents are too large to import.', 'my-slider-pro' ) );
			}
		}

		return true;
	}

	/**
	 * Validate the decoded manifest shape and version.
	 *
	 * @param mixed $document Decoded JSON.
	 * @return array<string, mixed>|\WP_Error
	 */
	private static function validate_document( $document ) {
		if ( ! is_array( $document ) ) {
			return new \WP_Error( 'psp_bad_manifest', __( 'The slider definition could not be read.', 'my-slider-pro' ) );
		}

		$format  = isset( $document['format'] ) ? (string) $document['format'] : '';
		$version = isset( $document['version'] ) ? (int) $document['version'] : 0;

		if ( self::FORMAT !== $format ) {
			return new \WP_Error( 'psp_wrong_format', __( 'This file is not a MY Slider PRO export.', 'my-slider-pro' ) );
		}

		if ( $version < 1 || $version > self::FORMAT_VERSION ) {
			return new \WP_Error( 'psp_unsupported_version', __( 'This export was made with a newer version of MY Slider PRO.', 'my-slider-pro' ) );
		}

		if ( ! isset( $document['slides'] ) || ! is_array( $document['slides'] ) ) {
			return new \WP_Error( 'psp_no_slides', __( 'The slider definition contains no slides.', 'my-slider-pro' ) );
		}

		$document['title']    = isset( $document['title'] ) ? (string) $document['title'] : '';
		$document['settings'] = isset( $document['settings'] ) && is_array( $document['settings'] ) ? $document['settings'] : array();

		return $document;
	}

	/**
	 * Return a slider title that does not collide with an existing one.
	 *
	 * @param string $title Imported title.
	 * @return string
	 */
	private static function unique_title( string $title ): string {
		$base = '' !== trim( $title ) ? trim( $title ) : __( 'Imported slider', 'my-slider-pro' );

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

		if ( ! in_array( $base, $existing, true ) ) {
			return $base;
		}

		for ( $number = 2; $number < 1000; $number++ ) {
			$candidate = sprintf( '%1$s (%2$d)', $base, $number );

			if ( ! in_array( $candidate, $existing, true ) ) {
				return $candidate;
			}
		}

		return $base;
	}

	/**
	 * Remove a temporary file created during transfer.
	 *
	 * @param string $path Absolute path.
	 * @return void
	 */
	private static function delete_temp( string $path ): void {
		if ( $path && is_file( $path ) ) {
			wp_delete_file( $path );
		}
	}
}
