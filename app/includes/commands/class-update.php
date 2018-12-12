<?php

namespace Dekode\InsightlyCli\Commands;

use Dekode\InsightlyCli\Services\InsightlyService;

class Update extends Command {

	/**
	 * Returns the string used to run this command.
	 *
	 * @return string
	 */
	public function get_key(): string {
		return 'update';
	}

	/**
	 * Returns a short description for this command-
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Updates fields for a specific project.';
	}

	/**
	 * Returns the help text for this command.
	 *
	 * @return string
	 */
	public function get_help(): string {
		$help = "Usage:\n";
		$help .= "isc update <name of project> --ssh-to-prod=\"ssh root@somedomain\"\n\n";
		$help .= "Flags:\n";
		$help .= "ssh-to-prod\tUpdates the field \"SSH to prod\"" . "\n";

		return $help;

	}

	/**
	 * Executes this command
	 */
	public function run() {
		$insightly_service = new InsightlyService();
		$insightly_service->set_api_key( INSIGHTLY_API_KEY );
		$project = $insightly_service->get_project_by_name( $this->get_arguments()[2] );

		if ( ! $project ) {
			print ( "\n\e[31mThat project could not be found.\n" );
			exit;
		}

		$arguments = $this->get_arguments();

		if ( $arguments['ssh-to-prod'] ) {
			echo "\e[1mSSH to prod\e[1m\n";
			echo "\e[31mOld value:\t" . $project->get_ssh_to_prod() . "\n";
			echo "\e[32mNew value:\t" . $arguments['ssh-to-prod'] . "\n";
			echo "\e[93m";


			$valid_answer     = false;
			$fields_to_update = false;
			while ( ! $valid_answer ) {
				$answer = readline( "Are you sure (Y/n)?" );

				if ( strtolower( $answer ) == 'y' || ! $answer ) {
					$project->set_ssh_to_prod( $arguments['ssh-to-prod'] );
					$fields_to_update = true;
					$valid_answer     = true;
				} elseif ( strtolower( $answer ) == 'n' ) {
					$valid_answer = true;
				} else {
					echo 'Invalid answer' . "\n";
				}

			}
		}

		if ( ! $fields_to_update ) {
			print( "\e[31m" . 'No fields to update.' . "\n\n" );
			exit;
		}

		$insightly_service->save_project( $project );
		print( "\e[32mProject updated.\n\n" );
	}
}