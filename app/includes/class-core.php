<?php

namespace Dekode\InsightlyCli;

use Dekode\InsightlyCli\Commands\Command;
use League\CLImate\CLImate;

class Core {
	private $command;
	private $arguments;
	private $commands;

	/**
	 * Core constructor.
	 *
	 * @param array $commands
	 */
	public function __construct( array $commands ) {

		foreach ( $commands as $command ) {
			$sorted_commands[ $command->get_key() ] = $command;
		}

		ksort( $sorted_commands );

		$this->set_commands( $sorted_commands );
	}

	/**
	 * Executes the core.
	 */
	public function execute() {
		if ( $this->get_command() == 'help' || ! $this->get_command() ) {
			$this->display_help();
			exit;
		}

		$command = $this->get_command_object( $this->get_command() );

		if ( ! $command ) {
			print ( "\n\e[31mWe could not find that command.\n" );
			$this->display_help();
			exit;

		}

		$command->set_arguments( $this->get_arguments() );
		$command->run();
	}

	/**
	 * Returns the command object that fit the given command key.
	 *
	 * @param string $command_key
	 *
	 * @return Command
	 */
	private function get_command_object( string $command_key ): ?Command {
		foreach ( $this->get_commands() as $command ) {
			if ( $command->get_key() == $command_key ) {
				return $command;
			}
		}

		return null;
	}

	/**
	 * Displays help.
	 */
	private function display_help() {
		$arguments = $this->get_arguments();
		if ( isset( $arguments[2] ) ) {
			$command = $this->get_command_object( $arguments[2] );

			if ( $command ) {

				echo $command->get_help();
				exit();
			}
		}

		$climate = new CLImate();

		$climate->yellow( 'Available commands' );

		foreach ( $this->get_commands() as $command ) {
			$climate->green()->inline( ' ' . $command->get_key() . "\t\t\t" );
			echo $command->get_description() . "\n";
		}

		echo "\n";
		echo "Get more detailed help on a command by typing\n";
		echo "  isc help <name of command>\n";
		echo "\n";
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

	/**
	 * @return mixed
	 */
	public function get_commands() {
		return $this->commands;
	}

	/**
	 * @param mixed $commands
	 */
	public function set_commands( $commands ) {
		$this->commands = $commands;
	}


}
