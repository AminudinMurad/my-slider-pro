<?php
/**
 * Dependency-free WordPress stubs for MY Slider PRO tests.
 *
 * @package MySliderPro
 */

declare(strict_types=1);

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

/** @var array<string,mixed> $mySlider_test_state */
$mySlider_test_state = array();

/**
 * Reset mutable WordPress test state.
 *
 * @return void
 */
function mySlider_test_reset_state(): void {
	global $mySlider_test_state;

	$mySlider_test_state = array(
		'actions'            => array(),
		'filters'            => array(),
		'fired_actions'      => array(),
		'shortcodes'         => array(),
		'menu_pages'         => array(),
		'submenu_pages'      => array(),
		'post_types'         => array(),
		'posts'              => array(),
		'meta'               => array(),
		'options'            => array(),
		'images'             => array(),
		'allowed'            => true,
		'denied_caps'        => array(),
		'denied_objects'     => array(),
		'current_user_id'    => 7,
		'registered_styles'  => array(),
		'registered_scripts' => array(),
		'enqueued_styles'    => array(),
		'enqueued_scripts'   => array(),
		'localized'          => array(),
		'media_calls'        => array(),
		'last_get_posts_args'=> array(),
		'current_post'       => null,
		'is_singular'        => false,
		'unique_id'          => 0,
		'nonce_valid'        => true,
		'redirect'           => '',
		'redirect_throws'    => false,
		'next_post_id'       => 500,
	);
}

mySlider_test_reset_state();

final class WP_Error {
	/** @var string */
	public $message;

	public function __construct( string $message = '' ) {
		$this->message = $message;
	}
}

final class MySliderTestRedirect extends RuntimeException {
}

function plugin_dir_path( string $file ): string {
	return dirname( $file ) . '/';
}

function plugin_dir_url(): string {
	return 'https://example.test/wp-content/plugins/my-slider-pro/';
}

function plugin_basename( string $file ): string {
	return 'my-slider-pro/' . basename( $file );
}

function add_action( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): void {
	global $mySlider_test_state;

	$mySlider_test_state['actions'][ $hook ][] = array(
		'callback'      => $callback,
		'priority'      => $priority,
		'accepted_args' => $accepted_args,
	);
}

function add_filter( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): void {
	global $mySlider_test_state;

	$mySlider_test_state['filters'][ $hook ][] = array(
		'callback'      => $callback,
		'priority'      => $priority,
		'accepted_args' => $accepted_args,
	);
}

function do_action( string $hook ): void {
	global $mySlider_test_state;

	$mySlider_test_state['fired_actions'][] = $hook;
}

function add_shortcode( string $tag, callable $callback ): void {
	global $mySlider_test_state;

	$mySlider_test_state['shortcodes'][ $tag ] = $callback;
}

function add_menu_page(
	string $page_title,
	string $menu_title,
	string $capability,
	string $menu_slug,
	callable $callback,
	string $icon_url = '',
	$position = null
): string {
	global $mySlider_test_state;

	$hook = 'toplevel_page_' . $menu_slug;
	$mySlider_test_state['menu_pages'][ $menu_slug ] = compact(
		'page_title',
		'menu_title',
		'capability',
		'menu_slug',
		'callback',
		'icon_url',
		'position',
		'hook'
	);

	return $hook;
}

function add_submenu_page(
	string $parent_slug,
	string $page_title,
	string $menu_title,
	string $capability,
	string $menu_slug,
	callable $callback
): string {
	global $mySlider_test_state;

	$hook = $parent_slug . '_page_' . $menu_slug;
	$mySlider_test_state['submenu_pages'][ $menu_slug ] = compact(
		'parent_slug',
		'page_title',
		'menu_title',
		'capability',
		'menu_slug',
		'callback',
		'hook'
	);

	return $hook;
}

/**
 * Resolve the effective admin page title using WordPress submenu precedence.
 *
 * @param string $menu_slug Registered menu slug.
 * @return string
 */
