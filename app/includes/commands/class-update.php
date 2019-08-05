<?php

namespace Dekode\InsightlyCli\Commands;

use Dekode\Insightly\InsightlyService;

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
		$help .= "isc update <name of project> --ssh-to-prod=\"ssh root@somedomain\" <other flags>\n\n";
		$help .= "Flags:\n";
		$help .= "ssh-to-prod\tUpdates the field \"SSH to prod\"" . "\n";
		$help .= "web-root\tUpdates the web root of this project." . "\n";

		return $help;

	}

	/**
	 * Executes this command
	 */
	public function run() {
		$climate           = $this->get_climate();
		if ( isset( $this->get_arguments()[2] ) ) {
			$project = $this->insightly_service->projects()->get_project_by_name( $this->get_arguments()[2] );
		} else {
			$climate->error( 'No project specified.' );
			exit;
		}

		if ( ! $project ) {
			$climate->error( 'That project could not be found.' );
			$climate->output();
			$this->show_similar_projects( $this->get_arguments()[2] );

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

		if ( isset( $arguments['web-root'] ) ) {
			if ( $this->confirm( $project->get_web_root(), $arguments['web-root'], 'The project\'s web root' ) ) {
				$fields_to_update = true;
				$project->set_web_root( $arguments['web-root'] );
			};
		}


		if ( ! $fields_to_update ) {
			$climate->error( 'No fields to update.' );
			exit;
		}

		$this->insightly_service->projects()->save_project( $project );
		$this->insightly_service->projects()->clear_cache();
		$this->insightly_service->projects()->get_projects();
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