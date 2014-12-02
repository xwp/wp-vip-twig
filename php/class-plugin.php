<?php

namespace VIP_Twig;

/**
 *
 */
class Plugin {

	/**
	 * @var array
	 */
	public $config;

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
	public $twig_loader;

	/**
	 * @var Twig_Environment
	 */
	public $twig_environment;

	/**
	 * @param array $config
	 */
	public function __construct( $config = array() ) {

		$location = $this->locate_plugin();
		$this->slug = $location['dir_basename'];
		$this->dir_path = $location['dir_path'];
		$this->dir_url = $location['dir_url'];
		$this->textdomain = $location['dir_basename'];

		$default_config = array(
			'require_precompilation' => ( defined( '\DISALLOW_FILE_MODS' ) && \DISALLOW_FILE_MODS ),
			'cache_dir' => trailingslashit( WP_CONTENT_DIR ) . '/twig-cache',
			'twig_lib_path' => $this->dir_path . '/vendor/twig/lib',
			'environment_options' => array(
				// ...
			),
			'loader_template_paths' => array(),
		);
		if ( get_template() !== get_stylesheet() ) {
			$default_config['loader_template_paths'][] = get_stylesheet_directory() . '/twig-cache';
		}
		$default_config['loader_template_paths'][] = get_template_directory() . '/twig-cache';
		$this->config = array_merge( $default_config, $config );

		add_action( 'after_setup_theme', array( $this, 'init' ) );
	}

	/**
	 * @action after_setup_theme
	 */
	function init() {
		spl_autoload_register( array( $this, 'autoload' ) );
		$this->apply_config_filters();

		$this->twig_loader = new Twig_Loader( $this, $this->config['template_paths'] );
		$this->twig_environment = new Twig_Environment( $this, $this->twig_loader, $this->config['environment_options'] );

		// @todo WP-CLI command
	}

	/**
	 * Apply 'vip_twig_plugin_config' filters for $this->config
	 */
	function apply_config_filters() {
		$filter_name = $this->prefix( 'plugin_config' );
		$this->config = \apply_filters( $filter_name, $this->config, $this );
		if ( defined( '\WPCOM_IS_VIP_ENV' ) && \WPCOM_IS_VIP_ENV ) {
			$this->config['require_precompilation'] = true;
		}
	}

	/**
	 * @throws Exception
	 */
	function abort_if_precommpilation_required() {
		if ( $this->plugin->config['require_precompilation'] ) {
			throw new Exception( 'Illegal due to require_precompilation' );
		}
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
