<?php

namespace VIP_Twig;

class BasicsTest extends \WP_UnitTestCase {

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

		$this->assertTrue( file_exists( vip_twig_environment()->getCache() ) );
	}

	function test_render() {
		vip_twig_environment()->clearCacheFiles();
		$this->assertEquals( 0, count( glob( vip_twig_environment()->getCache() . '/*.php' ) ) );

		$base_render = vip_twig_environment()->render( 'base.html.twig' );
		$this->assertTrue( strpos( $base_render, '<title>Base</title>' ) !== false );

		$index_render = vip_twig_environment()->render( 'index.html.twig' );
		$this->assertTrue( strpos( $index_render, '<title>Index</title>' ) !== false );

		$compiled_templates = glob( vip_twig_environment()->getCache() . '/*.php' );
		$this->assertEquals( 2, count( $compiled_templates ) );
	}

}
