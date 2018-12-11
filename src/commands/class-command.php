<?php

namespace Dekode\InsightlyCli\Commands;

abstract class Command {
	private $arguments;

	/**
	 * Executes the given command.
	 *
	 * @return mixed
	 */
	abstract public function run();

	/**
	 * @return mixed
	 */
	public function get_arguments() {
		return $this->arguments;
	}

	/**
	 * @param mixed $arguments
	 */
	public function set_arguments( $arguments ) {
		$this->arguments = $arguments;
	}


}
