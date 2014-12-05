<?php

namespace VIP_Twig;

class CLI extends \WP_CLI_Command {

	/**
	 * This gets set by Plugin::__construct()
	 *
	 * @var Plugin
	 */
	static public $plugin_instance;

	/**
	 * @var Plugin
	 */
	public $plugin;

	/**
	 *
	 */
	public function __construct() {
		$this->plugin = self::$plugin_instance;
		parent::__construct();
	}

	/**
	 * Re-compile all *.twig templates located on the plugin's loader_template_paths.
	 *
	 * @param array [$args]
	 * @param array $assoc_args
	 */
	public function compile( $args, $assoc_args ) {
		try {

			// TODO: WP-CLI should be doing this by default when --debug is provided
			register_shutdown_function( function () {
				$last_error = error_get_last();
				if ( ! empty( $last_error ) ) {
					print_r( $last_error );
					\WP_CLI::error( sprintf( '%s (type: %d, line: %d, file: %s)', $last_error['message'], $last_error['type'], $last_error['line'], $last_error['file'] ) );
				}
			} );

			if ( empty( $this->plugin->config['loader_template_paths'] ) ) {
				throw new Exception( 'No loader_template_paths config supplied.' );
			}
			if ( empty( $this->plugin->config['environment_options']['cache'] ) ) {
				throw new Exception( 'No cache config supplied.' );
			}
			$cache_dir = $this->plugin->config['environment_options']['cache'];
			if ( ! file_exists( $cache_dir ) && ! mkdir( $cache_dir ) ) {
				throw new Exception( 'Unable to create cache directory: ' . $cache_dir );
			}

			$twig_templates = array();
			foreach ( $this->plugin->config['loader_template_paths'] as $path ) {
				if ( ! file_exists( $path ) ) {
					\WP_CLI::line( 'Skipping non-existent loader_template_path: ' . $path );
					continue;
				}
				$it = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $path ), \RecursiveIteratorIterator::LEAVES_ONLY );
				$found = 0;
				foreach ( $it as $file ) {
					if ( $file->isFile() && preg_match( '/\.twig$/', $file->getPathname() ) ) {
						$twig_template = $file->getPathname();
						$twig_templates[] = substr( $twig_template, strlen( trailingslashit( $path ) ) );
						$found += 1;
					}
				}
				\WP_CLI::line( sprintf( 'Found %d *.twig file(s) in %s', $found, $path ) );

			}
			if ( empty( $twig_templates ) ) {
				\WP_CLI::warning( 'No twig files were found in the loader_template_paths.' );
			}
			$twig_templates = array_unique( $twig_templates );

			\WP_CLI::line( 'Clear Twig cache' );
			$this->plugin->twig_environment()->clearCacheFiles(); // TODO: Remove directories too

			\WP_CLI::line( 'Cache directory: ' . $cache_dir );

			$this->plugin->config['precompilation_required'] = false;

			$twig_env = $this->plugin->twig_environment();
			foreach ( $twig_templates as $twig_template ) {
				$cache_filename = $twig_env->getCacheFilename( $twig_template );
				\WP_CLI::line( 'Compiling: ' . $twig_template );
				$source = $twig_env->compileSource( $twig_env->getLoader()->getSource( $twig_template ), $twig_template );
				\WP_CLI::line( 'Writing: {cache_dir}' . str_replace( $cache_dir, '', $cache_filename ) );
				$twig_env->writeCacheFile( $cache_filename, $source );
			}

			\WP_CLI::success( sprintf( 'Compile %d Twig templates', count( $twig_templates ) ) );

		} catch ( \Exception $e ) {
			\WP_CLI::error( sprintf( '%s: %s', get_class( $e ), $e->getMessage() ) );
		}
	}

}
