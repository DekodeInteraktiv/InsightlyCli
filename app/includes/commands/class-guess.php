<?php

namespace Dekode\InsightlyCli\Commands;

use Dekode\InsightlyCli\Models\Project;
use Dekode\InsightlyCli\Models\RackspaceLoadBalancer;
use Dekode\InsightlyCli\Services\DigitalOceanService;
use Dekode\InsightlyCli\Services\InsightlyService;
use Dekode\InsightlyCli\Services\NetService;
use Dekode\InsightlyCli\Services\RackspaceService;
use Dekode\InsightlyCli\Services\SSHService;

class Guess extends Command {

	private $servers;
	private $report = [];

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

		$arguments = $this->get_arguments();
		if ( isset( $arguments[2] ) ) {
			$project = $this->insightly_service->get_projects_by_name_similarity( $arguments[2] );
			if ( ! $project ) {
				$this->climate->error( 'Could not find that project.' );
				exit;
			}

		} else {
			$this->climate->error( 'No project was specified.' );
			exit;
		}

		$this->get_servers();


		$this->projects = [ $project[0] ];

		$number_of_projects = count( $this->projects );
		$i                  = 0;

		foreach ( $this->projects as $project ) {
			$i ++;
			$this->climate->yellow( "\n" . '(' . $i . '/' . $number_of_projects . ') Working with ' . $project->get_name() );


			if ( ! $project->get_prod_url() ) {
				$this->try_alternative_urls( $project );

				if ( ! $project->get_prod_url() ) {
					$this->climate->error( 'Domain could not be guessed.' );
					continue;
				}
			}

			$this->find_production_server( $project );

			$this->climate->output();
			$this->climate->yellow( 'Server location: ' . $project->get_prod_server() );
			$this->climate->yellow( $project->get_ssh_to_prod() . " -t 'cd " . $project->get_web_root() . "; bash'" );
			$this->climate->output();

			exec( 'alias isc-ssh="' . $project->get_ssh_to_prod() . " -t 'cd " . $project->get_web_root() . "; bash'\"" );

			$this->climate->green( 'Type "isc-ssh" to run the above command.' );


		}

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
	private function try_alternative_urls( Project $project ) {
		$url = 'https://' . $project->get_name();
		$this->climate->error( 'Has no production URL. Trying name as domain: ' . $url );

		try {
			$response = $this->fetch_url( $url );
		} catch ( \Exception $e ) {
			$this->climate->red( $e->getMessage() );

			return;
		}

		if ( $response->getStatusCode() == 200 ) {
			$this->climate->green( 'Page exists.' );
			$project->set_prod_url( $url );
		}

	}

	/**
	 * Try to find the IP address/host name of the server and save the ssh command.
	 *
	 * @param Project $project
	 */
	private function find_production_server( Project $project ) {
		$this->climate->purple( 'Trying to find SSH command.' );

		$ip = $this->get_ip( $project );

		if ( ! $this->net_service->is_ip( $ip ) ) {
			$this->climate->error( 'IP could not be found' );

			return;
		}

		$this->climate->green( 'IP found: ' . $ip );
		$server = $this->find_server_by_ip( $ip );

		if ( ! $server ) {
			$this->climate->cyan( 'Server for that IP could not be found' );

			$reverse_proxy = $this->net_service->find_reverse_proxy( $ip );

			if ( $reverse_proxy ) {
				$this->climate->red( 'Found reverse proxy: "' . $reverse_proxy . '". Giving up.' );
				exit;

				return;

			}


			$this->climate->red( 'Giving up since we could not find server.' );
			exit;
		}

		$this->climate->green( 'Host ' . $server->get_name() . ' found.' );

		$server_name_ip = gethostbyname( $server->get_name() );

		if ( $server_name_ip == $ip ) {
			$ssh_host = $server->get_name();
		} else {
			$ssh_host = $server->get_public_ip();
		}

		$ssh = 'ssh root@' . $ssh_host;

		$this->climate->green( 'SSH command: "' . $ssh . '"' );
		$this->climate->green( 'Production server name: "' . $server->get_insightly_name() . '"..."' );
		$this->climate->cyan( 'Trying to find web root...' );

		$project->set_ssh_to_prod( $ssh );
		$project->set_prod_server( $server->get_insightly_name() );

		try {
			$ssh_service = new SSHService( $project );
			$web_root    = $ssh_service->get_web_root();
		} catch ( Exception $e ) {
			$this->report['could_not_ssh'][] = $project;
		}

		if ( $web_root ) {
			$this->climate->green( 'Found it: ' . $web_root );
			$project->set_web_root( $web_root );
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