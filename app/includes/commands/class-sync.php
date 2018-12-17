<?php

namespace Dekode\InsightlyCli\Commands;

use Dekode\InsightlyCli\Models\Project;
use Dekode\InsightlyCli\Models\RackspaceLoadBalancer;
use Dekode\InsightlyCli\Models\RackspaceServer;
use Dekode\InsightlyCli\Services\DigitalOceanService;
use Dekode\InsightlyCli\Services\InsightlyService;
use Dekode\InsightlyCli\Services\RackspaceService;
use League\CLImate\CLImate;

class Sync extends Command {

	private $servers;

	/**
	 * Returns the string used to run this command.
	 *
	 * @return string
	 */
	public function get_key(): string {
		return 'sync';
	}

	/**
	 * Returns a short description for this command-
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Loops through all projects and tries to automatically update all fields.';
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
		$this->climate = $this->get_climate();

		$this->get_servers();

		$this->insightly_service = new InsightlyService( INSIGHTLY_API_KEY );
		$this->projects          = $this->insightly_service->get_projects();

		foreach ( $this->projects as $project ) {
			$this->climate->yellow( "\n" . 'Working with ' . $project->get_name() );


			if ( ! $project->get_prod_url() ) {
				$this->try_alternative_urls_and_update_if_found( $project );

				if ( ! $project->get_prod_url() ) {
					$this->climate->error( 'Domain could not be guessed.' );
					continue;
				}
			}

			$this->find_ssh_command_and_update( $project );
		}


	}


	/**
	 * @return array
	 */
	private function get_servers(): array {

		if ( ! $this->servers ) {


			$this->climate->yellow( 'Getting all Rackspace servers...' );
			$this->rackspace_service  = new RackspaceService( RACKSPACE_USERNAME, RACKSPACE_API_KEY );
			$rackspace_servers        = $this->rackspace_service->get_servers();
			$rackspace_load_balancers = $this->rackspace_service->get_load_balancers();

			$this->climate->yellow( 'Getting all Digital Ocean servers...' );
			$this->digital_ocean_service = new DigitalOceanService( DIGITAL_OCEAN_API_KEY );
			$digital_ocean_servers       = $this->digital_ocean_service->get_servers();

			$servers = array_merge( $rackspace_servers, $digital_ocean_servers, $rackspace_load_balancers );

			$this->servers = $servers;

		}

		return $this->servers;


	}

	/**
	 * @param Project $project
	 *
	 * @return array|null
	 */
	private function try_alternative_urls_and_update_if_found( Project $project ) {
		$url = 'https://' . $project->get_name();
		$this->climate->error( 'Has no production URL. Trying name as domain: ' . $url );

		try {
			$response = $this->fetch_url( $url );
		} catch ( \Exception $e ) {
			$this->climate->red( $e->getMessage() );

			return;
		}

		if ( $response->getStatusCode() == 200 ) {
			$this->climate->green( 'Page exists. Updating project and continuing.' );
			$project->set_prod_url( $url );
			$this->insightly_service->save_project( $project );
		}

	}

	private function find_ssh_command_and_update( Project $project ) {
		$this->climate->green( 'Trying to find SSH command.' );

		$ip = $this->get_ip( $project );

		if ( ! preg_match( '/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/', $ip ) ) {
			$this->climate->error( 'IP could not be found' );

			return;
		}

		$this->climate->green( 'IP found: ' . $ip );
		$server = $this->find_server_by_ip( $ip );

		if ( ! $server ) {
			$this->climate->error( 'Server for that IP could not be found' );

			return;
		}

		$this->climate->green( 'Host ' . $server->get_name() . ' found.' );

		$server_name_ip = gethostbyname( $server->get_name() );

		if ( $server_name_ip == $ip ) {
			$ssh_host = $server->get_name();
		} else {
			$ssh_host = $server->get_public_ip();
		}

		$ssh = 'ssh root@' . $ssh_host;

		$this->climate->green( 'Updating SSH to prod to "' . $ssh . '"' );

		$project->set_ssh_to_prod( $ssh );
		$this->insightly_service->save_project( $project );

	}

	/**
	 * @param Project $project
	 *
	 * @return string
	 */
	private function get_ip( Project $project ): string {
		$domain_name = str_replace( 'https://', '', $project->get_prod_url() );
		$domain_name = str_replace( 'http://', '', $domain_name );
		$domain_name = str_replace( '/', '', $domain_name );


		$this->climate->green( 'Getting IP of ' . $domain_name );

		$ip = gethostbyname( $domain_name );

		return $ip;

	}

	/**
	 * @param string $ip
	 *
	 * @return mixed
	 */
	private function find_server_by_ip( string $ip ) {
		$climate = $this->get_climate();
		$climate->yellow( 'Looking for IP ' . $ip );
		$servers = $this->get_servers();

		$found = false;
		foreach ( $servers as $server ) {
			if ( $ip == $server->get_public_ip() ) {
				$found = true;
				break;
			}
			if ( $ip == $server->get_private_ip() ) {
				$found = true;
				break;
			}

		}

		if ( ! $found ) {
			return false;
		}

		if ( $server instanceof RackspaceLoadBalancer ) {
			$climate->cyan( 'Is load balancer.' );

			$ips = $server->get_nodes();

			foreach ( $ips as $ip ) {
				$node = $this->find_server_by_ip( $ip );
				if ( $node && strpos( $node->get_name(), 'slave' ) === false ) { // We want to avoid the slave nodes.
					return $node;
				}
			}

		} else {
			return $server;
		}

	}

	/**
	 * @param string $url
	 *
	 * @return \GuzzleHttp\Psr7\Response
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	private function fetch_url( string $url ): \GuzzleHttp\Psr7\Response {
		$client = new \GuzzleHttp\Client();
		$res    = $client->request( 'GET', $url );

		return $res;


	}
}