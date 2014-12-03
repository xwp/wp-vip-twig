<?php

namespace VIP_Twig;

/**
 *
 */
class Plugin {

	/**
	 * @var array
	 */
	public $config = array();

	/**
	 * @var string
	 */
	public $slug;

	/**
	 * @var string
	 */
	public $dir_path;

	/**
	 * @var string
	 */
	public $dir_url;

	/**
	 * @var string
	 */
	protected $autoload_class_dir = 'php';

	/**
	 * @var Twig_Loader
	 */
	protected $twig_loader;

	/**
	 * @var Twig_Environment
	 */
	protected $twig_environment;

	/**
	 * @param array $config
	 */
	public function __construct( $config = array() ) {

		$location = $this->locate_plugin();
		$this->slug = $location['dir_basename'];
		$this->dir_path = $location['dir_path'];
		$this->dir_url = $location['dir_url'];

		$default_config = array(
			'precompilation_required' => ( ! $this->is_wp_debug() && ( $this->is_wp_vip_env() || $this->is_disallow_file_mods() ) ),
			'twig_lib_path' => $this->dir_path . '/vendor/twig/lib',
			'environment_options' => array(
				'cache' => trailingslashit( $this->is_wp_vip_env() ? get_stylesheet_directory() : WP_CONTENT_DIR ) . 'twig-cache',
				'debug' => $this->is_wp_debug(),
				'auto_reload' => $this->is_wp_debug(),
				'strict_variables' => true,
			),
			'loader_template_paths' => array(),
			'vip_plugin_folders' => array( 'plugins' ), // On VIP, you may want to filter the config to add 'acmecorp-plugins'
			'charset' => ( $this->is_wp_vip_env() ? 'latin1' : 'UTF-8' ),
		);
		if ( get_template() !== get_stylesheet() ) {
			$default_config['loader_template_paths'][] = trailingslashit( get_stylesheet_directory() );
		}
		$default_config['loader_template_paths'][] = trailingslashit( get_template_directory() );
		// Plugins will probably prepend loader_template_paths with more paths

		$this->config = array_merge( $default_config, $config );

		add_action( 'after_setup_theme', array( $this, 'init' ) );
	}

	/**
	 * @action after_setup_theme
	 */
	function init() {
		spl_autoload_register( array( $this, 'autoload' ) );
		$this->apply_config_filters();
		$this->validate_config();

		$this->twig_loader = new Twig_Loader( $this, $this->config['loader_template_paths'] );
		$this->twig_environment = new Twig_Environment( $this, $this->twig_loader, $this->config['environment_options'] );

		// @todo WP-CLI command which iterates over all *.twig files in $this->config['loader_template_paths']
	}

	/**
	 * @return Twig_Environment
	 */
	function twig_environment() {
		return $this->twig_environment;
	}

	/**
	 * @return bool
	 */
	function is_wp_vip_env() {
		return ( defined( '\WPCOM_IS_VIP_ENV' ) && \WPCOM_IS_VIP_ENV );
	}

	/**
	 * @return bool
	 */
	function is_wp_debug() {
		return ( defined( '\WP_DEBUG' ) && \WP_DEBUG );
	}

	/**
	 * Apply 'vip_twig_plugin_config' filters for $this->config and validate config for environment.
	 */
	function apply_config_filters() {
		$filter_name = $this->prefix( 'plugin_config' );
		$this->config = \apply_filters( $filter_name, $this->config, $this );
	}

	/**
	 * @throws Exception
	 */
	function validate_config() {
		if ( $this->is_wp_vip_env() && ! $this->is_wp_debug() ) {
			if ( ! empty( $this->config['debug'] ) ) {
				trigger_error( 'Twig debug=false is required on VIP', E_USER_WARNING );
				$this->config['debug'] = false;
			}
			if ( empty( $this->config['precompilation_required'] ) && $this->is_disallow_file_mods() ) {
				trigger_error( 'VIP Twig precompilation_required=true is required on VIP', E_USER_NOTICE );
				$this->config['precompilation_required'] = true;
			}
			if ( ! empty( $this->config['environment_options']['auto_reload'] ) && $this->is_disallow_file_mods() ) {
				trigger_error( 'auto_reload=false is required on VIP', E_USER_WARNING );
				$this->config['environment_options']['auto_reload'] = false;
			}
			if ( empty( $this->config['environment_options']['strict_variables'] ) ) {
				trigger_error( 'Twig environment_options.strict_variables=true is required on VIP', E_USER_WARNING );
				$this->config['environment_options']['strict_variables'] = true;
			}
		}
		if ( ! $this->is_valid_cache_directory( $this->config['environment_options']['cache'] ) ) {
			throw new Exception( 'Invalid cache directory: ' . $this->config['environment_options']['cache'] );
		}
		foreach ( $this->config['loader_template_paths'] as $template_path ) {
			if ( file_exists( $template_path ) && ! $this->is_valid_source_directory( $template_path ) ) {
				throw new Exception( 'Invalid template source directory: ' . $template_path );
			}
		}
	}

