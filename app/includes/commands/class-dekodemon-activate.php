<?php

namespace Dekode\InsightlyCli\Commands;

use Dekode\InsightlyCli\Services\DekodemonService;
use Dekode\InsightlyCli\Services\DigitalOceanService;
use Dekode\InsightlyCli\Services\RackspaceService;
use Dekode\RemoteServers\Services\SSHService;

class DekodemonActivate extends Command {

	/**
	 * Returns the string used to run this command.
	 *
	 * @return string
	 */
	public function get_key(): string {
		return 'dekodemon-activate';
	}

	/**
	 * Returns a short description for this command-
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Tries to enable dekodemon and resets the secret string.';
	}

	/**
	 * Returns the help text for this command.
	 *
	 * @return string
	 */
	public function get_help(): string {
		$help = "Usage:\nisc " . $this->get_key() . " <name of project>\n\n";
		$help .= '--all Will try to activate dekodemon on all sites.';
		$help .= '--reset Will generate a new string even if plugin is already active.';

		return $help;

	}

	/**
	 * Executes this command
	 */
	public function run() {
		$this->climate = $this->get_climate();

		$arguments = $this->get_arguments();
		if ( array_key_exists( 'all', $arguments ) ) {
			$input    = $this->climate->red()->input( 'WARNING: This will reset the dekodemon secret strings for ALL sites. Enter "yes" if you are sure:' );
			$response = $input->prompt();

			if ( $response != 'yes' ) {
				exit;
			} else {
				$projects = $this->insightly_service->get_projects();
			}
		} else {
			if ( isset( $arguments[2] ) ) {
				$project = $this->insightly_service->get_project_by_name( $arguments[2] );
				if ( ! $project ) {
					$this->climate->error( 'Could not find that project.' );
					$this->climate->output();
					$this->show_similar_projects( $arguments[2] );
					exit;
				}

				$projects = [ $project ];

			} else {
				$this->climate->error( 'No project was specified.' );
				exit;
			}
		}

		$number_of_projects = count( $projects );
		$i                  = 0;
		$csv                = [];
		foreach ( $projects as $project ) {
			$dekodemon_service = new DekodemonService();
			$dekodemon_service->set_project( $project );

			try {
				$this->climate->yellow( "\n(" . ++ $i . ' / ' . $number_of_projects . ') ' . $project->get_name() );
				if ( ! $project->get_web_root() ) {
					$this->climate->red( 'No web root. Skipping.' );
					continue;

				}

				$plugin_is_activated = $dekodemon_service->plugin_is_activated();

				if ( $plugin_is_activated == DekodemonService::YES ) {
					$this->climate->green( 'Plugin is already activated.' );
					if ( ! array_key_exists( 'reset', $arguments ) ) {
						continue;
					}

				}

				$plugin_is_installed = $dekodemon_service->plugin_is_installed();

				if ( $plugin_is_installed == DekodemonService::NO ) {
					$this->climate->red( 'Plugin is not installed.' );
					continue;
				}

				if ( $plugin_is_installed == DekodemonService::CANNOT_DETERMINE ) {
					$this->climate->red( 'Cannot determine.' );
					continue;
				}

				$this->climate->green( 'Plugin is installed' );

				$ssh_service = new SSHService( $project->convert_to_ssh_server() );

				$is_multisite = $ssh_service->is_multisite();

				if ( $is_multisite ) {
					$this->climate->yellow( 'Is multisite' );

					$main_site_url = $ssh_service->get_main_site_url_in_multisite();


					if ( $main_site_url ) {

						$main_site_url = parse_url( $main_site_url );

						$main_site_url = $main_site_url['host'];

						$prod_url = $project->get_prod_url();

						$prod_url = parse_url( $prod_url );
						$prod_url = $prod_url['host'];

						if ( $prod_url != $main_site_url || ! $main_site_url ) {
							$this->climate->red( 'This does not seem to be the main site in this network. Main site is ' . $main_site_url );
							continue;
						}
					}

				}

				$db_credentials = $ssh_service->get_db_details();

				if ( ! $db_credentials['DB_HOST'] || ! $db_credentials['DB_NAME'] || ! $db_credentials['DB_USER'] || ! $db_credentials['DB_PASSWORD'] || ! $db_credentials['table_prefix'] ) {
					$this->climate->red( 'Insufficient DB credentials' );
					continue;
				}

				$mysql_connect_command = 'mysql -h ' . $db_credentials['DB_HOST'] . ' -u ' . $db_credentials['DB_USER'] . ' -p' . $db_credentials['DB_PASSWORD'] . ' ' . $db_credentials['DB_NAME'] . ' -e ';

				$ssh_service->run_raw_command( $mysql_connect_command . '"DELETE FROM ' . $db_credentials['table_prefix'] . 'options WHERE option_name = \"dekode_monitoring_tool_secret_string_hash\" OR option_name = \"_transient_dekode_monitoring_tool_secret_string\" OR option_name = \"_transient_timeout_dekode_monitoring_tool_secret_string\";"' );
				$output = $ssh_service->run_raw_command( 'cd ' . $project->get_web_root() . ' && wp plugin deactivate dekode-monitoring-tool-client --allow-root  && wp plugin activate dekode-monitoring-tool-client --allow-root' );


				unset( $matches );
				preg_match( '/Secret string: (.*)/', $output, $matches );

				if ( isset( $matches[1] ) ) {
					$secret_string = $matches[1];
				} else {

					$output = $ssh_service->run_raw_command( $mysql_connect_command . '"SELECT * FROM ' . $db_credentials['table_prefix'] . 'options WHERE option_name=\'_transient_dekode_monitoring_tool_secret_string\';"' );

					$output        = explode( "\n", $output );
					$secret_string = '';

					foreach ( $output as $line ) {
						unset( $matches );
						preg_match( ' /_transient_dekode_monitoring_tool_secret_string(.*)?	no/ ', $line, $matches );
						if ( isset( $matches[1] ) ) {
							$secret_string = $matches[1];
						}
					}
				}


				$secret_string = trim( $secret_string );
				if ( $secret_string ) {
					$row           = [];
					$row['host']   = $project->get_prod_url();
					$row['secret'] = $secret_string;
					$csv[]         = $row;

					$this->climate->green( 'Secret string added to CSV: ' . $secret_string );
				} else {
					$this->climate->red( 'No secret string found. Deactivating plugin again.' );
					$ssh_service->run_raw_command( 'cd ' . $project->get_web_root() . ' && wp plugin deactivate dekode - monitoring - tool - client --allow - root' );

				}
			} catch ( \Exception $e ) {
				$this->climate->red( $e->getMessage() );
			}
		}

		$csv_file = '';
		foreach ( $csv as $row ) {
			$csv_file .= join( ',', $row ) . "\n";
		}

		$this->climate->bold( 'Please paste the following CSV into "import sites" on cp.dekodes.no' );
		print( $csv_file );

	}
}
