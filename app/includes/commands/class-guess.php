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
			$project = $this->get_most_similar_project_or_die( $this->get_arguments()[2] );

		} else {
			$this->climate->error( 'No project was specified.' );
			exit;
		}

		$original_project = clone $project;

		$this->climate->yellow( 'Getting all servers...' );
		$this->get_servers();

		$this->climate->yellow( "\n" . 'Working with ' . $project->get_name() );

		$prod_url = $this->guess_prod_url( $project );
		$project->set_prod_url( $prod_url );
		$this->climate->green( 'Guessing that prod URL is: ' . $prod_url );

		$ip = $this->get_ip( $project );
		if ( ! $this->net_service->is_ip( $ip ) ) {
			$this->climate->red( 'Could not find IP address' );

		}


		$this->climate->green( 'Guessing that IP address is ' . $ip );

		$server = $this->guess_production_server( $ip );

		if ( $server['status'] == 0 ) {
			$provider = $this->net_service->guess_provider_by_ip( $ip );

			if ( $provider ) {
				$this->climate->green( 'Guessing external provider: ' . $provider );
			} else {
				$this->climate->red( 'Not able to guess server. Reason: ' . $server['message'] );
			}
		} else {
			$server = $server['server'];
			$this->climate->green( 'Guessing that server is located at ' . $server->get_provider_name() . ' / ' . $server->get_insightly_name() );
		}

		$this->climate->green()->inline( 'Testing if SSH in Insightly works...' );

		$ssh_failed = false;
		try {
			@$ssh_service = new SSHService( $project );
			$this->climate->green( 'success' );
		} catch ( \Exception $e ) {
			$this->climate->red( 'failed' );
			$ssh_failed = true;

		}

		if ( $ssh_failed ) {
			$guessed_ssh_command = 'ssh root@' . $ip;
			$this->climate->green()->inline( 'Guessing SSH command: ' . $guessed_ssh_command . '...' );
			$project->set_ssh_to_prod( $guessed_ssh_command );
			try {
				@$ssh_service = new SSHService( $project );
				$this->climate->green( 'success' );
				$ssh_failed = false;
			} catch ( \Exception $e ) {
				$this->climate->red( 'failed' );
				$ssh_failed = true;

			}
		}

		if ( $ssh_failed ) {
			$this->climate->red( 'Could not connect to SSH. Giving up.' );
			exit;
		}


		$web_root = $ssh_service->get_web_root();

		if ( $web_root ) {
			$this->climate->green( 'Guessing that web root is ' . $web_root );
		} else {
			$this->climate->red( 'Not able to guess web root.' );

		}

		if ( ! $original_project->get_web_root() && $web_root ) {
			$input = $this->climate->cyan()->input( 'Original web root is empty. Update to the one guessed? (Y/n)' );
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

		if ( ! $original_project->get_ssh_to_prod() ) {
			$input = $this->climate->cyan()->input( 'Original SSH to prod is empty? Update to the one guessed? (Y/n)' );
			$input->accept( [ 'Y', 'N' ] );
			$input->defaultTo( 'Y' );
			$response = $input->prompt();

			if ( strtolower( $response ) == 'y' ) {
				$this->climate->green( 'Saving...' );
				$original_project->set_web_root( $project->get_web_root() );
				$this->insightly_service->save_project( $original_project );
				$this->insightly_service->get_projects();
			}

		}


	}


	/**
	 * Gets all servers from all our service providers.
	 *
	 * @return array
	 */
	private function get_servers(): array {

		if ( ! $this->servers ) {

			$this->rackspace_service = new RackspaceService( RACKSPACE_USERNAME, RACKSPACE_API_KEY );
			$rackspace_servers       = $this->rackspace_service->get_servers();

			$rackspace_load_balancers = $this->rackspace_service->get_load_balancers();

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
	private function guess_prod_url( Project $project ) {
		$urls = [ $project->get_prod_url(), 'https://' . $project->get_name() ];

		foreach ( $urls as $url ) {

			try {
				$response = $this->fetch_url( $url );
			} catch ( \Exception $e ) {
			}

			if ( $response->getStatusCode() == 200 ) {
				return $url;
			}
		}

	}

	/**
	 * Try to find the IP address/host name of the server and save the ssh command.
	 *
	 * @param Project $project
	 */
	private function guess_production_server( $ip ) {

		$server = $this->find_server_by_ip( $ip );

		if ( ! $server ) {
			$reverse_proxy = $this->net_service->find_reverse_proxy( $ip );

			if ( $reverse_proxy ) {

				return [ 'status' => 0, 'message' => 'Found reverse proxy: ' . $reverse_proxy ];

			}


			return [ 'status' => 0, 'message' => 'Could not find server for IP ' . $ip ];
		}

		return [ 'status' => 1, 'server' => $server ];

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
	 * Loops through our list of servers trying to find a server which matches a specific IP address.
	 *
	 * @param string $ip The IP address to find.
	 *
	 * @return mixed
	 */
	private function find_server_by_ip( string $ip ) {
		$climate = $this->get_climate();
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