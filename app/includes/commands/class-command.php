<?php

namespace Dekode\InsightlyCli\Commands;

use Dekode\Insightly\Models\Project;
use Dekode\Insightly\InsightlyService;
use Dekode\RemoteServers\Models\SSHServer;

abstract class Command {
	private $arguments;

	/**
	 * Executes the given command.
	 *
	 * @return mixed
	 */
	abstract public function run();

	/**
	 * Returns the string used to run this command.
	 *
	 * @return string
	 */
	abstract public function get_key(): string;

	/**
	 * Returns a short description for this command-
	 *
	 * @return string
	 */
	abstract public function get_description(): string;

	/**
	 * Returns the help text for this command.
	 *
	 * @return string
	 */
	abstract public function get_help(): string;

	/**
	 * Gets an instance of climate.
	 *
	 * @return \League\CLImate\CLImate
	 */
	protected function get_climate() {
		$climate = new \League\CLImate\CLImate;

		return $climate;
	}


	protected function parse_flags( $arguments ) {
		foreach ( $arguments as $argument ) {
			if ( strpos( $argument, '--' ) === 0 ) {
				if ( strpos( $argument, '=' ) !== false ) {

					list( $flag, $value ) = explode( '=', $argument );
					$flag = str_replace( '--', '', $flag );

					$arguments[ $flag ] = $value;


				} else {
					$flag               = str_replace( '--', '', $argument );
					$arguments[ $flag ] = null;
				}
			}
		}

		return $arguments;
	}

	protected function show_similar_projects( $project_name ) {
		$insightly_service = new InsightlyService( INSIGHTLY_API_KEY );
		$climate           = $this->get_climate();
		$projects          = $insightly_service->get_most_similar_project( $project_name );

		$climate->yellow( 'Did you mean any of these projects?' );

		foreach ( $projects as $project ) {
			$climate->green( '   ' . $project->get_name() );
		}
	}

	protected function get_most_similar_project_or_die( $name ): Project {
		$climate           = $this->get_climate();
		$insightly_service = new InsightlyService( INSIGHTLY_API_KEY );

		$similar_projects = $insightly_service->get_most_similar_project( $name );

		if ( count( $similar_projects ) > 1 ) {
			$climate->green( 'Several similar projects were found. Please be a bit more specific:' );

			foreach ( $similar_projects as $project ) {
				$climate->yellow( '   ' . $project->get_name() );

			}

			exit;
		}

		if ( ! count( $similar_projects ) ) {
			$climate->error( 'No similar project was found.' );
			exit;
		}

		$project = $similar_projects[0];

		return $project;

	}

	protected function get_exact_project_or_die( $name ): Project {
		if ( trim( $name ) ) {
			$project = $this->insightly_service->get_project_by_name( $name );
			if ( ! $project ) {
				$this->climate->error( 'Could not find that project.' );
				$this->climate->output();
				$this->show_similar_projects( $name );
				exit;
			}

		} else {
			$this->climate->error( 'No project was specified.' );
			exit;
		}

		return $project;

	}

	/**
	 * Will create an object of type SSHServer ready to be passed to the SSHService.
	 *
	 * @return SSHServer
	 */
	protected function convert_to_ssh_server( Project $project ) {
		$ssh_server = new SSHServer();
		$ssh_server->set_ssh_command( $project->get_ssh_to_prod() );
		$ssh_server->set_domain( $project->get_prod_domain() );
		$ssh_server->set_production_url( $project->get_prod_url() );
		$ssh_server->set_web_root( $project->get_web_root() );

		return $ssh_server;
	}


	/**
	 * @return array
	 */
	public function get_arguments(): array {
		return $this->arguments;
	}

	/**
	 * @param  array $arguments
	 */
	public function set_arguments( array $arguments ) {

		$arguments = $this->parse_flags( $arguments );

		$this->arguments = $arguments;
	}


}
