<?php

namespace Dekode\InsightlyCli\OperatingSystems;

abstract class OperatingSystem {

	/**
	 * Returns the command this operating system uses to open a website in a browser from the terminal.
	 *
	 * @return string
	 */
	abstract public function get_open_in_browser_command(): string;

}