function mySlider_test_admin_page_title( string $menu_slug ): string {
	global $mySlider_test_state;

	if ( isset( $mySlider_test_state['submenu_pages'][ $menu_slug ]['page_title'] ) ) {
		return (string) $mySlider_test_state['submenu_pages'][ $menu_slug ]['page_title'];
	}

	return (string) ( $mySlider_test_state['menu_pages'][ $menu_slug ]['page_title'] ?? '' );
}

function register_post_type( string $post_type, array $args ) {
	global $mySlider_test_state;

	$mySlider_test_state['post_types'][ $post_type ] = $args;

	return (object) array( 'name' => $post_type );
}

function esc_html__( string $text, string $domain = '' ): string {
	unset( $domain );
	return esc_html( $text );
}

function esc_attr__( string $text, string $domain = '' ): string {
	unset( $domain );
	return esc_attr( $text );
}

function __( string $text, string $domain = '' ): string {
	unset( $domain );
	return $text;
}

function _n( string $single, string $plural, int $number, string $domain = '' ): string {
	unset( $domain );
	return 1 === $number ? $single : $plural;
}

function esc_html( string $text ): string {
	return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
}

function esc_attr( string $text ): string {
	return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
}

function esc_textarea( string $text ): string {
	return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
}

function esc_url( string $url ): string {
	return filter_var( $url, FILTER_SANITIZE_URL );
}

function esc_url_raw( string $url ): string {
	$url = trim( $url );

	if ( '' === $url ) {
		return '';
	}

	if ( preg_match( '/^([a-z][a-z0-9+.-]*):/i', $url, $matches ) && ! in_array( strtolower( $matches[1] ), array( 'http', 'https', 'mailto', 'tel' ), true ) ) {
		return '';
	}

	return filter_var( $url, FILTER_SANITIZE_URL );
}

function esc_js( string $text ): string {
	return str_replace( array( "\r", "\n", "'", '"' ), array( '', '\\n', "\\'", '\\"' ), $text );
}

function admin_url( string $path = '' ): string {
	return 'https://example.test/wp-admin/' . ltrim( $path, '/' );
}

function rest_url( string $path = '' ): string {
	return 'https://example.test/wp-json/' . ltrim( $path, '/' );
}

function wp_create_nonce( $action = -1 ): string {
	return 'test-nonce-' . md5( (string) $action );
}

function add_query_arg( $key, $value = null, $url = null ): string {
	if ( is_array( $key ) ) {
		$args = $key;
		$url  = is_string( $value ) ? $value : '';
	} else {
		$args = array( (string) $key => $value );
		$url  = is_string( $url ) ? $url : '';
	}

	$separator = false === strpos( $url, '?' ) ? '?' : '&';
	return $url . $separator . http_build_query( $args );
}

function current_user_can( string $capability, ...$args ): bool {
	global $mySlider_test_state;
	$object_id = isset( $args[0] ) ? absint( $args[0] ) : 0;

	return $mySlider_test_state['allowed'] &&
		empty( $mySlider_test_state['denied_caps'][ $capability ] ) &&
		( 0 === $object_id || empty( $mySlider_test_state['denied_objects'][ $capability ][ $object_id ] ) );
}

function get_option( string $name, $default = false ) {
	global $mySlider_test_state;

	return array_key_exists( $name, $mySlider_test_state['options'] ) ? $mySlider_test_state['options'][ $name ] : $default;
}

function update_option( string $name, $value ): bool {
	global $mySlider_test_state;
	$mySlider_test_state['options'][ $name ] = $value;

	return true;
}

function delete_option( string $name ): bool {
	global $mySlider_test_state;
	unset( $mySlider_test_state['options'][ $name ] );

	return true;
}

function wp_die( string $message ): void {
	throw new RuntimeException( $message );
}

function absint( $value ): int {
	return abs( (int) $value );
}

