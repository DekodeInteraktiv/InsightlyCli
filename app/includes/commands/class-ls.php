<?php

namespace Dekode\InsightlyCli\Commands;

use Dekode\Insightly\InsightlyService;

class Ls extends Command {

	/**
	 * Returns the string used to run this command.
	 *
	 * @return string
	 */
	public function get_key(): string {
		return 'ls';
	}

	/**
	 * Returns a short description for this command-
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Lists all projects in Insightly alphabetically.';
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
			print( $project->get_name() . "\n" );
		}


	}
}