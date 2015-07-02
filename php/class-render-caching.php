<?php

namespace VIP_Twig;

class Render_Caching {

	const INVALIDATE_ACTION = 'vip_twig_invalidate_render_cache';

	const OBJECT_CACHE_GROUP = 'vip-twig';

	/**
	 * @var Plugin
	 */
	public $plugin;

	/**
	 * @param Plugin $plugin
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;

		add_action( 'wp_ajax_' . self::INVALIDATE_ACTION, array( $this, 'handle_invalidate_request' ) );
		add_action( 'wp_ajax_nopriv_' . self::INVALIDATE_ACTION, array( $this, 'handle_invalidate_request' ) );
	}

	/**
	 * Get the incrementor used to salt the render cache.
	 *
	 * @link https://www.tollmanz.com/invalidation-schemes/#salting-with-an-incrementor
	 *
	 * @return int $incrementor
	 */
	function get_incrementor() {
		$salt = wp_cache_get( 'render_cache_incrementor', self::OBJECT_CACHE_GROUP );
		if ( empty( $salt ) ) {
			$salt = 0;
		}
		return $salt;
	}

	/**
	 * Bump the incrementor used to salt the render cache.
	 *
	 * @link https://www.tollmanz.com/invalidation-schemes/#salting-with-an-incrementor
	 *
	 * @return int $incrementor
	 */
	function bump_incrementor() {
		$incrementor = microtime( true );
		wp_cache_set( 'render_cache_incrementor', $incrementor, self::OBJECT_CACHE_GROUP );
		return $incrementor;
	}

	/**
	 * Get the cache key for a given template and context.
	 *
	 * @param string $name Template
	 * @param array $context Data
	 * @return string|mixed
	 */
	function get_cache_key( $name, $context ) {
		$incrementor = $this->get_incrementor();

		/**
		 * Allow the incrementor used in a cache key to be filtered.
		 *
		 * This allows a theme/plugin to bust the cache via a changing revision number.
		 *
		 * @param int $incrementor
		 */
		$incrementor = apply_filters( 'vip_twig_cache_key_incrementor', $incrementor );

		$cache_key = md5( serialize( compact( 'name', 'context', 'incrementor' ) ) );
		return $cache_key;
	}

	/**
	 * @param string $name Template
	 * @param array $context Data
	 * @param callable $renderer
	 * @return string
	 */
	function wrap_render( $name, $context, $renderer ) {
		$cache_key = $this->get_cache_key( $name, $context );
		$rendered = wp_cache_get( $cache_key, self::OBJECT_CACHE_GROUP );
		if ( false === $rendered ) {
			$rendered = call_user_func( $renderer, $name, $context );
			wp_cache_set( $cache_key, $rendered, self::OBJECT_CACHE_GROUP );
		}
		return $rendered;
	}

	/**
	 * Handle ajax request to invalidate the render cache.
	 *
	 * @action wp_ajax_vip_twig_invalidate_render_cache
	 * @action wp_ajax_nopriv_vip_twig_invalidate_render_cache
	 */
	function handle_invalidate_request() {
		if ( ! empty( $this->plugin->config['invalidate_render_cache_auth_key'] ) ) {
			if ( ! isset( $_REQUEST['auth_key'] ) ) { // input var okay
				status_header( 403 );
				wp_send_json_error( 'Missing auth_key param' );
			}
			if ( sanitize_text_field( wp_unslash( $_REQUEST['auth_key'] ) ) !== $this->plugin->config['invalidate_render_cache_auth_key'] ) { // input var okay
				status_header( 403 );
				wp_send_json_error( 'Invalid auth_key param' );
			}
		}
		if ( $this->plugin->config['render_cache_ttl'] <= 0 ) {
			status_header( 403 );
			wp_send_json_error( 'render cache is not enabled' );
		}
		$this->bump_incrementor();
		wp_send_json_success( array( 'incrementor' => $this->get_incrementor() ) );
	}
}
