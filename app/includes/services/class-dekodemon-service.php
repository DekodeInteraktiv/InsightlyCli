<?php

namespace Dekode\InsightlyCli\Services;

use Dekode\InsightlyCli\Models\Project;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Namshi\Cuzzle\Formatter\CurlFormatter;


/**
 * Class DekodemonService
 * @package Dekode\InsightlyCli\Services
 */
class DekodemonService {
	private $project;

	const YES = 1;
	const NO = 2;
	const CANNOT_DETERMINE = 3;


	/**
	 * Tries to SSH to server and find out if dekodemon plugin in installed.
	 *
	 * @return int
	 * @throws \Exception
	 */
	public function plugin_is_installed() {

		try {
			@$ssh_service = new SSHService( $this->get_project() );
		} catch ( Exception $e ) {
			return $this::CANNOT_DETERMINE;
		}

		$response = $ssh_service->plugin_is_installed( 'dekode-monitoring-tool-client' );

		if ( $response ) {
			return $this::YES;
		} else {
			return $this::NO;
		}


	}

	/**
	 * Checks if the endpoint is active.
	 *
	 * @return bool
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	public function plugin_is_activated() {
		$client = new Client();
		try {
			$res = $client->request( 'GET', $this->get_project()->get_prod_url() . '/dekodemon-rest/report/plugins_report' );
		} catch ( ClientException $e ) {

			$res = $e->getResponse();
		}

		if ( $res->getStatusCode() == 403 ) {
			return $this::YES;
		} else {
			return $this::NO;
		}

	}

	/**
	 * @return mixed
	 */
	public function get_project(): Project {
		return $this->project;
	}

	/**
	 * @param mixed $project
	 */
	public function set_project( Project $project ): void {
		$this->project = $project;
	}


}