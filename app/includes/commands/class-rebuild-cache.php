<?php

namespace Dekode\InsightlyCli\Commands;

use Dekode\Insightly\InsightlyService;

class ClearCache extends Command {

	/**
	 * Returns the string used to run this command.
	 *
	 * @return string
	 */
	public function get_key(): string {
		return 'rebuild-cache';
	}

	/**
	 * Returns a short description for this command-
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Rebuilds cache for InsightlyCli.';
	}

	/**
	 * Returns the help text for this command.
	 *
	 * @return string
	 */
	public function get_help(): string {
		$help = "Usage:\nisc " . $this->get_key() . "\n\n";

		return $help;

	}

	/**
	 * Executes this command
	 */
	public function run() {
		$climate = $this->get_climate();
		$climate->yellow( 'Rebuilding cache. This could take some time.' );

		$insightly_service = new InsightlyService( INSIGHTLY_API_KEY );
		$insightly_service->clear_cache();
		$insightly_service->get_projects();

		$climate->success( 'Cache rebuilt.' );

	}
}