<?php

namespace Dekode\InsightlyCli\Services;

use Dekode\InsightlyCli\Services\RackspaceService;


class ServerService {
	private $servers;

	/**
	 * Try to find the IP address/host name of the server and save the ssh command.
	 *
	 * @param Project $project
	 */
	public function guess_production_server( $ip ) {

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
	 * Loops through our list of servers trying to find a server which matches a specific IP address.
	 *
	 * @param string $ip The IP address to find.
	 *
	 * @return mixed
	 */
	public function find_server_by_ip( string $ip ) {
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
	 * Gets all servers from all our service providers.
	 *
	 * @return array
	 */
	private function get_servers(): array {

		if ( ! $this->servers ) {

			$rackspace_servers        = [];
			$rackspace_load_balancers = [];
			$digital_ocean_servers    = [];

			if ( defined( 'RACKSPACE_USERNAME' ) && RACKSPACE_USERNAME && defined( 'RACKSPACE_API_KEY' ) && RACKSPACE_API_KEY ) {

				$rackspace_service        = new RackspaceService( RACKSPACE_USERNAME, RACKSPACE_API_KEY );
				$rackspace_load_balancers = $rackspace_service->get_load_balancers();
				$rackspace_servers        = $rackspace_service->get_servers();
			}

			if ( defined( 'DIGITAL_OCEAN_API_KEY' ) && DIGITAL_OCEAN_API_KEY ) {
				$digital_ocean_service = new DigitalOceanService( DIGITAL_OCEAN_API_KEY );
				$digital_ocean_servers = $digital_ocean_service->get_servers();
			}

			$servers = array_merge( $rackspace_servers, $digital_ocean_servers, $rackspace_load_balancers );

			$this->servers = $servers;

		}

		return $this->servers;


	}


}