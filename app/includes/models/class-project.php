<?php

namespace Dekode\InsightlyCli\Models;

class Project {

	private $id;
	private $name;
	private $responsible_advisor;
	private $reverse_proxy;
	private $ssh_to_prod;
	private $prod_server;
	private $db_instance;
	private $stage_url;
	private $prod_url;
	private $project_manager;
	private $hosting_notes;

	/**
	 * @return mixed
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * @param mixed $id
	 */
	public function set_id( $id ) {
		$this->id = $id;
	}

	/**
	 * @return mixed
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * @param mixed $name
	 */
	public function set_name( $name ) {
		$this->name = $name;
	}

	/**
	 * @return mixed
	 */
	public function get_responsible_advisor() {
		return $this->responsible_advisor;
	}

	/**
	 * @param mixed $responsible_advisor
	 */
	public function set_responsible_advisor( $responsible_advisor ) {
		$this->responsible_advisor = $responsible_advisor;
	}

	/**
	 * @return mixed
	 */
	public function get_reverse_proxy() {
		return $this->reverse_proxy;
	}

	/**
	 * @param mixed $reverse_proxy
	 */
	public function set_reverse_proxy( $reverse_proxy ) {
		$this->reverse_proxy = $reverse_proxy;
	}

	/**
	 * @return mixed
	 */
	public function get_ssh_to_prod() {
		return $this->ssh_to_prod;
	}

	/**
	 * @param mixed $ssh_to_prod
	 */
	public function set_ssh_to_prod( $ssh_to_prod ) {
		$this->ssh_to_prod = $ssh_to_prod;
	}

	/**
	 * @return mixed
	 */
	public function get_prod_server() {
		return $this->prod_server;
	}

	/**
	 * @param mixed $prod_server
	 */
	public function set_prod_server( $prod_server ) {
		$this->prod_server = $prod_server;
	}

	public function get_url() {
		return "https://crm.na1.insightly.com/details/Project/" . $this->get_id();
	}

	/**
	 * @return mixed
	 */
	public function get_db_instance() {
		return $this->db_instance;
	}

	/**
	 * @param mixed $db_instance
	 */
	public function set_db_instance( $db_instance ) {
		$this->db_instance = $db_instance;
	}

	/**
	 * @return mixed
	 */
	public function get_stage_url() {
		return $this->stage_url;
	}

	/**
	 * @param mixed $stage_url
	 */
	public function set_stage_url( $stage_url ) {
		$this->stage_url = $stage_url;
	}

	/**
	 * @return mixed
	 */
	public function get_prod_url() {
		return $this->prod_url;
	}

	/**
	 * @param mixed $prod_url
	 */
	public function set_prod_url( $prod_url ) {
		$this->prod_url = $prod_url;
	}


	/**
	 * @return mixed
	 */
	public function get_project_manager() {
		return $this->project_manager;
	}

	/**
	 * @param mixed $project_manager
	 */
	public function set_project_manager( $project_manager ) {
		$this->project_manager = $project_manager;
	}

	/**
	 * @return mixed
	 */
	public function get_hosting_notes() {
		return $this->hosting_notes;
	}

	/**
	 * @param mixed $hosting_notes
	 */
	public function set_hosting_notes( $hosting_notes ) {
		$this->hosting_notes = $hosting_notes;
	}

}