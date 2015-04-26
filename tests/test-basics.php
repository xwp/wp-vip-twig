<?php

namespace VIP_Twig;

class BasicsTest extends \WP_UnitTestCase {

	function setUp() {
		if ( ! vip_twig_environment()->getCache() ) {
			$location = vip_twig_environment()->plugin->not_writable_cache_location;
			throw new \Exception( $location ? "Cache dir is not writable: $location" : 'Cache dir not supplied' );
		}
		if ( ! file_exists( vip_twig_environment()->getCache() ) ) {
			// @codingStandardsIgnoreStart
			mkdir( vip_twig_environment()->getCache() );
			// @codingStandardsIgnoreEnd
		}
		vip_twig_environment()->clearCacheFiles();
		parent::setUp();
	}

	function test_instance() {

		$this->assertTrue( $GLOBALS['vip_twig_plugin'] instanceof Plugin );
		/**
		 * @var Plugin $plugin
		 */
		$plugin = $GLOBALS['vip_twig_plugin'];
		$this->assertTrue( function_exists( 'vip_twig_environment' ) );
		$twig_env = vip_twig_environment();
		$this->assertTrue( $twig_env instanceof Twig_Environment );
		$this->assertTrue( $plugin->twig_environment() instanceof Twig_Environment );
	}

	function test_render() {
		$this->assertEquals( 0, count( glob( vip_twig_environment()->getCache() . '/*.php' ) ) );

		$base_render = vip_twig_environment()->render( 'base.html.twig' );
		$this->assertTrue( strpos( $base_render, '<title>Base</title>' ) !== false );

		$index_render = vip_twig_environment()->render( 'index.html.twig' );
		$this->assertTrue( strpos( $index_render, '<title>Index</title>' ) !== false );

		$compiled_templates = glob( vip_twig_environment()->getCache() . '/*.php' );
		$this->assertEquals( 2, count( $compiled_templates ) );

		foreach ( $compiled_templates as $compiled_template ) {
			$compiled_template = basename( $compiled_template );
			$this->assertTrue( 0 !== preg_match( '#^(index|base)\.html\.twig\.php$#', $compiled_template ) );
		}
	}

	function test_render_template_name_filter() {
		$test = $this;
		$filter_name = function ( $name, $context ) use ( $test ) {
			$test->assertEquals( 'base.html.twig', $name );
			$test->assertEquals( 'FOOD HEADING', $context['header_h1'] );
			return 'index.html.twig';
		};
		add_filter( 'vip_twig_render_template_name', $filter_name, 10, 2 );
		$render = vip_twig_environment()->render( 'base.html.twig', array( 'header_h1' => 'FOOD HEADING' ) );
		$this->assertContains( '<title>Index</title>', $render );
		$this->assertContains( '<h1>FOOD HEADING</h1>', $render );
		remove_filter( 'vip_twig_render_template_name', $filter_name, 10 );
	}

	function test_render_template_context_filter() {
		$test = $this;
		$filter_context = function ( $context, $name ) use ( $test ) {
			$test->assertEquals( 'index.html.twig', $name );
			$test->assertEquals( 'FOOD HEADING', $context['header_h1'] );
			$context['header_h1'] = 'BARD HEADING';
			return $context;
		};
		add_filter( 'vip_twig_render_template_context', $filter_context, 10, 2 );
		$render = vip_twig_environment()->render( 'index.html.twig', array( 'header_h1' => 'FOOD HEADING' ) );
		$this->assertContains( '<title>Index</title>', $render );
		$this->assertContains( '<h1>BARD HEADING</h1>', $render );
		remove_filter( 'vip_twig_render_template_context', $filter_context, 10 );
	}

	function test_render_override_template() {
		$render = vip_twig_environment()->render( 'override.html.twig' );
		$this->assertContains( '<title>Stylesheet Override</title>', $render );
		$this->assertContains( 'Welcome to the stylesheet override page', $render );
	}

