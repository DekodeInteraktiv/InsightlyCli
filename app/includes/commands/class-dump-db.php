<?php

namespace Dekode\InsightlyCli\Commands;

use Dekode\InsightlyCli\Models\Project;
use Dekode\InsightlyCli\Models\RackspaceLoadBalancer;
use Dekode\InsightlyCli\Services\DigitalOceanService;
use Dekode\InsightlyCli\Services\InsightlyService;
use Dekode\InsightlyCli\Services\NetService;
use Dekode\InsightlyCli\Services\RackspaceService;
use Dekode\InsightlyCli\Services\SSHService;

class DumpDB extends Command {

	/**
	 * Returns the string used to run this command.
	 *
	 * @return string
	 */
	public function get_key(): string {
		return 'dump-db';
	}

	/**
	 * Returns a short description for this command-
	 *
	 * @return string
	 */
	public function get_description(): string {
		$climate = $this->get_climate();

		$climate->yellow()->inline( 'Will try to dump an SQL database export to stdout.' );

		return '';


	}

	/**
	 * Returns the help text for this command.
	 *
	 * @return string
	 */
	public function get_help(): string {
		$climate = $this->get_climate();
		$climate->yellow( "Usage:\nisc " . $this->get_key() . " <name of project> > ~/file.sql" );
		$climate->output();

		$climate->red( 'There may be some command line errors at the top of the dump. They must be removed manually.' );

		return '';

	}

	/**
	 * Executes this command
	 */
	public function run() {
		$this->climate = $this->get_climate();

		$this->insightly_service = new InsightlyService( INSIGHTLY_API_KEY );

		$arguments = $this->get_arguments();
		if ( isset( $arguments[2] ) ) {
			$project = $this->insightly_service->get_project_by_name( $arguments[2] );
			if ( ! $project ) {
				$this->climate->error( 'Could not find that project.' );
				exit;
			}

		} else {
			$this->climate->error( 'No project was specified.' );
			exit;
		}

		$ssh_service = new SSHService( $project );
		$ssh_service->show_database_dump_commands();


	}


}