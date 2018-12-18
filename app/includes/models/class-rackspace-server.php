<?php

namespace Dekode\InsightlyCli\Models;

class RackspaceServer extends Server {

	/**
	 * Returns the name of this server when saving it in Insightly.
	 *
	 * @return string
	 */
	public function get_insightly_name() {
		$name = $this->get_name();

		if ( $name == 'prod02-master' ) {
			$name = 'prod02';
		}

		if ( $name == 'prod03-s01' ) {
			$name = 'prod03';
		}

		return $name;
	}


}