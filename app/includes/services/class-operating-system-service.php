<?php

namespace Dekode\InsightlyCli\Services;

use Dekode\InsightlyCli\OperatingSystems\Linux;
use Dekode\InsightlyCli\OperatingSystems\OperatingSystem;

class OperatingSystemService {

	/**
	 * Returns an object of the current operating system.
	 *
	 * @return Linux
	 */
	static public function get_current_os(): OperatingSystem {
		switch ( PHP_OS ) {
			case 'Linux':
				return new Linux();
				break;
			default:
				echo 'Unknown operating system: ' . PHP_OS;
				die();

		}
	}
}