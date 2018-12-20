<?php

namespace Dekode\InsightlyCli\Commands;

use Dekode\InsightlyCli\Services\InsightlyService;
use Dekode\InsightlyCli\Services\SSHService;

class DumpDB extends Command {

	/**
	 * Returns the string used to run this command.
	 *
	 * @return string
	 */
	public function get_key(): string {
		return 'dump-db';
	}

	/**
	 * Returns a short description for this command-
	 *
	 * @return string
	 */
	public function get_description(): string {
		$climate = $this->get_climate();

		$climate->inline( 'Will give you the commands you need to run to get a database dump either for yourself or someone else.' );

		return '';


	}

	/**
	 * Returns the help text for this command.
	 *
	 * @return string
	 */
	public function get_help(): string {
		$climate = $this->get_climate();
		$climate->green( "Usage:\nisc " . $this->get_key() . " <name of project>" );
		$climate->output();
		$climate->cyan( "Flags:" );
		$climate->green()->inline( "   --share\t" );
		$climate->yellow( 'Puts the dump in the web root and gives you a link you can send to another developer.' );

		return '';

	}

	/**
	 * Executes this command
	 */
	public function run() {
		$this->climate = $this->get_climate();

		$this->insightly_service = new InsightlyService( INSIGHTLY_API_KEY );

		$arguments = $this->get_arguments();
		if ( isset( $arguments[2] ) ) {
			$project = $this->insightly_service->get_project_by_name( $arguments[2] );
			if ( ! $project ) {
				$this->climate->error( 'Could not find that project.' );
				$this->climate->output();
				$this->show_similar_projects( $arguments[2] );
				exit;
			}

		} else {
			$this->climate->error( 'No project was specified.' );
			exit;
		}

		$ssh_service = new SSHService( $project );

		$config = $ssh_service->get_db_credentials();

		$required_fields = [
			'DB_HOST',
			'DB_USER',
			'DB_PASSWORD',
			'DB_NAME'
		];

		foreach ( $required_fields as $field ) {

			if ( ! isset( $config[ $field ] ) ) {
				throw new \Exception( $field . ' value not found in config file' );
			}
		}

		$climate = $this->get_climate();
		$climate->yellow( 'Carefully check these commands and then run them from your prompt:' );

		$arguments = $this->get_arguments();

		$uploads_folder = $ssh_service->get_uploads_folder();
		$web_root       = $ssh_service->get_web_root();

		if ( array_key_exists( 'share', $arguments ) ) {
			$filename = $this->generate_random_string( 32 ) . ".sql";
			$climate->green( $project->get_ssh_to_prod() . " 'mysqldump -h " . $config['DB_HOST'] . ' -u ' . $config['DB_USER'] . ' -p' . $config['DB_PASSWORD'] . ' ' . $config['DB_NAME'] . " > " . $uploads_folder . '/' . $filename . "';" );

			$php_code = '
			<?php 
			if ( !file_exists( "' . $uploads_folder . '/' . $filename . '" ) ) {
				echo "Dump already gone.";
			} else {
				unlink( "' . $uploads_folder . '/' . $filename . '" ); 
				
				var_dump( error_get_last() );
				echo "<br>";
			
				echo "If you see no errors above, the dump has been deleted.";
			} 
			?>';


			$php_code = addslashes( str_replace( "\n", '', str_replace( "\t", '', $php_code ) ) );

			$climate->green( $project->get_ssh_to_prod() . " 'echo \"" . $php_code . '" > ' . $web_root . '/delete_dump.php\';' );
			$climate->output();

			$climate->yellow( 'When the  commands have been run, send this message to the person receiving the dump:' );
			$climate->green( 'Hi! Your dump is ready and can be downloaded at ' . $ssh_service->get_uploads_url() . '/' . $filename . '. When you have downloaded it, please go to ' . $project->get_prod_url() . '/delete_dump.php to delete it.' );

		} else {

			$climate->green( $project->get_ssh_to_prod() . " 'mysqldump -h " . $config['DB_HOST'] . ' -u ' . $config['DB_USER'] . ' -p' . $config['DB_PASSWORD'] . ' ' . $config['DB_NAME'] . " > ~/" . $project->get_prod_domain() . '.sql\';' );

			$ssh_username_and_host = trim( str_replace( 'ssh', '', $project->get_ssh_to_prod() ) );

			$climate->green( 'scp -C ' . $ssh_username_and_host . ":~/" . $project->get_prod_domain() . '.sql .;' );
			$climate->green( $project->get_ssh_to_prod() . " 'rm ~/" . $project->get_prod_domain() . '.sql\';' );
		}

	}

	private function generate_random_string( $length ) {
		$characters        = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$characters_length = strlen( $characters );
		$random_string     = '';
		for ( $i = 0; $i < $length; $i ++ ) {
			$random_string .= $characters[ rand( 0, $characters_length - 1 ) ];
		}

		return $random_string;
	}


}