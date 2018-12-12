<?php

namespace Dekode\InsightlyCli\Models;

class Project {

	private $id;
	private $name;
	private $responsible_advisor;
	private $reverse_proxy;
	private $ssh_to_prod;
	private $prod_server;

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


}