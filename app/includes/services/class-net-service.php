<?php

namespace Dekode\InsightlyCli\Services;

class NetService {

	/**
	 * Tries to find out whether reverse proxy is Sucuri or Cloudflare.
	 *
	 * @param $ip
	 *
	 * @return bool|string
	 * @throws \Novutec\WhoisParser\Exception\NoQueryException
	 */
	public function find_reverse_proxy( $ip ) {
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
	 * Does some whois lookups to guess who owns the IP address.
	 *
	 * @param $ip
	 *
	 * @return mixed
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	public function guess_provider_by_ip( $ip ) {
		$client = new \GuzzleHttp\Client();
		$res    = $client->request( 'GET', 'http://rest.db.ripe.net/search.json?query-string=' . $ip );
		$body   = json_decode( $res->getBody()->getContents() );

		foreach ( $body->objects->object as $object ) {
			if ( $object->type == 'organisation' ) {
				foreach ( $object->attributes->attribute as $attribute ) {
					if ( $attribute->name == 'org-name' ) {
						return ( $attribute->value );
					}
				}
			}

		}

	}

	/**
	 * Checks if the given string is an IPv4 address
	 *
	 * @param string $ip
	 *
	 * @return boolean
	 */
	public function is_ip( string $ip ): bool {
		return preg_match( '/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/', $ip );
	}

	/**
	 * Tries to infer the web server serving a specific URL.
	 *
	 * @param $url
	 *
	 * @return string
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	public function get_web_server( $url ) {
		$client = new \GuzzleHttp\Client();
		$res    = $client->request( 'GET', $url );
		$server = $res->getHeaders()['Server'];

		return join( '', $server );

	}


}