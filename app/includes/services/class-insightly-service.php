<?php

namespace Dekode\InsightlyCli\Services;

use Dekode\InsightlyCli\Models\Project;
use GuzzleHttp\Client;
use League\CLImate\CLImate;
use Namshi\Cuzzle\Formatter\CurlFormatter;

class InsightlyService {
	private $api_key;

	/**
	 * InsightlyService constructor.
	 *
	 * @param $api_key
	 */
	public function __construct( $api_key ) {
		$this->set_api_key( $api_key );
	}

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
			$project = $this->convert_insightly_project( $project );

			if ( ! $project->is_terminated() ) {
				$return_value[] = $project;
			}
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
	public function get_project_by_name( string $name ): ?Project {
		$projects = $this->get_projects();
		$name     = strtolower( $name );
		foreach ( $projects as $project ) {

			if ( strtolower( $project->get_name() ) == $name ) {
				return $project;
			}
		}

		return null;
	}

	/**
	 * Searches through all projects and returns an array of projects with at least 50% similarity.
	 *
	 * @param $name
	 *
	 * @return array
	 */
	public function get_projects_by_name_similarity( $name ) {
		$projects     = $this->get_projects();
		$name         = strtolower( $name );
		$similarities = [];
		$return_array = [];

		foreach ( $projects as $index => $project ) {

			similar_text( $name, strtolower( $project->get_name() ), $similarity );

			if ( stripos( $project->get_name(), $name ) !== false ) {
				$similarities[ $index ] = 90;
			} else {
				$similarities[ $index ] = $similarity;

			}
		}

		arsort( $similarities );

		foreach ( $similarities as $index => $similarity ) {
			$return_array[] = $projects[ $index ];
		}

		return $return_array;

	}

	/**
	 * Returns most similar project.
	 *
	 * @param $name
	 *
	 * @return array
	 */
	public function get_most_similar_project( $name ) {
		$projects     = $this->get_projects();
		$name         = strtolower( $name );
		$similarities = [];
		$return_array = [];

		foreach ( $projects as $index => $project ) {

			similar_text( $name, strtolower( $project->get_name() ), $similarity );

			if ( $similarity > 50 ) {
				$similarities[ $index ] = $similarity;
			}

		}

		arsort( $similarities );

		$max_similarity = 0;
		foreach ( $similarities as $index => $similarity ) {

			print( $projects[ $index ]->get_name() . ': ' . $similarity . "\n" );

			if ( $similarity > $max_similarity ) {
				$max_similarity = $similarity;
				$return_array   = [ $projects[ $index ] ];
			} elseif ( $similarity == $max_similarity ) {
				$return_array[] = $projects[ $index ];

			}


		}

		return $return_array;

	}


	/**
	 * Saves the passed project.
	 *
	 * @param Project $project
	 *
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	public function save_project( Project $project ) {
		$body['PROJECT_ID'] = $project->get_id();

		$fields_to_save = [
			'ssh_tpprod__c'    => $project->get_ssh_to_prod(),
			'Prod_url__c'      => $project->get_prod_url(),
			'Stage_url__c'     => $project->get_stage_url(),
			'Prod_server__c'   => $project->get_prod_server(),
			'reverse_proxy__c' => $project->get_reverse_proxy(),
			'Web_root__c'      => $project->get_web_root()
		];

		foreach ( $fields_to_save as $field => $value ) {

			$custom_field                = [];
			$custom_field['FIELD_NAME']  = $field;
			$custom_field['FIELD_VALUE'] = $value;
			$body['CUSTOMFIELDS'][]      = $custom_field;
		}


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
		if ( ! isset( $args['method'] ) ) {
			$args['method'] = 'GET';
		}

		$args['headers']['Authorization'] = 'Basic ' . base64_encode( $this->get_api_key() );

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
						break;
					case 'DBInstans__c':
						$project->set_db_instance( $custom_field->FIELD_VALUE );
						break;
					case 'Stage_url__c':
						$project->set_stage_url( $custom_field->FIELD_VALUE );
						break;
					case 'Project_Manager__c':
						$project->set_project_manager( $custom_field->FIELD_VALUE );
						break;
					case 'Responsible_Advisor__c':
						$project->set_responsible_advisor( $custom_field->FIELD_VALUE );
						break;
					case 'Prod_url__c':
						$project->set_prod_url( $custom_field->FIELD_VALUE );
						break;
					case 'Hosting_note__c':
						$project->set_hosting_notes( $custom_field->FIELD_VALUE );
						break;
					case 'Service_Agreement__c':
						$project->set_service_agreement( $custom_field->FIELD_VALUE );
						break;
					case 'Hosting_level_agreement__c':
						$project->set_hosting_level_agreement( $custom_field->FIELD_VALUE );
						break;
					case 'Incidents_email_report_client__c':
						$project->set_incidents_email_report_client( $custom_field->FIELD_VALUE );
						break;
					case 'Web_root__c':
						$project->set_web_root( $custom_field->FIELD_VALUE );
						break;
					case 'Terminated_project__c':
						$project->set_terminated( $custom_field->FIELD_VALUE );
					default:
						//print( '"' . $custom_field->FIELD_NAME . '"' . " = " . $custom_field->FIELD_VALUE . "\n" );
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
		if ( file_exists( $this->get_projects_cache_file() ) ) {
			unlink( $this->get_projects_cache_file() );
		}

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