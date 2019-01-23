<?php

namespace Dekode\InsightlyCli\Commands;

use Dekode\InsightlyCli\Services\InsightlyService;

class Find extends Command {

	/**
	 * Returns the string used to run this command.
	 *
	 * @return string
	 */
	public function get_key(): string {
		return 'find';
	}

	/**
	 * Returns a short description for this command-
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Get a list of the names of similar projects.';
	}

	/**
	 * Returns the help text for this command.
	 *
	 * @return string
	 */
	public function get_help(): string {
		$help = "Usage:\nisc find <search string></search>\n\n";

		return $help;

	}

	/**
	 * Executes this command
	 */
	public function run() {
		$climate = $this->get_climate();

		$insightly_service = new InsightlyService( INSIGHTLY_API_KEY );

		if ( ! isset( $this->get_arguments()[2] ) ) {
			$climate->error( 'No search string specified.' );
			exit;
		}

		$projects = $insightly_service->get_projects_by_name_similarity( $this->get_arguments()[2] );

		usort( $projects, function ( $a, $b ) {
			return strcmp( $a->get_name(), $b->get_name() );
		} );

		$climate->green( 'Found these projects:' );
		foreach ( $projects as $project ) {
			$climate->yellow( $project->get_name() );

		}


	}
}