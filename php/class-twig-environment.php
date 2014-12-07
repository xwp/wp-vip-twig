<?php

namespace VIP_Twig;

class Twig_Environment extends \Twig_Environment {
	/**
	 * @var Plugin
	 */
	public $plugin;

	/**
	 * @param Plugin $plugin
	 * @param Twig_Loader $loader  The parent constructor only specifies Twig_LoaderInterface, but we want to force Twig_Loader which extends Twig_Loader and has that interface.
	 * @param array $options
	 */
	public function __construct( Plugin $plugin, Twig_Loader $loader, $options = array() ) {
		$this->plugin = $plugin;
		parent::__construct( $loader, $options );
	}

	public function enableDebug() {
		$this->plugin->abort_if_is_wp_vip_env();
		parent::enableDebug();
	}

	public function enableAutoReload() {
		$this->plugin->abort_if_precompilation_required();
		return parent::enableAutoReload();
	}

	public function isAutoReload() {
		if ( $this->plugin->is_precompilation_required() ) {
			$auto_reload = false;
		} else {
			$auto_reload = parent::isAutoReload();
		}
		return $auto_reload;
	}

	public function setCache( $cache ) {
		if ( ! $cache ) {
			$this->plugin->abort_if_precompilation_required();
		}
		parent::setCache( $cache );
	}

	/**
	 * Prepare a Twig template name for use in cache filename and class name.
	 *
	 * @param $name
	 * @return string
	 */
	protected function prepareTemplateName( $name ) {
		$name = preg_replace( '#//+#', '/', $name );
		$name = preg_replace( '#\.+/#', '', $name ); // @todo better scrub of relative paths? Can relative paths even be used?
		$name .= '.' . substr( hash( 'sha256', $this->getLoader()->getCacheKey( $name ) ), 0, 6 ); // @todo This probably is not necessary
		return $name;
	}

	/**
	 * Gets the cache filename for a given template.
	 *
	 * @param string $name The template name
	 *
	 * @return string|false The cache file name or false when caching is disabled
	 */
	public function getCacheFilename( $name ) {
		if ( false === $this->cache ) {
			return false;
		}
		$cache_filename = $this->prepareTemplateName( $name ) . '.php';
		$cache_filename = $this->getCache() . '/' . $cache_filename;
		return $cache_filename;
	}

	/**
	 * Gets the template class associated with the given string.
	 *
	 * @param string  $name  The name for which to calculate the template class name
	 * @param int     $index The index if it is an embedded template
	 *
	 * @return string The template class name
	 */
	public function getTemplateClass($name, $index = null) {
		$name = $this->prepareTemplateName( $name );
		$name = preg_replace( '/\W/', '_', $name );
		return $this->templateClassPrefix . $name . (null === $index ? '' : '_'.$index);
	}

	/**
	 * Loads a template by name.
	 *
	 * @param string  $name  The template name
	 * @param int     $index The index if it is an embedded template
	 *
	 * @return \Twig_TemplateInterface A template instance representing the given template name
	 *
	 * @throws Exception When the template cannot be found
	 */
	public function loadTemplate( $name, $index = null ) {
		$cls = $this->getTemplateClass( $name, $index );

		if ( isset( $this->loadedTemplates[ $cls ] ) ) {
			return $this->loadedTemplates[ $cls ];
		}

		if ( ! class_exists( $cls, false ) ) {
			$cache = $this->getCacheFilename( $name );
			$not_cached = ( empty( $cache ) || ! is_file( $cache ) );
			if ( $not_cached && $this->plugin->is_precompilation_required() ) {
				throw new Exception( sprintf( 'Unable to compile template %s at runtime.', $name ) );
			}
		}

		return parent::loadTemplate( $name, $index );
	}

	public function isTemplateFresh( $name, $time ) {
		if ( $this->plugin->is_precompilation_required() ) {
			$fresh = true;
		} else {
			$fresh = parent::isTemplateFresh( $name, $time );
		}
		return $fresh;
	}

	public function writeCacheFile( $file, $content ) {
		$this->plugin->abort_if_precompilation_required();
		return parent::writeCacheFile( $file, $content );
	}

	public function clearCacheFiles() {
		$this->plugin->abort_if_precompilation_required();
		return parent::clearCacheFiles();
	}

}
