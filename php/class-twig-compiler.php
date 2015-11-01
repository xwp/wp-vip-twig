<?php

namespace VIP_Twig;

class Twig_Compiler extends \Twig_Compiler {

	/**
	 * @var Plugin
	 */
	public $plugin;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin
	 * @param \Twig_Environment $env The twig environment instance
	 */
	public function __construct( Plugin $plugin, \Twig_Environment $env ) {
		$this->plugin = $plugin;
		parent::__construct( $env );
	}

	/**
	 * Adds debugging information.
	 *
	 * This is copied from the parent class, with the addition of a wrapper
	 * around the write() call for outputting the Twig source line.
	 *
	 * @param \Twig_NodeInterface $node The related twig node
	 *
	 * @return Twig_Compiler The current compiler instance
	 */
	public function addDebugInfo( \Twig_NodeInterface $node ) {
		if ( $node->getLine() !== $this->lastLine ) {
			// Write out the source map files only if explicitly requested
			if ( ! empty( $this->plugin->config['write_source_map_lines'] ) ) {
				$this->write( sprintf( "// line %d\n", $node->getLine() ) );
			}

			/*
			 * when mbstring.func_overload is set to 2
			 * mb_substr_count() replaces substr_count()
			 * but they have different signatures!
			 */
			if ( ( (int) ini_get( 'mbstring.func_overload' ) ) & 2 ) {
				// this is much slower than the "right" version
				$this->sourceLine += mb_substr_count( mb_substr( $this->source, $this->sourceOffset ), "\n" );
			} else {
				$this->sourceLine += substr_count( $this->source, "\n", $this->sourceOffset );
			}
			$this->sourceOffset = strlen( $this->source );
			$this->debugInfo[ $this->sourceLine ] = $node->getLine();

			$this->lastLine = $node->getLine();
		}

		return $this;
	}
}
