<?php

namespace Dekode\InsightlyCli\Services;

use Dekode\InsightlyCli\Models\DigitalOceanDroplet;
use GuzzleHttp\Client;
use Namshi\Cuzzle\Formatter\CurlFormatter;


class DigitalOceanService {
	private $api_key;

	/**
	 * InsightlyService constructor.
	 *
	 * @param $api_key
	 */
	public function __construct( $api_key ) {
		$this->set_api_key( $api_key );
	}

	public function get_servers() {
		$result   = $this->make_request( '/droplets' );
		$droplets = [];

		foreach ( $result->droplets as $raw_droplet ) {
			$droplet = new DigitalOceanDroplet();
			$droplet->set_public_ip( $raw_droplet->networks->v4[0]->ip_address );
			$droplet->set_name( $raw_droplet->name );

			$droplets[] = $droplet;

		}

		return $droplets;
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

		$args['headers']['Authorization'] = 'Bearer ' . $this->get_api_key();

		$endpoint = $this->get_api_path() . $endpoint;

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
		return 'https://api.digitalocean.com/v2';
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


}