function sanitize_key( $key ): string {
	$key = strtolower( (string) $key );
	return preg_replace( '/[^a-z0-9_\-]/', '', $key ) ?? '';
}

function sanitize_text_field( $value ): string {
	if ( ! is_scalar( $value ) ) {
		return '';
	}

	return trim( strip_tags( (string) $value ) );
}

function sanitize_textarea_field( $value ): string {
	if ( ! is_scalar( $value ) ) {
		return '';
	}

	return trim( str_replace( array( "\r\n", "\r" ), "\n", strip_tags( (string) $value ) ) );
}

function wp_unslash( $value ) {
	if ( is_array( $value ) ) {
		return array_map( 'wp_unslash', $value );
	}

	return is_string( $value ) ? stripslashes( $value ) : $value;
}

function wp_attachment_is_image( int $attachment_id ): bool {
	global $mySlider_test_state;
	return isset( $mySlider_test_state['images'][ $attachment_id ] );
}

function get_post_meta( int $post_id, string $key = '', bool $single = false ) {
	global $mySlider_test_state;
	unset( $single );

	if ( isset( $mySlider_test_state['meta'][ $post_id ][ $key ] ) ) {
		return $mySlider_test_state['meta'][ $post_id ][ $key ];
	}

	if ( '_wp_attachment_image_alt' === $key && isset( $mySlider_test_state['images'][ $post_id ]['alt'] ) ) {
		return $mySlider_test_state['images'][ $post_id ]['alt'];
	}

	return '';
}

function update_post_meta( int $post_id, string $key, $value ): bool {
	global $mySlider_test_state;

	$mySlider_test_state['meta'][ $post_id ][ $key ] = $value;
	return true;
}

function get_post( int $post_id ) {
	global $mySlider_test_state;
	return $mySlider_test_state['posts'][ $post_id ] ?? null;
}

function get_post_status( $post = null ) {
	global $mySlider_test_state;

	$post_id = is_object( $post ) && isset( $post->ID ) ? absint( $post->ID ) : absint( $post );

	if ( isset( $mySlider_test_state['posts'][ $post_id ]->post_status ) ) {
		return (string) $mySlider_test_state['posts'][ $post_id ]->post_status;
	}

	if ( isset( $mySlider_test_state['images'][ $post_id ] ) ) {
		return isset( $mySlider_test_state['images'][ $post_id ]['status'] )
			? (string) $mySlider_test_state['images'][ $post_id ]['status']
			: 'publish';
	}

	return false;
}

function get_posts( array $args = array() ): array {
	global $mySlider_test_state;
	$mySlider_test_state['last_get_posts_args'] = $args;
	$posts = array_values( $mySlider_test_state['posts'] );

	$posts = array_values(
		array_filter(
			$posts,
			static function ( $post ) use ( $args ): bool {
				if ( isset( $args['post_type'] ) && $args['post_type'] !== $post->post_type ) {
					return false;
				}

				if ( isset( $args['post_status'] ) ) {
					$statuses = (array) $args['post_status'];
					if ( ! in_array( $post->post_status, $statuses, true ) ) {
						return false;
					}
				}

				if ( isset( $args['author'] ) && absint( $args['author'] ) !== absint( $post->post_author ?? 0 ) ) {
					return false;
				}

				return true;
			}
		)
	);

	if ( isset( $args['offset'] ) || isset( $args['posts_per_page'] ) ) {
		$offset = isset( $args['offset'] ) ? max( 0, absint( $args['offset'] ) ) : 0;
		$limit  = isset( $args['posts_per_page'] ) ? (int) $args['posts_per_page'] : 0;
		$posts  = array_slice( $posts, $offset, $limit > 0 ? $limit : null );
	}

	return $posts;
}

