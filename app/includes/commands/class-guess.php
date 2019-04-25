<?php

namespace Dekode\InsightlyCli\Commands;

use Dekode\InsightlyCli\Models\Project;
use Dekode\InsightlyCli\Services\DekodemonService;
use Dekode\InsightlyCli\Services\InsightlyService;
use Dekode\InsightlyCli\Services\NetService;
use Dekode\InsightlyCli\Services\ServerService;
use Dekode\RemoteServers\Services\SSHService;
use GuzzleHttp\Exception\ClientException;

class Guess extends Command {

	private $servers;

	/**
	 * Returns the string used to run this command.
	 *
	 * @return string
	 */
	public function get_key(): string {
		return 'guess';
	}

	/**
	 * Returns a short description for this command-
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Tries to guess some of the technical values for a project.';
	}

	/**
	 * Returns the help text for this command.
	 *
	 * @return string
	 */
	public function get_help(): string {
		$help = "Usage:\nisc " . $this->get_key() . " <name of project>\n\n";

		return $help;

	}

	/**
	 * Executes this command
	 */
	public function run() {
		$this->climate = $this->get_climate();

		$this->insightly_service = new InsightlyService( INSIGHTLY_API_KEY );
		$this->net_service       = new NetService();
		$this->server_service    = new ServerService();

		$arguments = $this->get_arguments();

		if ( key_exists( 'all', $arguments ) ) {
			$projects = $this->insightly_service->get_projects();

			$sites = [];
		} else {
			$sites = array_slice( $arguments, 2 );
		}

		if ( count( $sites ) == 0 ) {
			$this->climate->red( 'No project was specified' );
		}

		$this->climate->yellow( 'Getting all servers...' );

		if ( ! defined( 'RACKSPACE_API_KEY' ) ) {
			$this->climate->red( 'Missing Rackspace API key. Rackspace servers and load balancers will not be loaded.' );
		}
		if ( ! defined( 'DIGITAL_OCEAN_API_KEY' ) ) {
			$this->climate->red( 'Missing Digital Ocean API key. Rackspace servers and load balancers will not be loaded.' );
		}

		foreach ( $sites as $site ) {

			$project = $this->get_most_similar_project_or_die( $site );

			$this->dekodemon_service = new DekodemonService();
			$this->dekodemon_service->set_project( $project );

			$original_project = clone $project;

			$this->climate->yellow( "\n" . 'Working with ' . $project->get_name() );

			$prod_url = $this->guess_prod_url( $project );

			if ( ! $prod_url ) {
				$this->climate->red( 'Could not guess prod URL. Giving up.' );
				continue;

			}

			$project->set_prod_url( $prod_url );
			$this->climate->green()->inline( 'Guessing that prod URL is: ' );
			$this->climate->cyan( $prod_url );

			$ip = $this->get_ip( $project );
			if ( ! $this->net_service->is_ip( $ip ) ) {
				$this->climate->red( 'Could not find IP address' );

			}


			$this->climate->green()->inline( 'Guessing that IP address is ' );
			$this->climate->cyan( $ip );


			$server = $this->server_service->guess_production_server( $ip );

			if ( $server['status'] == 0 ) {
				$provider = $this->net_service->guess_provider_by_ip( $ip );

				if ( $provider ) {
					$this->climate->green()->inline( 'Guessing external provider: ' );
					$this->climate->cyan( $provider );
				} else {
					$this->climate->red( 'Not able to guess server. Reason: "' . $server['message'] . '"' );
				}
			} else {
				$server = $server['server'];
				$this->climate->green()->inline( 'Guessing that server is located at ' );
				$this->climate->cyan( $server->get_provider_name() . ' / ' . $server->get_name() );
			}

			$this->climate->green()->inline( 'Dekode monitoring tool endpoint is ' );

			$dekodemon_is_active = $this->dekodemon_service->plugin_is_activated();

			if ( $dekodemon_is_active == DekodemonService::YES ) {
				$check_for_dekodemon_installed = false;
				$this->climate->lightGreen( 'active' );
			} elseif ( $dekodemon_is_active == DekodemonService::NO ) {
				$check_for_dekodemon_installed = true;
				$this->climate->red( 'not active' );

			}

			$this->climate->green()->inline( 'Guessing web server...' );

			$this->climate->cyan( $this->net_service->get_web_server( $project->get_prod_url() ) );

			if ( $project->get_ssh_to_prod() ) {
				$this->climate->green()->inline( 'Testing if SSH in Insightly works...' );

				$ssh_failed = false;
				try {
					@$ssh_service = new SSHService( $project->convert_to_ssh_server() );
					$this->climate->lightGreen( 'success' );
				} catch ( \Exception $e ) {
					$this->climate->red( 'failed' );
					$ssh_failed = true;

				}
			} else {
				$ssh_failed = true;
				$this->climate->red( 'No SSH command in Insightly' );
			}

			if ( $ssh_failed ) {
				$guessed_ssh_command = 'ssh root@' . $ip;
				$this->climate->green()->inline( 'Guessing SSH command: ' . $guessed_ssh_command . '...' );
				$project->set_ssh_to_prod( $guessed_ssh_command );
				try {
					@$ssh_service = new SSHService( $project->convert_to_ssh_server() );
					$this->climate->lightGreen( 'success' );
					$ssh_failed = false;
				} catch ( \Exception $e ) {
					$this->climate->red( 'failed' );
					$ssh_failed = true;

				}
			}

			if ( $ssh_failed ) {
				$this->climate->red( 'Could not connect to SSH. Giving up.' );
				continue;
			}

			$web_root = $ssh_service->guess_web_root();

			if ( $web_root ) {
				$this->climate->green()->inline( 'Guessing that web root is ' );
				$this->climate->cyan( $web_root );
			} else {
				$this->climate->red( 'Not able to guess web root.' );

			}

			$this->climate->green()->inline( 'Checking if wp cli is installed...' );

			if ( $ssh_service->wp_cli_is_installed() ) {
				$this->climate->lightGreen( 'installed' );
				$wp_cli_installed = true;
			} else {
				$this->climate->red( 'not installed' );
				$wp_cli_installed = false;

			}

			if ( $wp_cli_installed ) {
				$db_details = $ssh_service->get_db_details();

				if ( isset( $db_details['DB_HOST'] ) ) {
					$this->climate->green()->inline( 'Guessing that DB host is ' );
					$this->climate->cyan( $db_details['DB_HOST'] );
				} else {
					$this->climate->red( 'Not able to guess DB host.' );

				}


				if ( isset( $db_details['DB_NAME'] ) && $db_details['DB_NAME'] ) {
					$this->climate->green()->inline( 'Guessing that DB name is ' );
					$this->climate->cyan( $db_details['DB_NAME'] );
				} else {
					$this->climate->red( 'Not able to guess DB name.' );
				}

				if ( isset( $db_details['table_prefix'] ) && $db_details['table_prefix'] ) {
					$this->climate->green()->inline( 'Guessing that table prefix is ' );
					$this->climate->cyan( $db_details['table_prefix'] );
				} else {
					$this->climate->red( 'Not able to guess table prefix.' );
				}

				$multisite = $ssh_service->is_multisite();
				if ( $multisite == SSHService::YES ) {
					$main_site = $ssh_service->get_main_site_url_in_multisite();

					$this->climate->green()->inline( 'Is ' );
					$this->climate->lightGreen()->inline( 'multisite. ' );
					$this->climate->green()->inline( 'Main blog is ' );
					$this->climate->lightGreen( $main_site );

				} elseif ( $multisite == SSHService::NO ) {
					$this->climate->green()->inline( 'Is ' );
					$this->climate->lightGreen( 'single site.' );

				} else {
					$this->climate->red( 'Unable to guess multisite status.' );

				}
			}

			$os = $ssh_service->get_os();

			if ( $os ) {
				$this->climate->green()->inline( 'Guessing that OS on server is ' );
				$this->climate->cyan( $os );

			}

			if ( $check_for_dekodemon_installed ) {
				$this->climate->green()->inline( 'Dekode monitoring tool is ' );

				$dekodemon_is_installed = $this->dekodemon_service->plugin_is_installed();
				if ( $dekodemon_is_installed == DekodemonService::YES ) {
					$this->climate->lightGreen( 'installed' );

				} elseif ( $dekodemon_is_installed == DekodemonService::NO ) {
					$this->climate->red( 'not installed.' );

				}
			}


			if ( $original_project->get_web_root() != $web_root && $web_root ) {
				$this->climate->yellow( "\n" . '-= WEB ROOT =-' );
				$this->climate->red( 'Old value: ' . $original_project->get_web_root() );
				$this->climate->green( 'Guessed value: ' . $web_root );
				$input = $this->climate->yellow()->input( "\n" . 'Update to the one guessed? (Y/n)' );
				$input->accept( [ 'Y', 'N' ] );
				$input->defaultTo( 'Y' );
				$response = $input->prompt();

				if ( strtolower( $response ) == 'y' ) {
					$this->climate->green( 'Saving...' );
					$original_project->set_web_root( $web_root );
					$this->insightly_service->save_project( $original_project );
					$this->insightly_service->get_projects();
				}

			}
		}
	}


