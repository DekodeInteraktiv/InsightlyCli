<?php

namespace Dekode\InsightlyCli\Commands;

use Dekode\InsightlyCli\Services\InsightlyService;

class UpdateAutocomplete extends Command {

	/**
	 * Returns the string used to run this command.
	 *
	 * @return string
	 */
	public function get_key(): string {
		return 'update-autocomplete';
	}

	/**
	 * Returns a short description for this command-
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Will make an autocomplete-file for zsh';
	}

	/**
	 * Returns the help text for this command.
	 *
	 * @return string
	 */
	public function get_help(): string {
		$help = "Usage:\nisc generate-autocomplete\n\n";

		return $help;

	}

	/**
	 * Executes this command
	 */
	public function run() {
		$climate = $this->get_climate();
		if (!file_exists('~/.oh-my-zsh/completions')) {
			$climate->error('Directory ~/.oh-my-zsh/completions does not exist. Create it and run command again.');
		}

		$insightly_service = new InsightlyService();
		$insightly_service->set_api_key( INSIGHTLY_API_KEY );
		$projects = $insightly_service->get_projects();

		foreach ( $projects as $project ) {
			print ( $project->get_name() . ' ' );
		}

		print ( "\n" );
	}
}