function get_the_title( int $post_id ): string {
	global $mySlider_test_state;

	if ( isset( $mySlider_test_state['posts'][ $post_id ]->post_title ) ) {
		return (string) $mySlider_test_state['posts'][ $post_id ]->post_title;
	}

	return isset( $mySlider_test_state['images'][ $post_id ]['title'] ) ? (string) $mySlider_test_state['images'][ $post_id ]['title'] : '';
}

function wp_get_attachment_image( int $attachment_id, $size = 'thumbnail', bool $icon = false, $attr = '' ): string {
	global $mySlider_test_state;
	unset( $size, $icon );

	if ( ! isset( $mySlider_test_state['images'][ $attachment_id ] ) ) {
		return '';
	}

	$image = $mySlider_test_state['images'][ $attachment_id ];
	$attr  = is_array( $attr ) ? $attr : array();
	$class = isset( $attr['class'] ) ? (string) $attr['class'] : 'attachment-thumbnail';
	$alt   = array_key_exists( 'alt', $attr ) ? (string) $attr['alt'] : ( isset( $image['alt'] ) ? (string) $image['alt'] : '' );
	$extra = '';

	foreach ( $attr as $key => $value ) {
		if ( in_array( $key, array( 'class', 'alt' ), true ) || ! is_scalar( $value ) ) {
			continue;
		}

		$extra .= sprintf( ' %1$s="%2$s"', esc_attr( (string) $key ), esc_attr( (string) $value ) );
	}

	return sprintf(
		'<img data-image-id="%1$d" src="%2$s" srcset="%2$s 800w" sizes="100vw" class="%3$s" alt="%4$s"%5$s />',
		$attachment_id,
		esc_url( (string) $image['url'] ),
		esc_attr( $class ),
		esc_attr( $alt ),
		$extra
	);
}

function wp_get_attachment_image_url( int $attachment_id, $size = 'thumbnail' ) {
	global $mySlider_test_state;
	unset( $size );
	return $mySlider_test_state['images'][ $attachment_id ]['url'] ?? false;
}

function wp_get_attachment_caption( int $attachment_id ): string {
	global $mySlider_test_state;
	return isset( $mySlider_test_state['images'][ $attachment_id ]['caption'] ) ? (string) $mySlider_test_state['images'][ $attachment_id ]['caption'] : '';
}

function wp_register_style( string $handle, string $src, array $deps = array(), $version = false ): bool {
	global $mySlider_test_state;
	$mySlider_test_state['registered_styles'][ $handle ] = compact( 'src', 'deps', 'version' );
	return true;
}

function wp_register_script( string $handle, string $src, array $deps = array(), $version = false, $in_footer = false ): bool {
	global $mySlider_test_state;
	$mySlider_test_state['registered_scripts'][ $handle ] = compact( 'src', 'deps', 'version', 'in_footer' );
	return true;
}

function wp_enqueue_style( string $handle, string $src = '', array $deps = array(), $version = false ): void {
	global $mySlider_test_state;
	if ( '' !== $src ) {
		wp_register_style( $handle, $src, $deps, $version );
	}
	$mySlider_test_state['enqueued_styles'][ $handle ] = true;
}

function wp_enqueue_script( string $handle, string $src = '', array $deps = array(), $version = false, $in_footer = false ): void {
	global $mySlider_test_state;
	if ( '' !== $src ) {
		wp_register_script( $handle, $src, $deps, $version, $in_footer );
	}
	$mySlider_test_state['enqueued_scripts'][ $handle ] = true;
}

function wp_style_is( string $handle, string $status = 'enqueued' ): bool {
	global $mySlider_test_state;
	return 'registered' === $status ? isset( $mySlider_test_state['registered_styles'][ $handle ] ) : isset( $mySlider_test_state['enqueued_styles'][ $handle ] );
}

function wp_script_is( string $handle, string $status = 'enqueued' ): bool {
	global $mySlider_test_state;
	return 'registered' === $status ? isset( $mySlider_test_state['registered_scripts'][ $handle ] ) : isset( $mySlider_test_state['enqueued_scripts'][ $handle ] );
}

