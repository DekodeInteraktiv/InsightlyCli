<?php

namespace Dekode\InsightlyCli\Commands;

use Dekode\InsightlyCli\Services\InsightlyService;

class SSH extends Command {

	/**
	 * Returns the string used to run this command.
	 *
	 * @return string
	 */
	public function get_key(): string {
		return 'ssh';
	}

	/**
	 * Returns a short description for this command-
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Get the first parameter for ssh.';
	}

	/**
	 * Returns the help text for this command.
	 *
	 * @return string
	 */
	public function get_help(): string {
		$help = "Usage:\nisc ssh <name of project>\n\n";
		$help .= "Examples:\n";
		$help .= 'ssh $(isc ssh finansforbundet.no)' . "\n";

		return $help;

	}

	/**
	 * Executes this command
	 */
	public function run() {
		$insightly_service = new InsightlyService();
		$insightly_service->set_api_key( INSIGHTLY_API_KEY );
		$project = $insightly_service->get_projects_by_name( $this->get_arguments()[2] );

		if ( ! $project ) {
			print ( "\n\e[31mThat project could not be found.\n" );
			exit;
		}


		print( str_replace( 'ssh', '', $project->get_ssh_to_prod() ) );

	}
}