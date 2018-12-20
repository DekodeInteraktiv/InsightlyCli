<?php

namespace Dekode\InsightlyCli\Commands;

use Dekode\InsightlyCli\Services\InsightlyService;

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
		$projects          = $insightly_service->get_projects_by_name_similarity( $project_name );

		$climate->yellow( 'Did you mean any of these projects?' );

		foreach ( $projects as $project ) {
			$climate->green( '   ' . $project->get_name() );
		}
	}

	/**
	 * @return array
	 */
	public function get_arguments(): array {
		return $this->arguments;
	}

	/**
	 * @param array $arguments
	 */
	public function set_arguments( array $arguments ) {
		$arguments = $this->parse_flags( $arguments );

		$this->arguments = $arguments;
	}


}
