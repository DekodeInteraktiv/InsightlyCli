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
		$insightly_service = new InsightlyService( INSIGHTLY_API_KEY );
		$project           = $insightly_service->get_project_by_name( $this->get_arguments()[2] );
		$climate           = $this->get_climate();

		if ( ! $project ) {
			$climate->error( 'That project could not be found.' );
			exit;
		}

		shell_exec( $project->get_ssh_to_prod() );


	}
}