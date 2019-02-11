<?php

namespace Dekode\InsightlyCli\Commands;

use Dekode\InsightlyCli\Services\InsightlyService;
use Dekode\InsightlyCli\Services\SSHService;

class Users extends Command {

	/**
	 * Returns the string used to run this command.
	 *
	 * @return string
	 */
	public function get_key(): string {
		return 'users';
	}

	/**
	 * Returns a short description for this command-
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Lists all users on this site.';
	}

	/**
	 * Returns the help text for this command.
	 *
	 * @return string
	 */
	public function get_help(): string {
		$help = "Usage:\nisc admins <name of project>\n\n";

		return $help;

	}

	/**
	 * Executes this command
	 */
	public function run() {
		$project   = $this->get_most_similar_project_or_die( $this->get_arguments()[2] );
		$climate   = $this->get_climate();
		$arguments = $this->get_arguments();

		$climate->green( 'Found ' . $project->get_name() );

		$ssh_service = new SSHService( $project );
		$users       = $ssh_service->get_wp_users();

		foreach ( $users as $index => &$user ) {
			unset( $user['ID'] );
			unset( $user['user_registered'] );

			if ( isset( $arguments['role'] ) ) {
				if ( strpos( $user['roles'], $arguments['role'] ) === false ) {
					unset( $users[ $index ] );
				}
			}
		}

		$climate->table( $users );

	}
}