	function test_wp_kses_post_custom_filter() {
		$rendered = vip_twig_environment()->render( 'wp-kses.html.twig', array(
			'content' => '<script>alert("evil")</script>',
		) );
		$this->assertNotContains( '<script', $rendered );
	}

	/**
	 * @see Plugin::init()
	 */
	function test_eliminated_raw_filter() {
		$exception = null;
		try {
			vip_twig_environment()->render( 'raw.html.twig', array( 'unsanitized_data' => '<script>alert(1)</script>' ) );
		} catch ( \Exception $e ) {
			$exception = $e;
		}
		$this->assertInstanceOf( '\Twig_Error_Syntax', $exception );
		$this->assertContains( 'The filter "raw" does not exist', $exception->getMessage() );
	}

	/**
	 * @see Twig_Extension_Core::escape_filter()
	 */
	function test_escaping() {
		$context = array(
			'sample_html' => '<input onclick="alert(1)">',
			'sample_html_attr' => 'I do \'not\' love "straight quotes"',
			'sample_js' => 'var x = "fo\nod";',
			'sample_css' => 'body { background-image: url( "http://example.com/food.png" ); }</style><script>alert(1);</script>',
			'sample_url' => "http://example.com/?foo=bar&baz=quux\x00afternull",
			'sample_url_encode' => 'x y & z=#',
			'sample_json_encode' => 'The "string" \exists.',
		);
		$render = vip_twig_environment()->render( 'escaping.html.twig', $context );

		$results = array();
		foreach ( preg_split( "/\n/", trim( $render ) ) as $line ) {
			list( $key, $value ) = explode( ': ', $line, 2 );
			$results[ $key ] = $value;
		}

		// Note in the following examples, the HTML escape is also applied.
		$this->assertEquals( '&lt;input onclick=&quot;alert(1)&quot;&gt;', $results['test_escape_html'] );
		$this->assertEquals( $results['test_escape_html'], $results['test_escape_default'] );
		$this->assertEquals( 'http://example.com/?foo=bar&amp;baz=quuxafternull', $results['test_escape_url'] );
		$this->assertEquals( 'I do &#039;not&#039; love &quot;straight quotes&quot;', $results['test_escape_attr'] );
		$this->assertEquals( 'var x = &quot;fonod&quot;;', $results['test_escape_js'] );
		$this->assertEquals( 'body { background-image: url( &quot;http://example.com/food.png&quot; ); }', $results['test_escape_css'] );
		$this->assertEquals( 'x%20y%20%26%20z%3D%23', $results['test_escape_url_encode'] );
		$this->assertEquals( '&quot;The \&quot;string\&quot; \\\\exists.&quot;', $results['test_escape_json_encode'] );

		$escaper = vip_twig_environment()->getFilter( 'escape' );
		$callable = $escaper->getCallable();

		$env = vip_twig_environment();
		$charset = null;
		$autoescape = true;

		$string = $context['sample_html'];
		$strategy = 'html';
		$this->assertEquals( '&lt;input onclick=&quot;alert(1)&quot;&gt;', call_user_func( $callable, $env, $string, $strategy, $charset, $autoescape ) );

		$string = $context['sample_html_attr'];
		$strategy = 'html_attr';
		$this->assertEquals( 'I do &#039;not&#039; love &quot;straight quotes&quot;', call_user_func( $callable, $env, $string, $strategy, $charset, $autoescape ) );

		$string = $context['sample_js'];
		$strategy = 'js';
		$this->assertEquals( 'var x = &quot;fonod&quot;;', call_user_func( $callable, $env, $string, $strategy, $charset, $autoescape ) );

		$string = $context['sample_css'];
		$strategy = 'css';
		$this->assertEquals( 'body { background-image: url( "http://example.com/food.png" ); }', call_user_func( $callable, $env, $string, $strategy, $charset, $autoescape ) );

		$string = $context['sample_url'];
		$strategy = 'url';
		$this->assertEquals( 'http://example.com/?foo=bar&#038;baz=quuxafternull', call_user_func( $callable, $env, $string, $strategy, $charset, $autoescape ) );
	}

}
