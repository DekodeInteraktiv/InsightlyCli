<?php

namespace Dekode\InsightlyCli\Commands;

use Dekode\InsightlyCli\Services\NetService;
use Dekode\InsightlyCli\Services\ServerService;

class CheckLoad extends Command {

	/**
	 * Returns the string used to run this command.
	 *
	 * @return string
	 */
	public function get_key(): string {
		return 'check-load';
	}

	/**
	 * Returns a short description for this command-
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Checks the load on the given site';
	}

	/**
	 * Returns the help text for this command.
	 *
	 * @return string
	 */
	public function get_help(): string {
		$help = "Usage:\nisc check-load <name of project>\n\n";

		return $help;

	}

	/**
	 * Executes this command
	 */
	public function run() {
		$project = $this->get_most_similar_project_or_die( $this->get_arguments()[2] );
		$climate = $this->get_climate();

		$climate->green( 'Found ' . $project->get_name() );

		$server_service = new ServerService();
		$net_service    = new NetService();

		$ssh_command = $project->get_ssh_to_prod();

		list( $tmp, $host ) = explode( '@', $ssh_command );

		if ( ! $net_service->is_ip( $host ) ) {
			$ip = gethostbyname( $host );
		} else {
			$ip = $host;
		}

		$server = $server_service->find_server_by_ip( $ip );

		print_r( $server );

	}
}