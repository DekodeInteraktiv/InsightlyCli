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
		$help .= "prod-url\tUpdates the URL for the production server." . "\n";
		$help .= "stage-url\tUpdates the URL for the stage server." . "\n";

		return $help;

	}

	/**
	 * Executes this command
	 */
	public function run() {
		$climate           = $this->get_climate();
		$insightly_service = new InsightlyService( INSIGHTLY_API_KEY );
		$project           = $insightly_service->get_project_by_name( $this->get_arguments()[2] );

		if ( ! $project ) {
			$climate->error( 'That project could not be found.' );
			exit;
		}

		$arguments        = $this->get_arguments();
		$fields_to_update = false;

		if ( isset( $arguments['ssh-to-prod'] ) ) {
			if ( $this->confirm( $project->get_ssh_to_prod(), $arguments['ssh-to-prod'], 'SSH to prod' ) ) {
				$fields_to_update = true;
				$project->set_ssh_to_prod( $arguments['ssh-to-prod'] );
			};
		}

		if ( isset( $arguments['prod-url'] ) ) {
			if ( $this->confirm( $project->get_prod_url(), $arguments['prod-url'], 'The URL to the production site' ) ) {
				$fields_to_update = true;
				$project->set_prod_url( $arguments['prod-url'] );
			};
		}

		if ( isset( $arguments['stage-url'] ) ) {
			if ( $this->confirm( $project->get_stage_url(), $arguments['stage-url'], 'The URL to the stage site' ) ) {
				$fields_to_update = true;
				$project->set_stage_url( $arguments['stage-url'] );
			};
		}


		if ( ! $fields_to_update ) {
			$climate->error( 'No fields to update.' );
			exit;
		}

		$insightly_service->save_project( $project );
		$climate->green( 'Project updated.' );
	}

	private function confirm( $old_value, $new_value, $label ) {
		$climate = $this->get_climate();
		$climate->bold( $label );
		$climate->red( "Old value:\t" . $old_value );
		$climate->green( "New value:\t" . $new_value );

		$valid_answer = false;
		while ( ! $valid_answer ) {
			$climate->yellow()->inline( "Are you sure (Y/n)?" );
			$answer = readline();

			if ( strtolower( $answer ) == 'y' || ! $answer ) {
				$fields_to_update = true;
				$valid_answer     = true;

				return true;
			} elseif ( strtolower( $answer ) == 'n' ) {
				$valid_answer = true;
			} else {
				$climate->error( 'Invalid answer' );
			}

		}

		return false;

	}
}