	/**
	 * If there is no production URL in Insightly, sometimes the name of the project is the production URL. Try that, and if successful, save.
	 *
	 * @param Project $project
	 *
	 * @return array|null
	 */
	private function guess_prod_url( Project $project ) {
		$urls = [ $project->get_prod_url(), 'https://' . $project->get_name() ];

		foreach ( $urls as $url ) {
			if ( $url ) {
				try {
					$response = $this->fetch_url( $url );
				} catch ( \Exception $e ) {
				}
			}

			if ( isset( $response ) && is_object( $response ) && $response->getStatusCode() == 200 ) {
				return $url;
			}
		}

	}

	/**
	 * Tries to get the IP address of the projects host name.
	 *
	 * @param Project $project
	 *
	 * @return string
	 */
	private function get_ip( Project $project ): string {

		$hosts = [];

		$domain_name = str_replace( 'https://', '', $project->get_prod_url() );
		$domain_name = str_replace( 'http://', '', $domain_name );
		$domain_name = str_replace( '/', '', $domain_name );

		$hosts[] = $domain_name;

		$ssh = $project->get_ssh_to_prod();

		if ( $ssh ) {
			$ssh = str_replace( 'ssh ', '', $ssh );
			list( $username, $host ) = explode( '@', $ssh );
			$hosts[] = $host;

		}

		foreach ( $hosts as $host ) {
			$ip = gethostbyname( $host );

			if ( $ip ) {
				return $ip;
			}
		}

	}

	/**
	 * Fetches a URL.
	 *
	 * @param string $url
	 *
	 * @return \GuzzleHttp\Psr7\Response
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	private function fetch_url( string $url ): \GuzzleHttp\Psr7\Response {
		$client = new \GuzzleHttp\Client();
		try {
			$res = $client->request( 'GET', $url );
		} catch ( ClientException $e ) {

			$res = $e->getResponse();
		}

		return $res;


	}
}