function wp_localize_script( string $handle, string $object_name, array $data ): bool {
	global $mySlider_test_state;
	$mySlider_test_state['localized'][ $handle ][ $object_name ] = $data;
	return true;
}

function wp_enqueue_media( array $args = array() ): void {
	global $mySlider_test_state;
	$mySlider_test_state['media_calls'][] = $args;
}

function is_singular(): bool {
	global $mySlider_test_state;
	return (bool) $mySlider_test_state['is_singular'];
}

function get_queried_object() {
	global $mySlider_test_state;
	return $mySlider_test_state['current_post'];
}

function has_shortcode( string $content, string $tag ): bool {
	return false !== strpos( $content, '[' . $tag );
}

function shortcode_atts( array $pairs, $atts, string $shortcode = '' ): array {
	unset( $shortcode );
	return array_merge( $pairs, array_intersect_key( (array) $atts, $pairs ) );
}

function wp_unique_id( string $prefix = '' ): string {
	global $mySlider_test_state;
	++$mySlider_test_state['unique_id'];
	return $prefix . $mySlider_test_state['unique_id'];
}

function wp_nonce_field( string $action = '-1', string $name = '_wpnonce', bool $referer = true, bool $display = true ): string {
	unset( $referer );
	$field = sprintf( '<input type="hidden" name="%s" value="nonce-%s" />', esc_attr( $name ), esc_attr( $action ) );

	if ( $display ) {
		echo $field;
	}

	return $field;
}

function check_admin_referer( string $action = '-1', string $query_arg = '_wpnonce' ): int {
	global $mySlider_test_state;
	unset( $action, $query_arg );

	if ( ! $mySlider_test_state['nonce_valid'] ) {
		wp_die( 'Invalid nonce.' );
	}

	return 1;
}

function selected( $selected, $current = true, bool $display = true ): string {
	$result = (string) $selected === (string) $current ? ' selected="selected"' : '';
	if ( $display ) {
		echo $result;
	}
	return $result;
}

function checked( $checked, $current = true, bool $display = true ): string {
	$result = (string) $checked === (string) $current ? ' checked="checked"' : '';
	if ( $display ) {
		echo $result;
	}
	return $result;
}

function get_current_user_id(): int {
	global $mySlider_test_state;
	return (int) $mySlider_test_state['current_user_id'];
}

function wp_insert_post( array $postarr, bool $wp_error = false ) {
	global $mySlider_test_state;
	unset( $wp_error );
	$id = ++$mySlider_test_state['next_post_id'];
	$mySlider_test_state['posts'][ $id ] = (object) array_merge( array( 'ID' => $id ), $postarr );
	return $id;
}

function wp_update_post( array $postarr, bool $wp_error = false ) {
	global $mySlider_test_state;
	unset( $wp_error );
	$id = absint( $postarr['ID'] ?? 0 );
	if ( ! isset( $mySlider_test_state['posts'][ $id ] ) ) {
		return new WP_Error( 'Missing post.' );
	}
	foreach ( $postarr as $key => $value ) {
		if ( 'ID' !== $key ) {
			$mySlider_test_state['posts'][ $id ]->{$key} = $value;
		}
	}
	return $id;
}

function is_wp_error( $value ): bool {
	return $value instanceof WP_Error;
}

function wp_trash_post( int $post_id ) {
	global $mySlider_test_state;
	if ( isset( $mySlider_test_state['posts'][ $post_id ] ) ) {
		$mySlider_test_state['posts'][ $post_id ]->post_status = 'trash';
		return $mySlider_test_state['posts'][ $post_id ];
	}
	return false;
}

function wp_safe_redirect( string $location ): bool {
	global $mySlider_test_state;
	$mySlider_test_state['redirect'] = $location;

	if ( $mySlider_test_state['redirect_throws'] ) {
		throw new MySliderTestRedirect( $location );
	}

	return true;
}
