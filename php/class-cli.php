<?php

namespace VIP_Twig;

/**
 * Management of the vip-twig plugin.
 */
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
	 * Find all files with the $pattern inside of $root_directory.
	 *
	 * @param $root_directory
	 * @param $pattern
	 *
	 * @return array
	 */
	protected function find_files( $root_directory, $pattern ) {
		$found_files = array();
		$it = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root_directory ), \RecursiveIteratorIterator::LEAVES_ONLY );
		foreach ( $it as $file ) {
			if ( $file->isFile() && preg_match( $pattern, $file->getPathname() ) ) {
				$found_files[] = $file->getPathname();
			}
		}
		return $found_files;
	}

	/**
	 * Remove $prefix from beginning of $string; throw exception if it is not the prefix.
	 *
	 * @param $prefix
	 * @param $string
	 *
	 * @return string
	 * @throws Exception
	 */
	protected function strip_prefix( $prefix, $string ) {
		if ( 0 !== strpos( $string, $prefix ) ) {
			throw new Exception( "Expected prefix '$prefix' to be at start of '$string'." );
		}
		return substr( $string, strlen( $prefix ) );
	}

	/**
	 * Re-compile all *.twig templates located on the plugin's loader_template_paths.
	 *
	 * ## OPTIONS
	 *
	 * --force
	 * : Whether to force-compile all files regardless of modified time.
	 *
	 * --cleanup
	 * : Remove cache files that are no longer referenced.
	 *
	 * @param array [$args]
	 * @param array $assoc_args
	 * @synopsis [--force] [--cleanup]
	 */
	public function compile( $args, $assoc_args ) {
		try {
			unset( $args );
			$assoc_args = array_merge(
				array(
					'force' => false,
					'cleanup' => false,
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
				// @codingStandardsIgnoreStart
				$mkdir_success = mkdir( $cache_dir );
				// @codingStandardsIgnoreEnd
				if ( $mkdir_success ) {
					\WP_CLI::line( 'Creating cache directory: ' . $cache_dir );
				} else {
					throw new Exception( 'Unable to create cache directory: ' . $cache_dir );
				}
			} else {
				\WP_CLI::line( 'Cache directory: ' . $cache_dir );
			}

			$twig_templates = array();
			foreach ( $this->plugin->config['loader_template_paths'] as $path ) {
				$path = trailingslashit( realpath( $path ) );
				if ( ! file_exists( $path ) ) {
					\WP_CLI::line( 'Skipping non-existent loader_template_path: ' . $path );
					continue;
				}
				$found = 0;
				foreach ( $this->find_files( $path, '/\.twig$/' ) as $twig_template ) {
					$twig_template = $this->strip_prefix( $path, $twig_template );
					$twig_templates[] = $twig_template;
					$found += 1;
				}
				\WP_CLI::line( sprintf( 'Found %d *.twig file(s) in %s', $found, $path ) );

			}
			if ( empty( $twig_templates ) ) {
				\WP_CLI::error( 'No twig files were found in the loader_template_paths.' );
				return;
			}
			$twig_templates = array_unique( $twig_templates );

			$previous_cache_files = $this->find_files( $cache_dir, '/\.php$/' );
			$present_cache_files = array();

			$twig_env = $this->plugin->twig_environment();
			foreach ( $twig_templates as $twig_template ) {
				$cache_filename = $twig_env->getCacheFilename( $twig_template );
				$present_cache_files[] = $cache_filename;
				if ( ! $assoc_args['force'] && $twig_env->isTemplateFresh( $twig_template, \filemtime( $cache_filename ) ) ) {
					\WP_CLI::line( 'Skipping since fresh: ' . $twig_template );
				} else {
					\WP_CLI::line( 'Compiling: ' . $twig_template );
					$source = $twig_env->compileSource( $twig_env->getLoader()->getSource( $twig_template ), $twig_template );
					\WP_CLI::line( 'Writing: {cache_dir}' . str_replace( $cache_dir, '', $cache_filename ) );
					$twig_env->writeCacheFile( $cache_filename, $source );
				}
			}

			if ( $assoc_args['cleanup'] ) {
				foreach ( $previous_cache_files as $existing_cache_file ) {
					if ( ! in_array( $existing_cache_file, $present_cache_files ) ) {
						\WP_CLI::line( 'Cleanup unreferenced Twig cache file: ' . $existing_cache_file );
						// @codingStandardsIgnoreStart
						unlink( $existing_cache_file );
						// @codingStandardsIgnoreEnd
					}
				}
			}

			\WP_CLI::success( sprintf( 'Compiled %d Twig template(s)', count( $twig_templates ) ) );

		} catch ( \Exception $e ) {
			\WP_CLI::error( sprintf( '%s: %s', get_class( $e ), $e->getMessage() ) );
		}
	}

}
