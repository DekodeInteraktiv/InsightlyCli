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
	 * Returns the string used to run this command.
	 *
	 * @return string
	 */
	abstract public function get_key(): string;

	/**
	 * Returns a short description for this command-
	 *
	 * @return string
	 */
	abstract public function get_description(): string;

	/**
	 * Returns the help text for this command.
	 *
	 * @return string
	 */
	abstract public function get_help(): string;


	/**
	 * @return array
	 */
	public function get_arguments(): array {
		return $this->arguments;
	}

	/**
	 * @param array $arguments
	 */
	public function set_arguments( array $arguments ) {
		$this->arguments = $arguments;
	}


}
