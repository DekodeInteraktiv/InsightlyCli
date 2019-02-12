<?php

namespace Dekode\InsightlyCli\Commands;

use Dekode\InsightlyCli\Services\NetService;
use Dekode\InsightlyCli\Services\ServerService;
use Dekode\InsightlyCli\Services\SSHService;

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

		$ssh_service = new SSHService( $project );

		$memory_usage = $ssh_service->get_memory_usage();

		foreach ( $memory_usage as $key => $memory_type ) {
			$memory['Type']            = $key;
			$memory['Total']           = $memory_type['total_human'];
			$memory['Used']            = $memory_type['used_human'];
			$memory['Percentage used'] = round( $memory_type['used'] / $memory_type['total'] * 100 ) . '%';

			$memory_for_output[] = $memory;

		}

		$climate->table( $memory_for_output );

		$cpu_load = $ssh_service->get_cpu_load();

		$climate->green( "\n" . 'CPU is ' . $cpu_load['idle'] . '% idle.' );

		$disks = $ssh_service->get_disk_space();

		foreach ( $disks as $index => &$disk ) {
			if ( $disk['Mounted'] != '/' && strpos( $disk['Mounted'], 'mnt' ) === false ) {
				unset( $disks[ $index ] );
				continue;
			}

			unset( $disk['1B-blocks'] );

			$disk['Used']      = $ssh_service->human_filesize( $disk['Used'] );
			$disk['Available'] = $ssh_service->human_filesize( $disk['Available'] );

			$use = str_replace( '%', '', $disk['Use%'] );

			$color = 'green';

			if ( $use > 50 ) {
				$color = 'yellow';
			}

			if ( $use > 90 ) {
				$color = 'red';
			}

			$disk['Use%'] = '<' . $color . '>' . $disk['Use%'] . '</' . $color . '>';


		}
		$climate->table( $disks );


	}
}