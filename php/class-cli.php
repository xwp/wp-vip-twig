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
				throw new Exception( 'The supplied directory is not writable: ' . $this->plugin->not_writable_cache_location );
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

			// Use git to determine
			add_filter( 'vip_twig_template_is_fresh', array( $this, 'filter_git_template_is_fresh' ), 10, 2 );

			$compiled_count = 0;

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
					$compiled_count += 1;
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

			\WP_CLI::success( sprintf( 'Compiled %d of %d Twig template(s)', $compiled_count, count( $twig_templates ) ) );

		} catch ( \Exception $e ) {
			\WP_CLI::error( sprintf( '%s: %s', get_class( $e ), $e->getMessage() ) );
		}
	}

	/**
	 * @param bool $is_fresh
	 * @param string $template_name
	 *
	 * @return bool
	 * @throws Exception
	 */
	public function filter_git_template_is_fresh( $is_fresh, $template_name ) {
		unset( $is_fresh );
		$twig_env = $this->plugin->twig_environment();
		$cache_filename = $twig_env->getCacheFilename( $template_name );
		$cache_filename_mtime = $this->get_last_file_git_commit_timestamp( $cache_filename );
		$template_path = $twig_env->getLoader()->findTemplate( $template_name );
		$template_path_mtime = $this->get_last_file_git_commit_timestamp( $template_path );
		if ( $this->has_git_uncommitted_changes( $template_path ) ) {
			throw new Exception( "$template_path has uncommitted changes" );
		}
		$is_fresh = ( $cache_filename_mtime >= $template_path_mtime );
		return $is_fresh;
	}

	/**
	 * @param string $path
	 * @return bool
	 * @throws Exception
	 */
	public function has_git_uncommitted_changes( $path ) {
		$cmd = sprintf(
			'( cd %s && git status --porcelain %s )',
			escapeshellarg( dirname( $path ) ),
			escapeshellarg( $path )
		);
		$last_output_line = exec( $cmd, $output, $return_var );
		if ( 0 !== $return_var ) {
			throw new Exception( "Unable to determine dirty state for $path: $cmd (exit code: $return_var)" );
		}
		if ( '' === trim( $last_output_line ) ) {
			return false;
		} else {
			return true;
		}
	}

	/**
	 * Get the Unix timestamp for the last time a file.
	 *
	 * This is used in a vip_twig_template_is_fresh filter during compilation via WP-CLI.
	 *
	 * @param string $path
	 * @return int
	 * @throws Exception
	 */
	public function get_last_file_git_commit_timestamp( $path ) {
		$cmd = sprintf(
			'( cd %s && git --no-pager log -1 --format="%%ct" %s )',
			escapeshellarg( dirname( $path ) ),
			escapeshellarg( $path )
		);
		$last_committed_time = exec( $cmd, $output, $return_var );
		if ( 0 !== $return_var ) {
			throw new Exception( "Unable to get last commit commit time for $path: $cmd (exit code: $return_var)" );
		}
		return intval( trim( $last_committed_time ) );
	}

}
