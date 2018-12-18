<?php

namespace Dekode\InsightlyCli\Models;

class DigitalOceanDroplet extends Server {
	/**
	 * Returns the name of this server when saving it in Insightly.
	 *
	 * @return string
	 */
	public function get_insightly_name() {
		return 'Digital Ocean';
	}

}