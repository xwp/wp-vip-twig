<?php

namespace VIP_Twig;

class Twig_Loader extends \Twig_Loader_Filesystem {

	/**
	 * @var Plugin
	 */
	public $plugin;

	function __construct( $plugin, $paths ) {
		$this->plugin = $plugin;
		parent::__construct( $paths );
	}

	/**
	 * {@inheritdoc}
	 */
	function setPaths( $paths, $namespace = parent::MAIN_NAMESPACE ) {
		if ( $this->plugin->is_wpcom_vip() ) {
			foreach ( $paths as $path ) {
				if ( file_exists( $path ) && ! $this->plugin->is_valid_source_directory( $path ) ) {
					throw new Exception( 'Invalid template source directory: ' . $path );
				}
			}
		}
		parent::setPaths( $paths, $namespace );
	}

	/**
	 * Make the method public.
	 *
	 * {@inheritdoc}
	 */
	public function findTemplate( $name ) {
		return parent::findTemplate( $name );
	}

	/**
	 * Allow the freshness of a template to be determined by a plugin, e.g. so that
	 * the last git commit time for the located template can be compared with the
	 * cached file time (Environment::getCacheFilename()).
	 *
	 * {@inheritdoc}
	 */
	public function isFresh( $name, $time ) {
		$is_fresh = parent::isFresh( $name, $time );
		$is_fresh = apply_filters( 'vip_twig_template_is_fresh', $is_fresh, $name, $time, $this->plugin );
		return $is_fresh;
	}
}
