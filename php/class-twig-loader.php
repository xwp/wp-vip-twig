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

	function setPaths( $paths, $namespace = parent::MAIN_NAMESPACE ) {
		if ( $this->plugin->is_wp_vip_env() ) {
			foreach ( $paths as $path ) {
				if ( file_exists( $path ) && ! $this->plugin->is_valid_source_directory( $path ) ) {
					throw new Exception( 'Invalid template source directory: ' . $path );
				}
			}
		}
		parent::setPaths( $paths, $namespace );
	}

}
