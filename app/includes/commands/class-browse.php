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
		$insightly_service = new InsightlyService();
		$similar_projects  = $insightly_service->get_projects_by_name_similarity( $this->get_arguments()[2] );
		$climate           = $this->get_climate();
		$os                = OperatingSystemService::get_current_os();

		if ( $similar_projects[0] ) {
			$climate->success( 'Opening page for ' . $similar_projects[0]->get_name() );
			exec( $os->get_open_in_browser_command() . ' ' . $similar_projects[0]->get_insightly_url() );
		} else {
			$climate->error( 'Could not find this project.' );
		}

	}
}