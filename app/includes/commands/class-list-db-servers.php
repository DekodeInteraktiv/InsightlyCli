<?php

namespace Dekode\InsightlyCli\Commands;

use Dekode\InsightlyCli\Services\InsightlyService;
use Dekode\RemoteServers\Services\SSHService;


class ListDbServers extends Command {

	/**
	 * Returns the string used to run this command.
	 *
	 * @return string
	 */
	public function get_key(): string {
		return 'list-db-servers';
	}

	/**
	 * Returns a short description for this command-
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Tries to find the DB host of all sites';
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
		$insightly_service = new InsightlyService( INSIGHTLY_API_KEY );
		$projects          = $insightly_service->get_projects();

		usort( $projects, function ( $a, $b ) {
			return strcmp( strtolower( $a->get_name() ), strtolower( $b->get_name() ) );
		} );


		foreach ( $projects as $project ) {
			$db_details = [];

			try {
				@$ssh_service = new SSHService( $project->convert_to_ssh_server() );

				@$db_details = $ssh_service->get_db_details();
			} catch (\Exception $e) {
				$db_details['DB_HOST'] = 'Error: ' . $e->getMessage();
			}

			if (!$db_details['DB_HOST']) {
				$db_details['DB_HOST'] = 'Unknown or localhost';
			}

			print($project->get_name() . ': ' . $db_details['DB_HOST'] . "\n");
		}


	}
}