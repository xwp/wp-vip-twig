<?php

namespace VIP_Twig;

class Twig_Environment extends \Twig_Environment {
	/**
	 * @var Plugin
	 */
	public $plugin;

	public function __construct( Plugin $plugin, \Twig_Loader_Filesystem $loader, $options = array() ) {
		$this->plugin = $plugin;
		parent::__construct( $loader, $options );
	}

	/**
	 * Loads a template by name.
	 *
	 * @param string  $name  The template name
	 * @param int     $index The index if it is an embedded template
	 *
	 * @return \Twig_TemplateInterface A template instance representing the given template name
	 *
	 * @throws \Twig_Error_Loader When the template cannot be found
	 * @throws \Twig_Error_Syntax When an error occurred during compilation
	 */
	public function loadTemplate( $name, $index = null ) {
		$this->plugin->abort_if_precommpilation_required();
		return parent::loadTemplate( $name, $index );
	}

	public function writeCacheFile( $file, $content ) {
		$this->plugin->abort_if_precommpilation_required();
		return parent::writeCacheFile( $file, $content );
	}

	public function clearCacheFiles() {
		$this->plugin->abort_if_precommpilation_required();
		return parent::clearCacheFiles();
	}

}
