<?php

namespace Dekode\InsightlyCli\Commands;

use Dekode\InsightlyCli\Services\InsightlyService;

class ClearCache extends Command {

	/**
	 * Returns the string used to run this command.
	 *
	 * @return string
	 */
	public function get_key(): string {
		return 'clear-cache';
	}

	/**
	 * Returns a short description for this command-
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Clear cache for InsightlyCli.';
	}

	/**
	 * Returns the help text for this command.
	 *
	 * @return string
	 */
	public function get_help(): string {
		$help = "Usage:\nisc clear-cache\n\n";

		return $help;

	}

	/**
	 * Executes this command
	 */
	public function run() {
		$insightly_service = new InsightlyService();
		$insightly_service->clear_cache();

		print( 'Cache cleared.' );

	}
}