<?php

namespace Dekode\InsightlyCli\Commands;

use Dekode\RemoteServers\Services\SSHService;
use Dekode\Insightly\InsightlyService;

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
		$climate           = $this->get_climate();

		$project = $this->get_most_similar_project_or_die( $this->get_arguments()[2] );

		$climate->green( 'Found ' . $project->get_name() );

		$ssh_service = new SSHService( $this->convert_to_ssh_server( $project ) );

		if ( ! $ssh_service->wp_cli_is_installed() ) {
			$climate->red( 'WP CLI is not installed on remote server. Cannot get DB credentials' );
		} else {

			$db_credentials = $ssh_service->get_db_details();

			$climate->yellow( $project->get_ssh_to_prod() . ' -t \'mysql -h ' . $db_credentials['DB_HOST'] . ' -u ' . $db_credentials['DB_USER'] . ' -p' . $db_credentials['DB_PASSWORD'] . ' ' . $db_credentials['DB_NAME'] . '\'' );
		}

	}
}