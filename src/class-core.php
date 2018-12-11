<?php

namespace Dekode\InsightlyCli;

use Dekode\InsightlyCli\Commands\CommandFactory;

class Core {
	private $command;
	private $arguments;

	/**
	 * Executes the core.
	 */
	public function execute() {
		$command_factory = new CommandFactory();
		$command         = $command_factory->get_command( $this->get_command() );
		$command->set_arguments( $this->get_arguments() );
		$command->run();
	}


	/**
	 * @return mixed
	 */
	public function get_command() {
		return $this->command;
	}

	/**
	 * @param mixed $command
	 */
	public function set_command( $command ) {
		$this->command = $command;
	}

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