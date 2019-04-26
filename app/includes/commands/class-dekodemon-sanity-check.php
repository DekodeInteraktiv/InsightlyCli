<?php

namespace Dekode\InsightlyCli\Commands;

use Dekode\InsightlyCli\Services\DekodemonService;
use Dekode\RemoteServers\Services\SSHService;

class DekodemonSanityCheck extends Command {

	private $insightly_service;

	/**
	 * Returns the string used to run this command.
	 *
	 * @return string
	 */
	public function get_key(): string {
		return 'dekodemon-sanity-check';
	}

	/**
	 * Returns a short description for this command-
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Will output a list with the dekodemon status for all the projects.';


	}

	/**
	 * Returns the help text for this command.
	 *
	 * @return string
	 */
	public function get_help(): string {
		$climate = $this->get_climate();
		$climate->green( "Usage:\nisc " . $this->get_key() . " <name of project>" );
		$climate->output();

		return '';

	}

	/**
	 * Executes this command
	 */
	public function run() {
		$this->climate = $this->get_climate();

		$this->insightly_service = new InsightlyService( INSIGHTLY_API_KEY );

		$projects = $this->insightly_service->get_projects();

		$i                  = 0;
		$number_of_projects = count( $projects );
		$results            = [];

		foreach ( $projects as $project ) {
			$dekodemon_service = new DekodemonService();
			$dekodemon_service->set_project( $project );

			$this->climate->green()->inline( '(' . ++ $i . ' / ' . $number_of_projects . ') Working with ' . $project->get_name() . '...' );

			try {

				$is_active = $dekodemon_service->plugin_is_activated();

				if ( $is_active == $dekodemon_service::YES ) {
					$this->climate->lightGreen( 'installed and activated' );
					continue;
				}

				if ( ! $project->get_ssh_to_prod() ) {
					$this->climate->red( 'plugin not activated. SSH command missing therefore unable to determine installation status.' );
					$results['Unable to determine'][] = $project;
					continue;

				}

				$ssh_service = new SSHService( $project->convert_to_ssh_server() );
				$is_multisite = $ssh_service->is_multisite();

				if ( $is_multisite ) {
					$main_site_url = $ssh_service->get_main_site_url_in_multisite();

					$main_site_url = parse_url( $main_site_url );
					$main_site_url = $main_site_url['host'];

					$prod_url = $project->get_prod_url();
					$prod_url = parse_url( $prod_url );
					$prod_url = $prod_url['host'];

					if ( $prod_url != $main_site_url ) {
						$this->climate->lightGreen( 'Irrelevant because child in multisite. Main site is ' . $main_site_url );
						continue;
					}

				}


				$is_installed = $dekodemon_service->plugin_is_installed();
				if ( $is_installed == $dekodemon_service::YES ) {
					$this->climate->yellow( 'installed but not active' );
					$results['Activate on'][] = $project;
					continue;
				}

				if ( $is_installed == $dekodemon_service::NO ) {
					$this->climate->red( 'not installed' );
					$results['Install on'][] = $project;

					continue;
				}

				$this->climate->red( 'unable to determine' );
				$results['Unable to determine'][] = $project;

			} catch ( \Exception $e ) {
				$this->climate->red( $e->getMessage() );

			}

		}

		print( "\n" );

		foreach ( $results as $key => $projects ) {
			$this->climate->bold( '-= ' . $key . ' =-' );

			foreach ( $projects as $project ) {
				$this->climate->yellow( $project->get_name() );
			}

			print( "\n" );

		}

	}
}