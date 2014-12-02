<?php

namespace VIP_Twig;

class Twig_Loader extends \Twig_Loader_Filesystem {

	/**
	 * @var Plugin
	 */
	public $plugin;

	function __construct( $plugin, $paths ) {
		$this->plugin = $plugin;
		parent::__construct( $paths );
	}

}
