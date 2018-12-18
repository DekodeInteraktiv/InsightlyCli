<?php

namespace Dekode\InsightlyCli\Models;

abstract class Server {
	private $public_ip;
	private $private_ip;
	private $name;
	private $id;

	abstract function get_insightly_name();

	/**
	 * @return mixed
	 */
	public function get_public_ip() {
		return $this->public_ip;
	}

	/**
	 * @param mixed $public_ip
	 */
	public function set_public_ip( $public_ip ) {
		$this->public_ip = $public_ip;
	}

	/**
	 * @return mixed
	 */
	public function get_private_ip() {
		return $this->private_ip;
	}

	/**
	 * @param mixed $private_ip
	 */
	public function set_private_ip( $private_ip ) {
		$this->private_ip = $private_ip;
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
	public function get_id() {
		return $this->id;
	}

	/**
	 * @param mixed $id
	 */
	public function set_id( $id ) {
		$this->id = $id;
	}


}