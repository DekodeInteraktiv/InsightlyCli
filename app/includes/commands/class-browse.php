<?php

namespace Dekode\InsightlyCli\Commands;

use Dekode\InsightlyCli\Services\InsightlyService;
use Dekode\InsightlyCli\Services\OperatingSystemService;

class Browse extends Command {

	/**
	 * Returns the string used to run this command.
	 *
	 * @return string
	 */
	public function get_key(): string {
		return 'browse';
	}

	/**
	 * Returns a short description for this command-
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Opens a project\'s Insightly profile in a browser.';
	}

	/**
	 * Returns the help text for this command.
	 *
	 * @return string
	 */
	public function get_help(): string {
		$help = "Usage:\nisc " . $this->get_key() . " <name of project>\n\n";
		$help .= "This command will do a fuzzy search and open the closest match.";

		return $help;

	}

	/**
	 * Executes this command
	 */
	public function run() {
		$insightly_service = new InsightlyService( INSIGHTLY_API_KEY );
		$climate           = $this->get_climate();
		$os                = OperatingSystemService::get_current_os();

		$project = $this->get_most_similar_project_or_die( $this->get_arguments()[2] );

		$climate->success( 'Opening page for ' . $project->get_name() );
		exec( $os->get_open_in_browser_command() . ' ' . $project->get_insightly_url() );

	}
}