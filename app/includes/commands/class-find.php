<?php

namespace Dekode\InsightlyCli\Commands;

use Dekode\InsightlyCli\Services\InsightlyService;

class Find extends Command {

	/**
	 * Returns the string used to run this command.
	 *
	 * @return string
	 */
	public function get_key(): string {
		return 'find';
	}

	/**
	 * Returns a short description for this command-
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Get information about a project.';
	}

	/**
	 * Returns the help text for this command.
	 *
	 * @return string
	 */
	public function get_help(): string {
		$help = "Usage:\nisc find <name of project>\n\n";
		$help .= "Examples:\n";
		$help .= 'isc find finansforbundet.no' . "\n";

		return $help;

	}

	/**
	 * Executes this command
	 */
	public function run() {
		$insightly_service = new InsightlyService();
		$insightly_service->set_api_key( INSIGHTLY_API_KEY );
		$project = $insightly_service->get_projects_by_name( $this->get_arguments()[2] );

		print( "Reverse proxy:\t" . $project->get_reverse_proxy() . "\n" );
		print( "SSH to prod:\t" . $project->get_ssh_to_prod() . "\n" );

	}
}