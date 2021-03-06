<?php

namespace Dekode\InsightlyCli\Commands;

use Dekode\Insightly\InsightlyService;

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

		if ( ! isset( $this->get_arguments()[2] ) ) {
			$climate->error( 'No search string specified.' );
			exit;
		}

		$projects = $this->insightly_service->projects()->get_projects_by_search_string( $this->get_arguments()[2] );

		$climate->green( 'Found these projects:' );
		for ( $i = 0; $i < 10; $i ++ ) {
			$climate->yellow()->inline( $projects[ $i ]['project']->get_name() );

			switch ($projects[ $i ]['match_found_in_key']) {
				case $this->insightly_service->projects()::SEARCH_KEY_PRODUCTION_DOMAIN:
					$climate->lightGreen( ' (Production domain: ' . $projects[ $i ]['match_found_in_string'] . ')' );
					break;
				case $this->insightly_service->projects()::SEARCH_KEY_STAGE_DOMAIN:
					$climate->lightGreen( ' (Stage domain: ' . $projects[ $i ]['match_found_in_string'] . ')' );
					break;
				case $this->insightly_service->projects()::SEARCH_KEY_EMAIL_ADDRESS:
					$climate->lightGreen( ' (Email address: ' . $projects[ $i ]['match_found_in_string'] . ')' );
					break;
				default:
					echo "\n";
			}


		}


	}
}