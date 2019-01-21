<?php

namespace Dekode\InsightlyCli\Commands;

use Dekode\InsightlyCli\Services\InsightlyService;
use Dekode\InsightlyCli\Services\OperatingSystemService;

class SanityCheck extends Command {

	/**
	 * Returns the string used to run this command.
	 *
	 * @return string
	 */
	public function get_key(): string {
		return 'sanity-check';
	}

	/**
	 * Returns a short description for this command-
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Runs through all projects trying to find those that miss vital values.';
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
		$insightly_service = new InsightlyService( INSIGHTLY_API_KEY );
		$climate           = $this->get_climate();

		$projects = $insightly_service->get_projects();

		foreach ( $projects as $project ) {
			if ( ! $project->get_ssh_to_prod() ) {
				$report['Missing SSH to prod'][] = $project;
			}

			if ( ! $project->get_web_root() ) {
				$report['Missing web root'][] = $project;
			}

		}

		foreach ( $report as $label => $projects ) {
			$climate->yellow( "\n" . $label );

			foreach ( $projects as $project ) {
				$climate->cyan( $project->get_name() );

			}

		}

	}
}