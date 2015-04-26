<?php

namespace VIP_Twig;

class Twig_Extension_Rawless_Escaper extends \Twig_Extension_Escaper {

	/**
	 * Remove the raw filter from the list of escapers.
	 *
	 * @return array An array of filters
	 */
	public function getFilters() {
		return array_filter(
			parent::getFilters(),
			function ( $filter ) {
				return 'raw' !== $filter->getName();
			}
		);
	}

}
