<?php

namespace VIP_Twig;

class Twig_Extension_Core extends \Twig_Extension_Core {

	/**
	 * @var Plugin
	 */
	public $plugin;

	/**
	 * @param Plugin $plugin
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	public function getFilters() {
		$filters = array();
		/**
		 * @var \Twig_SimpleFilter[] $original_filters
		 */
		$original_filters = parent::getFilters();

		foreach ( $original_filters as $filter ) {
			$name = $filter->getName();
			if ( in_array( $name, array( 'escape', 'e' ) ) ) {
				// Replace twig_escape_filter() with our own escape_filter() method using WP's own escaping functions
				$filters[] = new \Twig_SimpleFilter( $name,
					array( $this, 'escape_filter' ),
					array(
						'needs_environment' => true,
						'is_safe_callback' => 'twig_escape_filter_is_safe',
					)
				);
			} else if ( 'json_encode' === $name ) {
				// Replace twig_jsonencode_filter() with our own using wp_json_encode()
				$filters[] = new \Twig_SimpleFilter( 'json_encode', array( $this, 'json_encode_filter' ) );
			} else {
				$filters[] = $filter;
			}
		}

		return $filters;
	}

	/**
	 * Adapted from art-direction-redux VIP plugin
	 *
	 * @param string $css
	 * @return string
	 * @link https://vip-svn.wordpress.com/plugins/art-direction-redux/art-direction-redux.php
	 * @link https://vip.wordpress.com/documentation/code-review-what-we-look-for/#arbitrary-javascript-and-css-stored-in-options-or-meta
	 */
	function esc_css( $css ) {
		$css = stripslashes( $css );
		$css = wp_strip_all_tags( $css );

		if ( function_exists( 'safecss_class' ) ) {
			// Stolen from the Custom CSS plugin. Sanitize and clean using CSS tidy if available.
			safecss_class();
			$csstidy = new \csstidy();
			$csstidy->optimise = new \safecss( $csstidy );
			$csstidy->set_cfg( 'remove_bslash', false );
			$csstidy->set_cfg( 'compress_colors', false );
			$csstidy->set_cfg( 'compress_font-weight', false );
			$csstidy->set_cfg( 'discard_invalid_properties', true );
			$csstidy->set_cfg( 'merge_selectors', false );
			$csstidy->set_cfg( 'remove_last_;', false );
			$csstidy->set_cfg( 'css_level', 'CSS3.0' );

			$css = preg_replace( '/\\\\([0-9a-fA-F]{4})/', '\\\\\\\\$1', $css );

			$csstidy->parse( $css );
			$css = $csstidy->print->plain();
		}
		return $css;
	}

	/**
	 * @see \twig_jsonencode_filter()
	 * @param string|mixed $value
	 * @param int $options
	 * @return string
	 */
	function json_encode_filter( $value, $options = 0 ) {
		if ( $value instanceof \Twig_Markup ) {
			$value = (string) $value;
		} else if ( is_array( $value ) ) {
			array_walk_recursive( $value, '_twig_markup2string' );
		}
		return wp_json_encode( $value, $options );
	}

	/**
	 * Escapes a string in a WordPress-way.
	 *
	 * @param \Twig_Environment $env        A Twig_Environment instance
	 * @param string           $string     The value to be escaped
	 * @param string           $strategy   The escaping strategy
	 * @param string           $charset    The charset
	 * @param bool             $autoescape Whether the function is called by the auto-escaping feature (true) or by the developer (false)
	 * @return string
	 * @throws \Twig_Error_Runtime
	 * @see \twig_escape_filter()
	 */
	function escape_filter( \Twig_Environment $env, $string, $strategy = 'html', $charset = null, $autoescape = false )  {
		unset( $env );

		if ( $autoescape && $string instanceof \Twig_Markup ) {
			return $string;
		}

		if ( ! is_string( $string ) ) {
			if ( is_object( $string ) && method_exists( $string, '__toString' ) ) {
				$string = (string) $string;
			} else if ( is_numeric( $string ) || is_bool( $string ) ) {
				$string = (string) $string;
			} else if ( is_null( $string ) ) {
				$string = '';
			} else {
				throw new \Twig_Error_Runtime( 'Unable to escape type: ' . gettype( $string ) );
			}
		}

		if ( ! is_null( $charset ) ) {
			throw new \Twig_Error_Runtime( 'Supplying a charset is not allowed by VIP Twig' );
		}

		switch ( $strategy ) {
			/*
			 * html: escapes a string for the HTML body context.
			 */
			case 'html':
				return esc_html( $string );

			/*
			 * html_attr: escapes a string for the HTML attribute context.
			 */
			case 'attr':
			case 'html_attr':
				return esc_attr( $string );

			/*
			 * js: escapes a string for the JavaScript context.
			 * You probably want to use the json_encode filter.
			 */
			case 'js':
				return esc_js( $string );

			/*
			 * css: escapes a string for the CSS context. CSS escaping can be applied
			 * to any string being inserted into CSS and escapes everything except alphanumerics.
			 */
			case 'css':
				return $this->esc_css( $string );

			/*
			 * url: escape a string as a full URL.
			 *
			 * IMPORTANT: Twig Core notes that this "escapes a string for the URI
			 * or parameter contexts. This should not be used to escape an entire
			 * URI; only a subcomponent being inserted."
			 *
			 * If you want to escape just a subcomponent, use the url_encode Twig filter instead.
			 */
			case 'url':
				return esc_url( $string );

			default:
				throw new \Twig_Error_Runtime( sprintf( 'Unrecognized VIP escaping strategy "%s".', $strategy ) );
		}
	}

}
