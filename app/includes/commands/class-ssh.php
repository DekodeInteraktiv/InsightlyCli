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
		return 'Returns the command needed to SSH to this site.';
	}

	/**
	 * Returns the help text for this command.
	 *
	 * @return string
	 */
	public function get_help(): string {
		$help = "Usage:\nisc ssh <name of project>\n\n";

		return $help;

	}

	/**
	 * Executes this command
	 */
	public function run() {
		$insightly_service = new InsightlyService( INSIGHTLY_API_KEY );
		$projects          = $insightly_service->get_projects_by_name_similarity( $this->get_arguments()[2] );
		$climate           = $this->get_climate();

		if ( ! $projects[0] ) {
			$climate->error( 'No similar project could be found.' );

			exit;
		}

		$project = $projects[0];

		$climate->green( 'Found ' . $project->get_name() );

		if ( $project->get_web_root() ) {
			$climate->yellow( $project->get_ssh_to_prod() . " -t 'cd " . $project->get_web_root() . "; bash'" );
		} else {
			$climate->yellow( $project->get_ssh_to_prod() );

		}


	}
}