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
	 * ## OPTIONS
	 *
	 * --force
	 * : Whether to force-compile all files regardless of modified time.
	 *
	 * @param array [$args]
	 * @param array $assoc_args
	 * @synopsis [--force]
	 */
	public function compile( $args, $assoc_args ) {
		try {
			$assoc_args = array_merge(
				array(
					'force' => false,
				),
				$assoc_args
			);

			if ( empty( $this->plugin->config['loader_template_paths'] ) ) {
				throw new Exception( 'No loader_template_paths config supplied.' );
			}
			if ( empty( $this->plugin->config['environment_options']['cache'] ) ) {
				throw new Exception( 'No cache config supplied.' );
			}
			$cache_dir = $this->plugin->config['environment_options']['cache'];
			if ( ! file_exists( $cache_dir ) ) {
				if ( mkdir( $cache_dir ) ) {
					\WP_CLI::line( 'Creating cache directory: ' . $cache_dir );
				} else {
					throw new Exception( 'Unable to create cache directory: ' . $cache_dir );
				}
			} else {
				\WP_CLI::line( 'Cache directory: ' . $cache_dir );
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

			$this->plugin->config['precompilation_required'] = false;

			$twig_env = $this->plugin->twig_environment();
			foreach ( $twig_templates as $twig_template ) {
				$cache_filename = $twig_env->getCacheFilename( $twig_template );
				if ( ! $assoc_args['force'] && $twig_env->isTemplateFresh( $twig_template, \filemtime( $cache_filename ) ) ) {
					\WP_CLI::line( 'Skipping since fresh: ' . $twig_template );
				} else {
					\WP_CLI::line( 'Compiling: ' . $twig_template );
					$source = $twig_env->compileSource( $twig_env->getLoader()->getSource( $twig_template ), $twig_template );
					\WP_CLI::line( 'Writing: {cache_dir}' . str_replace( $cache_dir, '', $cache_filename ) );
					$twig_env->writeCacheFile( $cache_filename, $source );
				}
			}

			// TODO: Remove any PHP files from cache directory that is no longer referenced

			\WP_CLI::success( sprintf( 'Compiled %d Twig template(s)', count( $twig_templates ) ) );

		} catch ( \Exception $e ) {
			\WP_CLI::error( sprintf( '%s: %s', get_class( $e ), $e->getMessage() ) );
		}
	}

}
