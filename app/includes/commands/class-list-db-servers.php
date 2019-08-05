<?php

namespace Dekode\InsightlyCli\Commands;

use Dekode\RemoteServers\Services\SSHService;
use Dekode\Insightly\InsightlyService;

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
		$projects          = $this->insightly_service->projects()->get_projects();

		usort( $projects, function ( $a, $b ) {
			return strcmp( strtolower( $a->get_name() ), strtolower( $b->get_name() ) );
		} );


		foreach ( $projects as $project ) {
			$db_details = [];

			try {
				@$ssh_service = new SSHService( $this->convert_to_ssh_server( $project ) );

				@$db_details = $ssh_service->get_db_details();
			} catch ( \Exception $e ) {
				$db_details['DB_HOST'] = 'Error: ' . $e->getMessage();
			}

			if ( ! $db_details['DB_HOST'] ) {
				$db_details['DB_HOST'] = 'Unknown or localhost';
			}

			print( $project->get_name() . ': ' . $db_details['DB_HOST'] . "\n" );
		}


	}
}