	/**
	 * @param string $parent_directory
	 * @param string $child_file_or_directory
	 *
	 * @return bool
	 */
	function is_directory_containing( $parent_directory, $child_file_or_directory ) {
		$parent_directory = trailingslashit( realpath( $parent_directory ) );
		$child_file_or_directory = realpath( $child_file_or_directory );
		if ( ! file_exists( $child_file_or_directory ) ) {
			return false;
		}
		if ( is_dir( $child_file_or_directory ) ) {
			$child_file_or_directory = trailingslashit( $child_file_or_directory );
		}
		$is_contained = ( 0 === strpos( $child_file_or_directory, $parent_directory ) );
		return $is_contained;
	}

	/**
	 * @param $cache_directory
	 * @return bool
	 */
	function is_valid_cache_directory( $cache_directory ) {
		$root = $this->is_wp_vip_env() ? get_stylesheet_directory() : WP_CONTENT_DIR;
		return $this->is_directory_containing( $root, $cache_directory );
	}

	/**
	 * @param string $source_directory
	 * @return bool
	 */
	function is_valid_source_directory( $source_directory ) {
		$valid_source_roots = array( get_stylesheet_directory() );
		if ( get_template() !== get_stylesheet() ) {
			$valid_source_roots[] = get_template_directory();
		}
		if ( $this->is_wp_vip_env() ) {
			if ( ! empty( $this->config['vip_plugin_folders'] ) ) {
				foreach ( $this->config['vip_plugin_folders'] as $folder ) {
					$valid_source_roots[] = WP_CONTENT_DIR . "/themes/vip/$folder";
				}
			}
		} else {
			$valid_source_roots[] = WP_CONTENT_DIR;
		}
		foreach ( $valid_source_roots as $valid_source_root ) {
			if ( $this->is_directory_containing( $valid_source_root, $source_directory ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @return bool
	 */
	function is_precompilation_required() {
		return ! empty( $this->config['precompilation_required'] );
	}

	/**
	 * @throws Exception
	 */
	function abort_if_precompilation_required() {
		if ( $this->is_precompilation_required() ) {
			throw new Exception( 'Illegal due to precompilation_required' );
		}
	}

	/**
	 * @throws Exception
	 */
	function abort_if_is_wp_vip_env() {
		if ( $this->is_wp_vip_env() ) {
			throw new Exception( 'Illegal since on WP VIP' );
		}
	}

	/**
	 * @return bool
	 */
	function is_wp_cli() {
		return ( defined( '\WP_CLI' ) && \WP_CLI );
	}

	/**
	 * @return bool
	 */
	function is_disallow_file_mods() {
		return ( defined( '\DISALLOW_FILE_MODS' ) && \DISALLOW_FILE_MODS );
	}

	/**
	 * @return \ReflectionObject
	 */
	function get_object_reflection() {
		static $reflection;
		if ( empty( $reflection ) ) {
			$reflection = new \ReflectionObject( $this );
		}
		return $reflection;
	}

	/**
	 * Compute the prefix from the plugin class's namespace.
	 *
	 * > FooBarBaz\Example_Test => 'foobarbaz_example_test'
	 *
	 * @return string
	 */
	function get_prefix_from_namespace() {
		return strtolower( str_replace( '\\', '_', $this->get_object_reflection()->getNamespaceName() ) );
	}

	/**
	 * Prepend $stem with $this->prefix followed by $delimiter
	 *
	 * @param string $stem
	 * @param string $delimiter
	 * @return string
	 */
	function prefix( $stem = '', $delimiter = '_' ) {
		return $this->get_prefix_from_namespace() . $delimiter . $stem;
	}

	/**
	 * Derive a WP-CLI command name from the class's namespace.
	 *
	 * FooBarBaz\Example_Test => 'foobarbaz example-test'
	 *
	 * @return string
	 */
	function get_cli_command_name() {
		$parts = explode( '\\', str_replace( '_', '-', strtolower( $this->get_object_reflection()->getNamespaceName() ) ) );
		return implode( ' ', $parts );
	}

	/**
	 * Autoload for classes that are in the same namespace as $this, and also for
	 * classes in the Twig library.
	 *
	 * @param  string $class
	 * @return void
	 */
	function autoload( $class ) {
		if ( 0 === strpos( $class, 'Twig_' ) ) {
			$this->autoload_twig( $class );
		} else {
			$this->autoload_self( $class );
		}
	}

	/**
	 * Autoload a class in the Twig library
	 *
	 * @see \Twig_Autoloader
	 * @param string $class
	 */
	function autoload_twig( $class ) {
		$class_path = trailingslashit( $this->config['twig_lib_path'] );
		$class_path .= str_replace( array( '_', "\0" ), array( '/', '' ), $class );
		$class_path .= '.php';
		if ( is_file( $class_path ) ) {
			require_once $class_path;
		}
	}

	/**
	 * Autoload classes in this plugin
	 *
	 * @param string $class
	 */
	function autoload_self( $class ) {
		if ( ! preg_match( '/^(?P<namespace>.+)\\\\(?P<class>[^\\\\]+)$/', $class, $matches ) ) {
			return;
		}
		if ( $this->get_object_reflection()->getNamespaceName() !== $matches['namespace'] ) {
			return;
		}
		$class_name = $matches['class'];

		$class_path = \trailingslashit( $this->dir_path );
		if ( $this->autoload_class_dir ) {
			$class_path .= \trailingslashit( $this->autoload_class_dir );
		}
		$class_path .= sprintf( 'class-%s.php', strtolower( str_replace( '_', '-', $class_name ) ) );
		if ( is_readable( $class_path ) ) {
			require_once $class_path;
		}
	}

	/**
	 * Version of plugin_dir_url() which works for plugins installed in the plugins directory,
	 * and for plugins bundled with themes.
	 *
	 * @throws \Exception
	 * @return array
	 */
	public function locate_plugin() {
		$reflection = new \ReflectionObject( $this );
		$file_name = $reflection->getFileName();
		$plugin_dir = preg_replace( '#(.*plugins[^/]*/[^/]+)(/.*)?#', '$1', $file_name, 1, $count );
		if ( 0 === $count ) {
			throw new \Exception( "Class not located within a directory tree containing 'plugins': $file_name" );
		}

		// Make sure that we can reliably get the relative path inside of the content directory
		$content_dir = trailingslashit( WP_CONTENT_DIR );
		if ( 0 !== strpos( $plugin_dir, $content_dir ) ) {
			throw new \Exception( 'Plugin dir is not inside of WP_CONTENT_DIR' );
		}
		$content_sub_path = substr( $plugin_dir, strlen( $content_dir ) );
		$dir_url = content_url( trailingslashit( $content_sub_path ) );
		$dir_path = $plugin_dir;
		$dir_basename = basename( $plugin_dir );
		return compact( 'dir_url', 'dir_path', 'dir_basename' );
	}

	/**
	 * Handle notice messages according to the appropriate context (WP-CLI or the WP Admin)
	 *
	 * @param string $message
	 * @param bool $is_warning
	 * @return void
	 */
	public static function notice( $message, $is_warning = true ) {
		if ( defined( '\WP_CLI' ) ) {
			$message = strip_tags( $message );
			if ( $is_warning ) {
				\WP_CLI::warning( $message );
			} else {
				\WP_CLI::success( $message );
			}
		} else {
			\add_action( 'admin_notices', function () use ( $message, $is_warning ) {
				$class_name   = empty( $notice['is_error'] ) ? 'updated' : 'error';
				$html_message = sprintf( '<div class="%s">%s</div>', esc_attr( $class_name ), wpautop( $notice['message'] ) );
				echo wp_kses_post( $html_message );
			} );
		}
	}

}
