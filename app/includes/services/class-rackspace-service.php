<?php

namespace Dekode\InsightlyCli\Services;


use Dekode\InsightlyCli\Models\RackspaceLoadBalancer;
use Dekode\InsightlyCli\Models\RackspaceServer;

class RackspaceService {
	private $username;
	private $api_key;
	private $api_token;
	private $tenant_id;

	/**
	 * InsightlyService constructor.
	 *
	 * @param $api_key
	 */
	public function __construct( $username, $api_key ) {
		$this->set_username( $username );
		$this->set_api_key( $api_key );

		$this->generate_token();
	}

	private function generate_token() {
		$auth_body = [
			'auth' => [
				'RAX-KSKEY:apiKeyCredentials' => [
					"username" => $this->get_username(),
					"apiKey"   => $this->get_api_key()
				]
			]
		];

		$result = $this->make_request( '/tokens', [
			'method'  => 'POST',
			'body'    => json_encode( $auth_body ),
			'headers' => [ 'content-type' => 'application/json' ],
			'url'     => 'https://identity.api.rackspacecloud.com/v2.0'
		] );

		$this->set_api_token( $result->access->token->id );
		$this->set_tenant_id( $result->access->token->tenant->id );

	}

	public function get_servers() {
		$results = $this->make_request( '/servers/detail' );
		$servers = [];


		foreach ( $results->servers as $raw_server ) {

			$server = $this->convert_api_to_server( $raw_server );

			$servers[] = $server;

		}

		return $servers;

	}

	public function get_load_balancers() {
		$url     = 'https://lon.loadbalancers.api.rackspacecloud.com/v1.0/' . $this->get_tenant_id();
		$results = $this->make_request( '/loadbalancers', [ 'url' => $url ] );

		$load_balancers = [];

		foreach ( $results->loadBalancers as $raw_load_balancer ) {
			$load_balancer = new RackspaceLoadBalancer();
			$load_balancer->set_name( $raw_load_balancer->name );

			foreach ( $raw_load_balancer->virtualIps as $ip ) {
				if ( $ip->type == 'PUBLIC' && $ip->ipVersion == 'IPV4' ) {
					break;
				}
			}

			$load_balancer->set_public_ip( $ip->address );

			$results = $this->make_request( '/loadbalancers/' . $raw_load_balancer->id, [ 'url' => $url ] );

			foreach ( $results->loadBalancer->nodes as $node ) {
				$load_balancer->add_node( $node->address );
			}

			$load_balancers[] = $load_balancer;
		}

		return $load_balancers;

	}

	private function convert_api_to_server( $raw_server ) {
		$server = new RackspaceServer();
		$server->set_name( $raw_server->name );

		$server->set_public_ip( $raw_server->accessIPv4 );

		foreach ( $raw_server->addresses->private as $ip_address ) {
			if ( $ip_address->version == 4 ) {
				break;
			}
		}

		$server->set_private_ip( $ip_address->addr );


		return $server;

	}

	/**
	 * Makes the actual request to the API.
	 *
	 * @param       $endpoint
	 * @param array $args
	 *
	 * @return mixed
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	private function make_request( $endpoint, $args = [] ) {
		if ( ! isset( $args['method'] ) ) {
			$args['method'] = 'GET';
		}

		$args['headers']['X-Auth-Token'] = $this->get_api_token();

		if ( isset( $args['url'] ) ) {
			$url = $args['url'];
		} else {
			$url = $this->get_api_path();
		}

		$endpoint = $url . $endpoint;

		$client = new \GuzzleHttp\Client();
		$res    = $client->request( $args['method'], $endpoint, [
			'headers' => $args['headers'],
			'body'    => isset( $args['body'] ) ? $args['body'] : ''
		] );
		$body   = $res->getBody()->getContents();

		return json_decode( $body );
	}

	/**
	 * @return string
	 */
	private function get_api_path() {
		return 'https://lon.servers.api.rackspacecloud.com/v2/' . $this->get_tenant_id();

	}

	/**
	 * @return mixed
	 */
	public function get_api_key() {
		return $this->api_key;
	}

	/**
	 * @param mixed $api_key
	 */
	public function set_api_key( $api_key ) {
		$this->api_key = $api_key;
	}

	/**
	 * @return mixed
	 */
	public function get_username() {
		return $this->username;
	}

	/**
	 * @param mixed $username
	 */
	public function set_username( $username ) {
		$this->username = $username;
	}

	/**
	 * @return mixed
	 */
	public function get_api_token() {
		return $this->api_token;
	}

	/**
	 * @param mixed $api_token
	 */
	public function set_api_token( $api_token ) {
		$this->api_token = $api_token;
	}

	/**
	 * @return mixed
	 */
	public function get_tenant_id() {
		return $this->tenant_id;
	}

	/**
	 * @param mixed $tenant_id
	 */
	public function set_tenant_id( $tenant_id ) {
		$this->tenant_id = $tenant_id;
	}


}