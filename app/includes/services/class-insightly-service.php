<?php

namespace Dekode\InsightlyCli\Services;

use Dekode\InsightlyCli\Models\Project;
use GuzzleHttp\Client;
use Namshi\Cuzzle\Formatter\CurlFormatter;

class InsightlyService {
	private $api_key;

	/**
	 * Returns an array of all projects.
	 *
	 * @return array
	 */
	public function get_projects() {
		$tmp_filename = $this->get_projects_cache_file();
		if ( file_exists( $tmp_filename ) && filemtime( $tmp_filename ) > time() - 60 * 60 ) {
			$projects = unserialize( file_get_contents( $tmp_filename ) );

			return $projects;
		}

		$endpoint = '/Projects?top=99999999';

		$projects = $this->make_request( $endpoint );

		foreach ( $projects as $project ) {
			$return_value[] = $this->convert_insightly_project( $project );
		}

		file_put_contents( $tmp_filename, serialize( $return_value ) );

		return $return_value;


	}

	/**
	 * Returns a project with a given name.
	 *
	 * @param $name
	 *
	 * @return mixed
	 */
	public function get_project_by_name( $name ) {
		$projects = $this->get_projects();
		$name     = strtolower( $name );
		foreach ( $projects as $project ) {

			if ( strtolower( $project->get_name() ) == $name ) {
				return $project;
			}
		}
	}

	public function get_projects_by_name_similarity( $name ) {
		$projects     = $this->get_projects();
		$name         = strtolower( $name );
		$similarities = [];
		foreach ( $projects as $index => $project ) {

			similar_text( $name, strtolower( $project->get_name() ), $similarity );
			if ( $similarity > 50 ) {
				$similarities[ $index ] = $similarity;
			}
		}

		arsort( $similarities );


		foreach ( $similarities as $index => $similarity ) {
			$return_array[] = $projects[ $index ];
		}

		return $return_array;

	}

	public function save_project( Project $project ) {
		$body['PROJECT_ID'] = $project->get_id();

		$custom_field                = [];
		$custom_field['FIELD_NAME']  = 'ssh_tpprod__c';
		$custom_field['FIELD_VALUE'] = $project->get_ssh_to_prod();
		$body['CUSTOMFIELDS'][]      = $custom_field;

		$endpoint = '/Projects';

		$args['headers']['Content-type'] = 'application/json';
		$args['method']                  = 'PUT';
		$args['body']                    = json_encode( $body, JSON_PRETTY_PRINT );

		$result = $this->make_request( $endpoint, $args );

		$this->clear_cache();
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
		if ( ! $args['method'] ) {
			$args['method'] = 'GET';
		}

		$args['headers']['Authorization'] = 'Basic ' . base64_encode( $this->get_api_key() );

		$endpoint = $this->get_api_path() . $endpoint;

		$client = new \GuzzleHttp\Client();
		$res    = $client->request( $args['method'], $endpoint, [
			'headers' => $args['headers'],
			'body'    => $args['body']
		] );
		$body   = $res->getBody()->getContents();

		return json_decode( $body );
	}

	/**
	 * Converts the output from the Insightly API to a Project object.
	 *
	 * @param \stdClass $insightly_project
	 *
	 * @return Project
	 */
	public function convert_insightly_project( \stdClass $insightly_project ): Project {
		$project = new Project();
		$project->set_id( $insightly_project->PROJECT_ID );
		$project->set_name( $insightly_project->PROJECT_NAME );

		foreach ( $insightly_project->CUSTOMFIELDS as $custom_field ) {
			if ( $custom_field->FIELD_VALUE ) {
				switch ( $custom_field->FIELD_NAME ) {
					case 'reverse_proxy__c':
						$project->set_reverse_proxy( $custom_field->FIELD_VALUE );
						break;
					case 'ssh_tpprod__c':
						$project->set_ssh_to_prod( $custom_field->FIELD_VALUE );
						break;
					case 'Prod_server__c':
						$project->set_prod_server( $custom_field->FIELD_VALUE );
					default:
						//print( $custom_field->FIELD_NAME . "\n" );
						break;
				}


			}
		}

		return $project;
	}


	/**
	 * Deletes all cache files
	 */
	public function clear_cache() {
		unlink( $this->get_projects_cache_file() );

	}

	/**
	 * Returns the path of the cache file for projects.
	 *
	 * @return string
	 */
	private function get_projects_cache_file() {
		return sys_get_temp_dir() . '/isc-projects.cache';
	}

	/**
	 * @return string
	 */
	private function get_api_path() {
		return 'https://api.insightly.com/v3.0';
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