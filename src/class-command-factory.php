<?php

namespace Dekode\InsightlyCli\Commands;

class CommandFactory {
	/**
	 * Returns a command object based on a given key.
	 *
	 * @param $key
	 *
	 * @return Find
	 */
	public function get_command( $key ) {
		switch ( $key ) {
			case 'find':
				return new Find();
				break;
		}
	}


}