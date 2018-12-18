<?php

namespace Dekode\InsightlyCli\Services;

class NetService {

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
	 * Checks if the given string is an IPv4 address
	 *
	 * @param string $ip
	 *
	 * @return boolean
	 */
	public function is_ip( string $ip ): bool {
		return preg_match( '/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/', $ip );
	}


}