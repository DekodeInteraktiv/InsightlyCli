<?php

namespace Dekode\InsightlyCli\Commands;

use Dekode\InsightlyCli\Services\InsightlyService;
use Dekode\InsightlyCli\Services\SSHService;

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
		return 'Get information about a project.';
	}

	/**
	 * Returns the help text for this command.
	 *
	 * @return string
	 */
	public function get_help(): string {
		$help = "Usage:\nisc find <name of project>\n\n";
		$help .= "Examples:\n";
		$help .= 'isc find finansforbundet.no' . "\n";

		return $help;

	}

	/**
	 * Executes this command
	 */
	public function run() {
		$climate = $this->get_climate();

		$insightly_service = new InsightlyService( INSIGHTLY_API_KEY );

		if ( ! isset( $this->get_arguments()[2] ) ) {
			$climate->error( 'No project specified.' );
			exit;
		}

		$similar_projects = $insightly_service->get_projects_by_name_similarity( $this->get_arguments()[2] );

		if ( is_array( $similar_projects ) && count( $similar_projects ) ) {
			$project = $similar_projects[0];
		}

		if ( ! $project ) {
			$climate->error( 'No similar project was found.' );
			exit;
		}


		$climate->green()->bold()->out( '-= ' . strtoupper( $project->get_name() ) . " =- \n" );
		$climate->cyan( "ID:\t\t\t" . $project->get_id() );
		$climate->cyan( "URL:\t\t\t" . $project->get_insightly_url() . "\n" );

		$climate->yellow( "Responsbile advisor:\t" . $project->get_responsible_advisor() );
		$climate->yellow( "Project manager:\t" . $project->get_project_manager() );
		$climate->yellow( "Service agreement:\t" . $project->get_service_agreement() );
		$climate->yellow( "Hosting agreement:\t" . $project->get_hosting_level_agreement() );
		$climate->yellow( "Incidents report to:\t" . $project->get_incidents_email_report_client() . "\n" );

		$climate->green( "SSH to prod:\t\t" . $project->get_ssh_to_prod() );
		$climate->green( "Web root:\t\t" . $project->get_web_root() );
		$climate->green( "Prod. server:\t\t" . $project->get_prod_server() );
		$climate->green( "Reverse proxy:\t\t" . $project->get_reverse_proxy() );
		$climate->green( "DB instance:\t\t" . $project->get_db_instance() . "\n" );


		$climate->red( "Prod URL:\t\t" . $project->get_prod_url() );
		$climate->red( "Stage URL:\t\t" . $project->get_stage_url() . "\n" );

		if ( $project->get_hosting_notes() ) {
			$climate->white( 'Hosting notes:' );
			$climate->white( $project->get_hosting_notes() );
		}

	}
}