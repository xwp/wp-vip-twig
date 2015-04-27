<?php

namespace VIP_Twig;

class Twig_Undisableable_TokenParser_AutoEscape extends \Twig_TokenParser_AutoEscape {

	/**
	 * Parses a token and returns a node.
	 *
	 * @param \Twig_Token $token A Twig_Token instance
	 * @throws Exception
	 * @throws \Twig_Error_Syntax
	 *
	 * @return \Twig_NodeInterface A Twig_NodeInterface instance
	 */
	public function parse( \Twig_Token $token ) {
		$stream = $this->parser->getStream();
		if ( ! $stream->test( \Twig_Token::BLOCK_END_TYPE ) ) {
			$expr = $this->parser->getExpressionParser()->parseExpression();
			if ( ! $expr instanceof \Twig_Node_Expression_Constant ) {
				throw new \Twig_Error_Syntax( 'An escaping strategy must be a string or a Boolean.', $stream->getCurrent()->getLine(), $stream->getFilename() );
			}
			$value = $expr->getAttribute( 'value' );
			if ( true === $value ) {
				$value = 'html';
			}
			if ( false === $value ) {
				throw new Exception( '{% autoescape false %} is forbidden in VIP Twig' );
			}
		}
		return parent::parse( $token );
	}

}
