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
			$this->assertTrue( 0 !== preg_match( '#^(index|base)\.html\.twig\.\w+\.php$#', $compiled_template ) );
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

	function test_wp_kses_post_custom_filter() {
		$this->assertEquals( 0, count( glob( vip_twig_environment()->getCache() . '/*.php' ) ) );

		$raw_filter = vip_twig_environment()->render( 'wp-kses-filter/raw.html.twig' );
		$this->assertTrue( 0 !== preg_match_all( '/<\/*script>/', $raw_filter ) );

		$wp_kses_filter = vip_twig_environment()->render( 'wp-kses-filter/wpkses.html.twig' );
		$this->assertTrue( 1 !== preg_match_all( '/<\/*script>/', $wp_kses_filter ) );

	}

}
