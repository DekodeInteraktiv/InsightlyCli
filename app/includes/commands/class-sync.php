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
	private $report = [];

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

		$number_of_projects = count( $this->projects );
		$i                  = 0;
		foreach ( $this->projects as $project ) {
			$i ++;
			$this->climate->yellow( "\n" . '(' . $i . '/' . $number_of_projects . ') Working with ' . $project->get_name() );


			if ( ! $project->get_prod_url() ) {
				$this->try_alternative_urls_and_update_if_found( $project );

				if ( ! $project->get_prod_url() ) {
					$this->climate->error( 'Domain could not be guessed.' );
					$this->report['invalid_host'][] = $project;
					continue;
				}
			}

			$this->find_production_server_and_update( $project );


		}

		$this->output_report();


	}


	/**
	 * Gets all servers from all our service providers.
	 *
	 * @return array
	 */
	private function get_servers(): array {

		if ( ! $this->servers ) {

			$this->climate->yellow( 'Getting all Rackspace servers...' );
			$this->rackspace_service = new RackspaceService( RACKSPACE_USERNAME, RACKSPACE_API_KEY );
			$rackspace_servers       = $this->rackspace_service->get_servers();

			$this->climate->yellow( 'Getting all Rackspace load balancers...' );
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
	 * If there is no production URL in Insightly, sometimes the name of the project is the production URL. Try that, and if successful, save.
	 *
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

	/**
	 * Try to find the IP address/host name of the server and save the ssh command.
	 *
	 * @param Project $project
	 */
	private function find_production_server_and_update( Project $project ) {
		$this->climate->green( 'Trying to find SSH command.' );

		$ip = $this->get_ip( $project );

		if ( ! $this->is_ip( $ip ) ) {
			$this->climate->error( 'IP could not be found' );

			return;
		}

		$this->climate->green( 'IP found: ' . $ip );
		$server = $this->find_server_by_ip( $ip );

		if ( ! $server ) {
			$this->climate->cyan( 'Server for that IP could not be found' );

			$reverse_proxy = $this->find_reverse_proxy( $ip );

			if ( $reverse_proxy ) {
				$this->climate->magenta( 'Found reverse proxy: "' . $reverse_proxy . '". Saving...' );
				$project->set_reverse_proxy( $reverse_proxy );
				$this->insightly_service->save_project( $project );

				return;

			}


			$this->climate->cyan( 'Setting production server name to "other"...' );
			$project->set_prod_server( 'Other' );
			$this->insightly_service->save_project( $project );

			$this->report['unknown_server'][] = $project;

			return;
		}

		$this->climate->green( 'Host ' . $server->get_name() . ' found.' );

		$server_name_ip = gethostbyname( $server->get_name() );

		if ( $server_name_ip == $ip ) {
			$ssh_host = $server->get_name();
		} else {
			$ssh_host = $server->get_public_ip();
		}

		if ( $this->is_ip( $ssh_host ) ) {
			$ssh_host = gethostbyaddr( $ssh_host );
		}

		$ssh = 'ssh root@' . $ssh_host;

		$this->climate->cyan( 'Updating SSH to prod to "' . $ssh . '"' );
		$this->climate->cyan( 'Setting production server name to "' . $server->get_insightly_name() . '"..."' );

		$project->set_ssh_to_prod( $ssh );
		$project->set_prod_server( $server->get_insightly_name() );
		$this->insightly_service->save_project( $project );

	}

	/**
	 * Tries to get the IP address of the projects host name.
	 *
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
	 * Loops through our list of servers trying to find a server which matches a specific IP address.
	 *
	 * @param string $ip The IP address to find.
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
	 * Outputs the report after having gone through all projects.
	 */
	private function output_report() {
		$climate = $this->get_climate();

		$climate->cyan( 'STATUS REPORT:' );


		foreach ( $this->report as $label => $projects ) {
			$climate->green( '-= ' . $label . ' =-' );

			foreach ( $projects as $project ) {
				$climate->yellow( $project->get_name() . ' (' . $project->get_insightly_url() . ')' );
			}

			print( "\n" );

		}
	}

	private function find_reverse_proxy( $ip ) {
		$parser = new \Novutec\WhoisParser\Parser();

		$result = $parser->lookup( $ip );

		$raw_data = current( $result->rawdata );

		if ( stripos( $raw_data, 'sucuri' ) !== false ) {
			return 'Sucuri';
		}

		if ( stripos( $raw_data, 'cloudflare' ) !== false ) {
			return 'Cloudflare';
		}

		return false;


	}

	/**
	 * Checks if the given string is an IPv4 address
	 *
	 * @param string $ip
	 *
	 * @return boolean
	 */
	private function is_ip( string $ip ): bool {
		return preg_match( '/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/', $ip );
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
		$res    = $client->request( 'GET', $url );

		return $res;


	}
}