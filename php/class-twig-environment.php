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

	public function disableStrictVariables() {
		if ( $this->plugin->is_wp_vip_env() && ! $this->plugin->is_wp_debug() ) {
			throw new Exception( 'Strict variables cannot be disabled on VIP without WP_DEBUG enabled.' );
		}
		parent::disableStrictVariables();
	}

	public function setCache( $cache ) {
		if ( ! $cache ) {
			$this->plugin->abort_if_precompilation_required();
		} else if ( ! $this->plugin->is_valid_cache_directory( $cache ) ) {
			throw new Exception( 'Invalid cache directory: ' . $cache );
		}
		parent::setCache( $cache );
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
