<?php

namespace Dekode\InsightlyCli\Commands;

use Dekode\InsightlyCli\Services\InsightlyService;
use Dekode\InsightlyCli\Services\SSHService;

class AccessDb extends Command {

	/**
	 * Returns the string used to run this command.
	 *
	 * @return string
	 */
	public function get_key(): string {
		return 'access-db';
	}

	/**
	 * Returns a short description for this command-
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Returns the command needed to connect to the mysql database of this site.';
	}

	/**
	 * Returns the help text for this command.
	 *
	 * @return string
	 */
	public function get_help(): string {
		$help = "Usage:\nisc access-db <name of project>\n\n";

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

		$ssh_service = new SSHService( $project );

		$db_credentials = $ssh_service->get_db_credentials();

		$climate->yellow( $project->get_ssh_to_prod() . ' -t \'mysql -h ' . $db_credentials['DB_HOST'] . ' -u ' . $db_credentials['DB_USER'] . ' -p' . $db_credentials['DB_PASSWORD'] . ' ' . $db_credentials['DB_NAME'] . '\'' );


	}
}