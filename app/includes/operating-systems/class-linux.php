<?php

namespace Dekode\InsightlyCli\OperatingSystems;

class Linux extends OperatingSystem {

	/**
	 * Returns the command this operating system uses to open a website in a browser from the terminal.
	 *
	 * @return string
	 */
	public function get_open_in_browser_command(): string {
		return 'xdg-open';
	}

}