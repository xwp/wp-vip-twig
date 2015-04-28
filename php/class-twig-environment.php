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
	public function getTemplateClass( $name, $index = null ) {
		$name = $this->prepareTemplateName( $name );
		$name = preg_replace( '/\W/', '_', $name );
		return $this->templateClassPrefix . $name . (null === $index ? '' : '_'.$index);
	}

	/**
	 * Renders a template, with the template name and context being filterable.
	 *
	 * @param string $name    The template name
	 * @param array  $context An array of parameters to pass to the template
	 *
	 * @return string The rendered template
	 *
	 * @throws \Twig_Error_Loader  When the template cannot be found
	 * @throws \Twig_Error_Syntax  When an error occurred during compilation
	 * @throws \Twig_Error_Runtime When an error occurred during rendering
	 */
	public function render( $name, array $context = array() ) {

		/**
		 * Filter the template $name used to render the $context.
		 *
		 * @param array $context data
		 * @param string $name of the template
		 */
		$name = apply_filters( 'vip_twig_render_template_name', $name, $context );

		/**
		 * Filter the $context passed when rendering template $name.
		 *
		 * @param array $context data
		 * @param string $name of the template
		 */
		$context = apply_filters( 'vip_twig_render_template_context', $context, $name );

		try {
			if ( empty( $context['disable_render_cache'] ) && $this->plugin->config['render_cache_ttl'] > 0 ) {
				$rendered = $this->plugin->render_caching->wrap_render( $name, $context, function () use ( $name, $context ) {
					// @todo the Twig functions executed should be able to add to the cache context when they are invoked, or to disable caching altogether? No, this is chicken and egg problem.
					// @todo We need to make sure that the dynamic twig functions do not get cached
					return parent::render( $name, $context );
				} );
			}
			else {
				unset( $context['disable_render_cache'] );
				$rendered = parent::render( $name, $context );
			}
		} catch ( \Exception $e ) {
			if ( empty( $this->plugin->config['catch_exceptions'] ) ) {
				throw $e;
			}

			nocache_headers();
			if ( current_user_can( 'edit_theme_options' ) ) {
				$message = get_class( $e ) . ': ' . $e->getMessage();
			} else {
				$message = __( 'Oops. Broken branch?', 'vip-twig' );
				trigger_error( esc_html( $e->getMessage() ), E_USER_WARNING );
			}
			wp_die(
				esc_html( $message ),
				esc_html__( 'Twig exception', 'vip-twig' ),
				array( 'response' => 500 )
			);
		}

		return $rendered;
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
